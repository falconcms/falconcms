<?php

namespace FalconCms\Core\Console\Commands;

use Illuminate\Console\Command;
use FalconCms\Core\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class InstallFalconCms extends Command
{
    protected $signature = 'falcon:install';
    protected $description = 'Full installation of Falcon CMS: migrations, assets, themes, and default data.';

    public function handle()
    {
        $this->info('--- Starting Falcon CMS Installation ---');

        // 1. Run Migrations
        $this->info('Step 1: Running migrations...');
        $this->call('migrate', ['--force' => true]);

        // 2. Publish Assets
        $this->info('Step 2: Publishing dashboard assets...');
        $this->call('vendor:publish', [
            '--tag' => 'falcon-cms-assets',
            '--force' => true
        ]);

        // 3. Publish Themes
        $this->info('Step 3: Publishing themes to resources/views/themes...');
        $this->call('vendor:publish', [
            '--tag' => 'falcon-themes',
            '--force' => true
        ]);

        // 3b. Publish child theme skeleton (no --force: never overwrite user customizations)
        $this->info('Step 3b: Publishing child theme (skipped if already exists)...');
        $this->call('vendor:publish', [
            '--tag' => 'falcon-theme-child',
        ]);

        // 4. Create Storage Link
        $this->info('Step 4: Creating storage link...');
        if (!file_exists(public_path('storage'))) {
            $this->call('storage:link');
        }

        // 5. Set Default Options
        $this->info('Step 5: Setting up default configurations...');
        $options = [
            'login_url' => 'falcon-admin',
            'register_url' => 'falcon-registration',
            'login_theme' => 'modern',
            'registration_theme' => 'modern',
            'active_theme' => 'falcon-theme',
        ];

        foreach ($options as $key => $value) {
            DB::table('cms_settings')->updateOrInsert(['key' => $key], ['value' => $value]);
        }

        // 6. Sync System Data (Permissions, Roles, Menus)
        $this->info('Step 6: Syncing Roles, Permissions and Menus...');
        $this->call('db:seed', [
            '--class' => 'FalconCms\\Core\\Database\\Seeders\\SystemSyncSeeder',
            '--force' => true
        ]);

        // 7. Sync Languages
        $this->info('Step 7: Syncing Languages...');
        $this->call('db:seed', [
            '--class' => 'FalconCms\\Core\\Database\\Seeders\\LanguageSeeder',
            '--force' => true
        ]);

        // 8. Auto-setup User Model Trait & Fillable
        $this->info('Step 8: Setting up User Model traits and permissions...');
        $this->setupUserModel();

        // 9. Create Admin User
        $this->info('Step 9: Setting up Administrator user...');
        $email = $this->ask('Enter Admin email', 'admin@admin.com');
        $password = $this->secret('Enter Admin password (min 8 chars)');

        if (empty($password)) {
            $password = 'password';
            $this->info('No password entered. Defaulting to: password');
        }

        $adminRole = Role::where('slug', 'super-admin')->first();
        
        if (!$adminRole) {
            $adminRole = Role::where('slug', 'administrator')->first();
        }

        if (!$adminRole) {
            $this->error('Admin role not found! Please run php artisan falcon:seed first.');
            return;
        }

        $user = User::where('email', $email)->first() ?: new User();
        $user->forceFill([
            'name' => 'Administrator',
            'email' => $email,
            'password' => Hash::make($password),
            'role_id' => $adminRole->id,
        ])->save();

        // 10. Auto-create E-commerce pages
        $this->info('Step 10: Auto-creating E-commerce pages...');
        $this->createEcommercePages();

        // 11. Create sample blog content (a sample post + a Blog page)
        $this->info('Step 11: Creating sample blog content...');
        $this->createSampleContent();

        $this->info('---------------------------------------');
        $this->info('Falcon CMS installed successfully!');
        $this->info("Login Email: {$email}");
        $this->info("Login Password: [hidden]");
        $this->info('Login URL: ' . url('/' . get_cms_option('login_url', 'falcon-admin')));
        $this->info('---------------------------------------');
    }

    protected function setupUserModel()
    {
        $path = app_path('Models/User.php');
        if (!file_exists($path)) return;

        $content = file_get_contents($path);
        $original = $content;

        // Cleanup any previous corruption (Removing duplicates)
        $content = str_replace(', HasCmsPermissions, HasCmsPermissions', ', HasCmsPermissions', $content);
        // Fix top level import corruption if it happened
        $content = preg_replace('/use Illuminate\\\\Database\\\\Eloquent\\\\Factories\\\\HasFactory, HasCmsPermissions;/', 'use Illuminate\Database\Eloquent\Factories\HasFactory;', $content);

        // 1. Add Namespace Import (at the top)
        if (!str_contains($content, 'FalconCms\Core\Traits\HasCmsPermissions')) {
            $content = preg_replace('/(namespace\s+App\\\\Models;)/', "$1\n\nuse FalconCms\Core\Traits\HasCmsPermissions;", $content);
        }

        // 2. Add role_id and username to Fillable
        if (!str_contains($content, 'role_id') || !str_contains($content, 'username')) {
            if (str_contains($content, '#[Fillable')) {
                // Ensure both role_id and username are added if missing
                if (!str_contains($content, 'username')) {
                    $content = preg_replace('/#\[Fillable\(\[(.*?)\]\)\]/s', "#[Fillable([$1, 'username'])]", $content);
                }
                if (!str_contains($content, 'role_id')) {
                    $content = preg_replace('/#\[Fillable\(\[(.*?)\]\)\]/s', "#[Fillable([$1, 'role_id'])]", $content);
                }
            } elseif (str_contains($content, '$fillable')) {
                if (!str_contains($content, 'username')) {
                    $content = preg_replace('/\$fillable\s*=\s*\[(.*?)\]/s', "\$fillable = [$1, 'username']", $content);
                }
                if (!str_contains($content, 'role_id')) {
                    $content = preg_replace('/\$fillable\s*=\s*\[(.*?)\]/s', "\$fillable = [$1, 'role_id']", $content);
                }
            }
        }

        // 3. Add Trait usage inside class body (Only if not already present in the class body)
        $classBody = strstr($content, 'class User');
        if ($classBody && !str_contains($classBody, 'HasCmsPermissions')) {
            $content = preg_replace('/(class\s+User\s+extends\s+Authenticatable\s*\{)/', "$1\n    use HasCmsPermissions;", $content);
        }

        if ($content !== $original) {
            file_put_contents($path, $content);
        }
    }

    protected function createEcommercePages()
    {
        $pages = [
            ['title' => 'Shop', 'slug' => 'product'],
            ['title' => 'Cart', 'slug' => 'cart'],
            ['title' => 'Checkout', 'slug' => 'checkout'],
            ['title' => 'Account', 'slug' => 'account'],
        ];

        $adminId = User::first()->id ?? 1;

        foreach ($pages as $page) {
            \FalconCms\Core\Models\Post::firstOrCreate(
                ['slug' => $page['slug'], 'type' => 'page'],
                [
                    'title' => $page['title'],
                    'status' => 'published',
                    'lang_code' => 'en',
                    'user_id' => $adminId,
                    'editor_type' => 'rich'
                ]
            );
        }
    }

    protected function createSampleContent()
    {
        $adminId = User::first()->id ?? 1;

        // Sample blog post so the blog listing has content right after install.
        \FalconCms\Core\Models\Post::firstOrCreate(
            ['slug' => 'hello-world', 'type' => 'post'],
            [
                'title'       => 'Hello World — Welcome to Falcon CMS',
                'status'      => 'published',
                'lang_code'   => 'en',
                'user_id'     => $adminId,
                'editor_type' => 'rich',
                'excerpt'     => 'Welcome to Falcon CMS! This is your first sample blog post — edit or delete it and start publishing your own stories.',
                'content'     => "<p>Welcome to <strong>Falcon CMS</strong> 🎉</p>"
                    . "<p>This is a sample blog post that was created automatically when you installed the CMS. "
                    . "You can edit it, delete it, or use it as a reference for how your posts will look on the front-end.</p>"
                    . "<h2>Getting started</h2>"
                    . "<ul><li>Create new posts from <em>Dashboard → Posts</em>.</li>"
                    . "<li>Customize colours, typography and the blog layout from <em>Appearance → Customize</em>.</li>"
                    . "<li>Assign a page as your Blog page from <em>Settings → General</em>.</li></ul>"
                    . "<p>Happy publishing!</p>",
            ]
        );

        // A ready-to-use "Blog" page the user can assign as the Blog page (Settings → General).
        \FalconCms\Core\Models\Post::firstOrCreate(
            ['slug' => 'blog', 'type' => 'page'],
            [
                'title'       => 'Blog',
                'status'      => 'published',
                'lang_code'   => 'en',
                'user_id'     => $adminId,
                'editor_type' => 'rich',
            ]
        );
    }
}
