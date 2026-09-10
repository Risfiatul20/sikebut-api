<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::with(['skpd', 'subKegiatan.kegiatan.program.bidangUrusan'])
            ->where('username', $validated['username'])
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'username' => [__('auth.failed')],
            ]);
        }

        $tokenName = $validated['device_name'] ?? 'auth_token';
        $token = $user->createToken($tokenName, [$user->role])->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token_type' => 'Bearer',
            'access_token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Get the authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['skpd', 'subKegiatan.kegiatan.program.bidangUrusan']);

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Perbarui profil akun sendiri (khusus No. WhatsApp untuk notifikasi WA).
     */
    public function updateMe(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'no_hp' => 'nullable|string|max:50',
        ]);

        $info = is_array($user->info) ? $user->info : [];
        $info['no_hp'] = $validated['no_hp'] ?? '';
        $user->update(['info' => $info]);

        $user->loadMissing(['skpd', 'subKegiatan.kegiatan.program.bidangUrusan']);

        return response()->json([
            'message' => 'Profil diperbarui',
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Log the user out (revoke current token).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout successful',
        ]);
    }
}
