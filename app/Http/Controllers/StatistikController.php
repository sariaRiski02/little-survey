<?php

namespace App\Http\Controllers;

use App\Support\DummyData;

class StatistikController extends Controller
{
    public function index()
    {
        return view('pages.statistik', DummyData::statistik());
    }
}
