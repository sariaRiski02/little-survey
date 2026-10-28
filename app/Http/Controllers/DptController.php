<?php

namespace App\Http\Controllers;

use App\Support\DummyData;

class DptController extends Controller
{
    public function index()
    {
        $data = DummyData::dpt();
        abort_if($data['mode'] !== 'dpt_lengkap', 404);

        return view('pages.dpt', $data);
    }
}
