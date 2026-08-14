<?php

namespace App\Models;

use FalconCms\Core\Traits\HasCmsPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * The host application's User model, as the package expects to find it.
 *
 * FalconCMS deliberately does not ship a User model — it attaches to the one the
 * Laravel application already has, at the conventional App\Models\User. That means
 * the package's own test suite has to supply one, and this is it: the smallest
 * model that satisfies everything the package reaches for (the permissions trait,
 * the is_blocked column scopePublished filters on, the factory).
 *
 * Kept deliberately plain — no PHP attributes, no casts() method — so the suite
 * runs on PHP 8.1 and Laravel 10 as well as on the current release.
 */
class User extends Authenticatable
{
    use HasCmsPermissions, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'username', 'role_id',
        'is_blocked', 'login_attempts', 'blocked_until', 'last_failed_login_ip',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'blocked_until' => 'datetime',
        'last_attempt_at' => 'datetime',
        'is_blocked' => 'boolean',
    ];
}
