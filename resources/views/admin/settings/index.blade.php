<x-falcon-cms::layouts.admin>
    <x-slot name="title">Settings - FalconCMS</x-slot>

    <div class="px-2">
        <h1 class="text-[23px] font-normal text-[#1d2327] mb-4">Settings</h1>
        
        @include('falcon-cms::admin.settings.nav')

        @if (session('success'))
            <div class="bg-[#edfaef] border-l-4 border-[#46b450] p-3 mb-6 text-[13px] text-[#1d2327]">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('admin.settings.update') }}" method="POST" class="max-w-[800px]">
            @csrf
            {!! do_falcon_action('falcon_settings_form_top') !!}

            <table class="w-full border-separate border-spacing-y-6">
                <!-- Site Title -->
                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-2">
                        <label for="site_title" class="text-[14px] font-semibold text-[#1d2327]">Site Title</label>
                    </th>
                    <td>
                        <input type="text" name="site_title" id="site_title"
                            value="{{ $settings['site_title'] ?? 'FalconCMS' }}"
                            class="wp-input w-[400px] h-8 shadow-sm">
                    </td>
                </tr>

                <!-- Tagline -->
                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-2">
                        <label for="tagline" class="text-[14px] font-semibold text-[#1d2327]">Tagline</label>
                    </th>
                    <td>
                        <input type="text" name="tagline" id="tagline" value="{{ $settings['tagline'] ?? '' }}"
                            class="wp-input w-[400px] h-8 shadow-sm mb-1">
                        <p class="text-[12px] text-[#646970] italic">In a few words, explain what this site is about.
                            Example: “Just another WordPress site.”</p>
                    </td>
                </tr>

                <!-- Admin Email -->
                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-2">
                        <label for="admin_email" class="text-[14px] font-semibold text-[#1d2327]">Administration Email
                            Address</label>
                    </th>
                    <td>
                        <input type="email" name="admin_email" id="admin_email"
                            value="{{ $settings['admin_email'] ?? auth()->user()->email }}"
                            class="wp-input w-[400px] h-8 shadow-sm mb-1">
                        <p class="text-[12px] text-[#646970]">This address is used for admin purposes.</p>
                    </td>
                </tr>

                <!-- Timezone -->
                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-2">
                        <label for="timezone" class="text-[14px] font-semibold text-[#1d2327]">Timezone</label>
                    </th>
                    <td>
                        @php $selectedTz = $settings['timezone'] ?? config('app.timezone') ?? 'UTC'; @endphp
                        <select name="timezone" id="timezone" class="wp-input w-[400px] h-8 py-0 shadow-sm mb-1">
                            @foreach(lazy_timezone_list() as $region => $zones)
                                <optgroup label="{{ $region }}">
                                    @foreach($zones as $tzId => $tzLabel)
                                        <option value="{{ $tzId }}" {{ $selectedTz === $tzId ? 'selected' : '' }}>{{ $tzLabel }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <p class="text-[12px] text-[#646970]">
                            Choose the timezone for publishing &amp; scheduling. Dates are stored in UTC and shown in this timezone.
                            Current time here: <strong>{{ \Illuminate\Support\Carbon::now($selectedTz)->format('M j, Y H:i') }}</strong>
                        </p>
                    </td>
                </tr>

                <!-- Homepage Selection -->
                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-2">
                        <label for="home_page_id" class="text-[14px] font-semibold text-[#1d2327]">Select your Home page</label>
                    </th>
                    <td>
                        <select name="home_page_id" id="home_page_id" class="wp-input w-[400px] h-8 py-0 shadow-sm mb-1">
                            <option value="">Latest Blog Posts (Default)</option>
                            @foreach($pages as $page)
                                <option value="{{ $page->id }}" {{ ($settings['home_page_id'] ?? '') == $page->id ? 'selected' : '' }}>
                                    {{ $page->title }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-[12px] text-[#646970]">Choose what to display on your site's home page. If none selected, the latest blog posts will be shown.</p>
                    </td>
                </tr>

                <!-- Blog Page Selection -->
                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-2">
                        <label for="blog_page_id" class="text-[14px] font-semibold text-[#1d2327]">Select your Blog page</label>
                    </th>
                    <td>
                        <select name="blog_page_id" id="blog_page_id" class="wp-input w-[400px] h-8 py-0 shadow-sm mb-1">
                            <option value="">— None —</option>
                            @foreach($pages as $page)
                                <option value="{{ $page->id }}" {{ ($settings['blog_page_id'] ?? '') == $page->id ? 'selected' : '' }}>
                                    {{ $page->title }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-[12px] text-[#646970]">The page assigned here will display your blog posts (rendered by the theme's <code>index</code> template) instead of its own content.</p>
                    </td>
                </tr>

                <!-- Membership -->
                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-2">
                        <label class="text-[14px] font-semibold text-[#1d2327]">Who can Sign Up</label>
                    </th>
                    <td>
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="users_can_register" id="users_can_register"
                                class="w-4 h-4 mr-2"
                                {{ ($settings['users_can_register'] ?? '0') == '1' ? 'checked' : '' }}>
                            <span class="text-[14px] text-[#1d2327]">Anyone can register</span>
                        </label>
                    </td>
                </tr>

                <!-- Email Verification -->
                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-2">
                        <label class="text-[14px] font-semibold text-[#1d2327]">Email Verification</label>
                    </th>
                    <td>
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="require_email_verification" id="require_email_verification"
                                class="w-4 h-4 mr-2"
                                {{ ($settings['require_email_verification'] ?? '1') == '1' ? 'checked' : '' }}>
                            <span class="text-[14px] text-[#1d2327]">Require email verification before users can sign in</span>
                        </label>
                        <p class="text-[12px] text-[#646970] mt-1">If unchecked, new users are signed in immediately after registering — no verification email is sent or required.</p>
                    </td>
                </tr>

                <!-- Documentation Access -->
                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-2">
                        <label class="text-[14px] font-semibold text-[#1d2327]">Enable Documentation</label>
                    </th>
                    <td>
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="enable_documentation" id="enable_documentation"
                                class="w-4 h-4 mr-2"
                                {{ ($settings['enable_documentation'] ?? '1') == '1' ? 'checked' : '' }}>
                            <span class="text-[14px] text-[#1d2327]">Show documentation in admin menu and allow access</span>
                        </label>
                        <p class="text-[12px] text-[#646970] mt-1">If unchecked, the documentation link will be hidden and direct access will be forbidden.</p>
                    </td>
                </tr>

                <!-- Themes Group -->
                <tr id="reg-theme-row">
                    <th scope="row" class="w-[200px] text-left align-top pt-2">
                        <label for="registration_theme" class="text-[14px] font-semibold text-[#1d2327]">Choose
                            Registration theme</label>
                    </th>
                    <td>
                        <select name="registration_theme" id="registration_theme" class="wp-input w-[200px] h-8 py-0">
                            <option value="modern"
                                {{ ($settings['registration_theme'] ?? 'modern') == 'modern' ? 'selected' : '' }}>Modern</option>
                            <option value="funny"
                                {{ ($settings['registration_theme'] ?? 'breeze') == 'funny' ? 'selected' : '' }}>Funny Theme</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-2">
                        <label for="login_theme" class="text-[14px] font-semibold text-[#1d2327]">Choose login
                            theme</label>
                    </th>
                    <td>
                        <select name="login_theme" id="login_theme" class="wp-input w-[200px] h-8 py-0">
                            <option value="modern"
                                {{ ($settings['login_theme'] ?? 'modern') == 'modern' ? 'selected' : '' }}>Modern</option>
                            <option value="funny"
                                {{ ($settings['login_theme'] ?? 'breeze') == 'funny' ? 'selected' : '' }}>Funny Theme</option>
                        </select>
                    </td>
                </tr>

                <!-- URLs Group -->
                <tr id="reg-url-row">
                    <th scope="row" class="w-[200px] text-left align-top pt-2">
                        <label for="register_url" class="text-[14px] font-semibold text-[#1d2327]">Change registration
                            url</label>
                    </th>
                    <td>
                        <div class="flex items-center gap-2">
                            <span class="text-[#646970] text-[13px]">{{ url('/') }}/</span>
                            <input type="text" name="register_url" id="register_url"
                                value="{{ $settings['register_url'] ?? 'lazy-register' }}"
                                class="wp-input w-[280px] h-8 shadow-sm">
                        </div>
                    </td>
                </tr>

                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-2">
                        <label for="login_url" class="text-[14px] font-semibold text-[#1d2327]">Change login url</label>
                    </th>
                    <td>
                        <div class="flex items-center gap-2">
                            <span class="text-[#646970] text-[13px]">{{ url('/') }}/</span>
                            <input type="text" name="login_url" id="login_url"
                                value="{{ $settings['login_url'] ?? 'lazy-admin' }}"
                                class="wp-input w-[280px] h-8 shadow-sm">
                        </div>
                    </td>
                </tr>

                <!-- Default Role -->
                <tr id="default-role-row">
                    <th scope="row" class="w-[200px] text-left align-top pt-2">
                        <label for="default_role" class="text-[14px] font-semibold text-[#1d2327]">New User Default
                            Role</label>
                    </th>
                    <td>
                        <select name="default_role" id="default_role" class="wp-input w-[200px] h-8 py-0">
                            @foreach($roles as $role)
                                <option value="{{ $role->slug }}"
                                    {{ ($settings['default_role'] ?? 'subscriber') == $role->slug ? 'selected' : '' }}>
                                    {{ $role->name }}
                                </option>
                            @endforeach
                        </select>
                    </td>
                </tr>

                <!-- Multi-device Login -->
                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-2">
                        <label class="text-[14px] font-semibold text-[#1d2327]">Multi-device Login</label>
                    </th>
                    <td>
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="allow_multi_device" id="allow_multi_device"
                                class="w-4 h-4 mr-2"
                                {{ ($settings['allow_multi_device'] ?? '0') == '1' ? 'checked' : '' }}>
                            <span class="text-[14px] text-[#1d2327]">Limit the number of devices per account</span>
                        </label>
                        <p class="text-[12px] text-[#646970] mt-1">When unchecked, users can stay logged in on <strong>unlimited</strong> devices. Check this to cap concurrent logins at the number set below.</p>
                    </td>
                </tr>

                <!-- Magic Login -->
                <tr>
                    <th scope="row" class="w-[200px] text-left align-top pt-2">
                        <label class="text-[14px] font-semibold text-[#1d2327]">Magic Login</label>
                    </th>
                    <td>
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="magic_login_enabled" id="magic_login_enabled"
                                class="w-4 h-4 mr-2"
                                {{ ($settings['magic_login_enabled'] ?? '0') == '1' ? 'checked' : '' }}>
                            <span class="text-[14px] text-[#1d2327]">Enable passwordless login via magic link (sent to email)</span>
                        </label>
                        <p class="text-[12px] text-[#646970] mt-1">When enabled, users can sign in without a password — both on the shop account page and the admin login form.</p>
                    </td>
                </tr>

                <tr id="max-devices-row">
                    <th scope="row" class="w-[200px] text-left align-top pt-2">
                        <label for="max_devices" class="text-[14px] font-semibold text-[#1d2327]">Max devices allowed</label>
                    </th>
                    <td>
                        <input type="number" name="max_devices" id="max_devices"
                            value="{{ $settings['max_devices'] ?? '3' }}" min="1"
                            class="wp-input w-[100px] h-8 shadow-sm mb-1">
                        <p class="text-[12px] text-[#646970]">Maximum number of devices a user can be logged in on at once. (Default: 3)</p>
                    </td>
                </tr>
            </table>

            {!! do_falcon_action('falcon_settings_form_bottom') !!}

            <div class="pt-6 border-t border-gray-100 mt-6">
                <button type="submit" class="wp-btn-primary px-4 h-8 font-semibold">Save Changes</button>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const registerCheckbox = document.getElementById('users_can_register');
                const regRows = [
                    document.getElementById('reg-theme-row'),
                    document.getElementById('reg-url-row'),
                    document.getElementById('default-role-row')
                ];

                const multiDeviceCheckbox = document.getElementById('allow_multi_device');
                const maxDevicesRow = document.getElementById('max-devices-row');

                function toggleRegistrationFields() {
                    const isVisible = registerCheckbox.checked;
                    regRows.forEach(row => {
                        if (row) {
                            row.style.display = isVisible ? 'table-row' : 'none';
                        }
                    });
                }

                function toggleMultiDeviceFields() {
                    if (maxDevicesRow) {
                        maxDevicesRow.style.display = multiDeviceCheckbox.checked ? 'table-row' : 'none';
                    }
                }

                // Initial checks
                toggleRegistrationFields();
                toggleMultiDeviceFields();

                // Listen for changes
                registerCheckbox.addEventListener('change', toggleRegistrationFields);
                multiDeviceCheckbox.addEventListener('change', toggleMultiDeviceFields);
                
                // Media Modal for settings
                document.querySelectorAll('.open-media-for-setting').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const target = this.getAttribute('data-target');
                        window.openMediaModal(function(media) {
                            const input = document.getElementById('input-' + target);
                            if (input) input.value = media.path;
                            const preview = document.getElementById('media-preview-' + target);
                            if (preview) {
                                preview.innerHTML = `<img src="/storage/${media.path}" class="max-w-full max-h-full object-contain">`;
                                preview.classList.remove('hidden');
                            }
                        });
                    });
                });

            });
        </script>
    @endpush

    {{-- Searchable Timezone dropdown (TomSelect) — same UX as the shop country selector --}}
    @push('scripts')
        <link href="{{ asset('vendor/falcon-cms/css/tom-select.default.min.css') }}" rel="stylesheet">
        <script src="{{ asset('vendor/falcon-cms/js/tom-select.complete.min.js') }}"></script>
        <style>
            .ts-wrapper.wp-input { padding: 0 !important; border: none !important; box-shadow: none !important; height: auto !important; min-height: 32px !important; }
            #timezone + .ts-wrapper { max-width: 400px; }
            .ts-control {
                border: 1px solid #8c8f94 !important; border-radius: 3px !important; padding: 4px 12px !important;
                font-size: 14px !important; color: #1d2327 !important; background-color: #fff !important;
                box-shadow: 0 1px 2px rgba(0,0,0,0.07) inset !important; min-height: 32px !important;
                display: flex !important; align-items: center !important;
            }
            .ts-dropdown { font-size: 13px; }
            .ts-dropdown .optgroup-header { font-weight: 600; color: #646970; }
        </style>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (window.TomSelect && document.getElementById('timezone')) {
                    new TomSelect('#timezone', {
                        create: false,
                        maxOptions: null,           // show all timezones; search narrows the list
                        placeholder: 'Search timezone (e.g. Dhaka, +06, Asia)…',
                        searchField: ['text', 'value'],
                    });
                }
            });
        </script>
    @endpush
</x-falcon-cms::layouts.admin>
