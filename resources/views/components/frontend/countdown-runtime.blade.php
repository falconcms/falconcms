{{-- Countdown — the one script that ticks every countdown on the page.

     Emitted after each countdown (and once in the builder), made safe to repeat by the flag
     below rather than @once: the theme renders builder content more than once per request
     and a spent @once would leave the visible copy with no script (see code-block.blade.php).

     It needs no setup per element. Once a second it looks up every .fcd[data-fcd] in the
     document and works its deadline out again from the data attribute, so an element added
     later — a builder canvas preview swapped in by Vue, a section loaded by a script — is
     picked up on the next tick, and a page served from the page cache counts from the real
     time rather than from when it was cached.

     Mirrors FalconCms\Core\Support\Countdown (split, dailyEndMs, pad). --}}
<script>
(function () {
    'use strict';
    if (window.__falconCountdown) { window.__falconCountdown.tick(); return; }

    var UNITS = [['days', 86400], ['hours', 3600], ['minutes', 60], ['seconds', 1]];
    var LIMIT = { hours: 24, minutes: 60, seconds: 60 };
    var previewStarts = {};   // evergreen starts in the builder: kept in memory, never stored

    // The site timezone's offset from UTC at a moment, in minutes. Intl knows every IANA
    // zone and its daylight saving rules; the browser's own offset is the last resort.
    function tzOffset(tz, ms) {
        try {
            var parts = {};
            new Intl.DateTimeFormat('en-US', {
                timeZone: tz, hourCycle: 'h23', year: 'numeric', month: '2-digit', day: '2-digit',
                hour: '2-digit', minute: '2-digit', second: '2-digit'
            }).formatToParts(new Date(ms)).forEach(function (p) { parts[p.type] = p.value; });
            var asUtc = Date.UTC(+parts.year, +parts.month - 1, +parts.day, +parts.hour % 24, +parts.minute, +parts.second);
            return Math.round((asUtc - Math.floor(ms / 1000) * 1000) / 60000);
        } catch (e) {
            return -new Date(ms).getTimezoneOffset();
        }
    }

    // Countdown::dailyEndMs()
    function dailyEnd(cfg, now) {
        var hm = String(cfg.time || '17:00').split(':');
        var h = parseInt(hm[0], 10) || 0, m = parseInt(hm[1], 10) || 0;
        var days = cfg.days && cfg.days.length ? cfg.days : [0, 1, 2, 3, 4, 5, 6];
        var wall = new Date(now + tzOffset(cfg.tz, now) * 60000);   // its UTC fields = the site's wall clock
        for (var i = 0; i < 8; i++) {
            var local = Date.UTC(wall.getUTCFullYear(), wall.getUTCMonth(), wall.getUTCDate() + i, h, m, 0);
            if (days.indexOf(new Date(local).getUTCDay()) === -1) continue;
            // Offset taken at the deadline itself, so a daylight saving change between now
            // and then does not move it by an hour.
            var end = local - tzOffset(cfg.tz, local - tzOffset(cfg.tz, now) * 60000) * 60000;
            if (end > now) return end;
        }
        return now;
    }

    function evergreenEnd(el, cfg, now) {
        var key = 'falcon-countdown:' + cfg.id;
        var length = (cfg.ever || 86400) * 1000;
        if (cfg.preview) {
            if (!previewStarts[key] || previewStarts[key].length !== length) previewStarts[key] = { at: now, length: length };
            return previewStarts[key].at + length;
        }
        var end = 0;
        try { end = parseInt(localStorage.getItem(key), 10) || 0; } catch (e) {}
        if (!end || (cfg.restart && end <= now)) {
            end = now + length;
            try { localStorage.setItem(key, String(end)); } catch (e) {}
        }
        return end;
    }

    function config(el) {
        if (!el.__fcdCfg || el.__fcdRaw !== el.getAttribute('data-fcd')) {
            el.__fcdRaw = el.getAttribute('data-fcd');
            try { el.__fcdCfg = JSON.parse(el.__fcdRaw) || {}; } catch (e) { el.__fcdCfg = {}; }
            el.__fcdTop = null;
        }
        return el.__fcdCfg;
    }

    function pad(n, zero) { return zero && n < 10 ? '0' + n : String(n); }

    function update(el, now) {
        var cfg = config(el);
        var end = cfg.mode === 'daily' ? dailyEnd(cfg, now)
                : cfg.mode === 'evergreen' ? evergreenEnd(el, cfg, now)
                : (cfg.end || 0);
        var rest = Math.max(0, Math.floor((end - now) / 1000));
        var shown = cfg.units || [];

        // Countdown::split()
        var values = {}, left = rest;
        UNITS.forEach(function (u) {
            if (shown.indexOf(u[0]) === -1) return;
            values[u[0]] = Math.floor(left / u[1]);
            left -= values[u[0]] * u[1];
        });

        // The first unit on show has no natural limit (a deadline can be 400 days away),
        // so its ring measures against the value it first had.
        var top = shown[0];
        if (top && (el.__fcdTop === null || el.__fcdTop === undefined || values[top] > el.__fcdTop)) el.__fcdTop = Math.max(1, values[top]);

        el.querySelectorAll('.fcd-item[data-unit]').forEach(function (item) {
            var unit = item.getAttribute('data-unit');
            if (!(unit in values)) return;
            var num = item.querySelector('.fcd-num');
            var text = pad(values[unit], cfg.zero);
            var seen = item.__fcdSeen;
            item.__fcdSeen = true;
            if (num && num.textContent !== text) {
                num.textContent = text;
                // Not on the first tick: the server's number being corrected is not a flip.
                if (cfg.design === 'flip' && seen) {
                    num.classList.remove('is-flip');
                    void num.offsetWidth;
                    num.classList.add('is-flip');
                }
            }
            var bar = item.querySelector('.fcd-bar');
            if (bar) {
                var max = unit === top ? el.__fcdTop : (LIMIT[unit] || 60);
                var pct = Math.max(0, Math.min(100, (values[unit] / max) * 100));
                bar.setAttribute('stroke-dashoffset', String(100 - pct));
            }
        });

        var ended = rest <= 0 && cfg.mode !== 'daily';
        if (!ended || cfg.preview) return;
        if (cfg.expire === 'hide') {
            el.style.display = 'none';
        } else if (cfg.expire === 'message') {
            var units = el.querySelector('.fcd-units'), msg = el.querySelector('.fcd-expired');
            if (units) units.hidden = true;
            if (msg) msg.hidden = false;
        } else if (cfg.expire === 'redirect' && cfg.url && !el.__fcdGone) {
            el.__fcdGone = true;
            var target = new URL(cfg.url, location.href);
            if (target.href !== location.href) location.assign(target.href);
        }
    }

    var timer = null;
    function tick() {
        var now = Date.now();
        document.querySelectorAll('.fcd[data-fcd]').forEach(function (el) {
            try { update(el, now); } catch (e) {}
        });
        clearTimeout(timer);
        // On the second boundary, so every countdown on the page changes together.
        timer = setTimeout(tick, 1000 - (Date.now() % 1000) + 5);
    }

    window.__falconCountdown = { tick: tick };
    tick();
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', tick);
})();
</script>
