<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SystemProjectService;
use Inertia\Inertia;
use Inertia\Response;

class FeedbackController extends Controller
{
    public function index(SystemProjectService $projects): Response
    {
        return Inertia::render('Feedback/Index', [
            'systemProjects' => $projects->summaries(),
        ]);
    }
}
