{{-- Advanced Search element (frontend). Expects $el (and $s, but $s leaks the parent
     column's settings via Blade @include scope, so we read $el['settings']). --}}
@php
    $sps = $el['settings'] ?? [];

    // Post types to search in (multi-select). Empty = all content.
    $types = $sps['searchPostType'] ?? [];
    if (is_string($types)) $types = array_filter(array_map('trim', explode(',', $types)));
    if (!is_array($types)) $types = [];
    $typesCsv = implode(',', $types);
    $useProductCats = in_array('product', $types, true);

    $placeholder = $sps['placeholder'] ?? 'Search...';
    $liveSearch  = array_key_exists('enableLiveSearch', $sps) ? (bool) $sps['enableLiveSearch'] : true;
    $catDropdown = !empty($sps['enableCategoryDropdown']);
    $showButton  = array_key_exists('showButton', $sps) ? (bool) $sps['showButton'] : true;
    $buttonText  = $sps['buttonText'] ?? 'Search';
    $accent      = $sps['accentColor'] ?? '#0091ea';
    $bg          = $sps['bgColor'] ?? '#ffffff';
    $textColor   = $sps['textColor'] ?? '#1d2327';
    $placeholderColor = $sps['placeholderColor'] ?? '#9ca3af';
    $dropdownTextColor = $sps['dropdownTextColor'] ?? '#1d2327';
    $dropdownBgColor   = $sps['dropdownBgColor'] ?? '#ffffff';
    $borderColor = $sps['borderColor'] ?? '#e5e7eb';
    $height      = (int) ($sps['height'] ?? 46);
    $radius      = (int) ($sps['borderRadius'] ?? 6);
    $maxWidth    = (int) ($sps['maxWidth'] ?? 0);
    $align       = $sps['align'] ?? 'flex-start';
    $uid         = 'lazyas-' . ($el['id'] ?? uniqid());

    $cats = collect();
    if ($catDropdown) {
        try {
            $cats = $useProductCats
                ? \FalconCms\Core\Models\ProductCategory::orderBy('name')->get(['id', 'name'])
                : \FalconCms\Core\Models\Category::orderBy('name')->get(['id', 'name']);
        } catch (\Throwable $e) { $cats = collect(); }
    }

    $actionUrl = \Illuminate\Support\Facades\Route::has('frontend.search') ? route('frontend.search') : url('/search');
    $liveUrl   = \Illuminate\Support\Facades\Route::has('frontend.search.live') ? route('frontend.search.live') : url('/search/live');
@endphp

<style>#{{ $uid }}-input::placeholder{color:{{ $placeholderColor }};opacity:1;}#{{ $uid }}-input::-webkit-input-placeholder{color:{{ $placeholderColor }};}</style>
<div class="lazy-adv-search" style="display:flex;justify-content:{{ $align }};width:100%;">
    <form action="{{ $actionUrl }}" method="GET" class="lazy-adv-search-form"
          style="position:relative;display:flex;align-items:stretch;width:100%;{{ $maxWidth > 0 ? 'max-width:'.$maxWidth.'px;' : '' }}background:{{ $bg }};border:1px solid {{ $borderColor }};border-radius:{{ $radius }}px;">
        @if($typesCsv !== '')<input type="hidden" name="post_type" value="{{ $typesCsv }}">@endif

        @if($catDropdown)
            <select name="cat" style="border:none;background:{{ $dropdownBgColor }};color:{{ $dropdownTextColor }};padding:0 10px;height:{{ $height }}px;border-right:1px solid {{ $borderColor }};outline:none;max-width:170px;cursor:pointer;">
                <option value="">All Categories</option>
                @foreach($cats as $c)
                    <option value="{{ $c->id }}" {{ (string)request('cat') === (string)$c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                @endforeach
            </select>
        @endif

        <input type="text" name="s" id="{{ $uid }}-input" autocomplete="off" placeholder="{{ $placeholder }}" value="{{ request('s') }}"
               style="flex:1;min-width:0;border:none;background:transparent;color:{{ $textColor }};padding:0 14px;height:{{ $height }}px;outline:none;font-size:14px;">

        @if($showButton)
            <button type="submit" style="border:none;background:{{ $accent }};color:#fff;padding:0 18px;height:{{ $height }}px;font-size:14px;font-weight:600;cursor:pointer;border-radius:0 {{ $radius }}px {{ $radius }}px 0;white-space:nowrap;">{{ $buttonText }}</button>
        @else
            <button type="submit" aria-label="Search" style="border:none;background:transparent;color:{{ $accent }};padding:0 14px;height:{{ $height }}px;cursor:pointer;"><i class="fa fa-magnifying-glass"></i></button>
        @endif

        @if($liveSearch)
            <div id="{{ $uid }}-results" class="lazy-adv-search-results"
                 style="display:none;position:absolute;top:100%;left:0;right:0;margin-top:4px;background:#fff;border:1px solid {{ $borderColor }};border-radius:6px;box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:9999;max-height:360px;overflow-y:auto;"></div>
        @endif
    </form>
</div>

@if($liveSearch)
<script>
(function(){
    var input = document.getElementById('{{ $uid }}-input');
    var box = document.getElementById('{{ $uid }}-results');
    if (!input || !box) return;
    var timer;
    function hide(){ box.style.display = 'none'; box.innerHTML = ''; }
    input.addEventListener('input', function(){
        var q = input.value.trim();
        clearTimeout(timer);
        if (q.length < 2) { hide(); return; }
        var form = input.closest('form');
        var catEl = form ? form.querySelector('select[name="cat"]') : null;
        var cat = catEl ? catEl.value : '';
        timer = setTimeout(function(){
            var url = @json($liveUrl) + '?q=' + encodeURIComponent(q) + '&post_type=' + encodeURIComponent(@json($typesCsv)) + (cat ? '&cat=' + encodeURIComponent(cat) : '');
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function(r){ return r.json(); })
                .then(function(d){
                    var items = (d && d.results) || [];
                    if (!items.length) {
                        box.innerHTML = '<div style="padding:10px 14px;color:#888;font-size:13px;">No results found</div>';
                    } else {
                        box.innerHTML = items.map(function(it){
                            var t = (it.title || '').replace(/</g, '&lt;');
                            var img = it.image
                                ? '<img src="' + it.image + '" alt="" style="width:38px;height:38px;object-fit:cover;border-radius:4px;flex-shrink:0;background:#f1f1f1;">'
                                : '<span style="width:38px;height:38px;border-radius:4px;background:#f1f1f1;display:inline-flex;align-items:center;justify-content:center;color:#bbb;flex-shrink:0;"><i class="fa fa-image"></i></span>';
                            return '<a href="' + it.url + '" style="display:flex;align-items:center;gap:10px;padding:8px 12px;font-size:13px;color:#1d2327;text-decoration:none;border-bottom:1px solid #f1f1f1;">' + img + '<span>' + t + '</span></a>';
                        }).join('');
                    }
                    box.style.display = 'block';
                })
                .catch(hide);
        }, 250);
    });
    document.addEventListener('click', function(e){ if (!box.contains(e.target) && e.target !== input) hide(); });
})();
</script>
@endif
