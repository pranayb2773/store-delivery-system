<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Postcode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/** @mixin Postcode */
final class PostcodeResource extends JsonApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'postcode' => $this->postcode,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at, //
        ];
    }
}
