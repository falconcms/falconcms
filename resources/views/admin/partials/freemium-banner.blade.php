@php
    // Hide the freemium/upgrade banner entirely once the site holds a valid Pro license.
    $graceActive = function_exists('falcon_freemium_grace_active') && falcon_freemium_grace_active()
        && ! (function_exists('falcon_licensed') && falcon_licensed());
    $graceUntil  = $graceActive ? config('falcon-options.freemium_grace_until', null) : null;
    $graceDays   = null;
    $graceDate   = null;
    if ($graceUntil) {
        try {
            $until     = \Illuminate\Support\Carbon::parse($graceUntil);
            $graceDays = max(0, (int) ceil(\Illuminate\Support\Carbon::now()->floatDiffInDays($until)));
            $graceDate = $until->format('M j, Y');
        } catch (\Throwable $e) { $graceActive = false; }
    }
    $gfRaw = get_cms_option('falcon_grandfathered_features', null);
    $gf    = is_array($gfRaw) ? $gfRaw : (is_string($gfRaw) ? json_decode($gfRaw, true) : []);
    $labels = [
        'ecommerce' => 'E-commerce', 'multilang' => 'Multi-language', 'analytics' => 'Analytics',
        'builder_pro' => 'Advanced Builder', 'custom_fields' => 'Custom Fields',
    ];
    $gfNames = array_values(array_filter(array_map(fn ($f) => $labels[$f] ?? null, is_array($gf) ? $gf : [])));
@endphp

@if($graceActive)
<div id="falcon-freemium-banner"
     class="mb-4 flex items-start gap-3 rounded-lg border border-[#f0c47a] bg-[#fdf6e9] px-4 py-3 text-[#5b4a1f]"
     style="display:none">
    <span class="mt-0.5 shrink-0 material-symbols-outlined text-[#c98a1a]" style="font-size:22px">rocket_launch</span>
    <div class="flex-1 text-[13px] leading-relaxed">
        <strong class="font-bold">FalconCMS is now freemium.</strong>
        @if(!empty($gfNames))
            The features you already use — <strong>{{ implode(', ', $gfNames) }}</strong> — stay free on this site, forever.
        @endif
        Every other Pro feature is unlocked for
        <strong>{{ $graceDays }} more {{ \Illuminate\Support\Str::plural('day', $graceDays) }}</strong>@if($graceDate) (until {{ $graceDate }})@endif,
        then it needs a Pro license.
    </div>
    <a href="{{ falcon_upgrade_url() }}" target="_blank" rel="noopener" class="shrink-0 rounded-md bg-[#e8912b] px-3 py-1.5 text-[12.5px] font-semibold text-[#171c23] hover:brightness-105">Upgrade to Pro</a>
    <button type="button" onclick="falconDismissFreemiumBanner()" class="shrink-0 text-[#b08a3e] hover:text-[#5b4a1f]" title="Dismiss for today" aria-label="Dismiss">
        <span class="material-symbols-outlined" style="font-size:20px">close</span>
    </button>
</div>
<script>
    (function () {
        var el = document.getElementById('falcon-freemium-banner');
        if (!el) return;
        var today = new Date().toISOString().slice(0, 10);
        if (localStorage.getItem('falcon-freemium-banner-dismissed') !== today) {
            el.style.display = 'flex';
        }
        window.falconDismissFreemiumBanner = function () {
            localStorage.setItem('falcon-freemium-banner-dismissed', today);
            el.style.display = 'none';
        };
    })();
</script>
@endif
