<?php

namespace App\Http\Requests\Admin;

use App\Models\MarketingAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarketingAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) ($user && $user->is_active && ! $user->isBlocked() && $user->isSuperAdmin());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'platform' => ['required', 'string', Rule::in(MarketingAccount::PLATFORMS)],
            'account_name' => ['required', 'string', 'max:150'],
            'profile_url' => ['required', 'url:http,https', 'max:500'],
            'channel_id' => ['nullable', 'string', 'max:150'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'followers_count' => ['nullable', 'integer', 'min:0'],
            'total_views' => ['nullable', 'integer', 'min:0'],
            'total_likes' => ['nullable', 'integer', 'min:0'],
            'engagement_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'top_video_title' => ['nullable', 'string', 'max:255'],
            'top_video_url' => ['nullable', 'url:http,https', 'max:500'],
            'top_video_views' => ['nullable', 'integer', 'min:0'],
            'top_video_likes' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
