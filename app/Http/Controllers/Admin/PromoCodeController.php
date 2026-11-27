<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PromoCodeRequest;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PromoCodeController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $query = PromoCode::query()
            ->with('creator')
            ->withCount('paymentRequests')
            ->latest();

        // Teachers strictly view & manage ONLY their own promo codes
        if ($user->isTeacher()) {
            $query->where('created_by', $user->id);
        }

        $promoCodes = $query->paginate(15)->withQueryString();

        return view('admin.promo-codes.index', [
            'promoCodes' => $promoCodes,
            'isTeacherScoped' => $user->isTeacher(),
        ]);
    }

    public function store(PromoCodeRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $promo = new PromoCode();
        $promo->code = $validated['code'];
        $promo->discount_type = $validated['discount_type'];
        $promo->discount_value = $validated['discount_value'];
        $promo->max_uses = isset($validated['max_uses']) && $validated['max_uses'] !== '' ? (int) $validated['max_uses'] : null;
        $promo->expires_at = $validated['expires_at'] ?? null;
        $promo->is_active = $request->boolean('is_active', true);
        // Always bind promo code ownership strictly to the authenticated instructor
        $promo->created_by = $request->user()->id;
        $promo->save();

        ActivityLogger::log(
            action: 'promo_code.created',
            subject: $promo,
            meta: [
                'code' => $promo->code,
                'discount_type' => $promo->discount_type,
                'discount_value' => $promo->discount_value,
                'created_by' => $promo->created_by,
            ],
            actor: $request->user(),
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.promo-codes.index')
            ->with('status', "Promo code \"{$promo->code}\" ({$promo->formattedDiscount()}) created and bound to your courses.");
    }

    public function update(PromoCodeRequest $request, PromoCode $promoCode): RedirectResponse
    {
        $this->authorizePromoCode($request->user(), $promoCode);

        $validated = $request->validated();

        $promoCode->code = $validated['code'];
        $promoCode->discount_type = $validated['discount_type'];
        $promoCode->discount_value = $validated['discount_value'];
        $promoCode->max_uses = isset($validated['max_uses']) && $validated['max_uses'] !== '' ? (int) $validated['max_uses'] : null;
        $promoCode->expires_at = $validated['expires_at'] ?? null;
        $promoCode->is_active = $request->boolean('is_active');
        $promoCode->save();

        ActivityLogger::log(
            action: 'promo_code.updated',
            subject: $promoCode,
            meta: ['code' => $promoCode->code, 'is_active' => $promoCode->is_active],
            actor: $request->user(),
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.promo-codes.index')
            ->with('status', "Promo code \"{$promoCode->code}\" updated.");
    }

    public function toggle(Request $request, PromoCode $promoCode): RedirectResponse
    {
        $this->authorizePromoCode($request->user(), $promoCode);

        $promoCode->is_active = ! $promoCode->is_active;
        $promoCode->save();

        ActivityLogger::log(
            action: $promoCode->is_active ? 'promo_code.enabled' : 'promo_code.disabled',
            subject: $promoCode,
            meta: ['code' => $promoCode->code],
            actor: $request->user(),
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.promo-codes.index')
            ->with('status', "Promo code \"{$promoCode->code}\" is now ".($promoCode->is_active ? 'enabled' : 'disabled').'.');
    }

    public function destroy(Request $request, PromoCode $promoCode): RedirectResponse
    {
        $this->authorizePromoCode($request->user(), $promoCode);

        $code = $promoCode->code;
        $promoCode->delete();

        ActivityLogger::log(
            action: 'promo_code.deleted',
            meta: ['code' => $code],
            actor: $request->user(),
            ip: $request->ip()
        );

        return redirect()
            ->route('admin.promo-codes.index')
            ->with('status', "Promo code \"{$code}\" deleted.");
    }

    private function authorizePromoCode(User $user, PromoCode $promoCode): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        if ($user->isTeacher() && (int) $promoCode->created_by === (int) $user->id) {
            return;
        }

        abort(403, 'You can only manage your own promo codes.');
    }
}
