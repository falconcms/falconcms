<?php

namespace FalconCms\Core\Tests\Feature\Cms;

use FalconCms\Core\Services\BuilderShortcodeConverter as C;
use FalconCms\Core\Services\ShortcodeConverter\Element;
use FalconCms\Core\Tests\TestCase;
use ReflectionClass;

/**
 * Each core builder element converts in its own class (ShortcodeConverter/Elements), listed
 * in BuilderShortcodeConverter::ELEMENTS. These guard that arrangement: a class that is
 * written but never registered, or registered but unable to make the round trip, is caught
 * here instead of on a customer's page.
 */
class BuilderElementRegistryTest extends TestCase
{
    public function test_every_registered_element_is_a_final_element_class(): void
    {
        $this->assertNotEmpty(C::ELEMENTS);

        foreach (C::ELEMENTS as $type => $class) {
            $this->assertTrue(class_exists($class), "{$type}: {$class} does not exist");
            $this->assertTrue(is_subclass_of($class, Element::class), "{$type}: {$class} does not extend Element");
            $this->assertTrue((new ReflectionClass($class))->isFinal(), "{$type}: {$class} should be final");
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $type, "{$type}: element types are snake_case");
        }
    }

    public function test_every_element_class_is_registered(): void
    {
        $registered = array_unique(array_values(C::ELEMENTS));

        foreach (glob(__DIR__.'/../../../src/Services/ShortcodeConverter/Elements/*.php') as $file) {
            $class = 'FalconCms\\Core\\Services\\ShortcodeConverter\\Elements\\'.basename($file, '.php');
            $this->assertContains($class, $registered,
                basename($file).' is never used: add it to BuilderShortcodeConverter::ELEMENTS');
        }
    }

    public function test_every_element_survives_the_round_trip_with_its_type_and_id(): void
    {
        foreach (array_keys(C::ELEMENTS) as $type) {
            $layout = [[
                'id' => 'c1', 'type' => 'container', 'settings' => [],
                'columns' => [[
                    'id' => 'col1', 'basis' => '100%', 'settings' => [],
                    'elements' => [['id' => 'el1', 'type' => $type, 'settings' => []]],
                ]],
            ]];

            $shortcode = C::jsonToShortcodes(json_encode($layout));
            $this->assertStringContainsString('[falcon_'.$type, $shortcode, "{$type}: not written as [falcon_{$type}]");

            $back = json_decode(C::shortcodesToJson($shortcode), true);
            $el = $back[0]['columns'][0]['elements'][0] ?? null;

            $this->assertNotNull($el, "{$type}: lost on the way back from the shortcode");
            $this->assertSame($type, $el['type'], "{$type}: came back as {$el['type']}");
            $this->assertSame('el1', $el['id'], "{$type}: lost its id");
        }
    }
}
