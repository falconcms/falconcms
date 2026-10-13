<?php

namespace FalconSeo\Http\Controllers\Admin;

use FalconCms\Core\Models\Media;
use FalconSeo\Support\AvifImages;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

/** Media Library → "Generate AVIF copies". */
class MediaAvifController extends Controller
{
    /** Images handled per request by generateAvif(): small enough for any host's time limit. */
    private const AVIF_BATCH = 20;

    /**
     * Media → Generate AVIF copies: an AVIF copy for each existing library image, in batches the
     * page calls one after another (after_id = the last id done). The original files and their
     * addresses are never touched, so nothing that links to an image can break.
     */
    public function generateAvif(Request $request)
    {
        // Heavy and site-wide: for whoever runs the site's settings, like the switch that asks for it.
        abort_unless(auth()->user()?->hasPermission('manage_settings'), 403);

        if (!AvifImages::enabled()) {
            return response()->json(['success' => false, 'message' => AvifImages::supported()
                ? 'Turn on Serve AVIF Images in Customizer → Performance first.'
                : 'The PHP on this server cannot write AVIF images (GD without AVIF support).'], 422);
        }

        $afterId = max(0, (int) $request->input('after_id', 0));
        $images = Media::where('mime_type', 'like', 'image/%')
            ->whereNotIn('mime_type', ['image/svg+xml', 'image/gif', 'image/avif', 'image/x-icon', 'image/vnd.microsoft.icon'])
            ->orderBy('id');
        $total = (clone $images)->count();
        $batch = (clone $images)->where('id', '>', $afterId)->limit(self::AVIF_BATCH)->get(['id', 'path']);

        $made = 0;
        foreach ($batch as $media) {
            if (Storage::disk('public')->exists($media->path) && AvifImages::generate($media->path)) {
                $made++;
            }
        }
        $last = $batch->last()?->id ?? $afterId;

        return response()->json([
            'success' => true,
            'done' => $batch->count() < self::AVIF_BATCH,
            'after_id' => $last,
            'checked' => (clone $images)->where('id', '<=', $last)->count(),
            'total' => $total,
            'made' => $made,
        ]);
    }
}
