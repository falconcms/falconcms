<x-falcon-cms::layouts.admin title="Dashboard">
    <style>
        .classic-card {
            background: #fff;
            border: 1px solid #c3c4c7;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            margin-bottom: 20px;
        }
        .classic-card-header {
            padding: 10px 15px;
            border-bottom: 1px solid #f0f0f1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .classic-card-title {
            font-size: 14px;
            font-weight: 600;
            color: #1d2327;
        }
        .classic-stat-box {
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .classic-stat-icon {
            width: 45px;
            height: 45px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }
        .classic-stat-value {
            font-size: 21px;
            font-weight: 700;
            color: #1d2327;
            line-height: 1.2;
        }
        .classic-stat-label {
            font-size: 13px;
            color: #646970;
            font-weight: 500;
        }
        /* The admin applies icon sizing to every <svg> (e.g. `svg,.material-symbols-outlined{max-height:349px}`),
           which shrinks the map. Force the map SVG to fill its container and drop those caps. */
        #world-map svg {
            width: 100% !important;
            height: 360px !important;
            min-height: 360px !important;
            max-height: none !important;
            max-width: none !important;
            min-width: 0 !important;
            display: block !important;
        }
        #world-map svg path { transition: fill .15s; }
        #world-map svg path:hover { fill: #16a34a !important; }
    </style>

    <div class="p-4 sm:p-6 bg-[#f0f0f1] min-h-screen">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-[23px] font-normal text-[#1d2327]">Dashboard</h1>
            <nav class="text-[13px] text-[#646970]">
                Home / Dashboard
            </nav>
        </div>

        @php
            $cmsNow  = \Illuminate\Support\Carbon::now(function_exists('cms_timezone') ? cms_timezone() : config('app.timezone'));
            $cmsHour = (int) $cmsNow->format('G');
            $cmsGreet = $cmsHour < 12 ? 'Good morning' : ($cmsHour < 17 ? 'Good afternoon' : ($cmsHour < 21 ? 'Good evening' : 'Good night'));
            $cmsName  = auth()->user()->name ?? 'there';
        @endphp
        <!-- Greeting -->
        <div class="mb-6 rounded-lg px-6 py-5 flex flex-wrap items-center justify-between gap-3"
             style="background:linear-gradient(135deg,#2271b1 0%,#135e96 100%);color:#fff;box-shadow:0 4px 14px rgba(34,113,177,.25)">
            <div>
                <h1 class="text-[22px] font-semibold m-0">{{ $cmsGreet }}, {{ $cmsName }} 👋</h1>
                <p class="text-[13px] m-0 mt-1" style="color:#cfe6f7">Welcome back to your dashboard — here's what's happening today.</p>
            </div>
            <div class="text-right">
                <div class="text-[18px] font-semibold leading-tight">{{ $cmsNow->format('g:i A') }}</div>
                <div class="text-[12px]" style="color:#cfe6f7">{{ $cmsNow->format('l, M j, Y') }}</div>
            </div>
        </div>

        @if(auth()->user()->hasPermission('access_dashboard'))
        <!-- Info Boxes -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
            <div class="classic-card">
                <div class="classic-stat-box">
                    <div class="classic-stat-icon bg-[#2271b1]">
                        <span class="material-symbols-outlined text-[24px]">article</span>
                    </div>
                    <div>
                        <div class="classic-stat-value">{{ $stats['total_posts']['count'] }}</div>
                        <div class="classic-stat-label">Total Posts</div>
                    </div>
                </div>
            </div>
            <div class="classic-card">
                <div class="classic-stat-box">
                    <div class="classic-stat-icon bg-[#46b450]">
                        <span class="material-symbols-outlined text-[24px]">description</span>
                    </div>
                    <div>
                        <div class="classic-stat-value">{{ $stats['total_pages']['count'] }}</div>
                        <div class="classic-stat-label">Total Pages</div>
                    </div>
                </div>
            </div>
            <div class="classic-card">
                <div class="classic-stat-box">
                    <div class="classic-stat-icon bg-[#d63638]">
                        <span class="material-symbols-outlined text-[24px]">group</span>
                    </div>
                    <div>
                        <div class="classic-stat-value">{{ $stats['total_users']['count'] }}</div>
                        <div class="classic-stat-label">Total Users</div>
                    </div>
                </div>
            </div>
            <div class="classic-card">
                <div class="classic-stat-box">
                    <div class="classic-stat-icon bg-[#dba617]">
                        <span class="material-symbols-outlined text-[24px]">block</span>
                    </div>
                    <div>
                        <div class="classic-stat-value">{{ $stats['blacklisted_ips']['count'] }}</div>
                        <div class="classic-stat-label">Blocked IPs</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Middle Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Chart -->
            <div class="lg:col-span-2">
                <div class="classic-card">
                    <div class="classic-card-header">
                        <span class="classic-card-title">Activity Overview</span>
                        <span class="text-[12px] text-[#646970]">Last 7 Months</span>
                    </div>
                    <div class="p-4">
                        <div class="h-[300px]">
                            <canvas id="impressionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- At a Glance / Right Sidebar -->
            <div class="lg:col-span-1">
                <div class="classic-card">
                    <div class="classic-card-header">
                        <span class="classic-card-title">At a Glance</span>
                    </div>
                    <div class="p-4 space-y-4">
                        <div class="flex items-center justify-between border-b border-[#f0f0f1] pb-3">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-[#2271b1] text-[20px]">movie</span>
                                <span class="text-[13px] font-medium">Media Assets</span>
                            </div>
                            <span class="font-bold text-[#1d2327]">{{ $stats['media_count']['count'] }}</span>
                        </div>
                        <div class="flex items-center justify-between border-b border-[#f0f0f1] pb-3">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-[#d63638] text-[20px]">person_off</span>
                                <span class="text-[13px] font-medium">Blocked Accounts</span>
                            </div>
                            <span class="font-bold text-[#d63638]">{{ $stats['blocked_users']['count'] }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-[#46b450] text-[20px]">trending_up</span>
                                <span class="text-[13px] font-medium">Conversion Rate</span>
                            </div>
                            <span class="font-bold text-[#46b450]">{{ $stats['traffic_stats']['conversion_rate']['value'] }}</span>
                        </div>
                    </div>
                    <div class="bg-[#f6f7f7] p-3 text-center border-t border-[#c3c4c7]">
                        <a href="{{ route('admin.posts.index') }}" class="text-[#2271b1] text-[12px] font-semibold hover:underline">View All Posts</a>
                    </div>
                </div>

                <!-- Security Status Box -->
                <div class="classic-card">
                    <div class="classic-card-header">
                        <span class="classic-card-title">Security Status</span>
                        @php $sec = $stats['traffic_stats']['security'] ?? ['status' => 'Healthy', 'message' => 'System protection is active.']; @endphp
                        <span class="px-2 py-0.5 {{ $sec['status'] === 'Healthy' ? 'bg-[#46b450]' : 'bg-[#d63638]' }} text-white text-[10px] rounded font-bold uppercase">
                            {{ $sec['status'] }}
                        </span>
                    </div>
                    <div class="p-4">
                        <p class="text-[13px] text-[#646970] leading-relaxed">
                            {{ $sec['message'] }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        @if($hasShop)
        <!-- Ecommerce KPI Cards -->
        @php
            $kpis = [
                [
                    'label' => 'Total Revenue', 'icon' => 'payments',
                    'value' => $currency . number_format($ecoStats['total_revenue'], 2),
                    'color' => '#2a8a3e', 'accent' => '#46b450', 'tint' => '#eafaef',
                    'delta' => $ecoStats['revenue_delta'],
                    'sub'   => $currency . number_format($ecoStats['revenue_this_month'] ?? 0, 0) . ' this month',
                ],
                [
                    'label' => 'Total Orders', 'icon' => 'shopping_bag',
                    'value' => number_format($ecoStats['total_orders']),
                    'color' => '#1f6fb2', 'accent' => '#2271b1', 'tint' => '#eef4fb',
                    'delta' => $ecoStats['orders_delta'],
                    'sub'   => $ecoStats['orders_month'] . ' this month',
                ],
                [
                    'label' => 'Pending Orders', 'icon' => 'pending_actions',
                    'value' => number_format($ecoStats['pending_orders']),
                    'color' => '#b8860b', 'accent' => '#dba617', 'tint' => '#fef9ee',
                    'delta' => null,
                    'sub'   => $ecoStats['pending_orders'] > 0 ? 'Awaiting processing' : 'All caught up',
                ],
                [
                    'label' => 'Total Products', 'icon' => 'inventory_2',
                    'value' => number_format($ecoStats['total_products']),
                    'color' => '#7c3aed', 'accent' => '#8c44db', 'tint' => '#f5eefb',
                    'delta' => null,
                    'sub'   => ($ecoStats['low_stock_count'] ?? 0) > 0
                                ? $ecoStats['low_stock_count'] . ' low on stock' : 'In catalog',
                ],
            ];
        @endphp
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-6 mt-6">
            @foreach($kpis as $k)
            <div class="bg-white rounded-lg border border-[#e2e4e7] overflow-hidden transition-all hover:-translate-y-0.5 hover:shadow-md" style="box-shadow:0 1px 2px rgba(0,0,0,.05)">
                <div style="height:3px;background:{{ $k['accent'] }}"></div>
                <div class="p-4">
                    <div class="flex items-start justify-between">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" style="background:{{ $k['tint'] }}">
                            <span class="material-symbols-outlined text-[22px]" style="color:{{ $k['color'] }}">{{ $k['icon'] }}</span>
                        </div>
                        @if(!is_null($k['delta']))
                            @php $up = $k['delta'] >= 0; $mag = abs($k['delta']); @endphp
                            <span class="inline-flex items-center gap-0.5 text-[11px] font-bold px-1.5 py-0.5 rounded"
                                  style="color:{{ $up ? '#1a7f37' : '#d63638' }};background:{{ $up ? '#eafaef' : '#fdecec' }}"
                                  title="vs last month">
                                {{ $up ? '↑' : '↓' }} {{ $mag > 999 ? '999+' : $mag }}%
                            </span>
                        @endif
                    </div>
                    <div class="text-[24px] font-bold text-[#1d2327] leading-tight mt-3">{{ $k['value'] }}</div>
                    <div class="text-[12px] text-[#646970] font-medium mt-0.5">{{ $k['label'] }}</div>
                    <div class="text-[11px] text-[#8c8f94] mt-1">{{ $k['sub'] }}</div>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Ecommerce Section: Revenue Chart + Recent Orders -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Revenue Bar Chart -->
            <div class="lg:col-span-2">
                <div class="classic-card">
                    <div class="classic-card-header">
                        <span class="classic-card-title">Revenue Overview</span>
                        <span class="text-[12px] text-[#646970]">Last 7 Months</span>
                    </div>
                    <div class="p-4">
                        <div class="h-[260px]">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders -->
                <div class="classic-card" style="margin-bottom:0">
                    <div class="classic-card-header">
                        <span class="classic-card-title">Recent Orders</span>
                        <a href="{{ route('admin.shop.orders.index') }}" class="text-[12px] text-[#2271b1] hover:underline">View all →</a>
                    </div>
                    @php
                        $rStatusColors = [
                            'pending' => 'bg-[#fef9ee] text-[#b8860b]', 'processing' => 'bg-[#eef4fb] text-[#1f6fb2]',
                            'completed' => 'bg-[#edfaee] text-[#2a8a3e]', 'cancelled' => 'bg-[#fef0f0] text-[#d63638]',
                            'partially-refunded' => 'bg-[#f5eefb] text-[#7c3aed]', 'refunded' => 'bg-[#f5eefb] text-[#7c3aed]',
                            'on-hold' => 'bg-[#f6f7f7] text-[#646970]', 'failed' => 'bg-[#fef0f0] text-[#d63638]',
                        ];
                    @endphp
                    <div class="overflow-x-auto">
                        <table class="w-full text-[13px]">
                            <thead>
                                <tr class="bg-[#f6f7f7] text-left text-[#646970]">
                                    <th class="px-4 py-2 font-semibold">Order</th>
                                    <th class="px-4 py-2 font-semibold">Customer</th>
                                    <th class="px-4 py-2 font-semibold">Date</th>
                                    <th class="px-4 py-2 font-semibold">Status</th>
                                    <th class="px-4 py-2 font-semibold text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($ecoStats['recent_orders'] as $o)
                                <tr class="border-t border-[#f0f0f1] hover:bg-[#f6f7f7]">
                                    <td class="px-4 py-2.5">
                                        <a href="{{ route('admin.shop.orders.show', $o->id) }}" class="text-[#2271b1] font-bold hover:underline">#{{ $o->order_number ?: $o->id }}</a>
                                    </td>
                                    <td class="px-4 py-2.5 text-[#1d2327]">{{ trim($o->first_name . ' ' . $o->last_name) ?: '—' }}</td>
                                    <td class="px-4 py-2.5 text-[#646970] whitespace-nowrap">{{ cms_date($o->created_at, 'M d, Y') }}</td>
                                    <td class="px-4 py-2.5">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $rStatusColors[$o->status] ?? 'bg-gray-100 text-gray-700' }}">{{ str_replace('-', ' ', $o->status) }}</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-bold whitespace-nowrap">{{ falcon_price_format($o->total, $o) }}</td>
                                </tr>
                                @empty
                                <tr><td colspan="5" class="px-6 py-10 text-center text-[#646970] italic">No orders yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="lg:col-span-1">
                @php
                $statusMeta = [
                    'pending'    => ['label' => 'Pending',    'color' => '#dba617', 'bg' => '#fef9ee', 'icon' => 'schedule'],
                    'processing' => ['label' => 'Processing', 'color' => '#2271b1', 'bg' => '#eef4fb', 'icon' => 'autorenew'],
                    'completed'  => ['label' => 'Completed',  'color' => '#46b450', 'bg' => '#edfaee', 'icon' => 'check_circle'],
                    'cancelled'  => ['label' => 'Cancelled',  'color' => '#d63638', 'bg' => '#fef0f0', 'icon' => 'cancel'],
                    'partially-refunded' => ['label' => 'Partially Refunded', 'color' => '#8c44db', 'bg' => '#f5eefb', 'icon' => 'currency_exchange'],
                    'refunded'   => ['label' => 'Refunded',   'color' => '#8c44db', 'bg' => '#f5eefb', 'icon' => 'currency_exchange'],
                    'on-hold'    => ['label' => 'On Hold',    'color' => '#646970', 'bg' => '#f6f7f7', 'icon' => 'pause_circle'],
                    'failed'     => ['label' => 'Failed',     'color' => '#d63638', 'bg' => '#fef0f0', 'icon' => 'error'],
                ];
                @endphp

                <!-- Order Status Breakdown -->
                <div class="classic-card" style="margin-bottom:16px">
                    <div class="classic-card-header">
                        <span class="classic-card-title">Order Status</span>
                        <span class="text-[12px] text-[#646970]">All time</span>
                    </div>
                    <div class="p-3 space-y-2">
                        @php
                            // Show every status that has orders, and always show the refund states so
                            // admins can see Partial Refund / Refunded activity even when the count is 0.
                            $alwaysShow = ['partially-refunded', 'refunded'];
                            $displayStatuses = [];
                            foreach ($statusMeta as $sKey => $sMeta) {
                                $sCnt = $ecoStats['status_counts'][$sKey] ?? 0;
                                if ($sCnt > 0 || in_array($sKey, $alwaysShow, true)) {
                                    $displayStatuses[$sKey] = $sMeta;
                                }
                            }
                        @endphp
                        @forelse($displayStatuses as $key => $meta)
                        @php $cnt = $ecoStats['status_counts'][$key] ?? 0; $total = $ecoStats['total_orders'] ?: 1; $pct = round($cnt / $total * 100); @endphp
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <div class="flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[15px]" style="color:{{ $meta['color'] }}">{{ $meta['icon'] }}</span>
                                    <span class="text-[12px] font-medium text-[#1d2327]">{{ $meta['label'] }}</span>
                                </div>
                                <span class="text-[12px] font-bold text-[#1d2327]">{{ $cnt }}</span>
                            </div>
                            <div class="h-1.5 bg-[#f0f0f1] rounded-full overflow-hidden">
                                <div class="h-full rounded-full" style="width:{{ $pct }}%;background:{{ $meta['color'] }}"></div>
                            </div>
                        </div>
                        @empty
                        <div class="py-4 text-center text-[13px] text-[#646970]">No orders yet.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Top Selling Products -->
                <div class="classic-card" style="margin-bottom:16px">
                    <div class="classic-card-header">
                        <span class="classic-card-title">Top Selling Products</span>
                        <a href="{{ route('admin.shop.reports.index') }}" class="text-[12px] text-[#2271b1] hover:underline">Reports →</a>
                    </div>
                    <div class="p-3 space-y-2">
                        @forelse($ecoStats['top_products'] as $i => $tp)
                        <div class="flex items-center justify-between gap-2 {{ !$loop->first ? 'border-t border-[#f0f0f1] pt-2' : '' }}">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="flex-shrink-0 w-5 h-5 rounded-full bg-[#eef4fb] text-[#2271b1] text-[11px] font-bold flex items-center justify-center">{{ $i + 1 }}</span>
                                <a href="{{ route('admin.posts.edit', $tp->product_id) }}" class="text-[12px] font-medium text-[#1d2327] hover:text-[#2271b1] truncate">{{ $tp->product_name }}</a>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <div class="text-[12px] font-bold text-[#1d2327]">{{ $currency }}{{ number_format((float) $tp->revenue, 0) }}</div>
                                <div class="text-[10px] text-[#646970]">{{ (int) $tp->qty }} sold</div>
                            </div>
                        </div>
                        @empty
                        <div class="py-5 text-center text-[13px] text-[#646970]">No sales yet — best sellers will appear here.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Low Stock Alerts -->
                <div class="classic-card" style="margin-bottom:0">
                    <div class="classic-card-header">
                        <span class="classic-card-title">Low Stock</span>
                        <span class="text-[12px] text-[#646970]">≤ 5 left</span>
                    </div>
                    <div class="p-3 space-y-2">
                        @forelse($ecoStats['low_stock'] as $lp)
                        @php $qty = (int) ($lp->shopData->stock_quantity ?? 0); @endphp
                        <div class="flex items-center justify-between gap-2 {{ !$loop->first ? 'border-t border-[#f0f0f1] pt-2' : '' }}">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="material-symbols-outlined text-[16px] flex-shrink-0 {{ $qty <= 0 ? 'text-[#d63638]' : 'text-[#dba617]' }}">{{ $qty <= 0 ? 'error' : 'warning' }}</span>
                                <a href="{{ route('admin.posts.edit', $lp->id) }}" class="text-[12px] font-medium text-[#1d2327] hover:text-[#2271b1] truncate">{{ $lp->title }}</a>
                            </div>
                            <span class="flex-shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full {{ $qty <= 0 ? 'bg-[#fef0f0] text-[#d63638]' : 'bg-[#fef9ee] text-[#dba617]' }}">
                                {{ $qty <= 0 ? 'Out of stock' : $qty . ' left' }}
                            </span>
                        </div>
                        @empty
                        <div class="py-5 text-center text-[13px] text-[#646970]">All products are well stocked.</div>
                        @endforelse
                    </div>
                </div>
            </div>

        </div>

        {{-- Orders by Country — dynamic world map (highlights countries orders came from) --}}
        <div class="classic-card mt-6">
            <div class="classic-card-header">
                <span class="classic-card-title">Orders by Country</span>
                <span class="text-[12px] text-[#646970]">All time</span>
            </div>
            <div class="p-4 grid grid-cols-1 lg:grid-cols-3 gap-5">
                <div class="lg:col-span-2">
                    <div id="world-map" style="height:360px;width:100%;min-height:360px;position:relative"></div>
                </div>
                <div>
                    <div class="text-[12px] font-semibold text-[#646970] uppercase tracking-wide mb-3">Top countries</div>
                    <div class="space-y-2.5">
                        @forelse($ecoStats['orders_by_country']->take(8) as $c)
                        <div class="flex items-center justify-between gap-2 {{ !$loop->first ? 'border-t border-[#f0f0f1] pt-2.5' : '' }}">
                            <div class="flex items-center gap-2 min-w-0">
                                <img src="https://flagcdn.com/20x15/{{ strtolower($c['code']) }}.png" width="20" height="15" style="border-radius:2px;flex-shrink:0" alt="{{ $c['code'] }}" onerror="this.style.display='none'">
                                <span class="text-[13px] font-medium text-[#1d2327] truncate">{{ $c['name'] }}</span>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <div class="text-[13px] font-bold text-[#1d2327]">{{ $c['orders'] }} <span class="text-[10px] font-normal text-[#646970]">orders</span></div>
                                @if($c['revenue'] > 0)<div class="text-[10px] text-[#46b450] font-semibold">{{ $currency }}{{ number_format($c['revenue'], 0) }}</div>@endif
                            </div>
                        </div>
                        @empty
                        <div class="py-6 text-center text-[13px] text-[#646970]">No orders with a country yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
        @endif

        @else
        {{-- User does not have dashboard access --}}
        <div class="bg-white border border-[#c3c4c7] rounded p-6 text-[14px] text-[#646970]">
            You do not have permission to view the dashboard overview.
        </div>
        @endif

    </div>

    @push('scripts')
    @if(auth()->user()->hasPermission('access_dashboard'))
    <script src="{{ asset('vendor/falcon-cms/js/chart.min.js') }}"></script>
    <script>
        const ctx = document.getElementById('impressionChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: {!! json_encode($stats['traffic_stats']['labels']) !!},
                datasets: [{
                    label: 'Impressions',
                    data: {!! json_encode($stats['traffic_stats']['impressions']) !!},
                    borderColor: '#2271b1',
                    backgroundColor: 'rgba(34, 113, 177, 0.05)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2,
                    pointRadius: 4,
                    pointBackgroundColor: '#2271b1'
                }, {
                    label: 'Visitors',
                    data: {!! json_encode($stats['traffic_stats']['visitors']) !!},
                    borderColor: '#c3c4c7',
                    borderDash: [5, 5],
                    fill: false,
                    tension: 0.4,
                    borderWidth: 2,
                    pointRadius: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, font: { size: 11 } }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f0f0f1' },
                        ticks: { font: { size: 10 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10 } }
                    }
                }
            }
        });
    </script>
    @if($hasShop)
    <script>
        const rCtx = document.getElementById('revenueChart').getContext('2d');
        const revData = {!! json_encode(array_map('floatval', $ecoStats['monthly_revenue'])) !!};
        const revLabels = {!! json_encode(!empty($ecoStats['monthly_labels']) ? $ecoStats['monthly_labels'] : ($stats['traffic_stats']['labels'] ?? [])) !!};
        const revFmt = v => '{{ $currency }}' + Number(v).toLocaleString(undefined, { maximumFractionDigits: 0 });

        // Draws each month's value above its bar so even tiny months (e.g. a single small order) are visible.
        const revValueLabels = {
            id: 'revValueLabels',
            afterDatasetsDraw(chart) {
                const { ctx } = chart;
                const meta = chart.getDatasetMeta(0);
                ctx.save();
                ctx.font = '600 10px sans-serif';
                ctx.fillStyle = '#3c434a';
                ctx.textAlign = 'center';
                meta.data.forEach((bar, i) => {
                    const val = revData[i] || 0;
                    if (val <= 0) return;
                    ctx.fillText(revFmt(val), bar.x, bar.y - 6);
                });
                ctx.restore();
            }
        };

        new Chart(rCtx, {
            type: 'bar',
            data: {
                labels: revLabels,
                datasets: [{
                    label: 'Revenue ({{ $currency }})',
                    data: revData,
                    backgroundColor: 'rgba(70, 180, 80, 0.65)',
                    borderColor: '#46b450',
                    borderWidth: 1.5,
                    borderRadius: 4,
                    // No minBarLength: months with no orders stay empty; the value label still shows small months.
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 18 } },
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                    tooltip: { callbacks: { label: c => ' ' + revFmt(c.parsed.y) } }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f0f0f1' },
                        ticks: { font: { size: 10 }, callback: v => '{{ $currency }}' + v }
                    },
                    x: { grid: { display: false }, ticks: { font: { size: 10 } } }
                }
            },
            plugins: [revValueLabels]
        });
    </script>
    <script>
        // Orders by Country — builds a plain inline SVG from jsVectorMap's map DATA (no library
        // rendering, so none of its sizing quirks apply; the browser scales the viewBox natively).
        (function () {
            const el  = document.getElementById('world-map');
            const dbg = document.getElementById('world-map-debug');
            const setDbg = t => { if (dbg) dbg.textContent = t; };
            if (!el) return;
            const data = @json($ecoStats['orders_by_country']->keyBy('code'));
            const codes = Object.keys(data);
            if (!codes.length) { el.innerHTML = '<div style="height:100%;display:flex;align-items:center;justify-content:center;color:#646970;font-size:13px">No order locations yet.</div>'; return; }
            const counts = {}; codes.forEach(c => { counts[c.toUpperCase()] = data[c].orders; });
            const max = Math.max(1, ...codes.map(c => data[c].orders));
            const shade = n => '#' + 'bbf7d0'.match(/\w\w/g).map((h, i) => {
                const t = max > 1 ? n / max : 1;
                const v = Math.round(parseInt(h, 16) + (parseInt('15803d'.match(/\w\w/g)[i], 16) - parseInt(h, 16)) * t);
                return v.toString(16).padStart(2, '0');
            }).join('');
            const esc = s => (s == null ? '' : String(s)).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

            setDbg('');
            const js = s => new Promise((res, rej) => { const x = document.createElement('script'); x.src = s; x.onload = res; x.onerror = () => rej(new Error('load fail')); document.head.appendChild(x); });
            const base = 'https://cdn.jsdelivr.net/npm/jsvectormap@1.5.3/dist/';

            let mapData = null;
            js(base + 'js/jsvectormap.min.js')
                .then(() => {
                    // world.js registers via jsVectorMap.addMap(name, data); intercept it to grab the data.
                    if (window.jsVectorMap && typeof jsVectorMap.addMap === 'function') {
                        const orig = jsVectorMap.addMap;
                        jsVectorMap.addMap = function (name, obj) { if (obj && obj.paths) mapData = obj; return orig.apply(this, arguments); };
                    }
                    return js(base + 'maps/world.js');
                })
                .then(() => {
                    const md = mapData;
                    if (!md || !md.paths) { setDbg('map data unavailable'); return; }
                    const inset = (md.insets && md.insets[0]) || { width: 900, height: 440 };
                    const W = Math.ceil(inset.width), H = Math.ceil(inset.height);
                    let paths = '';
                    for (const code in md.paths) {
                        const p = md.paths[code];
                        const has = counts[code] != null;
                        const name = (data[code] && data[code].name) || p.name || code;
                        const title = has ? name + ' (' + counts[code] + ' order' + (counts[code] === 1 ? '' : 's') + ')' : name;
                        paths += '<path d="' + p.path + '" fill="' + (has ? shade(counts[code]) : '#cbd5e1') + '" stroke="#ffffff" stroke-width="0.5">'
                              +  '<title>' + esc(title) + '</title></path>';
                    }
                    // Inline !important defeats the admin's global icon sizing on <svg> (max-height:349px, etc.).
                    const sStyle = 'display:block;width:100%!important;height:360px!important;min-height:360px!important;max-height:none!important;max-width:none!important;min-width:0!important;cursor:grab';
                    const bStyle = 'width:26px;height:26px;line-height:1;text-align:center;font-size:16px;font-weight:700;color:#1d2327;background:#fff;border:1px solid #c3c4c7;border-radius:4px;cursor:pointer;box-shadow:0 1px 2px rgba(0,0,0,.08);padding:0';
                    el.innerHTML =
                        '<svg id="wm-svg" viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="xMidYMid meet" style="' + sStyle + '">' + paths + '</svg>' +
                        '<div style="position:absolute;top:8px;left:8px;display:flex;flex-direction:column;gap:5px;z-index:5">' +
                        '<button type="button" id="wm-zin" title="Zoom in" style="' + bStyle + '">+</button>' +
                        '<button type="button" id="wm-zout" title="Zoom out" style="' + bStyle + '">&minus;</button></div>';

                    // Zoom & pan by manipulating the SVG viewBox (buttons + drag-to-pan).
                    const svg = el.querySelector('#wm-svg');
                    const m0 = { x: 0, y: 0, w: W, h: H };
                    const vb = { x: 0, y: 0, w: W, h: H };
                    const apply = () => svg.setAttribute('viewBox', vb.x + ' ' + vb.y + ' ' + vb.w + ' ' + vb.h);
                    const clamp = () => {
                        vb.w = Math.min(vb.w, m0.w); vb.h = Math.min(vb.h, m0.h);
                        vb.x = Math.max(m0.x, Math.min(vb.x, m0.x + m0.w - vb.w));
                        vb.y = Math.max(m0.y, Math.min(vb.y, m0.y + m0.h - vb.h));
                    };
                    const zoomAt = (f, cx, cy) => {
                        let nw = vb.w * f, nh = vb.h * f;
                        if (nw > m0.w) { nw = m0.w; nh = m0.h; }
                        if (nw < m0.w * 0.12) return;
                        vb.x = cx - (cx - vb.x) * (nw / vb.w);
                        vb.y = cy - (cy - vb.y) * (nh / vb.h);
                        vb.w = nw; vb.h = nh; clamp(); apply();
                    };
                    const ctr = () => ({ x: vb.x + vb.w / 2, y: vb.y + vb.h / 2 });
                    el.querySelector('#wm-zin').onclick = () => { const p = ctr(); zoomAt(0.65, p.x, p.y); };
                    el.querySelector('#wm-zout').onclick = () => { const p = ctr(); zoomAt(1.55, p.x, p.y); };
                    let drag = false, last = null;
                    svg.addEventListener('mousedown', e => { drag = true; last = { x: e.clientX, y: e.clientY }; svg.style.cursor = 'grabbing'; e.preventDefault(); });
                    window.addEventListener('mouseup', () => { if (drag) { drag = false; svg.style.cursor = 'grab'; } });
                    window.addEventListener('mousemove', e => {
                        if (!drag) return;
                        const r = svg.getBoundingClientRect();
                        vb.x -= (e.clientX - last.x) / r.width * vb.w;
                        vb.y -= (e.clientY - last.y) / r.height * vb.h;
                        last = { x: e.clientX, y: e.clientY }; clamp(); apply();
                    });
                    // Mouse-wheel zoom, centred on the cursor.
                    svg.addEventListener('wheel', e => {
                        e.preventDefault();
                        const r = svg.getBoundingClientRect();
                        const cx = vb.x + (e.clientX - r.left) / r.width * vb.w;
                        const cy = vb.y + (e.clientY - r.top) / r.height * vb.h;
                        zoomAt(e.deltaY < 0 ? 0.85 : 1.18, cx, cy);
                    }, { passive: false });
                })
                .catch(() => {
                    setDbg('');
                    el.innerHTML = '<div style="height:100%;display:flex;align-items:center;justify-content:center;color:#646970;font-size:13px">Map unavailable — see the country list.</div>';
                });
        })();
    </script>
    @endif
    @endif {{-- access_dashboard permission --}}
    @endpush
</x-falcon-cms::layouts.admin>
