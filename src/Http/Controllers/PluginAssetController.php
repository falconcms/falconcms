<?php

namespace FalconCms\Core\Http\Controllers;

use FalconCms\Core\Support\PluginManager;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves a file from an active plugin's assets/ folder (plugins are not under public/).
 *
 * Only plugins loaded on this request are served, so a deactivated plugin's CSS and JS
 * cannot be loaded at all. The path must stay inside assets/, and only static web file
 * types are served — never PHP or anything else in the plugin folder.
 */
class PluginAssetController extends Controller
{
    private const TYPES = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'mjs' => 'application/javascript; charset=utf-8',
        'map' => 'application/json; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
    ];

    public function __invoke(PluginManager $plugins, string $slug, string $path): BinaryFileResponse
    {
        $manifest = $plugins->loaded()[$slug] ?? null;
        abort_unless($manifest, 404);

        $type = self::TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;
        abort_unless($type, 404);

        $root = realpath($manifest['dir'].DIRECTORY_SEPARATOR.'assets');
        $file = $root ? realpath($root.DIRECTORY_SEPARATOR.$path) : false;
        abort_unless($file && is_file($file) && str_starts_with($file, $root.DIRECTORY_SEPARATOR), 404);

        return response()->file($file, [
            'Content-Type' => $type,
            // URLs carry ?v=<mtime> (falcon_plugin_asset), so a changed file gets a new URL.
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
