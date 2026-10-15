<?php

namespace FalconCms\Core\Tests\Feature\Admin;

use FalconCms\Core\Support\SiteHealth;
use FalconCms\Core\Tests\TestCase;

/**
 * The "File include from user input" rule of the Site Health code scan.
 *
 * It is the one critical rule that fired on ordinary Blade: a fixed view handed a value from the
 * request reads like an include of the request, and a site owner was told their code had a
 * file-inclusion hole when it had none.
 */
class SiteHealthCodeScanTest extends TestCase
{
    private function flags(string $line): bool
    {
        foreach (SiteHealth::SCAN_RULES as [, $title, , $re]) {
            if ($title === 'File include from user input') {
                return (bool) preg_match($re, $line);
            }
        }
        $this->fail('rule not found');
    }

    public function test_an_include_chosen_by_the_request_is_flagged(): void
    {
        $this->assertTrue($this->flags('include $_GET["page"];'));
        $this->assertTrue($this->flags("require_once base_path('pages/'.request('page').'.php');"));
        $this->assertTrue($this->flags('include $request->input("file");'));
        $this->assertTrue($this->flags("@include(request('view'))"));
        $this->assertTrue($this->flags("@include('partials.' . request('tab'))"), 'a view name built from the request');
        $this->assertTrue($this->flags("@extends(\$request->query('layout'))"));
    }

    public function test_a_fixed_view_given_request_data_is_not_flagged(): void
    {
        $this->assertFalse($this->flags("@include('falcon-wholesale::frontend.partials.login-form', ['redirectTo' => (string) request()->query('redirect_to', '')])"));
        $this->assertFalse($this->flags("@include(\"shop.partials.filters\", ['q' => request('q')])"));
        $this->assertFalse($this->flags("@includeIf('theme.banner', ['page' => \$request->path()])"));
    }

    public function test_methods_and_words_named_include_are_not_flagged(): void
    {
        $this->assertFalse($this->flags("\$query->include(request('with'));"));
        $this->assertFalse($this->flags("\$includeDrafts = request()->boolean('drafts');"));
    }
}
