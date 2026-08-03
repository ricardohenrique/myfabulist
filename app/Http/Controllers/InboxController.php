<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class InboxController extends Controller
{
    /**
     * Display the authenticated user's inbox.
     */
    public function __invoke(): View
    {
        return view('inbox');
    }
}
