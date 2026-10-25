/*
 * Motion Visual — the script behind every Motion Visual layer
 * (Column / Container → Extra → Motion Visual; markup in resources/views/frontend/motion-visual).
 *
 * The shapes animate with CSS by themselves. This adds two things:
 *   - the dots: drawn on the layer's <canvas> from its data-mv-dots-* attributes
 *     (count, size, color, speed, lines between near dots, what the pointer does to them);
 *   - parallax: each item (data-mv-depth) follows the pointer, deeper ones further.
 *
 * How much runs depends on the device: desktop gets everything; tablets and phones get what
 * the layer's mv_tablet / mv_mobile asks for (full | simple: half the dots, no pointer |
 * static: one still frame). Reduced motion is always static. Nothing runs while the layer is
 * off screen or the tab is hidden, and the pointer loop sleeps once things have settled.
 *
 * window.FalconMotionVisual.init(scope) / .destroy(root) — the builder's canvas uses these
 * for the previews it draws and replaces.
 */
(function () {
    'use strict';
    if (window.FalconMotionVisual) return;

    var reducedQuery = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
    var finePointer = window.matchMedia ? window.matchMedia('(hover: hover) and (pointer: fine)').matches : true;

    function num(v, d) { var n = parseFloat(v); return isNaN(n) ? d : n; }

    function level(root) {
        if (reducedQuery && reducedQuery.matches) return 'static';
        var w = window.innerWidth, d = root.dataset;
        if (w <= num(d.mvBpSm, 800)) return d.mvMobile || 'simple';
        if (w <= num(d.mvBpMed, 1100)) return d.mvTablet || 'simple';
        return 'full';
    }

    /** [r, g, b, alpha] from #rgb, #rrggbb or #rrggbbaa (the builder's picker adds the alpha). */
    function rgb(hex) {
        var h = String(hex || '#005ab4').replace('#', '');
        if (h.length === 3) h = h.replace(/./g, '$&$&');
        var n = parseInt(h.slice(0, 6), 16);
        var a = h.length === 8 ? parseInt(h.slice(6, 8), 16) / 255 : 1;
        return isNaN(n) ? [0, 90, 180, 1] : [(n >> 16) & 255, (n >> 8) & 255, n & 255, isNaN(a) ? 1 : a];
    }

    function Layer(root) {
        var d = root.dataset;
        this.root = root;
        this.host = root.parentElement || root;
        this.items = Array.prototype.slice.call(root.querySelectorAll('[data-mv-depth]')).map(function (el) {
            return { el: el, d: num(el.getAttribute('data-mv-depth'), 1) };
        });
        this.canvas = root.querySelector('.falcon-mv__dots');
        this.dotsOpt = this.canvas ? {
            count: num(d.mvDotsCount, 60), size: num(d.mvDotsSize, 3), color: rgb(d.mvDotsColor),
            // "random": every dot has a color of its own that keeps turning round the wheel.
            random: d.mvDotsColor === 'random',
            speed: num(d.mvDotsSpeed, 0.6) * num(d.mvSpeed, 1), links: d.mvDotsLinks === '1',
            dist: num(d.mvDotsLinkDist, 120), mouse: d.mvDotsMouse || 'none'
        } : null;
        this.dots = [];
        this.visible = false;
        this.pointer = { x: 0, y: 0, px: -9999, py: -9999, inside: false };
        this.current = { x: 0, y: 0 };
        this.frame = 0;
        this.cleanups = [];
        this.start();
    }

    Layer.prototype.on = function (target, type, fn, opts) {
        target.addEventListener(type, fn, opts || false);
        this.cleanups.push(function () { target.removeEventListener(type, fn, opts || false); });
    };

    Layer.prototype.start = function () {
        var self = this, root = this.root;
        this.level = level(root);
        root.classList.toggle('is-static', this.level === 'static');
        this.size();

        if ('IntersectionObserver' in window) {
            this.io = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) { self.visible = e.isIntersecting; self.wake(); });
            }, { rootMargin: '100px 0px' });
            this.io.observe(root);
        } else {
            this.visible = true;
        }
        if ('ResizeObserver' in window) {
            this.ro = new ResizeObserver(function () { self.size(); self.wake(); });
            this.ro.observe(root);
        }
        this.on(document, 'visibilitychange', function () { self.wake(); });
        var resizeT = 0;
        this.on(window, 'resize', function () {
            clearTimeout(resizeT);
            resizeT = setTimeout(function () {
                var was = self.level;
                self.level = level(root);
                root.classList.toggle('is-static', self.level === 'static');
                if (was !== self.level) { self.seed(); self.wake(); }
            }, 200);
        }, { passive: true });

        // The pointer is read over the whole column or container the layer covers, since the
        // layer itself lets every click through.
        if (root.dataset.mvMouse === '1' && finePointer) {
            this.on(this.host, 'pointermove', function (e) {
                if (self.level !== 'full') return;
                var r = root.getBoundingClientRect();
                var p = self.pointer;
                p.px = e.clientX - r.left;
                p.py = e.clientY - r.top;
                p.x = Math.max(-1, Math.min(1, (p.px / r.width) * 2 - 1));
                p.y = Math.max(-1, Math.min(1, (p.py / r.height) * 2 - 1));
                p.inside = true;
                self.wake();
            }, { passive: true });
            this.on(this.host, 'pointerleave', function () {
                var p = self.pointer;
                p.x = 0; p.y = 0; p.px = -9999; p.py = -9999; p.inside = false;
                self.wake();
            }, { passive: true });
        }
        this.wake();
    };

    // ── The dots ──────────────────────────────────────────────────────────────
    Layer.prototype.size = function () {
        if (!this.canvas) return;
        var r = this.root.getBoundingClientRect();
        var ratio = Math.min(2, window.devicePixelRatio || 1);
        this.w = Math.max(1, r.width);
        this.h = Math.max(1, r.height);
        this.canvas.width = Math.round(this.w * ratio);
        this.canvas.height = Math.round(this.h * ratio);
        this.ctx = this.canvas.getContext('2d');
        this.ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        this.seed();
    };

    Layer.prototype.seed = function () {
        if (!this.dotsOpt || !this.w) return;
        var o = this.dotsOpt;
        // The count is for a 1200 × 600 area; a smaller box gets proportionally fewer, so a
        // narrow column is not a blizzard. Calmer devices get half.
        var n = Math.round(o.count * Math.max(0.25, Math.min(2, (this.w * this.h) / 720000)));
        if (this.level !== 'full') n = Math.round(n / 2);
        n = Math.max(3, n);
        var dots = this.dots.slice(0, n);
        while (dots.length < n) {
            var a = Math.random() * Math.PI * 2, v = (0.15 + Math.random() * 0.35) * o.speed;
            dots.push({ x: Math.random() * this.w, y: Math.random() * this.h, vx: Math.cos(a) * v, vy: Math.sin(a) * v,
                r: o.size * (0.55 + Math.random() * 0.9), a: 0.35 + Math.random() * 0.55, ox: 0, oy: 0,
                hue: Math.random() * 360, spin: (Math.random() < 0.5 ? -1 : 1) * (0.6 + Math.random() * 0.8) });
        }
        this.dots = dots;
    };

    Layer.prototype.drawDots = function (dt) {
        var o = this.dotsOpt, ctx = this.ctx, w = this.w, h = this.h, dots = this.dots, p = this.pointer;
        if (!o || !ctx) return;
        var moving = this.level !== 'static', k = dt / 16.7, c = o.color, reach = 130;
        // One turn of the color wheel takes about 20 s at speed 1.
        var turn = moving && o.random ? 0.3 * k * Math.max(0.25, num(this.root.dataset.mvSpeed, 1)) : 0;
        var paint = function (dot, alpha) {
            return o.random
                ? 'hsla(' + Math.round(dot.hue) + ',80%,58%,' + alpha.toFixed(3) + ')'
                : 'rgba(' + c[0] + ',' + c[1] + ',' + c[2] + ',' + (alpha * c[3]).toFixed(3) + ')';
        };
        ctx.clearRect(0, 0, w, h);
        for (var i = 0; i < dots.length; i++) {
            var d = dots[i];
            if (moving) {
                d.x += d.vx * k; d.y += d.vy * k;
                if (d.x < -10) d.x = w + 10; else if (d.x > w + 10) d.x = -10;
                if (d.y < -10) d.y = h + 10; else if (d.y > h + 10) d.y = -10;
                // The pointer pushes dots away or draws them in; the push eases back by itself.
                var tx = 0, ty = 0;
                if (p.inside && o.mouse !== 'none') {
                    var dx = d.x - p.px, dy = d.y - p.py, dist = Math.sqrt(dx * dx + dy * dy);
                    if (dist < reach && dist > 0.1) {
                        var f = (1 - dist / reach) * 28 * (o.mouse === 'attract' ? -0.6 : 1);
                        tx = dx / dist * f; ty = dy / dist * f;
                    }
                }
                d.ox += (tx - d.ox) * 0.08 * k;
                d.oy += (ty - d.oy) * 0.08 * k;
            }
        }
        if (o.links) {
            // Lines between near dots, found through a grid so the cost stays near linear.
            var cell = o.dist, grid = {}, lw = Math.max(0.6, o.size / 3);
            for (var g = 0; g < dots.length; g++) {
                var dg = dots[g], key = Math.floor((dg.x + dg.ox) / cell) + ':' + Math.floor((dg.y + dg.oy) / cell);
                (grid[key] = grid[key] || []).push(dg);
            }
            ctx.lineWidth = lw;
            for (var m = 0; m < dots.length; m++) {
                var a = dots[m], ax = a.x + a.ox, ay = a.y + a.oy, gx = Math.floor(ax / cell), gy = Math.floor(ay / cell);
                for (var ix = gx - 1; ix <= gx + 1; ix++) {
                    for (var iy = gy - 1; iy <= gy + 1; iy++) {
                        var bucket = grid[ix + ':' + iy];
                        if (!bucket) continue;
                        for (var b = 0; b < bucket.length; b++) {
                            var e = bucket[b];
                            if (e === a || e.x + e.ox < ax) continue; // each pair once
                            var ex = e.x + e.ox - ax, ey = e.y + e.oy - ay, dd = Math.sqrt(ex * ex + ey * ey);
                            if (dd >= cell) continue;
                            ctx.strokeStyle = paint(a, 0.35 * (1 - dd / cell));
                            ctx.beginPath(); ctx.moveTo(ax, ay); ctx.lineTo(ax + ex, ay + ey); ctx.stroke();
                        }
                    }
                }
            }
        }
        for (var j = 0; j < dots.length; j++) {
            var q = dots[j];
            if (turn) q.hue = (q.hue + turn * q.spin + 360) % 360;
            ctx.fillStyle = paint(q, q.a);
            ctx.beginPath(); ctx.arc(q.x + q.ox, q.y + q.oy, q.r, 0, Math.PI * 2); ctx.fill();
        }
    };

    // ── Parallax ──────────────────────────────────────────────────────────────
    Layer.prototype.paintItems = function (k) {
        var c = this.current, p = this.pointer;
        c.x += (p.x - c.x) * k;
        c.y += (p.y - c.y) * k;
        var still = Math.abs(p.x - c.x) < 0.001 && Math.abs(p.y - c.y) < 0.001;
        if (still) { c.x = p.x; c.y = p.y; }
        var reach = Math.min(40, this.root.clientWidth / 40);
        this.items.forEach(function (it) {
            it.el.style.transform = (c.x || c.y)
                ? 'translate3d(' + (c.x * it.d * reach).toFixed(2) + 'px,' + (c.y * it.d * reach).toFixed(2) + 'px,0)'
                : '';
        });
        return still;
    };

    // ── One loop for both ─────────────────────────────────────────────────────
    Layer.prototype.wake = function () {
        var on = this.visible && !document.hidden;
        this.root.classList.toggle('is-paused', !on);
        if (!on || this.frame) return;
        var self = this, last = 0;
        var tick = function (now) {
            self.frame = 0;
            if (!self.visible || document.hidden) { self.root.classList.add('is-paused'); return; }
            var dt = last ? Math.min(64, now - last) : 16.7;
            last = now;
            // The same feel at 60 Hz and 144 Hz: the share of the way closed per frame
            // depends on how long the frame was (like gsap.quickTo).
            var settled = self.paintItems(1 - Math.pow(1 - 0.08, dt / 16.7));
            if (self.dotsOpt) self.drawDots(dt);
            // Dots move for as long as they are seen; without them the loop stops once the
            // items have settled behind the pointer.
            if ((self.dotsOpt && self.level !== 'static') || !settled) {
                self.frame = requestAnimationFrame(tick);
            }
        };
        this.frame = requestAnimationFrame(tick);
    };

    Layer.prototype.destroy = function () {
        if (this.frame) cancelAnimationFrame(this.frame);
        if (this.io) this.io.disconnect();
        if (this.ro) this.ro.disconnect();
        this.cleanups.forEach(function (fn) { fn(); });
        this.cleanups = [];
        this.root.__fmv = null;
    };

    function init(scope) {
        var list = [];
        if (scope && scope.matches && scope.matches('[data-falcon-mv]')) list = [scope];
        else list = (scope || document).querySelectorAll('[data-falcon-mv]');
        Array.prototype.forEach.call(list, function (root) {
            if (!root.__fmv) root.__fmv = new Layer(root);
        });
    }

    window.FalconMotionVisual = {
        init: init,
        destroy: function (root) { if (root && root.__fmv) root.__fmv.destroy(); }
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { init(); });
    else init();
})();
