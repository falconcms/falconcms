<?php

namespace FalconCms\Core\Tests\Feature\Admin;

use FalconCms\Core\Tests\TestCase;

/**
 * The page column of the analytics lists. A homepage visit is labelled with the domain it came
 * in on. It used to be APP_URL's domain, so a site moved to a new domain while APP_URL still
 * named the old one showed every homepage visit under a domain nobody had used.
 */
class VisitPageLabelTest extends TestCase
{
    public function test_a_homepage_visit_shows_the_domain_it_came_in_on(): void
    {
        config(['app.url' => 'https://new.example.com']);

        $this->assertSame('example.com', falcon_visit_page('https://example.com'));
        $this->assertSame('example.com', falcon_visit_page('https://example.com/'));
        $this->assertSame('new.example.com', falcon_visit_page('https://new.example.com/'));
        $this->assertSame('203.0.113.7', falcon_visit_page('http://203.0.113.7/'), 'a visit by bare IP says so');
    }

    public function test_other_pages_show_their_path(): void
    {
        $this->assertSame('/pricing', falcon_visit_page('https://example.com/pricing'));
        $this->assertSame('/cart?coupon=X', falcon_visit_page('https://example.com/cart?coupon=X'));
        $this->assertSame('/?author=1', falcon_visit_page('https://example.com?author=1'));
    }

    public function test_a_url_without_a_host_falls_back_to_app_url(): void
    {
        config(['app.url' => 'https://example.com']);

        $this->assertSame('example.com', falcon_visit_page(''));
        $this->assertSame('/about', falcon_visit_page('/about'));
    }
}
