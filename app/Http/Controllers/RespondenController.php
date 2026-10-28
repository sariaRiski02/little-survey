<?php

namespace App\Http\Controllers;

use App\Support\DummyData;

class RespondenController extends Controller
{
    public function index()
    {
        return view('pages.responden', DummyData::responden());
    }
}
