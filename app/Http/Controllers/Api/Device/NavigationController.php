<?php

namespace App\Http\Controllers\Api\Device;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Device\NavigationRequest;
use App\Http\Requests\Api\Device\StartNavigationRequest;
use App\Http\Requests\Api\Device\UpdateNavigationRequest;
use App\Services\NavigationService;
use Illuminate\Http\JsonResponse;

class NavigationController extends Controller
{
    public function store(StartNavigationRequest $request, NavigationService $navigation): JsonResponse
    {
        $session = $navigation->start($request->attributes->get('device'), $request->validated('destination_place_id'), $request->validated('language', app()->getLocale()));

        return response()->json(['success' => true, 'data' => $navigation->response($session)], $session->wasRecentlyCreated ? 201 : 200);
    }

    public function show(NavigationRequest $request, NavigationService $navigation): JsonResponse
    {
        $session = $navigation->active($request->attributes->get('device'));

        return response()->json(['success' => true, 'data' => $session === null ? null : $navigation->response($session)]);
    }

    public function update(UpdateNavigationRequest $request, NavigationService $navigation): JsonResponse
    {
        $session = $navigation->update($request->attributes->get('device'), $request->validated());

        return response()->json(['success' => true, 'data' => $navigation->response($session)]);
    }

    public function destroy(NavigationRequest $request, NavigationService $navigation): JsonResponse
    {
        $session = $navigation->stop($request->attributes->get('device'));

        return response()->json(['success' => true, 'data' => $session === null ? null : $navigation->response($session)]);
    }
}
