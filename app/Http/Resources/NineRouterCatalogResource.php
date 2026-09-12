<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NineRouterCatalogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'base_url' => $this->resource['base_url'],
            'models' => $this->resource['models'],
            'fetched_at' => $this->resource['fetched_at'],
        ];
    }
}
