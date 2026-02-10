<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\LoginUserAction;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginUserAction $action): JsonResponse
    {
        $result = $action->handle($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'type' => 'Bearer',
                'token' => $result['token'],
                'user' => UserResource::make($result['user']),
            ],
        ], Response::HTTP_OK);
    }

    public function logout()
    {
        Auth::user()->currentAccessToken()->delete();

        Auth::logout();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful',
        ], Response::HTTP_OK);
    }
}
