<?php

namespace App\Http\Middleware;

use App\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentProjectMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $project = $request->route('project');

        // The route binds by `code_project`, so the default must use the "param:field" key;
        // a plain `project` key leaves an empty `?project=` on every generated link.
        URL::defaults([
            'project:code_project' => $project->code_project,
        ]);

        View::share('project', $project);

        return $next($request);
    }
}
