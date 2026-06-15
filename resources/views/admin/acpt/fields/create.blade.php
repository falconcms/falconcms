<x-falcon-cms::layouts.admin>
    <div class="max-w-5xl mx-auto">
        <div class="mb-6 flex justify-between items-center">
            <h1 class="text-[23px] font-normal text-[#1d2327]">Add New Field Group</h1>
            <div class="flex gap-2">
                <a href="{{ route('admin.acpt.fields.index') }}" class="wp-btn-secondary px-4 py-1.5 shadow-sm">Cancel</a>
                <button type="button" onclick="document.getElementById('field-group-form').submit()" class="wp-btn-primary px-6 py-1.5 shadow-md">Save Group</button>
            </div>
        </div>

        @if($errors->any())
            <div class="bg-white border-l-4 border-[#d63638] p-3 mb-6 shadow-sm text-[13px] text-[#1d2327]">
                <p class="font-bold mb-2">Error: Please check the following fields:</p>
                <ul class="list-disc list-inside text-[#d63638] space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="field-group-form" action="{{ route('admin.acpt.fields.store') }}" method="POST">
            @csrf
            <div class="space-y-5">
                <!-- Group Title Area -->
                <div class="bg-white border border-[#c3c4c7] shadow-sm rounded-sm">
                    <div class="bg-[#f6f7f7] border-b border-[#c3c4c7] px-4 py-2 font-bold text-[13px]">Group Title</div>
                    <div class="p-4 flex items-center justify-between gap-6">
                        <input type="text" name="title" class="wp-input w-full text-[16px] py-1" placeholder="Enter group title here" required>
                        
                        <div class="flex items-center gap-3 shrink-0">
                            <span class="text-[12px] font-bold text-[#646970] uppercase">Status</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" checked class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-[#2271b1] after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Location Rules -->
                <div class="bg-white border border-[#c3c4c7] shadow-sm rounded-sm">
                    <div class="bg-[#f6f7f7] border-b border-[#c3c4c7] px-4 py-2 font-bold text-[13px]">Location Rules</div>
                    <div class="p-5">
                        <p class="text-[13px] text-[#2c3338] mb-4">Show this field group if:</p>
                        <div class="flex items-center gap-3 text-[13px]">
                            <span class="font-medium">Post Type</span>
                            <span class="text-[#646970]">is equal to</span>
                            <select name="rules[post_type]" class="wp-input h-8 py-0 text-[13px]">
                                <option value="post">Post</option>
                                <option value="page">Page</option>
                                @foreach($postTypes as $type)
                                    <option value="{{ $type->slug }}">{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Fields List Container -->
                <div class="bg-white border border-[#c3c4c7] shadow-sm rounded-sm overflow-hidden">
                    <div class="bg-[#f6f7f7] border-b border-[#c3c4c7] px-4 py-2 font-bold text-[13px]">Fields</div>
                    
                    <div id="fields-list" class="divide-y divide-[#f0f0f1]">
                        <!-- Dynamic rows -->
                    </div>

                    <div class="bg-[#fcfcfc] p-6 text-center border-t border-[#c3c4c7]">
                        <button type="button" onclick="addNewFieldRow()" class="wp-btn-primary px-8 py-2 text-[14px] shadow-sm">Add Field</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <template id="field-row-template">
        <div class="field-item bg-white" data-id="{ID}">
            <!-- Header -->
            <div class="flex items-center px-4 py-3 cursor-pointer hover:bg-[#fcfcfc] field-header" onclick="toggleFieldBody(this)">
                <div class="w-8 text-[11px] text-[#c3c4c7] font-mono">{INDEX}</div>
                <div class="flex-grow">
                    <span class="font-bold text-[14px] field-label-display">New Field</span>
                    <span class="ml-3 text-[12px] text-[#646970] font-mono field-name-display"></span>
                </div>
                <div class="text-[11px] uppercase text-[#c3c4c7] font-bold mr-6 field-type-display">Text</div>
                <svg class="w-4 h-4 text-[#646970] transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
            
            <!-- Body -->
            <div class="hidden bg-[#fcfcfc] border-t border-[#f0f0f1] field-body">
                <!-- Tabs -->
                <div class="flex bg-white border-b border-[#f0f0f1] px-4">
                    <button type="button" onclick="switchFieldTab(this, 'general')" class="field-tab-btn active px-4 py-2 text-[13px] text-[#2271b1] border-b-2 border-[#2271b1] font-bold">General</button>
                    <button type="button" onclick="switchFieldTab(this, 'validation')" class="field-tab-btn px-4 py-2 text-[13px] text-[#646970] border-b-2 border-transparent">Validation</button>
                </div>

                <div class="p-6 space-y-5">
                    <!-- General -->
                    <div class="field-tab-content active space-y-5" data-tab="general">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-[13px] font-bold text-[#1d2327] mb-1">Field Label</label>
                                <input type="text" name="fields[{ID}][label]" oninput="handleLabelInput(this)" class="wp-input w-full text-[13px]" placeholder="e.g. Hero Title">
                            </div>
                            <div>
                                <label class="block text-[13px] font-bold text-[#1d2327] mb-1">Field Name (Slug)</label>
                                <input type="text" name="fields[{ID}][name]" oninput="updateDisplay(this, '.field-name-display')" class="wp-input w-full text-[13px] font-mono field-name-input" placeholder="e.g. hero_title">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-[13px] font-bold text-[#1d2327] mb-1">Field Type</label>
                                <select name="fields[{ID}][type]" onchange="updateDisplay(this, '.field-type-display'); toggleSelectOptions(this)" class="wp-input w-full h-8 py-0">
                                    <option value="text">Text</option>
                                    <option value="textarea">Textarea</option>
                                    <option value="select">Select</option>
                                    <option value="checkbox">Checkbox</option>
                                    <option value="radio">Radio Button</option>
                                    <option value="image">Image</option>
                                    <option value="wysiwyg">Rich Editor</option>
                                    <option value="repeater">Repeater</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[13px] font-bold text-[#1d2327] mb-1">Default Value</label>
                                <input type="text" name="fields[{ID}][default_value]" class="wp-input w-full text-[13px]">
                            </div>
                        </div>
                        <div class="select-options-row hidden">
                            <label class="block text-[13px] font-bold text-[#1d2327] mb-1">Options <span class="font-normal text-[#646970]">(one per line)</span></label>
                            <textarea name="fields[{ID}][options]" rows="4" class="wp-input w-full text-[13px]" placeholder="Option 1&#10;Option 2&#10;Option 3"></textarea>
                        </div>
                        <div class="repeater-subfields-row hidden mt-4">
                            <label class="block text-[13px] font-bold text-[#1d2327] mb-2">Sub Fields</label>
                            <div class="sf-list space-y-2 mb-3"></div>
                            <button type="button" onclick="addSubField(this)" class="text-[12px] text-[#2271b1] hover:underline">+ Add Sub Field</button>
                        </div>
                    </div>

                    <!-- Validation -->
                    <div class="field-tab-content hidden space-y-5" data-tab="validation">
                        <div class="flex items-center gap-4">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="fields[{ID}][required]" value="1" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-[#2271b1] after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                            </label>
                            <span class="text-[13px] font-bold text-[#1d2327]">Required</span>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4 border-t border-[#f0f0f1] gap-3">
                        <button type="button" onclick="toggleFieldBody(this.closest('.field-item').querySelector('.field-header'))" class="text-[12px] text-[#2271b1] hover:underline">Close Field</button>
                        <button type="button" onclick="this.closest('.field-item').remove()" class="text-[12px] text-[#d63638] hover:underline">Remove Field</button>
                    </div>
                </div>
            </div>
        </div>
    </template>

    @push('scripts')
    <script>
        let fieldCount = 0;

        function addNewFieldRow() {
            fieldCount++;
            const template = document.getElementById('field-row-template').innerHTML;
            const html = template.replace(/{ID}/g, fieldCount).replace(/{INDEX}/g, fieldCount);
            document.getElementById('fields-list').insertAdjacentHTML('beforeend', html);
            
            const newRow = document.getElementById('fields-list').lastElementChild;
            toggleFieldBody(newRow.querySelector('.field-header'));
        }

        function toggleFieldBody(header) {
            const item = header.closest('.field-item');
            const body = item.querySelector('.field-body');
            const icon = header.querySelector('svg');
            body.classList.toggle('hidden');
            icon.classList.toggle('rotate-180');
        }

        function handleLabelInput(input) {
            const item = input.closest('.field-item');
            const nameInput = item.querySelector('.field-name-input');
            const labelDisplay = item.querySelector('.field-label-display');
            
            labelDisplay.innerText = input.value || 'New Field';
            
            // Auto-slugify (only if name is empty or was auto-generated)
            const slug = input.value.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
            nameInput.value = slug;
            item.querySelector('.field-name-display').innerText = slug;
        }

        function updateDisplay(input, targetClass) {
            const item = input.closest('.field-item');
            item.querySelector(targetClass).innerText = input.value;
        }

        function toggleSelectOptions(selectEl) {
            const item = selectEl.closest('.field-item');
            const optRow = item.querySelector('.select-options-row');
            const repRow = item.querySelector('.repeater-subfields-row');
            const val = selectEl.value;
            if (optRow) optRow.classList.toggle('hidden', !['select', 'checkbox', 'radio'].includes(val));
            if (repRow) repRow.classList.toggle('hidden', val !== 'repeater');
        }

        function addSubField(btn) {
            const item = btn.closest('.field-item');
            const rawId = item.dataset.id;
            const prefix = String(rawId).startsWith('new_') ? `new_fields[${rawId.replace('new_', '')}]` : `fields[${rawId}]`;
            const sfList = item.querySelector('.sf-list');
            const html = `<div class="sf-row flex items-center gap-2 mt-1">
                <input type="text" name="${prefix}[sf_label][]" placeholder="Label" oninput="autoSlugSF(this)" class="wp-input flex-1 text-[13px] h-8 py-0">
                <input type="text" name="${prefix}[sf_name][]" placeholder="slug" class="wp-input flex-1 text-[13px] h-8 py-0 font-mono sf-name-input">
                <select name="${prefix}[sf_type][]" class="wp-input w-28 h-8 py-0 text-[13px]">
                    <option value="text">Text</option>
                    <option value="textarea">Textarea</option>
                    <option value="image">Image</option>
                </select>
                <button type="button" onclick="this.closest('.sf-row').remove()" class="text-[#d63638] text-[12px] hover:underline shrink-0">Remove</button>
            </div>`;
            sfList.insertAdjacentHTML('beforeend', html);
        }

        function autoSlugSF(input) {
            const row = input.closest('.sf-row');
            const nameInput = row.querySelector('.sf-name-input');
            if (nameInput) nameInput.value = input.value.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
        }

        function switchFieldTab(btn, tabName) {
            const item = btn.closest('.field-item');
            item.querySelectorAll('.field-tab-btn').forEach(b => {
                b.classList.remove('active', 'text-[#2271b1]', 'border-[#2271b1]', 'font-bold');
                b.classList.add('text-[#646970]', 'border-transparent');
            });
            btn.classList.add('active', 'text-[#2271b1]', 'border-[#2271b1]', 'font-bold');
            item.querySelectorAll('.field-tab-content').forEach(c => c.classList.add('hidden'));
            item.querySelector(`.field-tab-content[data-tab="${tabName}"]`).classList.remove('hidden');
        }

        document.addEventListener('DOMContentLoaded', () => addNewFieldRow());
    </script>
    @endpush
</x-falcon-cms::layouts.admin>
