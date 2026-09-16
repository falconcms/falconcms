<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Models\NavigationMenu;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Two menus may share a name; they may not share a slug.
 *
 * The slug column is unique and the controller built the slug straight from the name, so
 * naming a second menu after an existing one — "Main Menu" twice, or one deleted and made
 * again — threw a duplicate-key error and put a stack trace on the screen instead of a menu.
 * Categories, tags and posts have counted up for years. Menus never did.
 */
class MenuSlugTest extends TestCase
{
    private ?User $admin = null;

    private function administrator(): User
    {
        return $this->admin ??= User::forceCreate([
            'name' => 'Menu Admin',
            'email' => 'menu-admin@example.test',
            'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    private function create(string $name)
    {
        return $this->actingAs($this->administrator())
            ->post(route('admin.menus.store'), ['name' => $name]);
    }

    public function test_a_second_menu_with_the_same_name_gets_its_own_slug(): void
    {
        $this->create('Journal Menu')->assertRedirect();
        $this->create('Journal Menu')->assertRedirect();

        $slugs = NavigationMenu::where('name', 'Journal Menu')->pluck('slug')->sort()->values()->all();

        $this->assertSame(['journal-menu', 'journal-menu-2'], $slugs);
    }

    public function test_a_third_keeps_counting(): void
    {
        $this->create('Journal Menu');
        $this->create('Journal Menu');
        $this->create('Journal Menu');

        $this->assertTrue(NavigationMenu::where('slug', 'journal-menu-3')->exists());
    }

    public function test_a_name_with_no_slug_characters_still_gets_one(): void
    {
        $this->create('!!!')->assertRedirect();

        $this->assertTrue(NavigationMenu::where('slug', 'menu')->exists());
    }

    public function test_duplicating_a_menu_does_not_collide_either(): void
    {
        $this->create('Journal Menu');
        $menu = NavigationMenu::where('slug', 'journal-menu')->firstOrFail();

        $this->actingAs($this->administrator())
            ->post(route('admin.menus.duplicate', $menu))
            ->assertRedirect();
        $this->actingAs($this->administrator())
            ->post(route('admin.menus.duplicate', $menu))
            ->assertRedirect();

        $this->assertSame(3, NavigationMenu::where('slug', 'like', 'journal-menu%')->count());
    }

    public function test_the_slug_is_what_a_theme_asks_for(): void
    {
        $this->create('Journal Menu');

        // get_falcon_menu() takes this slug, so a surprise suffix would break a theme that
        // names it — which is exactly why the first one keeps the plain slug.
        $this->assertTrue(NavigationMenu::where('slug', 'journal-menu')->exists());
    }
}
