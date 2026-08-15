<div
    class="flex w-full flex-col gap-6 p-6 lg:p-8"
    data-realtime-scope="admin-dashboard"
    data-realtime-url="{{ route('realtime.snapshot', ['scope' => 'admin-dashboard']) }}"
    data-realtime-interval="10000"
>
    <div class="dashboard-reveal">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('admin.dashboard')" wire:navigate>Super Admin</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Dashboard</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-x-6 gap-y-4">
            <div class="min-w-0">
                <flux:heading size="xl" level="1">Good day, {{ $firstName }} <span aria-hidden="true">👋</span></flux:heading>
                <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Here are the latest insights from your service operations.</flux:text>
            </div>

            <flux:badge color="emerald" size="lg">Live overview</flux:badge>
        </div>
    </div>

    <div class="dashboard-stagger grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <flux:card
                class="dashboard-reveal card-lift shadow-sm !border-s-4 {{ match ($loop->iteration) {
                    1 => '!border-s-violet-400',
                    2 => '!border-s-cyan-400',
                    3 => '!border-s-emerald-400',
                    default => '!border-s-amber-400',
                } }}"
            >
                <div class="flex items-center gap-4">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-lg {{ match ($loop->iteration) {
                        1 => 'bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400',
                        2 => 'bg-cyan-50 text-cyan-600 dark:bg-cyan-500/10 dark:text-cyan-400',
                        3 => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400',
                        default => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400',
                    } }}">
                        <flux:icon :name="$stat['icon']" class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <flux:text class="text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ $stat['label'] }}</flux:text>
                        <flux:heading class="mt-1 text-2xl tracking-tight">{{ $stat['value'] }}</flux:heading>
                    </div>
                </div>
            </flux:card>
        @endforeach
    </div>

    <div class="dashboard-stagger grid gap-4 lg:grid-cols-3">
        <flux:card class="dashboard-reveal card-lift flex flex-col shadow-sm lg:col-span-2" data-super-admin-booking-activity-layout="compact">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <flux:heading size="lg">Booking activity</flux:heading>
                    <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Seven-day view of active, completed, and cancelled jobs.</flux:text>
                </div>

                <div class="flex items-center gap-2">
                    <div class="flex flex-wrap gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                        <span class="inline-flex items-center gap-1.5"><i class="size-2 rounded-full bg-violet-400"></i>Active</span>
                        <span class="inline-flex items-center gap-1.5"><i class="size-2 rounded-full bg-emerald-400"></i>Completed</span>
                        <span class="inline-flex items-center gap-1.5"><i class="size-2 rounded-full bg-rose-400"></i>Cancelled</span>
                    </div>
                    <x-super-admin.section-menu :actions="[
                        ['label' => __('Refresh section'), 'icon' => 'arrow-path', 'wire' => '$refresh'],
                        ['label' => __('View bookings'), 'icon' => 'arrow-top-right-on-square', 'href' => route('admin.module', ['module' => 'service-bookings'])],
                    ]" />
                </div>
            </div>

            <div class="mt-5 flex flex-wrap items-end justify-between gap-4" data-super-admin-chart-summary>
                <div>
                    <div class="text-3xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ $bookingTrend['summary']['total'] }}</div>
                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Total bookings · last 7 days</div>
                </div>
                <div class="text-start sm:text-end">
                    <div class="text-sm font-semibold text-violet-600 dark:text-violet-300">{{ $bookingTrend['summary']['peakLabel'] }}</div>
                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Peak activity · {{ $bookingTrend['summary']['peakValue'] }} bookings</div>
                </div>
            </div>

            <div class="relative mt-4 min-h-[12rem] flex-1 overflow-hidden rounded-xl border border-zinc-200/80 bg-zinc-50/70 p-3 dark:border-white/10 dark:bg-white/[0.03]" data-super-admin-booking-activity-chart x-data="{ hoveredDay: null }">
                <div
                    x-cloak
                    x-show="hoveredDay"
                    x-transition
                    class="pointer-events-none absolute end-4 top-4 z-10 w-36 rounded-lg border border-zinc-200 bg-white/95 p-3 shadow-lg dark:border-white/10 dark:bg-zinc-800/95"
                    data-super-admin-booking-tooltip
                >
                    <div class="text-xs font-semibold text-zinc-900 dark:text-white" x-text="hoveredDay ? hoveredDay.label : ''"></div>
                    <div class="mt-2 space-y-1 text-[11px]">
                        <div class="flex items-center justify-between gap-3"><span class="text-violet-600 dark:text-violet-300">Active</span><span class="font-medium tabular-nums text-zinc-700 dark:text-zinc-200" x-text="hoveredDay ? hoveredDay.active : 0"></span></div>
                        <div class="flex items-center justify-between gap-3"><span class="text-emerald-600 dark:text-emerald-300">Completed</span><span class="font-medium tabular-nums text-zinc-700 dark:text-zinc-200" x-text="hoveredDay ? hoveredDay.completed : 0"></span></div>
                        <div class="flex items-center justify-between gap-3"><span class="text-rose-600 dark:text-rose-300">Cancelled</span><span class="font-medium tabular-nums text-zinc-700 dark:text-zinc-200" x-text="hoveredDay ? hoveredDay.cancelled : 0"></span></div>
                    </div>
                </div>

                <svg viewBox="0 0 700 220" class="h-full min-h-[12rem] w-full" role="img" aria-label="Seven-day booking activity line chart" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="super-admin-active-area" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#8b5cf6" stop-opacity="0.24" />
                            <stop offset="100%" stop-color="#8b5cf6" stop-opacity="0" />
                        </linearGradient>
                    </defs>

                    <g data-super-admin-chart-grid>
                        <line x1="42" y1="180" x2="660" y2="180" class="stroke-zinc-200 dark:stroke-white/10" />
                        <line x1="42" y1="108" x2="660" y2="108" class="stroke-zinc-200/70 dark:stroke-white/[0.06]" />
                        <line x1="42" y1="36" x2="660" y2="36" class="stroke-zinc-200/70 dark:stroke-white/[0.06]" />
                        <text x="26" y="184" text-anchor="end" class="fill-zinc-400 text-[10px] dark:fill-zinc-500">0</text>
                        <text x="26" y="112" text-anchor="end" class="fill-zinc-400 text-[10px] dark:fill-zinc-500">{{ (int) ceil($bookingTrend['maxValue'] / 2) }}</text>
                        <text x="26" y="40" text-anchor="end" class="fill-zinc-400 text-[10px] dark:fill-zinc-500">{{ $bookingTrend['maxValue'] }}</text>
                    </g>

                    <path data-super-admin-booking-area="active" d="{{ $bookingTrend['area'] }}" fill="url(#super-admin-active-area)" />
                    <path data-super-admin-booking-series="active" data-super-admin-booking-series-type="smooth" d="{{ $bookingTrend['paths']['active'] }}" fill="none" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="stroke-violet-400" vector-effect="non-scaling-stroke" />
                    <path data-super-admin-booking-series="completed" data-super-admin-booking-series-type="smooth" d="{{ $bookingTrend['paths']['completed'] }}" fill="none" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="stroke-emerald-400" vector-effect="non-scaling-stroke" />
                    <path data-super-admin-booking-series="cancelled" data-super-admin-booking-series-type="smooth" d="{{ $bookingTrend['paths']['cancelled'] }}" fill="none" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="stroke-rose-400" vector-effect="non-scaling-stroke" />

                    @foreach ($bookingTrend['days'] as $day)
                        <g
                            role="button"
                            tabindex="0"
                            aria-label="{{ $day['label'] }}: {{ $day['active'] }} active, {{ $day['completed'] }} completed, {{ $day['cancelled'] }} cancelled"
                            data-super-admin-booking-point="{{ $day['label'] }}"
                            x-on:mouseenter="hoveredDay = { label: '{{ $day['label'] }}', active: {{ $day['active'] }}, completed: {{ $day['completed'] }}, cancelled: {{ $day['cancelled'] }} }"
                            x-on:mouseleave="hoveredDay = null"
                            x-on:focus="hoveredDay = { label: '{{ $day['label'] }}', active: {{ $day['active'] }}, completed: {{ $day['completed'] }}, cancelled: {{ $day['cancelled'] }} }"
                            x-on:blur="hoveredDay = null"
                            class="cursor-crosshair outline-none"
                        >
                            <circle cx="{{ $day['x'] }}" cy="{{ $day['activeY'] }}" r="4" class="fill-zinc-50 stroke-violet-400 stroke-2 dark:fill-zinc-900" />
                            <circle cx="{{ $day['x'] }}" cy="{{ $day['completedY'] }}" r="4" class="fill-zinc-50 stroke-emerald-400 stroke-2 dark:fill-zinc-900" />
                            <circle cx="{{ $day['x'] }}" cy="{{ $day['cancelledY'] }}" r="4" class="fill-zinc-50 stroke-rose-400 stroke-2 dark:fill-zinc-900" />
                            <text x="{{ $day['x'] }}" y="207" text-anchor="middle" class="fill-zinc-400 text-[11px] dark:fill-zinc-500">{{ $day['label'] }}</text>
                        </g>
                    @endforeach
                </svg>
            </div>
        </flux:card>

        <flux:card class="dashboard-reveal card-lift flex flex-col shadow-sm" data-super-admin-status-layout="compact">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:heading size="lg">Booking status</flux:heading>
                    <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Where the current workload stands.</flux:text>
                </div>
                <div class="flex items-center gap-1">
                    <flux:icon name="chart-pie" class="size-5 text-zinc-400" />
                    <x-super-admin.section-menu :actions="[
                        ['label' => __('Refresh section'), 'icon' => 'arrow-path', 'wire' => '$refresh'],
                        ['label' => __('View bookings'), 'icon' => 'arrow-top-right-on-square', 'href' => route('admin.module', ['module' => 'service-bookings'])],
                    ]" />
                </div>
            </div>

            @php
                $statusTotal = array_sum(array_column($statusBreakdown, 'value'));
                $ringRadii = [78, 66, 54, 42];
                $statusRingClasses = [
                    'violet' => ['stroke' => 'stroke-violet-400', 'dot' => 'bg-violet-400'],
                    'sky' => ['stroke' => 'stroke-sky-400', 'dot' => 'bg-sky-400'],
                    'blue' => ['stroke' => 'stroke-blue-400', 'dot' => 'bg-blue-400'],
                    'emerald' => ['stroke' => 'stroke-emerald-400', 'dot' => 'bg-emerald-400'],
                    'rose' => ['stroke' => 'stroke-rose-400', 'dot' => 'bg-rose-400'],
                ];
            @endphp

            <div class="mt-5 min-h-[12rem] flex-1 grid min-w-0 items-center gap-5 xl:grid-cols-[minmax(0,1fr)_7rem]" data-super-admin-booking-status-chart data-super-admin-status-chart-layout="responsive">
                <svg viewBox="0 0 200 200" class="mx-auto aspect-square w-full max-w-56" role="img" aria-label="Booking status ring chart with {{ $statusTotal }} bookings">
                    <circle cx="100" cy="100" r="88" fill="none" class="stroke-zinc-200/70 dark:stroke-white/[0.04]" stroke-width="1" />

                    @foreach ($statusBreakdown as $status)
                        @php($ringRadius = $ringRadii[$loop->index] ?? 18)
                        <circle cx="100" cy="100" r="{{ $ringRadius }}" fill="none" class="stroke-zinc-200 dark:stroke-white/10" stroke-width="9" />
                        <circle
                            data-super-admin-status-ring="{{ $status['key'] }}"
                            cx="100"
                            cy="100"
                            r="{{ $ringRadius }}"
                            fill="none"
                            pathLength="100"
                            stroke-dasharray="{{ $status['percentage'] }} 100"
                            stroke-dashoffset="25"
                            stroke-width="9"
                            stroke-linecap="round"
                            class="{{ $statusRingClasses[$status['color']]['stroke'] }} transition-[stroke-dasharray] duration-500"
                            transform="rotate(-90 100 100)"
                        />
                    @endforeach

                    <g data-super-admin-status-chart-center="open">
                        <text x="100" y="98" text-anchor="middle" class="fill-zinc-800 text-[22px] font-semibold dark:fill-white">{{ $statusTotal }}</text>
                        <text x="100" y="113" text-anchor="middle" class="fill-zinc-500 text-[8px] uppercase tracking-[0.16em] dark:fill-zinc-400">Total</text>
                    </g>
                </svg>

                <div class="grid w-full min-w-0 grid-cols-2 gap-x-4 gap-y-2.5 xl:grid-cols-1" data-super-admin-status-legend>
                    @foreach ($statusBreakdown as $status)
                        <div class="flex items-center gap-2.5 text-xs">
                            <span class="size-2 shrink-0 rounded-full {{ $statusRingClasses[$status['color']]['dot'] }}"></span>
                            <span class="min-w-0 flex-1 whitespace-nowrap text-zinc-600 dark:text-zinc-300">{{ $status['label'] }}</span>
                            <span class="tabular-nums font-medium text-zinc-800 dark:text-zinc-100">{{ $status['value'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </flux:card>
    </div>

    <div class="dashboard-stagger grid gap-4 lg:grid-cols-3">
        <flux:card class="dashboard-reveal card-lift shadow-sm lg:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <flux:heading size="lg">Recent bookings</flux:heading>
                    <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">The latest requests moving through FixTrack.</flux:text>
                </div>
                <div class="flex items-center gap-1">
                    <flux:badge color="zinc" size="sm">Latest 6</flux:badge>
                    <x-super-admin.section-menu :actions="[
                        ['label' => __('Refresh section'), 'icon' => 'arrow-path', 'wire' => '$refresh'],
                        ['label' => __('View bookings'), 'icon' => 'arrow-top-right-on-square', 'href' => route('admin.module', ['module' => 'service-bookings'])],
                    ]" />
                </div>
            </div>

            <div class="app-table-shell mt-4" data-app-table-shell>
            <flux:table data-super-admin-dashboard-table>
                <flux:table.columns>
                    <flux:table.column>Booking</flux:table.column>
                    <flux:table.column>Customer</flux:table.column>
                    <flux:table.column>Service</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Created</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($recentBookings as $booking)
                        <flux:table.row class="transition-colors hover:bg-zinc-500/[0.04] dark:hover:bg-white/[0.04]">
                            <flux:table.cell variant="strong" class="whitespace-normal break-words align-top">{{ $booking['reference'] }}</flux:table.cell>
                            <flux:table.cell class="whitespace-normal break-words align-top">{{ $booking['customer'] }}</flux:table.cell>
                            <flux:table.cell class="whitespace-normal break-words align-top">{{ $booking['service'] }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    <x-super-admin.table-cell :value="$booking['status']" type="status" />
                                    @if ($booking['priority'])
                                        <flux:badge color="amber" size="sm">Priority</flux:badge>
                                    @endif
                                </div>
                            </flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap text-zinc-500 dark:text-zinc-400">{{ $booking['createdAt'] }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="py-0">
                                <div class="flex flex-col items-center justify-center px-6 py-14 text-center">
                                    <flux:icon name="clipboard-document-list" class="size-7 text-zinc-400" />
                                    <flux:heading size="lg" class="mt-3">No bookings yet</flux:heading>
                                    <flux:text class="mt-1.5 max-w-sm text-zinc-500 dark:text-zinc-400">No booking records yet.</flux:text>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
            </div>
        </flux:card>

        <flux:card class="dashboard-reveal card-lift shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:heading size="lg">Top services</flux:heading>
                    <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Most requested service types.</flux:text>
                </div>
                <x-super-admin.section-menu :actions="[
                    ['label' => __('Refresh section'), 'icon' => 'arrow-path', 'wire' => '$refresh'],
                    ['label' => __('View bookings'), 'icon' => 'arrow-top-right-on-square', 'href' => route('admin.module', ['module' => 'service-bookings'])],
                ]" />
            </div>

            <div class="mt-5 space-y-4">
                @forelse ($topServices as $service)
                    <div>
                        <div class="mb-1.5 flex items-center justify-between gap-3 text-sm">
                            <span class="text-zinc-600 dark:text-zinc-300">{{ $service['label'] }}</span>
                            <span class="font-medium tabular-nums text-zinc-700 dark:text-zinc-200">{{ $service['value'] }}</span>
                        </div>
                        <flux:progress :value="$service['percentage']" color="violet" />
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-12 text-center">
                        <flux:icon name="wrench-screwdriver" class="size-7 text-zinc-400" />
                        <flux:text class="mt-3 text-zinc-500 dark:text-zinc-400">Service demand will appear here.</flux:text>
                    </div>
                @endforelse
            </div>
        </flux:card>
    </div>

    <div class="dashboard-stagger grid gap-4 lg:grid-cols-2">
        <flux:card class="dashboard-reveal card-lift shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:heading size="lg">Needs attention</flux:heading>
                    <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Requests and reviews waiting for an admin decision.</flux:text>
                </div>
                <div class="flex items-center gap-1">
                    <flux:icon name="bell-alert" class="size-5 text-zinc-400" />
                    <x-super-admin.section-menu :actions="[
                        ['label' => __('Refresh section'), 'icon' => 'arrow-path', 'wire' => '$refresh'],
                        ['label' => __('View bookings'), 'icon' => 'arrow-top-right-on-square', 'href' => route('admin.module', ['module' => 'service-bookings'])],
                    ]" />
                </div>
            </div>

            <div class="mt-4 divide-y divide-zinc-200 dark:divide-white/10">
                @forelse ($needsAttention as $item)
                    <div class="flex items-center gap-3 py-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                            <flux:icon name="exclamation-triangle" class="size-4" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <flux:text class="truncate text-sm font-medium text-zinc-800 dark:text-white">{{ $item['title'] }}</flux:text>
                            <flux:text class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $item['detail'] }}</flux:text>
                        </div>
                        <flux:badge :color="$item['color']" size="sm">{{ $item['status'] }}</flux:badge>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-12 text-center">
                        <div class="flex size-11 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                            <flux:icon name="check-circle" class="size-6" />
                        </div>
                        <flux:heading size="lg" class="mt-3">All caught up</flux:heading>
                        <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">No pending decisions right now.</flux:text>
                    </div>
                @endforelse
            </div>
        </flux:card>

        <flux:card class="dashboard-reveal card-lift shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:heading size="lg">System status</flux:heading>
                    <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">A quick read on the services behind the dashboard.</flux:text>
                </div>
                <div class="flex items-center gap-1">
                    <flux:icon name="server-stack" class="size-5 text-zinc-400" />
                    <x-super-admin.section-menu :actions="[
                        ['label' => __('Refresh section'), 'icon' => 'arrow-path', 'wire' => '$refresh'],
                        ['label' => __('View system health'), 'icon' => 'arrow-top-right-on-square', 'href' => route('admin.module', ['module' => 'system-health'])],
                    ]" />
                </div>
            </div>

            <div class="mt-4 divide-y divide-zinc-200 dark:divide-white/10">
                @foreach ($systemStatus as $service)
                    <div class="flex items-center gap-3 py-3">
                        <span class="size-2 shrink-0 rounded-full {{ $service['color'] === 'emerald' ? 'bg-emerald-400' : 'bg-amber-400' }}"></span>
                        <div class="min-w-0 flex-1">
                            <flux:text class="text-sm font-medium text-zinc-800 dark:text-white">{{ $service['label'] }}</flux:text>
                            <flux:text class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $service['detail'] }}</flux:text>
                        </div>
                        <flux:badge :color="$service['color']" size="sm">{{ $service['value'] }}</flux:badge>
                    </div>
                @endforeach
            </div>
        </flux:card>
    </div>
</div>
