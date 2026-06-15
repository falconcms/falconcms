<x-cms-dashboard::layouts.admin>
    <x-slot name="title">Users - FalconCMS</x-slot>

    <div class="px-2">
        <div class="flex items-baseline gap-2 mb-6">
            <h1 class="text-[23px] font-normal text-[#1d2327]">Users</h1>
            <a href="{{ route('admin.users.create') }}" class="border border-[#2271b1] text-[#2271b1] px-2 py-0.5 rounded-[3px] text-[13px] font-semibold hover:bg-[#f0f6fa]">Add New</a>
        </div>

        @if(session('success'))
            <div class="bg-[#edfaef] border-l-4 border-[#46b450] p-3 mb-6 text-[13px] text-[#1d2327]">
                {{ session('success') }}
            </div>
        @endif
        
        @if(session('error'))
            <div class="bg-[#fcf0f1] border-l-4 border-[#d63638] p-3 mb-6 text-[13px] text-[#1d2327]">
                {{ session('error') }}
            </div>
        @endif

        <div class="flex justify-between items-center mb-2">
            <div class="tabs-nav">
                <ul class="flex flex-wrap gap-2 text-[13px] text-[#1d2327] border-slate-200">
                    <li class="pr-2 border-r border-slate-300"><a href="{{ route('admin.users.index') }}" class="{{ !request('role') && !request('status') ? 'font-bold text-black' : 'text-[#2271b1]' }}">All <span class="text-[#646970] font-normal">({{ $allCount }})</span></a></li>
                    @foreach($roles as $role)
                        @if($role->count > 0)
                            <li class="px-2 border-r border-slate-300">
                                <a href="{{ route('admin.users.index', ['role' => $role->slug]) }}" class="{{ request('role') == $role->slug ? 'font-bold text-black' : 'text-[#2271b1]' }}">
                                    {{ $role->name }} <span class="text-[#646970] font-normal">({{ $role->count }})</span>
                                </a>
                            </li>
                        @endif
                    @endforeach
                    <li class="pl-2"><a href="{{ route('admin.users.index', ['status' => 'blocked']) }}" class="{{ request('status') == 'blocked' ? 'font-bold text-black' : 'text-[#2271b1]' }}">Blocked <span class="text-[#f87171] font-normal">({{ $blockedCount }})</span></a></li>
                </ul>
            </div>

            <form action="{{ route('admin.users.index') }}" method="GET" class="flex gap-1">
                <input type="search" name="s" value="{{ request('s') }}" class="wp-input h-8 px-2 border border-[#8c8f94] focus:border-[#2271b1] outline-none" placeholder="Search Users...">
                <button type="submit" class="border border-[#8c8f94] text-[#2c3338] px-3 py-1 rounded-[3px] text-[13px] font-semibold hover:bg-[#f6f7f7]">Search Users</button>
            </form>
        </div>

        {{-- Hidden Bulk Action Form --}}
        <form id="user-bulk-form" action="{{ route('admin.users.bulk') }}" method="POST" class="hidden">
            @csrf
        </form>

        <div class="flex justify-between items-center mb-2">
            <div class="flex gap-1 items-center">
                <select name="action" form="user-bulk-form" class="wp-input h-8 text-[13px]">
                    <option>Bulk Actions</option>
                    <option value="delete">Delete</option>
                    <option value="block">Block</option>
                    <option value="unblock">Unblock</option>
                </select>
                <button type="submit" form="user-bulk-form" class="border border-[#2271b1] text-[#2271b1] px-3 py-1 rounded-[3px] text-[13px] font-semibold hover:bg-[#f0f6fa]">Apply</button>
            </div>
            
            <x-cms-dashboard::admin.pagination :paginator="$users" size="small" />
        </div>

        <div class="bg-white border border-[#c3c4c7] shadow-sm overflow-x-auto">
            <table class="w-full text-left text-[13px] border-collapse">
                <thead>
                    <tr class="border-b border-[#c3c4c7] bg-[#f9f9f9]">
                        <th class="p-2 w-10 text-center"><input type="checkbox" id="select-all"></th>
                        <th class="p-2 font-semibold">Username</th>
                        <th class="p-2 font-semibold">Name</th>
                        <th class="p-2 font-semibold">Email</th>
                        <th class="p-2 font-semibold">Role</th>
                        <th class="p-2 font-semibold">Posts</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr class="border-b border-[#f0f0f1] hover:bg-[#f6f7f7] group">
                            <td class="p-2 text-center"><input type="checkbox" name="ids[]" value="{{ $user->id }}" form="user-bulk-form" class="user-checkbox"></td>
                            <td class="p-2 font-semibold">
                                <div class="flex items-center gap-2">
                                    <img src="https://secure.gravatar.com/avatar/{{ md5($user->email) }}?s=32&d=mm" class="w-8 h-8 rounded">
                                    <div>
                                        @php 
                                            $isBlocked = $user->is_blocked || ($user->blocked_until && $user->blocked_until->isFuture());
                                        @endphp
                                        <div class="flex items-center gap-2">
                                            <a href="{{ route('admin.users.edit', $user) }}" class="text-[#2271b1] font-semibold">{{ $user->username ?: $user->name }}</a>
                                            @if($isBlocked)
                                                <span class="bg-[#f87171] text-white text-[10px] px-1.5 py-0.5 rounded font-bold uppercase">Blocked</span>
                                            @endif
                                        </div>
                                        <div class="flex gap-2 opacity-0 group-hover:opacity-100 text-[12px] mt-1 pr-4 items-center">
                                            <a href="{{ route('admin.users.edit', $user) }}" class="text-[#2271b1]">Edit</a>
                                            <span class="text-[#c3c4c7]">|</span>
                                            <button type="button" 
                                                    onclick="openDeleteModal('{{ $user->id }}', '{{ $user->name }}')"
                                                    class="text-[#b32d2e] {{ auth()->id() == $user->id ? 'hidden' : '' }}">
                                                Delete
                                            </button>
                                            <span class="text-[#c3c4c7] {{ auth()->id() == $user->id ? 'hidden' : '' }}">|</span>
                                            
                                            <form action="{{ route('admin.users.toggle-block', $user) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="{{ $isBlocked ? 'text-[#00a32a]' : 'text-[#b32d2e]' }} {{ auth()->id() == $user->id ? 'hidden' : '' }}">
                                                    {{ $isBlocked ? 'Unblock' : 'Block' }}
                                                </button>
                                            </form>
                                            <span class="text-[#c3c4c7] {{ auth()->id() == $user->id ? 'hidden' : '' }}">|</span>
                                            <a href="#" class="text-[#2271b1]">View</a>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-2">{{ $user->name }}</td>
                            <td class="p-2 "><a href="mailto:{{ $user->email }}" class="text-[#2271b1]">{{ $user->email }}</a></td>
                            <td class="p-2 text-[#2c3338]">{{ $user->role ? $user->role->name : 'No Role' }}</td>
                            <td class="p-2 text-[#2271b1]">
                                <a href="{{ route('admin.posts.index', ['author' => $user->id]) }}" class="text-[#2271b1] hover:underline font-semibold">
                                    {{ \DB::table('posts')->where('user_id', $user->id)->count() }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-4 text-center text-slate-500 italic">No users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex justify-between items-center text-[13px] text-[#2c3338]">
            <div class="flex items-center space-x-2">
                <select name="action2" form="user-bulk-form" class="wp-input h-8 text-[13px]">
                    <option>Bulk Actions</option>
                    <option value="delete">Delete</option>
                    <option value="block">Block</option>
                    <option value="unblock">Unblock</option>
                </select>
                <button type="submit" form="user-bulk-form" class="border border-[#2271b1] text-[#2271b1] px-3 py-1 rounded-[3px] text-[13px] font-semibold hover:bg-[#f0f6fa]">Apply</button>
                <span class="ml-2 text-[#2c3338]">{{ $users->total() }} items</span>
            </div>
            <x-cms-dashboard::admin.pagination :paginator="$users" />
        </div>

    </div>

    <!-- User Delete Modal -->
    <div id="delete-user-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black bg-opacity-50">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="bg-white rounded-[3px] shadow-xl w-full max-w-md overflow-hidden">
                <div class="bg-[#fcfcfc] border-b border-[#dcdcde] px-4 py-3 flex justify-between items-center">
                    <h3 class="text-[14px] font-bold text-[#1d2327]">Delete User: <span id="delete-user-name"></span></h3>
                    <button type="button" onclick="closeDeleteModal()" class="text-[#646970] hover:text-[#2271b1]">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <form id="delete-user-form" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    
                    <div class="p-4 text-[13px] text-[#1d2327]">
                        <p class="mb-4">What should be done with content owned by this user?</p>
                        
                        <div class="space-y-3">
                            <label class="flex items-start gap-2 cursor-pointer">
                                <input type="radio" name="delete_option" value="delete" checked class="mt-0.5" onclick="toggleReassign(false)">
                                <span>Delete all content (Posts, Pages, CPTs)</span>
                            </label>
                            
                            <label class="flex items-start gap-2 cursor-pointer">
                                <input type="radio" name="delete_option" value="reassign" class="mt-0.5" onclick="toggleReassign(true)">
                                <span>Attribute all content to:</span>
                            </label>
                            
                            <div id="reassign-container" class="ml-6 hidden">
                                <select name="reassign_to" class="wp-input w-full h-8 text-[13px]">
                                    @foreach($allUsers as $otherUser)
                                        <option value="{{ $otherUser->id }}">{{ $otherUser->name }} ({{ $otherUser->username }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-[#fcfcfc] border-t border-[#dcdcde] px-4 py-3 flex justify-end gap-2">
                        <button type="button" onclick="closeDeleteModal()" class="border border-[#8c8f94] text-[#2c3338] px-3 py-1 rounded-[3px] text-[13px] font-semibold hover:bg-[#f6f7f7]">Cancel</button>
                        <button type="submit" class="bg-[#d63638] text-white px-3 py-1 rounded-[3px] text-[13px] font-semibold hover:bg-[#b32d2e]">Confirm Deletion</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.getElementById('select-all')?.addEventListener('click', function() {
            document.querySelectorAll('.user-checkbox').forEach(cb => {
                cb.checked = this.checked;
            });
        });

        function openDeleteModal(userId, userName) {
            const modal = document.getElementById('delete-user-modal');
            const form = document.getElementById('delete-user-form');
            const nameSpan = document.getElementById('delete-user-name');
            
            // Set name and action
            nameSpan.textContent = userName;
            form.action = "{{ route('admin.users.index') }}/" + userId;
            
            // Show modal
            modal.classList.remove('hidden');
            
            // Filter out the current user from reassignment dropdown
            const select = form.querySelector('select[name="reassign_to"]');
            Array.from(select.options).forEach(opt => {
                opt.style.display = opt.value == userId ? 'none' : 'block';
                if (opt.value == userId) opt.disabled = true;
                else opt.disabled = false;
            });
            
            // Pick the first available user if current one was selected
            if (select.value == userId) {
                for (let i = 0; i < select.options.length; i++) {
                    if (select.options[i].value != userId) {
                        select.selectedIndex = i;
                        break;
                    }
                }
            }
        }

        function closeDeleteModal() {
            document.getElementById('delete-user-modal').classList.add('hidden');
        }

        function toggleReassign(show) {
            const container = document.getElementById('reassign-container');
            if (show) container.classList.remove('hidden');
            else container.classList.add('hidden');
        }
    </script>
    @endpush
</x-cms-dashboard::layouts.admin>
