<?php

namespace App\Http\Controllers\Api;

use App\Actions\Uploads\UploadActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUploadRequest;

class UploadController extends Controller
{
    public function __construct(private UploadActions $actions) {}

    public function store(StoreUploadRequest $request)
    {
        $result = $this->actions->store($request->file('file'));

        return response()->json(['data' => $result], 201);
    }
}
