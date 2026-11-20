<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAcademySettingsRequest;
use App\Http\Requests\Admin\UpdateInstaPayQrRequest;
use App\Models\AcademySetting;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AcademySettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.academy', [
            'settings' => AcademySetting::allSettings(),
            'logoUrl' => AcademySetting::logoUrl(),
            'instapayQrUrl' => AcademySetting::instapayQrUrl(),
        ]);
    }

    public function update(UpdateAcademySettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $fields = [
            'academy_name',
            'academy_name_ar',
            'tagline',
            'tagline_ar',
            'about',
            'about_ar',
            'vision',
            'vision_ar',
            'contact_email',
            'contact_phone',
            'facebook_url',
            'instagram_url',
            'youtube_url',
            'whatsapp_number',
        ];

        $updates = [];
        foreach ($fields as $field) {
            $updates[$field] = isset($validated[$field]) && $validated[$field] !== ''
                ? trim((string) $validated[$field])
                : null;
        }

        $logoUpdated = false;
        if ($request->hasFile('logo')) {
            $previousLogo = AcademySetting::getValue('logo_path');
            $storedPath = $request->file('logo')->store('branding', 'public');
            $updates['logo_path'] = $storedPath;
            $logoUpdated = true;

            if ($previousLogo && Storage::disk('public')->exists($previousLogo)) {
                Storage::disk('public')->delete($previousLogo);
            }
        }

        AcademySetting::setMany($updates);

        ActivityLogger::log(
            action: $logoUpdated ? 'settings.academy_and_logo_updated' : 'settings.academy_updated',
            meta: [
                'academy_name' => $updates['academy_name'],
                'logo_updated' => $logoUpdated,
            ],
            actor: $request->user(),
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.settings.edit')
            ->with('status', 'Academy settings and branding have been updated site-wide.');
    }

    public function destroyLogo(Request $request): RedirectResponse
    {
        $currentLogo = AcademySetting::getValue('logo_path');

        if ($currentLogo && Storage::disk('public')->exists($currentLogo)) {
            Storage::disk('public')->delete($currentLogo);
        }

        AcademySetting::setValue('logo_path', null);

        ActivityLogger::log(
            action: 'settings.logo_removed',
            actor: $request->user(),
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.settings.edit')
            ->with('status', 'Academy logo has been removed. The default emblem will be displayed.');
    }

    public function updateInstaPayQr(UpdateInstaPayQrRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $updates = [
            'instapay_account_note' => isset($validated['instapay_account_note']) && $validated['instapay_account_note'] !== ''
                ? trim((string) $validated['instapay_account_note'])
                : null,
        ];

        $qrUpdated = false;
        if ($request->hasFile('instapay_qr')) {
            $previousQr = AcademySetting::getValue('instapay_qr_path');
            $updates['instapay_qr_path'] = $request->file('instapay_qr')->store('payments/instapay', 'public');
            $qrUpdated = true;

            if ($previousQr && Storage::disk('public')->exists($previousQr)) {
                Storage::disk('public')->delete($previousQr);
            }
        }

        AcademySetting::setMany($updates);

        ActivityLogger::log(
            action: $qrUpdated ? 'settings.instapay_qr_updated' : 'settings.instapay_note_updated',
            meta: ['qr_updated' => $qrUpdated],
            actor: $request->user(),
            ip: $request->ip()
        );

        return back()->with('status', 'InstaPay QR settings have been updated.');
    }

    public function destroyInstaPayQr(Request $request): RedirectResponse
    {
        $currentQr = AcademySetting::getValue('instapay_qr_path');

        if ($currentQr && Storage::disk('public')->exists($currentQr)) {
            Storage::disk('public')->delete($currentQr);
        }

        AcademySetting::setValue('instapay_qr_path', null);

        ActivityLogger::log(
            action: 'settings.instapay_qr_removed',
            actor: $request->user(),
            ip: $request->ip()
        );

        return back()->with('status', 'InstaPay QR image has been removed.');
    }
}
