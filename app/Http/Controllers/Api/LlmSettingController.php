<?php

namespace App\Http\Controllers\Api;

use App\Actions\LlmSettings\LlmSettingActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLlmSettingRequest;
use App\Http\Resources\LlmSettingResource;
use App\Models\LlmSetting;

// Platform-wide singleton resource (see LlmSettingPolicy): no index/store/
// destroy, just show/update on "the" settings row.
class LlmSettingController extends Controller
{
    public function __construct(private LlmSettingActions $actions) {}

    public function show()
    {
        $this->authorize('view', LlmSetting::class);

        return new LlmSettingResource($this->actions->current());
    }

    public function update(UpdateLlmSettingRequest $request)
    {
        $setting = $this->actions->update($request->user(), $request->validated());

        return new LlmSettingResource($setting);
    }
}
