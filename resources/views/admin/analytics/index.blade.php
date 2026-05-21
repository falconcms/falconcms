<x-cms-dashboard::layouts.admin>
    <x-slot name="title">Analytics Dashboard - Lazy CMS</x-slot>

    <div class="px-2">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-[23px] font-normal text-[#1d2327]">Analytics Dashboard</h1>
                <p class="text-[13px] text-gray-500">Real-time traffic overview for the last 30 days.</p>
            </div>
            <div class="flex gap-2">
                <span class="bg-blue-50 text-blue-700 px-3 py-1 rounded-full text-xs font-bold border border-blue-100">Live Tracking Active</span>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Total Visits</p>
                <h3 class="text-2xl font-black text-gray-900">{{ $dailyVisits->sum('count') }}</h3>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Unique Pages</p>
                <h3 class="text-2xl font-black text-gray-900">{{ $topPages->count() }}</h3>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Top Browser</p>
                <h3 class="text-2xl font-black text-gray-900">{{ $browsers->sortByDesc('count')->first()->browser ?? 'N/A' }}</h3>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Mobile Users</p>
                <h3 class="text-2xl font-black text-gray-900">
                    {{ round(($devices->where('device_type', 'mobile')->first()->count ?? 0) / max($dailyVisits->sum('count'), 1) * 100) }}%
                </h3>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Chart -->
            <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <h4 class="text-sm font-bold text-gray-800 mb-6 flex items-center gap-2">
                    <span class="w-2 h-2 bg-blue-600 rounded-full"></span>
                    Traffic Overview (Daily)
                </h4>
                <canvas id="trafficChart" height="120"></canvas>
            </div>

            <!-- Top Pages -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <h4 class="text-sm font-bold text-gray-800 mb-6">Top 10 Visited Pages</h4>
                <div class="space-y-4">
                    @foreach($topPages as $page)
                        <div class="flex items-center justify-between group">
                            <div class="flex flex-col min-w-0">
                                <span class="text-xs font-medium text-gray-800 truncate">{{ str_replace(url('/'), '', $page->url) ?: '/' }}</span>
                                <div class="w-full bg-gray-50 h-1 mt-1 rounded-full overflow-hidden">
                                    <div class="bg-blue-600 h-full transition-all duration-500" style="width: {{ ($page->count / $topPages->first()->count) * 100 }}%"></div>
                                </div>
                            </div>
                            <span class="text-xs font-bold text-gray-400 ml-4">{{ $page->count }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
            <!-- Browser Stats -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <h4 class="text-sm font-bold text-gray-800 mb-6">Browsers</h4>
                <div class="flex items-center gap-8">
                    <div class="w-1/2">
                        <canvas id="browserChart"></canvas>
                    </div>
                    <div class="w-1/2 space-y-2">
                        @foreach($browsers as $browser)
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-gray-500">{{ $browser->browser }}</span>
                                <span class="font-bold text-gray-800">{{ $browser->count }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Device Stats -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <h4 class="text-sm font-bold text-gray-800 mb-6">Device Types</h4>
                <div class="flex items-center gap-8">
                    <div class="w-1/2">
                        <canvas id="deviceChart"></canvas>
                    </div>
                    <div class="w-1/2 space-y-2">
                        @foreach($devices as $device)
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-gray-500 capitalize">{{ $device->device_type }}</span>
                                <span class="font-bold text-gray-800">{{ $device->count }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="{{ asset('vendor/cms-dashboard/js/chart.min.js') }}"></script>
    <script>
        // Traffic Chart
        const trafficCtx = document.getElementById('trafficChart').getContext('2d');
        new Chart(trafficCtx, {
            type: 'line',
            data: {
                labels: {!! json_encode($dailyVisits->pluck('date')) !!},
                datasets: [{
                    label: 'Visits',
                    data: {!! json_encode($dailyVisits->pluck('count')) !!},
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.05)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 3,
                    pointRadius: 4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#2563eb',
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { display: false } },
                    x: { grid: { display: false } }
                }
            }
        });

        // Browser Chart
        const browserCtx = document.getElementById('browserChart').getContext('2d');
        new Chart(browserCtx, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode($browsers->pluck('browser')) !!},
                datasets: [{
                    data: {!! json_encode($browsers->pluck('count')) !!},
                    backgroundColor: ['#2563eb', '#7c3aed', '#db2777', '#ea580c', '#16a34a', '#4b5563'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                cutout: '70%'
            }
        });

        // Device Chart
        const deviceCtx = document.getElementById('deviceChart').getContext('2d');
        new Chart(deviceCtx, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode($devices->pluck('device_type')) !!},
                datasets: [{
                    data: {!! json_encode($devices->pluck('count')) !!},
                    backgroundColor: ['#10b981', '#f59e0b', '#3b82f6'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                cutout: '70%'
            }
        });
    </script>
    @endpush
</x-cms-dashboard::layouts.admin>
