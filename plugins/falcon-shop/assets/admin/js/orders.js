        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.order-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });

        document.getElementById('bulk-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const topSelect = this.querySelector('select[name="action"]');
            const bottomSelect = this.querySelector('select[name="action_bottom"]');
            
            let action = topSelect.value;
            if (!action && bottomSelect) action = bottomSelect.value;
            
            if (!action) {
                alert('Please select an action.');
                return;
            }
            
            const checkedCount = document.querySelectorAll('.order-checkbox:checked').length;
            if (checkedCount === 0) {
                alert('Please select at least one order.');
                return;
            }

            topSelect.value = action;

            if (action === 'delete') {
                const confirmed = await window.falconConfirm({
                    title: 'Delete Orders',
                    message: `Are you sure you want to permanently delete ${checkedCount} selected orders? This action cannot be undone.`,
                    confirmText: 'Delete Permanently',
                    isDanger: true
                });
                
                if (confirmed) {
                    this.submit();
                }
            } else {
                this.submit();
            }
        });

        const selects = document.querySelectorAll('.bulk-action-select');
        selects.forEach(select => {
            select.addEventListener('change', function() {
                selects.forEach(s => s.value = this.value);
            });
        });
    