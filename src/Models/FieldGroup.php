<?php

namespace FalconCms\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FieldGroup extends Model
{
    protected $table = 'custom_field_groups';
    protected $fillable = [
        'title', 'description', 'rules', 'order', 'is_active',
    ];

    protected $casts = [
        'rules' => 'array',
        'is_active' => 'boolean',
    ];

    public function fields(): HasMany
    {
        return $this->hasMany(Field::class, 'field_group_id')->orderBy('order');
    }
}
