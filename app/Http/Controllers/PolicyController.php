<?php

namespace App\Http\Controllers;

use App\Models\Policy;
use Illuminate\View\View;

class PolicyController extends Controller
{
    public function show(): View
    {
        return view('public.policy', [
            'policy' => Policy::active(),
        ]);
    }
}
