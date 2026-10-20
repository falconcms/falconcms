<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Models\Plugin;
use FalconCms\Core\Support\PluginManager;
use FalconCms\Core\Tests\TestCase;

/**
 * A plugin that declares a dependency runs only while that dependency runs.
 *
 * activate() already refused Wholesale without the shop, but nothing stopped the shop going off
 * underneath it — the config switch, a restored database, a bundled default — and Wholesale
 * then loaded against a shop that was not there. The Plugins screen also offered "Activate"
 * on it, only to refuse once clicked.
 */
class PluginDependencyTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = resource_path('views/plugins/zz-addon');
        @mkdir($this->dir, 0777, true);
        file_put_contents($this->dir.'/plugin.json', json_encode([
            'name' => 'ZZ Addon', 'slug' => 'zz-addon', 'version' => '1.0.0', 'dependencies' => ['falcon-shop'],
        ]));
    }

    protected function tearDown(): void
    {
        @unlink($this->dir.'/plugin.json');
        @rmdir($this->dir);

        parent::tearDown();
    }

    private function manager(): PluginManager
    {
        return new PluginManager(resource_path('views/plugins'));
    }

    public function test_it_cannot_be_activated_while_its_dependency_is_off(): void
    {
        $this->assertTrue($this->manager()->deactivate('falcon-shop')['ok']);

        $result = $this->manager()->activate('zz-addon');

        $this->assertFalse($result['ok']);
        $this->assertNotContains('zz-addon', $this->manager()->activeSlugs());
    }

    public function test_the_plugins_screen_says_what_it_needs_instead_of_offering_activate(): void
    {
        $this->assertTrue($this->manager()->deactivate('falcon-shop')['ok']);

        $listed = $this->manager()->all()['zz-addon'];

        $this->assertFalse($listed['active']);
        $this->assertSame([$this->manager()->manifest('falcon-shop')['name']], $listed['missing_dependencies']);
    }

    public function test_it_stops_counting_as_active_when_its_dependency_goes_off(): void
    {
        Plugin::updateOrCreate(['slug' => 'zz-addon'], ['is_active' => true, 'version' => '1.0.0']);
        $this->assertContains('zz-addon', $this->manager()->activeSlugs());

        // Switched off directly in the database, past the guard in deactivate().
        Plugin::updateOrCreate(['slug' => 'falcon-shop'], ['is_active' => false]);

        $this->assertNotContains('zz-addon', $this->manager()->activeSlugs());
        $this->assertFalse($this->manager()->all()['zz-addon']['active']);
    }

    public function test_it_activates_once_its_dependency_is_on(): void
    {
        $this->assertContains('falcon-shop', $this->manager()->activeSlugs());

        $this->assertTrue($this->manager()->activate('zz-addon')['ok']);
        $this->assertSame([], $this->manager()->all()['zz-addon']['missing_dependencies']);
    }
}
