<x-falcon-cms::layouts.admin>
    <x-slot name="title">Email Templates - FalconCMS</x-slot>

    <div class="px-2">
        <h1 class="text-[23px] font-normal text-[#1d2327] mb-4">Settings</h1>

        @include('falcon-cms::admin.settings.nav')

        @if(session('success'))
            <div class="bg-[#edfaef] border-l-4 border-[#46b450] p-3 mb-6 text-[13px] text-[#1d2327]">{{ session('success') }}</div>
        @endif

        <div class="max-w-[860px]">

            <p class="text-[13px] text-[#646970] mb-6">
                Customize the subject and content of emails sent by FalconCMS. Use the variable chips to insert dynamic values.
                Click <strong>Reset to Default</strong> to restore a template's original text.
            </p>

            {{-- Template Tabs --}}
            @php $activeTab = request('tab', 'form_notification'); @endphp
            <div class="flex items-center gap-1 border-b border-[#c3c4c7] mb-6">
                @foreach($templates as $key => $tpl)
                    <button type="button" onclick="switchEmailTab('{{ $key }}')"
                        id="tab-btn-{{ $key }}"
                        class="email-tab-btn px-4 py-2 text-[13px] {{ $activeTab === $key ? 'text-[#1d2327] font-semibold bg-white -mb-[1px] border-l border-t border-r border-[#c3c4c7]' : 'text-[#2271b1] hover:text-[#135e96]' }}">
                        {{ $tpl['label'] }}
                    </button>
                @endforeach
            </div>

            {{-- Template Panels --}}
            @foreach($templates as $key => $tpl)
            <div id="tab-panel-{{ $key }}" class="email-tab-panel {{ $activeTab !== $key ? 'hidden' : '' }}">

                {{-- Variables --}}
                <div class="mb-5">
                    <p class="text-[12px] font-semibold text-[#646970] uppercase tracking-wide mb-2">Available Variables — click to copy</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($tpl['variables'] as $var)
                            <button type="button" onclick="insertVar('{{ $key }}', '{{ $var }}')"
                                class="font-mono text-[11px] bg-[#f0f6fc] border border-[#c3c4c7] text-[#2271b1] px-2 py-1 rounded hover:bg-[#2271b1] hover:text-white transition-colors">
                                {{ $var }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <form action="{{ route('admin.settings.email-templates.update') }}" method="POST" class="space-y-5">
                    @csrf
                    <input type="hidden" name="template_key" value="{{ $key }}">

                    {{-- Subject --}}
                    <div>
                        <label class="block text-[13px] font-semibold text-[#1d2327] mb-1">Subject Line</label>
                        <input type="text" name="subject" id="field-{{ $key }}-subject"
                            value="{{ $tpl['subject'] }}"
                            class="wp-input w-full text-[13px]"
                            placeholder="{{ $defaults[$key]['subject'] }}">
                        <p class="text-[12px] text-[#646970] mt-1">The email subject recipients will see.</p>
                    </div>

                    {{-- Form-specific fields --}}
                    @if($key === 'form_notification')
                        <div>
                            <label class="block text-[13px] font-semibold text-[#1d2327] mb-1">Intro Message</label>
                            <textarea name="intro" id="field-{{ $key }}-intro" rows="3"
                                class="wp-input w-full text-[13px]"
                                placeholder="{{ $defaults[$key]['intro'] }}">{{ $tpl['intro'] }}</textarea>
                            <p class="text-[12px] text-[#646970] mt-1">The opening paragraph shown above the submitted data table.</p>
                        </div>
                        <div>
                            <label class="block text-[13px] font-semibold text-[#1d2327] mb-1">Footer Note</label>
                            <input type="text" name="footer" id="field-{{ $key }}-footer"
                                value="{{ $tpl['footer'] }}"
                                class="wp-input w-full text-[13px]"
                                placeholder="{{ $defaults[$key]['footer'] }}">
                            <p class="text-[12px] text-[#646970] mt-1">Small note shown at the bottom of the email.</p>
                        </div>
                    @endif

                    {{-- Order-placed fields --}}
                    @if(in_array($key, ['order_placed_customer', 'order_placed_admin']))
                        <div>
                            <label class="block text-[13px] font-semibold text-[#1d2327] mb-1">Email Body Message</label>
                            <textarea name="message" id="field-{{ $key }}-message" rows="4"
                                class="wp-input w-full text-[13px]"
                                placeholder="{{ $defaults[$key]['message'] }}">{{ $tpl['message'] }}</textarea>
                            <p class="text-[12px] text-[#646970] mt-1">Main message paragraph. Basic HTML like &lt;strong&gt; is supported.</p>
                        </div>
                    @endif

                    {{-- Order status fields --}}
                    @if($key === 'order_status_updated')
                        <div>
                            <label class="block text-[13px] font-semibold text-[#1d2327] mb-1">Default Status Message</label>
                            <textarea name="message_default" id="field-{{ $key }}-message_default" rows="3"
                                class="wp-input w-full text-[13px]">{{ $tpl['message_default'] }}</textarea>
                            <p class="text-[12px] text-[#646970] mt-1">Used when no specific message is set for the status.</p>
                        </div>
                        <div>
                            <label class="block text-[13px] font-semibold text-[#1d2327] mb-1">Message — Completed</label>
                            <textarea name="message_completed" id="field-{{ $key }}-message_completed" rows="3"
                                class="wp-input w-full text-[13px]">{{ $tpl['message_completed'] }}</textarea>
                        </div>
                        <div>
                            <label class="block text-[13px] font-semibold text-[#1d2327] mb-1">Message — Processing</label>
                            <textarea name="message_processing" id="field-{{ $key }}-message_processing" rows="3"
                                class="wp-input w-full text-[13px]">{{ $tpl['message_processing'] }}</textarea>
                        </div>
                    @endif

                    {{-- Actions --}}
                    <div class="flex items-center gap-3 pt-2 border-t border-[#f0f0f1]">
                        <button type="submit" class="wp-btn-primary px-6 py-1.5 shadow-sm">Save Template</button>
                        <button type="button" onclick="resetTemplate('{{ $key }}')" class="wp-btn-secondary px-4 py-1.5">Reset to Default</button>
                        <button type="button" onclick="sendTestEmail('{{ $key }}')" class="text-[13px] text-[#2271b1] hover:underline ml-2" id="test-btn-{{ $key }}">
                            Send Test Email
                        </button>
                        <span id="test-result-{{ $key }}" class="text-[12px] ml-1 hidden"></span>
                    </div>
                </form>
            </div>
            @endforeach

        </div>
    </div>

    @push('scripts')
    <script>
        const defaults = @json($defaults);

        function switchEmailTab(key) {
            document.querySelectorAll('.email-tab-panel').forEach(p => p.classList.add('hidden'));
            document.querySelectorAll('.email-tab-btn').forEach(b => {
                b.classList.remove('text-[#1d2327]', 'font-semibold', 'bg-white', '-mb-[1px]', 'border-l', 'border-t', 'border-r', 'border-[#c3c4c7]');
                b.classList.add('text-[#2271b1]', 'hover:text-[#135e96]');
            });
            document.getElementById('tab-panel-' + key)?.classList.remove('hidden');
            const btn = document.getElementById('tab-btn-' + key);
            if (btn) {
                btn.classList.add('text-[#1d2327]', 'font-semibold', 'bg-white', '-mb-[1px]', 'border-l', 'border-t', 'border-r', 'border-[#c3c4c7]');
                btn.classList.remove('text-[#2271b1]', 'hover:text-[#135e96]');
            }
        }

        function insertVar(key, variable) {
            // Try to find the last focused field in the active panel
            const panel = document.getElementById('tab-panel-' + key);
            const fields = panel.querySelectorAll('input[type="text"], textarea');
            let target = null;
            fields.forEach(f => { if (document.activeElement === f) target = f; });
            if (!target) target = fields[0];
            if (!target) return;

            const start = target.selectionStart;
            const end = target.selectionEnd;
            const val = target.value;
            target.value = val.substring(0, start) + variable + val.substring(end);
            target.selectionStart = target.selectionEnd = start + variable.length;
            target.focus();
        }

        function resetTemplate(key) {
            const d = defaults[key];
            if (!d) return;
            const panel = document.getElementById('tab-panel-' + key);

            const fields = {
                subject:             d.subject,
                intro:               d.intro,
                footer:              d.footer,
                message:             d.message,
                message_default:     d.message_default,
                message_completed:   d.message_completed,
                message_processing:  d.message_processing,
            };

            Object.entries(fields).forEach(([name, val]) => {
                if (val === undefined) return;
                const el = panel.querySelector(`[name="${name}"]`);
                if (el) el.value = val;
            });
        }

        async function sendTestEmail(key) {
            const btn = document.getElementById('test-btn-' + key);
            const result = document.getElementById('test-result-' + key);
            btn.textContent = 'Sending...';
            btn.disabled = true;
            result.classList.add('hidden');

            try {
                const res = await fetch('{{ route('admin.settings.email-templates.test') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ template_key: key })
                });
                const data = await res.json();
                result.textContent = data.message;
                result.className = 'text-[12px] ml-1 ' + (data.success ? 'text-[#46b450]' : 'text-[#d63638]');
                result.classList.remove('hidden');
            } catch (e) {
                result.textContent = 'Failed to send.';
                result.className = 'text-[12px] ml-1 text-[#d63638]';
                result.classList.remove('hidden');
            }

            btn.textContent = 'Send Test Email';
            btn.disabled = false;
        }
    </script>
    @endpush
</x-falcon-cms::layouts.admin>
