<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;

class FormSubmission extends Model
{
    protected $table = 'cms_form_submissions';
    protected $fillable = ['form_id', 'data', 'ip_address', 'user_agent', 'is_read'];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
    ];

    public function form()
    {
        return $this->belongsTo(Form::class, 'form_id');
    }
}
