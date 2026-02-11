<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateStoreAction;
use App\Actions\GetNearByStoresAction;
use App\Http\Requests\CreateStoreRequest;
use App\Http\Requests\NearByStoresRequest;
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

    public function nearbyStores(NearByStoresRequest $request, GetNearByStoresAction $action): JsonResponse
    {
        $result = $action->handle($request->validated());
        $paginator = $result['stores'];

        return response()->json([
            'success' => true,
            'data' => [
                'search_location' => $result['search_location'],
                'stores' => StoreResource::collection($paginator),
                'pagination' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ]);
    }
}
