@extends('falcon-cms::themes.falcon-theme.layouts.app')

@section('title', 'My Account')

@section('content')
@php $primaryColor = get_cms_option('theme_primary_color', '#4f46e5'); @endphp
<div class="bg-gray-50 py-16 min-h-screen">
    <div class="container-custom">

@auth
{{-- ─── Logged-in view ─────────────────────────────────────────────── --}}
@php
    $user = auth()->user();
    // Active tab: driven by flash (after save/error) then URL param, default orders
    $activeTab = request('tab', 'orders');
    if (session('profile_success') || $errors->hasAny(['name','email'])) $activeTab = 'profile';
    if (session('password_success') || $errors->hasAny(['current_password','password'])) $activeTab = 'password';
@endphp

        {{-- Header --}}
        <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-3xl font-bold text-heading">My Account</h1>
                <p class="text-body mt-1">Welcome back, <strong>{{ $user->name ?? 'Customer' }}</strong>.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('shop.wishlist') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-sm border border-slate-200 bg-white text-slate-600 text-sm font-bold hover:border-primary hover:text-primary transition">
                    <i data-lucide="heart" class="w-4 h-4"></i> Wishlist
                    <span class="falcon-wishlist-count bg-primary text-white text-[11px] font-bold rounded-full px-1.5 min-w-[18px] text-center">{{ falcon_wishlist_count() }}</span>
                </a>
                <form action="{{ route('shop.account.logout') }}" method="POST">
                    @csrf
                    <input type="hidden" name="redirect_to" value="{{ url()->current() }}">
                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-sm border border-slate-200 bg-white text-slate-600 text-sm font-bold hover:border-primary hover:text-primary transition">
                        <i data-lucide="log-out" class="w-4 h-4"></i> Log out
                    </button>
                </form>
            </div>
        </div>

        {{-- Tab Navigation --}}
        @php $tabBase = strtok(url()->current(), '?'); @endphp
        <div class="flex gap-1 border-b border-gray-200 mb-6">
            @foreach([['orders','My Orders','package'],['downloads','Downloads','download'],['profile','Profile','user'],['password','Password','lock']] as [$slug,$label,$icon])
            <a href="{{ $tabBase }}?tab={{ $slug }}"
               class="inline-flex items-center gap-1.5 px-5 py-2.5 text-sm font-semibold border-b-2 transition
                      {{ $activeTab === $slug
                           ? 'border-primary text-primary'
                           : 'border-transparent text-slate-500 hover:text-primary hover:border-primary' }}">
                <i data-lucide="{{ $icon }}" class="w-4 h-4"></i> {{ $label }}
            </a>
            @endforeach
        </div>

        {{-- ── ORDERS TAB ── --}}
        @if($activeTab === 'orders')
        <div class="bg-white rounded-sm shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-sm font-black uppercase tracking-widest text-heading">My Orders</h2>
                <form action="" method="GET" class="flex items-center gap-2">
                    <input type="hidden" name="tab" value="orders">
                    <input type="text" name="s" value="{{ request('s') }}" placeholder="Search orders..."
                           class="border border-slate-200 rounded px-3 py-1.5 text-sm outline-none focus:border-primary transition w-44">
                    <button type="submit" class="bg-primary text-white px-3 py-1.5 rounded text-sm font-semibold hover:bg-primary-hover transition">Search</button>
                    @if(request('s'))
                        <a href="{{ $tabBase }}?tab=orders" class="text-xs text-slate-400 hover:text-primary">Clear</a>
                    @endif
                </form>
            </div>

            @if($orders->isEmpty())
                <div class="p-12 text-center">
                    <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="package" class="w-8 h-8 text-slate-300"></i>
                    </div>
                    @if(request('s'))
                        <p class="text-body mb-5">No orders match "<strong>{{ request('s') }}</strong>".</p>
                        <a href="{{ $tabBase }}?tab=orders" class="inline-block bg-primary text-white px-6 py-2.5 rounded-sm font-bold hover:bg-primary-hover transition uppercase text-sm">View all orders</a>
                    @else
                        <p class="text-body mb-5">You haven't placed any orders yet.</p>
                        <a href="{{ get_lazy_shop_url() }}" class="inline-block bg-primary text-white px-6 py-2.5 rounded-sm font-bold hover:bg-primary-hover transition uppercase text-sm">Start shopping</a>
                    @endif
                </div>
            @else
                @php
                    $statusColors = [
                        'pending'            => 'bg-amber-100 text-amber-700',
                        'processing'         => 'bg-blue-100 text-blue-700',
                        'completed'          => 'bg-emerald-100 text-emerald-700',
                        'cancelled'          => 'bg-rose-100 text-rose-700',
                        'partially-refunded' => 'bg-purple-100 text-purple-700',
                        'refunded'           => 'bg-gray-200 text-gray-600',
                        'on-hold'            => 'bg-amber-100 text-amber-700',
                        'failed'             => 'bg-rose-100 text-rose-700',
                    ];
                @endphp
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-gray-500 text-[12px] uppercase tracking-wider">
                            <th class="px-6 py-3 font-bold">Order</th>
                            <th class="px-6 py-3 font-bold">Date</th>
                            <th class="px-6 py-3 font-bold">Status</th>
                            <th class="px-6 py-3 font-bold text-right">Total</th>
                            <th class="px-6 py-3 font-bold text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $order)
                            <tr class="border-t border-gray-100 hover:bg-gray-50/60">
                                <td class="px-6 py-4 font-bold text-heading">#{{ $order->order_number ?: $order->id }}</td>
                                <td class="px-6 py-4 text-body">{{ cms_date($order->created_at, 'M d, Y') }}</td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase {{ $statusColors[$order->status] ?? 'bg-gray-100 text-gray-600' }}">
                                        {{ str_replace('-', ' ', $order->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right font-bold text-heading">{{ falcon_price_format($order->total, $order) }}</td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('shop.confirmation', $order->id) }}" class="text-primary font-bold hover:underline">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if($orders->hasPages())
                    <div class="px-6 py-4 border-t border-gray-100 flex justify-end">
                        {{ $orders->links('falcon-cms::components.admin.pagination') }}
                    </div>
                @endif
            @endif
        </div>

        {{-- ── DOWNLOADS TAB ── --}}
        @elseif($activeTab === 'downloads')
        @php
            $myDownloads = \FalconCms\Core\Models\OrderDownload::with(['productDownload', 'order'])
                ->whereHas('order', fn($q) => $q->where('customer_email', $user->email))
                ->orderByDesc('created_at')
                ->get();
        @endphp
        <div class="bg-white rounded-sm shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-black uppercase tracking-widest text-heading">My Downloads</h2>
            </div>
            @if($myDownloads->isEmpty())
            <div class="flex flex-col items-center justify-center py-20 text-center px-6">
                <i data-lucide="download" class="w-12 h-12 text-gray-200 mb-4"></i>
                <p class="text-gray-400 text-sm">You have no downloadable purchases yet.</p>
            </div>
            @else
            <div class="divide-y divide-gray-100">
                @foreach($myDownloads as $dl)
                @php
                    $file = $dl->productDownload;
                    $accessible = $dl->isAccessible();
                @endphp
                <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">{{ $file?->name ?? 'File' }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            Order #{{ $dl->order?->order_number }}
                            @if($dl->expires_at) · Expires {{ $dl->expires_at->format('M d, Y') }} @endif
                            @if($dl->download_limit) · {{ $dl->download_count }}/{{ $dl->download_limit }} downloads @endif
                        </p>
                    </div>
                    @if($accessible)
                    <a href="{{ route('shop.download', $dl->token) }}"
                       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-sm text-sm font-bold bg-primary text-white hover:opacity-90 transition">
                        <i data-lucide="download" class="w-4 h-4"></i> Download
                    </a>
                    @else
                    <span class="inline-flex items-center gap-1.5 px-4 py-2 rounded-sm text-sm font-bold bg-gray-100 text-gray-400 cursor-not-allowed">
                        @if($dl->isExpired()) Expired @else Limit reached @endif
                    </span>
                    @endif
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- ── PROFILE TAB ── --}}
        @elseif($activeTab === 'profile')
        <div class="max-w-lg bg-white rounded-sm shadow-sm border border-gray-100 p-8">
            <h2 class="text-sm font-black uppercase tracking-widest text-heading mb-6">Edit Profile</h2>

            @if(session('profile_success'))
                <div class="mb-5 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm px-4 py-3 rounded flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-4 h-4 flex-shrink-0"></i> {{ session('profile_success') }}
                </div>
            @endif

            <form action="{{ route('shop.account.profile.update') }}" method="POST" class="space-y-5">
                @csrf
                <div class="space-y-1.5">
                    <label class="text-sm font-semibold text-heading">Full Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}"
                           class="w-full border {{ $errors->has('name') ? 'border-red-400' : 'border-slate-200' }} rounded-sm px-3 py-2.5 text-sm outline-none focus:border-primary transition">
                    @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="space-y-1.5">
                    <label class="text-sm font-semibold text-heading">Email Address</label>
                    <input type="text" value="{{ $user->email }}" disabled
                           class="w-full border border-slate-200 rounded-sm px-3 py-2.5 text-sm bg-gray-50 text-slate-400 cursor-not-allowed">
                    <p class="text-xs text-slate-400 mt-1">Email address cannot be changed.</p>
                </div>
                <button type="submit" class="inline-block bg-primary text-white px-6 py-2.5 rounded-sm font-bold hover:bg-primary-hover transition text-sm uppercase">
                    Save Changes
                </button>
            </form>
        </div>

        {{-- ── PASSWORD TAB ── --}}
        @elseif($activeTab === 'password')
        <style>
            .acc-str-track { height:3px; background:#e5e7eb; border-radius:99px; overflow:hidden; margin-top:6px; }
            .acc-str-fill  { height:100%; width:0; transition:width .3s,background-color .3s; border-radius:99px; }
            .acc-pwd-wrap  { position:relative; }
            .acc-pwd-wrap input { padding-right:2.5rem; }
            .acc-eye { position:absolute; right:.65rem; top:50%; transform:translateY(-50%);
                       background:none; border:none; cursor:pointer; color:#9ca3af; padding:0; line-height:0; }
            .acc-eye:hover { color:#6b7280; }
        </style>
        <div class="max-w-lg bg-white rounded-sm shadow-sm border border-gray-100 p-8">
            <h2 class="text-sm font-black uppercase tracking-widest text-heading mb-6">Change Password</h2>

            @if(session('password_success'))
                <div class="mb-5 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm px-4 py-3 rounded flex items-center gap-2">
                    <i data-lucide="check-circle" class="w-4 h-4 flex-shrink-0"></i> {{ session('password_success') }}
                </div>
            @endif

            <form action="{{ route('shop.account.password.update') }}" method="POST" class="space-y-5">
                @csrf
                <div class="space-y-1.5">
                    <label class="text-sm font-semibold text-heading">Current Password <span class="text-red-500">*</span></label>
                    <div class="acc-pwd-wrap">
                        <input type="password" name="current_password" id="acc_cur_pwd" autocomplete="current-password"
                               class="w-full border {{ $errors->has('current_password') ? 'border-red-400' : 'border-slate-200' }} rounded-sm px-3 py-2.5 text-sm outline-none focus:border-primary transition">
                        <button type="button" class="acc-eye" data-target="acc_cur_pwd" tabindex="-1" aria-label="Show/hide password">
                            <svg id="acc_cur_pwd-eye-off" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                            <svg id="acc_cur_pwd-eye-on" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>
                    </div>
                    @error('current_password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="space-y-1.5">
                    <label class="text-sm font-semibold text-heading">New Password <span class="text-red-500">*</span></label>
                    <div class="acc-pwd-wrap">
                        <input type="password" name="password" id="acc_new_pwd" autocomplete="new-password"
                               class="w-full border {{ $errors->has('password') ? 'border-red-400' : 'border-slate-200' }} rounded-sm px-3 py-2.5 text-sm outline-none focus:border-primary transition">
                        <button type="button" class="acc-eye" data-target="acc_new_pwd" tabindex="-1" aria-label="Show/hide password">
                            <svg id="acc_new_pwd-eye-off" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                            <svg id="acc_new_pwd-eye-on" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                    <div class="acc-str-track"><div class="acc-str-fill" id="acc-str-bar"></div></div>
                    <div style="font-size:.7rem;font-weight:800;min-height:14px;margin-top:3px" id="acc-str-text"></div>
                    @error('password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="space-y-1.5">
                    <label class="text-sm font-semibold text-heading">Confirm New Password <span class="text-red-500">*</span></label>
                    <div class="acc-pwd-wrap">
                        <input type="password" name="password_confirmation" id="acc_pwd2" autocomplete="new-password"
                               class="w-full border border-slate-200 rounded-sm px-3 py-2.5 text-sm outline-none focus:border-primary transition">
                        <button type="button" class="acc-eye" data-target="acc_pwd2" tabindex="-1" aria-label="Show/hide password">
                            <svg id="acc_pwd2-eye-off" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                            <svg id="acc_pwd2-eye-on" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                    <div style="font-size:.72rem;font-weight:700;min-height:14px;margin-top:3px" id="acc-match-msg"></div>
                </div>
                <button type="submit" class="inline-block bg-primary text-white px-6 py-2.5 rounded-sm font-bold hover:bg-primary-hover transition text-sm uppercase">
                    Update Password
                </button>
            </form>
        </div>
        <script>
        (function(){
            // Eye toggle
            document.querySelectorAll('.acc-eye').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var id  = this.dataset.target;
                    var inp = document.getElementById(id);
                    var isText = inp.type === 'text';
                    inp.type = isText ? 'password' : 'text';
                    document.getElementById(id + '-eye-off').style.display = isText ? '' : 'none';
                    document.getElementById(id + '-eye-on').style.display  = isText ? 'none' : '';
                });
            });

            var pwd  = document.getElementById('acc_new_pwd');
            var pwd2 = document.getElementById('acc_pwd2');
            var bar  = document.getElementById('acc-str-bar');
            var txt  = document.getElementById('acc-str-text');
            var msg  = document.getElementById('acc-match-msg');

            function checkStrength() {
                var v = pwd.value, score = 0;
                if (v.length > 6)    score++;
                if (/[0-9]/.test(v)) score++;
                if (/[A-Z]/.test(v)) score++;
                if (!v.length) {
                    bar.style.width = '0'; txt.textContent = '';
                } else if (score <= 1) {
                    bar.style.width = '33%'; bar.style.backgroundColor = '#ef4444';
                    txt.textContent = 'WEAK'; txt.style.color = '#ef4444';
                } else if (score === 2) {
                    bar.style.width = '66%'; bar.style.backgroundColor = '#f59e0b';
                    txt.textContent = 'GOOD'; txt.style.color = '#f59e0b';
                } else {
                    bar.style.width = '100%'; bar.style.backgroundColor = '#10b981';
                    txt.textContent = 'STRONG!'; txt.style.color = '#10b981';
                }
            }
            function checkMatch() {
                if (!pwd2.value) { msg.textContent = ''; return; }
                if (pwd.value === pwd2.value) {
                    msg.textContent = '✓ Passwords match'; msg.style.color = '#10b981';
                } else {
                    msg.textContent = '✗ Passwords do not match'; msg.style.color = '#ef4444';
                }
            }
            pwd.addEventListener('input',  function(){ checkStrength(); checkMatch(); });
            pwd2.addEventListener('input', checkMatch);
        })();
        </script>
        @endif

@else
{{-- ─── Guest / Login form ──────────────────────────────────────────── --}}
<style>
.acc-lf-wrap { position: relative; }
.acc-lf-input {
    border: 1px solid #d1d5db; border-radius: 8px;
    padding: 1.3rem 14px .45rem; width: 100%;
    font-size: .9rem; color: #111827; background: #fff;
    transition: border-color .15s, box-shadow .15s;
    box-sizing: border-box; display: block;
}
.acc-lf-input:focus { outline: none; border-color: {{ $primaryColor }}; box-shadow: 0 0 0 3px {{ $primaryColor }}26; }
.acc-lf-input.has-icon { padding-right: 44px; }
.acc-lf-label {
    position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
    transition: top .15s ease, transform .15s ease, color .15s ease, background-color .15s ease;
    pointer-events: none; color: #9ca3af; font-size: .875rem; font-weight: 500; line-height: 1;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: calc(100% - 28px); z-index: 1;
}
.acc-lf-wrap.lf-focused .acc-lf-label,
.acc-lf-wrap.lf-filled  .acc-lf-label {
    top: 0; transform: translateY(-50%) scale(.78); transform-origin: left center;
    padding: 0 3px; background-color: #fff; color: #374151; max-width: none; overflow: visible;
}
.acc-lf-wrap.lf-focused .acc-lf-label { color: {{ $primaryColor }}; }
.acc-toggle-pwd {
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer; color: #9ca3af; padding: 0; line-height: 0; z-index: 10;
}
</style>

@php $magicEnabled = get_cms_option('magic_login_enabled'); @endphp
        <div class="max-w-md mx-auto">

            <div class="text-center mb-8">
                <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" style="background:{{ $primaryColor }}1a">
                    <i data-lucide="user" class="w-8 h-8" style="color:{{ $primaryColor }}"></i>
                </div>
                <h1 class="text-2xl font-bold text-heading">Sign in to your account</h1>
                <p class="text-body mt-2 text-sm">
                    @if($magicEnabled) Enter your email and we'll send you a one-click sign-in link.
                    @else Enter your credentials to view your orders and account details.
                    @endif
                </p>
            </div>

@if($magicEnabled)
            {{-- ── MAGIC LOGIN ONLY ── --}}
            @if(session('magic_sent'))
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm px-5 py-4 rounded-xl flex items-start gap-3 mb-6">
                    <i data-lucide="mail-check" class="w-5 h-5 flex-shrink-0 mt-0.5"></i>
                    <div>
                        <p class="font-semibold">Check your inbox!</p>
                        <p class="mt-0.5 opacity-90">We've sent a magic sign-in link to your email. The link expires in 10 minutes and can only be used once.</p>
                        <a href="{{ url()->current() }}" style="color:inherit;font-weight:700;font-size:.8rem;text-decoration:underline;margin-top:6px;display:inline-block">Send another link</a>
                    </div>
                </div>
            @else
                @if($errors->has('account_email') || $errors->has('magic_email'))
                    <div class="bg-rose-50 border border-rose-200 text-rose-700 text-sm px-4 py-3 rounded-lg mb-5 flex items-center gap-2">
                        <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
                        {{ $errors->first('account_email') ?: $errors->first('magic_email') }}
                    </div>
                @endif

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
                    <form action="{{ route('shop.magic.request') }}" method="POST" id="magic-form">
                        @csrf
                        <div style="display:flex;flex-direction:column;gap:1.1rem">
                            <div>
                                <div class="acc-lf-wrap">
                                    <input type="email" id="acc_magic_email" name="magic_email"
                                           placeholder=" " autocomplete="email"
                                           value="{{ old('magic_email') }}"
                                           pattern="[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}"
                                           class="acc-lf-input" required>
                                    <label class="acc-lf-label" for="acc_magic_email">Email address</label>
                                </div>
                                <div id="magic-email-status" style="font-size:.76rem;font-weight:600;min-height:14px;margin-top:5px"></div>
                            </div>
                            <button type="submit" id="magic-send-btn"
                                    style="background:{{ $primaryColor }};color:#fff;padding:13px;border:none;border-radius:8px;font-weight:600;font-size:.9rem;width:100%;cursor:pointer;transition:opacity .2s,transform .1s;opacity:.45"
                                    disabled
                                    onmouseover="if(!this.disabled){this.style.opacity='.88';this.style.transform='translateY(-1px)'}"
                                    onmouseout="this.style.opacity=this.disabled?'.45':'1';this.style.transform='none'">
                                Send Magic Link
                            </button>
                        </div>
                    </form>
                </div>
            @endif
@else
            {{-- ── STANDARD PASSWORD LOGIN ── --}}
            @if($errors->has('account_email'))
                <div class="bg-rose-50 border border-rose-200 text-rose-700 text-sm px-4 py-3 rounded-lg mb-5 flex items-center gap-2">
                    <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
                    {{ $errors->first('account_email') }}
                </div>
            @endif

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
                <form action="{{ route('shop.account.login') }}" method="POST">
                    @csrf
                    <input type="hidden" name="redirect_to" value="{{ url()->current() }}">
                    <div style="display:flex;flex-direction:column;gap:1.1rem">
                        <div class="acc-lf-wrap">
                            <input type="email" id="acc_email" name="account_email"
                                   placeholder=" " autocomplete="email"
                                   value="{{ old('account_email') }}"
                                   class="acc-lf-input {{ $errors->has('account_email') ? 'border-rose-400' : '' }}" required>
                            <label class="acc-lf-label" for="acc_email">Email address</label>
                        </div>
                        <div>
                            <div class="acc-lf-wrap">
                                <input type="password" id="acc_password" name="account_password"
                                       placeholder=" " autocomplete="current-password"
                                       class="acc-lf-input has-icon" required>
                                <label class="acc-lf-label" for="acc_password">Password</label>
                                <button type="button" class="acc-toggle-pwd" data-target="acc_password" tabindex="-1">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            </div>
                            <div style="text-align:right;margin-top:.35rem">
                                <a href="{{ route('admin.password.request') }}" style="font-size:.78rem;font-weight:600;text-decoration:none;color:{{ $primaryColor }}">Forgot password?</a>
                            </div>
                        </div>
                        <label style="display:flex;align-items:center;gap:.5rem;font-size:.875rem;color:#374151;cursor:pointer">
                            <input type="checkbox" name="remember" value="1" style="width:15px;height:15px;accent-color:{{ $primaryColor }};border-radius:3px">
                            Keep me signed in
                        </label>
                        <button type="submit"
                                style="background:{{ $primaryColor }};color:#fff;padding:13px;border:none;border-radius:8px;font-weight:600;font-size:.9rem;width:100%;cursor:pointer;transition:opacity .2s,transform .1s"
                                onmouseover="this.style.opacity='.88';this.style.transform='translateY(-1px)'"
                                onmouseout="this.style.opacity='1';this.style.transform='none'">
                            Sign In
                        </button>
                    </div>
                </form>
            </div>
@endif

            <p class="text-center text-sm text-body mt-6">
                Don't have an account?
                <a href="{{ route('shop.checkout') }}" style="color:{{ $primaryColor }};font-weight:600;text-decoration:none">Create one at checkout</a>
            </p>

        </div>

@endauth

    </div>
</div>

<script>
(function () {
    // Floating labels
    document.querySelectorAll('.acc-lf-wrap').forEach(function (wrap) {
        var inp = wrap.querySelector('.acc-lf-input');
        if (!inp) return;
        function update() { wrap.classList.toggle('lf-filled', inp.value.trim() !== ''); }
        inp.addEventListener('focus', function () { wrap.classList.add('lf-focused'); });
        inp.addEventListener('blur',  function () { wrap.classList.remove('lf-focused'); update(); });
        inp.addEventListener('input', update);
        update();
    });
    // Password toggle
    document.querySelectorAll('.acc-toggle-pwd').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var inp = document.getElementById(this.dataset.target);
            inp.type = inp.type === 'password' ? 'text' : 'password';
            this.style.color = inp.type === 'text' ? '{{ $primaryColor }}' : '#9ca3af';
        });
    });

@if(get_cms_option('magic_login_enabled') && !session('magic_sent'))
    // Real-time email check for magic login form
    (function() {
        var emailInp  = document.getElementById('acc_magic_email');
        var statusEl  = document.getElementById('magic-email-status');
        var sendBtn   = document.getElementById('magic-send-btn');
        if (!emailInp) return;

        var timer = null;
        var csrfToken = '{{ csrf_token() }}';

        function isValidEmail(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v.trim()); }

        function setBtn(enabled) {
            sendBtn.disabled = !enabled;
            sendBtn.style.opacity = enabled ? '1' : '.45';
            sendBtn.style.cursor  = enabled ? 'pointer' : 'not-allowed';
        }

        function checkEmail(email) {
            statusEl.textContent = 'Checking...';
            statusEl.style.color = '#9ca3af';
            setBtn(false);

            fetch('{{ route("shop.magic.email.check") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ email: email })
            })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.exists) {
                    statusEl.textContent = '✓ Account found';
                    statusEl.style.color = '#16a34a';
                    setBtn(true);
                } else {
                    statusEl.textContent = '✗ No account found with this email';
                    statusEl.style.color = '#dc2626';
                    setBtn(false);
                }
            })
            .catch(function() { statusEl.textContent = ''; setBtn(true); });
        }

        emailInp.addEventListener('input', function() {
            clearTimeout(timer);
            var val = this.value.trim();
            statusEl.textContent = '';
            setBtn(false);
            if (!val) return;
            if (!isValidEmail(val)) {
                statusEl.textContent = 'Please enter a valid email address';
                statusEl.style.color = '#dc2626';
                return;
            }
            timer = setTimeout(function() { checkEmail(val); }, 400);
        });

        // Trigger on load if old value present
        if (emailInp.value.trim() && isValidEmail(emailInp.value.trim())) {
            checkEmail(emailInp.value.trim());
        }
    })();
@endif
})();
</script>
@stop
