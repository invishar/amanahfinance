<?php

namespace App\Http\Controllers\Api;

use App\Actions\OnboardingAnswers\OnboardingAnswerActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOnboardingAnswerRequest;
use App\Http\Requests\UpdateOnboardingAnswerRequest;
use App\Http\Resources\OnboardingAnswerResource;
use App\Models\OnboardingAnswer;

class OnboardingAnswerController extends Controller
{
    public function __construct(private OnboardingAnswerActions $actions) {}

    public function index()
    {
        $this->authorize('viewAny', OnboardingAnswer::class);

        return OnboardingAnswerResource::collection(OnboardingAnswer::query()->paginate(20));
    }

    public function store(StoreOnboardingAnswerRequest $request)
    {
        $answer = $this->actions->create($request->validated());

        return (new OnboardingAnswerResource($answer))->response()->setStatusCode(201);
    }

    public function show(OnboardingAnswer $onboardingAnswer)
    {
        $this->authorize('view', $onboardingAnswer);

        return new OnboardingAnswerResource($onboardingAnswer);
    }

    public function update(UpdateOnboardingAnswerRequest $request, OnboardingAnswer $onboardingAnswer)
    {
        $onboardingAnswer = $this->actions->update($onboardingAnswer, $request->validated());

        return new OnboardingAnswerResource($onboardingAnswer);
    }

    public function destroy(OnboardingAnswer $onboardingAnswer)
    {
        $this->authorize('delete', $onboardingAnswer);

        $this->actions->delete($onboardingAnswer);

        return response()->json(null, 204);
    }
}
