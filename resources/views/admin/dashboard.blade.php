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
        <!-- Ecommerce Stat Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-6 mt-6">
            <div class="classic-card">
                <div class="classic-stat-box">
                    <div class="classic-stat-icon bg-[#46b450]">
                        <span class="material-symbols-outlined text-[24px]">payments</span>
                    </div>
                    <div>
                        <div class="classic-stat-value">{{ $currency }}{{ number_format($ecoStats['total_revenue'], 2) }}</div>
                        <div class="classic-stat-label">Total Revenue</div>
                    </div>
                </div>
            </div>
            <div class="classic-card">
                <div class="classic-stat-box">
                    <div class="classic-stat-icon bg-[#2271b1]">
                        <span class="material-symbols-outlined text-[24px]">shopping_bag</span>
                    </div>
                    <div>
                        <div class="classic-stat-value">{{ $ecoStats['total_orders'] }}</div>
                        <div class="classic-stat-label">Total Orders</div>
                    </div>
                </div>
            </div>
            <div class="classic-card">
                <div class="classic-stat-box">
                    <div class="classic-stat-icon bg-[#dba617]">
                        <span class="material-symbols-outlined text-[24px]">pending_actions</span>
                    </div>
                    <div>
                        <div class="classic-stat-value">{{ $ecoStats['pending_orders'] }}</div>
                        <div class="classic-stat-label">Pending Orders</div>
                    </div>
                </div>
            </div>
            <div class="classic-card">
                <div class="classic-stat-box">
                    <div class="classic-stat-icon bg-[#8c44db]">
                        <span class="material-symbols-outlined text-[24px]">inventory_2</span>
                    </div>
                    <div>
                        <div class="classic-stat-value">{{ $ecoStats['total_products'] }}</div>
                        <div class="classic-stat-label">Total Products</div>
                    </div>
                </div>
            </div>
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

                <!-- Today & This Month -->
                <div class="classic-card" style="margin-bottom:0">
                    <div class="classic-card-header">
                        <span class="classic-card-title">Quick Stats</span>
                    </div>
                    <div class="p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-[#2271b1] text-[18px]">today</span>
                                <span class="text-[13px] font-medium">Orders Today</span>
                            </div>
                            <span class="font-bold text-[#1d2327]">{{ $ecoStats['orders_today'] }}</span>
                        </div>
                        <div class="flex items-center justify-between border-t border-[#f0f0f1] pt-3">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-[#46b450] text-[18px]">calendar_month</span>
                                <span class="text-[13px] font-medium">Orders This Month</span>
                            </div>
                            <span class="font-bold text-[#1d2327]">{{ $ecoStats['orders_month'] }}</span>
                        </div>
                        <div class="flex items-center justify-between border-t border-[#f0f0f1] pt-3">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-[#dba617] text-[18px]">inventory_2</span>
                                <span class="text-[13px] font-medium">Total Products</span>
                            </div>
                            <span class="font-bold text-[#1d2327]">{{ $ecoStats['total_products'] }}</span>
                        </div>
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
    @endif
    @endif {{-- access_dashboard permission --}}
    @endpush
</x-falcon-cms::layouts.admin>
