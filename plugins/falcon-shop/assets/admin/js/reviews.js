        document.querySelectorAll('#cb-select-all-1, #cb-select-all-2').forEach(function(master) {
            master.addEventListener('change', function() {
                let isChecked = this.checked;
                document.querySelectorAll('.cb-select-item').forEach(function(item) {
                    item.checked = isChecked;
                });
            });
        });

        window.confirmDelete = async function(id) {
            const confirmed = await window.falconConfirm({
                title: 'Delete Review',
                message: 'Are you sure you want to delete this review?',
                confirmText: 'Delete',
                isDanger: true
            });
            if (confirmed) document.getElementById(`delete-form-${id}`).submit();
        };

        window.handleBulkAction = async function(formId, selectName) {
            const form = document.getElementById(formId);
            const action = form.querySelector(`select[name="${selectName}"]`).value;
            const selected = form.querySelectorAll('.cb-select-item:checked');
            if (action === '-1') return;
            if (selected.length === 0) {
                window.showToast('Please select at least one item.', 'warning');
                return;
            }
            form.querySelector('select[name="action"]').value = action;
            form.submit();
        };
    