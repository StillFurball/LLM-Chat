<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Exchange an email and password for a Sanctum API token.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::once($credentials)) {
            return response()->json(['status' => 'error', 'message' => 'Email atau password salah.'], 401);
        }

        return response()->json([
            'access_token' => Auth::user()->createToken('api')->plainTextToken,
            'token_type' => 'Bearer',
        ]);
    }
}
