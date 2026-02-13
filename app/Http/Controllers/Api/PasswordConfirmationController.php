<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;

class PasswordConfirmationController
{
    /**
     * Get the password confirmation status for the current session.
     */
    public function status(Request $request): JsonResponse
    {
        $lastConfirmation = $request->session()->get('auth.password_confirmed_at', 0);
        $elapsed = Date::now()->unix() - $lastConfirmation;
        $timeout = config('auth.password_timeout', 10800);

        return response()->json([
            'confirmed' => $elapsed < $timeout,
        ]);
    }

    /**
     * Confirm the user's password.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'errors' => [
                    'password' => [__('The provided password was incorrect.')],
                ],
            ], 422);
        }

        $request->session()->put('auth.password_confirmed_at', Date::now()->unix());

        return response()->json([], 201);
    }
}
