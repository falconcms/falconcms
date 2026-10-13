{{-- Media Library → "Generate AVIF copies" (falcon_media_library_actions). New uploads get a copy
     already (Customizer → Performance → Serve AVIF Images); this makes one for the rest. --}}
@if(\FalconSeo\Support\AvifImages::enabled() && auth()->user()?->hasPermission('manage_settings'))
    <div class="flex items-center gap-3">
        <span id="avif-progress" class="text-[12px] text-[#646970]"></span>
        <button type="button" id="avif-generate-btn" onclick="falconGenerateAvif()" class="wp-btn-secondary px-2 py-0.5 text-[13px]"
                title="Makes an AVIF copy of every library image that has none. Originals and their addresses are not changed.">Generate AVIF copies</button>
    </div>
    <script>
        function falconGenerateAvif() {
            const btn = document.getElementById('avif-generate-btn');
            const out = document.getElementById('avif-progress');
            let made = 0;
            btn.disabled = true;
            out.style.color = '#646970';
            const step = (afterId) => fetch(@json(route('admin.media.avif-generate')), {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token())},
                body: JSON.stringify({after_id: afterId}),
            }).then(r => r.json()).then(d => {
                if (!d.success) throw new Error(d.message || 'Failed');
                made += d.made;
                out.textContent = `Checked ${d.checked} of ${d.total} images…`;
                if (!d.done) return step(d.after_id);
                out.style.color = '#00731a';
                out.textContent = `Done: ${made} new AVIF ${made === 1 ? 'copy' : 'copies'} (images that already had one, or would not get smaller, were skipped).`;
                btn.disabled = false;
            });
            step(0).catch(e => { out.style.color = '#b32d2e'; out.textContent = e.message; btn.disabled = false; });
        }
    </script>
@endif
