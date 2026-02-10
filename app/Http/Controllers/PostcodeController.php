<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PostcodeRequest;
use App\Http\Resources\PostcodeResource;
use App\Models\Postcode;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

final class PostcodeController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('viewAny', Postcode::class);

        return PostcodeResource::collection(Postcode::all());
    }

    public function store(PostcodeRequest $request)
    {
        $this->authorize('create', Postcode::class);

        return new PostcodeResource(Postcode::create($request->validated()));
    }

    public function show(Postcode $postcode)
    {
        $this->authorize('view', $postcode);

        return new PostcodeResource($postcode);
    }

    public function update(PostcodeRequest $request, Postcode $postcode)
    {
        $this->authorize('update', $postcode);

        $postcode->update($request->validated());

        return new PostcodeResource($postcode);
    }

    public function destroy(Postcode $postcode)
    {
        $this->authorize('delete', $postcode);

        $postcode->delete();

        return response()->json();
    }
}
