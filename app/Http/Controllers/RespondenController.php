<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\RespondentService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RespondenController extends Controller
{
    public function index(Project $project): View
    {
        return view('pages.responden', compact('project'));
    }

    public function template(Project $project, RespondentService $service): BinaryFileResponse
    {
        abort_if($project->isFullDptMode(), 404);

        $path = tempnam(sys_get_temp_dir(), 'responden-template-');
        $service->writeTemplate($project, $path);

        return response()
            ->download($path, 'template-responden.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }

    public function export(Project $project, RespondentService $service): StreamedResponse
    {
        return response()->streamDownload(function () use ($project, $service) {
            $out = fopen('php://output', 'w');

            foreach ($service->exportRows($project) as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, 'tautan-responden.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
