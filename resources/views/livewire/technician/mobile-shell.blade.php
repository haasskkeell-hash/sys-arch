@php
    $technicianUser = auth()->user();
    $technicianActiveJob = $content['activeJob'] ?? null;
    $technicianMapMarkers = collect();

    foreach (collect([$technicianActiveJob, ...collect($content['incomingRequests'] ?? [])->all(), ...collect($content['routeJobs'] ?? [])->all()])->filter() as $booking) {
        if ($booking->latitude === null || $booking->longitude === null) {
            continue;
        }

        $technicianMapMarkers->push([
            'type' => $booking->id === $technicianActiveJob?->id ? 'active' : 'booking',
            'title' => $booking->service?->name ?: \Illuminate\Support\Str::headline($booking->service_type),
            'address' => $booking->address,
            'avatar' => $booking->customer?->avatarUrl(),
            'initials' => $booking->customer?->initials(),
            'latitude' => (float) $booking->latitude,
            'longitude' => (float) $booking->longitude,
        ]);
    }

    if (is_numeric($technicianUser->latitude ?? null) && is_numeric($technicianUser->longitude ?? null)) {
        $technicianMapMarkers->push([
            'type' => 'technician',
            'title' => 'Your current location',
            'address' => $technicianUser->name,
            'avatar' => $technicianUser->avatarUrl(),
            'initials' => $technicianUser->initials(),
            'latitude' => (float) $technicianUser->latitude,
            'longitude' => (float) $technicianUser->longitude,
        ]);
    }

    $technicianMapMarkers = $technicianMapMarkers
        ->unique(fn (array $marker): string => $marker['type'].':'.$marker['latitude'].':'.$marker['longitude'])
        ->values();
    $technicianMapCenterLatitude = (float) ($technicianMapMarkers->first()['latitude'] ?? 14.5995);
    $technicianMapCenterLongitude = (float) ($technicianMapMarkers->first()['longitude'] ?? 120.9842);
    $technicianStatusLabel = static fn (?string $status): string => $status ? \Illuminate\Support\Str::headline($status) : '—';
    $technicianAction = static fn (?string $status): ?array => match ($status) {
        'assigned' => ['label' => 'Start route', 'action' => 'start-route', 'icon' => 'map'],
        'en_route' => ['label' => 'Complete job', 'action' => 'complete-job', 'icon' => 'check-circle'],
        'in_progress' => ['label' => 'Complete job', 'action' => 'complete-job', 'icon' => 'check-circle'],
        default => null,
    };
    $technicianMobileBackToProfile = request()->query('from') === 'profile';
    $technicianMobileBackUrl = $technicianMobileBackToProfile
        ? route('technician.module', ['module' => 'verification-profile'])
        : route('technician.module');
@endphp

<section
    class="technician-mobile-shell mx-auto w-full max-w-screen-sm lg:hidden {{ $moduleSlug === 'overview' ? 'technician-mobile-shell--fixed-home' : 'space-y-5 px-4 pb-28 pt-4' }}"
    data-app-technician-mobile-shell
    data-app-technician-mobile-module="{{ $moduleSlug }}"
>
    <div data-app-network-status hidden class="mx-4 rounded-xl bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800 dark:bg-amber-500/10 dark:text-amber-200" role="status" aria-live="polite"></div>

    @if ($moduleSlug === 'overview')
        <div
            class="technician-mobile-map technician-mobile-map-surface relative overflow-hidden"
            data-app-technician-mobile-map
            data-app-customer-mobile-map
            data-map-center-lat="{{ $technicianMapCenterLatitude }}"
            data-map-center-lng="{{ $technicianMapCenterLongitude }}"
            data-map-markers="{{ $technicianMapMarkers->toJson() }}"
        >
            <div
                class="absolute inset-0"
                data-app-customer-map
                data-map-center-lat="{{ $technicianMapCenterLatitude }}"
                data-map-center-lng="{{ $technicianMapCenterLongitude }}"
                data-map-markers="{{ $technicianMapMarkers->toJson() }}"
                wire:ignore
            >
                <div data-app-map-status class="pointer-events-none absolute inset-0 z-[1000] flex items-center justify-center bg-zinc-100/90 text-sm font-medium text-zinc-500 dark:bg-zinc-900/90 dark:text-zinc-400" role="status" aria-live="polite">Loading live map…</div>
            </div>

            <div class="pointer-events-none absolute inset-x-4 top-4 z-[1000] flex items-start justify-between gap-3">
                <div class="rounded-full bg-zinc-950/90 px-3 py-2 text-xs font-semibold text-white shadow-lg">Live dispatch map</div>
                <span class="rounded-full bg-white/95 px-3 py-2 text-xs font-semibold text-zinc-800 shadow-lg dark:bg-zinc-950/95 dark:text-white">{{ $moduleState['label'] }}</span>
            </div>

            <button type="button" data-app-map-locate class="absolute bottom-12 right-4 z-[1000] flex size-11 items-center justify-center rounded-full border border-zinc-200 bg-white text-zinc-800 shadow-lg transition hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-violet-500 dark:border-white/10 dark:bg-zinc-950 dark:text-white dark:hover:bg-zinc-900" aria-label="Center map on my location">
                <flux:icon name="map-pin" class="size-5" />
            </button>
        </div>

        <div class="technician-mobile-home-sheet relative z-10 -mt-8 bg-white px-4 pb-4 pt-3 dark:bg-zinc-950" data-app-mobile-home-sheet data-mobile-sheet-state="normal">
            <button type="button" class="technician-mobile-sheet-handle mx-auto mb-3 block h-1 w-10 rounded-full border-0 bg-zinc-300 p-0 dark:bg-zinc-700" data-app-mobile-home-sheet-handle aria-expanded="false" aria-label="Raise home sheet"></button>

            <div class="mb-3 flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-500">Technician home</p>
                    <h1 class="truncate text-lg font-semibold tracking-tight text-zinc-900 dark:text-white">Ready for the next job?</h1>
                </div>
                <button type="button" wire:click="setAvailability('{{ $technicianUser->availability_status === 'available' ? 'offline' : 'available' }}')" class="min-h-11 shrink-0 rounded-full px-3 text-xs font-semibold {{ $technicianUser->availability_status === 'available' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-zinc-100 text-zinc-700 dark:bg-white/10 dark:text-zinc-200' }}">
                    {{ $technicianUser->availability_status === 'available' ? 'Online' : 'Go online' }}
                </button>
            </div>

            <div class="grid grid-cols-3 gap-2.5">
                <a href="{{ route('technician.module', ['module' => 'job-requests']) }}" wire:navigate data-app-technician-mobile-action="job-requests" class="technician-mobile-action flex min-h-20 flex-col items-center justify-center gap-1.5 rounded-2xl border border-violet-100 bg-violet-50 px-2 py-2 text-center transition hover:border-violet-300 dark:border-violet-400/20 dark:bg-violet-500/10">
                    <span class="technician-mobile-action__icon flex size-9 items-center justify-center rounded-xl bg-violet-600 text-white"><flux:icon name="bolt" class="size-4.5" /></span>
                    <span class="text-[11px] font-semibold text-zinc-900 dark:text-white">Job requests</span>
                </a>
                <a href="{{ route('technician.module', ['module' => 'my-jobs']) }}" wire:navigate data-app-technician-mobile-action="my-jobs" class="technician-mobile-action flex min-h-20 flex-col items-center justify-center gap-1.5 rounded-2xl border border-zinc-200 bg-zinc-50 px-2 py-2 text-center transition hover:border-violet-300 hover:bg-violet-50 dark:border-white/10 dark:bg-white/[0.04]">
                    <span class="technician-mobile-action__icon flex size-9 items-center justify-center rounded-xl bg-zinc-900 text-white dark:bg-white dark:text-zinc-900"><flux:icon name="clipboard-document-list" class="size-4.5" /></span>
                    <span class="text-[11px] font-semibold text-zinc-900 dark:text-white">My jobs</span>
                </a>
                <a href="{{ route('technician.module', ['module' => 'schedule']) }}" wire:navigate data-app-technician-mobile-action="schedule" class="technician-mobile-action flex min-h-20 flex-col items-center justify-center gap-1.5 rounded-2xl border border-zinc-200 bg-zinc-50 px-2 py-2 text-center transition hover:border-violet-300 hover:bg-violet-50 dark:border-white/10 dark:bg-white/[0.04]">
                    <span class="technician-mobile-action__icon flex size-9 items-center justify-center rounded-xl bg-zinc-900 text-white dark:bg-white dark:text-zinc-900"><flux:icon name="calendar-days" class="size-4.5" /></span>
                    <span class="text-[11px] font-semibold text-zinc-900 dark:text-white">Schedule</span>
                </a>
            </div>

            @if ($technicianActiveJob)
                @php($mobileJobAction = $technicianAction($technicianActiveJob->status))
                <div class="mt-3 rounded-2xl bg-violet-50 px-3.5 py-3 dark:bg-violet-500/10">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-violet-700 dark:text-violet-300">Active job</p>
                            <p class="mt-1 truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $technicianActiveJob->service?->name ?: \Illuminate\Support\Str::headline($technicianActiveJob->service_type) }}</p>
                        <p class="mt-0.5 truncate text-xs text-zinc-600 dark:text-zinc-300">{{ $technicianActiveJob->address }}</p>
                    </div>
                    <span class="shrink-0 rounded-full bg-white/80 px-2 py-1 text-[10px] font-semibold text-violet-800 dark:bg-zinc-950/50 dark:text-violet-200">{{ $technicianStatusLabel($technicianActiveJob->status) }}</span>
                </div>
                    <div class="mt-2 flex items-center justify-between gap-2">
                        <span class="text-[11px] text-zinc-500">{{ $technicianActiveJob->reference }}</span>
                        @if ($mobileJobAction)
                            @if ($mobileJobAction['action'] === 'complete-job')
                                <span class="text-[11px] font-medium text-zinc-500">{{ $technicianActiveJob->status === 'en_route' ? 'On the way to customer' : 'Service in progress' }}</span>
                            @else
                                <button type="button" wire:click="requestConfirmation('{{ $mobileJobAction['action'] }}', {{ $technicianActiveJob->id }})" class="min-h-10 rounded-xl bg-zinc-950 px-3 text-xs font-semibold text-white dark:bg-white dark:text-zinc-950">{{ $mobileJobAction['label'] }}</button>
                            @endif
                        @endif
                    </div>
                    @if ($mobileJobAction && $mobileJobAction['action'] === 'complete-job')
                        <button type="button" data-app-technician-complete-swipe data-booking-id="{{ $technicianActiveJob->id }}" class="technician-mobile-swipe mt-3" aria-label="Swipe right to complete job">
                            <span data-app-technician-complete-swipe-fill></span>
                            <span data-app-technician-complete-swipe-label class="relative z-10">Swipe to complete</span>
                            <span data-app-technician-complete-swipe-thumb><flux:icon name="arrow-right" class="size-4" /></span>
                        </button>
                    @endif
                    @if (in_array($technicianActiveJob->status, \App\Models\Booking::ACTIVE_STATUSES, true))
                        <button type="button" wire:click="requestConfirmation('cancel-job', {{ $technicianActiveJob->id }})" class="mt-3 min-h-12 w-full rounded-xl bg-red-500 px-3 text-xs font-semibold text-white transition hover:bg-red-600 dark:bg-red-600 dark:hover:bg-red-500">Cancel</button>
                    @endif
                </div>
            @elseif (($content['incomingRequestCount'] ?? 0) > 0)
                <a href="{{ route('technician.module', ['module' => 'job-requests']) }}" wire:navigate class="mt-3 flex min-h-14 items-center justify-between gap-3 rounded-2xl bg-violet-50 px-3.5 py-3 dark:bg-violet-500/10">
                    <span><span class="block text-[10px] font-semibold uppercase tracking-[0.14em] text-violet-700 dark:text-violet-300">Requests waiting</span><span class="mt-1 block text-sm font-semibold text-zinc-900 dark:text-white">{{ $content['incomingRequestCount'] }} customer request(s)</span></span>
                    <flux:icon name="chevron-right" class="size-5 text-violet-700 dark:text-violet-300" />
                </a>
            @else
                <div class="mt-3 flex min-h-14 items-center gap-3 rounded-2xl bg-zinc-50 px-3.5 py-3 dark:bg-white/[0.04]">
                    <flux:icon name="check-circle" class="size-5 text-emerald-600 dark:text-emerald-400" />
                    <span class="text-xs text-zinc-600 dark:text-zinc-300">No active assignments. Your next request will appear here.</span>
                </div>
            @endif

            <div class="mt-3 grid grid-cols-3 divide-x divide-zinc-200 rounded-2xl border border-zinc-200 bg-white py-2.5 dark:divide-white/10 dark:border-white/10 dark:bg-zinc-900">
                @foreach (array_slice($content['stats'] ?? [], 0, 3) as $stat)
                    <div class="min-w-0 px-2 text-center"><span class="block truncate text-[10px] text-zinc-500">{{ $stat['label'] }}</span><span class="mt-0.5 block truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $stat['value'] }}</span></div>
                @endforeach
            </div>
        </div>
    @else
        @if ($moduleSlug !== 'verification-profile')
            <header class="flex items-center gap-3 pt-1">
                <a href="{{ $technicianMobileBackUrl }}" wire:navigate class="flex min-h-11 min-w-11 items-center justify-center text-zinc-800 transition hover:text-violet-700 dark:text-white dark:hover:text-violet-300" aria-label="{{ $technicianMobileBackToProfile ? 'Back to profile' : 'Back to technician home' }}">
                    <flux:icon name="arrow-left" class="size-6" />
                </a>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-medium text-zinc-500">Technician workspace</p>
                    <h1 class="truncate text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ $module['label'] }}</h1>
                </div>
                <span class="shrink-0 rounded-full bg-zinc-100 px-2.5 py-1.5 text-[10px] font-semibold text-zinc-700 dark:bg-white/10 dark:text-zinc-200">{{ $moduleState['label'] }}</span>
            </header>
        @endif

        @if ($moduleSlug === 'job-requests')
            <div class="space-y-3">
                <input wire:model.live.debounce.300ms="requestSearch" type="search" placeholder="Search requests" class="h-12 w-full rounded-2xl border border-zinc-200 bg-white px-4 text-sm outline-none focus:border-violet-400 dark:border-white/10 dark:bg-zinc-900" />
                @forelse ($content['requests'] as $request)
                    <article class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-white/10 dark:bg-zinc-900">
                        <div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="truncate font-semibold text-zinc-900 dark:text-white">{{ $request->service?->name ?: \Illuminate\Support\Str::headline($request->service_type) }}</p><p class="mt-1 truncate text-sm text-zinc-500">{{ $request->address }}</p></div><span class="shrink-0 rounded-full bg-amber-100 px-2 py-1 text-[10px] font-semibold text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">{{ $technicianStatusLabel($request->status) }}</span></div>
                        <div class="mt-3 flex items-center justify-between gap-3 text-xs text-zinc-500"><span>{{ $request->customer?->name ?: 'Customer' }}</span><span>{{ $request->scheduled_at ? \Illuminate\Support\Carbon::parse($request->scheduled_at)->format('M d · g:i A') : 'As soon as possible' }}</span></div>
                        <div class="mt-3 flex gap-2"><button type="button" wire:click="requestConfirmation('accept-request', {{ $request->id }})" class="min-h-11 flex-1 rounded-xl bg-zinc-950 px-3 text-xs font-semibold text-white dark:bg-white dark:text-zinc-950">Accept</button><button type="button" wire:click="requestConfirmation('decline-request', {{ $request->id }})" class="min-h-11 rounded-xl border border-zinc-200 px-4 text-xs font-semibold text-zinc-700 dark:border-white/10 dark:text-zinc-200">Decline</button></div>
                    </article>
                @empty
                    <div class="technician-mobile-empty"><flux:icon name="queue-list" class="mx-auto size-9 text-zinc-400" /><p class="mt-3 font-semibold text-zinc-900 dark:text-white">No requests right now</p><p class="mt-1 text-sm text-zinc-500">New matching customer requests will appear here.</p></div>
                @endforelse
                <div>{{ $content['requests']->links() }}</div>
            </div>
        @elseif ($moduleSlug === 'my-jobs')
            <div class="flex gap-2"><input wire:model.live.debounce.300ms="jobSearch" type="search" placeholder="Search jobs" class="h-12 min-w-0 flex-1 rounded-2xl border border-zinc-200 bg-white px-4 text-sm outline-none focus:border-violet-400 dark:border-white/10 dark:bg-zinc-900" /><select wire:model.live="jobStatus" class="h-12 rounded-2xl border border-zinc-200 bg-white px-3 text-sm dark:border-white/10 dark:bg-zinc-900"><option value="all">All</option><option value="assigned">Assigned</option><option value="en_route">En route</option><option value="in_progress">In progress</option><option value="completed">Completed</option></select></div>
            <div class="space-y-3">
                @forelse ($content['jobs'] as $job)
                    @php($jobAction = $technicianAction($job->status))
                    <article wire:key="technician-mobile-job-{{ $job->id }}" class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-white/10 dark:bg-zinc-900"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="truncate font-semibold text-zinc-900 dark:text-white">{{ $job->service?->name ?: \Illuminate\Support\Str::headline($job->service_type) }}</p><p class="mt-1 truncate text-sm text-zinc-500">{{ $job->address }}</p></div><span class="shrink-0 rounded-full bg-violet-100 px-2 py-1 text-[10px] font-semibold text-violet-800 dark:bg-violet-500/15 dark:text-violet-300">{{ $technicianStatusLabel($job->status) }}</span></div><div class="mt-3 flex items-center justify-between gap-3 text-xs text-zinc-500"><span>{{ $job->reference }}</span><span>{{ $job->scheduled_at ? \Illuminate\Support\Carbon::parse($job->scheduled_at)->format('M d · g:i A') : 'Quick service' }}</span></div>@if ($jobAction && $jobAction['action'] === 'complete-job')<button type="button" data-app-technician-complete-swipe data-booking-id="{{ $job->id }}" class="technician-mobile-swipe mt-3" aria-label="Swipe right to complete job"><span data-app-technician-complete-swipe-fill></span><span data-app-technician-complete-swipe-label class="relative z-10">Swipe to complete</span><span data-app-technician-complete-swipe-thumb><flux:icon name="arrow-right" class="size-4" /></span></button>@elseif ($jobAction)<button type="button" wire:click="requestConfirmation('{{ $jobAction['action'] }}', {{ $job->id }})" class="mt-3 min-h-11 flex-1 rounded-xl bg-zinc-950 px-3 text-xs font-semibold text-white dark:bg-white dark:text-zinc-950">{{ $jobAction['label'] }}</button>@endif @if (in_array($job->status, \App\Models\Booking::ACTIVE_STATUSES, true))<button type="button" wire:click="requestConfirmation('cancel-job', {{ $job->id }})" class="mt-3 min-h-11 w-full rounded-xl bg-red-500 px-3 text-xs font-semibold text-white transition hover:bg-red-600 dark:bg-red-600 dark:hover:bg-red-500">Cancel job</button>@endif @if ($content['quotationsAvailable'] && in_array($job->status, ['assigned', 'en_route', 'in_progress'], true))<button type="button" wire:click="openQuotation({{ $job->id }})" class="mt-3 min-h-11 rounded-xl border border-zinc-200 px-3 text-xs font-semibold text-zinc-700 dark:border-white/10 dark:text-zinc-200">Quotation</button>@endif</article>
                @empty
                    <div class="technician-mobile-empty"><flux:icon name="clipboard-document-list" class="mx-auto size-9 text-zinc-400" /><p class="mt-3 font-semibold text-zinc-900 dark:text-white">No jobs match</p><p class="mt-1 text-sm text-zinc-500">Accepted assignments will appear in this list.</p></div>
                @endforelse
                <div>{{ $content['jobs']->links() }}</div>
            </div>
        @elseif ($moduleSlug === 'schedule')
            <div class="flex gap-2"><input wire:model.live.debounce.300ms="scheduleSearch" type="search" placeholder="Search schedule" class="h-12 min-w-0 flex-1 rounded-2xl border border-zinc-200 bg-white px-4 text-sm outline-none focus:border-violet-400 dark:border-white/10 dark:bg-zinc-900" /><select wire:model.live="scheduleRange" class="h-12 rounded-2xl border border-zinc-200 bg-white px-3 text-sm dark:border-white/10 dark:bg-zinc-900"><option value="upcoming">Upcoming</option><option value="today">Today</option><option value="completed">Completed</option><option value="all">All</option></select></div>
            <div class="space-y-3">@forelse ($content['schedule'] as $scheduled)<article class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-white/10 dark:bg-zinc-900"><div class="flex items-start justify-between gap-3"><div><p class="font-semibold text-zinc-900 dark:text-white">{{ $scheduled->scheduled_at ? \Illuminate\Support\Carbon::parse($scheduled->scheduled_at)->format('M d · g:i A') : 'Unscheduled' }}</p><p class="mt-1 text-sm text-zinc-500">{{ $scheduled->service?->name ?: \Illuminate\Support\Str::headline($scheduled->service_type) }}</p></div><span class="rounded-full bg-amber-100 px-2 py-1 text-[10px] font-semibold text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">{{ $technicianStatusLabel($scheduled->status) }}</span></div><p class="mt-3 truncate text-sm text-zinc-600 dark:text-zinc-300">{{ $scheduled->address }}</p><p class="mt-2 text-xs text-zinc-500">{{ $scheduled->customer?->name ?: 'Customer' }} · {{ $scheduled->reference }}</p></article>@empty<div class="technician-mobile-empty"><flux:icon name="calendar-days" class="mx-auto size-9 text-zinc-400" /><p class="mt-3 font-semibold text-zinc-900 dark:text-white">No appointments found</p><p class="mt-1 text-sm text-zinc-500">Your scheduled visits will appear here.</p></div>@endforelse<div>{{ $content['schedule']->links() }}</div></div>
        @elseif ($moduleSlug === 'dispatch-routes')
            <div class="technician-mobile-route-map technician-mobile-map relative overflow-hidden rounded-3xl" data-app-technician-mobile-map data-app-customer-mobile-map data-map-center-lat="{{ $technicianMapCenterLatitude }}" data-map-center-lng="{{ $technicianMapCenterLongitude }}" data-map-markers="{{ $technicianMapMarkers->toJson() }}"><div class="absolute inset-0" data-app-customer-map data-map-center-lat="{{ $technicianMapCenterLatitude }}" data-map-center-lng="{{ $technicianMapCenterLongitude }}" data-map-markers="{{ $technicianMapMarkers->toJson() }}" wire:ignore><div data-app-map-status class="pointer-events-none absolute inset-0 z-[1000] flex items-center justify-center bg-zinc-100/90 text-sm font-medium text-zinc-500 dark:bg-zinc-900/90 dark:text-zinc-400" role="status" aria-live="polite">Loading live map…</div></div><button type="button" data-app-map-locate class="absolute bottom-3 right-3 z-[1000] flex size-11 items-center justify-center rounded-full border border-zinc-200 bg-white text-zinc-800 shadow-lg dark:border-white/10 dark:bg-zinc-950 dark:text-white" aria-label="Center map on my location"><flux:icon name="map-pin" class="size-5" /></button></div>
            @if ($content['currentRoute'])
                @php($route = $content['currentRoute'])
                @php($wazeUrl = $route->latitude !== null && $route->longitude !== null ? 'https://waze.com/ul?ll='.$route->latitude.','.$route->longitude.'&navigate=yes' : 'https://waze.com/ul?q='.rawurlencode($route->address).'&navigate=yes')
                <article class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-white/10 dark:bg-zinc-900"><div class="flex items-start justify-between gap-3"><div><p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-violet-700 dark:text-violet-300">Current route</p><p class="mt-1 font-semibold text-zinc-900 dark:text-white">{{ $route->service?->name ?: \Illuminate\Support\Str::headline($route->service_type) }}</p><p class="mt-1 text-sm text-zinc-500">{{ $route->address }}</p></div><span class="rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-semibold text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">{{ $technicianStatusLabel($route->status) }}</span></div><a href="{{ $wazeUrl }}" target="_blank" rel="noopener noreferrer" class="mt-3 flex min-h-11 items-center justify-center rounded-xl bg-zinc-950 px-3 text-xs font-semibold text-white dark:bg-white dark:text-zinc-950">Navigate with Waze</a></article>
            @else
                <div class="technician-mobile-empty"><flux:icon name="map" class="mx-auto size-9 text-zinc-400" /><p class="mt-3 font-semibold text-zinc-900 dark:text-white">No active route</p><p class="mt-1 text-sm text-zinc-500">Accepted assignments will appear here.</p></div>
            @endif
            <div class="space-y-3"><div class="flex items-center justify-between"><h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Route assignments</h2><span class="text-xs text-zinc-500">{{ count($content['routeJobs']) }} active</span></div>@foreach ($content['routeJobs'] as $routeJob)<div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-white/10 dark:bg-zinc-900"><div class="flex items-center justify-between gap-3"><span class="font-semibold text-zinc-900 dark:text-white">{{ $routeJob->reference }}</span><span class="text-xs text-zinc-500">{{ $technicianStatusLabel($routeJob->status) }}</span></div><p class="mt-1 text-sm text-zinc-500">{{ $routeJob->address }}</p></div>@endforeach</div>
        @elseif ($moduleSlug === 'earnings')
            <div class="grid grid-cols-2 gap-2.5">@foreach ($content['stats'] as $stat)<div class="rounded-2xl border border-zinc-200 bg-white p-3 dark:border-white/10 dark:bg-zinc-900"><p class="text-xs text-zinc-500">{{ $stat['label'] }}</p><p class="mt-1 truncate text-lg font-semibold text-zinc-900 dark:text-white">{{ $stat['value'] }}</p></div>@endforeach</div><div class="space-y-3">@forelse ($content['payments'] as $payment)<div class="flex items-center justify-between gap-3 rounded-2xl border border-zinc-200 bg-white p-4 dark:border-white/10 dark:bg-zinc-900"><div><p class="font-semibold text-zinc-900 dark:text-white">{{ $payment->booking?->reference ?: 'Payment' }}</p><p class="mt-1 text-sm text-zinc-500">{{ $payment->booking?->service?->name ?: $payment->booking?->service_type }}</p></div><div class="text-right"><p class="font-semibold text-zinc-900 dark:text-white">₱{{ number_format((float) $payment->amount, 2) }}</p><p class="mt-1 text-xs text-zinc-500">{{ $technicianStatusLabel($payment->status) }}</p></div></div>@empty<div class="technician-mobile-empty"><flux:icon name="banknotes" class="mx-auto size-9 text-zinc-400" /><p class="mt-3 font-semibold text-zinc-900 dark:text-white">No payment records yet</p></div>@endforelse<div>{{ $content['payments']->links() }}</div></div>
        @elseif ($moduleSlug === 'ratings-reviews')
            <div class="grid grid-cols-3 gap-2.5">@foreach ($content['stats'] as $stat)<div class="rounded-2xl border border-zinc-200 bg-white p-3 text-center dark:border-white/10 dark:bg-zinc-900"><p class="text-[11px] text-zinc-500">{{ $stat['label'] }}</p><p class="mt-1 text-sm font-semibold text-zinc-900 dark:text-white">{{ $stat['value'] }}</p></div>@endforeach</div><div class="space-y-3">@forelse ($content['reviews'] as $review)<article class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-white/10 dark:bg-zinc-900"><div class="flex items-center justify-between gap-3"><span class="font-semibold text-amber-500">★ {{ $review->rating }} / 5</span><span class="text-xs text-zinc-500">{{ \Illuminate\Support\Carbon::parse($review->created_at)->format('M d, Y') }}</span></div><p class="mt-3 text-sm text-zinc-700 dark:text-zinc-200">{{ $review->comment ?: 'No written feedback.' }}</p><p class="mt-2 text-xs text-zinc-500">{{ $review->booking?->reference ?: 'Service booking' }}</p></article>@empty<div class="technician-mobile-empty"><flux:icon name="star" class="mx-auto size-9 text-zinc-400" /><p class="mt-3 font-semibold text-zinc-900 dark:text-white">No reviews yet</p></div>@endforelse<div>{{ $content['reviews']->links() }}</div></div>
        @elseif ($moduleSlug === 'verification-profile')
            @php($profile = $content['profile'])
            <x-profile-photo-editor :user="$technicianUser" :pending-photo="$profilePhoto" input-id="technician-profile-photo" />

            @foreach ([
                ['title' => 'My account', 'items' => [
                    ['slug' => 'schedule', 'label' => 'Schedule', 'icon' => 'calendar-days', 'from' => 'profile'],
                    ['slug' => 'earnings', 'label' => 'Earnings', 'icon' => 'banknotes', 'from' => 'profile'],
                    ['slug' => 'ratings-reviews', 'label' => 'Ratings & reviews', 'icon' => 'star', 'from' => 'profile'],
                ]],
                ['title' => 'General', 'items' => [
                    ['slug' => 'verification-profile', 'label' => 'Verification & Profile', 'icon' => 'user-circle'],
                    ['slug' => 'notifications', 'label' => 'Notifications', 'icon' => 'bell', 'from' => 'profile'],
                    ['slug' => 'support', 'label' => 'Support & Help', 'icon' => 'chat-bubble-left-right', 'from' => 'profile'],
                ]],
            ] as $section)
                <div wire:key="technician-profile-section-{{ \Illuminate\Support\Str::slug($section['title']) }}">
                    <flux:heading size="sm" class="mb-2 px-1 text-zinc-900 dark:text-white">{{ $section['title'] }}</flux:heading>
                    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-white/10 dark:bg-zinc-900">
                        @foreach ($section['items'] as $item)
                            <a
                                wire:key="technician-profile-module-{{ $item['slug'] }}"
                                href="{{ route('technician.module', ['module' => $item['slug'], 'from' => $item['from'] ?? null]) }}"
                                wire:navigate
                                data-app-technician-profile-module="{{ $item['slug'] }}"
                                class="flex min-h-14 items-center gap-3 border-b border-zinc-100 px-4 last:border-b-0 dark:border-white/10"
                            >
                                <flux:icon name="{{ $item['icon'] }}" class="size-5 text-zinc-500" />
                                <span class="flex-1 text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $item['label'] }}</span>
                                <flux:icon name="chevron-right" class="size-4 text-zinc-400" />
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach

            @if ($profile)
                <div wire:key="technician-profile-details">
                    <flux:heading size="sm" class="mb-2 px-1 text-zinc-900 dark:text-white">Verification details</flux:heading>
                    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-white/10 dark:bg-zinc-900">
                        @foreach ([
                            ['label' => 'Verification status', 'value' => $technicianStatusLabel($profile['status']), 'icon' => 'shield-check'],
                            ['label' => 'Service area', 'value' => $profile['service_area'] ?: 'Not provided', 'icon' => 'map-pin'],
                            ['label' => 'Experience', 'value' => $profile['years_experience'].' years', 'icon' => 'briefcase'],
                            ['label' => 'Phone', 'value' => $profile['phone'] ?: 'Not provided', 'icon' => 'phone'],
                            ['label' => 'Risk level', 'value' => $technicianStatusLabel($profile['risk_level']), 'icon' => 'shield-exclamation'],
                            ['label' => 'Service categories', 'value' => collect($profile['service_labels'] ?? $profile['service_categories'] ?? [])->join(', ') ?: 'No categories listed', 'icon' => 'wrench-screwdriver'],
                        ] as $detail)
                            <div wire:key="technician-profile-detail-{{ \Illuminate\Support\Str::slug($detail['label']) }}" class="flex min-h-14 items-center gap-3 border-b border-zinc-100 px-4 last:border-b-0 dark:border-white/10">
                                <flux:icon name="{{ $detail['icon'] }}" class="size-5 text-zinc-500" />
                                <span class="flex-1 text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $detail['label'] }}</span>
                                <span class="max-w-[55%] text-right text-sm text-zinc-500 dark:text-zinc-400">{{ $detail['value'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="rounded-2xl border border-dashed border-zinc-300 px-5 py-8 text-center dark:border-zinc-700">
                    <flux:icon name="identification" class="mx-auto size-9 text-zinc-400" />
                    <flux:heading size="lg" class="mt-4">Verification not submitted</flux:heading>
                    <flux:text class="mt-1 text-zinc-500">Your verification details will appear here once submitted.</flux:text>
                </div>
            @endif

            <form method="POST" action="{{ route('logout') }}" class="pt-2" data-app-technician-profile-logout>
                @csrf
                <button type="submit" class="flex min-h-12 w-full items-center justify-center rounded-xl border border-zinc-200 bg-white text-sm font-semibold text-zinc-700 dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-200">Log out</button>
            </form>
        @elseif ($moduleSlug === 'notifications')
            <div class="space-y-3">@forelse ($content['items'] as $item)<article class="flex items-start gap-3 rounded-2xl border border-zinc-200 bg-white p-4 dark:border-white/10 dark:bg-zinc-900"><span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-violet-50 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"><flux:icon name="bell" class="size-5" /></span><div><p class="font-semibold text-zinc-900 dark:text-white">{{ $item['title'] }}</p><p class="mt-1 text-sm text-zinc-500">{{ $item['detail'] }}</p></div></article>@empty<div class="technician-mobile-empty"><flux:icon name="bell" class="mx-auto size-9 text-zinc-400" /><p class="mt-3 font-semibold text-zinc-900 dark:text-white">No new updates</p><p class="mt-1 text-sm text-zinc-500">You are all caught up.</p></div>@endforelse</div>
        @elseif ($moduleSlug === 'support')
            <div class="space-y-3">@forelse ($content['tickets'] as $ticket)<article class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-white/10 dark:bg-zinc-900"><div class="flex items-start justify-between gap-3"><div><p class="font-semibold text-zinc-900 dark:text-white">{{ $ticket->subject }}</p><p class="mt-1 text-xs text-zinc-500">{{ $ticket->reference }}</p></div><span class="rounded-full bg-amber-100 px-2 py-1 text-[10px] font-semibold text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">{{ $technicianStatusLabel($ticket->status) }}</span></div><p class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">{{ $ticket->latest_message }}</p><p class="mt-2 text-xs text-zinc-500">Updated {{ \Illuminate\Support\Carbon::parse($ticket->updated_at)->format('M d, Y') }}</p></article>@empty<div class="technician-mobile-empty"><flux:icon name="chat-bubble-left-right" class="mx-auto size-9 text-zinc-400" /><p class="mt-3 font-semibold text-zinc-900 dark:text-white">No support requests</p><p class="mt-1 text-sm text-zinc-500">You are all caught up.</p></div>@endforelse<div>{{ $content['tickets']->links() }}</div></div>
        @endif
    @endif
</section>
