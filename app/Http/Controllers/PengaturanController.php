<?php

namespace App\Http\Controllers;

use App\Support\DummyData;

class PengaturanController extends Controller
{
    public function index()
    {
        return view('pages.pengaturan', DummyData::pengaturan());
    }
}
