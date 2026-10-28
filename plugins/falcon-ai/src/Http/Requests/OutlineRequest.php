<?php

namespace FalconAi\Http\Requests;

use FalconAi\Prompts\ArticlePrompts;
use FalconAi\Support\Settings;
use Illuminate\Validation\Rule;

/** Write with AI, step 1: the brief for an outline. */
class OutlineRequest extends WriterRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'topic' => ['required', 'string', 'min:3', 'max:300'],
            'keyword' => ['nullable', 'string', 'max:120'],
            'audience' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'language' => ['nullable', Rule::in(array_keys(Settings::LANGUAGES))],
            'tone' => ['nullable', Rule::in(array_keys(Settings::TONES))],
            'length' => ['nullable', Rule::in(array_keys(ArticlePrompts::LENGTHS))],
        ];
    }
}
