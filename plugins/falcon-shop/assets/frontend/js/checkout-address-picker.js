(function () {
    function fill(picker) {
        var option = picker.options[picker.selectedIndex];
        if (!option || !option.value) return;      // "Enter a new address" leaves the form alone

        var fields;
        try { fields = JSON.parse(option.dataset.fields || '{}'); } catch (e) { return; }

        var form = picker.closest('form');
        if (!form) return;

        Object.keys(fields).forEach(function (name) {
            var input = form.elements[name];
            if (!input || input.value === fields[name]) return;
            input.value = fields[name];
            // The country select drives shipping and tax, which recalculate on change.
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    function init() {
        document.querySelectorAll('.falcon-address-picker').forEach(function (wrap) {
            // Revealed only now: without JS the picker could not fill anything, so showing it
            // would just be a control that does nothing.
            wrap.hidden = false;

            var picker = wrap.querySelector('[data-address-picker]');
            if (picker) picker.addEventListener('change', function () { fill(picker); });
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
