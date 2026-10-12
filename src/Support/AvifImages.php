<?php

namespace FalconCms\Core\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * AVIF copies of library images, served to browsers that support them.
 *
 * Each image keeps its file (WebP after the usual optimisation) and gains a sibling
 * "name.avif", typically 20-30% smaller again. HtmlOptimizeMiddleware then wraps a library
 * <img> in a <picture> offering the AVIF first, so a browser without AVIF support still gets
 * the original. Off by default (Customizer → Performance), and only where PHP's GD can write
 * AVIF.
 */
final class AvifImages
{
    public static function supported(): bool
    {
        return function_exists('imageavif') && (bool) (gd_info()['AVIF Support'] ?? false);
    }

    public static function enabled(): bool
    {
        return get_cms_option('perf_avif', '0') === '1' && self::supported();
    }

    /** "media/a.webp" → "media/a.avif" */
    public static function sibling(string $path): string
    {
        return preg_replace('/\.[A-Za-z0-9]+$/', '', $path).'.avif';
    }

    /** Write the AVIF copy of a stored image (public disk path). Returns whether one exists afterwards. */
    public static function generate(string $path): bool
    {
        if (!self::enabled() || preg_match('/\.(svg|gif|avif|ico)$/i', $path)) {
            return false;
        }
        $disk = Storage::disk('public');
        $target = self::sibling($path);
        if ($disk->exists($target)) {
            return true;
        }

        try {
            $img = @imagecreatefromstring((string) $disk->get($path));
            if (!$img) {
                return false;
            }
            imagepalettetotruecolor($img);
            imagealphablending($img, true);
            imagesavealpha($img, true);
            // AVIF at a lower number looks like WebP at a higher one.
            $quality = max(35, (int) get_cms_option('performance_image_quality', 80) - 25);
            ob_start();
            $ok = imageavif($img, null, $quality, 6);
            $data = ob_get_clean();
            imagedestroy($img);
            if (!$ok || !$data) {
                return false;
            }
            // Only worth keeping when it is actually smaller.
            if (strlen($data) >= $disk->size($path)) {
                return false;
            }
            $disk->put($target, $data);

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function delete(string $path): void
    {
        try {
            Storage::disk('public')->delete(self::sibling($path));
        } catch (Throwable $e) {
        }
    }

    /** Whether the AVIF copy of a public /storage/… path exists (checked on the local disk). */
    public static function existsFor(string $storagePath): bool
    {
        return is_file(storage_path('app/public/'.ltrim(self::sibling($storagePath), '/')));
    }
}
