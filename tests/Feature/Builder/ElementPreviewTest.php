<?php

namespace FalconCms\Core\Tests\Feature\Builder;

use App\Models\User;
use FalconCms\Core\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * A plugin's builder element can ask for its canvas preview to be its real front-end output
 * ('preview' => 'server'), so an element that draws data the browser does not have — posts
 * from an API, figures from the database — looks on the canvas the way it will on the page.
 */
class ElementPreviewTest extends TestCase
{
    private string $views;

    protected function setUp(): void
    {
        parent::setUp();

        $this->views = sys_get_temp_dir().'/falcon-preview-views-'.uniqid();
        File::ensureDirectoryExists($this->views);
        File::put($this->views.'/demo.blade.php', '<style>.demo{color:red}</style><div class="demo" data-id="{{ $el[\'id\'] }}">{{ $s[\'greeting\'] ?? \'none\' }} · {{ $previewDevice ?? \'desktop\' }} · {{ !empty($builderPreview) ? \'preview\' : \'page\' }}</div>');
        view()->addNamespace('preview-test', $this->views);

        add_falcon_filter('falcon_builder_elements', function (array $elements) {
            $elements['demo_server'] = ['type' => 'demo_server', 'name' => 'Demo', 'template' => 'preview-test::demo', 'preview' => 'server',
                'fields' => ['greeting' => ['type' => 'text', 'default' => 'Hi']]];
            $elements['demo_plain'] = ['type' => 'demo_plain', 'name' => 'Plain', 'template' => 'preview-test::demo',
                'fields' => ['greeting' => ['type' => 'text', 'default' => 'Hi']]];

            return $elements;
        });
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->views);
        parent::tearDown();
    }

    private function administrator(): User
    {
        return User::forceCreate([
            'name' => 'Admin', 'email' => 'preview-admin@example.test', 'password' => 'secret-password',
            'role_id' => (int) DB::table('roles')->where('slug', 'administrator')->value('id'),
        ]);
    }

    public function test_the_element_template_is_rendered_with_the_settings_being_edited(): void
    {
        $this->actingAs($this->administrator())
            ->postJson(route('admin.falcon-builder.element-preview'), [
                'id' => 'el42', 'type' => 'demo_server', 'settings' => ['greeting' => 'Hello'], 'device' => 'mobile',
            ])
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('html', fn ($html) => str_contains($html, 'data-id="el42"') && str_contains($html, 'Hello · mobile · preview') && str_contains($html, '<style>'));
    }

    public function test_only_elements_that_ask_for_it_and_only_for_the_admin(): void
    {
        $this->actingAs($this->administrator())
            ->postJson(route('admin.falcon-builder.element-preview'), ['type' => 'demo_plain', 'settings' => []])
            ->assertNotFound();
        $this->postJson(route('admin.falcon-builder.element-preview'), ['type' => 'nope', 'settings' => []])
            ->assertNotFound();

        // A guest is turned away (the admin hides itself: a 404, or the login page).
        auth()->logout();
        $guest = $this->post(route('admin.falcon-builder.element-preview'), ['type' => 'demo_server', 'settings' => []]);
        $this->assertContains($guest->status(), [302, 401, 403, 404]);
    }

    public function test_the_canvas_asks_the_server_for_such_elements(): void
    {
        $script = File::get(__DIR__.'/../../../resources/views/admin/falcon-builder/partials/scripts.blade.php');

        $this->assertStringContainsString("def.preview === 'server'", $script);
        $this->assertStringContainsString('admin.falcon-builder.element-preview', $script);
    }
}
