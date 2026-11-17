{{-- Dual Button — the element's one stylesheet, from FalconCms\Core\Support\DualButton.

     Included by both renderers (the Vue canvas through
     admin/falcon-builder/partials/styles.blade.php and the page through
     frontend/builder/render.blade.php), so what an author previews is literally the
     rules that ship. Per-element values arrive as custom properties on the wrapper.

     Not @once-guarded, for the reason text-anim-styles is not: render.blade.php decides
     whether a layout needs it, and a guard further in could be spent by a render whose
     output is thrown away. --}}
@php
    $fdbSm  = (int) get_cms_option('theme_small_screen_breakpoint', '800');
    $fdbMed = (int) get_cms_option('theme_medium_screen_breakpoint', '1100');
@endphp
<style>{!! \FalconCms\Core\Support\DualButton::css($fdbSm, $fdbMed) !!}</style>
