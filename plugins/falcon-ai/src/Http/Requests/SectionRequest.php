<?php

namespace FalconAi\Http\Requests;

/** Write with AI, step 2: one section of the outline the author approved (with the brief). */
class SectionRequest extends OutlineRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return parent::rules() + [
            'title' => ['required', 'string', 'max:300'],
            'headings' => ['required', 'array', 'min:1', 'max:12'],
            'headings.*' => ['required', 'string', 'max:200'],
            'index' => ['required', 'integer', 'min:0', 'max:11'],
            'points' => ['nullable', 'array', 'max:8'],
            'points.*' => ['string', 'max:300'],
        ];
    }

    /** The heading being written must be one of the outline's. */
    public function after(): array
    {
        return [function ($validator) {
            if (!$validator->errors()->has('index') && (int) $this->input('index') >= count((array) $this->input('headings', []))) {
                $validator->errors()->add('index', 'That section is not in the outline.');
            }
        }];
    }
}
