<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\DptService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DptController extends Controller
{
    public function index(Project $project): View
    {
        abort_unless($project->isFullDptMode(), 404);

        return view('pages.dpt', compact('project'));
    }

    public function template(Project $project, DptService $service): BinaryFileResponse
    {
        abort_unless($project->isFullDptMode(), 404);

        $path = tempnam(sys_get_temp_dir(), 'dpt-template-');
        $service->writeTemplate($project, $path);

        return response()
            ->download($path, 'template-dpt.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }
}
