<?php

namespace FalconAi\Http\Requests;

use FalconAi\Support\Access;
use FalconAi\Support\AiManager;
use FalconAi\Support\Settings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** AI → Settings. */
class SaveSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Access::canManage($this->user());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // Each provider's key, its Remove box and its model.
        $keys = [];
        foreach (array_keys(Settings::KEYS) as $provider) {
            $keys[$provider.'_key'] = ['nullable', 'string', 'max:300'];
            $keys[$provider.'_key_remove'] = ['nullable', 'boolean'];
            $keys[$provider.'_model'] = ['nullable', 'string', 'max:100'];
        }

        return $keys + [
            'provider' => ['required', Rule::in(array_keys(app(AiManager::class)->providers()))],
            'language' => ['required', Rule::in(array_keys(Settings::LANGUAGES))],
            'tone' => ['required', Rule::in(array_keys(Settings::TONES))],
            'writer_roles' => ['nullable', 'array'],
            'writer_roles.*' => ['string', Rule::in(DB::table('roles')->pluck('slug')->all())],
            'monthly_limit' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
        ];
    }
}
