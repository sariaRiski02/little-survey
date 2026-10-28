<?php

namespace App\Http\Controllers;

use App\Models\Project;

class DashboardController extends Controller
{
    public function index(Project $project)
    {
        return view('pages.dashboard', compact('project'));
    }
}
