{{-- Off-canvas mini-cart drawer. Globally included in the layout. --}}
<div id="mini-cart-root" class="fixed inset-0 z-[9999] invisible" aria-hidden="true">
    <!-- Backdrop -->
    <div id="mini-cart-overlay" class="absolute inset-0 bg-black/40 opacity-0 transition-opacity duration-300" onclick="LazyCart.close()"></div>

    <!-- Panel -->
    <aside id="mini-cart-panel"
           class="absolute top-0 right-0 h-full w-[88%] max-w-[400px] bg-white shadow-2xl flex flex-col translate-x-full transition-transform duration-300 ease-out"
           role="dialog" aria-modal="true" aria-label="Shopping cart">

        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h2 class="text-[16px] font-bold text-heading flex items-center gap-2">
                <i data-lucide="shopping-bag" class="w-5 h-5 text-primary"></i>
                Your Cart <span id="mini-cart-count" class="text-[13px] font-semibold text-gray-400">(0)</span>
            </h2>
            <button type="button" onclick="LazyCart.close()" class="text-gray-400 hover:text-heading transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Items (filled via AJAX) -->
        <div id="mini-cart-items" class="flex-1 overflow-y-auto">
            <div class="flex items-center justify-center py-20">
                <i data-lucide="loader-circle" class="w-6 h-6 text-gray-300 animate-spin"></i>
            </div>
        </div>

        <!-- Footer -->
        <div id="mini-cart-footer" class="border-t border-gray-100 px-5 py-4 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-[14px] text-gray-500">Subtotal</span>
                <span id="mini-cart-subtotal" class="text-[18px] font-black text-heading">{{ falcon_price_format(0) }}</span>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <a href="{{ route('shop.cart') }}" class="text-center bg-white text-primary border border-primary px-4 py-2.5 rounded-[3px] text-[13px] font-semibold uppercase tracking-wider hover:bg-gray-50 transition-colors">View Cart</a>
                <a href="{{ route('shop.checkout') }}" class="text-center bg-primary text-white px-4 py-2.5 rounded-[3px] text-[13px] font-semibold uppercase tracking-wider hover:bg-primary-hover transition-colors">Checkout</a>
            </div>
        </div>
    </aside>
</div>

<script>
window.LazyCart = (function () {
    const ROUTES = {
        add:      @json(route('shop.cart.add')),
        fragment: @json(route('shop.cart.fragment')),
        removeTpl: @json(route('shop.cart.remove', ['key' => '__KEY__'])),
    };
    const CSRF = @json(csrf_token());

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
        if (window.Swal) {
            Swal.fire({ title: message, icon: icon || 'success', toast: true, position: 'top-end', showConfirmButton: false, timer: 2500, timerProgressBar: true });
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
</script>
