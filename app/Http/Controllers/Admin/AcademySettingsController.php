<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAcademySettingsRequest;
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
        ]);
    }

    public function update(UpdateAcademySettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $fields = [
            'academy_name',
            'tagline',
            'about',
            'vision',
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
}
