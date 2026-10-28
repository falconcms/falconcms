<?php

namespace FalconAi\Http\Requests;

/**
 * SEO title or meta description options for the page being edited. The page's text comes from
 * the editor itself, never looked up by id: a post the user cannot open is never read.
 */
class SeoSuggestRequest extends WriterRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'field' => ['required', 'in:title,description'],
            'title' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string', 'max:300000'],
            'keyword' => ['nullable', 'string', 'max:200'],
            'current' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
