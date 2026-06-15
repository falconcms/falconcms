<x-cms-dashboard::layouts.admin>
    <x-slot name="title">Add New Role - FalconCMS</x-slot>

    <div class="px-2">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-[23px] font-normal text-[#1d2327]">Add New Role</h1>
            <a href="{{ route('admin.roles.index') }}" class="wp-btn-secondary h-8 px-3">Back to Roles</a>
        </div>

        <form action="{{ route('admin.roles.store') }}" method="POST" class="max-w-[1000px]">
            @csrf
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left: Basic Info -->
                <div class="lg:col-span-1 space-y-6">
                    <div class="wp-metabox">
                        <div class="wp-metabox-header">Role Details</div>
                        <div class="wp-metabox-content space-y-4">
                            <div>
                                <label for="name" class="block text-[13px] font-bold text-[#2c3338] mb-1">Name <span class="text-[#d72828]">*</span></label>
                                <input type="text" name="name" id="name" placeholder="e.g. Content Manager" required class="wp-input w-full">
                            </div>

                            <div>
                                <label for="slug" class="block text-[13px] font-bold text-[#2c3338] mb-1">Slug <span class="text-[#d72828]">*</span></label>
                                <input type="text" name="slug" id="slug" placeholder="e.g. content-manager" required class="wp-input w-full">
                                <p class="text-[11px] text-[#646970] mt-1">Unique identifier used for permission checks.</p>
                            </div>

                            <div>
                                <label for="description" class="block text-[13px] font-bold text-[#2c3338] mb-1">Description</label>
                                <textarea name="description" id="description" rows="3" class="wp-input w-full" placeholder="What can this role do?"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex gap-3">
                        <button type="submit" class="wp-btn-primary px-6">Create Role</button>
                        <a href="{{ route('admin.roles.index') }}" class="wp-btn-secondary px-6">Cancel</a>
                    </div>
                </div>

                <!-- Right: Dynamic Privileges -->
                <div class="lg:col-span-2">
                    <div class="wp-metabox">
                        <div class="wp-metabox-header">Privileges & Permissions</div>
                        <div class="wp-metabox-content">
                            <div class="space-y-8">
                                @foreach($dynamicPermissions as $groupName => $items)
                                    <div>
                                        <h3 class="text-[11px] font-bold text-[#646970] uppercase tracking-wider mb-4 border-b pb-1">{{ $groupName }}</h3>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                                            @foreach($items as $item)
                                                <div class="space-y-2 role-permission-group">
                                                    <label class="flex items-center group cursor-pointer">
                                                        <input type="checkbox" name="permissions[]" value="{{ $item['slug'] }}" 
                                                            class="parent-checkbox w-4 h-4 rounded border-[#8c8f94] text-[#2271b1] focus:ring-[#2271b1] mr-3">
                                                        <span class="text-[14px] font-semibold text-[#1d2327] group-hover:text-[#2271b1]">{{ $item['title'] }}</span>
                                                    </label>

                                                    @if(!empty($item['children']))
                                                        <div class="ml-7 space-y-2 border-l border-[#dcdcde] pl-4">
                                                            @foreach($item['children'] as $child)
                                                                <label class="flex items-center group cursor-pointer">
                                                                    <input type="checkbox" name="permissions[]" value="{{ $child['slug'] }}" 
                                                                        class="child-checkbox w-4 h-4 rounded border-[#8c8f94] text-[#2271b1] focus:ring-[#2271b1] mr-3">
                                                                    <span class="text-[13px] text-[#2c3338] group-hover:text-[#2271b1]">{{ $child['title'] }}</span>
                                                                </label>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('parent-checkbox')) {
                const group = e.target.closest('.role-permission-group');
                if (group) {
                    const children = group.querySelectorAll('.child-checkbox');
                    children.forEach(child => {
                        child.checked = e.target.checked;
                    });
                }
            }
        });
    </script>
    @endpush
</x-cms-dashboard::layouts.admin>

