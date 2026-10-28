<?php

namespace App\Http\Controllers;

use App\Support\DummyData;

class PertanyaanController extends Controller
{
    public function index()
    {
        return view('pages.pertanyaan', DummyData::pertanyaan());
    }
}
