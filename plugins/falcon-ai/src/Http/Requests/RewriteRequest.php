<?php

namespace FalconAi\Http\Requests;

use FalconAi\Prompts\ArticlePrompts;
use FalconAi\Support\Settings;
use Illuminate\Validation\Rule;

/** Improve the text selected in the editor. */
class RewriteRequest extends WriterRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'html' => ['required', 'string', 'max:20000'],
            'action' => ['required', Rule::in(array_keys(ArticlePrompts::ACTIONS))],
            'language' => ['nullable', Rule::in(array_keys(Settings::LANGUAGES))],
        ];
    }
}
