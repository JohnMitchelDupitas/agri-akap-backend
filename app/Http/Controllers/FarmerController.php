<?php

namespace App\Http\Controllers;

use App\Imports\FarmersImport;
use App\Models\Farmer;
use App\Http\Requests\StoreFarmerRequest;
use App\Services\SmsService;
use App\Traits\DecodesBase64Image;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class FarmerController extends Controller
{
    use DecodesBase64Image;

    public function __construct(private SmsService $sms)
    {
    }

    /**
     * Retrieve paginated RSBSA farmer registry with role-based scoping.
     *
     * - admin: all farmers
     * - barangay_official / barangay: only assigned_barangay
     * - technician: all farmers; search optimized for rsbsa_no / surname
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user?->role;
        $searchQuery = trim((string) $request->query('search', ''));
        $barangay = $request->query('barangay');
        $commodity = trim((string) $request->query('commodity', ''));

        $query = Farmer::withCount('farmPlots');

        if (in_array($role, ['barangay_official', 'barangay'], true)) {
            $assigned = $user->assigned_barangay;
            if (empty($assigned)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No barangay assignment on this account.',
                ], 403);
            }
            $query->where('permanent_brgy', $assigned);
        } elseif ($role === 'admin') {
            // Full registry — optional barangay filter still allowed
            $query->when($barangay, fn ($q, $b) => $q->where('permanent_brgy', $b));
        } elseif ($role === 'technician') {
            // Field search across Echague; keep barangay filter optional
            $query->when($barangay, fn ($q, $b) => $q->where('permanent_brgy', $b));
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to view the farmer registry.',
            ], 403);
        }

        // Barangay crop forms: only farmers with a matching farm-plot commodity
        if ($commodity !== '') {
            $query->whereHas('farmPlots', function ($q) use ($commodity) {
                $q->whereRaw('LOWER(commodity) = ?', [Str::lower($commodity)]);
            });
        }

        if ($searchQuery !== '') {
            if ($role === 'technician') {
                // Optimized field lookup: RSBSA number or last name (surname)
                $term = '%'.$searchQuery.'%';
                $query->where(function ($q) use ($term, $searchQuery) {
                    $q->where('rsbsa_no', 'like', $term)
                        ->orWhere('surname', 'like', $term)
                        ->orWhere('first_name', 'like', $term);

                    // Exact RSBSA match first-class for QR / typed IDs
                    if (strlen($searchQuery) >= 5) {
                        $q->orWhere('rsbsa_no', $searchQuery);
                    }
                });
            } else {
                $query->search($searchQuery);
            }
        }

        $farmers = $query->orderBy('surname', 'asc')->paginate(15);

        return response()->json([
            'status' => 'success',
            'message' => 'Farmers registry retrieved.',
            'data' => $farmers,
        ]);
    }

    /**
     * Bulk import the official RSBSA Excel masterlist (admin only).
     * Upserts by rsbsa_no so re-uploads update existing farmers.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            $import = new FarmersImport;
            Excel::import($import, $request->file('excel_file'));

            return response()->json([
                'status' => 'success',
                'message' => 'RSBSA masterlist imported successfully.',
                'data' => [
                    'created' => $import->created,
                    'updated' => $import->updated,
                    'skipped' => $import->skipped,
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Farmer Excel import failed: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Import failed. Check the file format and try again.',
                'error' => app()->isLocal() ? $e->getMessage() : null,
            ], 500);
        }
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

            // Empty RSBSA becomes null via ConvertEmptyStringsToNull — assign one
            // so new enrollments are never saved without an RSBSA reference number.
            if (empty($validatedData['rsbsa_no'])) {
                $validatedData['rsbsa_no'] = $this->generateUniqueRsbsaNo();
            }

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

    /**
     * Local Echague RSBSA reference: IV-02-0423-{YEAR}-{SEQ}.
     * Matches FarmerSeeder convention; sequential and unique (incl. soft-deleted).
     */
    protected function generateUniqueRsbsaNo(): string
    {
        $prefix = 'IV-02-0423-'.now()->year.'-';

        $maxSeq = (int) Farmer::withTrashed()
            ->where('rsbsa_no', 'like', $prefix.'%')
            ->lockForUpdate()
            ->selectRaw('MAX(CAST(SUBSTRING_INDEX(rsbsa_no, "-", -1) AS UNSIGNED)) as max_seq')
            ->value('max_seq');

        $next = $maxSeq + 1;

        do {
            $candidate = $prefix.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
            $exists = Farmer::withTrashed()->where('rsbsa_no', $candidate)->exists();
            $next++;
        } while ($exists);

        return $candidate;
    }
}
