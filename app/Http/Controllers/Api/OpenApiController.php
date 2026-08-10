<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\OpenApi\OpenApiSpec;

// Public: API documentation isn't a secret, and tools like Swagger UI
// fetch this unauthenticated.
class OpenApiController extends Controller
{
    public function index()
    {
        return response()->json(OpenApiSpec::generate());
    }
}
