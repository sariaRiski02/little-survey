<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\View\View;

class HasilController extends Controller
{
    public function show(Project $project): View
    {
        return view('pages.hasil', compact('project'));
    }
}
