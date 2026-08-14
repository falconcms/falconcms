<?php

namespace FalconCms\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ApiToken extends Model
{
    protected $fillable = ['user_id', 'name', 'token', 'last_used_at'];

    protected $casts = ['last_used_at' => 'datetime'];

    protected $hidden = ['token'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
