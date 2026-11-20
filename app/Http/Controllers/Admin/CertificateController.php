<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Services\CertificateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CertificateController extends Controller
{
    public function __construct(
        private readonly CertificateService $certificateService
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $statusFilter = trim((string) $request->query('status', ''));

        $certificates = Certificate::query()
            ->with(['user', 'course', 'revoker'])
            ->when($search !== '', function ($q) use ($search): void {
                $q->where('certificate_code', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('course', fn ($c) => $c->where('title', 'like', "%{$search}%"));
            })
            ->when($statusFilter === 'valid', fn ($q) => $q->whereNull('revoked_at'))
            ->when($statusFilter === 'revoked', fn ($q) => $q->whereNotNull('revoked_at'))
            ->latest('issued_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.certificates.index', [
            'certificates' => $certificates,
            'search' => $search,
            'statusFilter' => $statusFilter,
        ]);
    }

    public function revoke(Request $request, Certificate $certificate): RedirectResponse
    {
        $validated = $request->validate([
            'revocation_reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $this->certificateService->revoke(
            certificate: $certificate,
            actor: $request->user(),
            reason: $validated['revocation_reason'],
            ip: $request->ip()
        );

        return back()->with('status', "Certificate {$certificate->certificate_code} has been revoked.");
    }

    public function reinstate(Request $request, Certificate $certificate): RedirectResponse
    {
        $this->certificateService->reinstate(
            certificate: $certificate,
            actor: $request->user(),
            ip: $request->ip()
        );

        return back()->with('status', "Certificate {$certificate->certificate_code} has been reinstated.");
    }
}
