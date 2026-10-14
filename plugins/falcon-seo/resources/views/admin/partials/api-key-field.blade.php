{{--
    One outside service's key on SEO → Settings: $service (claude | bing | dataforseo), $label.

    The field stays empty on purpose (a saved key is never sent back to the browser); what is
    saved shows beside it as its last four characters, with Remove and a Test that checks it
    works. autocomplete="new-password" (and the password managers' own opt-outs) keeps the
    browser from filling the admin password in here, which would replace the key on every save.
--}}
@php
    [$falconKeyOption] = \FalconSeo\Support\ApiKeys::SERVICES[$service];
    $falconKeyHint = \FalconSeo\Support\ApiKeys::hint($service);
@endphp
<div>
    <label class="block text-[13px] font-bold text-[#2c3338] mb-1">{{ $label }}</label>
    <div class="flex flex-wrap items-center gap-2">
        <input type="password" name="{{ $falconKeyOption }}" autocomplete="new-password" data-lpignore="true" data-1p-ignore data-bwignore
               spellcheck="false" class="wp-input w-[360px] max-w-full font-mono"
               placeholder="{{ $falconKeyHint ? 'Paste a new key to replace the saved one' : ($placeholder ?? 'Paste the key') }}">
        @if($falconKeyHint)
            <span class="inline-flex items-center gap-1 text-[12px] text-[#00731a] font-semibold">✓ Saved <code class="font-normal text-[#50575e]">{{ $falconKeyHint }}</code></span>
            <button type="button" class="wp-btn-secondary h-7 py-0 px-2 text-[12px]" onclick="falconSeoTestKey(this, @js($service))">Test</button>
            <label class="inline-flex items-center gap-1 text-[12px] text-[#646970]">
                <input type="checkbox" name="{{ $falconKeyOption }}_remove" value="1" class="rounded-sm border-[#8c8f94]"> Remove
            </label>
        @else
            <span class="text-[12px] text-[#646970]">Not saved yet</span>
        @endif
    </div>
    <p class="falcon-seo-key-result text-[12px] mt-1 hidden"></p>
</div>
@once
    <script>
        // "Test": asks the service with the saved key, by a call that costs nothing.
        function falconSeoTestKey(btn, service) {
            const out = btn.closest('div').parentNode.querySelector('.falcon-seo-key-result');
            btn.disabled = true;
            out.classList.remove('hidden');
            out.style.color = '#646970';
            out.textContent = 'Testing…';
            fetch(@json(route('admin.seo.test-key')), {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token())},
                body: JSON.stringify({service}),
            }).then(r => r.json()).then(d => {
                out.style.color = d.ok ? '#00731a' : '#b32d2e';
                out.textContent = (d.ok ? '✓ ' : '✗ ') + (d.message || '');
            }).catch(() => {
                out.style.color = '#b32d2e';
                out.textContent = '✗ The test could not run.';
            }).finally(() => { btn.disabled = false; });
        }
    </script>
@endonce
