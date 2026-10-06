        document.querySelectorAll('#cb-select-all-1, #cb-select-all-2').forEach(function (master) {
            master.addEventListener('change', function () {
                const isChecked = this.checked;
                document.querySelectorAll('.cb-select-item').forEach(function (item) { item.checked = isChecked; });
                document.getElementById('cb-select-all-1').checked = isChecked;
                document.getElementById('cb-select-all-2').checked = isChecked;
            });
        });

        // Deleting a promotion never touches orders that already used it, so the confirmation
        // says so rather than implying past discounts are at risk.
        window.deletePromotion = async function (id) {
            const confirmed = await window.falconConfirm({
                title: 'Delete promotion',
                message: 'Delete this promotion? Orders that already used it keep their discount.',
                confirmText: 'Delete',
                isDanger: true,
            });
            if (confirmed) document.getElementById('delete-promotion-' + id).submit();
        };

        window.handleBulkAction = async function (formId, selectName = 'action') {
            const form = document.getElementById(formId);
            const action = form.querySelector(`select[name="${selectName}"]`).value;
            const selected = form.querySelectorAll('.cb-select-item:checked');

            if (action === '-1') return;
            if (selected.length === 0) {
                window.showToast('Please select at least one promotion.', 'warning');
                return;
            }

            if (action === 'delete') {
                const confirmed = await window.falconConfirm({
                    title: 'Delete promotions',
                    message: `Delete ${selected.length} promotion(s)? Orders that already used them keep their discount.`,
                    confirmText: 'Delete',
                    isDanger: true,
                });
                if (!confirmed) return;
            }

            form.submit();
        };
    