<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreRequest;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

final class StoreController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', Store::class);

        return StoreResource::collection(Store::all());
    }

    public function store(StoreRequest $request)
    {
        $this->authorize('create', Store::class);

        return new StoreResource(Store::create($request->validated()));
    }

    public function show(Store $store)
    {
        $this->authorize('view', $store);

        return new StoreResource($store);
    }

    public function update(StoreRequest $request, Store $store)
    {
        $this->authorize('update', $store);

        $store->update($request->validated());

        return new StoreResource($store);
    }

    public function destroy(Store $store)
    {
        $this->authorize('delete', $store);

        $store->delete();

        return response()->json();
    }
}
