/**
 * Lazy Builder ↔ Shortcode Converter
 *
 * Converts builder JSON ↔ [falcon_section] shortcodes inside the admin rich editor.
 * - On page load : JSON → shortcodes (display-side conversion)
 * - On form submit: shortcodes → JSON (editor_type stays 'rich' to preserve the active tab)
 *
 * All settings are plain human-readable snake_case attributes — no base64.
 * Mirrors BuilderShortcodeConverter.php exactly.
 */
(function () {
    'use strict';

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    function num(v) {
        if (v === null || v === undefined || v === '') return null;
        if (typeof v === 'number') return v;
        var n = parseFloat(v);
        if (isNaN(n)) return v;
        return n === Math.floor(n) ? Math.floor(n) : n;
    }

    /** Push key="value" onto array; skip null/undefined/empty */
    function attr(a, key, value) {
        if (value === null || value === undefined || value === '') return;
        a.push(key + '="' + value + '"');
    }

    /** Push key="value" onto array; skip null/undefined/empty AND skip === default */
    function attrIf(a, key, value, skip) {
        if (value === null || value === undefined || value === '' || value === skip) return;
        a.push(key + '="' + value + '"');
    }

    /** Append key="value" to a string; skip null/undefined/empty AND skip === default */
    function attrI(str, key, value, skip) {
        if (value === null || value === undefined || value === '') return str;
        if (skip !== undefined && value === skip) return str;
        return str + ' ' + key + '="' + value + '"';
    }

    // ── Custom element helpers (shared by serialize + parse) ────────────────────
    /** Unicode-safe base64 encode of a JSON-able value */
    function ceEncode(val) {
        try { return btoa(unescape(encodeURIComponent(JSON.stringify(val)))); }
        catch (e) { return ''; }
    }
    /** Decode a base64(JSON) attribute value */
    function ceDecode(str) {
        try { return JSON.parse(decodeURIComponent(escape(atob(str)))); }
        catch (e) { return null; }
    }

    // ── Lossless fidelity — readable "extra" attributes (mirror of PHP, no base64) ──
    // The per-type serializers emit only a subset of each settings object; the rest is
    // appended as plain attributes named after the literal setting key (e.g. dividerWidth="60")
    // so a rich-editor round-trip stays lossless AND the shortcode stays human-readable.
    var FIDELITY_STRUCTURAL = ['id', 'type', 'width', 'width_tablet', 'width_mobile', 'hide_mobile', 'hide_tablet', 'hide_desktop'];
    // Element types with a concrete built-in serializer/parser case (parsed via parseElementInner).
    var BUILTIN_TYPES = ['heading', 'title', 'text', 'button', 'image', 'spacer', 'video', 'menu', 'row', 'counter', 'star_rating', 'gallery'];
    var _suppressExtras = false;

    function isTransientKey(k) {
        return !k || k.charAt(0) === '_' || k === 'isHovered' || k === 'isTextHovered';
    }

    /** Custom element defs keyed by type (mirror of PHP customDefs). */
    function customDefsJs() {
        var d = (typeof window !== 'undefined' && window.falconCustomElements) ? window.falconCustomElements : {};
        var out = {};
        Object.keys(d).forEach(function (k) { var def = d[k]; if (def) out[def.type || k] = def; });
        return out;
    }

    /** Attribute names on the opening tag of a shortcode string. */
    function tagAttrNames(sc) {
        var m = /^\s*\[[a-z0-9_]+([^\]]*)\]/i.exec(sc || '');
        if (!m) return [];
        var names = [], rx = /([A-Za-z_][\w-]*)\s*=\s*"/g, mm;
        while ((mm = rx.exec(m[1])) !== null) names.push(mm[1]);
        return names;
    }

    function looseEq(a, b) {
        if (typeof a === 'boolean' || typeof b === 'boolean') return !!a === !!b;
        if (a === null || b === null || a === undefined || b === undefined) return a === b;
        if (typeof a === 'object' || typeof b === 'object') return JSON.stringify(a) === JSON.stringify(b);
        return String(a) === String(b);
    }

    function coerceAttr(v) {
        if (v === 'true') return true;
        if (v === 'false') return false;
        if (v === 'null') return null;
        if (v !== '' && !isNaN(v)) {
            if (/^-?\d+$/.test(v)) { var i = parseInt(v, 10); return (String(i) === v) ? i : v; }
            return parseFloat(v);
        }
        return v;
    }

    function stripChildren(node) {
        var n = {}; Object.keys(node || {}).forEach(function (k) { if (k !== 'columns' && k !== 'elements') n[k] = node[k]; });
        return n;
    }

    /** Append readable "extra" attributes for any scalar setting the per-type round-trip drops. */
    function appendExtras(sc, settings, recovered) {
        if (_suppressExtras) return sc;
        settings = settings || {}; recovered = recovered || {};
        var extras = [];
        Object.keys(settings).forEach(function (k) {
            if (isTransientKey(k)) return;
            var v = settings[k];
            if (v === null || v === undefined || v === '' || (typeof v === 'object')) return;
            if (Object.prototype.hasOwnProperty.call(recovered, k) && looseEq(recovered[k], v)) return;
            var val = (typeof v === 'boolean') ? (v ? 'true' : 'false') : String(v);
            if (val.indexOf('"') !== -1) return;
            extras.push(k + '="' + val + '"');
        });
        if (!extras.length) return sc;
        return sc.replace(/^(\s*\[[a-z0-9_]+\s)/i, '$1' + extras.join(' ') + ' ');
    }

    /** Merge back any attribute the per-type serializer doesn't itself emit (and isn't structural). */
    function mergeExtras(attrs, settings, perTypeNames) {
        Object.keys(attrs || {}).forEach(function (k) {
            if (!k) return;
            if (FIDELITY_STRUCTURAL.indexOf(k) !== -1) return;
            if (perTypeNames.indexOf(k) !== -1) return;
            settings[k] = (typeof attrs[k] === 'string') ? coerceAttr(attrs[k]) : attrs[k];
        });
        return settings;
    }

    /** The attribute names the per-type serializer alone emits for a node (extras suppressed). */
    function perTypeNames(kind, node) {
        var prev = _suppressExtras; _suppressExtras = true; var sc;
        try {
            if (kind === 'container')   sc = containerToShortcode(stripChildren(node));
            else if (kind === 'column') sc = columnToShortcode(stripChildren(node));
            else                        sc = elementToShortcode(stripChildren(node));
        } finally { _suppressExtras = prev; }
        return (typeof sc === 'string') ? tagAttrNames(sc) : [];
    }

    /** What parsing a freshly-serialized element shortcode recovers — used to find dropped fields. */
    function elementRecovered(sc, type, settings) {
        var m = /^\s*\[([^\s\]\/]+)\s*([\s\S]*?)(?:\/\]|\]([\s\S]*)\[\/\1\])\s*$/i.exec(sc || '');
        if (!m) return settings;
        var attrStr = m[2], inner = m[3] || '';
        var cdef = customDefsJs()[type];
        var el = (cdef && BUILTIN_TYPES.indexOf(type) === -1)
            ? parseCustomElement(cdef, attrStr, inner)
            : parseElementInner(type, attrStr, inner);
        return (el && el.settings) ? el.settings : settings;
    }
    var _CE_SUFFIXES = ['_hover_color','_hover_bg','_color','_bg','_typo','_pad','_margin'];
    function _ceHasSuffix(k) { for (var i=0;i<_CE_SUFFIXES.length;i++){ if (k.endsWith(_CE_SUFFIXES[i])) return true; } return false; }
    /** Derive the storage key for a param. Array param_name: suffixed → first entry; bare targets → heading slug. */
    function ceAutoKey(p) {
        var pn = p.param_name;
        if (Array.isArray(pn) && pn.length) {
            if (_ceHasSuffix(pn[0])) return pn[0];
            return p.heading ? p.heading.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '') : ('cf_' + pn.join('_'));
        }
        if (pn) return pn;
        if (p.heading) return p.heading.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
        return null;
    }
    /** Flat list of {key,type,value} for a custom element definition (params or fields) */
    function ceFieldList(def) {
        if (def.params && def.params.length) {
            return def.params.map(function (p) { return { key: ceAutoKey(p), type: p.type, value: p.value }; });
        }
        if (def.fields) {
            return Object.keys(def.fields).map(function (k) {
                return { key: k, type: def.fields[k].type, value: def.fields[k]['default'] };
            });
        }
        return [];
    }
    /** Array of base field keys (for prefix-based pruning of removed fields). null = allow all */
    function ceAllowedKeys(def) {
        var list = ceFieldList(def);
        if (!list.length) return null;
        return list.map(function (f) { return f.key; }).filter(Boolean);
    }
    /** True if `key` equals a base or starts with `base + "_"` (covers _top, _size, _tablet, _unit, _target, _dynamic…) */
    function ceKeyAllowed(bases, key) {
        if (!bases) return true;
        for (var i = 0; i < bases.length; i++) {
            if (key === bases[i] || key.indexOf(bases[i] + '_') === 0) return true;
        }
        return false;
    }
    /** Global setting keys every element shares (Extra tab: visibility cond, animation, css). Always serialized. */
    var CE_GLOBAL_KEYS = [
        'cssClass', 'cssId',
        'anim_type', 'anim_duration', 'anim_delay', 'anim_easing',
        'vis_condition', 'vis_date_from', 'vis_date_to'
    ];
    function ceGlobalAllowed(key) {
        return CE_GLOBAL_KEYS.indexOf(key) !== -1;
    }
    /** Parse a numeric-looking string to Number, otherwise return as-is (keeps units like "px") */
    function maybeNum(v) {
        if (typeof v === 'string' && /^-?\d+(\.\d+)?$/.test(v)) return Number(v);
        return v;
    }

    /** Append _tablet/_mobile variant attrs onto array; skip null/undefined/empty */
    function rAttr(a, attrKey, s, sKey) {
        var tv = s[sKey + '_tablet'];
        var mv = s[sKey + '_mobile'];
        if (tv !== null && tv !== undefined && tv !== '') a.push(attrKey + '_tablet="' + tv + '"');
        if (mv !== null && mv !== undefined && mv !== '') a.push(attrKey + '_mobile="' + mv + '"');
    }

    /**
     * Inject _tablet/_mobile responsive variants into a settings object.
     * defs: array of [settingsKey, attrKey, parserFn|null]
     * Only sets the key if the attr is present and non-empty.
     */
    function addRespProps(s, a, defs) {
        ['tablet', 'mobile'].forEach(function(dev) {
            defs.forEach(function(def) {
                var sk = def[0], ak = def[1] + '_' + dev, parser = def[2];
                var raw = a[ak];
                if (raw !== undefined && raw !== null && raw !== '')
                    s[sk + '_' + dev] = parser ? parser(raw) : raw;
            });
        });
    }

    function parseAttrs(str) {
        var out = {};
        var rx = /(\w+)\s*=\s*"([^"]*)"/g;
        var m;
        while ((m = rx.exec(str)) !== null) {
            out[m[1]] = m[2];
        }
        return out;
    }

    function visibilityFromAttrs(a) {
        return {
            mobile:  (a.hide_mobile  || '') !== 'yes',
            tablet:  (a.hide_tablet  || '') !== 'yes',
            desktop: (a.hide_desktop || '') !== 'yes'
        };
    }

    function generateId() {
        return Math.random().toString(36).substring(2, 11);
    }

    // -------------------------------------------------------------------------
    // JSON → Shortcodes
    // -------------------------------------------------------------------------

    function jsonToShortcodes(jsonStr) {
        var layout;
        try { layout = JSON.parse(jsonStr); } catch (e) { return jsonStr; }
        if (!Array.isArray(layout) || !layout.length) return jsonStr;
        return layout.map(containerToShortcode).join('\n\n');
    }

    function containerToShortcode(c) {
        var s = c.settings || {};
        var a = [];

        a.push('id="'   + (c.id   || '') + '"');
        a.push('type="' + (c.type || 'container') + '"');

        attr(a, 'status',        s.status);
        attr(a, 'content_width', s.contentWidth);

        // Height (responsive)
        attr(a, 'height',        s.height);
        rAttr(a, 'height', s, 'height');
        attr(a, 'custom_height', s.customHeight);
        rAttr(a, 'custom_height', s, 'customHeight');
        attr(a, 'min_height',    s.minHeight);
        rAttr(a, 'min_height', s, 'minHeight');

        // Background (responsive)
        attr(a, 'bg_type',    s.bgType);
        attr(a, 'bg_color',   s.bgColor);
        rAttr(a, 'bg_color', s, 'bgColor');
        attrIf(a, 'bg_opacity', s.bgColorOpacity, 1);
        rAttr(a, 'bg_opacity', s, 'bgColorOpacity');

        // Gradient
        attr(a, 'gradient_start',        s.bgGradientStartColor);
        attr(a, 'gradient_end',          s.bgGradientEndColor);
        attrIf(a, 'gradient_type',       s.bgGradientType, 'linear');
        attrIf(a, 'gradient_angle',      s.bgGradientAngle, 180);
        attrIf(a, 'gradient_start_pos',  s.bgGradientStartPosition, 0);
        attrIf(a, 'gradient_end_pos',    s.bgGradientEndPosition, 100);

        // Background image (responsive)
        attr(a, 'bg_image',       s.bgImage);
        attr(a, 'bg_position',    s.bgImagePosition);
        rAttr(a, 'bg_position', s, 'bgImagePosition');
        attrIf(a, 'bg_size',      s.bgImageSize, 'auto');
        rAttr(a, 'bg_size', s, 'bgImageSize');
        attrIf(a, 'bg_repeat',    s.bgImageRepeat, 'no-repeat');
        rAttr(a, 'bg_repeat', s, 'bgImageRepeat');
        attrIf(a, 'bg_parallax',  s.bgImageParallax, 'none');
        attrIf(a, 'bg_blend',     s.bgImageBlendMode, 'normal');
        rAttr(a, 'bg_blend', s, 'bgImageBlendMode');

        // Spacing with units and responsive variants
        ['top', 'bottom', 'left', 'right'].forEach(function (side) {
            var cap = side.charAt(0).toUpperCase() + side.slice(1);
            var pk = 'padding' + cap, pu = pk + 'Unit';
            var mk = 'margin'  + cap, mu = mk + 'Unit';

            if (pk in s && s[pk] !== null && s[pk] !== undefined) a.push('padding_' + side + '="' + s[pk] + '"');
            if (pu in s && s[pu] && s[pu] !== 'px') a.push('padding_' + side + '_unit="' + s[pu] + '"');
            ['tablet', 'mobile'].forEach(function(dev) {
                var kd = pk + '_' + dev, ud = pu + '_' + dev;
                if (kd in s && s[kd] !== null && s[kd] !== undefined) a.push('padding_' + side + '_' + dev + '="' + s[kd] + '"');
                if (ud in s && s[ud] && s[ud] !== 'px') a.push('padding_' + side + '_unit_' + dev + '="' + s[ud] + '"');
            });

            if (mk in s && s[mk] !== null && s[mk] !== undefined) a.push('margin_' + side + '="' + s[mk] + '"');
            if (mu in s && s[mu] && s[mu] !== 'px') a.push('margin_' + side + '_unit="' + s[mu] + '"');
            ['tablet', 'mobile'].forEach(function(dev) {
                var kd = mk + '_' + dev, ud = mu + '_' + dev;
                if (kd in s && s[kd] !== null && s[kd] !== undefined) a.push('margin_' + side + '_' + dev + '="' + s[kd] + '"');
                if (ud in s && s[ud] && s[ud] !== 'px') a.push('margin_' + side + '_unit_' + dev + '="' + s[ud] + '"');
            });
        });

        // Flex/alignment (responsive)
        attrIf(a, 'align_items',     s.alignItems,     'stretch');
        rAttr(a, 'align_items', s, 'alignItems');
        attrIf(a, 'justify_content', s.justifyContent, 'flex-start');
        rAttr(a, 'justify_content', s, 'justifyContent');
        attrIf(a, 'flex_wrap',       s.flexWrap,       'wrap');
        rAttr(a, 'flex_wrap', s, 'flexWrap');
        attr(a, 'row_align_content', s.rowAlignContent);
        rAttr(a, 'row_align_content', s, 'rowAlignContent');
        attr(a, 'column_gap', s.columnGap);
        rAttr(a, 'column_gap', s, 'columnGap');

        attrIf(a, 'html_tag',    s.htmlTag, 'div');
        attr(a, 'menu_anchor',   s.menuAnchor);
        attr(a, 'css_class',     s.cssClass);
        attr(a, 'z_index',       s.zIndex);
        attrIf(a, 'overflow',    s.overflow, 'default');
        if (s.sticky) {
            a.push('sticky="yes"');
            if (s.stickyDesktop === false) a.push('sticky_desktop="no"');
            if (s.stickyTablet  === false) a.push('sticky_tablet="no"');
            if (s.stickyMobile  === false) a.push('sticky_mobile="no"');
            attr(a, 'sticky_offset',  s.stickyOffset);
            attr(a, 'sticky_z_index', s.stickyZIndex);
        }

        var v = s.visibility || {};
        if (v.mobile  === false) a.push('hide_mobile="yes"');
        if (v.tablet  === false) a.push('hide_tablet="yes"');
        if (v.desktop === false) a.push('hide_desktop="yes"');

        attr(a, 'link',         s.linkUrl);
        attrIf(a, 'link_target', s.linkTarget, '_self');
        attr(a, 'link_color',   s.linkColor);

        ['Top', 'Right', 'Bottom', 'Left'].forEach(function (side) {
            attr(a, 'border_' + side.toLowerCase(), s['borderSize' + side]);
        });
        attrIf(a, 'border_color', s.borderColor, '#000000');
        [['TopLeft','tl'],['TopRight','tr'],['BottomRight','br'],['BottomLeft','bl']].forEach(function (pair) {
            attr(a, 'radius_' + pair[1], s['borderRadius' + pair[0]]);
        });

        if (s.boxShadow) {
            a.push('box_shadow="yes"');
            attr(a, 'shadow_color',  s.boxShadowColor);
            attr(a, 'shadow_h',      s.boxShadowPositionHorizontal);
            attr(a, 'shadow_v',      s.boxShadowPositionVertical);
            attr(a, 'shadow_blur',   s.boxShadowBlurRadius);
            attr(a, 'shadow_spread', s.boxShadowSpreadRadius);
            attrIf(a, 'shadow_style', s.boxShadowStyle, 'outer');
        }

        var colLines = (c.columns || []).map(function (col) {
            return '  ' + columnToShortcode(col);
        });
        var inner = colLines.length ? '\n' + colLines.join('\n') + '\n' : '';

        var attrStr   = a.join(' ');
        var recovered = containerSettings(parseAttrs(attrStr));
        return appendExtras('[falcon_section ' + attrStr + ']' + inner + '[/falcon_section]', s, recovered);
    }

    function columnToShortcode(col) {
        var s = col.settings || {};
        var a = [];

        a.push('id="'    + (col.id    || '') + '"');
        a.push('width="' + (col.basis || '100%') + '"');

        // Spacing with units and responsive variants
        ['top', 'bottom', 'left', 'right'].forEach(function (side) {
            var cap = side.charAt(0).toUpperCase() + side.slice(1);
            var pk = 'padding' + cap, pu = pk + 'Unit';
            var mk = 'margin'  + cap, mu = mk + 'Unit';

            if (pk in s && s[pk] !== null && s[pk] !== undefined) a.push('padding_' + side + '="' + s[pk] + '"');
            if (pu in s && s[pu] && s[pu] !== 'px') a.push('padding_' + side + '_unit="' + s[pu] + '"');
            ['tablet', 'mobile'].forEach(function(dev) {
                var kd = pk + '_' + dev, ud = pu + '_' + dev;
                if (kd in s && s[kd] !== null && s[kd] !== undefined) a.push('padding_' + side + '_' + dev + '="' + s[kd] + '"');
                if (ud in s && s[ud] && s[ud] !== 'px') a.push('padding_' + side + '_unit_' + dev + '="' + s[ud] + '"');
            });

            if (mk in s && s[mk] !== null && s[mk] !== undefined) a.push('margin_' + side + '="' + s[mk] + '"');
            if (mu in s && s[mu] && s[mu] !== 'px') a.push('margin_' + side + '_unit="' + s[mu] + '"');
            ['tablet', 'mobile'].forEach(function(dev) {
                var kd = mk + '_' + dev, ud = mu + '_' + dev;
                if (kd in s && s[kd] !== null && s[kd] !== undefined) a.push('margin_' + side + '_' + dev + '="' + s[kd] + '"');
                if (ud in s && s[ud] && s[ud] !== 'px') a.push('margin_' + side + '_unit_' + dev + '="' + s[ud] + '"');
            });
        });

        // Layout (responsive)
        attrIf(a, 'alignment',     s.alignment,    'default');
        rAttr(a, 'alignment', s, 'alignment');
        attr(a, 'content_layout',  s.contentLayout);
        attr(a, 'align_h',         s.contentAlignH);
        rAttr(a, 'align_h', s, 'contentAlignH');
        attr(a, 'align_v',         s.contentAlignV);
        rAttr(a, 'align_v', s, 'contentAlignV');
        attr(a, 'gap_width',       s.gapWidth);
        attr(a, 'gap_height',      s.gapHeight);
        attrIf(a, 'html_tag',      s.htmlTag, 'div');
        attr(a, 'css_class',       s.cssClass);
        attr(a, 'css_id',          s.cssId);

        // Colors (responsive)
        attrIf(a, 'bg_color',      s.bgColor, 'transparent');
        rAttr(a, 'bg_color', s, 'bgColor');
        attr(a, 'text_color',      s.textColor);
        attrIf(a, 'bg_opacity',    s.bgColorOpacity, 1);
        rAttr(a, 'bg_opacity', s, 'bgColorOpacity');
        attrIf(a, 'bg_type',       s.bgType, 'color');
        attrIf(a, 'hover_type',    s.hoverType, 'none');

        // Gradient
        attr(a, 'gradient_start',      s.bgGradientStartColor);
        attr(a, 'gradient_end',        s.bgGradientEndColor);
        attrIf(a, 'gradient_angle',    s.bgGradientAngle, 180);

        // Background image (responsive)
        attr(a, 'bg_image',     s.bgImage);
        attr(a, 'bg_position',  s.bgImagePosition);
        rAttr(a, 'bg_position', s, 'bgImagePosition');
        attrIf(a, 'bg_size',    s.bgImageSize, 'auto');
        rAttr(a, 'bg_size', s, 'bgImageSize');
        attrIf(a, 'bg_repeat',  s.bgImageRepeat, 'no-repeat');
        rAttr(a, 'bg_repeat', s, 'bgImageRepeat');
        attrIf(a, 'bg_blend',   s.bgImageBlendMode, 'normal');
        rAttr(a, 'bg_blend', s, 'bgImageBlendMode');

        attr(a, 'link',           s.linkUrl);
        attrIf(a, 'link_target',  s.linkTarget, '_self');
        attr(a, 'z_index',        s.zIndex);
        attrIf(a, 'overflow',     s.overflow, 'default');
        if (s.sticky) {
            a.push('sticky="yes"');
            if (s.stickyDesktop === false) a.push('sticky_desktop="no"');
            if (s.stickyTablet  === false) a.push('sticky_tablet="no"');
            if (s.stickyMobile  === false) a.push('sticky_mobile="no"');
            attr(a, 'sticky_offset',  s.stickyOffset);
            attr(a, 'sticky_z_index', s.stickyZIndex);
        }

        var v = s.visibility || {};
        if (v.mobile  === false) a.push('hide_mobile="yes"');
        if (v.tablet  === false) a.push('hide_tablet="yes"');
        if (v.desktop === false) a.push('hide_desktop="yes"');

        ['Top', 'Right', 'Bottom', 'Left'].forEach(function (side) {
            attr(a, 'border_' + side.toLowerCase(), s['borderSize' + side]);
        });
        attrIf(a, 'border_color', s.borderColor, '#000000');
        [['TopLeft','tl'],['TopRight','tr'],['BottomRight','br'],['BottomLeft','bl']].forEach(function (pair) {
            attr(a, 'radius_' + pair[1], s['borderRadius' + pair[0]]);
        });

        var elems = (col.elements || []).map(function (el) {
            var sc = elementToShortcode(el);
            var eSettings = el.settings || {};
            var recovered = elementRecovered(sc, el.type || 'text', eSettings);
            return appendExtras(sc, eSettings, recovered);
        });
        var inner = elems.length ? ' ' + elems.join(' ') + ' ' : '';

        var attrStr   = a.join(' ');
        var recovered = columnSettings(parseAttrs(attrStr));
        return appendExtras('[falcon_col ' + attrStr + ']' + inner + '[/falcon_col]', s, recovered);
    }

    function elementToShortcode(el) {
        var type = el.type     || 'text';
        var id   = el.id       || '';
        var s    = el.settings || {};
        var base = id ? 'id="' + id + '"' : '';

        var visAttrs = [];
        var v = s.visibility || {};
        if (v.mobile  === false) visAttrs.push('hide_mobile="yes"');
        if (v.tablet  === false) visAttrs.push('hide_tablet="yes"');
        if (v.desktop === false) visAttrs.push('hide_desktop="yes"');
        var vis = visAttrs.length ? ' ' + visAttrs.join(' ') : '';

        switch (type) {
            case 'heading': {
                var a = base;
                a = attrI(a, 'tag',        s.tag,        'h2');
                a = attrI(a, 'font_size',   s.fontSize);
                a = attrI(a, 'font_weight', s.fontWeight);
                a = attrI(a, 'align',       s.textAlign);
                a = attrI(a, 'color',       s.color);
                a = attrI(a, 'css_class',   s.cssClass);
                return '[falcon_heading ' + a.trim() + vis + ']' + (s.title || '').replace(/[\r\n]+/g, '') + '[/falcon_heading]';
            }
            case 'title': {
                var a = base;
                a = attrI(a, 'font_size',      s.fontSize);
                a = attrI(a, 'font_size_unit',  s.fontSizeUnit, 'px');
                a = attrI(a, 'font_weight',     s.fontWeight);
                a = attrI(a, 'align',           s.textAlign);
                a = attrI(a, 'color',           s.titleColor);
                a = attrI(a, 'separator',       s.separator, 'default');
                a = attrI(a, 'separator_color', s.separatorColor);
                a = attrI(a, 'use_link',        s.useLink ? 'yes' : null);
                a = attrI(a, 'link_url',        s.linkUrl);
                a = attrI(a, 'link_color',      s.linkColor);
                a = attrI(a, 'css_class',       s.cssClass);
                return '[falcon_title ' + a.trim() + vis + ']' + (s.title || '').replace(/[\r\n]+/g, '') + '[/falcon_title]';
            }
            case 'text': {
                var a = base;
                a = attrI(a, 'font_size',   s.fontSize);
                a = attrI(a, 'font_weight', s.fontWeight);
                a = attrI(a, 'color',       s.color);
                a = attrI(a, 'align',       s.textAlign);
                a = attrI(a, 'css_class',   s.cssClass);
                return '[falcon_text ' + a.trim() + vis + ']' + (s.content || '').replace(/[\r\n]+/g, '') + '[/falcon_text]';
            }
            case 'button': {
                var a = base;
                a = attrI(a, 'text',       s.text      || 'Button');
                a = attrI(a, 'url',        s.url       || '#');
                a = attrI(a, 'target',     s.target,   '_self');
                a = attrI(a, 'bg_color',   s.bgColor);
                a = attrI(a, 'text_color', s.textColor);
                a = attrI(a, 'align',      s.alignment);
                a = attrI(a, 'size',       s.size);
                a = attrI(a, 'css_class',  s.cssClass);
                return '[falcon_button ' + a.trim() + vis + ' /]';
            }
            case 'image': {
                var a = base;
                a = attrI(a, 'src',       s.src);
                a = attrI(a, 'alt',       s.alt);
                a = attrI(a, 'width',     s.width);
                a = attrI(a, 'align',     s.alignment);
                a = attrI(a, 'css_class', s.cssClass);
                return '[falcon_image ' + a.trim() + vis + ' /]';
            }
            case 'spacer': {
                var a = base;
                a = attrI(a, 'height', s.height !== undefined ? s.height : 20);
                return '[falcon_spacer ' + a.trim() + vis + ' /]';
            }
            case 'video': {
                var a = base;
                a = attrI(a, 'src',    s.src);
                a = attrI(a, 'type',   s.type);
                a = attrI(a, 'width',  s.width);
                a = attrI(a, 'height', s.height);
                return '[falcon_video ' + a.trim() + vis + ' /]';
            }
            case 'menu': {
                var a = base;
                // General
                a = attrI(a, 'menu_id',           s.menuId);
                a = attrI(a, 'layout',             s.layout,         'horizontal');
                a = attrI(a, 'margin_top',         s.marginTop);
                a = attrI(a, 'margin_bottom',      s.marginBottom);
                a = attrI(a, 'item_transition',    s.itemTransition);
                a = attrI(a, 'item_transition_ms', s.itemTransitionMs);
                a = attrI(a, 'submenu_space',      s.submenuSpace,   10);
                var aso = s.arrowScopeObj || {};
                if ((aso.main  ?? true)  === false) a += ' arrow_main="no"';
                if ((aso.active  || false) === true) a += ' arrow_active="yes"';
                if ((aso.submenu || false) === true) a += ' arrow_submenu="yes"';
                a = attrI(a, 'css_class', s.cssClass);
                a = attrI(a, 'css_id',    s.cssId);
                // Design
                a = attrI(a, 'min_height',         s.minHeight);
                a = attrI(a, 'align_items',        s.alignItems,    'flex-start');
                a = attrI(a, 'justification',      s.justification, 'flex-start');
                a = attrI(a, 'font_family',        s.fontFamily,    'inherit');
                a = attrI(a, 'font_size',          s.fontSize);
                a = attrI(a, 'font_weight',        s.fontWeight);
                a = attrI(a, 'line_height',        s.lineHeight);
                a = attrI(a, 'letter_spacing',     s.letterSpacing);
                a = attrI(a, 'text_transform',     s.textTransform);
                a = attrI(a, 'item_padding_top',   s.itemPaddingTop);
                a = attrI(a, 'item_padding_right', s.itemPaddingRight);
                a = attrI(a, 'item_padding_bottom',s.itemPaddingBottom);
                a = attrI(a, 'item_padding_left',  s.itemPaddingLeft);
                a = attrI(a, 'item_spacing',       s.itemSpacing);
                a = attrI(a, 'item_border_radius', s.itemBorderRadius);
                a = attrI(a, 'item_bg_color',      s.itemBgColor);
                a = attrI(a, 'item_bg_color_hover',s.itemBgColorHover);
                a = attrI(a, 'item_color',         s.itemColor);
                a = attrI(a, 'item_color_hover',   s.itemColorHover);
                a = attrI(a, 'item_border_top',    s.itemBorderSizeTop);
                a = attrI(a, 'item_border_right',  s.itemBorderSizeRight);
                a = attrI(a, 'item_border_bottom', s.itemBorderSizeBottom);
                a = attrI(a, 'item_border_left',   s.itemBorderSizeLeft);
                a = attrI(a, 'item_border_color',  s.itemBorderColor);
                a = attrI(a, 'item_border_top_h',  s.itemBorderSizeTopHover);
                a = attrI(a, 'item_border_right_h',s.itemBorderSizeRightHover);
                a = attrI(a, 'item_border_bottom_h',s.itemBorderSizeBottomHover);
                a = attrI(a, 'item_border_left_h', s.itemBorderSizeLeftHover);
                a = attrI(a, 'item_border_color_h',s.itemBorderColorHover);
                // Submenu
                a = attrI(a, 'show_arrows',          s.showArrows,        'yes');
                a = attrI(a, 'submenu_direction',    s.submenuDirection,  'right');
                a = attrI(a, 'submenu_transition',   s.submenuTransition, 'fade');
                a = attrI(a, 'submenu_min_width',    s.submenuMinWidth);
                a = attrI(a, 'submenu_max_width',    s.submenuMaxWidth);
                a = attrI(a, 'sub_sub_direction',    s.subSubMenuDirection, 'right');
                a = attrI(a, 'sub_sub_offset',       s.subSubMenuOffset,  5);
                a = attrI(a, 'submenu_font_family',  s.submenuFontFamily, 'inherit');
                a = attrI(a, 'submenu_font_size',    s.submenuFontSize);
                a = attrI(a, 'submenu_line_height',  s.submenuLineHeight);
                a = attrI(a, 'submenu_letter_sp',    s.submenuLetterSpacing);
                a = attrI(a, 'submenu_text_transform',s.submenuTextTransform);
                a = attrI(a, 'submenu_text_align',   s.submenuTextAlign);
                a = attrI(a, 'submenu_pt',           s.submenuPaddingTop);
                a = attrI(a, 'submenu_pr',           s.submenuPaddingRight);
                a = attrI(a, 'submenu_pb',           s.submenuPaddingBottom);
                a = attrI(a, 'submenu_pl',           s.submenuPaddingLeft);
                a = attrI(a, 'submenu_radius_tl',    s.submenuBorderRadiusTopLeft);
                a = attrI(a, 'submenu_radius_tr',    s.submenuBorderRadiusTopRight);
                a = attrI(a, 'submenu_radius_br',    s.submenuBorderRadiusBottomRight);
                a = attrI(a, 'submenu_radius_bl',    s.submenuBorderRadiusBottomLeft);
                a = attrI(a, 'submenu_shadow',       s.submenuBoxShadow,  'no');
                a = attrI(a, 'submenu_shadow_color', s.submenuShadowColor);
                a = attrI(a, 'submenu_shadow_h',     s.submenuShadowH);
                a = attrI(a, 'submenu_shadow_v',     s.submenuShadowV);
                a = attrI(a, 'submenu_shadow_blur',  s.submenuShadowBlur);
                a = attrI(a, 'submenu_shadow_spread',s.submenuShadowSpread);
                a = attrI(a, 'submenu_thumb_w',      s.submenuThumbWidth);
                a = attrI(a, 'submenu_thumb_h',      s.submenuThumbHeight);
                a = attrI(a, 'submenu_sep_color',    s.submenuSeparatorColor);
                a = attrI(a, 'submenu_bg_color',     s.submenuBgColor);
                a = attrI(a, 'submenu_text_color',   s.submenuTextColor);
                a = attrI(a, 'submenu_text_color_h', s.submenuTextColorHover);
                // Mobile
                a = attrI(a, 'mobile_breakpoint',   s.mobileCollapseBreakpoint,         'tablet');
                a = attrI(a, 'mobile_mode',          s.mobileMenuMode,                  'collapsed');
                a = attrI(a, 'mobile_expand_mode',   s.mobileMenuExpandMode,            'full-width-static');
                a = attrI(a, 'mobile_sidebar_side',  s.mobileMenuSidebarSide,           'left');
                a = attrI(a, 'mobile_trigger_pt',    s.mobileMenuTriggerPaddingTop,     10);
                a = attrI(a, 'mobile_trigger_pr',    s.mobileMenuTriggerPaddingRight,   15);
                a = attrI(a, 'mobile_trigger_pb',    s.mobileMenuTriggerPaddingBottom,  10);
                a = attrI(a, 'mobile_trigger_pl',    s.mobileMenuTriggerPaddingLeft,    15);
                a = attrI(a, 'mobile_trigger_bg',    s.mobileMenuTriggerBgColor);
                a = attrI(a, 'mobile_trigger_color', s.mobileMenuTriggerTextColor);
                a = attrI(a, 'mobile_trigger_text',  s.mobileMenuTriggerText);
                a = attrI(a, 'mobile_expand_icon',   s.mobileMenuTriggerExpandIcon);
                a = attrI(a, 'mobile_collapse_icon', s.mobileMenuTriggerCollapseIcon);
                a = attrI(a, 'mobile_trigger_fs',    s.mobileMenuTriggerFontSize);
                a = attrI(a, 'mobile_trigger_align', s.mobileMenuTriggerHorizontalAlign,'flex-start');
                a = attrI(a, 'mobile_item_min_h',    s.mobileMenuItemMinHeight);
                a = attrI(a, 'mobile_item_pt',       s.mobileMenuItemPaddingTop);
                a = attrI(a, 'mobile_item_pr',       s.mobileMenuItemPaddingRight);
                a = attrI(a, 'mobile_item_pb',       s.mobileMenuItemPaddingBottom);
                a = attrI(a, 'mobile_item_pl',       s.mobileMenuItemPaddingLeft);
                a = attrI(a, 'mobile_text_align',    s.mobileMenuTextAlign,             'left');
                a = attrI(a, 'mobile_indent',        s.mobileMenuIndentSubmenus,        'on');
                a = attrI(a, 'mobile_font_family',   s.mobileMenuFontFamily,            'inherit');
                a = attrI(a, 'mobile_font_size',     s.mobileMenuFontSize);
                a = attrI(a, 'mobile_font_weight',   s.mobileMenuFontWeight);
                a = attrI(a, 'mobile_line_height',   s.mobileMenuLineHeight);
                a = attrI(a, 'mobile_letter_sp',     s.mobileMenuLetterSpacing);
                a = attrI(a, 'mobile_text_transform',s.mobileMenuTextTransform,         'none');
                a = attrI(a, 'mobile_separator',     s.mobileSeparatorEnabled,          'yes');
                a = attrI(a, 'mobile_sep_color',     s.mobileMenuSeparatorColor);
                a = attrI(a, 'mobile_bg_color',      s.mobileMenuBgColor);
                a = attrI(a, 'mobile_bg_color_h',    s.mobileMenuBgColorHover);
                a = attrI(a, 'mobile_text_color',    s.mobileMenuTextColor);
                a = attrI(a, 'mobile_text_color_h',  s.mobileMenuTextColorHover);
                return '[falcon_menu ' + a.trim() + vis + ' /]';
            }
            case 'row': {
                if (el.columns && el.columns.length) {
                    var rowCols = el.columns.map(columnToShortcode);
                    var rowInner = ' ' + rowCols.join(' ') + ' ';
                    return '[falcon_row' + (base ? ' ' + base.trim() : '') + vis + ']' + rowInner + '[/falcon_row]';
                }
                return '[falcon_row ' + base.trim() + vis + ' /]';
            }
            case 'counter': {
                var a = base;
                a = attrI(a, 'end',        s.endValue);
                a = attrI(a, 'start',      s.startValue,       0);
                a = attrI(a, 'prefix',     s.prefix);
                a = attrI(a, 'suffix',     s.suffix);
                a = attrI(a, 'label',      s.label);
                a = attrI(a, 'dur',        s.duration,         2000);
                a = attrI(a, 'dec',        s.decimals,         0);
                a = attrI(a, 'sep',        s.separator);
                a = attrI(a, 'align',      s.textAlign,        'center');
                a = attrI(a, 'num_size',   s.numberFontSize,   '48px');
                a = attrI(a, 'num_weight', s.numberFontWeight, '700');
                a = attrI(a, 'num_color',  s.numberColor);
                a = attrI(a, 'num_family', s.numberFontFamily, 'inherit');
                a = attrI(a, 'num_lh',     s.numberLineHeight,    '1.1');
                a = attrI(a, 'num_ls',     s.numberLetterSpacing, '0px');
                a = attrI(a, 'lbl_size',   s.labelFontSize,   '14px');
                a = attrI(a, 'lbl_weight', s.labelFontWeight, '400');
                a = attrI(a, 'lbl_color',  s.labelColor);
                a = attrI(a, 'lbl_family', s.labelFontFamily, 'inherit');
                a = attrI(a, 'lbl_lh',     s.labelLineHeight,    '1.4');
                a = attrI(a, 'lbl_ls',     s.labelLetterSpacing, '0px');
                a = attrI(a, 'lbl_tt',     s.labelTextTransform, 'none');
                a = attrI(a, 'icon',       s.icon);
                a = attrI(a, 'icon_size',  s.iconSize,   40);
                a = attrI(a, 'icon_color', s.iconColor, '#0091ea');
                a = attrI(a, 'mt',         s.marginTop,    0);
                a = attrI(a, 'mt_unit',    s.marginTopUnit,    'px');
                a = attrI(a, 'mb',         s.marginBottom, 0);
                a = attrI(a, 'mb_unit',    s.marginBottomUnit, 'px');
                a = attrI(a, 'css_class',  s.cssClass);
                a = attrI(a, 'css_id',     s.cssId);
                return '[falcon_counter ' + a.trim() + vis + ' /]';
            }
            case 'star_rating': {
                var a = base;
                a = attrI(a, 'rating',      s.rating,              5);
                a = attrI(a, 'max',         s.maxStars,            5);
                a = attrI(a, 'label',       s.label);
                a = attrI(a, 'size',        s.starSize,            24);
                a = attrI(a, 'color',       s.starColor,           '#f59e0b');
                a = attrI(a, 'empty',       s.emptyColor,          '#d1d5db');
                a = attrI(a, 'align',       s.textAlign,           'center');
                a = attrI(a, 'gap',         s.gap,                 4);
                a = attrI(a, 'lbl_family',  s.labelFontFamily,     'inherit');
                a = attrI(a, 'lbl_size',    s.labelFontSize,       '13px');
                a = attrI(a, 'lbl_weight',  s.labelFontWeight,     '400');
                a = attrI(a, 'lbl_lh',      s.labelLineHeight,     '1.4');
                a = attrI(a, 'lbl_ls',      s.labelLetterSpacing,  '0px');
                a = attrI(a, 'lbl_tt',      s.labelTextTransform,  'none');
                a = attrI(a, 'lbl_color',   s.labelColor,          '#6b7280');
                a = attrI(a, 'mt',          s.marginTop,           0);
                a = attrI(a, 'mt_unit',     s.marginTopUnit,       'px');
                a = attrI(a, 'mb',          s.marginBottom,        0);
                a = attrI(a, 'mb_unit',     s.marginBottomUnit,    'px');
                a = attrI(a, 'css_class',   s.cssClass);
                a = attrI(a, 'css_id',      s.cssId);
                return '[falcon_star_rating ' + a.trim() + vis + ' /]';
            }
            case 'gallery': {
                var a = base;
                var imgs = s.images || [];
                if (imgs.length > 0) {
                    a = attrI(a, 'img_n', imgs.length);
                    for (var i = 0; i < imgs.length; i++) {
                        a = attrI(a, 'img_' + i,           imgs[i].url);
                        a = attrI(a, 'img_' + i + '_a',    imgs[i].alt);
                        a = attrI(a, 'img_' + i + '_c',    imgs[i].caption);
                    }
                }
                a = attrI(a, 'cols',    s.columns,         3);
                a = attrI(a, 'cols_t',  s.columnsTablet,   2);
                a = attrI(a, 'cols_m',  s.columnsMobile,   1);
                a = attrI(a, 'gap',     s.gap,             8);
                a = attrI(a, 'ratio',   s.aspectRatio,     'square');
                a = attrI(a, 'radius',  s.borderRadius,    0);
                a = attrI(a, 'lightbox', s.lightbox !== undefined ? (s.lightbox ? '1' : '0') : null, '1');
                a = attrI(a, 'hover',      s.hoverEffect,          'zoom');
                a = attrI(a, 'cap_align', s.captionAlign,         'center');
                a = attrI(a, 'cap_family',s.captionFontFamily,    'inherit');
                a = attrI(a, 'cap_size',  s.captionFontSize,      '13px');
                a = attrI(a, 'cap_weight',s.captionFontWeight,    '400');
                a = attrI(a, 'cap_lh',    s.captionLineHeight,    '1.4');
                a = attrI(a, 'cap_ls',    s.captionLetterSpacing, '0px');
                a = attrI(a, 'cap_tt',    s.captionTextTransform, 'none');
                a = attrI(a, 'cap_color', s.captionColor,         '#6b7280');
                a = attrI(a, 'img_bw',   s.imgBorderWidth,   0);
                a = attrI(a, 'img_bs',   s.imgBorderStyle,   'solid');
                a = attrI(a, 'img_bc',   s.imgBorderColor,   '#e2e8f0');
                a = attrI(a, 'mt',        s.marginTop,            0);
                a = attrI(a, 'mt_unit', s.marginTopUnit,   'px');
                a = attrI(a, 'mb',      s.marginBottom,    0);
                a = attrI(a, 'mb_unit', s.marginBottomUnit,'px');
                a = attrI(a, 'mt_t',      (s['marginTop_tablet']    !== undefined && s['marginTop_tablet']    !== '' && s['marginTop_tablet']    !== null) ? s['marginTop_tablet']    : null);
                a = attrI(a, 'mt_t_unit', (s['marginTop_tablet']    !== undefined && s['marginTop_tablet']    !== '' && s['marginTop_tablet']    !== null) ? (s['marginTopUnit_tablet']    || 'px') : null);
                a = attrI(a, 'mb_t',      (s['marginBottom_tablet'] !== undefined && s['marginBottom_tablet'] !== '' && s['marginBottom_tablet'] !== null) ? s['marginBottom_tablet'] : null);
                a = attrI(a, 'mb_t_unit', (s['marginBottom_tablet'] !== undefined && s['marginBottom_tablet'] !== '' && s['marginBottom_tablet'] !== null) ? (s['marginBottomUnit_tablet'] || 'px') : null);
                a = attrI(a, 'mt_m',      (s['marginTop_mobile']    !== undefined && s['marginTop_mobile']    !== '' && s['marginTop_mobile']    !== null) ? s['marginTop_mobile']    : null);
                a = attrI(a, 'mt_m_unit', (s['marginTop_mobile']    !== undefined && s['marginTop_mobile']    !== '' && s['marginTop_mobile']    !== null) ? (s['marginTopUnit_mobile']    || 'px') : null);
                a = attrI(a, 'mb_m',      (s['marginBottom_mobile'] !== undefined && s['marginBottom_mobile'] !== '' && s['marginBottom_mobile'] !== null) ? s['marginBottom_mobile'] : null);
                a = attrI(a, 'mb_m_unit', (s['marginBottom_mobile'] !== undefined && s['marginBottom_mobile'] !== '' && s['marginBottom_mobile'] !== null) ? (s['marginBottomUnit_mobile'] || 'px') : null);
                a = attrI(a, 'css_class', s.cssClass);
                a = attrI(a, 'css_id',    s.cssId);
                return '[falcon_gallery ' + a.trim() + vis + ' /]';
            }
            default: {
                // Custom element: registered via lazy_builder_elements
                var _cdefs = (typeof window !== 'undefined' && window.falconCustomElements) ? window.falconCustomElements : {};
                var _cdef  = _cdefs[type] || (Object.values(_cdefs).find(function(e) { return e.type === type; }) || null);
                if (_cdef) {
                    var _tag    = _cdef.shortcode ? _cdef.shortcode : 'falcon_element';
                    var bases   = ceAllowedKeys(_cdef); // null = allow all
                    var extraKeys = Array.isArray(_cdef.shortcode_keys) ? _cdef.shortcode_keys : [];
                    var repKeys = ceFieldList(_cdef).filter(function(f){ return f.type === 'repeater'; }).map(function(f){ return f.key; });
                    var a = base;
                    if (!_cdef.shortcode) a = attrI(a, 'type', type); // falcon_element needs the type attr
                    Object.keys(s).forEach(function(k) {
                        if (k === 'visibility' || k.charAt(0) === '_') return;     // skip transient/UI keys
                        if (repKeys.indexOf(k) !== -1) return;                     // repeater → child shortcodes
                        // prune keys that aren't a declared field, a global key, or a user-whitelisted key
                        if (bases && !ceKeyAllowed(bases, k) && !ceGlobalAllowed(k) && extraKeys.indexOf(k) === -1) return;
                        var v = s[k];
                        if (v === null || v === undefined || v === '') return;
                        if (typeof v === 'boolean') { a = attrI(a, k, v ? '1' : '0'); return; }
                        if (Array.isArray(v))       { a = attrI(a, k, v.join(',')); return; } // checkbox → comma list
                        if (typeof v === 'object')  return;                                   // skip unknown objects
                        a = attrI(a, k, String(v));
                    });

                    // Repeater rows → readable child shortcodes [lzr_<key> sub="..." /]
                    var children = '';
                    repKeys.forEach(function(rk) {
                        var rows = s[rk];
                        if (!Array.isArray(rows)) return;
                        var childTag = 'lzr_' + rk;
                        rows.forEach(function(row) {
                            if (!row || typeof row !== 'object') return;
                            var ra = '';
                            Object.keys(row).forEach(function(sk) {
                                if (sk.charAt(0) === '_') return;
                                var rv = row[sk];
                                if (rv === null || rv === undefined || rv === '') return;
                                if (typeof rv === 'boolean') { ra = attrI(ra, sk, rv ? '1' : '0'); return; }
                                if (typeof rv === 'object')  return;
                                ra = attrI(ra, sk, String(rv));
                            });
                            children += '[' + childTag + ra + ' /]';
                        });
                    });

                    if (children) return '[' + _tag + ' ' + a.trim() + vis + ']' + children + '[/' + _tag + ']';
                    return '[' + _tag + ' ' + a.trim() + vis + ' /]';
                }
                return '[falcon_element type="' + type + '" ' + base.trim() + vis + ' /]';
            }
        }
    }

    // -------------------------------------------------------------------------
    // Shortcodes → JSON
    // -------------------------------------------------------------------------

    // Strip rich-editor (TinyMCE) <p>/<br> wrapping + encoded brackets around builder shortcodes
    function unwrapEditorMarkup(content) {
        content = content
            .replace(/&#91;|&lbrack;|&#x5[Bb];/g, '[')
            .replace(/&#93;|&rbrack;|&#x5[Dd];/g, ']');
        content = content.replace(/<p[^>]*>\s*(\[\/?(?:lazy_|lzr_)[^\]]*\])/gi, '$1');
        content = content.replace(/(\[\/?(?:lazy_|lzr_)[^\]]*\])\s*<\/p>/gi, '$1');
        content = content.replace(/(\])\s*<br\s*\/?>\s*(\[)/gi, '$1$2');
        content = content.replace(/<p[^>]*>\s*(?:&nbsp;)?\s*<\/p>/gi, '');
        return content;
    }

    function shortcodesToJson(content) {
        content = unwrapEditorMarkup(content);
        var layout = [];
        var containerRx = /\[falcon_section([^\]]*)\]([\s\S]*?)\[\/falcon_section\]/g;
        var m;
        while ((m = containerRx.exec(content)) !== null) {
            var container = parseContainer(m[1], m[2]);
            if (container) layout.push(container);
        }
        return JSON.stringify(layout);
    }

    function parseContainer(attrStr, inner) {
        var a  = parseAttrs(attrStr);
        var id = a.id || generateId();
        var tp = a.type || 'container';
        var settings = containerSettings(a);
        var names = perTypeNames('container', { id: id, type: tp, settings: settings, columns: [] });
        settings = mergeExtras(a, settings, names);
        return {
            id:       id,
            type:     tp,
            settings: settings,
            columns:  parseColumns(inner)
        };
    }

    function parseColumns(inner) {
        var cols = [];
        var pos  = 0;
        var len  = inner.length;

        while (pos < len) {
            var tagStart = inner.indexOf('[falcon_col', pos);
            if (tagStart === -1) break;

            var c = inner[tagStart + 9];
            if (c !== ' ' && c !== ']') { pos = tagStart + 9; continue; }

            var openEnd = inner.indexOf(']', tagStart);
            if (openEnd === -1) break;

            var attrStr = inner.substring(tagStart + 9, openEnd);
            var depth   = 1;
            var search  = openEnd + 1;
            var done    = false;

            while (depth > 0 && search < len) {
                var nextOpen  = inner.indexOf('[falcon_col', search);
                var nextClose = inner.indexOf('[/falcon_col]', search);

                if (nextClose === -1) break;

                if (nextOpen !== -1 && nextOpen < nextClose) {
                    var nc = inner[nextOpen + 9];
                    if (nc === ' ' || nc === ']') depth++;
                    search = nextOpen + 9;
                } else {
                    depth--;
                    if (depth === 0) {
                        var colInner = inner.substring(openEnd + 1, nextClose);
                        var col = parseColumn(attrStr, colInner);
                        if (col) cols.push(col);
                        pos  = nextClose + 11; // '[/falcon_col]'.length
                        done = true;
                        break;
                    }
                    search = nextClose + 11;
                }
            }

            if (!done) break;
        }

        return cols;
    }

    function parseColumn(attrStr, inner) {
        var a  = parseAttrs(attrStr);
        var id = a.id || generateId();
        var basis = a.width || '100%';
        var settings = columnSettings(a);
        var names = perTypeNames('column', { id: id, basis: basis, settings: settings, elements: [] });
        settings = mergeExtras(a, settings, names);
        return {
            id:       id,
            basis:    basis,
            settings: settings,
            elements: parseElements(inner)
        };
    }

    function parseElements(inner) {
        var results = [];
        var elemRx = /\[lazy_(?!section\b|col\b)(\w+)([^\]]*?)(?:\/\]|\]([\s\S]*?)\[\/lazy_\1\])/g;
        var m;
        while ((m = elemRx.exec(inner)) !== null) {
            var elem = parseElement(m[1], m[2], m[3] || '');
            if (elem) results.push({ pos: m.index, elem: elem });
        }

        // Also match custom element shortcode tags registered via lazy_builder_elements
        var _cdefs = (typeof window !== 'undefined' && window.falconCustomElements) ? window.falconCustomElements : {};
        Object.values(_cdefs).forEach(function(def) {
            var tag = def.shortcode || def.type;
            if (!tag || /^lazy_/.test(tag)) return; // already handled above
            var escapedTag = tag.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            var rx = new RegExp('\\[' + escapedTag + '([^\\]]*?)(?:\\/\\]|\\]([\\s\\S]*?)\\[\\/' + escapedTag + '\\])', 'g');
            while ((m = rx.exec(inner)) !== null) {
                var ce = parseCustomElement(def, m[1], m[2] || '');
                if (ce) results.push({ pos: m.index, elem: ce });
            }
        });

        results.sort(function(a, b) { return a.pos - b.pos; });
        return results.map(function(r) { return r.elem; });
    }

    function parseRepeaterChildren(inner, rkey) {
        var rows = [];
        if (!inner) return rows;
        var childTag = 'lzr_' + rkey;
        var esc = childTag.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        var rx = new RegExp('\\[' + esc + '([^\\]]*?)(?:\\/\\]|\\]([\\s\\S]*?)\\[\\/' + esc + '\\])', 'g');
        var m;
        while ((m = rx.exec(inner)) !== null) {
            var ra = parseAttrs(m[1]);
            var row = {};
            Object.keys(ra).forEach(function(k) { row[k] = maybeNum(ra[k]); });
            rows.push(row);
        }
        return rows;
    }

    function parseCustomElement(def, attrStr, inner) {
        var a   = parseAttrs(attrStr);
        var vis = visibilityFromAttrs(a);
        var settings = { visibility: vis };

        var fieldList = ceFieldList(def);
        if (fieldList.length) {
            fieldList.forEach(function(f) {
                if (!f.key) return;
                var t = f.type;
                if (t === 'repeater') {
                    settings[f.key] = parseRepeaterChildren(inner, f.key);
                } else if (t === 'checkbox') {
                    settings[f.key] = a[f.key] !== undefined ? String(a[f.key]).split(',').filter(Boolean) : (Array.isArray(f.value) ? f.value : []);
                } else if (t === 'toggle') {
                    settings[f.key] = a[f.key] !== undefined ? (a[f.key] === '1' || a[f.key] === 'yes' || a[f.key] === 'true') : !!f.value;
                } else if (t === 'dimensions' || t === 'typography') {
                    // restore every sub-key attr (responsive + unit variants included)
                    Object.keys(a).forEach(function(ak) {
                        if (ak.indexOf(f.key + '_') === 0) settings[ak] = maybeNum(a[ak]);
                    });
                } else if (t === 'number' || t === 'slider') {
                    settings[f.key] = a[f.key] !== undefined ? num(a[f.key]) : (f.value !== undefined ? f.value : '');
                } else {
                    settings[f.key] = a[f.key] !== undefined ? a[f.key] : (f.value !== undefined ? f.value : '');
                }
                // composite extras shared by many types (link/button targets, dynamic source, button url)
                if (a[f.key + '_target'] !== undefined)  settings[f.key + '_target']  = a[f.key + '_target'];
                if (a[f.key + '_dynamic'] !== undefined) settings[f.key + '_dynamic'] = a[f.key + '_dynamic'];
                if (a[f.key + '_url'] !== undefined)     settings[f.key + '_url']     = a[f.key + '_url'];
            });
            // Restore global keys (Extra tab: css, animation, conditional visibility) + whitelisted keys
            var extraKeys = Array.isArray(def.shortcode_keys) ? def.shortcode_keys : [];
            Object.keys(a).forEach(function(k) {
                if (k === 'id') return;
                if ((ceGlobalAllowed(k) || extraKeys.indexOf(k) !== -1) && settings[k] === undefined) {
                    settings[k] = maybeNum(a[k]);
                }
            });
        } else {
            // No field definitions: restore all attrs as settings
            Object.keys(a).forEach(function(k) {
                if (k !== 'id' && k !== 'hide_mobile' && k !== 'hide_tablet' && k !== 'hide_desktop') {
                    settings[k] = a[k];
                }
            });
        }

        var el = { id: a.id || generateId(), type: def.type || def.shortcode, settings: settings };
        var names = perTypeNames('element', el);
        el.settings = mergeExtras(a, settings, names);
        return el;
    }

    function parseElement(type, attrStr, inner) {
        var el = parseElementInner(type, attrStr, inner);
        if (el && typeof el === 'object') {
            var a = parseAttrs(attrStr);
            var names = perTypeNames('element', el);
            el.settings = mergeExtras(a, el.settings || {}, names);
        }
        return el;
    }

    function parseElementInner(type, attrStr, inner) {
        var a   = parseAttrs(attrStr);
        var vis = visibilityFromAttrs(a);

        switch (type) {
            case 'heading':
                return { id: a.id || generateId(), type: 'heading', settings: {
                    title:      inner.trim(),
                    tag:        a.tag        || 'h2',
                    fontSize:   a.font_size   || null,
                    fontWeight: a.font_weight || null,
                    textAlign:  a.align       || null,
                    color:      a.color       || null,
                    cssClass:   a.css_class   || null,
                    visibility: vis
                }};

            case 'title':
                return { id: a.id || generateId(), type: 'title', settings: {
                    title:          inner.trim(),
                    fontSize:       a.font_size ? parseInt(a.font_size) : null,
                    fontSizeUnit:   a.font_size_unit  || 'px',
                    fontWeight:     a.font_weight     || null,
                    textAlign:      a.align           || null,
                    titleColor:     a.color           || null,
                    separator:      a.separator       || 'default',
                    separatorColor: a.separator_color || null,
                    useLink:        (a.use_link || '') === 'yes',
                    linkUrl:        a.link_url   || null,
                    linkColor:      a.link_color || null,
                    cssClass:       a.css_class  || null,
                    visibility: vis
                }};

            case 'text':
                return { id: a.id || generateId(), type: 'text', settings: {
                    content:    inner.trim(),
                    fontSize:   a.font_size   || null,
                    fontWeight: a.font_weight || null,
                    color:      a.color       || null,
                    textAlign:  a.align       || null,
                    cssClass:   a.css_class   || null,
                    visibility: vis
                }};

            case 'button':
                return { id: a.id || generateId(), type: 'button', settings: {
                    text:      a.text       || 'Button',
                    url:       a.url        || '#',
                    target:    a.target     || '_self',
                    bgColor:   a.bg_color   || null,
                    textColor: a.text_color || null,
                    alignment: a.align      || null,
                    size:      a.size       || null,
                    cssClass:  a.css_class  || null,
                    visibility: vis
                }};

            case 'image':
                return { id: a.id || generateId(), type: 'image', settings: {
                    src:       a.src       || '',
                    alt:       a.alt       || '',
                    width:     a.width     || null,
                    alignment: a.align     || null,
                    cssClass:  a.css_class || null,
                    visibility: vis
                }};

            case 'spacer':
                return { id: a.id || generateId(), type: 'spacer', settings: {
                    height:    a.height ? parseInt(a.height) : 20,
                    visibility: vis
                }};

            case 'video':
                return { id: a.id || generateId(), type: 'video', settings: {
                    src:    a.src    || '',
                    type:   a.type   || null,
                    width:  a.width  || null,
                    height: a.height || null,
                    visibility: vis
                }};

            case 'menu': {
                var aso = {
                    main:    (a.arrow_main    || '') !== 'no',
                    active:  (a.arrow_active  || '') === 'yes',
                    submenu: (a.arrow_submenu || '') === 'yes'
                };
                return { id: a.id || generateId(), type: 'menu', settings: {
                    menuId:             a.menu_id          || null,
                    layout:             a.layout            || 'horizontal',
                    marginTop:          num(a.margin_top),
                    marginBottom:       num(a.margin_bottom),
                    itemTransition:     a.item_transition    ? parseFloat(a.item_transition)    : null,
                    itemTransitionMs:   a.item_transition_ms ? parseInt(a.item_transition_ms)   : null,
                    submenuSpace:       a.submenu_space      ? parseInt(a.submenu_space)         : 10,
                    arrowScopeObj:      aso,
                    cssClass:           a.css_class          || null,
                    cssId:              a.css_id             || null,
                    minHeight:          a.min_height         ? parseInt(a.min_height)  : null,
                    alignItems:         a.align_items        || 'flex-start',
                    justification:      a.justification      || 'flex-start',
                    fontFamily:         a.font_family        || 'inherit',
                    fontSize:           a.font_size          || null,
                    fontWeight:         a.font_weight        || null,
                    lineHeight:         a.line_height        || null,
                    letterSpacing:      a.letter_spacing     || null,
                    textTransform:      a.text_transform     || null,
                    itemPaddingTop:     num(a.item_padding_top),
                    itemPaddingRight:   num(a.item_padding_right),
                    itemPaddingBottom:  num(a.item_padding_bottom),
                    itemPaddingLeft:    num(a.item_padding_left),
                    itemSpacing:        num(a.item_spacing),
                    itemBorderRadius:   num(a.item_border_radius),
                    itemBgColor:        a.item_bg_color        || null,
                    itemBgColorHover:   a.item_bg_color_hover  || null,
                    itemColor:          a.item_color           || null,
                    itemColorHover:     a.item_color_hover     || null,
                    itemBorderSizeTop:         num(a.item_border_top),
                    itemBorderSizeRight:       num(a.item_border_right),
                    itemBorderSizeBottom:      num(a.item_border_bottom),
                    itemBorderSizeLeft:        num(a.item_border_left),
                    itemBorderColor:           a.item_border_color    || null,
                    itemBorderSizeTopHover:    num(a.item_border_top_h),
                    itemBorderSizeRightHover:  num(a.item_border_right_h),
                    itemBorderSizeBottomHover: num(a.item_border_bottom_h),
                    itemBorderSizeLeftHover:   num(a.item_border_left_h),
                    itemBorderColorHover:      a.item_border_color_h  || null,
                    showArrows:         a.show_arrows          || 'yes',
                    submenuDirection:   a.submenu_direction    || 'right',
                    submenuTransition:  a.submenu_transition   || 'fade',
                    submenuMinWidth:    a.submenu_min_width    || null,
                    submenuMaxWidth:    a.submenu_max_width    || null,
                    subSubMenuDirection:a.sub_sub_direction    || 'right',
                    subSubMenuOffset:   a.sub_sub_offset       ? parseInt(a.sub_sub_offset) : 5,
                    submenuFontFamily:  a.submenu_font_family  || 'inherit',
                    submenuFontSize:    a.submenu_font_size    || null,
                    submenuLineHeight:  a.submenu_line_height  || null,
                    submenuLetterSpacing: a.submenu_letter_sp  || null,
                    submenuTextTransform: a.submenu_text_transform || null,
                    submenuTextAlign:   a.submenu_text_align   || null,
                    submenuPaddingTop:  num(a.submenu_pt),
                    submenuPaddingRight:num(a.submenu_pr),
                    submenuPaddingBottom:num(a.submenu_pb),
                    submenuPaddingLeft: num(a.submenu_pl),
                    submenuBorderRadiusTopLeft:     num(a.submenu_radius_tl),
                    submenuBorderRadiusTopRight:    num(a.submenu_radius_tr),
                    submenuBorderRadiusBottomRight: num(a.submenu_radius_br),
                    submenuBorderRadiusBottomLeft:  num(a.submenu_radius_bl),
                    submenuBoxShadow:   a.submenu_shadow       || 'no',
                    submenuShadowColor: a.submenu_shadow_color || null,
                    submenuShadowH:     num(a.submenu_shadow_h),
                    submenuShadowV:     num(a.submenu_shadow_v),
                    submenuShadowBlur:  num(a.submenu_shadow_blur),
                    submenuShadowSpread:num(a.submenu_shadow_spread),
                    submenuThumbWidth:  a.submenu_thumb_w      || null,
                    submenuThumbHeight: a.submenu_thumb_h      || null,
                    submenuSeparatorColor: a.submenu_sep_color || null,
                    submenuBgColor:     a.submenu_bg_color     || null,
                    submenuTextColor:   a.submenu_text_color   || null,
                    submenuTextColorHover: a.submenu_text_color_h || null,
                    mobileCollapseBreakpoint:         a.mobile_breakpoint   || 'tablet',
                    mobileMenuMode:                   a.mobile_mode         || 'collapsed',
                    mobileMenuExpandMode:             a.mobile_expand_mode  || 'full-width-static',
                    mobileMenuSidebarSide:            a.mobile_sidebar_side || 'left',
                    mobileMenuTriggerPaddingTop:      num(a.mobile_trigger_pt) !== null ? num(a.mobile_trigger_pt) : 10,
                    mobileMenuTriggerPaddingRight:    num(a.mobile_trigger_pr) !== null ? num(a.mobile_trigger_pr) : 15,
                    mobileMenuTriggerPaddingBottom:   num(a.mobile_trigger_pb) !== null ? num(a.mobile_trigger_pb) : 10,
                    mobileMenuTriggerPaddingLeft:     num(a.mobile_trigger_pl) !== null ? num(a.mobile_trigger_pl) : 15,
                    mobileMenuTriggerBgColor:         a.mobile_trigger_bg    || null,
                    mobileMenuTriggerTextColor:       a.mobile_trigger_color || null,
                    mobileMenuTriggerText:            a.mobile_trigger_text  || null,
                    mobileMenuTriggerExpandIcon:      a.mobile_expand_icon   || null,
                    mobileMenuTriggerCollapseIcon:    a.mobile_collapse_icon || null,
                    mobileMenuTriggerFontSize:        a.mobile_trigger_fs    || null,
                    mobileMenuTriggerHorizontalAlign: a.mobile_trigger_align || 'flex-start',
                    mobileMenuItemMinHeight:          a.mobile_item_min_h    ? parseInt(a.mobile_item_min_h) : null,
                    mobileMenuItemPaddingTop:         num(a.mobile_item_pt),
                    mobileMenuItemPaddingRight:       num(a.mobile_item_pr),
                    mobileMenuItemPaddingBottom:      num(a.mobile_item_pb),
                    mobileMenuItemPaddingLeft:        num(a.mobile_item_pl),
                    mobileMenuTextAlign:              a.mobile_text_align    || 'left',
                    mobileMenuIndentSubmenus:         a.mobile_indent        || 'on',
                    mobileMenuFontFamily:             a.mobile_font_family   || 'inherit',
                    mobileMenuFontSize:               a.mobile_font_size     || null,
                    mobileMenuFontWeight:             a.mobile_font_weight   || null,
                    mobileMenuLineHeight:             a.mobile_line_height   || null,
                    mobileMenuLetterSpacing:          a.mobile_letter_sp     || null,
                    mobileMenuTextTransform:          a.mobile_text_transform || 'none',
                    mobileSeparatorEnabled:           a.mobile_separator     || 'yes',
                    mobileMenuSeparatorColor:         a.mobile_sep_color     || null,
                    mobileMenuBgColor:                a.mobile_bg_color      || null,
                    mobileMenuBgColorHover:           a.mobile_bg_color_h    || null,
                    mobileMenuTextColor:              a.mobile_text_color    || null,
                    mobileMenuTextColorHover:         a.mobile_text_color_h  || null,
                    visibility: vis
                }};
            }

            case 'row': {
                var rowObj = { id: a.id || generateId(), type: 'row', settings: { visibility: vis } };
                if (inner.trim()) {
                    var nestedCols = parseColumns(inner);
                    if (nestedCols.length) rowObj.columns = nestedCols;
                }
                return rowObj;
            }

            case 'counter':
                return { id: a.id || generateId(), type: 'counter', settings: {
                    endValue:            a.end        !== undefined ? parseFloat(a.end)      : 100,
                    startValue:          a.start      !== undefined ? parseFloat(a.start)    : 0,
                    prefix:              a.prefix     || '',
                    suffix:              a.suffix     || '',
                    label:               a.label      || '',
                    duration:            a.dur        !== undefined ? parseInt(a.dur)        : 2000,
                    decimals:            a.dec        !== undefined ? parseInt(a.dec)        : 0,
                    separator:           a.sep        || '',
                    textAlign:           a.align      || 'center',
                    numberFontSize:      a.num_size   || '48px',
                    numberFontWeight:    a.num_weight || '700',
                    numberColor:         a.num_color  || '#222222',
                    numberFontFamily:    a.num_family || 'inherit',
                    numberLineHeight:    a.num_lh     || '1.1',
                    numberLetterSpacing: a.num_ls     || '0px',
                    labelFontSize:       a.lbl_size   || '14px',
                    labelFontWeight:     a.lbl_weight || '400',
                    labelColor:          a.lbl_color  || '#666666',
                    labelFontFamily:     a.lbl_family || 'inherit',
                    labelLineHeight:     a.lbl_lh     || '1.4',
                    labelLetterSpacing:  a.lbl_ls     || '0px',
                    labelTextTransform:  a.lbl_tt     || 'none',
                    icon:                a.icon       || '',
                    iconSize:            a.icon_size  !== undefined ? parseInt(a.icon_size)  : 40,
                    iconColor:           a.icon_color || '#0091ea',
                    marginTop:           a.mt         !== undefined ? parseFloat(a.mt)       : 0,
                    marginTopUnit:       a.mt_unit    || 'px',
                    marginBottom:        a.mb         !== undefined ? parseFloat(a.mb)       : 0,
                    marginBottomUnit:    a.mb_unit    || 'px',
                    cssClass:            a.css_class  || '',
                    cssId:               a.css_id     || '',
                    visibility:          vis
                }};

            case 'star_rating':
                return { id: a.id || generateId(), type: 'star_rating', settings: {
                    rating:              a.rating     !== undefined ? parseFloat(a.rating)  : 5,
                    maxStars:            a.max        !== undefined ? parseInt(a.max)        : 5,
                    label:               a.label      || '',
                    starSize:            a.size       !== undefined ? parseInt(a.size)       : 24,
                    starColor:           a.color      || '#f59e0b',
                    emptyColor:          a.empty      || '#d1d5db',
                    textAlign:           a.align      || 'center',
                    gap:                 a.gap        !== undefined ? parseInt(a.gap)        : 4,
                    labelFontFamily:     a.lbl_family || 'inherit',
                    labelFontSize:       a.lbl_size   || '13px',
                    labelFontWeight:     a.lbl_weight || '400',
                    labelLineHeight:     a.lbl_lh     || '1.4',
                    labelLetterSpacing:  a.lbl_ls     || '0px',
                    labelTextTransform:  a.lbl_tt     || 'none',
                    labelColor:          a.lbl_color  || '#6b7280',
                    marginTop:           a.mt         !== undefined ? parseFloat(a.mt)      : 0,
                    marginTopUnit:       a.mt_unit    || 'px',
                    marginBottom:        a.mb         !== undefined ? parseFloat(a.mb)      : 0,
                    marginBottomUnit:    a.mb_unit    || 'px',
                    cssClass:            a.css_class  || '',
                    cssId:               a.css_id     || '',
                    visibility:          vis
                }};

            case 'gallery': {
                var n = a.img_n !== undefined ? parseInt(a.img_n) : 0;
                var imgs = [];
                for (var i = 0; i < n; i++) {
                    imgs.push({
                        url:     a['img_' + i]         || '',
                        alt:     a['img_' + i + '_a']  || '',
                        caption: a['img_' + i + '_c']  || ''
                    });
                }
                return { id: a.id || generateId(), type: 'gallery', settings: {
                    images:          imgs,
                    columns:         a.cols   !== undefined ? parseInt(a.cols)   : 3,
                    columnsTablet:   a.cols_t !== undefined ? parseInt(a.cols_t) : 2,
                    columnsMobile:   a.cols_m !== undefined ? parseInt(a.cols_m) : 1,
                    gap:             a.gap    !== undefined ? parseInt(a.gap)    : 8,
                    aspectRatio:     a.ratio  || 'square',
                    borderRadius:    a.radius !== undefined ? parseInt(a.radius) : 0,
                    lightbox:             (a.lightbox !== undefined ? a.lightbox : '1') !== '0',
                    hoverEffect:          a.hover      || 'zoom',
                    captionAlign:         a.cap_align  || 'center',
                    captionFontFamily:    a.cap_family || 'inherit',
                    captionFontSize:      a.cap_size   || '13px',
                    captionFontWeight:    a.cap_weight || '400',
                    captionLineHeight:    a.cap_lh     || '1.4',
                    captionLetterSpacing: a.cap_ls     || '0px',
                    captionTextTransform: a.cap_tt     || 'none',
                    captionColor:         a.cap_color  || '#6b7280',
                    imgBorderWidth:  a.img_bw !== undefined ? parseInt(a.img_bw)   : 0,
                    imgBorderStyle:  a.img_bs  || 'solid',
                    imgBorderColor:  a.img_bc  || '#e2e8f0',
                    marginTop:       a.mt      !== undefined ? parseFloat(a.mt)   : 0,
                    marginTopUnit:   a.mt_unit || 'px',
                    marginBottom:    a.mb      !== undefined ? parseFloat(a.mb)   : 0,
                    marginBottomUnit: a.mb_unit || 'px',
                    'marginTop_tablet':       a.mt_t     !== undefined ? parseFloat(a.mt_t)   : null,
                    'marginTopUnit_tablet':   a.mt_t_unit || null,
                    'marginBottom_tablet':    a.mb_t     !== undefined ? parseFloat(a.mb_t)   : null,
                    'marginBottomUnit_tablet': a.mb_t_unit || null,
                    'marginTop_mobile':       a.mt_m     !== undefined ? parseFloat(a.mt_m)   : null,
                    'marginTopUnit_mobile':   a.mt_m_unit || null,
                    'marginBottom_mobile':    a.mb_m     !== undefined ? parseFloat(a.mb_m)   : null,
                    'marginBottomUnit_mobile': a.mb_m_unit || null,
                    cssClass:        a.css_class || '',
                    cssId:           a.css_id    || '',
                    visibility:      vis
                }};
            }

            default: {
                // [falcon_element type="x"] or a bare custom tag → resolve the real type and
                // route to the custom-element parser (mirror of PHP) so fields + visibility survive.
                var realType = (type === 'element') ? (a.type || 'text') : type;
                var cdef = customDefsJs()[realType];
                if (cdef) return parseCustomElement(cdef, attrStr, inner);
                return { id: a.id || generateId(), type: realType, settings: { visibility: vis } };
            }
        }
    }

    // -------------------------------------------------------------------------
    // Settings builders — mirror PHP containerSettings() / columnSettings()
    // -------------------------------------------------------------------------

    function containerSettings(a) {
        var s = {
            marginTop:    num(a.margin_top    !== undefined ? a.margin_top    : null),
            marginBottom: num(a.margin_bottom !== undefined ? a.margin_bottom : null),
            marginTopUnit:    a.margin_top_unit    || 'px',
            marginBottomUnit: a.margin_bottom_unit || 'px',
            paddingTop:   num(a.padding_top   !== undefined ? a.padding_top   : 0),
            paddingBottom:num(a.padding_bottom !== undefined ? a.padding_bottom : 0),
            paddingLeft:  num(a.padding_left  !== undefined ? a.padding_left  : 0),
            paddingRight: num(a.padding_right !== undefined ? a.padding_right : 0),
            paddingTopUnit:    a.padding_top_unit    || 'px',
            paddingBottomUnit: a.padding_bottom_unit || 'px',
            paddingLeftUnit:   a.padding_left_unit   || 'px',
            paddingRightUnit:  a.padding_right_unit  || 'px',
            bgColor:              a.bg_color       || null,
            bgColorOpacity:       a.bg_opacity ? parseFloat(a.bg_opacity) : 1,
            bgType:               a.bg_type        || 'color',
            bgGradientStartColor: a.gradient_start || null,
            bgGradientEndColor:   a.gradient_end   || null,
            bgGradientStartPosition: a.gradient_start_pos ? parseInt(a.gradient_start_pos) : 0,
            bgGradientEndPosition:   a.gradient_end_pos   ? parseInt(a.gradient_end_pos)   : 100,
            bgGradientType:       a.gradient_type  || 'linear',
            bgGradientAngle:      a.gradient_angle ? parseInt(a.gradient_angle) : 180,
            bgImage:              a.bg_image        || null,
            bgImageSkipLazy:      false,
            bgImagePosition:      a.bg_position     || 'center center',
            bgImageRepeat:        a.bg_repeat       || 'no-repeat',
            bgImageSize:          a.bg_size         || 'auto',
            bgImageFading:        false,
            bgImageParallax:      a.bg_parallax     || 'none',
            bgImageBlendMode:     a.bg_blend        || 'normal',
            contentWidth:         a.content_width   || 'site',
            height:               a.height          || 'auto',
            customHeight:         a.custom_height   || null,
            minHeight:            a.min_height      || null,
            rowAlignContent:      a.row_align_content || null,
            alignItems:           a.align_items     || 'stretch',
            alignContent:         null,
            justifyContent:       a.justify_content || 'flex-start',
            flexWrap:             a.flex_wrap       || 'wrap',
            columnGap:            num(a.column_gap  || null),
            htmlTag:              a.html_tag        || 'div',
            menuAnchor:           a.menu_anchor     || null,
            visibility:           visibilityFromAttrs(a),
            status:               a.status          || 'published',
            cssClass:             a.css_class       || null,
            linkColor:            a.link_color      || null,
            linkUrl:              a.link            || null,
            linkTarget:           a.link_target     || '_self',
            borderSizeTop:        num(a.border_top    || null),
            borderSizeRight:      num(a.border_right  || null),
            borderSizeBottom:     num(a.border_bottom || null),
            borderSizeLeft:       num(a.border_left   || null),
            borderColor:          a.border_color     || '#000000',
            borderRadiusTopLeft:     num(a.radius_tl  || null),
            borderRadiusTopRight:    num(a.radius_tr  || null),
            borderRadiusBottomRight: num(a.radius_br  || null),
            borderRadiusBottomLeft:  num(a.radius_bl  || null),
            boxShadow:               (a.box_shadow || '') === 'yes',
            boxShadowPositionVertical:   num(a.shadow_v      || 0),
            boxShadowPositionHorizontal: num(a.shadow_h      || 0),
            boxShadowBlurRadius:         num(a.shadow_blur   || 0),
            boxShadowSpreadRadius:       num(a.shadow_spread || 0),
            boxShadowColor:              a.shadow_color || '#000000',
            boxShadowStyle:              a.shadow_style || 'outer',
            zIndex:                      num(a.z_index  || null),
            overflow:                    a.overflow    || 'default',
            sticky:        (a.sticky         || '') === 'yes',
            stickyDesktop: (a.sticky_desktop || '') !== 'no',
            stickyTablet:  (a.sticky_tablet  || '') !== 'no',
            stickyMobile:  (a.sticky_mobile  || '') !== 'no',
            stickyOffset:  a.sticky_offset  !== undefined ? num(a.sticky_offset)  : 0,
            stickyZIndex:  a.sticky_z_index !== undefined ? num(a.sticky_z_index) : 99
        };
        addRespProps(s, a, [
            ['bgColor',          'bg_color',            null],
            ['bgColorOpacity',   'bg_opacity',          parseFloat],
            ['bgImagePosition',  'bg_position',         null],
            ['bgImageSize',      'bg_size',             null],
            ['bgImageRepeat',    'bg_repeat',           null],
            ['bgImageBlendMode', 'bg_blend',            null],
            ['height',           'height',              null],
            ['customHeight',     'custom_height',       null],
            ['minHeight',        'min_height',          null],
            ['alignItems',       'align_items',         null],
            ['justifyContent',   'justify_content',     null],
            ['flexWrap',         'flex_wrap',           null],
            ['rowAlignContent',  'row_align_content',   null],
            ['columnGap',        'column_gap',          num],
            ['paddingTop',       'padding_top',         num],
            ['paddingTopUnit',   'padding_top_unit',    null],
            ['paddingBottom',    'padding_bottom',      num],
            ['paddingBottomUnit','padding_bottom_unit', null],
            ['paddingLeft',      'padding_left',        num],
            ['paddingLeftUnit',  'padding_left_unit',   null],
            ['paddingRight',     'padding_right',       num],
            ['paddingRightUnit', 'padding_right_unit',  null],
            ['marginTop',        'margin_top',          num],
            ['marginTopUnit',    'margin_top_unit',     null],
            ['marginBottom',     'margin_bottom',       num],
            ['marginBottomUnit', 'margin_bottom_unit',  null]
        ]);
        return s;
    }

    function columnSettings(a) {
        var s = {
            paddingTop:    num(a.padding_top    !== undefined ? a.padding_top    : 10),
            paddingBottom: num(a.padding_bottom !== undefined ? a.padding_bottom : 10),
            paddingLeft:   num(a.padding_left   !== undefined ? a.padding_left   : 10),
            paddingRight:  num(a.padding_right  !== undefined ? a.padding_right  : 10),
            paddingTopUnit:    a.padding_top_unit    || 'px',
            paddingBottomUnit: a.padding_bottom_unit || 'px',
            paddingLeftUnit:   a.padding_left_unit   || 'px',
            paddingRightUnit:  a.padding_right_unit  || 'px',
            marginTop:     num(a.margin_top     !== undefined ? a.margin_top     : 0),
            marginBottom:  num(a.margin_bottom  !== undefined ? a.margin_bottom  : 0),
            marginLeft:    num(a.margin_left    !== undefined ? a.margin_left    : 0),
            marginRight:   num(a.margin_right   !== undefined ? a.margin_right   : 0),
            marginTopUnit:    a.margin_top_unit    || 'px',
            marginBottomUnit: a.margin_bottom_unit || 'px',
            marginLeftUnit:   a.margin_left_unit   || 'px',
            marginRightUnit:  a.margin_right_unit  || 'px',
            alignment:     a.alignment      || 'default',
            contentLayout: a.content_layout || null,
            contentAlignH: a.align_h        || 'flex-start',
            contentAlignV: a.align_v        || 'flex-start',
            gapWidth:      num(a.gap_width   || null),
            gapHeight:     num(a.gap_height  || null),
            htmlTag:       a.html_tag        || 'div',
            linkUrl:       a.link            || null,
            linkTarget:    a.link_target     || '_self',
            visibility:    visibilityFromAttrs(a),
            cssClass:      a.css_class       || null,
            cssId:         a.css_id          || null,
            textColor:     a.text_color      || null,
            bgColor:       a.bg_color        || 'transparent',
            bgColorOpacity: a.bg_opacity ? parseFloat(a.bg_opacity) : 1,
            bgType:        a.bg_type         || 'color',
            hoverType:     a.hover_type      || 'none',
            bgGradientStartColor: a.gradient_start || null,
            bgGradientEndColor:   a.gradient_end   || null,
            bgGradientStartOpacity:  1,
            bgGradientEndOpacity:    1,
            bgGradientStartPosition: 0,
            bgGradientEndPosition:   100,
            bgGradientType:   'linear',
            bgGradientAngle:  a.gradient_angle ? parseInt(a.gradient_angle) : 180,
            bgImage:          a.bg_image       || null,
            bgImageSkipLazy:  false,
            bgImagePosition:  a.bg_position    || 'center center',
            bgImageRepeat:    a.bg_repeat      || 'no-repeat',
            bgImageSize:      a.bg_size        || 'auto',
            bgImageFading:    false,
            bgImageParallax:  'none',
            bgImageBlendMode: a.bg_blend       || 'normal',
            fontSize:         null,
            fontWeight:       null,
            lineHeight:       null,
            letterSpacing:    null,
            textAlign:        null,
            borderSizeTop:    num(a.border_top    || null),
            borderSizeRight:  num(a.border_right  || null),
            borderSizeBottom: num(a.border_bottom || null),
            borderSizeLeft:   num(a.border_left   || null),
            borderColor:      a.border_color      || '#000000',
            borderRadiusTopLeft:     num(a.radius_tl || null),
            borderRadiusTopRight:    num(a.radius_tr || null),
            borderRadiusBottomRight: num(a.radius_br || null),
            borderRadiusBottomLeft:  num(a.radius_bl || null),
            boxShadow:               false,
            boxShadowPositionVertical:   0,
            boxShadowPositionHorizontal: 0,
            boxShadowBlurRadius:         0,
            boxShadowSpreadRadius:       0,
            boxShadowColor:              '#000000',
            boxShadowStyle:              'outer',
            zIndex:        num(a.z_index  || null),
            overflow:      a.overflow    || 'default',
            sticky:        (a.sticky         || '') === 'yes',
            stickyDesktop: (a.sticky_desktop || '') !== 'no',
            stickyTablet:  (a.sticky_tablet  || '') !== 'no',
            stickyMobile:  (a.sticky_mobile  || '') !== 'no',
            stickyOffset:  a.sticky_offset  !== undefined ? num(a.sticky_offset)  : 0,
            stickyZIndex:  a.sticky_z_index !== undefined ? num(a.sticky_z_index) : 99
        };
        addRespProps(s, a, [
            ['bgColor',          'bg_color',            null],
            ['bgColorOpacity',   'bg_opacity',          parseFloat],
            ['bgImagePosition',  'bg_position',         null],
            ['bgImageSize',      'bg_size',             null],
            ['bgImageRepeat',    'bg_repeat',           null],
            ['bgImageBlendMode', 'bg_blend',            null],
            ['alignment',        'alignment',           null],
            ['contentAlignH',    'align_h',             null],
            ['contentAlignV',    'align_v',             null],
            ['paddingTop',       'padding_top',         num],
            ['paddingTopUnit',   'padding_top_unit',    null],
            ['paddingBottom',    'padding_bottom',      num],
            ['paddingBottomUnit','padding_bottom_unit', null],
            ['paddingLeft',      'padding_left',        num],
            ['paddingLeftUnit',  'padding_left_unit',   null],
            ['paddingRight',     'padding_right',       num],
            ['paddingRightUnit', 'padding_right_unit',  null],
            ['marginTop',        'margin_top',          num],
            ['marginTopUnit',    'margin_top_unit',     null],
            ['marginBottom',     'margin_bottom',       num],
            ['marginBottomUnit', 'margin_bottom_unit',  null],
            ['marginLeft',       'margin_left',         num],
            ['marginLeftUnit',   'margin_left_unit',    null],
            ['marginRight',      'margin_right',        num],
            ['marginRightUnit',  'margin_right_unit',   null]
        ]);
        return s;
    }

    // -------------------------------------------------------------------------
    // Detection helpers
    // -------------------------------------------------------------------------

    function isBuilderJson(str) {
        var s = (str || '').trim();
        if (!s || (s[0] !== '[' && s[0] !== '{')) return false;
        try {
            var parsed = JSON.parse(s);
            return Array.isArray(parsed) && parsed.length > 0 && parsed[0].id !== undefined;
        } catch (e) {
            return false;
        }
    }

    function isBuilderShortcode(str) {
        return (str || '').indexOf('[falcon_section') !== -1;
    }

    // -------------------------------------------------------------------------
    // Main initialisation
    // -------------------------------------------------------------------------

    function setEditorContent(shortcodes) {
        var textarea = document.getElementById('wp-editor');
        if (textarea) textarea.value = shortcodes;

        if (typeof tinymce === 'undefined') return;
        var ed = tinymce.get('wp-editor');
        if (ed && ed.initialized) {
            ed.setContent(shortcodes);
        } else {
            tinymce.on('AddEditor', function onAdd(ev) {
                if (ev.editor.id === 'wp-editor') {
                    ev.editor.on('init', function () { this.setContent(shortcodes); });
                    tinymce.off('AddEditor', onAdd);
                }
            });
        }
    }

    function init() {
        var textarea = document.getElementById('wp-editor');
        if (!textarea) return;

        // When returning via browser Back from the page builder (bfcache restore),
        // the textarea still holds the old shortcodes — force a fresh load so the
        // latest saved JSON is converted again.
        window.addEventListener('pageshow', function (e) {
            if (e.persisted) location.reload();
        });

        // Convert JSON → shortcodes on load (for display)
        var content = textarea.value;
        if (isBuilderJson(content)) {
            try {
                var shortcodes = jsonToShortcodes(content);
                if (shortcodes && shortcodes !== content) {
                    setEditorContent(shortcodes);
                }
            } catch (e) {
                console.warn('[LazyBuilder] Could not convert JSON to shortcodes:', e);
            }
        }

        // Intercept form submit: shortcodes → JSON before sending to server
        var form = document.getElementById('post-form');
        if (!form) return;

        form.addEventListener('submit', function () {
            var richContainer = document.getElementById('rich-editor-container');

            // Only act when the rich editor is the active tab
            if (richContainer && richContainer.classList.contains('hidden')) return;

            // Sync TinyMCE → textarea so the latest typed content is submitted.
            if (typeof tinymce !== 'undefined') {
                var ed = tinymce.get('wp-editor');
                if (ed) ed.save();
            }

            // NOTE: We intentionally submit the shortcode content as-is and let the server-side
            // BuilderShortcodeMiddleware convert shortcodes → JSON with the complete (lossless,
            // readable) PHP converter. The browser converter omits a few element cases, so doing
            // the conversion here could drop data on submit. Server conversion keeps it lossless.
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
