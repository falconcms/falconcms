/*
 * Falcon Shop — wishlist heart buttons (delegated click handler).
 * Needs window.FalconShopConfig.wishlist, printed just before it by the wishlist-button partial.
 */
(function () {
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.lazy-wishlist-btn');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        var pid = btn.getAttribute('data-product-id');
        // The token comes from the page (not every theme prints a csrf-token meta tag).
        var cfg = (window.FalconShopConfig || {}).wishlist || {};
        var token = cfg.csrf || (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        btn.disabled = true;
        fetch(cfg.toggle, {
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
