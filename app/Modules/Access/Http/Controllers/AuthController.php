<?php

namespace App\Modules\Access\Http\Controllers;

use App\Modules\Access\Http\Requests\LoginRequest;
use App\Modules\Access\Http\Resources\UserResource;
use App\Modules\Access\Repositories\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Token sign-in for the JSON API (Sanctum). */
class AuthController
{
    public function login(LoginRequest $request, UserRepository $users): JsonResponse
    {
        $user = $users->activeByEmail($request->validated('email'));

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages(['email' => 'These details do not match an active user.']);
        }

        return response()->json([
            'token' => $user->createToken('portal')->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(status: 204);
    }
}
