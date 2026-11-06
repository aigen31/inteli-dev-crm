<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Ai\CrmHelpService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HelpController extends Controller
{
    public function index(Request $request, CrmHelpService $help): Response
    {
        $entries = collect($help->forUser($request->user()))
            ->groupBy('section')
            ->map(fn ($items, string $section): array => [
                'title' => $section,
                'entries' => $items->values()->all(),
            ])
            ->values()
            ->all();

        return Inertia::render('Help/Index', [
            'sections' => $entries,
            'version' => CrmHelpService::VERSION,
        ]);
    }
}
