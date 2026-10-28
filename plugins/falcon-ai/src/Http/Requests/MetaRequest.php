<?php

namespace FalconAi\Http\Requests;

use FalconAi\Support\Settings;
use Illuminate\Validation\Rule;

/** Write with AI, step 3: SEO title, description, excerpt and tags for the written article. */
class MetaRequest extends WriterRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:300'],
            'content' => ['required', 'string', 'max:300000'],
            'keyword' => ['nullable', 'string', 'max:120'],
            'language' => ['nullable', Rule::in(array_keys(Settings::LANGUAGES))],
        ];
    }
}
