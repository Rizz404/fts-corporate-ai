<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureDevPreviewEnabled;
use Illuminate\Contracts\View\View;

/**
 * Temporary setup landing page. Plan 2 replaces "/" with the sign-in redirect.
 */
class LandingController extends Controller
{
    public function __invoke(): View
    {
        return view('landing', [
            'showPreviews' => app()->environment(EnsureDevPreviewEnabled::ENVIRONMENTS),
        ]);
    }
}
