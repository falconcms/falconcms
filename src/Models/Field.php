<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Field extends Model
{
    protected $table = 'custom_fields';
    protected $fillable = [
        'field_group_id', 'label', 'name', 'type',
        'instructions', 'required', 'params', 'order',
    ];

    protected $casts = [
        'params' => 'array',
        'required' => 'boolean',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(FieldGroup::class, 'field_group_id');
    }
}
