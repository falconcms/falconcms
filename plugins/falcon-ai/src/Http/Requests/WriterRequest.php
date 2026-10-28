<?php

namespace FalconAi\Http\Requests;

use FalconAi\Support\Access;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base for every AI writing request: only someone allowed to write with AI gets past it (the
 * admin middleware checks the same, this is the second lock). Each request validates its own
 * fields with hard length limits, so nobody can send a book's worth of text to a paid API.
 */
abstract class WriterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Access::canWrite($this->user());
    }
}
