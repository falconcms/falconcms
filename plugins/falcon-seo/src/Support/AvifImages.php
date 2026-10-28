<?php

namespace FalconSeo\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * AVIF copies of library images, served to browsers that support them.
 *
 * Each image keeps its file (WebP after the usual optimisation) and gains a sibling
 * "name.avif", typically 20-30% smaller again. pictures() (on the falcon_optimize_html filter) then wraps a library
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

    /**
     * Offer the AVIF copy of library images: <img src="/storage/x.webp"> becomes
     * <picture class="falcon-avif"><source type="image/avif" srcset="/storage/x.avif"><img …></picture>.
     * The picture is display:contents, so it adds no box and the image sits in the layout
     * exactly as before. Images with their own srcset, and ones already inside a <picture>,
     * are left alone.
     */
    /** Drops a picture's AVIF <source> once its <img> is given another src (see pictures()). */
    private const SWAP_SCRIPT = '<script>(function(){if(!window.MutationObserver)return;'
        .'var o=new MutationObserver(function(l){l.forEach(function(r){var i=r.target,p=i.parentNode,s;'
        .'if(p&&p.tagName==="PICTURE"&&i.getAttribute("src")!==i.__fa){while((s=p.querySelector("source")))p.removeChild(s);}});});'
        .'document.querySelectorAll("picture.falcon-avif>img").forEach(function(i){i.__fa=i.getAttribute("src");o.observe(i,{attributes:true,attributeFilter:["src"]});});'
        .'})();</script>';

    public static function pictures(string $html): string
    {
        $wrapped = 0;
        $swapped = [];
        $out = preg_replace_callback('/(<picture\b.*?<\/picture>)|<img\b[^>]*>/is', function ($m) use (&$wrapped, &$swapped) {
            if (!empty($m[1])) {
                return $m[0];
            }
            $tag = $m[0];
            if (stripos($tag, 'srcset=') !== false
                || !preg_match('/\bsrc=(["\'])([^"\']*\/storage\/([^"\'?#]+\.(?:webp|jpe?g|png)))(?:[?#][^"\']*)?\1/i', $tag, $src)
                || !self::existsFor(rawurldecode($src[3]))) {
                return $tag;
            }
            $wrapped++;
            $avif = preg_replace('/\.[A-Za-z0-9]+$/', '.avif', $src[2]);
            $swapped[$src[2]] = $avif;

            return '<picture class="falcon-avif"><source type="image/avif" srcset="'.$avif.'">'.$tag.'</picture>';
        }, $html) ?? $html;

        // A preloaded hero should fetch the AVIF, not the original as well. With type set, a
        // browser that cannot show AVIF skips the preload and loads the original as usual.
        foreach ($swapped as $original => $avif) {
            $out = str_replace('<link rel="preload" as="image" href="'.$original.'" fetchpriority="high"',
                '<link rel="preload" as="image" href="'.$avif.'" type="image/avif" fetchpriority="high"', $out);
        }

        if ($wrapped && ($head = stripos($out, '</head>')) !== false) {
            $out = substr_replace($out, '<style>picture.falcon-avif{display:contents}</style>', $head, 0);
        }
        // A script that changes a picture (a product gallery's thumbnails, an Alpine :src) sets the
        // <img>'s src, but the browser keeps showing the <source>: once the src changes, the
        // source goes and the new picture shows.
        if ($wrapped && ($body = strripos($out, '</body>')) !== false) {
            $out = substr_replace($out, self::SWAP_SCRIPT, $body, 0);
        }

        return $out;
    }
}
