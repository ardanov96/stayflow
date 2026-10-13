<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

final class ShellController extends Controller
{
    public function dashboard(): Response
    {
        return Inertia::render('Dashboard', [
            'app' => [
                'name' => (string) config('app.name'),
                'locales' => config('app.supported_locales', ['en']),
                'currentLocale' => app()->getLocale(),
            ],
        ]);
    }
}
