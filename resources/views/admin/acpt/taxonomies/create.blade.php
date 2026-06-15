<x-falcon-cms::layouts.admin title="Add New Taxonomy">
    <div class="max-w-[1280px] mx-auto pb-12" x-data="taxonomyForm()">
        <form action="{{ route('admin.acpt.taxonomies.store') }}" method="POST">
            @csrf

            <!-- Header -->
            <div class="flex items-center justify-between mb-4 mt-2">
                <h1 class="text-[22px] font-normal text-[#1d2327]">Add New Taxonomy</h1>
                <button type="submit" class="bg-[#2271b1] hover:bg-[#135e96] text-white px-3 py-[4px] text-[13px] rounded-[3px] border border-[#2271b1]">Save Changes</button>
            </div>

            @if($errors->any())
                <div class="bg-white border-l-4 border-[#d63638] p-3 mb-4 shadow-[0_1px_1px_rgba(0,0,0,0.04)] text-[13px] text-[#1d2327]">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Main Box -->
            <div class="bg-white border border-[#c3c4c7] shadow-[0_1px_1px_rgba(0,0,0,0.04)] rounded-[4px] mb-6 p-6 space-y-6">

                <!-- Plural Label -->
                <div class="grid grid-cols-[200px_1fr] items-start">
                    <label class="text-[13px] font-semibold text-[#2c3338] pt-1">Plural Label <span class="text-[#d63638]">*</span></label>
                    <div class="w-full max-w-[400px]">
                        <input type="text" name="plural_label" x-model="pluralLabel" @input="updateKey" required
                            placeholder="Genres"
                            class="w-full border-[#8c8f94] focus:border-[#2271b1] border py-1.5 px-3 rounded-[3px] shadow-[inset_0_1px_2px_rgba(0,0,0,0.07)] text-[14px]">
                    </div>
                </div>

                <!-- Singular Label -->
                <div class="grid grid-cols-[200px_1fr] items-start">
                    <label class="text-[13px] font-semibold text-[#2c3338] pt-1">Singular Label <span class="text-[#d63638]">*</span></label>
                    <div class="w-full max-w-[400px]">
                        <input type="text" name="singular_label" x-model="singularLabel" required
                            placeholder="Genre"
                            class="w-full border-[#8c8f94] focus:border-[#2271b1] border py-1.5 px-3 rounded-[3px] shadow-[inset_0_1px_2px_rgba(0,0,0,0.07)] text-[14px]">
                    </div>
                </div>

                <!-- Taxonomy Key -->
                <div class="grid grid-cols-[200px_1fr] items-start">
                    <label class="text-[13px] font-semibold text-[#2c3338] pt-1">Taxonomy Key <span class="text-[#d63638]">*</span></label>
                    <div class="w-full max-w-[400px]">
                        <input type="text" name="taxonomy_key" x-model="taxonomyKey" required
                            placeholder="genre"
                            class="w-full border-[#8c8f94] focus:border-[#2271b1] border py-1.5 px-3 rounded-[3px] shadow-[inset_0_1px_2px_rgba(0,0,0,0.07)] text-[14px]">
                        <p class="text-[12px] text-[#2271b1] mt-1">Lower case letters, underscores and dashes only. Max 32 characters.</p>
                    </div>
                </div>

                <!-- Post Types (Single select) -->
                <div class="grid grid-cols-[200px_1fr] items-start">
                    <label class="text-[13px] font-semibold text-[#2c3338] pt-1">Post Type <span class="text-[#d63638]">*</span></label>
                    <div class="w-full max-w-[400px]">
                        <select name="post_types[]" required class="w-full border-[#8c8f94] focus:border-[#2271b1] border py-1.5 px-3 rounded-[3px] shadow-[inset_0_1px_2px_rgba(0,0,0,0.07)] text-[14px]">
                            <option value="">— Select Post Type —</option>
                            @foreach($postTypes as $pt)
                                <option value="{{ $pt->slug }}">{{ $pt->name }} ({{ $pt->slug }})</option>
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
                            <option value="1">Hierarchical Taxonomy (Category/Tree structure)</option>
                            <option value="0">Non-Hierarchical Taxonomy (Tag structure)</option>
                        </select>
                        <p class="text-[12px] text-[#646970] mt-1">Hierarchical taxonomies can have parents/children (like Categories). Non-hierarchical ones are flat (like Tags).</p>
                    </div>
                </div>

            </div>

            <!-- Bottom Save -->
            <div class="flex justify-end">
                <button type="submit" class="bg-[#2271b1] hover:bg-[#135e96] text-white px-4 py-2 text-[13px] rounded-[3px] border border-[#2271b1]">Save Changes</button>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        // Available post types (CPTs from DB + built-ins)
        const availablePostTypes = @json($postTypes->map(fn($pt) => ['name' => $pt->name . ' (' . $pt->slug . ')', 'slug' => $pt->slug])->values());

        document.addEventListener('alpine:init', () => {
            Alpine.data('taxonomyForm', () => ({
                pluralLabel: '',
                singularLabel: '',
                taxonomyKey: '',

                updateKey() {
                    this.taxonomyKey = this.pluralLabel.toLowerCase()
                        .replace(/[^a-z0-9]/g, '_')
                        .replace(/_+/g, '_')
                        .replace(/^_|_$/g, '')
                        .substring(0, 32);
                }
            }));
        });
    </script>
    <style>[x-cloak] { display: none !important; }</style>
    @endpush
</x-falcon-cms::layouts.admin>
