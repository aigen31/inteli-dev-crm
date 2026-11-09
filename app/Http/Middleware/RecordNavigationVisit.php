<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Project;
use App\Models\Task;
use App\Services\NavigationHistoryService;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Records the task/project detail pages a user opens so the dashboard can offer
 * a way back. Never breaks the page: failures are logged and swallowed.
 */
class RecordNavigationVisit
{
    /**
     * Route name => [route parameter, model class].
     *
     * @var array<string, array{0: string, 1: class-string<Model>}>
     */
    private const ROUTES = [
        'admin.tasks.show' => ['task', Task::class],
        'client.tasks.show' => ['task', Task::class],
        'admin.projects.show' => ['project', Project::class],
        'client.projects.show' => ['project', Project::class],
    ];

    public function __construct(private readonly NavigationHistoryService $history) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldRecord($request, $response)) {
            return $response;
        }

        $route = $request->route();
        [$parameter, $class] = self::ROUTES[(string) $route?->getName()];
        $visitable = $route?->parameter($parameter);

        if (! $visitable instanceof $class) {
            return $response;
        }

        try {
            $this->history->touch(
                $request->user(),
                $visitable,
                '/'.ltrim($request->path(), '/'),
            );
        } catch (Throwable $exception) {
            Log::warning('Не удалось записать историю переходов.', [
                'user_id' => $request->user()?->getKey(),
                'path' => $request->path(),
                'exception' => $exception->getMessage(),
            ]);
        }

        return $response;
    }

    private function shouldRecord(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || $request->user() === null) {
            return false;
        }

        if (! $response->isSuccessful()) {
            return false;
        }

        // Inertia partial reloads re-render the same page: nothing new to remember.
        if ($request->headers->has('X-Inertia-Partial-Data')) {
            return false;
        }

        $name = $request->route()?->getName();

        return is_string($name) && array_key_exists($name, self::ROUTES);
    }
}
