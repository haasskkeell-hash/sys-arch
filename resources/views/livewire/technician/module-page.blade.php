@php
    $formatDate = static fn ($date): string => $date ? \Illuminate\Support\Carbon::parse($date)->format('M d, Y') : '—';
    $formatDateTime = static fn ($date): string => $date ? \Illuminate\Support\Carbon::parse($date)->format('M d, Y · g:i A') : '—';
    $realtimeInterval = match ($moduleSlug) {
        'job-requests' => 5000,
        'overview', 'my-jobs', 'dispatch-routes' => 10000,
        'earnings', 'verification-profile' => 60000,
        default => null,
    };
    $realtimeScope = $realtimeInterval ? "technician-{$moduleSlug}" : null;
@endphp

<div
    class="flex w-full flex-col gap-6 p-6 lg:p-8"
    @if ($realtimeScope)
        data-realtime-scope="{{ $realtimeScope }}"
        data-realtime-url="{{ route('realtime.snapshot', ['scope' => $realtimeScope]) }}"
        data-realtime-interval="{{ $realtimeInterval }}"
    @endif
>
    <div class="dashboard-reveal">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('technician.module')" wire:navigate>Technician</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $module['label'] }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <flux:heading size="xl" level="1">{{ $module['label'] }}</flux:heading>
                <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">{{ $module['description'] }}</flux:text>
            </div>

            <div class="flex items-center gap-2">
                <flux:badge :color="$moduleState['color']" size="lg">{{ $moduleState['label'] }}</flux:badge>
                @if ($moduleSlug === 'overview' || $moduleSlug === 'job-requests')
                    <flux:button
                        size="sm"
                        variant="primary"
                        icon="bolt"
                        wire:click="setAvailability('{{ auth()->user()->availability_status === 'available' ? 'offline' : 'available' }}')"
                    >
                        {{ auth()->user()->availability_status === 'available' ? 'Go offline' : 'Go online' }}
                    </flux:button>
                @endif
            </div>
        </div>
    </div>

    <div class="dashboard-stagger grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($content['stats'] as $stat)
            <flux:card class="dashboard-reveal card-lift shadow-sm !border-s-4 !border-s-violet-400">
                <div class="flex items-center gap-4">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-300">
                        <flux:icon :name="$stat['icon']" class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <flux:text class="text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ $stat['label'] }}</flux:text>
                        <flux:heading class="mt-1 truncate text-2xl tracking-tight">{{ $stat['value'] }}</flux:heading>
                    </div>
                </div>
            </flux:card>
        @endforeach
    </div>

    @if ($moduleSlug === 'overview')
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(20rem,0.8fr)]">
            <flux:card class="dashboard-reveal shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <flux:heading size="lg">Current job</flux:heading>
                        <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">The next action in your active service flow.</flux:text>
                    </div>
                    @if ($content['activeJob'])
                        <x-super-admin.table-cell :value="$content['activeJob']->status" type="status" />
                    @endif
                </div>

                @if ($content['activeJob'])
                    @php($job = $content['activeJob'])
                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <div class="space-y-3">
                            <div>
                                <flux:text class="text-xs uppercase tracking-wider text-zinc-500">{{ $job->reference }}</flux:text>
                                <flux:heading size="lg" class="mt-1">{{ $job->service?->name ?: \Illuminate\Support\Str::headline($job->service_type) }}</flux:heading>
                            </div>
                            <div class="grid gap-3 text-sm sm:grid-cols-2">
                                <div><flux:text class="text-zinc-500">Customer</flux:text><div class="mt-1">{{ $job->customer?->name ?: '—' }}</div></div>
                                <div><flux:text class="text-zinc-500">Schedule</flux:text><div class="mt-1">{{ $formatDateTime($job->scheduled_at) }}</div></div>
                                <div class="sm:col-span-2"><flux:text class="text-zinc-500">Location</flux:text><div class="mt-1">{{ $job->address }}</div></div>
                            </div>
                        </div>
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-white/10 dark:bg-white/[0.03]">
                            <flux:text class="text-xs uppercase tracking-wider text-zinc-500">Service progress</flux:text>
                            <div class="mt-4 space-y-3 text-sm">
                                @foreach ([['Confirmed', true], ['En route', in_array($job->status, ['en_route', 'in_progress', 'completed'], true)], ['Repair in progress', in_array($job->status, ['in_progress', 'completed'], true)], ['Completed', $job->status === 'completed']] as [$step, $done])
                                    <div class="flex items-center gap-3">
                                        <span class="flex size-6 items-center justify-center rounded-full {{ $done ? 'bg-emerald-500 text-white' : 'border border-zinc-300 text-zinc-400 dark:border-zinc-600' }}">
                                            @if ($done)<flux:icon name="check" class="size-3.5" />@endif
                                        </span>
                                        <span class="{{ $done ? 'text-zinc-900 dark:text-white' : 'text-zinc-500' }}">{{ $step }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 flex flex-wrap gap-2">
                        @if ($job->status === 'assigned')
                            <flux:button size="sm" variant="primary" icon="map" wire:click="requestConfirmation('start-route', {{ $job->id }})">Start route</flux:button>
                        @elseif ($job->status === 'en_route')
                            <flux:button size="sm" variant="primary" icon="play" wire:click="requestConfirmation('start-job', {{ $job->id }})">Start repair</flux:button>
                        @elseif ($job->status === 'in_progress')
                            <flux:button size="sm" variant="primary" icon="check-circle" wire:click="requestConfirmation('complete-job', {{ $job->id }})">Complete job</flux:button>
                        @endif
                        <flux:button size="sm" variant="subtle" icon="map" href="{{ route('technician.module', ['module' => 'dispatch-routes']) }}" wire:navigate>View route</flux:button>
                    </div>
                @else
                    <div class="mt-6 rounded-xl border border-dashed border-zinc-300 px-6 py-12 text-center dark:border-zinc-700">
                        <flux:icon name="clipboard-document-list" class="mx-auto size-8 text-zinc-400" />
                        <flux:heading size="lg" class="mt-4">No active job</flux:heading>
                        <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Review the request queue when you are ready for your next assignment.</flux:text>
                        <flux:button class="mt-5" size="sm" variant="subtle" href="{{ route('technician.module', ['module' => 'job-requests']) }}" wire:navigate>Browse job requests</flux:button>
                    </div>
                @endif
            </flux:card>

            <flux:card class="dashboard-reveal shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <flux:heading size="lg">Performance</flux:heading>
                        <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Your service quality snapshot.</flux:text>
                    </div>
                    <flux:icon name="chart-bar" class="size-5 text-zinc-400" />
                </div>
                <div class="mt-6 divide-y divide-zinc-200 dark:divide-white/10">
                    @foreach ($content['performance'] as $metric)
                        <div class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
                            <flux:text class="text-zinc-500 dark:text-zinc-400">{{ $metric['label'] }}</flux:text>
                            <span class="font-medium tabular-nums">{{ $metric['value'] }}</span>
                        </div>
                    @endforeach
                </div>
                @if ($content['jobNotes'])
                    <div class="mt-6 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
                        <div class="font-medium">Job notes</div>
                        <div class="mt-1">{{ $content['jobNotes'] }}</div>
                    </div>
                @endif
            </flux:card>
        </div>

        <div class="dashboard-reveal">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <flux:heading size="lg">Incoming requests</flux:heading>
                    <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">New customer bookings waiting for a technician.</flux:text>
                </div>
                <div class="flex items-center gap-2">
                    <flux:badge color="sky" size="sm">{{ $content['incomingRequestCount'] }} waiting</flux:badge>
                    <x-super-admin.section-menu :actions="[['label' => __('Open all requests'), 'icon' => 'queue-list', 'href' => route('technician.module', ['module' => 'job-requests'])]]" />
                </div>
            </div>
            <div class="app-table-shell mt-4 overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-white/10 dark:bg-zinc-900" data-app-table-shell>
                <flux:table class="w-full min-w-[780px]">
                    <flux:table.columns><flux:table.column>Request</flux:table.column><flux:table.column>Customer</flux:table.column><flux:table.column>Service</flux:table.column><flux:table.column>Schedule</flux:table.column><flux:table.column>Status</flux:table.column><flux:table.column align="end" data-app-table-actions>Actions</flux:table.column></flux:table.columns>
                    <flux:table.rows>
                        @forelse ($content['incomingRequests'] as $request)
                            <flux:table.row :key="'incoming-'.$request->id">
                                <flux:table.cell variant="strong">{{ $request->reference }}</flux:table.cell>
                                    <flux:table.cell>{{ $request->customer?->name ?: '—' }}</flux:table.cell>
                                    <flux:table.cell>{{ $request->service?->name ?: \Illuminate\Support\Str::headline($request->service_type) }}</flux:table.cell>
                                <flux:table.cell class="whitespace-nowrap text-zinc-500">{{ $formatDateTime($request->scheduled_at ?: $request->created_at) }}</flux:table.cell>
                                <flux:table.cell><x-super-admin.table-cell :value="$request->status" type="status" /></flux:table.cell>
                                <flux:table.cell class="text-right" data-app-table-actions>
                                    <x-table-actions>
                                        <flux:menu.item as="button" type="button" icon="check-circle" wire:click="requestConfirmation('accept-request', {{ $request->id }})">Accept</flux:menu.item>
                                        <flux:menu.item as="button" type="button" icon="x-circle" wire:click="requestConfirmation('decline-request', {{ $request->id }})">Decline</flux:menu.item>
                                    </x-table-actions>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="6" class="py-10 text-center text-zinc-500">No incoming customer bookings right now.</flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="dashboard-reveal">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <flux:heading size="lg">Upcoming jobs</flux:heading>
                        <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Your next scheduled service visits.</flux:text>
                    </div>
                    <flux:badge color="zinc" size="sm">{{ count($content['upcomingJobs']) }} shown</flux:badge>
                </div>
                <div class="app-table-shell mt-4 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-white/10 dark:bg-zinc-900" data-app-table-shell>
                    <flux:table class="w-full">
                        <flux:table.columns><flux:table.column>Booking</flux:table.column><flux:table.column>Customer</flux:table.column><flux:table.column>Schedule</flux:table.column><flux:table.column>Status</flux:table.column></flux:table.columns>
                        <flux:table.rows>
                            @forelse ($content['upcomingJobs'] as $upcoming)
                                <flux:table.row :key="'upcoming-'.$upcoming->id">
                                    <flux:table.cell variant="strong">{{ $upcoming->reference }}</flux:table.cell>
                                    <flux:table.cell>{{ $upcoming->customer?->name ?: '—' }}</flux:table.cell>
                                    <flux:table.cell class="whitespace-nowrap text-zinc-500">{{ $formatDateTime($upcoming->scheduled_at) }}</flux:table.cell>
                                    <flux:table.cell><x-super-admin.table-cell :value="$upcoming->status" type="status" /></flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row><flux:table.cell colspan="4" class="py-8 text-center text-zinc-500">No upcoming jobs.</flux:table.cell></flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>
            </div>

            <div class="dashboard-reveal">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <flux:heading size="lg">Recent activity</flux:heading>
                        <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">The latest jobs attached to your profile.</flux:text>
                    </div>
                    <x-super-admin.section-menu :actions="[['label' => __('Refresh section'), 'icon' => 'arrow-path', 'wire' => '$refresh']]" />
                </div>
                <div class="app-table-shell mt-4 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-white/10 dark:bg-zinc-900" data-app-table-shell>
                    <flux:table class="w-full">
                        <flux:table.columns><flux:table.column>Booking</flux:table.column><flux:table.column>Service</flux:table.column><flux:table.column>Created</flux:table.column><flux:table.column>Status</flux:table.column></flux:table.columns>
                        <flux:table.rows>
                            @forelse ($content['recentJobs'] as $recent)
                                <flux:table.row :key="'recent-'.$recent->id">
                                    <flux:table.cell variant="strong">{{ $recent->reference }}</flux:table.cell>
                                    <flux:table.cell>{{ $recent->service?->name ?: \Illuminate\Support\Str::headline($recent->service_type) }}</flux:table.cell>
                                    <flux:table.cell class="whitespace-nowrap text-zinc-500">{{ $formatDate($recent->created_at) }}</flux:table.cell>
                                    <flux:table.cell><x-super-admin.table-cell :value="$recent->status" type="status" /></flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row><flux:table.cell colspan="4" class="py-8 text-center text-zinc-500">No job activity yet.</flux:table.cell></flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>
            </div>
        </div>
    @elseif ($moduleSlug === 'job-requests')
        <div class="dashboard-reveal">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div><flux:heading size="lg">Available Job Requests</flux:heading><flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Review and accept customer service requests that match your queue.</flux:text></div>
                <div class="flex items-center gap-2"><input wire:model.live.debounce.300ms="requestSearch" type="search" placeholder="Search requests" class="h-9 w-56 rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none focus:border-violet-400 dark:border-white/10 dark:bg-zinc-900" /><x-super-admin.section-menu :actions="[['label' => __('Refresh section'), 'icon' => 'arrow-path', 'wire' => '$refresh']]" /></div>
            </div>
            <div class="app-table-shell mt-4 overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-white/10 dark:bg-zinc-900" data-app-table-shell>
                <flux:table class="w-full min-w-[850px]">
                    <flux:table.columns><flux:table.column>Request</flux:table.column><flux:table.column>Service</flux:table.column><flux:table.column>Customer</flux:table.column><flux:table.column>Schedule</flux:table.column><flux:table.column>Location</flux:table.column><flux:table.column>Status</flux:table.column><flux:table.column align="end" data-app-table-actions>Actions</flux:table.column></flux:table.columns>
                    <flux:table.rows>
                        @forelse ($content['requests'] as $request)
                            <flux:table.row :key="'request-'.$request->id">
                                <flux:table.cell variant="strong">{{ $request->reference }}</flux:table.cell><flux:table.cell>{{ $request->service?->name ?: \Illuminate\Support\Str::headline($request->service_type) }}</flux:table.cell><flux:table.cell>{{ $request->customer?->name ?: '—' }}</flux:table.cell><flux:table.cell class="whitespace-nowrap text-zinc-500">{{ $formatDateTime($request->scheduled_at) }}</flux:table.cell><flux:table.cell class="max-w-56 truncate text-zinc-500">{{ $request->address }}</flux:table.cell><flux:table.cell><x-super-admin.table-cell :value="$request->status" type="status" /></flux:table.cell>
                                <flux:table.cell class="text-right" data-app-table-actions>
                                    <x-table-actions>
                                        <flux:menu.item as="button" type="button" icon="check-circle" wire:click="requestConfirmation('accept-request', {{ $request->id }})">Accept</flux:menu.item>
                                        <flux:menu.item as="button" type="button" icon="x-circle" wire:click="requestConfirmation('decline-request', {{ $request->id }})">Decline</flux:menu.item>
                                    </x-table-actions>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="7" class="py-14 text-center text-zinc-500">No available requests match your search.</flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
            <div class="mt-4">{{ $content['requests']->links() }}</div>
        </div>
    @elseif ($moduleSlug === 'my-jobs')
        <div class="dashboard-reveal">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div><flux:heading size="lg">My Job Registry</flux:heading><flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Manage assigned service requests and job progress.</flux:text></div>
                <div class="flex flex-wrap items-center gap-2"><input wire:model.live.debounce.300ms="jobSearch" type="search" placeholder="Search jobs" class="h-9 w-48 rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none focus:border-violet-400 dark:border-white/10 dark:bg-zinc-900" /><select wire:model.live="jobStatus" class="h-9 rounded-lg border border-zinc-200 bg-white px-3 text-sm dark:border-white/10 dark:bg-zinc-900"><option value="all">All statuses</option><option value="assigned">Assigned</option><option value="en_route">En route</option><option value="in_progress">In progress</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select><x-super-admin.section-menu :actions="[['label' => __('Refresh section'), 'icon' => 'arrow-path', 'wire' => '$refresh']]" /></div>
            </div>
            <div class="app-table-shell mt-4 overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-white/10 dark:bg-zinc-900" data-app-table-shell>
                <flux:table class="w-full min-w-[850px]"><flux:table.columns><flux:table.column>Booking</flux:table.column><flux:table.column>Customer</flux:table.column><flux:table.column>Service</flux:table.column><flux:table.column>Schedule</flux:table.column><flux:table.column>Location</flux:table.column><flux:table.column>Status</flux:table.column><flux:table.column align="end" data-app-table-actions>Actions</flux:table.column></flux:table.columns><flux:table.rows>
                    @forelse ($content['jobs'] as $job)
                        <flux:table.row :key="'job-'.$job->id">
                            <flux:table.cell variant="strong">{{ $job->reference }}</flux:table.cell>
                            <flux:table.cell>{{ $job->customer?->name ?: '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $job->service?->name ?: \Illuminate\Support\Str::headline($job->service_type) }}</flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap text-zinc-500">{{ $formatDateTime($job->scheduled_at) }}</flux:table.cell>
                            <flux:table.cell class="max-w-56 truncate text-zinc-500">{{ $job->address }}</flux:table.cell>
                            <flux:table.cell><x-super-admin.table-cell :value="$job->status" type="status" /></flux:table.cell>
                            <flux:table.cell class="text-right" data-app-table-actions>
                                <x-table-actions>
                                    @if ($job->status === 'assigned')
                                        <flux:menu.item as="button" type="button" icon="map" wire:click="requestConfirmation('start-route', {{ $job->id }})">Start route</flux:menu.item>
                                    @elseif ($job->status === 'en_route')
                                        <flux:menu.item as="button" type="button" icon="play" wire:click="requestConfirmation('start-job', {{ $job->id }})">Start repair</flux:menu.item>
                                    @elseif ($job->status === 'in_progress')
                                        <flux:menu.item as="button" type="button" icon="check-circle" wire:click="requestConfirmation('complete-job', {{ $job->id }})">Complete job</flux:menu.item>
                                    @endif
                                </x-table-actions>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row><flux:table.cell colspan="7" class="py-14 text-center text-zinc-500">No jobs match your filters.</flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows></flux:table>
            </div>
            <div class="mt-4">{{ $content['jobs']->links() }}</div>
        </div>
    @elseif ($moduleSlug === 'schedule')
        <div class="dashboard-reveal">
            <div class="flex flex-wrap items-end justify-between gap-3"><div><flux:heading size="lg">Service Schedule</flux:heading><flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Review upcoming appointments and service timing.</flux:text></div><div class="flex flex-wrap items-center gap-2"><input wire:model.live.debounce.300ms="scheduleSearch" type="search" placeholder="Search schedule" class="h-9 w-48 rounded-lg border border-zinc-200 bg-white px-3 text-sm outline-none focus:border-violet-400 dark:border-white/10 dark:bg-zinc-900" /><select wire:model.live="scheduleRange" class="h-9 rounded-lg border border-zinc-200 bg-white px-3 text-sm dark:border-white/10 dark:bg-zinc-900"><option value="upcoming">Upcoming</option><option value="today">Today</option><option value="completed">Completed</option><option value="all">All appointments</option></select><x-super-admin.section-menu :actions="[['label' => __('Refresh section'), 'icon' => 'arrow-path', 'wire' => '$refresh']]" /></div></div>
            <div class="mt-4 overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-white/10 dark:bg-zinc-900"><flux:table class="w-full min-w-[800px]"><flux:table.columns><flux:table.column>Date</flux:table.column><flux:table.column>Time</flux:table.column><flux:table.column>Booking</flux:table.column><flux:table.column>Customer</flux:table.column><flux:table.column>Service</flux:table.column><flux:table.column>Location</flux:table.column><flux:table.column>Status</flux:table.column></flux:table.columns><flux:table.rows>
                @forelse ($content['schedule'] as $scheduled)
                    <flux:table.row :key="'schedule-'.$scheduled->id"><flux:table.cell variant="strong">{{ $formatDate($scheduled->scheduled_at) }}</flux:table.cell><flux:table.cell class="whitespace-nowrap">{{ $scheduled->scheduled_at ? \Illuminate\Support\Carbon::parse($scheduled->scheduled_at)->format('g:i A') : '—' }}</flux:table.cell><flux:table.cell>{{ $scheduled->reference }}</flux:table.cell><flux:table.cell>{{ $scheduled->customer?->name ?: '—' }}</flux:table.cell><flux:table.cell>{{ $scheduled->service?->name ?: \Illuminate\Support\Str::headline($scheduled->service_type) }}</flux:table.cell><flux:table.cell class="max-w-48 truncate text-zinc-500">{{ $scheduled->address }}</flux:table.cell><flux:table.cell><x-super-admin.table-cell :value="$scheduled->status" type="status" /></flux:table.cell></flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="7" class="py-14 text-center text-zinc-500">No appointments match this range.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows></flux:table></div><div class="mt-4">{{ $content['schedule']->links() }}</div>
        </div>
    @elseif ($moduleSlug === 'dispatch-routes')
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(20rem,0.75fr)]">
            <flux:card class="dashboard-reveal shadow-sm"><div class="flex items-start justify-between gap-3"><div><flux:heading size="lg">Current Route</flux:heading><flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Follow active assignments and service locations.</flux:text></div><x-super-admin.section-menu :actions="[['label' => __('Refresh route'), 'icon' => 'arrow-path', 'wire' => '$refresh']]" /></div><div class="mt-5 flex min-h-64 items-center justify-center rounded-xl border border-dashed border-zinc-300 bg-zinc-50 text-center dark:border-zinc-700 dark:bg-white/[0.03]"><div>@if ($content['currentRoute'])<flux:icon name="map" class="mx-auto size-9 text-violet-500" /><flux:heading size="lg" class="mt-4">{{ $content['currentRoute']->reference }}</flux:heading><flux:text class="mt-1 text-zinc-500">{{ $content['currentRoute']->address }}</flux:text><flux:button class="mt-5" size="sm" variant="subtle" href="{{ route('technician.module', ['module' => 'my-jobs']) }}" wire:navigate>Open job details</flux:button>@else<flux:icon name="map" class="mx-auto size-9 text-zinc-400" /><flux:heading size="lg" class="mt-4">No active route</flux:heading><flux:text class="mt-1 text-zinc-500">Accepted assignments will appear here.</flux:text>@endif</div></flux:card>
            <flux:card class="dashboard-reveal shadow-sm"><flux:heading size="lg">Location signal</flux:heading><flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">The last location available to dispatch.</flux:text><div class="mt-6 space-y-4 text-sm"><div class="flex justify-between gap-4"><span class="text-zinc-500">Latitude</span><span>{{ $content['location']['latitude'] ?? 'Waiting' }}</span></div><div class="flex justify-between gap-4"><span class="text-zinc-500">Longitude</span><span>{{ $content['location']['longitude'] ?? 'Waiting' }}</span></div><div class="flex justify-between gap-4"><span class="text-zinc-500">Last seen</span><span>{{ $formatDateTime($content['location']['last_seen_at']) }}</span></div></div><div class="mt-6 rounded-xl bg-sky-50 p-4 text-sm text-sky-900 dark:bg-sky-500/10 dark:text-sky-200">Keep location sharing enabled while you are available so dispatch can coordinate the next visit.</div></flux:card>
        </div>
        <div class="dashboard-reveal"><div class="flex items-start justify-between gap-3"><div><flux:heading size="lg">Route assignments</flux:heading><flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Active jobs currently assigned to your route.</flux:text></div><flux:badge color="zinc" size="sm">{{ count($content['routeJobs']) }} shown</flux:badge></div><div class="mt-4 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-white/10 dark:bg-zinc-900"><flux:table class="w-full"><flux:table.columns><flux:table.column>Booking</flux:table.column><flux:table.column>Service</flux:table.column><flux:table.column>Location</flux:table.column><flux:table.column>Status</flux:table.column></flux:table.columns><flux:table.rows>@forelse ($content['routeJobs'] as $routeJob)<flux:table.row :key="'route-'.$routeJob->id"><flux:table.cell variant="strong">{{ $routeJob->reference }}</flux:table.cell><flux:table.cell>{{ $routeJob->service_type }}</flux:table.cell><flux:table.cell>{{ $routeJob->address }}</flux:table.cell><flux:table.cell><x-super-admin.table-cell :value="$routeJob->status" type="status" /></flux:table.cell></flux:table.row>@empty<flux:table.row><flux:table.cell colspan="4" class="py-10 text-center text-zinc-500">No active route assignments.</flux:table.cell></flux:table.row>@endforelse</flux:table.rows></flux:table></div></div>
    @elseif ($moduleSlug === 'earnings')
        <div class="dashboard-reveal"><div class="flex items-start justify-between gap-3"><div><flux:heading size="lg">Earnings history</flux:heading><flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Review completed-job earnings and payout status.</flux:text></div><x-super-admin.section-menu :actions="[['label' => __('Refresh earnings'), 'icon' => 'arrow-path', 'wire' => '$refresh']]" /></div><div class="mt-4 overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-white/10 dark:bg-zinc-900"><flux:table class="w-full min-w-[720px]"><flux:table.columns><flux:table.column>Booking</flux:table.column><flux:table.column>Service</flux:table.column><flux:table.column>Amount</flux:table.column><flux:table.column>Method</flux:table.column><flux:table.column>Paid at</flux:table.column><flux:table.column>Status</flux:table.column></flux:table.columns><flux:table.rows>@forelse ($content['payments'] as $payment)<flux:table.row :key="'payment-'.$payment->id"><flux:table.cell variant="strong">{{ $payment->booking?->reference ?: '—' }}</flux:table.cell><flux:table.cell>{{ $payment->booking?->service_type ?: '—' }}</flux:table.cell><flux:table.cell class="tabular-nums">₱{{ number_format((float) $payment->amount, 2) }}</flux:table.cell><flux:table.cell>{{ $payment->method ?: '—' }}</flux:table.cell><flux:table.cell class="whitespace-nowrap text-zinc-500">{{ $formatDateTime($payment->paid_at) }}</flux:table.cell><flux:table.cell><x-super-admin.table-cell :value="$payment->status" type="status" /></flux:table.cell></flux:table.row>@empty<flux:table.row><flux:table.cell colspan="6" class="py-14 text-center text-zinc-500">No payment records yet.</flux:table.cell></flux:table.row>@endforelse</flux:table.rows></flux:table></div><div class="mt-4">{{ $content['payments']->links() }}</div></div>
    @elseif ($moduleSlug === 'ratings-reviews')
        <div class="dashboard-reveal"><div class="flex items-start justify-between gap-3"><div><flux:heading size="lg">Customer feedback</flux:heading><flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Track customer feedback and service quality.</flux:text></div><x-super-admin.section-menu :actions="[['label' => __('Refresh reviews'), 'icon' => 'arrow-path', 'wire' => '$refresh']]" /></div><div class="mt-4 overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-white/10 dark:bg-zinc-900"><flux:table class="w-full min-w-[700px]"><flux:table.columns><flux:table.column>Rating</flux:table.column><flux:table.column>Comment</flux:table.column><flux:table.column>Booking</flux:table.column><flux:table.column>Published</flux:table.column><flux:table.column>Status</flux:table.column></flux:table.columns><flux:table.rows>@forelse ($content['reviews'] as $review)<flux:table.row :key="'review-'.$review->id"><flux:table.cell variant="strong"><span class="text-amber-500">★</span> {{ $review->rating }} / 5</flux:table.cell><flux:table.cell class="max-w-md truncate">{{ $review->comment ?: 'No written feedback.' }}</flux:table.cell><flux:table.cell>{{ $review->booking?->reference ?: '—' }}</flux:table.cell><flux:table.cell class="whitespace-nowrap text-zinc-500">{{ $formatDate($review->created_at) }}</flux:table.cell><flux:table.cell><x-super-admin.table-cell :value="$review->status" type="status" /></flux:table.cell></flux:table.row>@empty<flux:table.row><flux:table.cell colspan="5" class="py-14 text-center text-zinc-500">No published reviews yet.</flux:table.cell></flux:table.row>@endforelse</flux:table.rows></flux:table></div><div class="mt-4">{{ $content['reviews']->links() }}</div></div>
    @elseif ($moduleSlug === 'verification-profile')
        @php($profile = $content['profile'])
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(20rem,0.7fr)]"><flux:card class="dashboard-reveal shadow-sm"><div class="flex items-start justify-between gap-3"><div><flux:heading size="lg">Verification status</flux:heading><flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Manage the profile information used for technician assignments.</flux:text></div>@if ($profile)<x-super-admin.table-cell :value="$profile['status']" type="status" />@endif</div>@if ($profile)<div class="mt-6 grid gap-4 sm:grid-cols-2"><div><flux:text class="text-zinc-500">Service area</flux:text><div class="mt-1">{{ $profile['service_area'] ?: 'Not provided' }}</div></div><div><flux:text class="text-zinc-500">Years of experience</flux:text><div class="mt-1">{{ $profile['years_experience'] }}</div></div><div><flux:text class="text-zinc-500">Phone</flux:text><div class="mt-1">{{ $profile['phone'] ?: 'Not provided' }}</div></div><div><flux:text class="text-zinc-500">Risk level</flux:text><div class="mt-1"><x-super-admin.table-cell :value="$profile['risk_level']" type="risk" /></div></div><div class="sm:col-span-2"><flux:text class="text-zinc-500">Address</flux:text><div class="mt-1">{{ $profile['address'] ?: 'Not provided' }}</div></div></div>@if ($profile['decision_reason'])<div class="mt-6 rounded-xl bg-zinc-50 p-4 text-sm dark:bg-white/[0.03]"><div class="font-medium">Reviewer note</div><div class="mt-1 text-zinc-500">{{ $profile['decision_reason'] }}</div></div>@endif @else<div class="mt-6 rounded-xl border border-dashed border-zinc-300 px-6 py-12 text-center dark:border-zinc-700"><flux:icon name="identification" class="mx-auto size-8 text-zinc-400" /><flux:heading size="lg" class="mt-4">Verification not submitted</flux:heading><flux:text class="mt-1 text-zinc-500">Your verification details will appear here once submitted.</flux:text></div>@endif</flux:card><flux:card class="dashboard-reveal shadow-sm"><flux:heading size="lg">Skills and documents</flux:heading><flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">The credentials and services attached to your profile.</flux:text><div class="mt-6 flex flex-wrap gap-2">@forelse (($profile['service_categories'] ?? []) as $category)<flux:badge color="zinc" size="sm">{{ $category }}</flux:badge>@empty<flux:text class="text-sm text-zinc-500">No service categories listed.</flux:text>@endforelse</div><div class="mt-6 space-y-3">@forelse (($profile['documents'] ?? []) as $document)<div class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 px-3 py-2.5 text-sm dark:border-white/10"><span>{{ $document->label }}</span><x-super-admin.table-cell :value="$document->status" type="status" /></div>@empty<flux:text class="text-sm text-zinc-500">No documents listed.</flux:text>@endforelse</div></flux:card></div>
    @elseif ($moduleSlug === 'notifications')
        <div class="dashboard-reveal"><div class="flex items-start justify-between gap-3"><div><flux:heading size="lg">Latest updates</flux:heading><flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Review job assignments, updates, and announcements.</flux:text></div><x-super-admin.section-menu :actions="[['label' => __('Refresh updates'), 'icon' => 'arrow-path', 'wire' => '$refresh']]" /></div><div class="mt-4 grid gap-3">@forelse ($content['items'] as $item)<div class="flex items-start gap-4 rounded-xl border border-zinc-200 bg-white p-4 dark:border-white/10 dark:bg-zinc-900"><div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-300"><flux:icon name="bell" class="size-5" /></div><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><flux:heading size="sm">{{ $item['title'] }}</flux:heading><x-super-admin.table-cell :value="$item['status']" type="status" /></div><flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">{{ $item['detail'] }}</flux:text></div></div>@empty<div class="rounded-xl border border-dashed border-zinc-300 px-6 py-14 text-center dark:border-zinc-700"><flux:icon name="bell" class="mx-auto size-8 text-zinc-400" /><flux:heading size="lg" class="mt-4">No new updates</flux:heading><flux:text class="mt-1 text-zinc-500">You are all caught up.</flux:text></div>@endforelse</div></div>
    @elseif ($moduleSlug === 'support')
        <div class="dashboard-reveal"><div class="flex items-start justify-between gap-3"><div><flux:heading size="lg">Support requests</flux:heading><flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Access service support and review help requests.</flux:text></div><x-super-admin.section-menu :actions="[['label' => __('Refresh section'), 'icon' => 'arrow-path', 'wire' => '$refresh']]" /></div><div class="mt-4 overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-white/10 dark:bg-zinc-900"><flux:table class="w-full min-w-[720px]"><flux:table.columns><flux:table.column>Reference</flux:table.column><flux:table.column>Subject</flux:table.column><flux:table.column>Category</flux:table.column><flux:table.column>Priority</flux:table.column><flux:table.column>Updated</flux:table.column><flux:table.column>Status</flux:table.column></flux:table.columns><flux:table.rows>@forelse ($content['tickets'] as $ticket)<flux:table.row :key="'ticket-'.$ticket->id"><flux:table.cell variant="strong">{{ $ticket->reference }}</flux:table.cell><flux:table.cell>{{ $ticket->subject }}</flux:table.cell><flux:table.cell><x-super-admin.table-cell :value="$ticket->category" type="category" /></flux:table.cell><flux:table.cell><x-super-admin.table-cell :value="$ticket->priority" type="priority" /></flux:table.cell><flux:table.cell class="whitespace-nowrap text-zinc-500">{{ $formatDateTime($ticket->updated_at) }}</flux:table.cell><flux:table.cell><x-super-admin.table-cell :value="$ticket->status" type="status" /></flux:table.cell></flux:table.row>@empty<flux:table.row><flux:table.cell colspan="6" class="py-14 text-center text-zinc-500">No support requests yet.</flux:table.cell></flux:table.row>@endforelse</flux:table.rows></flux:table></div><div class="mt-4">{{ $content['tickets']->links() }}</div></div>
    @endif

    <flux:modal name="technician-confirmation-modal" class="max-w-md" @close="cancelConfirmation" wire:model="showConfirmation" data-technician-confirmation-modal>
        <div class="space-y-6">
            <div class="space-y-2"><flux:heading size="lg">{{ $confirmationTitle }}</flux:heading><flux:text>{{ $confirmationDescription }}</flux:text></div>
            <div class="flex justify-end gap-3"><flux:button variant="outline" wire:click="cancelConfirmation">Cancel</flux:button><flux:button :variant="$confirmationVariant" wire:click="executeConfirmedAction" wire:loading.attr="disabled" wire:target="executeConfirmedAction">{{ $confirmationActionLabel }}</flux:button></div>
        </div>
    </flux:modal>
</div>
