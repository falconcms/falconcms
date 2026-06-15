<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Form extends Model
{
    protected $table = 'cms_forms';
    protected $fillable = ['title', 'slug', 'fields', 'settings', 'status', 'success_message', 'redirect_url', 'email_to'];

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
