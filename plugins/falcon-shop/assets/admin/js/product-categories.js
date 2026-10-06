        document.querySelectorAll('#cb-select-all-1, #cb-select-all-2').forEach(function(master) {
            master.addEventListener('change', function() {
                let isChecked = this.checked;
                document.querySelectorAll('.cb-select-item').forEach(function(item) {
                    item.checked = isChecked;
                });
                document.getElementById('cb-select-all-1').checked = isChecked;
                document.getElementById('cb-select-all-2').checked = isChecked;
            });
        });

        window.handleBulkAction = async function(formId, selectName = 'action') {
            const form = document.getElementById(formId);
            const action = form.querySelector(`select[name="${selectName}"]`).value;
            const selected = form.querySelectorAll('.cb-select-item:checked');

            if (action === '-1' || action === 'none') return;
            if (selected.length === 0) {
                window.showToast('Please select at least one item.', 'warning');
                return;
            }

            if (action === 'delete') {
                const confirmed = await window.falconConfirm({
                    title: 'Bulk Delete Categories',
                    message: `Are you sure you want to delete ${selected.length} selected categories? This action cannot be undone.`,
                    confirmText: 'Delete',
                    isDanger: true
                });

                if (confirmed) {
                    if (selectName === 'action2') {
                        form.querySelector('select[name="action"]').value = action;
                    }
                    form.submit();
                }
            } else {
                form.submit();
            }
        };

        window.confirmDeleteCategory = async function(id) {
            const confirmed = await window.falconConfirm({
                title: 'Delete Category',
                message: 'Are you sure you want to delete this category? This action cannot be undone.',
                confirmText: 'Delete',
                isDanger: true
            });
            if (confirmed) {
                document.getElementById(`delete-form-${id}`).submit();
            }
        };
    