<div id="lazy-delete-modal" class="fixed inset-0 z-[9999] hidden">
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-2xl w-full max-w-md transform transition-all animate-in fade-in zoom-in duration-200">
            <div class="p-6">
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-center text-[#1d2327] mb-2" id="lazy-modal-title">Confirm Action</h3>
                <p class="text-sm text-center text-[#646970] mb-6" id="lazy-modal-message">Are you sure you want to perform this action? This might be permanent.</p>
                <div class="flex justify-center gap-3">
                    <button type="button" id="lazy-modal-cancel" class="px-4 py-2 text-sm font-medium text-[#2c3338] bg-white border border-[#c3c4c7] rounded hover:bg-[#f6f7f7] transition-colors">
                        Cancel
                    </button>
                    <button type="button" id="lazy-modal-confirm" class="px-4 py-2 text-sm font-medium text-white bg-[#d63638] rounded hover:bg-[#b32d2e] transition-colors">
                        Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    window.falconConfirm = function(options) {
        const modal = document.getElementById('lazy-delete-modal');
        const titleEl = document.getElementById('lazy-modal-title');
        const messageEl = document.getElementById('lazy-modal-message');
        const confirmBtn = document.getElementById('lazy-modal-confirm');
        const cancelBtn = document.getElementById('lazy-modal-cancel');

        titleEl.innerText = options.title || 'Confirm Action';
        messageEl.innerText = options.message || 'Are you sure?';
        confirmBtn.innerText = options.confirmText || 'Confirm';
        confirmBtn.className = `px-4 py-2 text-sm font-medium text-white rounded transition-colors ${options.isDanger ? 'bg-[#d63638] hover:bg-[#b32d2e]' : 'bg-[#2271b1] hover:bg-[#135e96]'}`;

        modal.classList.remove('hidden');

        return new Promise((resolve) => {
            const onConfirm = () => {
                modal.classList.add('hidden');
                cleanup();
                resolve(true);
            };

            const onCancel = () => {
                modal.classList.add('hidden');
                cleanup();
                resolve(false);
            };

            const cleanup = () => {
                confirmBtn.removeEventListener('click', onConfirm);
                cancelBtn.removeEventListener('click', onCancel);
            };

            confirmBtn.addEventListener('click', onConfirm);
            cancelBtn.addEventListener('click', onCancel);
        });
    };
</script>
