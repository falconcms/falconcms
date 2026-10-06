        document.addEventListener('DOMContentLoaded', function() {
            const config = {
                plugins: ['dropdown_input'],
                create: false,
                render: {
                    no_results: function(data, escape) {
                        return '<div class="no-results">No results found for "' + escape(data.input) + '"</div>';
                    }
                }
            };

            new TomSelect('#country_state', {
                ...config,
                placeholder: 'Select a country / state...',
                maxOptions: 1000,
                sortField: { field: "text", direction: "asc" }
            });
            new TomSelect('#shop_page_id', {
                ...config,
                placeholder: 'Select a page...'
            });
            new TomSelect('#cart_page_id', {
                ...config,
                placeholder: 'Select a page...'
            });
            new TomSelect('#checkout_page_id', {
                ...config,
                placeholder: 'Select a page...'
            });
            new TomSelect('#account_page_id', {
                ...config,
                placeholder: 'Select a page...'
            });
            new TomSelect('#selling_specific_countries', {
                ...config,
                plugins: ['dropdown_input', 'remove_button'],
                placeholder: 'Select specific countries...',
                maxOptions: 1000
            });
            new TomSelect('#selling_except_countries', {
                ...config,
                plugins: ['dropdown_input', 'remove_button'],
                placeholder: 'Select countries to exclude...',
                maxOptions: 1000
            });
            new TomSelect('#shipping_specific_countries', {
                ...config,
                plugins: ['dropdown_input', 'remove_button'],
                placeholder: 'Select specific countries...',
                maxOptions: 1000
            });
            new TomSelect('#shop_currency', {
                ...config,
                placeholder: 'Select a currency...',
                maxOptions: 500
            });
        });
    