<?php

namespace FalconCms\Core\Tests\Feature\Shop;

use FalconCms\Core\Tests\TestCase;

/**
 * The variable product page before a variation is chosen: `selectedVariation` is null.
 *
 * x-show only hides an element; Alpine still evaluates every binding inside it. The
 * Availability row and the SKU sat in an x-show block and read selectedVariation.stock_status
 * and .sku directly, so each page load and each "Reset Selection" threw a burst of "Cannot read
 * properties of null" errors. A binding that reads the variation must either sit inside a
 * <template x-if> that checks it, or read it safely (`?.`, or `selectedVariation && …`).
 */
class VariableProductAlpineTest extends TestCase
{
    public function test_no_binding_reads_the_variation_before_one_is_chosen(): void
    {
        $file = __DIR__.'/../../../plugins/falcon-shop/resources/views/frontend/single-product-variable.blade.php';
        $html = (string) file_get_contents($file);

        // Drop every <template x-if="…selectedVariation…"> block, nested ones included: what is
        // inside is never evaluated while the variation is null.
        $unguarded = $this->withoutGuardedTemplates($html);

        preg_match_all('/\s(?:x-text|x-html|x-show|:class|:disabled|:value|x-bind:[\w-]+)="([^"]*)"/', $unguarded, $m);
        $offenders = [];
        foreach ($m[1] as $expression) {
            // the safe forms: optional chaining, an && guard, a ternary guard
            $plain = str_replace(['selectedVariation?.', 'selectedVariation && selectedVariation.', 'selectedVariation ? selectedVariation.'], '', $expression);
            if (preg_match('/\bselectedVariation\.\w+/', $plain)) {
                $offenders[] = $expression;
            }
        }

        $this->assertSame([], $offenders, 'these read selectedVariation while it can still be null');
    }

    private function withoutGuardedTemplates(string $html): string
    {
        $out = '';
        $depth = 0;
        $guarded = 0; // depth at which a guarded template opened, 0 when outside one
        $offset = 0;
        while (preg_match('#<template\b([^>]*)>|</template>#i', $html, $tag, PREG_OFFSET_CAPTURE, $offset)) {
            [$text, $at] = $tag[0];
            if ($guarded === 0) {
                $out .= substr($html, $offset, $at - $offset);
            }
            if ($text[1] === '/') {
                if ($guarded === $depth) {
                    $guarded = 0;
                }
                $depth--;
            } else {
                $depth++;
                if ($guarded === 0 && preg_match('/x-if="[^"]*selectedVariation/', $tag[1][0])) {
                    $guarded = $depth;
                }
            }
            $offset = $at + strlen($text);
        }

        return $out.($guarded === 0 ? substr($html, $offset) : '');
    }
}
