<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    /**
     * Redirect guests to the login page and authenticated users to the post-login page.
     */
    public function __invoke(): RedirectResponse
    {
        return redirect()->route(Auth::check() ? 'inbox' : 'login');
    }
}
