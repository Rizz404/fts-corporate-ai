<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Guests go to sign-in; signed-in users go to the office (the account
     * middleware there sends temporary-password users to the change page).
     */
    public function __invoke(Request $request): RedirectResponse
    {
        return $request->user() !== null
            ? redirect()->route('office.reception')
            : redirect()->route('login');
    }
}
