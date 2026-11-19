<?php

namespace App\Http\Controllers;

use App\Models\Policy;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        return view('public.home', [
            'activePolicy' => Policy::active(),
        ]);
    }
}
