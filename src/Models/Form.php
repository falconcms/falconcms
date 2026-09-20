<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * A form built in the form builder.
 *
 * `slug` is the form's address — `[falcon_form slug="…"]` resolves through it — so it is set
 * once at creation and does NOT follow a rename; see FormController::saveBuilder().
 *
 * The columns are spelled out because static analysis cannot see through Eloquent's `__get`.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property array<int, mixed>|null $fields
 * @property array<string, mixed>|null $settings
 * @property bool $status
 * @property string|null $lang_code
 * @property string|null $success_message
 * @property string|null $redirect_url
 * @property string|null $email_to
 * @property Collection<int, FormSubmission> $submissions
 */
class Form extends Model
{
    protected $table = 'cms_forms';

    // lang_code included: FormController::store() passes it on every create, and without it
    // here every form was saved with no language at all.
    protected $fillable = ['title', 'slug', 'fields', 'settings', 'status', 'lang_code', 'success_message', 'redirect_url', 'email_to'];

    protected $casts = [
        'fields' => 'array',
        'settings' => 'array',
        'status' => 'boolean',
    ];

    public function submissions()
    {
        return $this->hasMany(FormSubmission::class, 'form_id')->latest();
    }
}
