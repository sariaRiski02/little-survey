<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\View\View;

class PertanyaanController extends Controller
{
    public function index(Project $project): View
    {
        return view('pages.pertanyaan', compact('project'));
    }
}
