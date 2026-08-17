<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Customer authentication for the storefront.
 *
 * Sanctum personal access tokens, not sessions — the storefront is a
 * separate origin and the admin panel keeps its own session-based login.
 * The two never mix: admins are rejected here.
 */
class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'password' => Hash::make($request->string('password')),
            'phone' => $request->input('phone'),
            'is_active' => true,
        ]);

        $user->assignRole(Role::Customer->value);

        return response()->json([
            'token' => $this->issueToken($user, $request),
            'user' => $this->profile($user),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            // One message for both cases, so this cannot be used to probe
            // which addresses are registered.
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => __('This account has been deactivated.'),
            ]);
        }

        // Staff sign in through the admin panel, which is session-based.
        if ($user->isAdmin()) {
            throw ValidationException::withMessages([
                'email' => __('Please sign in through the admin panel.'),
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'token' => $this->issueToken($user, $request),
            'user' => $this->profile($user),
        ]);
    }

    /** Revokes only the token that made this request. */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    /** Revokes every token — "sign out everywhere". */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Signed out on all devices.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->profile($request->user())]);
    }

    private function issueToken(User $user, Request $request): string
    {
        $device = $request->input('device_name') ?: 'storefront';

        return $user->createToken($device, ['customer'])->plainTextToken;
    }

    private function profile(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $user->avatar,
        ];
    }
}
