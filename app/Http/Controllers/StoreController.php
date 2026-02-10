<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateStoreAction;
use App\Http\Requests\CreateStoreRequest;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class StoreController extends Controller
{
    use AuthorizesRequests;

    public function store(CreateStoreRequest $request, CreateStoreAction $action): JsonResponse
    {
        $this->authorize('create', Store::class);

        $store = $action->handle($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Store created successfully',
            'data' => StoreResource::make($store),
        ], Response::HTTP_CREATED);
    }
}
