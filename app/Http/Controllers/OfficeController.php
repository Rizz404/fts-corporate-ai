<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Temporary authenticated landing. Plan 3 replaces it with the office scenes.
 */
class OfficeController extends Controller
{
    public function reception(Request $request): View
    {
        return view('office.placeholder', [
            'user' => $request->user()->load('department'),
            'adminHref' => Gate::allows('admin.access') ? route('admin.dashboard') : null,
        ]);
    }
}
