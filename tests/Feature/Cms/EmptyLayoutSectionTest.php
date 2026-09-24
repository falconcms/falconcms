<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use App\Models\User;
use FalconCms\Core\Models\Post;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * A layout section that was assigned but never built.
 *
 * Creating a Footer from the Layout screen assigns it to the slot straight away, before there
 * is anything in it. That empty section rendered as `<footer class="falcon-builder-footer">
 * </footer>` — forty-seven characters of nothing, and truthy — so the theme's own footer was
 * skipped and the page ended with no footer at all.
 *
 * From the outside the two failures looked like one: the Layout footer "would not activate"
 * (it had, and showed nothing) and the Customizer's Footer options "did nothing" (the footer
 * they configure was no longer being rendered). An empty section now yields to the theme
 * default, which is what having nothing in it means.
 */
class EmptyLayoutSectionTest extends TestCase
{
    private ?User $admin = null;

    private function administrator(): User
    {
        return $this->admin ??= User::forceCreate([
            'name' => 'Admin', 'email' => 'layout-admin@example.test', 'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    private function section(string $type, string $content = ''): Post
    {
        return Post::create([
            'title' => 'Section', 'slug' => 'section-'.uniqid(), 'type' => $type,
            'status' => 'published', 'editor_type' => 'builder', 'content' => $content,
            'user_id' => User::forceCreate([
                'name' => 'A', 'email' => uniqid().'@example.test', 'password' => 'secret-password',
                'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
            ])->id,
        ]);
    }

    private function assignGlobally(string $slot, Post $section): void
    {
        update_cms_option('falcon_layout_global', json_encode([
            $slot => ['id' => $section->id, 'active' => true],
        ]));
        forget_cms_options_cache();
    }

    public function test_an_empty_footer_section_yields_to_the_theme_footer(): void
    {
        $this->assignGlobally('footer', $this->section('falcon_footer', ''));

        $this->assertNull(get_falcon_footer(),
            'an empty section still replaces the theme footer with an empty shell');
    }

    public function test_an_empty_header_section_yields_to_the_theme_header(): void
    {
        $this->assignGlobally('header', $this->section('falcon_header', ''));

        $this->assertNull(get_falcon_header());
    }

    public function test_the_same_holds_for_the_title_bar_and_the_content_slot(): void
    {
        $this->assignGlobally('page_title_bar', $this->section('falcon_ptb', ''));
        $this->assertNull(get_falcon_page_title_bar());

        $this->assignGlobally('content', $this->section('falcon_content', ''));
        $this->assertNull(get_falcon_content());
    }

    public function test_a_section_with_something_in_it_is_rendered_as_before(): void
    {
        // The fix must not swallow a real section. Anything at all inside the wrapper counts.
        $this->assignGlobally('footer', $this->section('falcon_footer', '<p>Contact us</p>'));

        $html = get_falcon_footer();

        $this->assertNotNull($html);
        $this->assertStringContainsString('falcon-builder-footer', $html);
        $this->assertStringContainsString('Contact us', $html);
    }

    public function test_whitespace_is_not_content(): void
    {
        // A section saved with nothing but blank lines reads as empty, because it is.
        $this->assignGlobally('footer', $this->section('falcon_footer', "  \n\t "));

        $this->assertNull(get_falcon_footer());
    }

    public function test_the_layout_screen_says_a_section_is_empty_rather_than_active(): void
    {
        // The other half of the same complaint: the front end was right to show the theme
        // footer, and the Layout screen was calling that section "Active · Footer" in green
        // while it rendered nothing. One of the two had to change, and it was the label.
        $empty = $this->section('falcon_footer', '');
        $built = $this->section('falcon_header', '<p>Real header</p>');

        update_cms_option('falcon_layout_global', json_encode([
            'footer' => ['id' => $empty->id, 'active' => true],
            'header' => ['id' => $built->id, 'active' => true],
        ]));
        forget_cms_options_cache();

        $html = $this->actingAs($this->administrator())
            ->get('/admin/falcon-builder-sections')
            ->assertOk()
            ->getContent();

        preg_match_all('/<span class="slot-status[^>]*>([^<]*)</', $html, $m);
        $labels = array_map('trim', $m[1]);

        $this->assertContains('Empty &middot; open to build it', $labels,
            'an assigned section with nothing in it is still announced as active');
        $this->assertContains('Active · Header', $labels,
            'a section that does have content lost its Active label');
    }

    public function test_a_freshly_created_section_is_reported_as_empty(): void
    {
        // Creating from the Layout screen assigns the slot immediately, so this is the exact
        // moment the screen used to start claiming "Active" about an empty section.
        $this->withProLicensed(); // creating a section is Pro-gated; the gate is tested elsewhere

        $response = $this->actingAs($this->administrator())
            ->postJson('/admin/falcon-builder-sections/section', [
                'slot' => 'footer', 'layout' => 'global', 'name' => 'My Footer',
            ]);

        $response->assertOk()->assertJsonPath('section.is_empty', true);
    }

    public function test_an_unassigned_slot_is_still_null(): void
    {
        update_cms_option('falcon_layout_global', json_encode([]));
        forget_cms_options_cache();

        $this->assertNull(get_falcon_footer());
        $this->assertNull(get_falcon_header());
    }
}
