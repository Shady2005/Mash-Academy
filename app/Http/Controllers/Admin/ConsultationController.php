<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademySetting;
use App\Models\Consultation;
use App\Services\ActivityLogger;
use App\Services\NotificationDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ConsultationController extends Controller
{
    public function __construct(
        private readonly NotificationDispatcher $notifications
    ) {}

    public function index(Request $request): View
    {
        $statusFilter = trim((string) $request->query('status', ''));

        $consultations = Consultation::query()
            ->with(['user', 'instructor', 'paymentRequest'])
            ->when(
                in_array($statusFilter, [
                    Consultation::STATUS_PENDING,
                    Consultation::STATUS_CONFIRMED,
                    Consultation::STATUS_COMPLETED,
                    Consultation::STATUS_CANCELLED,
                ], true),
                fn ($q) => $q->where('status', $statusFilter)
            )
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $settings = AcademySetting::allSettings();

        return view('admin.consultations.index', [
            'consultations' => $consultations,
            'statusFilter' => $statusFilter,
            'settings' => $settings,
            'consultationQrUrl' => AcademySetting::consultationInstaPayQrUrl(),
            'hasDedicatedConsultationQr' => ! empty($settings['consultation_instapay_qr_path']),
            'canEditSettings' => $request->user()->isSuperAdmin(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $validated = $request->validate([
            'enable_consultations' => ['nullable', 'boolean'],
            'consultation_duration_minutes' => ['required', 'integer', Rule::in([15, 30, 45, 60, 90])],
            'consultation_pricing_mode' => ['required', Rule::in([Consultation::PRICING_FREE, Consultation::PRICING_PAID])],
            'consultation_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'consultation_currency' => ['nullable', 'string', 'max:10'],
            'consultation_availability_note' => ['nullable', 'string', 'max:500'],
            'consultation_instapay_note' => ['nullable', 'string', 'max:255'],
            'consultation_instapay_qr' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
        ]);

        $updates = [
            'enable_consultations' => $request->boolean('enable_consultations') ? '1' : '0',
            'consultation_duration_minutes' => (string) $validated['consultation_duration_minutes'],
            'consultation_pricing_mode' => $validated['consultation_pricing_mode'],
            'consultation_price' => $validated['consultation_pricing_mode'] === Consultation::PRICING_PAID
                ? (string) round((float) ($validated['consultation_price'] ?? 0), 2)
                : '0',
            'consultation_currency' => strtoupper((string) ($validated['consultation_currency'] ?? 'EGP')),
            'consultation_availability_note' => isset($validated['consultation_availability_note']) && trim((string) $validated['consultation_availability_note']) !== ''
                ? trim((string) $validated['consultation_availability_note'])
                : null,
            'consultation_instapay_note' => isset($validated['consultation_instapay_note']) && trim((string) $validated['consultation_instapay_note']) !== ''
                ? trim((string) $validated['consultation_instapay_note'])
                : null,
        ];

        if ($request->hasFile('consultation_instapay_qr')) {
            $old = AcademySetting::getValue('consultation_instapay_qr_path');
            if ($old && Storage::disk('public')->exists($old)) {
                Storage::disk('public')->delete($old);
            }
            $updates['consultation_instapay_qr_path'] = $request->file('consultation_instapay_qr')->store('consultations/instapay-qr', 'public');
        }

        AcademySetting::setMany($updates);

        ActivityLogger::log(
            action: 'consultations.settings_updated',
            meta: [
                'enabled' => $updates['enable_consultations'] === '1',
                'pricing_mode' => $updates['consultation_pricing_mode'],
                'price' => $updates['consultation_price'],
                'duration' => $updates['consultation_duration_minutes'],
            ],
            actor: $request->user(),
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.consultations.index')
            ->with('status', 'Consultation settings updated successfully.');
    }

    public function destroyInstaPayQr(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $old = AcademySetting::getValue('consultation_instapay_qr_path');
        if ($old && Storage::disk('public')->exists($old)) {
            Storage::disk('public')->delete($old);
        }

        AcademySetting::setValue('consultation_instapay_qr_path', null);

        return redirect()
            ->route('admin.consultations.index')
            ->with('status', 'Dedicated consultation InstaPay QR removed (will fall back to global Academy QR).');
    }

    public function updateStatus(Request $request, Consultation $consultation): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                Consultation::STATUS_PENDING,
                Consultation::STATUS_CONFIRMED,
                Consultation::STATUS_COMPLETED,
                Consultation::STATUS_CANCELLED,
            ])],
            'meeting_link' => ['nullable', 'url:http,https', 'max:500'],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $consultation->status = $validated['status'];
        $consultation->meeting_link = isset($validated['meeting_link']) && trim((string) $validated['meeting_link']) !== ''
            ? trim((string) $validated['meeting_link'])
            : null;
        $consultation->admin_note = isset($validated['admin_note']) && trim((string) $validated['admin_note']) !== ''
            ? trim((string) $validated['admin_note'])
            : null;
        $consultation->save();

        $this->notifications->notifyConsultationUpdated($consultation);

        ActivityLogger::log(
            action: 'consultation.status_updated',
            subject: $consultation,
            meta: ['status' => $consultation->status],
            actor: $request->user(),
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.consultations.index')
            ->with('status', "Consultation #{$consultation->id} updated to ".ucfirst($consultation->status).'.');
    }
}
