<x-falcon-cms::layouts.admin>
    <x-slot name="title">{{ $tab['label'] }} - Settings - FalconCMS</x-slot>

    <div class="px-2">
        <h1 class="text-[23px] font-normal text-[#1d2327] mb-4">Settings</h1>

        @include('falcon-cms::admin.settings.nav')

        @if (session('success'))
            <div class="bg-[#edfaef] border-l-4 border-[#46b450] p-3 mb-6 text-[13px] text-[#1d2327]">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('admin.settings.custom-tab.save', ['tab' => $tab['id']]) }}" method="POST" class="max-w-[800px]">
            @csrf

            @if(!empty($fields))
                @include('falcon-cms::admin.partials.settings-fields-native', ['fields' => $fields, 'values' => $values])
            @else
                <p class="text-[13px] text-[#646970] py-6">No fields have been registered for this tab yet.</p>
            @endif

            <p class="mt-6">
                <button type="submit" class="wp-btn-primary px-4 h-8 font-semibold">Save Changes</button>
            </p>
        </form>
    </div>

    @include('falcon-cms::admin.partials.options-fields-assets')
</x-falcon-cms::layouts.admin>
