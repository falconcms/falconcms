<?php

namespace FalconSeo\Support;

use Throwable;

/**
 * The SEO score beside each post in the posts and pages lists: a coloured number, and how
 * many problems its last check found. Hooked to `falcon_admin_list_seo_cell`, which runs once
 * per row, so the scores are loaded once per request.
 */
final class ScoreCell
{
    public static function render($post = null): void
    {
        try {
            $row = $post && !empty($post->id) ? (SiteScan::scores()[(int) $post->id] ?? null) : null;
            if (!$row) {
                return;
            }
            $colour = $row->score >= 80 ? '#00a32a' : ($row->score >= 50 ? '#dba617' : '#d63638');
            $problems = (int) $row->errors + (int) $row->warnings;
            $title = 'SEO score '.$row->score.'/100'.($problems ? ': '.$row->errors.' error(s), '.$row->warnings.' warning(s)' : ', nothing to fix')
                .($row->analyzed_at ? '' : ' (changed since, checked again soon)');
            echo '<div class="flex items-center gap-1.5 mt-1 text-[11px] text-[#646970]" title="'.e($title).'">'
                .'<span style="display:inline-block;min-width:24px;padding:0 4px;border-radius:3px;background:'.$colour.';color:#fff;font-weight:700;text-align:center">'.(int) $row->score.'</span>'
                .'<span>'.($problems ? $problems.' to fix' : 'SEO ok').'</span></div>';
        } catch (Throwable $e) {
            // The list must render even without the scores table.
        }
    }
}
