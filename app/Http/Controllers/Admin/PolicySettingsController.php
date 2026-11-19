<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePolicyRequest;
use App\Models\Policy;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PolicySettingsController extends Controller
{
    public function edit(): View
    {
        $activePolicy = Policy::active();
        $history = Policy::query()
            ->with('updatedBy')
            ->withCount('acceptances')
            ->orderByDesc('version')
            ->get();

        return view('admin.settings.policy', [
            'activePolicy' => $activePolicy,
            'history' => $history,
        ]);
    }

    public function store(UpdatePolicyRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $policy = Policy::createNewVersion(
            title: trim((string) $validated['title']),
            body: trim((string) $validated['body']),
            actor: $request->user()
        );

        ActivityLogger::log(
            action: 'policy.version_published',
            subject: $policy,
            meta: [
                'version' => $policy->version,
                'title' => $policy->title,
            ],
            actor: $request->user(),
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.settings.policy.edit')
            ->with('status', "Policy Version {$policy->version} has been published and is now active on the registration page.");
    }
}
