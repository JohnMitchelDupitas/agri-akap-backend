<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Authenticate user, generate Sanctum token, and return RBAC profile.
     */
    public function login(Request $request)
    {
        // 1. Validate incoming request
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required|string|max:255', // e.g., "Technician's Galaxy Tab A"
        ]);

        // 2. Fetch user
        $user = User::where('email', $request->email)->first();

        // 3. Verify credentials and active status
        if (! $user || ! Hash::check($request->password, $user->password)) {
            // We use a generic message to prevent user enumeration attacks
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials.'
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Account is deactivated. Please contact the MAO Administrator.'
            ], 403);
        }

        // 4. Generate Sanctum Token
        // The token is hashed in the database; the plain text is only shown once here.
        $token = $user->createToken($request->device_name)->plainTextToken;

        // 5. Return standardized API contract
        return response()->json([
            'status' => 'success',
            'message' => 'Login successful.',
            'data' => [
                'access_token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role, // Frontend uses this for RBAC routing
                ]
            ]
        ], 200);
    }

    /**
     * Revoke the current access token.
     */
    public function logout(Request $request)
    {
        // Revokes the specific token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully.',
            'data' => null
        ], 200);
    }

    /**
     * Fetch the currently authenticated user's profile.
     * Useful for restoring frontend session on app reload.
     */
    public function me(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Profile retrieved.',
            'data' => [
                'user' => $request->user()->only(['id', 'name', 'email', 'role'])
            ]
        ], 200);
    }
}
