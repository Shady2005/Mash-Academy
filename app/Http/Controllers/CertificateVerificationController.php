<?php

namespace App\Http\Controllers;

use App\Models\AcademySetting;
use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CertificateVerificationController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(AcademySetting::isFeatureEnabled('enable_certificate_verification'), 404);

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
