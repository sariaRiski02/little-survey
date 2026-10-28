<?php

namespace App\Http\Controllers;

use App\Services\SurveyService;
use Illuminate\View\View;

class SurveiController extends Controller
{
    public function show(string $token, SurveyService $service): View
    {
        $respondent = $service->findByToken($token);

        return view('pages.survei', compact('respondent'));
    }
}
