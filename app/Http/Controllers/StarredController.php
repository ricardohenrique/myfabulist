<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class StarredController extends Controller
{
    /**
     * Display the authenticated user's starred items.
     */
    public function __invoke(): View
    {
        return view('starred');
    }
}
