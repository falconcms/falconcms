/*
 * Falcon Shop — off-canvas mini-cart (window.LazyCart).
 *
 * Moved from the falcon-theme mini-cart partial. Needs window.FalconShopConfig, printed
 * just before it by falcon-shop::frontend.partials.mini-cart.
 */
window.LazyCart = (function () {
    // Server-side values (routes, CSRF token) come from the page: see falcon-shop::frontend.partials.mini-cart.
    const ROUTES = window.FalconShopConfig.routes;
    const CSRF = window.FalconShopConfig.csrf;

    const root    = () => document.getElementById('mini-cart-root');
    const overlay = () => document.getElementById('mini-cart-overlay');
    const panel   = () => document.getElementById('mini-cart-panel');

    function refreshIcons() { if (window.lucide && typeof lucide.createIcons === 'function') lucide.createIcons(); }

    function setBadges(count) {
        document.querySelectorAll('.cart-count-badge').forEach(b => {
            b.textContent = count;
            b.classList.toggle('hidden', !(count > 0));
        });
        const mc = document.getElementById('mini-cart-count');
        if (mc) mc.textContent = '(' + count + ')';
    }

    function open() {
        const r = root();
        r.classList.remove('invisible');
        r.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        // next frame so transitions run
        requestAnimationFrame(() => {
            overlay().classList.remove('opacity-0');
            panel().classList.remove('translate-x-full');
        });
        // Always load the latest cart contents when the drawer opens
        // (callers like the header/menu cart icon just call open()).
        refresh();
    }

    function close() {
        overlay().classList.add('opacity-0');
        panel().classList.add('translate-x-full');
        document.body.style.overflow = '';
        setTimeout(() => {
            const r = root();
            r.classList.add('invisible');
            r.setAttribute('aria-hidden', 'true');
        }, 300);
    }

    function refresh() {
        return fetch(ROUTES.fragment, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(res => res.json())
            .then(data => {
                document.getElementById('mini-cart-items').innerHTML = data.html;
                document.getElementById('mini-cart-subtotal').textContent = data.subtotal;
                setBadges(data.count);
                refreshIcons();
                return data;
            });
    }

    let _toastTimer;
    function toast(message, icon) {
        // falconToast uses SweetAlert if it is on the page, and otherwise lazy-loads it on the
        // first toast — so this global mini-cart never forces the bundle onto a plain content page.
        if (window.falconToast) {
            window.falconToast(message, icon);
            return;
        }
        // Fallback: small bar at top of the mini-cart panel
        let bar = document.getElementById('mc-toast-bar');
        if (!bar) {
            bar = document.createElement('div');
            bar.id = 'mc-toast-bar';
            bar.style.cssText = 'position:absolute;top:0;left:0;right:0;z-index:10;padding:10px 16px;font-size:13px;font-weight:600;text-align:center;transition:opacity .3s';
            document.getElementById('mini-cart-panel').prepend(bar);
        }
        const isError = icon === 'error';
        bar.style.background = isError ? '#fee2e2' : '#d1fae5';
        bar.style.color      = isError ? '#b91c1c' : '#065f46';
        bar.style.opacity    = '1';
        bar.textContent      = message;
        clearTimeout(_toastTimer);
        _toastTimer = setTimeout(() => { bar.style.opacity = '0'; }, 2500);
    }

    function add(productId, quantity, variationId) {
        const payload = { product_id: productId, quantity: quantity || 1 };
        if (variationId) payload.variation_id = variationId;

        return fetch(ROUTES.add, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json().then(data => ({ ok: res.ok, data })))
        .then(({ ok, data }) => {
            if (ok && data.success) {
                open(); // open() refreshes the drawer contents
                return data;
            }
            toast(data.message || 'Could not add to cart.', 'error');
            return Promise.reject(data);
        })
        .catch(err => { if (!(err && err.message)) toast('Could not add to cart.', 'error'); return Promise.reject(err); });
    }

    function remove(key) {
        const itemsEl = document.getElementById('mini-cart-items');
        if (itemsEl) {
            itemsEl.innerHTML = '<div class="flex items-center justify-center py-20"><svg class="animate-spin w-7 h-7 text-gray-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg></div>';
        }

        const url = ROUTES.removeTpl.replace('__KEY__', encodeURIComponent(key));
        return fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
        })
        .then(res => res.json())
        .then(data => {
            toast(data.message || 'Item removed from cart.', 'success');
            window.dispatchEvent(new CustomEvent('falconCartItemRemoved', { detail: { key, ...data } }));
            return refresh();
        })
        .catch(() => {
            toast('Could not remove item.', 'error');
            return refresh();
        });
    }

    // Close on ESC
    document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });

    return { open, close, refresh, add, remove, setBadges, toast };
})();

// Global helper used by product cards across the theme.
function addToCart(productId, quantity, variationId) {
    return LazyCart.add(productId, quantity, variationId);
}
