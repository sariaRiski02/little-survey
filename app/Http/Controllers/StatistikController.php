<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\View\View;

class StatistikController extends Controller
{
    public function index(Project $project): View
    {
        return view('pages.statistik', compact('project'));
    }
}
