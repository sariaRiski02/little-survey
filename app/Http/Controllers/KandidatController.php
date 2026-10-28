<?php

namespace App\Http\Controllers;

use App\Models\Project;



class KandidatController extends Controller
{
    public function index(Project $project)
    {


        return view('pages.kandidat', compact('project'));
    }
}
