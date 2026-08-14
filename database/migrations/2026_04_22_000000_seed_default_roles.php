<?php

use App\Models\User;
use FalconCms\Core\Models\Permission;
use FalconCms\Core\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        $roles = [
            [
                'name' => 'Administrator',
                'slug' => 'administrator',
                'description' => 'Full access to all settings and content.',
            ],
            [
                'name' => 'Editor',
                'slug' => 'editor',
                'description' => 'Can publish and manage posts including the work of other users.',
            ],
            [
                'name' => 'Author',
                'slug' => 'author',
                'description' => 'Can publish and manage their own posts.',
            ],
            [
                'name' => 'Contributor',
                'slug' => 'contributor',
                'description' => 'Can write and manage their own posts but cannot publish them.',
            ],
            [
                'name' => 'Subscriber',
                'slug' => 'subscriber',
                'description' => 'Can only manage their profile.',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['slug' => $role['slug']], $role);
        }

        // Seed default permissions
        $permissions = [
            ['name' => 'Manage Content', 'slug' => 'manage_content'],
            ['name' => 'Manage Users', 'slug' => 'manage_users'],
            ['name' => 'Manage Roles', 'slug' => 'manage_roles'],
            ['name' => 'Manage Settings', 'slug' => 'manage_settings'],
            ['name' => 'Manage Media', 'slug' => 'manage_media'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['slug' => $permission['slug']], $permission);
        }

        // Sync all permissions to administrator role
        $adminRole = Role::where('slug', 'administrator')->first();
        if ($adminRole) {
            $allPermissionIds = Permission::pluck('id')->toArray();
            $adminRole->permissions()->sync($allPermissionIds);
        }

        // Seed default administrator user
        $adminRole = Role::where('slug', 'administrator')->first();
        if ($adminRole && !User::where('email', 'admin@admin.com')->exists()) {
            User::create([
                'name' => 'Administrator',
                'username' => 'admin',
                'email' => 'admin@admin.com',
                'password' => Hash::make('password'),
                'role_id' => $adminRole->id,
            ]);
        }
    }

    public function down(): void
    {
        // We might not want to delete roles on rollback if users are assigned to them,
        // but for completeness:
        // Role::whereIn('slug', ['administrator', 'editor', 'author', 'contributor', 'subscriber'])->delete();
    }
};
