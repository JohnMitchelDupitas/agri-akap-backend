<?php

namespace App\Http\Controllers;

use App\Models\Farmer;
use App\Http\Requests\StoreFarmerRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FarmerController extends Controller
{
    /**
     * Retrieve paginated RSBSA farmer registry with search capabilities.
     */
    public function index(Request $request): JsonResponse
    {
        // 1. Extract the search term from the ?search= parameter
        $searchQuery = $request->query('search');

        // 2. Build the query efficiently
        $farmers = Farmer::withCount('farmPlots')
            // 3. Apply the search scope ONLY if $searchQuery has a value
            ->when($searchQuery, function ($query, $searchQuery) {
                return $query->search($searchQuery);
            })
            ->orderBy('surname', 'asc')
            ->paginate(15);

        return response()->json([
            'status' => 'success',
            'message' => 'Farmers registry retrieved.',
            'data' => $farmers
        ]);
    }

    /**
     * Store an RSBSA-compliant farmer profile alongside nested farm plots.
     */
    public function store(StoreFarmerRequest $request): JsonResponse
    {
        // Leverage strict database transactions for atomicity
        DB::beginTransaction();

        try {
            // 1. Generate local unique fallback QR hash if not yet provided by frontend
            $validatedData = $request->validated();
            $validatedData['qr_code_hash'] = (string) Str::uuid();

            // 2. Persist Farmer Identity Profile
            $farmer = Farmer::create($validatedData);

            // 3. Loop through and persist child farm plots via the relationship link
            foreach ($request->input('plots') as $plotData) {
                $farmer->farmPlots()->create($plotData);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Farmer and corresponding parcel logs enrolled successfully.',
                'data' => $farmer->load('farmPlots')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Database transaction failed. Record aborted.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
