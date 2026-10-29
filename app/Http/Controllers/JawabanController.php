<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\AnswerService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class JawabanController extends Controller
{
    public function index(Project $project): View
    {
        return view('pages.jawaban', compact('project'));
    }

    public function export(Request $request, Project $project, AnswerService $service): BinaryFileResponse
    {
        $filters = $request->validate(['wilayah' => ['nullable', 'string', 'max:255']]);

        $path = tempnam(sys_get_temp_dir(), 'jawaban-');
        $service->writeExport($project, $path, $filters['wilayah'] ?? null);

        return response()
            ->download($path, 'jawaban-responden.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }
}
