<x-falcon-cms::layouts.admin pro-lock-allow-forms="1" pro-lock-feature="custom_fields">
    <x-falcon-cms::admin.delete-modal />
    <div class="max-w-5xl mx-auto">
        <div class="mb-6 flex justify-between items-center">
            <h1 class="text-[23px] font-normal text-[#1d2327]">Edit Field Group: <span class="font-bold">{{ $fieldGroup->title }}</span></h1>
            <div class="flex gap-2">
                <a href="{{ route('admin.acpt.fields.index') }}" class="wp-btn-secondary px-4 py-1.5 shadow-sm">Back</a>
                <button type="button" onclick="document.getElementById('field-group-form').submit()" class="wp-btn-primary px-6 py-1.5 shadow-md inline-flex items-center gap-1" @if(! falcon_pro_editable('custom_fields')) title="This feature is available in the Pro version." @endif>@if(! falcon_pro_editable('custom_fields'))<span class="material-symbols-outlined" style="font-size:15px">lock</span>@endif Update Group</button>
            </div>
        </div>

        @if(session('success'))
            <div class="bg-white border-l-4 border-[#46b450] shadow-sm p-3 mb-4 text-[13px]">
                {{ session('success') }}
            </div>
        @endif

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

        <form id="field-group-form" action="{{ route('admin.acpt.fields.update', $fieldGroup) }}" method="POST">
            @csrf @method('PUT')
            <div class="space-y-5">
                <!-- Group Title Area -->
                <div class="bg-white border border-[#c3c4c7] shadow-sm rounded-sm">
                    <div class="bg-[#f6f7f7] border-b border-[#c3c4c7] px-4 py-2 font-bold text-[13px]">Group Title</div>
                    <div class="p-4 flex items-center justify-between gap-6">
                        <input type="text" name="title" value="{{ $fieldGroup->title }}" class="wp-input w-full text-[16px] py-1" placeholder="Enter group title here" required>
                        
                        <div class="flex items-center gap-3 shrink-0">
                            <span class="text-[12px] font-bold text-[#646970] uppercase">Status</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" {{ $fieldGroup->is_active ? 'checked' : '' }} class="sr-only peer">
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
                                <option value="post" {{ ($fieldGroup->rules['post_type'] ?? '') === 'post' ? 'selected' : '' }}>Post</option>
                                <option value="page" {{ ($fieldGroup->rules['post_type'] ?? '') === 'page' ? 'selected' : '' }}>Page</option>
                                @foreach($postTypes as $type)
                                    <option value="{{ $type->slug }}" {{ ($fieldGroup->rules['post_type'] ?? '') === $type->slug ? 'selected' : '' }}>{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Fields List -->
                <div class="bg-white border border-[#c3c4c7] shadow-sm rounded-sm overflow-hidden">
                    <div class="bg-[#f6f7f7] border-b border-[#c3c4c7] px-4 py-2 font-bold text-[13px]">Fields</div>
                    
                    <div id="fields-list" class="divide-y divide-[#f0f0f1]">
                        @foreach($fieldGroup->fields as $index => $field)
                        <div class="field-item bg-white" data-id="{{ $field->id }}">
                            <!-- Header -->
                            <div class="flex items-center px-4 py-3 cursor-pointer hover:bg-[#fcfcfc] field-header" onclick="toggleFieldBody(this)">
                                <div class="w-8 text-[11px] text-[#c3c4c7] font-mono">{{ $index + 1 }}</div>
                                <div class="flex-grow">
                                    <span class="font-bold text-[14px] field-label-display">{{ $field->label }}</span>
                                    <span class="ml-3 text-[12px] text-[#646970] font-mono field-name-display">{{ $field->name }}</span>
                                </div>
                                <div class="text-[11px] uppercase text-[#c3c4c7] font-bold mr-6 field-type-display">{{ $field->type }}</div>
                                <svg class="w-4 h-4 text-[#646970] transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                            
                            <!-- Body -->
                            <div class="hidden bg-[#fcfcfc] border-t border-[#f0f0f1] field-body">
                                <div class="flex bg-white border-b border-[#f0f0f1] px-4">
                                    <button type="button" onclick="switchFieldTab(this, 'general')" class="field-tab-btn active px-4 py-2 text-[13px] text-[#2271b1] border-b-2 border-[#2271b1] font-bold">General</button>
                                    <button type="button" onclick="switchFieldTab(this, 'validation')" class="field-tab-btn px-4 py-2 text-[13px] text-[#646970] border-b-2 border-transparent">Validation</button>
                                </div>

                                <div class="p-6 space-y-5">
                                    <div class="field-tab-content active space-y-5" data-tab="general">
                                        <input type="hidden" name="fields[{{ $field->id }}][id]" value="{{ $field->id }}">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div>
                                                <label class="block text-[13px] font-bold text-[#1d2327] mb-1">Field Label</label>
                                                <input type="text" name="fields[{{ $field->id }}][label]" value="{{ $field->label }}" oninput="handleLabelInput(this)" class="wp-input w-full text-[13px]">
                                            </div>
                                            <div>
                                                <label class="block text-[13px] font-bold text-[#1d2327] mb-1">Field Name (Slug)</label>
                                                <input type="text" name="fields[{{ $field->id }}][name]" value="{{ $field->name }}" oninput="updateDisplay(this, '.field-name-display')" class="wp-input w-full text-[13px] font-mono field-name-input">
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div>
                                                <label class="block text-[13px] font-bold text-[#1d2327] mb-1">Field Type</label>
                                                <select name="fields[{{ $field->id }}][type]" onchange="updateDisplay(this, '.field-type-display'); toggleSelectOptions(this)" class="wp-input w-full h-8 py-0">
                                                    <option value="text" {{ $field->type === 'text' ? 'selected' : '' }}>Text</option>
                                                    <option value="textarea" {{ $field->type === 'textarea' ? 'selected' : '' }}>Textarea</option>
                                                    <option value="select" {{ $field->type === 'select' ? 'selected' : '' }}>Select</option>
                                                    <option value="checkbox" {{ $field->type === 'checkbox' ? 'selected' : '' }}>Checkbox</option>
                                                    <option value="radio" {{ $field->type === 'radio' ? 'selected' : '' }}>Radio Button</option>
                                                    <option value="image" {{ $field->type === 'image' ? 'selected' : '' }}>Image</option>
                                                    <option value="wysiwyg" {{ $field->type === 'wysiwyg' ? 'selected' : '' }}>Rich Editor</option>
                                                    <option value="repeater" {{ $field->type === 'repeater' ? 'selected' : '' }}>Repeater</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="select-options-row {{ !in_array($field->type, ['select', 'checkbox', 'radio']) ? 'hidden' : '' }}">
                                            <label class="block text-[13px] font-bold text-[#1d2327] mb-1">Options <span class="font-normal text-[#646970]">(one per line)</span></label>
                                            <textarea name="fields[{{ $field->id }}][options]" rows="4" class="wp-input w-full text-[13px]" placeholder="Option 1&#10;Option 2&#10;Option 3">{{ $field->params['options'] ?? '' }}</textarea>
                                        </div>
                                        @php $subFields = $field->params['sub_fields'] ?? []; @endphp
                                        <div class="repeater-subfields-row {{ $field->type !== 'repeater' ? 'hidden' : '' }} mt-4">
                                            <label class="block text-[13px] font-bold text-[#1d2327] mb-2">Sub Fields</label>
                                            <div class="sf-list space-y-2 mb-3">
                                                @foreach($subFields as $sf)
                                                <div class="sf-row flex items-center gap-2 mt-1">
                                                    <input type="text" name="fields[{{ $field->id }}][sf_label][]" value="{{ $sf['label'] }}" placeholder="Label" oninput="autoSlugSF(this)" class="wp-input flex-1 text-[13px] h-8 py-0">
                                                    <input type="text" name="fields[{{ $field->id }}][sf_name][]" value="{{ $sf['name'] }}" placeholder="slug" class="wp-input flex-1 text-[13px] h-8 py-0 font-mono sf-name-input">
                                                    <select name="fields[{{ $field->id }}][sf_type][]" class="wp-input w-28 h-8 py-0 text-[13px]">
                                                        <option value="text" {{ ($sf['type'] ?? 'text') === 'text' ? 'selected' : '' }}>Text</option>
                                                        <option value="textarea" {{ ($sf['type'] ?? '') === 'textarea' ? 'selected' : '' }}>Textarea</option>
                                                        <option value="image" {{ ($sf['type'] ?? '') === 'image' ? 'selected' : '' }}>Image</option>
                                                    </select>
                                                    <button type="button" onclick="this.closest('.sf-row').remove()" class="text-[#d63638] text-[12px] hover:underline shrink-0">Remove</button>
                                                </div>
                                                @endforeach
                                            </div>
                                            <button type="button" onclick="addSubField(this)" class="text-[12px] text-[#2271b1] hover:underline">+ Add Sub Field</button>
                                        </div>
                                    </div>

                                    <div class="field-tab-content hidden space-y-5" data-tab="validation">
                                        <div class="flex items-center gap-4">
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="checkbox" name="fields[{{ $field->id }}][required]" value="1" {{ $field->required ? 'checked' : '' }} class="sr-only peer">
                                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-[#2271b1] after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                                            </label>
                                            <span class="text-[13px] font-bold text-[#1d2327]">Required</span>
                                        </div>
                                    </div>

                                    <div class="flex justify-end pt-4 border-t border-[#f0f0f1] gap-3">
                                        <button type="button" onclick="deleteExistingField({{ $field->id }})" class="text-[12px] text-[#d63638] hover:underline">Delete Field</button>
                                        <button type="button" onclick="toggleFieldBody(this.closest('.field-item').querySelector('.field-header'))" class="text-[12px] text-[#2271b1] hover:underline">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <div class="bg-[#fcfcfc] p-6 text-center border-t border-[#c3c4c7]">
                        <button type="button" onclick="addNewFieldRow()" class="wp-btn-primary px-8 py-2 text-[14px] shadow-sm">Add New Field</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Template for new fields -->
    <template id="field-row-template">
        <div class="field-item bg-[#fcfcfc]" data-id="new_{ID}">
            <!-- Header -->
            <div class="flex items-center px-4 py-3 cursor-pointer hover:bg-[#f3f4f6] field-header" onclick="toggleFieldBody(this)">
                <div class="w-8 text-[11px] text-[#c3c4c7]">New</div>
                <div class="flex-grow">
                    <span class="font-bold text-[14px] field-label-display">New Field</span>
                    <span class="ml-3 text-[12px] text-[#646970] font-mono field-name-display"></span>
                </div>
                <div class="text-[11px] uppercase text-[#c3c4c7] font-bold mr-6 field-type-display">Text</div>
                <svg class="w-4 h-4 text-[#646970] transition-transform duration-200 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
            
            <!-- Body -->
            <div class="bg-white border-t border-[#f0f0f1] field-body">
                <div class="flex bg-[#fcfcfc] border-b border-[#f0f0f1] px-4">
                    <button type="button" onclick="switchFieldTab(this, 'general')" class="field-tab-btn active px-4 py-2 text-[13px] text-[#2271b1] border-b-2 border-[#2271b1] font-bold">General</button>
                    <button type="button" onclick="switchFieldTab(this, 'validation')" class="field-tab-btn px-4 py-2 text-[13px] text-[#646970] border-b-2 border-transparent">Validation</button>
                </div>
                <div class="p-6 space-y-5">
                    <div class="field-tab-content active space-y-5" data-tab="general">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-[13px] font-bold text-[#1d2327] mb-1">Field Label</label>
                                <input type="text" name="new_fields[{ID}][label]" oninput="handleLabelInput(this)" class="wp-input w-full text-[13px]">
                            </div>
                            <div>
                                <label class="block text-[13px] font-bold text-[#1d2327] mb-1">Field Name</label>
                                <input type="text" name="new_fields[{ID}][name]" oninput="updateDisplay(this, '.field-name-display')" class="wp-input w-full text-[13px] font-mono field-name-input">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-[13px] font-bold text-[#1d2327] mb-1">Field Type</label>
                                <select name="new_fields[{ID}][type]" onchange="updateDisplay(this, '.field-type-display'); toggleSelectOptions(this)" class="wp-input w-full h-8 py-0 select-type-input">
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
                        </div>
                        <div class="select-options-row hidden">
                            <label class="block text-[13px] font-bold text-[#1d2327] mb-1">Options <span class="font-normal text-[#646970]">(one per line)</span></label>
                            <textarea name="new_fields[{ID}][options]" rows="4" class="wp-input w-full text-[13px]" placeholder="Option 1&#10;Option 2&#10;Option 3"></textarea>
                        </div>
                        <div class="repeater-subfields-row hidden mt-4">
                            <label class="block text-[13px] font-bold text-[#1d2327] mb-2">Sub Fields</label>
                            <div class="sf-list space-y-2 mb-3"></div>
                            <button type="button" onclick="addSubField(this)" class="text-[12px] text-[#2271b1] hover:underline">+ Add Sub Field</button>
                        </div>
                    </div>
                    <div class="field-tab-content hidden space-y-5" data-tab="validation">
                        <div class="flex items-center gap-4">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="new_fields[{ID}][required]" value="1" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:bg-[#2271b1] after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                            </label>
                            <span class="text-[13px] font-bold text-[#1d2327]">Required</span>
                        </div>
                    </div>
                    <div class="flex justify-end pt-4 border-t border-[#f0f0f1]">
                        <button type="button" onclick="this.closest('.field-item').remove()" class="text-[12px] text-[#d63638] hover:underline">Remove</button>
                    </div>
                </div>
            </div>
        </div>
    </template>

    @push('scripts')
    <script>
        let fieldCount = 1000;

        function addNewFieldRow() {
            fieldCount++;
            const template = document.getElementById('field-row-template').innerHTML;
            const html = template.replace(/{ID}/g, fieldCount);
            document.getElementById('fields-list').insertAdjacentHTML('beforeend', html);
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
            labelDisplay.innerText = input.value || '(empty)';
            
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

        async function deleteExistingField(id) {
            const confirmed = await window.falconConfirm({
                title: 'Delete Field',
                message: 'Are you sure you want to permanently delete this field? Any data stored in this field across your posts will be lost. This action cannot be undone.',
                confirmText: 'Delete Field',
                isDanger: true
            });

            if (!confirmed) return;
            let url = "{{ route('admin.acpt.fields.delete-field', ':id') }}";
            url = url.replace(':id', id);
            const res = await fetch(url, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            });
            if((await res.json()).success) {
                document.querySelector(`[data-id="${id}"]`).remove();
            }
        }
    </script>
    @endpush
</x-falcon-cms::layouts.admin>
