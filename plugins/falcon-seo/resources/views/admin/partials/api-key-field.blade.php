{{--
    One outside service's key on SEO → Settings: $service (bing | dataforseo), $label.

    A saved key shows in its field with the middle hidden ("sk-ant-api03-a••••••••••••VwAA"):
    the whole key is never sent back to the browser, and saving the field unchanged keeps it.
    Clicking into the field empties it for a new key (put back if left empty). Remove and a Test
    that checks the key works sit beside it. autocomplete="new-password" (and the password
    managers' own opt-outs) keeps the browser from filling the admin password in here, which
    would replace the key on every save.
--}}
@php
    [$falconKeyOption] = \FalconSeo\Support\ApiKeys::SERVICES[$service];
    $falconKeyMasked = \FalconSeo\Support\ApiKeys::masked($service);
@endphp
<div>
    <label class="block text-[13px] font-bold text-[#2c3338] mb-1">{{ $label }}</label>
    <div class="flex flex-wrap items-center gap-2">
        @if($falconKeyMasked)
            <input type="text" name="{{ $falconKeyOption }}" value="{{ $falconKeyMasked }}" data-masked="{{ $falconKeyMasked }}"
                   autocomplete="off" data-lpignore="true" data-1p-ignore data-bwignore spellcheck="false" class="wp-input w-[360px] max-w-full font-mono text-[#50575e]"
                   title="Saved. Click to paste a new key; leave it as it is to keep this one."
                   onfocus="if (this.value === this.dataset.masked) { this.value = ''; this.type = 'password'; this.autocomplete = 'new-password'; this.placeholder = 'Paste a new key, or leave empty to keep the saved one'; }"
                   onblur="if (this.value === '') { this.type = 'text'; this.value = this.dataset.masked; }">
            <span class="inline-flex items-center gap-1 text-[12px] text-[#00731a] font-semibold">✓ Saved</span>
            <button type="button" class="wp-btn-secondary h-7 py-0 px-2 text-[12px]" onclick="falconSeoTestKey(this, @js($service))">Test</button>
            <label class="inline-flex items-center gap-1 text-[12px] text-[#646970]">
                <input type="checkbox" name="{{ $falconKeyOption }}_remove" value="1" class="rounded-sm border-[#8c8f94]"> Remove
            </label>
        @else
            <input type="password" name="{{ $falconKeyOption }}" autocomplete="new-password" data-lpignore="true" data-1p-ignore data-bwignore
                   spellcheck="false" class="wp-input w-[360px] max-w-full font-mono" placeholder="{{ $placeholder ?? 'Paste the key' }}">
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
