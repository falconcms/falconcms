        document.addEventListener('DOMContentLoaded', function () {
            if (typeof TomSelect === 'undefined') return;

            [
                ['#promo-trigger-products',   'Search and select products...'],
                ['#promo-trigger-categories', 'Search and select categories...'],
                ['#promo-reward-products',    'Search and select products...'],
                ['#promo-reward-categories',  'Search and select categories...'],
            ].forEach(function ([selector, placeholder]) {
                var el = document.querySelector(selector);
                if (!el) return;
                new TomSelect(el, {
                    plugins: ['remove_button', 'dropdown_input'],
                    placeholder: placeholder,
                    maxOptions: 1000,
                    // Rendered on <body> so the list is not clipped by the metabox.
                    dropdownParent: 'body',
                    onItemAdd: function () { this.setTextboxValue(''); }
                });
            });
        });
    