<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CertificateVerificationController extends Controller
{
    public function index(Request $request): View
    {
        $code = strtoupper(trim((string) $request->query('code', '')));
        $certificate = null;

        if ($code !== '') {
            $certificate = Certificate::query()
                ->with(['user', 'course'])
                ->where('certificate_code', $code)
                ->first();
        }

        return view('public.certificates.verify', [
            'code' => $code,
            'certificate' => $certificate,
        ]);
    }
}
