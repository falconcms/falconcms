<x-falcon-cms::layouts.admin>
    <x-slot name="title">Add New User - FalconCMS</x-slot>

    <div class="px-2">
        <h1 class="text-[23px] font-normal text-[#1d2327] mb-6">Add New User</h1>

        <form action="{{ route('admin.users.store') }}" method="POST" class="max-w-[800px]">
            @csrf
            
            <p class="text-[14px] text-[#2c3338] mb-6">Create a new user and add them to this site.</p>

        @if($errors->any())
            <div class="bg-[#fcf0f1] border-l-4 border-[#d63638] p-3 mb-6 text-[13px] text-[#1d2327]">
                <ul class="list-disc ml-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

            <table class="w-full border-separate border-spacing-y-6">
                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-2">
                        <label for="username" class="text-[14px] font-semibold text-[#1d2327]">Username (required)</label>
                    </th>
                    <td><input type="text" name="username" id="username" class="wp-input w-[400px] h-8 shadow-sm" required></td>
                </tr>
                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-2">
                        <label for="email" class="text-[14px] font-semibold text-[#1d2327]">Email (required)</label>
                    </th>
                    <td><input type="email" name="email" id="email" class="wp-input w-[400px] h-8 shadow-sm" required></td>
                </tr>
                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-2">
                        <label for="name" class="text-[14px] font-semibold text-[#1d2327]">Full Name</label>
                    </th>
                    <td><input type="text" name="name" id="name" class="wp-input w-[400px] h-8 shadow-sm" required></td>
                </tr>
                
                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-2">
                        <label for="password" class="text-[14px] font-semibold text-[#1d2327]">Password</label>
                    </th>
                    <td><input type="password" name="password" id="password" class="wp-input w-[400px] h-8 shadow-sm" required></td>
                </tr>
                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-2">
                        <label for="password_confirmation" class="text-[14px] font-semibold text-[#1d2327]">Confirm Password</label>
                    </th>
                    <td><input type="password" name="password_confirmation" id="password_confirmation" class="wp-input w-[400px] h-8 shadow-sm" required></td>
                </tr>

                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-2">
                        <label class="text-[14px] font-semibold text-[#1d2327]">Roles</label>
                    </th>
                    <td>
                        <div class="space-y-1.5">
                            @foreach($roles as $role)
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="roles[]" value="{{ $role->id }}"
                                           {{ collect(old('roles', []))->contains($role->id) ? 'checked' : '' }}
                                           class="w-4 h-4 rounded border-[#8c8f94] text-[#2271b1]">
                                    <span class="text-[13px] text-[#2c3338]">{{ $role->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="text-[11px] text-[#646970] mt-1">Select one or more roles — the user gets the combined permissions of all selected roles.</p>
                        @error('roles')<p class="text-[11px] text-[#d63638] mt-1">{{ $message }}</p>@enderror
                    </td>
                </tr>
            </table>

            <div class="mt-8 pt-6 border-t border-[#c3c4c7]">
                <button type="submit" class="wp-btn-primary h-[32px] px-4 font-semibold rounded-[3px] bg-[#2271b1] text-white">Add New User</button>
            </div>
        </form>
    </div>
</x-falcon-cms::layouts.admin>
