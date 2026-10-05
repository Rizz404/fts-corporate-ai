<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Http\Requests\Api\UpdatePreferencesRequest;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class PreferenceController extends Controller
{
    public function update(UpdatePreferencesRequest $request): JsonResponse
    {
        $locale = $request->validated('locale');

        $request->user()->forceFill(['preferred_locale' => $locale])->save();
        $request->session()->put(SetLocale::SESSION_KEY, $locale);
        app()->setLocale($locale);

        return ApiResponse::success(['locale' => $locale]);
    }
}
