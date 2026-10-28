<x-falcon-cms::layouts.admin title="AI Settings" active-menu="ai">
    <div class="px-2 max-w-[860px]">
        <h1 class="text-[23px] font-normal text-[#1d2327] mb-1">AI</h1>
        <p class="text-[13px] text-[#646970] mb-5">Write with AI in the post editor, and AI suggestions for SEO titles, descriptions and alt text.
            It uses your own API key: you pay the provider for what you use, and only the text you are working on is sent.</p>

        @if(session('success'))
            <div class="bg-[#edfaef] border-l-4 border-[#46b450] p-3 mb-5 text-[13px] text-[#1d2327]">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="bg-[#fcf0f1] border-l-4 border-[#d63638] p-3 mb-5 text-[13px] text-[#1d2327]">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="bg-[#fcf0f1] border-l-4 border-[#d63638] p-3 mb-5 text-[13px] text-[#1d2327]">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('admin.ai.settings.save') }}" class="space-y-6" x-data="{ provider: @js($chosen) }" autocomplete="off">
            @csrf

            {{-- Provider and keys --}}
            <div class="wp-metabox">
                <div class="wp-metabox-header">Provider</div>
                <div class="wp-metabox-content p-4 space-y-5">
                    <div>
                        <label class="block text-[13px] font-bold text-[#2c3338] mb-2">Write with</label>
                        <div class="flex flex-wrap gap-4">
                            @foreach($providers as $id => $provider)
                                <label class="inline-flex items-center gap-2 text-[14px] cursor-pointer">
                                    <input type="radio" name="provider" value="{{ $id }}" x-model="provider" class="w-4 h-4">
                                    {{ $provider->label() }}
                                    @if($provider->ready())
                                        <span class="text-[11px] text-[#00a32a]">● ready</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                        <p class="text-[12px] text-[#646970] mt-1">Save a key for one or more; every AI request uses the one chosen here.</p>
                    </div>

                    @foreach($providers as $id => $provider)
                        @php
                            [, $keyLink, $keyPlaceholder] = \FalconAi\Support\Settings::KEY_INFO[$id] ?? [null, null, ''];
                            // Claude's models are a fixed list with a default; the others come from the account.
                            $fixedList = $id === 'claude';
                            $modelHint = [
                                'openai' => 'The list comes from your OpenAI account, so new models show up here as OpenAI adds them.',
                                'gemini' => 'The list comes from your Google account. Flash models are free within Google\'s limits (a few requests a minute, about a thousand a day); Pro models need billing.',
                            ][$id] ?? null;
                        @endphp
                        <div class="pt-4 border-t border-[#f0f0f1]" :class="provider === @js($id) ? '' : 'opacity-70'">
                            <h3 class="text-[14px] font-semibold text-[#1d2327] mb-2">{{ $provider->label() }}</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="{{ $id }}_key" class="block text-[13px] font-bold text-[#2c3338] mb-1">API key</label>
                                    {{-- Never the key itself: its first and last characters. Saving it unchanged keeps the key. --}}
                                    <input type="password" id="{{ $id }}_key" name="{{ $id }}_key" value="{{ $masked[$id] ?? '' }}"
                                           autocomplete="new-password" spellcheck="false" placeholder="{{ $keyPlaceholder }}"
                                           class="wp-input w-full h-8 text-[13px] font-mono">
                                    <div class="flex flex-wrap items-center gap-3 mt-1.5 text-[12px]">
                                        @if($keyLink)
                                            <a href="{{ $keyLink }}" target="_blank" rel="noopener noreferrer" class="text-[#2271b1]">Get a key</a>
                                        @endif
                                        @if(!empty($masked[$id]))
                                            <button type="button" class="text-[#2271b1] hover:underline" data-ai-test="{{ $id }}">Test</button>
                                            <label class="inline-flex items-center gap-1 text-[#b32d2e]">
                                                <input type="checkbox" name="{{ $id }}_key_remove" value="1" class="w-3.5 h-3.5"> Remove
                                            </label>
                                        @endif
                                        <span data-ai-test-result="{{ $id }}" class="text-[#646970]"></span>
                                    </div>
                                </div>
                                <div>
                                    <label for="{{ $id }}_model" class="block text-[13px] font-bold text-[#2c3338] mb-1">Model</label>
                                    @if(!empty($models[$id]))
                                        <select id="{{ $id }}_model" name="{{ $id }}_model" class="wp-input w-full h-8 py-0 text-[13px]" @unless($fixedList) data-ai-searchable @endunless>
                                            @if(!$fixedList && ($model[$id] ?? '') === '')
                                                <option value="">Choose a model…</option>
                                            @endif
                                            @foreach($models[$id] as $modelId => $label)
                                                <option value="{{ $modelId }}" @selected($modelId === ($model[$id] ?? ''))>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="text" id="{{ $id }}_model" name="{{ $id }}_model" value="{{ $model[$id] ?? '' }}" maxlength="100"
                                               class="wp-input w-full h-8 text-[13px] font-mono" placeholder="Save the key first, then choose from the list">
                                    @endif
                                    @if($modelHint)
                                        <p class="text-[12px] text-[#646970] mt-1">{{ $modelHint }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Writing defaults --}}
            <div class="wp-metabox">
                <div class="wp-metabox-header">Writing</div>
                <div class="wp-metabox-content p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="ai-language" class="block text-[13px] font-bold text-[#2c3338] mb-1">Language</label>
                        <select id="ai-language" name="language" class="wp-input w-full h-8 py-0 text-[13px]" data-ai-searchable>
                            @foreach(\FalconAi\Support\Settings::languageOptions() as $value => $label)
                                <option value="{{ $value }}" @selected($value === $language)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="text-[12px] text-[#646970] mt-1">The default; each article can choose its own. Type to search.</p>
                    </div>
                    <div>
                        <label for="ai-tone" class="block text-[13px] font-bold text-[#2c3338] mb-1">Tone</label>
                        <select id="ai-tone" name="tone" class="wp-input w-full h-8 py-0 text-[13px]">
                            @foreach(\FalconAi\Support\Settings::TONES as $value => $label)
                                <option value="{{ $value }}" @selected($value === $tone)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- Who, and how much --}}
            <div class="wp-metabox">
                <div class="wp-metabox-header">Access and spending</div>
                <div class="wp-metabox-content p-4 space-y-5">
                    <div>
                        <span class="block text-[13px] font-bold text-[#2c3338] mb-1">Who can write with AI</span>
                        <p class="text-[12px] text-[#646970] mb-2">Administrators always can. Of the roles ticked, only users who can write posts or pages see Write with AI.</p>
                        <div class="flex flex-wrap gap-x-5 gap-y-2">
                            @foreach($roles as $slug => $name)
                                <label class="inline-flex items-center gap-2 text-[13px]">
                                    <input type="checkbox" name="writer_roles[]" value="{{ $slug }}" @checked(in_array($slug, $writerRoles, true)) class="w-4 h-4"> {{ $name }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <label for="ai-limit" class="block text-[13px] font-bold text-[#2c3338] mb-1">Monthly limit (tokens)</label>
                        <input type="number" id="ai-limit" name="monthly_limit" min="0" step="1000" value="{{ $limit }}" class="wp-input w-[200px] h-8 text-[13px]">
                        <p class="text-[12px] text-[#646970] mt-1">When the month's requests reach this many tokens, AI writing stops until the next month. 0 means no limit.
                            A medium article takes roughly 10,000–20,000 tokens.</p>
                    </div>
                </div>
            </div>

            <div>
                <button type="submit" class="wp-btn-primary px-4 h-8 font-semibold">Save Changes</button>
            </div>
        </form>

        {{-- This month --}}
        <div class="wp-metabox mt-8" id="usage">
            <div class="wp-metabox-header">This month</div>
            <div class="wp-metabox-content p-4">
                <div class="flex flex-wrap gap-8 mb-4">
                    <div><div class="text-[22px] font-semibold text-[#1d2327]">{{ number_format($usage['requests']) }}</div><div class="text-[12px] text-[#646970]">requests</div></div>
                    <div><div class="text-[22px] font-semibold text-[#1d2327]">{{ number_format($usage['tokens']) }}</div><div class="text-[12px] text-[#646970]">tokens ({{ number_format($usage['input']) }} in, {{ number_format($usage['output']) }} out)</div></div>
                    @if($limit > 0)
                        <div><div class="text-[22px] font-semibold text-[#1d2327]">{{ min(100, (int) floor($usage['tokens'] / $limit * 100)) }}%</div><div class="text-[12px] text-[#646970]">of the monthly limit</div></div>
                    @endif
                </div>
                @if($breakdown)
                    <table class="w-full text-[13px]">
                        <thead><tr class="text-left text-[#646970] border-b border-[#f0f0f1]">
                            <th class="py-1.5 font-normal">Provider</th><th class="py-1.5 font-normal">Model</th><th class="py-1.5 font-normal">For</th>
                            <th class="py-1.5 font-normal text-right">Requests</th><th class="py-1.5 font-normal text-right">Tokens</th>
                        </tr></thead>
                        <tbody>
                            @foreach($breakdown as $row)
                                <tr class="border-b border-[#f6f7f7]">
                                    <td class="py-1.5">{{ isset($providers[$row->provider]) ? $providers[$row->provider]->label() : $row->provider }}</td>
                                    <td class="py-1.5 font-mono text-[12px]">{{ $row->model }}</td>
                                    <td class="py-1.5">{{ str_replace('_', ' ', ucfirst($row->feature)) }}</td>
                                    <td class="py-1.5 text-right">{{ number_format($row->requests) }}</td>
                                    <td class="py-1.5 text-right">{{ number_format($row->input + $row->output) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="text-[11px] text-[#8c8f94] mt-2">Only counts are kept, never what was written. Your provider's dashboard shows what it costs.</p>
                @else
                    <p class="text-[13px] text-[#646970]">Nothing yet this month.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- A searchable dropdown for the long language list (Tom Select, shipped with the CMS). --}}
    <link rel="stylesheet" href="{{ asset('vendor/falcon-cms/css/tom-select.default.min.css') }}">
    @php $falconAiCss = falcon_plugin_asset('falcon-ai', 'admin/css/ai-writer.css'); @endphp
    @if($falconAiCss !== '')
        <link rel="stylesheet" href="{{ $falconAiCss }}">
    @endif
    <script src="{{ asset('vendor/falcon-cms/js/tom-select.complete.min.js') }}"></script>
    <script>
    (function () {
        if (window.TomSelect) {
            document.querySelectorAll('select[data-ai-searchable]').forEach(function (select) {
                new TomSelect(select, {
                    maxOptions: 200, create: false, plugins: ['dropdown_input'],
                    wrapperClass: 'ts-wrapper falcon-ai-ts', dropdownClass: 'ts-dropdown falcon-ai-ts-dropdown'
                });
            });
        }
        document.querySelectorAll('[data-ai-test]').forEach(function (button) {
            button.addEventListener('click', function () {
                var id = button.getAttribute('data-ai-test');
                var out = document.querySelector('[data-ai-test-result="' + id + '"]');
                out.textContent = 'Testing…';
                out.style.color = '#646970';
                fetch(@json(route('admin.ai.settings.test')), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                    body: JSON.stringify({ provider: id })
                })
                    .then(function (r) { return r.json(); })
                    .then(function (d) { out.textContent = d.message || 'No answer.'; out.style.color = d.ok ? '#00a32a' : '#b32d2e'; })
                    .catch(function () { out.textContent = 'The test could not run.'; out.style.color = '#b32d2e'; });
            });
        });
    })();
    </script>
</x-falcon-cms::layouts.admin>
