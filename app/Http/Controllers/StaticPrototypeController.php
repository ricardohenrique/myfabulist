<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class StaticPrototypeController extends Controller
{
    /**
     * Render a fixture-backed Phase 1 interface for visual review.
     */
    public function __invoke(string $view = 'inbox'): Response
    {
        return Inertia::render('prototype/workspace', [
            'initialView' => $view,
        ]);
    }
}
