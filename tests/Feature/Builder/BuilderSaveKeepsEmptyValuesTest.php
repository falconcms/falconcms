<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use App\Models\User;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Support\DualButton;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * A builder layout is saved exactly as the builder sent it, empty strings included.
 *
 * The builder posts nested JSON, and ConvertEmptyStringsToNull turned every '' in it into
 * null. In a layout those differ: '' is a choice (Dual Button's "Solid + Outline" clears the
 * second button's background so it is transparent), null is "never set" and takes the
 * default. The outline button was saved with no background and the page drew it dark —
 * in a page, a header or footer layout, a card, a global section alike.
 */
class BuilderSaveKeepsEmptyValuesTest extends TestCase
{
    private const VIEWS = __DIR__.'/../../../resources/views';

    private function administrator(): User
    {
        return User::forceCreate([
            'name' => 'Admin',
            'email' => uniqid('admin').'@example.test',
            'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    /** One Dual Button wearing the "Solid + Outline" look, as the builder sends it. */
    private function outlineLayout(): array
    {
        $settings = DualButton::defaults();
        foreach (DualButton::PRESETS['outline'] as $key => $value) {
            if ($key !== 'name') {
                $settings[$key] = $value;
            }
        }

        return [[
            'id' => 'c1', 'type' => 'container', 'settings' => [],
            'columns' => [['id' => 'k1', 'basis' => '100%', 'settings' => [], 'elements' => [[
                'id' => 'e1', 'type' => 'dual_button', 'settings' => $settings,
            ]]]],
        ]];
    }

    /**
     * @return list<string>
     */
    private function postTypes(): array
    {
        return ['page', 'falcon_header', 'falcon_footer'];
    }

    public function test_a_page_header_and_footer_layout_keep_a_cleared_background(): void
    {
        $admin = $this->administrator();

        foreach ($this->postTypes() as $type) {
            $post = Post::create([
                'title' => 'T', 'slug' => uniqid('p'), 'type' => $type, 'status' => 'published',
                'editor_type' => 'builder', 'content' => '[]', 'user_id' => $admin->id,
            ]);

            $this->actingAs($admin)
                ->postJson(route('admin.falcon-builder.save', $post->id), ['layout' => $this->outlineLayout()])
                ->assertOk()->assertJson(['success' => true]);

            $saved = json_decode((string) $post->fresh()->content, true);
            $s = $saved[0]['columns'][0]['elements'][0]['settings'];
            $this->assertSame('', $s['secondaryBg'], "{$type}: the cleared background came back as ".var_export($s['secondaryBg'], true));

            // And the page draws it transparent, not in the default dark.
            $html = get_falcon_post_content($post->fresh()->content);
            $this->assertStringNotContainsString('--fdb-s-bg:#1d2327', str_replace(' ', '', $html), $type);
        }
    }

    // Mega Menu and Off-canvas layouts go through the same falcon_builder_payload().
    public function test_a_post_card_and_a_global_section_keep_it_too(): void
    {
        $this->withProLicensed();
        $admin = $this->administrator();
        $this->setCmsOptions(['falcon_post_cards' => json_encode([['id' => 'pc1', 'name' => 'Card', 'config' => []]])]);

        $this->actingAs($admin)
            ->postJson(route('admin.falcon-builder.post-cards.save-layout', 'pc1'), ['layout' => $this->outlineLayout()])
            ->assertOk()->assertJson(['success' => true]);
        $cards = json_decode((string) $this->storedOption('falcon_post_cards'), true);
        $this->assertSame('', $cards[0]['config']['layout'][0]['columns'][0]['elements'][0]['settings']['secondaryBg']);

        $this->actingAs($admin)
            ->postJson(route('admin.falcon-builder.global-sections.save'), ['name' => 'G', 'data' => $this->outlineLayout()[0]])
            ->assertOk()->assertJson(['success' => true]);
        $sections = json_decode((string) $this->storedOption('falcon_global_sections'), true);
        $this->assertSame('', $sections[0]['data']['columns'][0]['elements'][0]['settings']['secondaryBg']);
    }

    /**
     * Pressing Save in the builder makes the change live at once: the page cache goes, for
     * a page, header or footer layout and for the layouts other pages draw (a Post Card,
     * a Mega Menu, an Off-canvas panel, a global section). Only the page save did this, so
     * a saved card or section still showed the old version until the cache ran out.
     */
    public function test_every_builder_save_clears_the_page_cache(): void
    {
        $this->withProLicensed();
        $admin = $this->administrator();
        $this->setCmsOptions([
            'falcon_post_cards' => json_encode([['id' => 'pc1', 'name' => 'Card', 'config' => []]]),
            'falcon_mega_menus' => json_encode([['id' => 'mm1', 'name' => 'Menu', 'config' => []]]),
            'falcon_global_sections' => json_encode([['id' => 'gs1', 'name' => 'G', 'data' => []]]),
        ]);
        $post = Post::create([
            'title' => 'T', 'slug' => uniqid('p'), 'type' => 'falcon_header', 'status' => 'published',
            'editor_type' => 'builder', 'content' => '[]', 'user_id' => $admin->id,
        ]);

        $saves = [
            'page/header/footer' => fn () => $this->postJson(route('admin.falcon-builder.save', $post->id), ['layout' => $this->outlineLayout()]),
            'post card' => fn () => $this->postJson(route('admin.falcon-builder.post-cards.save-layout', 'pc1'), ['layout' => $this->outlineLayout()]),
            'mega menu' => fn () => $this->postJson(route('admin.falcon-builder.mega-menus.save-layout', 'mm1'), ['layout' => $this->outlineLayout()]),
            'global section' => fn () => $this->patchJson(route('admin.falcon-builder.global-sections.update', 'gs1'), ['data' => $this->outlineLayout()[0]]),
        ];

        $this->actingAs($admin);
        foreach ($saves as $what => $save) {
            Cache::put('falcon-test-cached-page', 'old html', 600);
            $save()->assertOk()->assertJson(['success' => true]);
            $this->assertFalse(Cache::has('falcon-test-cached-page'), "saving a {$what} left the page cache in place");
        }
    }

    /** Read from the table: the option store in this process still holds the value from before the request. */
    private function storedOption(string $key): ?string
    {
        return DB::table('cms_settings')->where('key', $key)->value('value');
    }

    public function test_the_payload_helper_falls_back_to_input_for_a_form_post(): void
    {
        $request = Request::create('/x', 'POST', ['layout' => 'form value']);

        $this->assertSame('form value', falcon_builder_payload($request, 'layout'));
    }

    /**
     * The look in use is read back from the settings, so it is marked again after a reload
     * and no look claims an element whose colours were changed by hand.
     */
    public function test_the_design_tab_marks_the_look_in_use(): void
    {
        $scripts = (string) file_get_contents(self::VIEWS.'/admin/falcon-builder/partials/scripts.blade.php');
        $design = (string) file_get_contents(self::VIEWS.'/admin/falcon-builder/partials/components/elements/dual-button-design.blade.php');

        $this->assertStringContainsString('const fcDbActivePreset = (el) =>', $scripts);
        $this->assertStringContainsString('fcDbActivePreset,', $scripts, 'not handed to the template');
        $this->assertStringContainsString('fcDbActivePreset(editingElement) === slug', $design);
    }
}
