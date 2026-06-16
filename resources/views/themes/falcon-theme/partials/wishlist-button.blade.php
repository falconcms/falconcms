@php $wlPid = $product->id ?? ($productId ?? 0); $wlActive = lazy_in_wishlist($wlPid); @endphp
<button type="button"
        class="lazy-wishlist-btn{{ $wlActive ? ' is-active' : '' }}"
        data-product-id="{{ $wlPid }}"
        title="{{ $wlActive ? 'In your wishlist' : 'Add to wishlist' }}"
        aria-label="Add to wishlist">
    <i data-lucide="heart"></i>
</button>

@once
<style>
    .lazy-wishlist-btn { display:inline-flex; align-items:center; justify-content:center; width:38px; height:38px; border-radius:9999px; background:#fff; color:#64748b; border:1px solid #e2e8f0; box-shadow:0 1px 3px rgba(0,0,0,.08); cursor:pointer; transition:color .2s, border-color .2s, transform .15s; padding:0; }
    .lazy-wishlist-btn:hover { color:#e0245e; border-color:#f6c9d6; }
    .lazy-wishlist-btn:active { transform:scale(.9); }
    .lazy-wishlist-btn svg { width:18px; height:18px; }
    .lazy-wishlist-btn.is-active { color:#e0245e; border-color:#f6c9d6; }
    .lazy-wishlist-btn.is-active svg { fill:#e0245e; }
    .lazy-wishlist-btn:disabled { opacity:.6; cursor:default; }
</style>
<script>
(function () {
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.lazy-wishlist-btn');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        var pid = btn.getAttribute('data-product-id');
        var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        btn.disabled = true;
        fetch('{{ route('shop.wishlist.toggle') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ product_id: pid })
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            btn.disabled = false;
            if (d.requires_login) { window.location.href = d.login_url; return; }
            if (d.success) {
                // Update every button for this product on the page.
                document.querySelectorAll('.lazy-wishlist-btn[data-product-id="' + pid + '"]').forEach(function (b) {
                    b.classList.toggle('is-active', d.added);
                    b.title = d.added ? 'In your wishlist' : 'Add to wishlist';
                });
                document.querySelectorAll('.falcon-wishlist-count').forEach(function (el) { el.textContent = d.count; });
                if (window.lucide) lucide.createIcons();
            }
        })
        .catch(function () { btn.disabled = false; });
    });
})();
</script>
@endonce
