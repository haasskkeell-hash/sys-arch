@php
    $formatDate = static fn ($date): string => $date ? \Illuminate\Support\Carbon::parse($date)->format('M d, Y') : '—';
    $formatDateTime = static fn ($date): string => $date ? \Illuminate\Support\Carbon::parse($date)->format('M d, Y · g:i A') : '—';
    $serviceLabel = static fn (?string $service): string => $serviceCatalog->firstWhere('code', $service)?->name
        ?? \Illuminate\Support\Str::headline((string) $service);
    $paymentMethodLabel = static function (?string $method): string {
        return match (\Illuminate\Support\Str::lower((string) $method)) {
            'gcash' => 'GCash',
            'maya' => 'Maya',
            'cash' => 'Cash',
            'card', 'credit_card', 'debit_card' => 'Card',
            default => $method ? \Illuminate\Support\Str::headline($method) : 'Not recorded',
        };
    };
    $realtimeInterval = match ($moduleSlug) {
        'walk-in-queue' => 5000,
        'overview' => 1000,
        'my-bookings' => 10000,
        'payments' => 60000,
        default => null,
    };
    $realtimeScope = $realtimeInterval ? "customer-{$moduleSlug}" : null;
    $mobileTab = match ($moduleSlug) {
        'overview' => 'home',
        'my-bookings', 'walk-in-queue', 'quotations', 'payments' => 'activity',
        'notifications', 'support' => 'messages',
        'settings', 'ratings-reviews' => 'account',
        default => null,
    };
@endphp

<div
    class="w-full"
    @if ($realtimeScope)
        data-realtime-scope="{{ $realtimeScope }}"
        data-realtime-url="{{ route('realtime.snapshot', ['scope' => $realtimeScope]) }}"
        data-realtime-interval="{{ $realtimeInterval }}"
    @endif
>
    @if ($mobileTab)
        @include('livewire.customer.mobile-shell', ['tab' => $mobileTab])
    @endif

    <div class="{{ $mobileTab ? 'hidden lg:flex' : 'flex' }} w-full flex-col gap-6 p-6 lg:p-8">
        <div class="dashboard-reveal">
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('customer.module')" wire:navigate>Customer</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $module['label'] }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <flux:heading size="xl" level="1">{{ $module['label'] }}</flux:heading>
                <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">{{ $module['description'] }}</flux:text>
            </div>

            <div class="flex items-center gap-2">
                <flux:badge :color="$moduleState['color']" size="lg">{{ $moduleState['label'] }}</flux:badge>
                @if ($moduleSlug !== 'book-service')
                    <flux:button size="sm" variant="primary" icon="plus" wire:click="startBooking">Book a technician</flux:button>
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
        @php($activeBooking = $content['activeBooking'])
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(20rem,0.8fr)]">
            <flux:card class="dashboard-reveal shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <flux:heading size="lg">Current booking</flux:heading>
                        <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Follow the next step in your home service.</flux:text>
                    </div>
                    @if ($activeBooking)
                        <x-super-admin.table-cell :value="$activeBooking->status" type="status" />
                    @endif
                </div>

                @if ($activeBooking)
                    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(15rem,0.8fr)]">
                        <div class="space-y-5">
                            <div>
                                <flux:text class="text-xs uppercase tracking-wider text-zinc-500">{{ $activeBooking->reference }}</flux:text>
                                <flux:heading size="lg" class="mt-1">{{ $serviceLabel($activeBooking->service_type) }}</flux:heading>
                            </div>
                            <div class="grid gap-4 text-sm sm:grid-cols-2">
                                <div><flux:text class="text-zinc-500">Technician</flux:text><div class="mt-1">{{ $activeBooking->technician?->name ?: 'Matching in progress' }}</div></div>
                                <div><flux:text class="text-zinc-500">Booking type</flux:text><div class="mt-1">{{ \Illuminate\Support\Str::headline($activeBooking->booking_type) }}</div></div>
                                <div class="sm:col-span-2"><flux:text class="text-zinc-500">Service address</flux:text><div class="mt-1">{{ $activeBooking->address }}</div></div>
                                <div class="sm:col-span-2"><flux:text class="text-zinc-500">Schedule</flux:text><div class="mt-1">{{ $formatDateTime($activeBooking->scheduled_at) }}</div></div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <flux:button size="sm" variant="subtle" icon="eye" wire:click="openBooking({{ $activeBooking->id }})">View booking</flux:button>
                                @if (in_array($activeBooking->status, \App\Models\Booking::ACTIVE_STATUSES, true))
                                    <flux:button size="sm" variant="danger" icon="x-circle" wire:click="prepareCancellation({{ $activeBooking->id }})">Cancel booking</flux:button>
                                @endif
                            </div>
                        </div>

                        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-white/10 dark:bg-white/[0.03]">
                            <flux:text class="text-xs uppercase tracking-wider text-zinc-500">Service progress</flux:text>
                            <div class="mt-4 space-y-3 text-sm">
                                @foreach ([
                                    ['Confirmed', in_array($activeBooking->status, ['pending', 'matching', 'assigned', 'en_route', 'in_progress'], true)],
                                    ['Matching', in_array($activeBooking->status, ['matching', 'assigned', 'en_route', 'in_progress'], true)],
                                    ['On the way', in_array($activeBooking->status, ['en_route', 'in_progress'], true)],
                                    ['In progress', $activeBooking->status === 'in_progress'],
                                ] as [$step, $done])
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
                @else
                    <div class="mt-6 rounded-xl border border-dashed border-zinc-300 px-6 py-12 text-center dark:border-zinc-700">
                        <flux:icon name="wrench-screwdriver" class="mx-auto size-8 text-zinc-400" />
                        <flux:heading size="lg" class="mt-4">No active service</flux:heading>
                        <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Book a technician when your home needs a hand.</flux:text>
                        <flux:button class="mt-5" size="sm" variant="primary" icon="plus" wire:click="startBooking">Book a service</flux:button>
                    </div>
                @endif
            </flux:card>

            <flux:card class="dashboard-reveal shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <flux:heading size="lg">Next scheduled visit</flux:heading>
                        <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Your upcoming appointment at a glance.</flux:text>
                    </div>
                    <x-super-admin.section-menu :actions="[['label' => __('Refresh overview'), 'icon' => 'arrow-path', 'wire' => '$refresh']]" />
                </div>
                @if ($content['upcomingBookings']->isNotEmpty())
                    @php($upcoming = $content['upcomingBookings']->first())
                    <div class="mt-6 rounded-xl border border-zinc-200 p-4 dark:border-white/10">
                        <flux:text class="text-xs uppercase tracking-wider text-zinc-500">{{ $formatDate($upcoming->scheduled_at) }}</flux:text>
                        <flux:heading size="lg" class="mt-2">{{ $serviceLabel($upcoming->service_type) }}</flux:heading>
                        <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">{{ $upcoming->address }}</flux:text>
                        <div class="mt-4 flex items-center justify-between gap-3 text-sm"><span class="text-zinc-500">{{ $formatDateTime($upcoming->scheduled_at) }}</span><x-super-admin.table-cell :value="$upcoming->status" type="status" /></div>
                    </div>
                @else
                    <div class="mt-6 rounded-xl border border-dashed border-zinc-300 px-5 py-10 text-center dark:border-zinc-700">
                        <flux:icon name="calendar-days" class="mx-auto size-8 text-zinc-400" />
                        <flux:text class="mt-3 text-zinc-500">No scheduled visits yet.</flux:text>
                    </div>
                @endif
            </flux:card>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(18rem,0.75fr)]">
            <div class="dashboard-reveal">
                <div class="flex items-start justify-between gap-3">
                    <div><flux:heading size="lg">Recent bookings</flux:heading><flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">The latest requests moving through FixTrack.</flux:text></div>
                    <div class="flex items-center gap-2"><flux:badge color="zinc" size="sm">{{ $content['recentBookings']->count() }} shown</flux:badge><x-super-admin.section-menu :actions="[['label' => __('View all bookings'), 'icon' => 'arrow-up-right', 'href' => route('customer.module', ['module' => 'my-bookings'])]]" /></div>
                </div>
                <div class="app-table-shell mt-4 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-white/10 dark:bg-zinc-900" data-app-table-shell>
                    <flux:table class="w-full min-w-[680px]">
                        <flux:table.columns><flux:table.column>Booking</flux:table.column><flux:table.column>Service</flux:table.column><flux:table.column>Schedule</flux:table.column><flux:table.column>Status</flux:table.column><flux:table.column>Actions</flux:table.column></flux:table.columns>
                        <flux:table.rows>
                            @forelse ($content['recentBookings'] as $booking)
                                <flux:table.row :key="'recent-booking-'.$booking->id">
                                    <flux:table.cell variant="strong">{{ $booking->reference }}</flux:table.cell>
                                    <flux:table.cell>{{ $serviceLabel($booking->service_type) }}</flux:table.cell>
                                    <flux:table.cell class="whitespace-nowrap text-zinc-500">{{ $formatDate($booking->scheduled_at ?: $booking->created_at) }}</flux:table.cell>
                                    <flux:table.cell><x-super-admin.table-cell :value="$booking->status" type="status" /></flux:table.cell>
                                    <flux:table.cell>
                                        @php($recentBookingActions = [['label' => __('View details'), 'icon' => 'eye', 'wire' => 'openBooking('.$booking->id.')']])
                                        @if (in_array($booking->status, \App\Models\Booking::ACTIVE_STATUSES, true))
                                            @php($recentBookingActions[] = ['label' => __('Cancel booking'), 'icon' => 'x-circle', 'wire' => 'prepareCancellation('.$booking->id.')'])
                                        @endif
                                        <x-table-actions :actions="$recentBookingActions" />
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row><flux:table.cell colspan="5" class="py-12 text-center text-zinc-500">Your booking history will appear here.</flux:table.cell></flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>
            </div>

            <flux:card class="dashboard-reveal shadow-sm">
                <div class="flex items-start justify-between gap-3"><div><flux:heading size="lg">Popular services</flux:heading><flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Background-checked professionals, upfront quotations.</flux:text></div><x-super-admin.section-menu :actions="[['label' => __('Open booking form'), 'icon' => 'plus', 'wire' => 'startBooking']]" /></div>
                <div class="mt-5 space-y-3">
                    @foreach ($content['popularServices'] as $service)
                        <button type="button" wire:click="startBooking('{{ $service->code }}')" class="flex w-full items-center justify-between gap-3 rounded-xl border border-zinc-200 p-3 text-start transition hover:border-violet-300 hover:bg-violet-50/60 dark:border-white/10 dark:hover:border-violet-400/40 dark:hover:bg-violet-500/10">
                            <span class="flex items-center gap-3"><span class="flex size-9 items-center justify-center rounded-lg bg-violet-50 text-xs font-semibold text-violet-600 dark:bg-violet-500/10 dark:text-violet-300">{{ strtoupper(substr($service->code, 0, 2)) }}</span><span><span class="block font-medium">{{ $service->name }}</span><span class="block text-xs text-zinc-500">{{ $service->description }}</span></span></span>
                            <flux:icon name="chevron-right" class="size-4 text-zinc-400" />
                        </button>
                    @endforeach
                </div>
            </flux:card>
        </div>
    @elseif ($moduleSlug === 'book-service')
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(18rem,0.65fr)]">
            <flux:card class="dashboard-reveal shadow-sm">
                <div class="flex items-start justify-between gap-3"><div><flux:heading size="lg">Request a technician</flux:heading><flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Tell us what your home needs and we will move it into the service flow.</flux:text></div><x-super-admin.section-menu :actions="[['label' => __('Clear form'), 'icon' => 'arrow-path', 'wire' => '$refresh']]" /></div>
                <form class="mt-6 space-y-5" wire:submit="createBooking">
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ([['quick', 'Quick booking', 'Start matching now.'], ['scheduled', 'Schedule a visit', 'Choose a preferred time.']] as [$type, $label, $hint])
                            <button type="button" wire:click="$set('bookingType', '{{ $type }}')" class="rounded-xl border p-4 text-start transition {{ $bookingType === $type ? 'border-violet-400 bg-violet-50 dark:border-violet-400 dark:bg-violet-500/10' : 'border-zinc-200 dark:border-white/10' }}">
                                <div class="flex items-center justify-between gap-3"><span class="font-medium">{{ $label }}</span><span class="size-3 rounded-full {{ $bookingType === $type ? 'bg-violet-500' : 'border border-zinc-400' }}"></span></div><span class="mt-1 block text-sm text-zinc-500">{{ $hint }}</span>
                            </button>
                        @endforeach
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <div class="text-sm font-medium text-zinc-900 dark:text-white">Choose a service category</div>
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Pick a category first, then choose the exact service you need.</p>
                            @if ($serviceCategory === '')
                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    @foreach ($content['services']->groupBy('category') as $category => $services)
                                        <button type="button" wire:key="service-category-{{ $category }}" wire:click="chooseServiceCategory(@js($category))" class="min-h-20 rounded-xl border p-4 text-start transition {{ $serviceCategory === $category ? 'border-violet-400 bg-violet-50 dark:border-violet-400 dark:bg-violet-500/10' : 'border-zinc-200 hover:border-violet-300 hover:bg-violet-50/50 dark:border-white/10 dark:hover:border-violet-400/40 dark:hover:bg-violet-500/10' }}" aria-pressed="{{ $serviceCategory === $category ? 'true' : 'false' }}">
                                            <span class="block font-semibold text-zinc-900 dark:text-white">{{ $category }}</span>
                                            <span class="mt-1 block text-sm text-zinc-500">{{ $services->pluck('name')->join(', ') }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @else
                                <div class="mt-3 rounded-xl border border-violet-200 bg-violet-50/70 p-4 dark:border-violet-400/30 dark:bg-violet-500/10">
                                    <div class="flex items-center justify-between gap-3">
                                        <div><span class="block text-xs font-semibold uppercase tracking-wider text-violet-700 dark:text-violet-300">Category</span><span class="mt-1 block font-semibold text-zinc-900 dark:text-white">{{ $serviceCategory }}</span></div>
                                        <button type="button" wire:click="chooseServiceCategory('')" class="text-sm font-semibold text-violet-700 hover:text-violet-900 dark:text-violet-300 dark:hover:text-violet-100">Change category</button>
                                    </div>
                                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                        @foreach ($content['services']->where('category', $serviceCategory) as $service)
                                            <button type="button" wire:key="service-type-{{ $service->code }}" wire:click="chooseServiceType(@js($service->code))" class="min-h-16 rounded-xl border p-3 text-start transition {{ $serviceType === $service->code ? 'border-violet-500 bg-white ring-2 ring-violet-200 dark:border-violet-400 dark:bg-zinc-900 dark:ring-violet-500/20' : 'border-zinc-200 bg-white hover:border-violet-300 dark:border-white/10 dark:bg-zinc-900 dark:hover:border-violet-400/40' }}" aria-pressed="{{ $serviceType === $service->code ? 'true' : 'false' }}">
                                                <span class="block font-medium text-zinc-900 dark:text-white">{{ $service->name }}</span>
                                                <span class="mt-1 block text-xs text-zinc-500">{{ $service->description }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            @error('serviceType')<p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <flux:input wire:model="customerName" label="Your name" readonly required />
                        <div class="grid gap-3 sm:col-span-2 sm:grid-cols-[minmax(9rem,0.35fr)_minmax(0,1fr)]">
                            <flux:select wire:model="mobileCountryCode" label="Country code">
                                @foreach ($content['countryCallingCodes'] as $code => $label)<flux:select.option value="{{ $code }}">{{ $label }}</flux:select.option>@endforeach
                            </flux:select>
                            <flux:input wire:model="customerPhone" label="Mobile number" type="tel" placeholder="917 123 4567" required />
                        </div>
                        <div class="sm:col-span-2">
                            <div class="flex items-end gap-3">
                                <div class="min-w-0 flex-1"><flux:input wire:model="address" label="Service address" placeholder="Search your service location" required /></div>
                                <flux:button type="button" variant="subtle" icon="magnifying-glass" wire:click="searchAddress" wire:loading.attr="disabled" wire:target="searchAddress">Find on map</flux:button>
                            </div>
                            @if ($addressSuggestions !== [])
                                <div class="mt-2 overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm dark:border-white/10 dark:bg-zinc-900">
                                    @foreach ($addressSuggestions as $index => $suggestion)
                                        <button type="button" wire:key="address-suggestion-{{ $index }}" wire:click="selectAddress({{ $index }})" class="block w-full border-b border-zinc-100 px-3 py-2.5 text-start text-sm last:border-b-0 hover:bg-violet-50 dark:border-white/10 dark:hover:bg-violet-500/10">{{ $suggestion['label'] }}</button>
                                    @endforeach
                                </div>
                            @endif
                            <div
                                class="customer-desktop-booking-map relative mt-3 h-56 overflow-hidden rounded-xl border border-zinc-200 bg-zinc-100 dark:border-white/10 dark:bg-zinc-900"
                                data-app-customer-booking-map
                                data-map-center-lat="{{ $addressLatitude ?? 14.5995 }}"
                                data-map-center-lng="{{ $addressLongitude ?? 120.9842 }}"
                            >
                                <div
                                    class="absolute inset-0"
                                    data-app-customer-map
                                    data-map-center-lat="{{ $addressLatitude ?? 14.5995 }}"
                                    data-map-center-lng="{{ $addressLongitude ?? 120.9842 }}"
                                    data-map-markers="[]"
                                    data-map-draggable="true"
                                    data-map-auto-locate="{{ $bookingType === 'quick' ? 'true' : 'false' }}"
                                    data-map-watch-location="false"
                                    wire:ignore
                                >
                                    <div data-app-map-status class="pointer-events-none absolute inset-0 z-[1000] flex items-center justify-center bg-zinc-100/90 text-sm font-medium text-zinc-500 dark:bg-zinc-900/90 dark:text-zinc-400" role="status" aria-live="polite">Loading live map…</div>
                                </div>
                                <div class="pointer-events-none absolute inset-x-3 bottom-3 z-[1000]"><span class="inline-flex rounded-full bg-white/95 px-3 py-1.5 text-xs font-medium text-zinc-700 shadow-md dark:bg-zinc-950/95 dark:text-zinc-200">Drag the pin to fine-tune</span></div>
                                <button type="button" data-app-map-locate class="absolute right-3 top-3 z-[1000] flex size-10 items-center justify-center rounded-full border border-zinc-200 bg-white text-zinc-800 shadow-md dark:border-white/10 dark:bg-zinc-950 dark:text-white" aria-label="Use my current location"><flux:icon name="map-pin" class="size-4" /></button>
                                <input type="hidden" wire:model.live.number="addressLatitude" data-app-map-latitude />
                                <input type="hidden" wire:model.live.number="addressLongitude" data-app-map-longitude />
                            </div>
                        </div>
                        @if ($bookingType === 'scheduled')
                            <flux:input wire:model="scheduledAt" label="Preferred date and time" type="datetime-local" required />
                        @endif
                    </div>
                    <flux:textarea wire:model="description" label="What needs attention?" placeholder="Add details that will help the technician prepare." rows="4" />
                    <div class="flex flex-wrap items-center justify-between gap-3"><flux:text class="text-sm text-zinc-500">Your request will appear in My Bookings after submission.</flux:text><flux:button type="submit" variant="primary" icon="arrow-right" wire:loading.attr="disabled" wire:target="createBooking">Create booking</flux:button></div>
                </form>
            </flux:card>
            <flux:card class="dashboard-reveal shadow-sm"><flux:heading size="lg">How it works</flux:heading><flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">The customer flow from the blueprint, adapted to Livewire.</flux:text><div class="mt-6 space-y-4">@foreach (['Request a service', 'Technician matching', 'Assessment and quotation', 'Approval, payment, and rating'] as $step)<div class="flex gap-3"><span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-violet-100 text-sm font-medium text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">{{ $loop->iteration }}</span><div><div class="font-medium">{{ $step }}</div><flux:text class="mt-0.5 text-sm text-zinc-500">Your status and next action stay visible in the dashboard.</flux:text></div></div>@endforeach</div></flux:card>
        </div>
    @elseif ($moduleSlug === 'my-bookings')
        <div class="dashboard-reveal">
            <div class="mb-4"><flux:heading size="lg">Booking history</flux:heading><flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Review current requests and completed services.</flux:text></div>
            <div class="flex flex-wrap items-end justify-between gap-4"><div class="flex flex-1 flex-wrap gap-3"><flux:input wire:model.live.debounce.300ms="bookingSearch" placeholder="Search bookings" icon="magnifying-glass" class="w-full sm:w-72" /><flux:select wire:model.live="bookingStatus" placeholder="All statuses" class="w-44"><flux:select.option value="all">All statuses</flux:select.option><flux:select.option value="pending">Pending</flux:select.option><flux:select.option value="matching">Matching</flux:select.option><flux:select.option value="assigned">Assigned</flux:select.option><flux:select.option value="en_route">En route</flux:select.option><flux:select.option value="in_progress">In progress</flux:select.option><flux:select.option value="completed">Completed</flux:select.option><flux:select.option value="cancelled">Cancelled</flux:select.option><flux:select.option value="no_show">No show</flux:select.option></flux:select></div><x-super-admin.section-menu :actions="[['label' => __('Refresh bookings'), 'icon' => 'arrow-path', 'wire' => '$refresh'], ['label' => __('Book a service'), 'icon' => 'plus', 'wire' => 'startBooking']]" /></div>
            <div class="app-table-shell mt-4 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-white/10 dark:bg-zinc-900" data-app-table-shell><flux:table class="w-full min-w-[880px]"><flux:table.columns><flux:table.column>Booking</flux:table.column><flux:table.column>Service</flux:table.column><flux:table.column>Schedule</flux:table.column><flux:table.column>Address</flux:table.column><flux:table.column>Technician</flux:table.column><flux:table.column>Status</flux:table.column><flux:table.column>Actions</flux:table.column></flux:table.columns><flux:table.rows>@forelse ($content['bookings'] as $booking)<flux:table.row :key="'booking-'.$booking->id"><flux:table.cell variant="strong">{{ $booking->reference }}</flux:table.cell><flux:table.cell>{{ $serviceLabel($booking->service_type) }}</flux:table.cell><flux:table.cell class="whitespace-nowrap text-zinc-500">{{ $formatDateTime($booking->scheduled_at ?: $booking->created_at) }}</flux:table.cell><flux:table.cell class="max-w-xs truncate">{{ $booking->address }}</flux:table.cell><flux:table.cell>{{ $booking->technician?->name ?: 'Unassigned' }}</flux:table.cell><flux:table.cell><x-super-admin.table-cell :value="$booking->status" type="status" /></flux:table.cell><flux:table.cell>@php($bookingActions = [['label' => __('View details'), 'icon' => 'eye', 'wire' => 'openBooking('.$booking->id.')']])@if (in_array($booking->status, \App\Models\Booking::ACTIVE_STATUSES, true))@php($bookingActions[] = ['label' => __('Cancel booking'), 'icon' => 'x-circle', 'wire' => 'prepareCancellation('.$booking->id.')'])@endif<x-table-actions :actions="$bookingActions" /></flux:table.cell></flux:table.row>@empty<flux:table.row><flux:table.cell colspan="7" class="py-14 text-center text-zinc-500">No bookings match your search.</flux:table.cell></flux:table.row>@endforelse</flux:table.rows></flux:table></flux:table><div class="border-t border-zinc-200 px-4 py-3 dark:border-white/10">{{ $content['bookings']->links() }}</div></div>
        </div>
    @elseif ($moduleSlug === 'walk-in-queue')
        <div class="grid gap-6 xl:grid-cols-[minmax(0,0.7fr)_minmax(0,1.3fr)]">
            @if ($content['available'])
                <flux:card class="dashboard-reveal shadow-sm">
                    <div class="flex items-start justify-between gap-3"><div><flux:heading size="lg">Join the queue</flux:heading><flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Check in for an in-person service visit.</flux:text></div><flux:icon name="queue-list" class="size-5 text-amber-500" /></div>
                    <form class="mt-6 space-y-4" wire:submit="joinWalkInQueue">
                        <flux:select wire:model="walkInServiceType" label="Service type" placeholder="Choose a service">
                            @foreach ($content['services'] as $service)
                                <flux:select.option value="{{ $service->code }}">{{ $service->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model="walkInPhone" label="Mobile number" type="tel" required />
                        <flux:textarea wire:model="walkInNotes" label="Notes" rows="4" placeholder="Add anything the service counter should know." />
                        <div class="flex justify-end"><flux:button type="submit" variant="primary" icon="queue-list">Join queue</flux:button></div>
                    </form>
                </flux:card>
            @else
                <flux:card class="dashboard-reveal shadow-sm">
                    <div class="rounded-xl border border-dashed border-amber-300 bg-amber-50 px-6 py-12 text-center dark:border-amber-400/30 dark:bg-amber-500/10">
                        <flux:icon name="queue-list" class="mx-auto size-9 text-amber-500" />
                        <flux:heading size="lg" class="mt-4">Walk-in queue is currently unavailable</flux:heading>
                        <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">{{ $content['message'] }}</flux:text>
                    </div>
                </flux:card>
            @endif
            <div class="dashboard-reveal">
                <div class="flex items-start justify-between gap-3"><div><flux:heading size="lg">My queue entries</flux:heading><flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Track your check-in and service progress.</flux:text></div><flux:badge color="zinc" size="sm">{{ $content['entries']->total() }} total</flux:badge></div>
                <div class="app-table-shell mt-4 overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-white/10 dark:bg-zinc-900" data-app-table-shell><flux:table class="w-full min-w-[650px]"><flux:table.columns><flux:table.column>Queue</flux:table.column><flux:table.column>Service</flux:table.column><flux:table.column>Checked in</flux:table.column><flux:table.column>Status</flux:table.column></flux:table.columns><flux:table.rows>@forelse ($content['entries'] as $entry)<flux:table.row :key="'walk-in-'.$entry->id"><flux:table.cell variant="strong">{{ $entry->queue_number }}</flux:table.cell><flux:table.cell>{{ $serviceLabel($entry->service_type) }}</flux:table.cell><flux:table.cell class="whitespace-nowrap text-zinc-500">{{ $formatDateTime($entry->checked_in_at) }}</flux:table.cell><flux:table.cell><x-super-admin.table-cell :value="$entry->status" type="status" /></flux:table.cell></flux:table.row>@empty<flux:table.row><flux:table.cell colspan="4" class="py-14 text-center text-zinc-500">You have no walk-in queue entries yet.</flux:table.cell></flux:table.row>@endforelse</flux:table.rows></flux:table></div>
                <div class="mt-4">{{ $content['entries']->links() }}</div>
            </div>
        </div>
    @elseif ($moduleSlug === 'quotations')
        <div class="dashboard-reveal">
            <div class="flex items-start justify-between gap-3"><div><flux:heading size="lg">Quotation history</flux:heading><flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Review technician assessments and approve the proposed work before service continues.</flux:text></div><x-super-admin.section-menu :actions="[['label' => __('View bookings'), 'icon' => 'clipboard-document-list', 'href' => route('customer.module', ['module' => 'my-bookings'])]]" /></div>
            @if ($content['available'])
                <div class="app-table-shell mt-4 overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-white/10 dark:bg-zinc-900" data-app-table-shell><flux:table class="w-full min-w-[940px]"><flux:table.columns><flux:table.column>Booking</flux:table.column><flux:table.column>Service</flux:table.column><flux:table.column>Technician</flux:table.column><flux:table.column>Assessment</flux:table.column><flux:table.column>Total</flux:table.column><flux:table.column>Status</flux:table.column><flux:table.column>Actions</flux:table.column></flux:table.columns><flux:table.rows>@forelse ($content['quotations'] as $quotation)<flux:table.row :key="'quotation-'.$quotation->id"><flux:table.cell variant="strong">{{ $quotation->booking?->reference ?: '—' }}</flux:table.cell><flux:table.cell>{{ $quotation->booking?->service?->name ?: \Illuminate\Support\Str::headline($quotation->booking?->service_type) }}</flux:table.cell><flux:table.cell>{{ $quotation->technician?->name ?: '—' }}</flux:table.cell><flux:table.cell class="max-w-xs whitespace-normal text-zinc-500">{{ $quotation->assessment_notes }}</flux:table.cell><flux:table.cell class="whitespace-nowrap tabular-nums">₱{{ number_format((float) $quotation->total_amount, 2) }}</flux:table.cell><flux:table.cell><x-super-admin.table-cell :value="$quotation->status" type="status" /></flux:table.cell><flux:table.cell>@if ($quotation->status === 'awaiting_approval')<div class="flex gap-2"><flux:button size="sm" variant="primary" wire:click="respondToQuotation({{ $quotation->id }}, 'approved')">Approve</flux:button><flux:button size="sm" variant="subtle" wire:click="respondToQuotation({{ $quotation->id }}, 'rejected')">Reject</flux:button></div>@else<flux:text class="text-xs text-zinc-500">Answered</flux:text>@endif</flux:table.cell></flux:table.row>@empty<flux:table.row><flux:table.cell colspan="7" class="py-14 text-center text-zinc-500">No quotations to review yet. They will appear after a technician assessment.</flux:table.cell></flux:table.row>@endforelse</flux:table.rows></flux:table></flux:table><div class="border-t border-zinc-200 px-4 py-3 dark:border-white/10">{{ $content['quotations']->links() }}</div></div>
            @else
                <div class="mt-8 rounded-xl border border-dashed border-zinc-300 px-6 py-14 text-center dark:border-zinc-700"><flux:icon name="document-text" class="mx-auto size-9 text-zinc-400" /><flux:heading size="lg" class="mt-4">No quotations to review</flux:heading><flux:text class="mx-auto mt-1 max-w-lg text-zinc-500">{{ $content['message'] }}</flux:text></div>
            @endif
        </div>
    @elseif ($moduleSlug === 'payments')
        <div class="dashboard-reveal">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <flux:heading size="lg">Payment history</flux:heading>
                    <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Receipts and payment states connected to your FixTrack bookings.</flux:text>
                </div>
                <x-super-admin.section-menu :actions="[['label' => __('Refresh payments'), 'icon' => 'arrow-path', 'wire' => '$refresh']]" />
            </div>

            <div class="app-table-shell mt-4 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-white/10 dark:bg-zinc-900" data-app-table-shell>
                <flux:table class="w-full min-w-[880px]">
                    <flux:table.columns>
                        <flux:table.column>Booking</flux:table.column>
                        <flux:table.column>Service</flux:table.column>
                        <flux:table.column>Amount</flux:table.column>
                        <flux:table.column>Method</flux:table.column>
                        <flux:table.column>Paid at</flux:table.column>
                        <flux:table.column>Status</flux:table.column>
                        <flux:table.column align="end">Actions</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($content['payments'] as $payment)
                            <flux:table.row :key="'payment-'.$payment->id">
                                <flux:table.cell variant="strong">{{ $payment->booking?->reference ?: '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $serviceLabel($payment->booking?->service_type) }}</flux:table.cell>
                                <flux:table.cell class="tabular-nums">₱{{ number_format((float) $payment->amount, 2) }}</flux:table.cell>
                                <flux:table.cell>{{ $paymentMethodLabel($payment->method) }}</flux:table.cell>
                                <flux:table.cell class="whitespace-nowrap text-zinc-500">{{ $formatDateTime($payment->paid_at ?: $payment->created_at) }}</flux:table.cell>
                                <flux:table.cell><x-super-admin.table-cell :value="$payment->status" type="status" /></flux:table.cell>
                                <flux:table.cell align="end">
                                    <flux:button size="sm" variant="subtle" icon="eye" wire:click="openPayment({{ $payment->id }})">View details</flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="7" class="py-14 text-center text-zinc-500">Payment records will appear here after a booking is billed.</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
                <div class="border-t border-zinc-200 px-4 py-3 dark:border-white/10">{{ $content['payments']->links() }}</div>
            </div>
        </div>
    @elseif ($moduleSlug === 'ratings-reviews')
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(19rem,0.7fr)]"><div class="dashboard-reveal"><div class="flex items-start justify-between gap-3"><div><flux:heading size="lg">Your reviews</flux:heading><flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Feedback helps technicians and the FixTrack service improve.</flux:text></div><x-super-admin.section-menu :actions="[['label' => __('Refresh reviews'), 'icon' => 'arrow-path', 'wire' => '$refresh']]" /></div><div class="app-table-shell mt-4 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-white/10 dark:bg-zinc-900" data-app-table-shell><flux:table class="w-full min-w-[720px]"><flux:table.columns><flux:table.column>Booking</flux:table.column><flux:table.column>Technician</flux:table.column><flux:table.column>Rating</flux:table.column><flux:table.column>Review</flux:table.column><flux:table.column>Status</flux:table.column></flux:table.columns><flux:table.rows>@forelse ($content['reviews'] as $review)<flux:table.row :key="'review-'.$review->id"><flux:table.cell variant="strong">{{ $review->booking?->reference ?: '—' }}</flux:table.cell><flux:table.cell>{{ $review->technician?->name ?: '—' }}</flux:table.cell><flux:table.cell><flux:badge color="amber" size="sm">★ {{ $review->rating }} / 5</flux:badge></flux:table.cell><flux:table.cell class="max-w-sm truncate">{{ $review->comment ?: 'No written feedback.' }}</flux:table.cell><flux:table.cell><x-super-admin.table-cell :value="$review->status" type="status" /></flux:table.cell></flux:table.row>@empty<flux:table.row><flux:table.cell colspan="5" class="py-14 text-center text-zinc-500">No reviews submitted yet.</flux:table.cell></flux:table.row>@endforelse</flux:table.rows></flux:table></flux:table><div class="border-t border-zinc-200 px-4 py-3 dark:border-white/10">{{ $content['reviews']->links() }}</div></div></div><flux:card class="dashboard-reveal shadow-sm"><div class="flex items-start justify-between gap-3"><div><flux:heading size="lg">Leave feedback</flux:heading><flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Review completed services when you are ready.</flux:text></div><flux:icon name="star" class="size-5 text-amber-500" /></div><div class="mt-5 space-y-3">@forelse ($content['reviewableBookings'] as $booking)<div class="rounded-xl border border-zinc-200 p-3 dark:border-white/10"><div class="flex items-center justify-between gap-3"><div><div class="font-medium">{{ $serviceLabel($booking->service_type) }}</div><flux:text class="text-xs text-zinc-500">{{ $booking->reference }}</flux:text></div><flux:button size="sm" variant="primary" wire:click="startReview({{ $booking->id }})">Review</flux:button></div></div>@empty<flux:text class="text-sm text-zinc-500">Completed bookings ready for feedback will appear here.</flux:text>@endforelse</div>@if ($reviewBookingId)<form class="mt-5 space-y-4 border-t border-zinc-200 pt-5 dark:border-white/10" wire:submit="createReview"><flux:select wire:model="reviewRating" label="Rating" placeholder="Choose a rating"><flux:select.option value="1">1 / 5</flux:select.option><flux:select.option value="2">2 / 5</flux:select.option><flux:select.option value="3">3 / 5</flux:select.option><flux:select.option value="4">4 / 5</flux:select.option><flux:select.option value="5">5 / 5</flux:select.option></flux:select><flux:textarea wire:model="reviewComment" label="Comment" rows="3" placeholder="Tell us about the service." /><div class="flex justify-end"><flux:button type="submit" variant="primary">Submit review</flux:button></div></form>@endif</flux:card></div>
    @elseif ($moduleSlug === 'notifications')
        <div class="dashboard-reveal"><div class="flex items-start justify-between gap-3"><div><flux:heading size="lg">Latest updates</flux:heading><flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Booking and support activity collected for your account.</flux:text></div><x-super-admin.section-menu :actions="[['label' => __('Refresh updates'), 'icon' => 'arrow-path', 'wire' => '$refresh']]" /></div><div class="mt-4 grid gap-3">@forelse ($content['items'] as $item)<div class="flex items-start gap-4 rounded-xl border border-zinc-200 bg-white p-4 dark:border-white/10 dark:bg-zinc-900"><div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-300"><flux:icon name="bell" class="size-5" /></div><div class="min-w-0 flex-1"><div class="flex flex-wrap items-center justify-between gap-2"><flux:heading size="sm">{{ $item['title'] }}</flux:heading><x-super-admin.table-cell :value="$item['status']" type="status" /></div><flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">{{ $item['detail'] }}</flux:text><flux:text class="mt-2 text-xs text-zinc-400">{{ $formatDateTime($item['date']) }}</flux:text></div></div>@empty<div class="rounded-xl border border-dashed border-zinc-300 px-6 py-14 text-center dark:border-zinc-700"><flux:icon name="bell" class="mx-auto size-8 text-zinc-400" /><flux:heading size="lg" class="mt-4">No new updates</flux:heading><flux:text class="mt-1 text-zinc-500">You are all caught up.</flux:text></div>@endforelse</div></div>
    @elseif ($moduleSlug === 'support')
        <div class="grid gap-6 xl:grid-cols-[minmax(0,0.7fr)_minmax(0,1.3fr)]"><flux:card class="dashboard-reveal shadow-sm"><div class="flex items-start justify-between gap-3"><div><flux:heading size="lg">Contact support</flux:heading><flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Send a request and keep the conversation tied to your account.</flux:text></div><flux:icon name="chat-bubble-left-right" class="size-5 text-violet-500" /></div><form class="mt-6 space-y-4" wire:submit="createSupportTicket"><flux:input wire:model="supportSubject" label="Subject" required /><div class="grid gap-4 sm:grid-cols-2"><flux:select wire:model="supportCategory" label="Category"><flux:select.option value="booking">Booking</flux:select.option><flux:select.option value="payment">Payment</flux:select.option><flux:select.option value="technical">Technical</flux:select.option><flux:select.option value="account">Account</flux:select.option><flux:select.option value="other">Other</flux:select.option></flux:select><flux:select wire:model="supportPriority" label="Priority"><flux:select.option value="low">Low</flux:select.option><flux:select.option value="normal">Normal</flux:select.option><flux:select.option value="high">High</flux:select.option></flux:select></div><flux:textarea wire:model="supportMessage" label="Message" rows="5" required /><div class="flex justify-end"><flux:button type="submit" variant="primary" icon="paper-airplane">Send request</flux:button></div></form></flux:card><div class="dashboard-reveal"><div class="flex items-start justify-between gap-3"><div><flux:heading size="lg">Your support requests</flux:heading><flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Track questions about bookings, payments, or your account.</flux:text></div><x-super-admin.section-menu :actions="[['label' => __('Refresh requests'), 'icon' => 'arrow-path', 'wire' => '$refresh']]" /></div><div class="app-table-shell mt-4 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-white/10 dark:bg-zinc-900" data-app-table-shell><flux:table class="w-full min-w-[720px]"><flux:table.columns><flux:table.column>Reference</flux:table.column><flux:table.column>Subject</flux:table.column><flux:table.column>Category</flux:table.column><flux:table.column>Priority</flux:table.column><flux:table.column>Updated</flux:table.column><flux:table.column>Status</flux:table.column></flux:table.columns><flux:table.rows>@forelse ($content['tickets'] as $ticket)<flux:table.row :key="'ticket-'.$ticket->id"><flux:table.cell variant="strong">{{ $ticket->reference }}</flux:table.cell><flux:table.cell>{{ $ticket->subject }}</flux:table.cell><flux:table.cell><x-super-admin.table-cell :value="$ticket->category" type="category" /></flux:table.cell><flux:table.cell><x-super-admin.table-cell :value="$ticket->priority" type="priority" /></flux:table.cell><flux:table.cell class="whitespace-nowrap text-zinc-500">{{ $formatDateTime($ticket->updated_at) }}</flux:table.cell><flux:table.cell><x-super-admin.table-cell :value="$ticket->status" type="status" /></flux:table.cell></flux:table.row>@empty<flux:table.row><flux:table.cell colspan="6" class="py-14 text-center text-zinc-500">No support requests yet.</flux:table.cell></flux:table.row>@endforelse</flux:table.rows></flux:table></div><div class="mt-4">{{ $content['tickets']->links() }}</div></div></div>
    @elseif ($moduleSlug === 'settings')
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(18rem,0.7fr)]"><flux:card class="dashboard-reveal shadow-sm"><div class="flex items-start gap-4"><div class="flex size-12 items-center justify-center rounded-xl bg-zinc-100 text-zinc-700 dark:bg-white/10 dark:text-zinc-200">{{ $content['user']->initials() }}</div><div><flux:heading size="lg">{{ $content['user']->name }}</flux:heading><flux:text class="mt-1 text-zinc-500">{{ $content['user']->email }}</flux:text><div class="mt-3"><x-super-admin.table-cell :value="$content['user']->email_verified_at ? 'Verified' : 'Pending'" type="status" /></div></div></div><div class="mt-8 grid gap-3 sm:grid-cols-3"><flux:button variant="primary" href="{{ route('profile.edit') }}" wire:navigate>Profile</flux:button><flux:button variant="subtle" href="{{ route('security.edit') }}" wire:navigate>Security</flux:button><flux:button variant="subtle" href="{{ route('appearance.edit') }}" wire:navigate>Appearance</flux:button></div></flux:card><flux:card class="dashboard-reveal shadow-sm"><flux:heading size="lg">Account preferences</flux:heading><flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Use the settings pages to update your profile and sign-in protection.</flux:text><div class="mt-6 space-y-3 text-sm"><div class="flex items-center justify-between gap-3 border-b border-zinc-200 pb-3 dark:border-white/10"><span class="text-zinc-500">Role</span><x-super-admin.table-cell :value="ucfirst($content['user']->role)" type="role" /></div><div class="flex items-center justify-between gap-3"><span class="text-zinc-500">Member since</span><span>{{ $formatDate($content['user']->created_at) }}</span></div></div></flux:card></div>
    @endif

    </div>

    <flux:modal name="customer-booking-details" class="max-w-lg" @close="closeBooking" wire:model="showBookingDetails">
        <div class="space-y-6">
            @if ($selectedBooking)
                <div class="space-y-2"><div class="flex items-center justify-between gap-3"><flux:heading size="lg">{{ $serviceLabel($selectedBooking->service_type) }}</flux:heading><x-super-admin.table-cell :value="$selectedBooking->status" type="status" /></div><flux:text class="text-zinc-500">{{ $selectedBooking->reference }}</flux:text></div>
                <div class="grid gap-4 text-sm sm:grid-cols-2"><div><flux:text class="text-zinc-500">Address</flux:text><div class="mt-1">{{ $selectedBooking->address }}</div></div><div><flux:text class="text-zinc-500">Mobile number</flux:text><div class="mt-1">{{ $selectedBooking->customer_phone ?: 'Not provided' }}</div></div><div><flux:text class="text-zinc-500">Schedule</flux:text><div class="mt-1">{{ $formatDateTime($selectedBooking->scheduled_at ?: $selectedBooking->created_at) }}</div></div><div><flux:text class="text-zinc-500">Booking type</flux:text><div class="mt-1">{{ \Illuminate\Support\Str::headline($selectedBooking->booking_type) }}</div></div><div><flux:text class="text-zinc-500">Created</flux:text><div class="mt-1">{{ $formatDate($selectedBooking->created_at) }}</div></div><div class="sm:col-span-2"><flux:text class="text-zinc-500">Description</flux:text><div class="mt-1">{{ $selectedBooking->description ?: 'No additional notes.' }}</div></div></div>
            @endif
            <div class="flex justify-end gap-2">
                @if ($selectedBooking && in_array($selectedBooking->status, \App\Models\Booking::ACTIVE_STATUSES, true))
                    <flux:button variant="danger" icon="x-circle" wire:click="prepareCancellation({{ $selectedBooking->id }})">Cancel booking</flux:button>
                @endif
                <flux:button variant="subtle" wire:click="closeBooking">Close</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="customer-payment-details" class="max-w-md" @close="closePaymentDetails" wire:model="showPaymentDetails">
        <div class="space-y-6">
            @if ($selectedPayment)
                <div class="space-y-2">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <flux:heading size="lg">Payment details</flux:heading>
                            <flux:text class="mt-1 text-zinc-500">{{ $selectedPayment->booking?->reference ?: 'Payment record' }}</flux:text>
                        </div>
                        <x-super-admin.table-cell :value="$selectedPayment->status" type="status" />
                    </div>
                </div>
                <div class="grid gap-4 text-sm sm:grid-cols-2">
                    <div><flux:text class="text-zinc-500">Service</flux:text><div class="mt-1">{{ $serviceLabel($selectedPayment->booking?->service_type) }}</div></div>
                    <div><flux:text class="text-zinc-500">Amount</flux:text><div class="mt-1 font-semibold tabular-nums">₱{{ number_format((float) $selectedPayment->amount, 2) }}</div></div>
                    <div><flux:text class="text-zinc-500">Payment method</flux:text><div class="mt-1">{{ $paymentMethodLabel($selectedPayment->method) }}</div></div>
                    <div><flux:text class="text-zinc-500">Paid at</flux:text><div class="mt-1">{{ $formatDateTime($selectedPayment->paid_at ?: $selectedPayment->created_at) }}</div></div>
                    <div class="sm:col-span-2"><flux:text class="text-zinc-500">Transaction reference</flux:text><div class="mt-1 break-all font-mono text-xs">{{ $selectedPayment->transaction_ref ?: 'Not available' }}</div></div>
                </div>
            @endif
            <div class="flex justify-end">
                <flux:button variant="subtle" wire:click="closePaymentDetails">Close</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="customer-booking-cancellation" class="max-w-md" @close="closeCancellation" wire:model="showCancellation">
        <form class="space-y-6" wire:submit="cancelBooking">
            <div class="space-y-2"><flux:heading size="lg">Cancel booking?</flux:heading><flux:text>This will release the technician assignment. Please tell us why you are cancelling.</flux:text></div>
            <flux:textarea wire:model="cancellationReason" label="Reason" rows="4" required />
            <div class="flex justify-end gap-3"><flux:button type="button" variant="outline" wire:click="closeCancellation">Keep booking</flux:button><flux:button type="submit" variant="danger" wire:loading.attr="disabled" wire:target="cancelBooking">Confirm cancellation</flux:button></div>
        </form>
    </flux:modal>
</div>
