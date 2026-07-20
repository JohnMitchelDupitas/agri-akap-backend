<?php

namespace App\Http\Controllers;

use App\Models\Farmer;
use App\Http\Requests\StoreFarmerRequest;
use App\Services\SmsService;
use App\Traits\DecodesBase64Image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FarmerController extends Controller
{
    use DecodesBase64Image;

    public function __construct(private SmsService $sms)
    {
    }

    /**
     * Retrieve paginated RSBSA farmer registry with search capabilities.
     */
    public function index(Request $request): JsonResponse
    {
        $searchQuery = $request->query('search');
        $barangay = $request->query('barangay');

        $farmers = Farmer::withCount('farmPlots')
            ->when($searchQuery, fn ($q, $s) => $q->search($s))
            ->when($barangay, fn ($q, $b) => $q->where('permanent_brgy', $b))
            ->orderBy('surname', 'asc')
            ->paginate(15);

        return response()->json([
            'status' => 'success',
            'message' => 'Farmers registry retrieved.',
            'data' => $farmers,
        ]);
    }

    /**
     * Show a single farmer profile with their farm plots and distributions.
     */
    public function show(string $id): JsonResponse
    {
        $farmer = Farmer::with([
            'farmPlots',
            'distributions.program:id,name,unit_of_measurement,type',
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Farmer profile retrieved.',
            'data' => $farmer,
        ], 200);
    }

    /**
     * Resolve a scanned QR value to a farmer and their plots.
     * The QR encodes the farmer UUID (see IdIssuancePage); we also fall back
     * to matching the qr_code_hash for forward compatibility.
     */
    public function lookup(Request $request): JsonResponse
    {
        $request->validate(['qr' => 'required|string']);

        $qr = trim($request->query('qr'));

        $farmer = Farmer::with('farmPlots')
            ->where(function ($q) use ($qr) {
                $q->where('id', $qr)->orWhere('qr_code_hash', $qr);
            })
            ->first();

        if (!$farmer) {
            return response()->json([
                'status' => 'error',
                'message' => 'No registered farmer matches this QR code.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Farmer identified.',
            'data' => $farmer,
        ], 200);
    }

    /**
     * Return distinct barangay names for filter dropdowns.
     */
    public function barangays(): JsonResponse
    {
        $barangays = Farmer::distinct()
            ->orderBy('permanent_brgy')
            ->pluck('permanent_brgy')
            ->filter()
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => $barangays,
        ]);
    }

    /**
     * Return distinct commodities from farm plots for filter dropdowns.
     */
    public function commodities(): JsonResponse
    {
        $commodities = \App\Models\FarmPlot::distinct()
            ->orderBy('commodity')
            ->pluck('commodity')
            ->filter()
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => $commodities,
        ]);
    }

    /**
     * Upload/update a farmer's photo (base64 from the admin ID issuance UI).
     */
    public function uploadPhoto(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'photo_base64' => 'required|string',
        ]);

        $farmer = Farmer::findOrFail($id);
        $path = $this->storeBase64Image($request->input('photo_base64'), 'farmer-photos');

        if ($path === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Photo could not be decoded. Please recapture.',
            ], 422);
        }

        $farmer->update(['photo_path' => $path]);

        return response()->json([
            'status' => 'success',
            'message' => 'Farmer photo saved.',
            'data' => ['photo_url' => asset('storage/' . $path)],
        ]);
    }

    /**
     * Store an RSBSA-compliant farmer profile alongside nested farm plots.
     */
    public function store(StoreFarmerRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        // Prevent duplicate intake: reject when an existing farmer already
        // matches the same surname + first name + birthdate (case-insensitive).
        $duplicate = Farmer::whereRaw('LOWER(surname) = ?', [Str::lower($validatedData['surname'])])
            ->whereRaw('LOWER(first_name) = ?', [Str::lower($validatedData['first_name'])])
            ->whereDate('birthdate', $validatedData['birthdate'])
            ->first();

        if ($duplicate) {
            return response()->json([
                'status' => 'error',
                'message' => 'A farmer with the same name and birthdate is already registered.',
                'errors' => [
                    'surname' => ['A matching RSBSA record already exists for this person.'],
                ],
            ], 422);
        }

        DB::beginTransaction();

        try {
            $validatedData['qr_code_hash'] = (string) Str::uuid();

            // Handle optional farmer photo captured during enrollment.
            if ($request->filled('photo_base64')) {
                $path = $this->storeBase64Image($request->input('photo_base64'), 'farmer-photos');
                if ($path) {
                    $validatedData['photo_path'] = $path;
                }
            }

            $plots = $validatedData['plots'] ?? [];
            unset($validatedData['plots']);

            $farmer = Farmer::create($validatedData);

            foreach ($plots as $plotData) {
                $farmer->farmPlots()->create($plotData);
            }

            DB::commit();

            // Step 4 — instant SMS receipt. Best-effort: never blocks enrollment.
            $this->sendEnrollmentReceipt($farmer);

            return response()->json([
                'status' => 'success',
                'message' => 'Farmer and corresponding parcel logs enrolled successfully.',
                'data' => $farmer->load('farmPlots'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Database transaction failed. Record aborted.',
                'error' => app()->isLocal() ? $e->getMessage() : 'Please contact support.',
            ], 500);
        }
    }

    /**
     * Fire an instant SMS receipt to a freshly enrolled farmer. Wrapped in a
     * try/catch so any gateway failure is logged but never breaks enrollment.
     */
    protected function sendEnrollmentReceipt(Farmer $farmer): void
    {
        if (empty($farmer->mobile_number)) {
            return;
        }

        try {
            $reference = $farmer->rsbsa_no ?: $farmer->transaction_code;
            $name = trim($farmer->first_name . ' ' . $farmer->surname);

            $message = "AGRI-AKAP: Hi {$name}, your RSBSA enrollment is received. "
                . "Reference: {$reference}. Present your QR ID at the MAO for programs and claims.";

            $this->sms->send($farmer->mobile_number, $message);
        } catch (\Throwable $e) {
            Log::warning('Enrollment SMS receipt failed: ' . $e->getMessage());
        }
    }
}
