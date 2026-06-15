<x-falcon-cms::layouts.admin title="Edit Taxonomy">
    <div class="max-w-[1280px] mx-auto pb-12" x-data="taxonomyForm()">
        <x-falcon-cms::admin.delete-modal />
        <form action="{{ route('admin.acpt.taxonomies.update', $taxonomy->id) }}" method="POST">
            @csrf
            @method('PUT')

            <!-- Header -->
            <div class="flex items-center justify-between mb-4 mt-2">
                <h1 class="text-[22px] font-normal text-[#1d2327]">Edit Taxonomy: <strong>{{ $taxonomy->name }}</strong></h1>
                <button type="submit" class="bg-[#2271b1] hover:bg-[#135e96] text-white px-3 py-[4px] text-[13px] rounded-[3px] border border-[#2271b1]">Update</button>
            </div>

            @if($errors->any())
                <div class="bg-white border-l-4 border-[#d63638] p-3 mb-4 shadow-[0_1px_1px_rgba(0,0,0,0.04)] text-[13px] text-[#1d2327]">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white border border-[#c3c4c7] shadow-[0_1px_1px_rgba(0,0,0,0.04)] rounded-[4px] mb-6 p-6 space-y-6">

                <!-- Plural Label -->
                <div class="grid grid-cols-[200px_1fr] items-start">
                    <label class="text-[13px] font-semibold text-[#2c3338] pt-1">Plural Label <span class="text-[#d63638]">*</span></label>
                    <div class="w-full max-w-[400px]">
                        <input type="text" name="plural_label" x-model="pluralLabel" required
                            value="{{ old('plural_label', $taxonomy->name) }}"
                            class="w-full border-[#8c8f94] focus:border-[#2271b1] border py-1.5 px-3 rounded-[3px] shadow-[inset_0_1px_2px_rgba(0,0,0,0.07)] text-[14px]">
                    </div>
                </div>

                <!-- Singular Label -->
                <div class="grid grid-cols-[200px_1fr] items-start">
                    <label class="text-[13px] font-semibold text-[#2c3338] pt-1">Singular Label <span class="text-[#d63638]">*</span></label>
                    <div class="w-full max-w-[400px]">
                        <input type="text" name="singular_label" required
                            value="{{ old('singular_label', $taxonomy->singular_name) }}"
                            class="w-full border-[#8c8f94] focus:border-[#2271b1] border py-1.5 px-3 rounded-[3px] shadow-[inset_0_1px_2px_rgba(0,0,0,0.07)] text-[14px]">
                    </div>
                </div>

                <!-- Taxonomy Key -->
                <div class="grid grid-cols-[200px_1fr] items-start">
                    <label class="text-[13px] font-semibold text-[#2c3338] pt-1">Taxonomy Key <span class="text-[#d63638]">*</span></label>
                    <div class="w-full max-w-[400px]">
                        <input type="text" name="taxonomy_key" x-model="taxonomyKey" required
                            value="{{ old('taxonomy_key', $taxonomy->slug) }}"
                            class="w-full border-[#8c8f94] focus:border-[#2271b1] border py-1.5 px-3 rounded-[3px] shadow-[inset_0_1px_2px_rgba(0,0,0,0.07)] text-[14px]">
                        <p class="text-[12px] text-[#2271b1] mt-1">Lower case letters, underscores and dashes only. Max 32 characters.</p>
                    </div>
                </div>

                <!-- Post Types (Single select) -->
                <div class="grid grid-cols-[200px_1fr] items-start">
                    <label class="text-[13px] font-semibold text-[#2c3338] pt-1">Post Type <span class="text-[#d63638]">*</span></label>
                    <div class="w-full max-w-[400px]">
                        @php
                            $pts = $taxonomy->post_types;
                            $selectedPt = is_array($pts) ? reset($pts) : null;
                        @endphp
                        <select name="post_types[]" required class="w-full border-[#8c8f94] focus:border-[#2271b1] border py-1.5 px-3 rounded-[3px] shadow-[inset_0_1px_2px_rgba(0,0,0,0.07)] text-[14px]">
                            <option value="">— Select Post Type —</option>
                            @foreach($postTypes as $pt)
                                <option value="{{ $pt->slug }}" @selected($selectedPt === $pt->slug)>{{ $pt->name }} ({{ $pt->slug }})</option>
                            @endforeach
                        </select>
                        <p class="text-[12px] text-[#646970] mt-1">Select which post type this taxonomy applies to.</p>
                    </div>
                </div>

                <!-- Style Mode -->
                <div class="grid grid-cols-[200px_1fr] items-start">
                    <label class="text-[13px] font-semibold text-[#2c3338] pt-1">Style Mode</label>
                    <div class="w-full max-w-[400px]">
                        <select name="hierarchical" class="w-full border-[#8c8f94] focus:border-[#2271b1] border py-1.5 px-3 rounded-[3px] shadow-[inset_0_1px_2px_rgba(0,0,0,0.07)] text-[14px]">
                            <option value="1" @selected($taxonomy->hierarchical)>Hierarchical Taxonomy (Category/Tree structure)</option>
                            <option value="0" @selected(!$taxonomy->hierarchical)>Non-Hierarchical Taxonomy (Tag structure)</option>
                        </select>
                        <p class="text-[12px] text-[#646970] mt-1">Hierarchical taxonomies can have parents/children (like Categories). Non-hierarchical ones are flat (like Tags).</p>
                    </div>
                </div>

            </div>

                <div class="flex justify-end pt-4">
                    <button type="submit" class="bg-[#2271b1] hover:bg-[#135e96] text-white px-4 py-2 text-[13px] rounded-[3px]">Update</button>
                </div>
            </div>
        </form>

        <div class="flex justify-between items-center -mt-10 mb-10 px-6">
            <form id="delete-taxonomy-form" action="{{ route('admin.acpt.taxonomies.destroy', $taxonomy->id) }}" method="POST">
                @csrf @method('DELETE')
                <button type="button" onclick="confirmDeleteTaxonomy()" class="text-[#d63638] text-[13px] hover:underline">Delete Taxonomy</button>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        const availablePostTypes = @json($postTypes->map(fn($pt) => ['name' => $pt->name . ' (' . $pt->slug . ')', 'slug' => $pt->slug])->values());
        const existingPostTypes  = @json(is_array($taxonomy->post_types) ? $taxonomy->post_types : []);

        document.addEventListener('alpine:init', () => {
            Alpine.data('taxonomyForm', () => ({
                pluralLabel: '{{ $taxonomy->name }}',
                singularLabel: '{{ $taxonomy->singular_name }}',
                taxonomyKey: '{{ $taxonomy->slug }}',
            }));
        });

        window.confirmDeleteTaxonomy = async function() {
            const confirmed = await window.falconConfirm({
                title: 'Delete Taxonomy',
                message: 'Are you sure you want to delete this taxonomy? This will remove all associated terms and their relationships with posts. This action cannot be undone.',
                confirmText: 'Delete Taxonomy',
                isDanger: true
            });

            if (confirmed) {
                document.getElementById('delete-taxonomy-form').submit();
            }
        };
    </script>
    <style>[x-cloak] { display: none !important; }</style>
    @endpush
</x-falcon-cms::layouts.admin>
