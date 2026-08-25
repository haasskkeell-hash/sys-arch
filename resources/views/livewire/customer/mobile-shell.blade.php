@php
    $mobileUser = auth()->user();
    $mobileBookings = $content['bookings'] ?? collect();
    $mobileQuotations = $content['quotations'] ?? collect();
    $mobilePayments = $content['payments'] ?? collect();
    $mobileUpdates = $content['items'] ?? collect();
    $mobileTickets = $content['tickets'] ?? collect();
    $mobileActiveBooking = $content['activeBooking'] ?? null;
    $mobileTechnicianStores = $technicianStores ?? collect();
    $selectedMobileTechnicianStore = $mobileTechnicianStores->firstWhere('id', $selectedTechnicianId);
    $mobileRecentLocations = collect($content['recentBookings'] ?? [])
        ->filter(fn ($booking): bool => filled($booking->address))
        ->unique(fn ($booking): string => (string) $booking->address)
        ->values()
        ->take(4);
    $mobileSuggestedLocations = collect($content['upcomingBookings'] ?? [])
        ->filter(fn ($booking): bool => filled($booking->address))
        ->unique(fn ($booking): string => (string) $booking->address)
        ->values()
        ->take(4);
    $mobileMapMarkers = collect($content['recentBookings'] ?? [])
        ->flatMap(function ($booking) use ($mobileActiveBooking, $mobileUser, $serviceLabel): array {
            $markers = [];

            if ($booking->latitude !== null && $booking->longitude !== null) {
                $markers[] = [
                    'type' => $mobileActiveBooking?->id === $booking->id ? 'active' : 'booking',
                    'title' => $serviceLabel($booking->service_type),
                    'address' => $booking->address,
                    'avatar' => $mobileUser->avatarUrl(),
                    'initials' => $mobileUser->initials(),
                    'latitude' => (float) $booking->latitude,
                    'longitude' => (float) $booking->longitude,
                ];
            }

            if ($booking->technician?->latitude !== null && $booking->technician?->longitude !== null) {
                $markers[] = [
                    'type' => 'technician',
                    'title' => $booking->technician->name,
                    'address' => $booking->technician->name,
                    'avatar' => $booking->technician->avatarUrl(),
                    'initials' => $booking->technician->initials(),
                    'technician_id' => $booking->technician->id,
                    'latitude' => (float) $booking->technician->latitude,
                    'longitude' => (float) $booking->technician->longitude,
                ];
            }

            return $markers;
        })
        ->unique(fn (array $marker): string => $marker['type'] === 'technician'
            ? 'technician|'.($marker['technician_id'] ?? $marker['address'])
            : $marker['type'].'|'.$marker['address'].'|'.$marker['latitude'].'|'.$marker['longitude'])
        ->values();

    $mobileMapCenterLatitude = is_numeric($mobileUser->latitude ?? null)
        ? (float) $mobileUser->latitude
        : (float) ($mobileMapMarkers->first()['latitude'] ?? 14.5995);
    $mobileMapCenterLongitude = is_numeric($mobileUser->longitude ?? null)
        ? (float) $mobileUser->longitude
        : (float) ($mobileMapMarkers->first()['longitude'] ?? 120.9842);
    $mobileBookingMapLatitude = $addressLatitude ?? $mobileMapCenterLatitude;
    $mobileBookingMapLongitude = $addressLongitude ?? $mobileMapCenterLongitude;
    $mobileIsHomeMap = $tab === 'home' && $mobileHomeView === 'home';
@endphp

<section
    class="customer-mobile-shell mx-auto min-h-[calc(100svh-9rem)] w-full max-w-screen-sm pb-6 lg:hidden {{ $mobileIsHomeMap ? 'customer-mobile-shell--fixed-home px-0 pb-0 pt-0' : 'space-y-5 px-4 pt-4' }}"
    data-app-customer-mobile-shell
    data-app-customer-mobile-tab="{{ $tab }}"
    data-app-customer-mobile-view="{{ $mobileHomeView }}"
>
    <div data-app-network-status hidden class="mx-4 rounded-xl bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800 dark:bg-amber-500/10 dark:text-amber-200" role="status" aria-live="polite"></div>

    @if ($mobileIsHomeMap && $mobileActiveBooking)
        <span
            hidden
            data-realtime-tracking
            data-realtime-tracking-booking-id="{{ $mobileActiveBooking->id }}"
            data-realtime-tracking-url="{{ route('realtime.snapshot', ['scope' => 'customer-active-booking']) }}"
            data-realtime-tracking-interval="1000"
        ></span>
    @endif

    @if ($tab === 'home')
        @if ($mobileHomeView === 'home')
            <div class="customer-mobile-map customer-mobile-map-surface relative overflow-hidden" data-app-customer-mobile-map data-map-center-lat="{{ $mobileMapCenterLatitude }}" data-map-center-lng="{{ $mobileMapCenterLongitude }}" data-map-markers="{{ $mobileMapMarkers->toJson() }}">
                <div
                    class="absolute inset-0"
                    data-app-customer-map
                    data-map-center-lat="{{ $mobileMapCenterLatitude }}"
                    data-map-center-lng="{{ $mobileMapCenterLongitude }}"
                    data-map-markers="{{ $mobileMapMarkers->toJson() }}"
                    wire:ignore
                >
                    <div data-app-map-status class="pointer-events-none absolute inset-0 z-[1000] flex items-center justify-center bg-zinc-100/90 text-sm font-medium text-zinc-500 dark:bg-zinc-900/90 dark:text-zinc-400" role="status" aria-live="polite">Loading live map…</div>
                </div>

                <div class="pointer-events-none absolute inset-x-4 top-4 z-[1000]">
                    <button type="button" wire:click="openMobileNavigate" class="customer-mobile-map-search pointer-events-auto flex w-full items-center gap-3 rounded-2xl border border-zinc-200 bg-white px-3 py-2.5 text-start shadow-lg dark:border-white/10 dark:bg-zinc-950" aria-label="Search for a service address">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-zinc-700 dark:bg-white/10 dark:text-zinc-200"><flux:icon name="magnifying-glass" class="size-5" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-zinc-900 dark:text-white">Where do you need service?</span>
                            <span class="mt-0.5 block truncate text-xs text-zinc-500">Search a service address or choose a recent place.</span>
                        </span>
                        <flux:icon name="chevron-right" class="size-5 shrink-0 text-zinc-400" />
                    </button>
                </div>

                <button type="button" data-app-map-locate class="customer-mobile-map-locate absolute bottom-[3.25rem] right-4 z-[1000] flex size-11 items-center justify-center rounded-full border border-zinc-200 bg-white text-zinc-800 shadow-lg transition hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-violet-500 dark:border-white/10 dark:bg-zinc-950 dark:text-white dark:hover:bg-zinc-900" aria-label="Center map on my location"><flux:icon name="map-pin" class="size-5" /></button>
            </div>

            <div class="customer-mobile-home-sheet relative z-10 -mt-8 bg-white px-4 pb-4 pt-3 dark:bg-zinc-950" data-app-mobile-home-sheet data-mobile-sheet-state="normal">
                <button type="button" class="customer-mobile-sheet-handle mx-auto mb-4 block h-1 w-10 rounded-full border-0 bg-zinc-300 p-0 dark:bg-zinc-700" data-app-mobile-home-sheet-handle aria-expanded="false" aria-label="Raise home sheet"></button>
                <div class="grid grid-cols-3 gap-2.5">
                    <button type="button" wire:click="openMobileQuickBook" class="customer-mobile-action flex min-h-24 flex-col items-center justify-center gap-2 rounded-2xl border border-violet-100 bg-violet-50 px-2 py-3 text-center transition hover:border-violet-300 dark:border-violet-400/20 dark:bg-violet-500/10">
                        <span class="customer-mobile-action__icon flex size-10 items-center justify-center rounded-xl bg-violet-600 text-white"><flux:icon name="bolt" class="size-5" /></span>
                        <span class="text-xs font-semibold text-zinc-900 dark:text-white">Quick book</span>
                    </button>
                    <button type="button" wire:click="openMobileSchedule" class="customer-mobile-action flex min-h-24 flex-col items-center justify-center gap-2 rounded-2xl border border-zinc-200 bg-zinc-50 px-2 py-3 text-center transition hover:border-violet-300 hover:bg-violet-50 dark:border-white/10 dark:bg-white/[0.04]">
                        <span class="customer-mobile-action__icon flex size-10 items-center justify-center rounded-xl bg-zinc-900 text-white dark:bg-white dark:text-zinc-900"><flux:icon name="calendar-days" class="size-5" /></span>
                        <span class="text-xs font-semibold text-zinc-900 dark:text-white">Schedule a visit</span>
                    </button>
                    <button type="button" wire:click="openMobileNavigate" class="customer-mobile-action flex min-h-24 flex-col items-center justify-center gap-2 rounded-2xl border border-zinc-200 bg-zinc-50 px-2 py-3 text-center transition hover:border-violet-300 hover:bg-violet-50 dark:border-white/10 dark:bg-white/[0.04]">
                        <span class="customer-mobile-action__icon flex size-10 items-center justify-center rounded-xl bg-zinc-900 text-white dark:bg-white dark:text-zinc-900"><flux:icon name="map" class="size-5" /></span>
                        <span class="text-xs font-semibold text-zinc-900 dark:text-white">Navigate</span>
                    </button>
                </div>

                @if (! $mobileActiveBooking)
                    @if ($mobileRecentLocations->isNotEmpty())
                    <div class="mt-5 flex items-center justify-between gap-3">
                        <flux:heading size="sm">Recent places</flux:heading>
                        <button type="button" wire:click="openMobileNavigate" class="text-xs font-semibold text-violet-700 dark:text-violet-300">View all</button>
                    </div>
                    <div class="mt-2 space-y-2">
                        @foreach ($mobileRecentLocations->take(2) as $booking)
                            <button type="button" wire:key="mobile-home-place-{{ $booking->id }}" wire:click="selectMobileRecentDestination({{ $booking->id }})" class="customer-mobile-place flex w-full items-center gap-3 rounded-xl border border-zinc-200 px-3 py-3 text-start transition hover:border-violet-300 hover:bg-violet-50/60 dark:border-white/10 dark:hover:bg-violet-500/10">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-zinc-700 dark:bg-white/10 dark:text-zinc-200"><flux:icon name="map-pin" class="size-4" /></span>
                                <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium text-zinc-900 dark:text-white">{{ \Illuminate\Support\Str::limit($booking->address, 48) }}</span><span class="mt-0.5 block text-xs text-zinc-500">{{ $serviceLabel($booking->service_type) }}</span></span>
                                <flux:icon name="chevron-right" class="size-4 shrink-0 text-zinc-400" />
                            </button>
                        @endforeach
                    </div>
                    @else
                        <div class="mt-5 rounded-xl border border-dashed border-zinc-300 px-4 py-3 text-center text-sm text-zinc-500 dark:border-zinc-700">Your recent service locations will appear here.</div>
                    @endif
                @endif

                @if ($mobileActiveBooking)
                    @php
                        $mobileStatus = $mobileActiveBooking->status;
                        [$mobileTrackingTitle, $mobileTrackingMessage] = match ($mobileStatus) {
                            'pending', 'matching' => ['Waiting for a technician', 'Your request is being shared with available technicians.'],
                            'assigned' => ['Technician assigned', 'Your technician is preparing to start the route.'],
                            'en_route' => ['Technician is on the way', 'You can follow the technician on the map above.'],
                            'in_progress' => ['Service in progress', 'Your technician is working on your service.'],
                            default => ['Active service', \Illuminate\Support\Str::headline($mobileStatus)],
                        };
                    @endphp
                    <div class="customer-mobile-active-service mt-3 rounded-xl bg-violet-50 px-3 py-3 dark:bg-violet-500/10">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <span class="block text-xs font-semibold uppercase tracking-[0.12em] text-violet-700 dark:text-violet-300">{{ $mobileTrackingTitle }}</span>
                                <span class="mt-1 block text-sm font-semibold text-zinc-900 dark:text-white">{{ $serviceLabel($mobileActiveBooking->service_type) }}</span>
                                <span class="mt-0.5 block text-xs text-zinc-600 dark:text-zinc-300">{{ $mobileTrackingMessage }}</span>
                                @if ($mobileActiveBooking->technician)
                                    <span class="mt-2 block text-xs font-medium text-violet-800 dark:text-violet-200">{{ $mobileActiveBooking->technician->name }}</span>
                                @endif
                            </div>
                            <button type="button" wire:click="openBooking({{ $mobileActiveBooking->id }})" class="flex size-8 shrink-0 items-center justify-center rounded-full bg-white/80 text-violet-700 transition hover:bg-white dark:bg-white/10 dark:text-violet-200" aria-label="View active booking">
                                <flux:icon name="chevron-right" class="size-4" />
                            </button>
                        </div>
                        @if (in_array($mobileActiveBooking->status, \App\Models\Booking::ACTIVE_STATUSES, true))
                            <button type="button" wire:click="prepareCancellation({{ $mobileActiveBooking->id }})" class="mt-3 min-h-10 w-full rounded-xl bg-red-500 px-3 text-xs font-semibold text-white transition hover:bg-red-600 dark:bg-red-600 dark:hover:bg-red-500">Cancel booking</button>
                        @endif
                    </div>
                @endif
            </div>
        @elseif (in_array($mobileHomeView, ['quick-book', 'schedule-form'], true))
            <header class="flex items-center gap-3">
                @if ($mobileHomeView === 'schedule-form')
                    <button type="button" wire:click="openMobileSchedule" class="flex size-10 shrink-0 items-center justify-center text-zinc-800 transition hover:text-violet-700 dark:text-white dark:hover:text-violet-300" aria-label="Back to technician stores"><flux:icon name="arrow-left" class="size-5" /></button>
                @else
                    <button type="button" wire:click="closeMobileHomeView" class="flex size-10 shrink-0 items-center justify-center text-zinc-800 transition hover:text-violet-700 dark:text-white dark:hover:text-violet-300" aria-label="Back to home"><flux:icon name="arrow-left" class="size-5" /></button>
                @endif
                <div><flux:heading size="xl" class="tracking-tight">{{ $mobileHomeView === 'schedule-form' ? 'Schedule a visit' : 'Quick book' }}</flux:heading><flux:text class="mt-0.5 text-zinc-500">{{ $mobileHomeView === 'schedule-form' ? 'Choose a time that works for you.' : 'Tell us where you need a hand.' }}</flux:text></div>
            </header>

            <div class="rounded-[1.75rem] bg-violet-700 px-5 py-6 text-white shadow-sm">
                <flux:icon name="wrench-screwdriver" class="size-8 text-violet-200" />
                <flux:heading size="lg" class="mt-4 text-white">{{ $mobileHomeView === 'schedule-form' ? 'Set your preferred visit' : 'Get help at home' }}</flux:heading>
                <p class="mt-1 text-sm text-violet-100">A FixTrack technician will use these details to prepare for your service.</p>
            </div>

            <form wire:submit="createBooking" class="customer-mobile-booking-form space-y-4">
                <div class="relative">
                    <label for="mobile-service-address" class="mb-1.5 block text-sm font-semibold text-zinc-900 dark:text-white">Address</label>
                    <div class="flex items-center gap-2 rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-1.5 focus-within:border-violet-400 dark:border-white/10 dark:bg-white/[0.04]">
                        <flux:icon name="map-pin" class="size-5 shrink-0 text-violet-600" />
                        <input id="mobile-service-address" wire:model="address" x-on:input.debounce.600ms="$wire.searchAddress($event.target.value)" type="text" class="min-w-0 flex-1 border-0 bg-transparent py-2 text-sm text-zinc-900 outline-none placeholder:text-zinc-400 focus:ring-0 dark:text-white" placeholder="Where do you need service?" required />
                        <button type="button" wire:click="searchAddress" class="flex size-9 shrink-0 items-center justify-center rounded-lg text-zinc-500 hover:bg-white hover:text-violet-700 dark:hover:bg-white/10" aria-label="Search address" wire:loading.attr="disabled" wire:target="searchAddress"><flux:icon name="magnifying-glass" class="size-5" /></button>
                    </div>
                    @error('address')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                    @if ($addressSuggestions !== [])
                        <div class="customer-mobile-address-suggestions absolute inset-x-0 top-full z-30 mt-2 max-h-60 overflow-y-auto rounded-xl border border-zinc-200 bg-white shadow-xl dark:border-white/10 dark:bg-zinc-900">
                            @foreach ($addressSuggestions as $index => $suggestion)
                                <button type="button" wire:key="mobile-address-suggestion-{{ $index }}" wire:click="selectAddress({{ $index }})" class="block w-full border-b border-zinc-100 px-3 py-2.5 text-start text-sm text-zinc-900 last:border-b-0 hover:bg-zinc-50 dark:border-white/10 dark:text-zinc-100 dark:hover:bg-white/5">{{ $suggestion['label'] }}</button>
                            @endforeach
                        </div>
                    @endif

                    <div
                        class="customer-mobile-booking-map relative mt-3 overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-100 dark:border-white/10 dark:bg-zinc-900"
                        data-app-customer-mobile-map
                        data-map-center-lat="{{ $mobileBookingMapLatitude }}"
                        data-map-center-lng="{{ $mobileBookingMapLongitude }}"
                    >
                        <div
                            class="absolute inset-0"
                            data-app-customer-map
                            data-map-center-lat="{{ $mobileBookingMapLatitude }}"
                            data-map-center-lng="{{ $mobileBookingMapLongitude }}"
                            data-map-markers="[]"
                            data-map-draggable="true"
                            data-map-auto-locate="{{ $mobileHomeView === 'quick-book' ? 'true' : 'false' }}"
                            data-map-watch-location="false"
                            wire:ignore
                        >
                            <div data-app-map-status class="pointer-events-none absolute inset-0 z-[1000] flex items-center justify-center bg-zinc-100/90 text-sm font-medium text-zinc-500 dark:bg-zinc-900/90 dark:text-zinc-400" role="status" aria-live="polite">Loading live map…</div>
                        </div>
                        <div class="pointer-events-none absolute inset-x-3 bottom-3 z-[1000]">
                            <span class="inline-flex rounded-full bg-white/95 px-3 py-1.5 text-xs font-medium text-zinc-700 shadow-md dark:bg-zinc-950/95 dark:text-zinc-200">Drag the pin to fine-tune</span>
                        </div>
                        <button type="button" data-app-map-locate class="absolute right-3 top-3 z-[1000] flex size-10 items-center justify-center rounded-full border border-zinc-200 bg-white text-zinc-800 shadow-md dark:border-white/10 dark:bg-zinc-950 dark:text-white" aria-label="Use my current location"><flux:icon name="map-pin" class="size-4" /></button>
                        <input type="hidden" wire:model.live.number="addressLatitude" data-app-map-latitude />
                        <input type="hidden" wire:model.live.number="addressLongitude" data-app-map-longitude />
                    </div>
                </div>

                @if ($mobileHomeView === 'schedule-form' && $selectedMobileTechnicianStore)
                    @php($selectedStoreVerification = $selectedMobileTechnicianStore->technicianVerification)
                    <div class="rounded-xl bg-violet-50 px-3 py-3 dark:bg-violet-500/10"><span class="block text-xs font-semibold uppercase tracking-[0.12em] text-violet-700 dark:text-violet-300">Technician store</span><div class="mt-1 flex items-start justify-between gap-3"><div class="min-w-0"><span class="block truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $selectedMobileTechnicianStore->name }} Service Store</span><span class="mt-0.5 block truncate text-xs text-zinc-600 dark:text-zinc-300">{{ $selectedStoreVerification?->address }}</span></div><button type="button" wire:click="openMobileSchedule" class="shrink-0 text-xs font-semibold text-violet-700 dark:text-violet-300">Change</button></div></div>
                @endif

                <div>
                    <span class="mb-1.5 block text-sm font-semibold text-zinc-900 dark:text-white">1. Choose a service category</span>
                    @if ($serviceCategory === '')
                        <div class="grid gap-3">
                            @foreach ($serviceCatalog->groupBy('category') as $category => $services)
                                <button type="button" wire:key="mobile-service-category-{{ $category }}" wire:click="chooseServiceCategory(@js($category))" class="min-h-16 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-start transition hover:border-violet-300 hover:bg-violet-50 dark:border-white/10 dark:bg-white/[0.04] dark:hover:border-violet-400/40 dark:hover:bg-violet-500/10" aria-pressed="false">
                                    <span class="block font-semibold text-zinc-900 dark:text-white">{{ $category }}</span>
                                    <span class="mt-1 block text-xs text-zinc-500">{{ $services->pluck('name')->join(', ') }}</span>
                                </button>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-xl border border-violet-200 bg-violet-50 p-3 dark:border-violet-400/30 dark:bg-violet-500/10">
                            <div class="flex items-center justify-between gap-3">
                                <div><span class="block text-[11px] font-semibold uppercase tracking-wider text-violet-700 dark:text-violet-300">Category</span><span class="mt-1 block font-semibold text-zinc-900 dark:text-white">{{ $serviceCategory }}</span></div>
                                <button type="button" wire:click="chooseServiceCategory('')" class="text-xs font-semibold text-violet-700 dark:text-violet-300">Change</button>
                            </div>
                            <span class="mt-4 block text-sm font-semibold text-zinc-900 dark:text-white">2. Choose a service</span>
                            <div class="mt-2 grid gap-2">
                                @foreach ($serviceCatalog->where('category', $serviceCategory) as $service)
                                    <button type="button" wire:key="mobile-service-type-{{ $service->code }}" wire:click="chooseServiceType(@js($service->code))" class="min-h-14 rounded-xl border px-3 py-3 text-start transition {{ $serviceType === $service->code ? 'border-violet-500 bg-white ring-2 ring-violet-200 dark:border-violet-400 dark:bg-zinc-900 dark:ring-violet-500/20' : 'border-zinc-200 bg-white hover:border-violet-300 dark:border-white/10 dark:bg-zinc-900 dark:hover:border-violet-400/40' }}" aria-pressed="{{ $serviceType === $service->code ? 'true' : 'false' }}">
                                        <span class="block text-sm font-medium text-zinc-900 dark:text-white">{{ $service->name }}</span>
                                        <span class="mt-0.5 block text-xs text-zinc-500">{{ $service->description }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @error('serviceType')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div><label for="mobile-customer-phone" class="mb-1.5 block text-sm font-semibold text-zinc-900 dark:text-white">Mobile number</label><input id="mobile-customer-phone" wire:model="customerPhone" type="tel" class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-3 text-sm text-zinc-900 outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-white" placeholder="917 123 4567" required />@error('customerPhone')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror</div>

                @if ($bookingType === 'scheduled')
                    <div><label for="mobile-scheduled-at" class="mb-1.5 block text-sm font-semibold text-zinc-900 dark:text-white">Preferred date and time</label><input id="mobile-scheduled-at" wire:model="scheduledAt" type="datetime-local" class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-3 text-sm text-zinc-900 outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-white" required />@error('scheduledAt')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                @endif

                <div><label for="mobile-description" class="mb-1.5 block text-sm font-semibold text-zinc-900 dark:text-white">Description <span class="font-normal text-zinc-400">(optional)</span></label><textarea id="mobile-description" wire:model="description" rows="3" class="w-full resize-none rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-3 text-sm text-zinc-900 outline-none focus:border-violet-400 focus:ring-2 focus:ring-violet-100 dark:border-white/10 dark:bg-white/[0.04] dark:text-white" placeholder="Tell us what needs attention."></textarea>@error('description')<p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>@enderror</div>

                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl bg-zinc-900 px-4 py-3.5 text-sm font-semibold text-white transition hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100" wire:loading.attr="disabled" wire:target="createBooking"><span wire:loading.remove wire:target="createBooking">{{ $bookingType === 'scheduled' ? 'Schedule visit' : 'Find a technician' }}</span><span wire:loading wire:target="createBooking">Submitting…</span><flux:icon name="arrow-right" class="size-4" /></button>
            </form>
        @elseif ($mobileHomeView === 'schedule')
            <header class="flex items-center gap-3">
                <button type="button" wire:click="closeMobileHomeView" class="flex size-10 shrink-0 items-center justify-center text-zinc-800 transition hover:text-violet-700 dark:text-white dark:hover:text-violet-300" aria-label="Back to home"><flux:icon name="arrow-left" class="size-5" /></button>
                <div><flux:heading size="xl" class="tracking-tight">Schedule a visit</flux:heading><flux:text class="mt-0.5 text-zinc-500">Choose a technician store near you.</flux:text></div>
            </header>

            <div class="flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2 shadow-sm dark:border-white/10 dark:bg-zinc-900">
                <flux:icon name="magnifying-glass" class="size-5 shrink-0 text-zinc-500" />
                <input wire:model.live.debounce.300ms="mobileTechnicianStoreSearch" type="search" class="min-w-0 flex-1 border-0 bg-transparent py-2 text-sm text-zinc-900 outline-none placeholder:text-zinc-400 focus:ring-0 dark:text-white" placeholder="Search technician stores" aria-label="Search technician stores" />
                @if ($mobileTechnicianStoreSearch !== '')
                    <button type="button" wire:click="$set('mobileTechnicianStoreSearch', '')" class="flex size-8 shrink-0 items-center justify-center rounded-lg text-zinc-500 hover:bg-zinc-100 dark:hover:bg-white/10" aria-label="Clear store search"><flux:icon name="x-mark" class="size-4" /></button>
                @endif
            </div>

            <div><flux:heading size="lg">Technician stores</flux:heading><flux:text class="mt-1 text-sm text-zinc-500">Schedule directly with a verified technician store.</flux:text></div>
            <div class="space-y-3">
                @forelse ($mobileTechnicianStores as $technicianStore)
                    <button type="button" wire:key="mobile-schedule-store-{{ $technicianStore->id }}" wire:click="selectMobileTechnicianStore({{ $technicianStore->id }})" class="flex w-full items-center gap-3 rounded-2xl border border-zinc-200 bg-white p-3 text-start shadow-sm transition hover:border-violet-300 hover:bg-violet-50/50 dark:border-white/10 dark:bg-zinc-900 dark:hover:bg-violet-500/10">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-violet-100 text-xs font-bold text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">{{ $technicianStore->initials() }}</span>
                        <span class="min-w-0 flex-1"><span class="block truncate font-semibold text-zinc-900 dark:text-white">{{ $technicianStore->name }} Service Store</span><span class="mt-0.5 block truncate text-sm text-zinc-500">{{ \Illuminate\Support\Str::limit($technicianStore->technicianVerification->address, 60) }}</span></span>
                        <span class="flex shrink-0 flex-col items-end gap-1"><span class="rounded-full bg-emerald-100 px-2 py-1 text-[10px] font-semibold text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">Verified</span><flux:icon name="chevron-right" class="size-5 text-zinc-400" /></span>
                    </button>
                @empty
                    <div class="rounded-2xl border border-dashed border-zinc-300 px-5 py-14 text-center dark:border-zinc-700"><flux:icon name="building-storefront" class="mx-auto size-9 text-zinc-400" /><flux:heading size="lg" class="mt-4">{{ $mobileTechnicianStoreSearch !== '' ? 'No matching technician stores' : 'No technician stores yet' }}</flux:heading><flux:text class="mt-1 text-zinc-500">{{ $mobileTechnicianStoreSearch !== '' ? 'Try another store name or address.' : 'Verified technicians with a registered store address will appear here.' }}</flux:text></div>
                @endforelse
            </div>
        @elseif ($mobileHomeView === 'navigate')
            <header class="flex items-center gap-3">
                <button type="button" wire:click="closeMobileHomeView" class="flex size-10 shrink-0 items-center justify-center text-zinc-800 transition hover:text-violet-700 dark:text-white dark:hover:text-violet-300" aria-label="Back to home"><flux:icon name="arrow-left" class="size-5" /></button>
                <div><flux:heading size="xl" class="tracking-tight">Navigate</flux:heading><flux:text class="mt-0.5 text-zinc-500">Choose a service destination.</flux:text></div>
            </header>

            <form wire:submit="searchAddress" class="flex items-center gap-3 rounded-xl border border-zinc-200 bg-white px-3 py-2 shadow-sm dark:border-white/10 dark:bg-zinc-900"><flux:icon name="map-pin" class="size-5 shrink-0 text-violet-600" /><input wire:model="address" x-on:input.debounce.600ms="$wire.searchAddress($event.target.value)" type="search" class="min-w-0 flex-1 border-0 bg-transparent py-2 text-sm text-zinc-900 outline-none placeholder:text-zinc-400 focus:ring-0 dark:text-white" placeholder="Search for an address" aria-label="Search for an address" /><button type="submit" class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-zinc-900 text-white dark:bg-white dark:text-zinc-900" aria-label="Search" wire:loading.attr="disabled" wire:target="searchAddress"><flux:icon name="magnifying-glass" class="size-4" /></button></form>
            @error('address')<p class="-mt-3 text-xs text-red-600">{{ $message }}</p>@enderror

            <div class="flex items-center gap-1 border-b border-zinc-200 text-sm dark:border-white/10">
                @foreach (['recent' => 'Recent', 'suggested' => 'Suggested', 'saved' => 'Saved'] as $tabKey => $label)
                    <button type="button" wire:key="mobile-navigate-tab-{{ $tabKey }}" wire:click="setMobileNavigateTab('{{ $tabKey }}')" class="flex-1 border-b-2 px-2 py-3 font-medium {{ $mobileNavigateTab === $tabKey ? 'border-zinc-900 text-zinc-900 dark:border-white dark:text-white' : 'border-transparent text-zinc-500' }}">{{ $label }}</button>
                @endforeach
            </div>

            @if ($addressSuggestions !== [])
                <div><flux:heading size="lg">Search results</flux:heading><div class="mt-3 space-y-2">@foreach ($addressSuggestions as $index => $suggestion)<button type="button" wire:key="mobile-navigate-result-{{ $index }}" wire:click="selectMobileAddress({{ $index }})" class="flex w-full items-start gap-3 rounded-xl border border-zinc-200 px-3 py-3 text-start dark:border-white/10"><flux:icon name="map-pin" class="mt-0.5 size-5 shrink-0 text-zinc-900 dark:text-white" /><span class="text-sm text-zinc-700 dark:text-zinc-200">{{ $suggestion['label'] }}</span></button>@endforeach</div></div>
            @elseif ($mobileNavigateTab === 'saved')
                <div class="rounded-2xl border border-dashed border-zinc-300 px-5 py-14 text-center dark:border-zinc-700"><flux:icon name="bookmark" class="mx-auto size-9 text-zinc-400" /><flux:heading size="lg" class="mt-4">No saved places</flux:heading><flux:text class="mt-1 text-zinc-500">Saved service locations will appear here.</flux:text></div>
            @else
                @php($mobileLocationItems = $mobileNavigateTab === 'suggested' ? $mobileSuggestedLocations : $mobileRecentLocations)
                <div><flux:heading size="lg">{{ $mobileNavigateTab === 'suggested' ? 'Suggested places' : 'Recent places' }}</flux:heading><div class="mt-3 space-y-2">
                    @forelse ($mobileLocationItems as $booking)
                        <button type="button" wire:key="mobile-navigate-place-{{ $mobileNavigateTab }}-{{ $booking->id }}" wire:click="selectMobileRecentDestination({{ $booking->id }})" class="flex w-full items-center gap-3 border-b border-zinc-100 px-1 py-3 text-start dark:border-white/10"><span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-zinc-700 dark:bg-white/10 dark:text-zinc-200"><flux:icon name="map-pin" class="size-4" /></span><span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium text-zinc-900 dark:text-white">{{ \Illuminate\Support\Str::limit($booking->address, 54) }}</span><span class="mt-0.5 block truncate text-xs text-zinc-500">{{ $serviceLabel($booking->service_type) }}</span></span><flux:icon name="chevron-right" class="size-4 shrink-0 text-zinc-400" /></button>
                    @empty
                        <div class="rounded-2xl border border-dashed border-zinc-300 px-5 py-14 text-center dark:border-zinc-700"><flux:icon name="map-pin" class="mx-auto size-9 text-zinc-400" /><flux:heading size="lg" class="mt-4">No places yet</flux:heading><flux:text class="mt-1 text-zinc-500">Search for an address above to start a service request.</flux:text></div>
                    @endforelse
                </div></div>
            @endif
        @endif
    @elseif ($tab === 'activity')
        <header class="flex items-end justify-between gap-4">
            <div>
                <flux:text class="text-sm text-zinc-500">Your service journey</flux:text>
                <flux:heading size="xl" class="mt-1 tracking-tight">Activity</flux:heading>
            </div>
            <a href="{{ route('customer.module', ['module' => 'book-service']) }}" wire:navigate class="flex size-10 items-center justify-center rounded-full bg-zinc-900 text-white dark:bg-white dark:text-zinc-900" aria-label="Book a service">
                <flux:icon name="plus" class="size-5" />
            </a>
        </header>

        @if (isset($content['bookings']))
            <div class="flex items-center justify-between gap-3 rounded-xl bg-zinc-100 p-1 dark:bg-white/10">
                <span class="flex-1 rounded-lg bg-white px-3 py-2 text-center text-sm font-semibold text-zinc-900 shadow-sm dark:bg-zinc-800 dark:text-white">Bookings</span>
                <a href="{{ route('customer.module', ['module' => 'quotations']) }}" wire:navigate class="flex-1 px-3 py-2 text-center text-sm font-medium text-zinc-500">Quotations</a>
            </div>
            <div class="space-y-3">
                @forelse ($mobileBookings as $booking)
                    <article class="w-full rounded-2xl border border-zinc-200 bg-white p-4 text-start shadow-sm dark:border-white/10 dark:bg-zinc-900">
                        <button type="button" wire:click="openBooking({{ $booking->id }})" class="w-full text-start">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate font-semibold text-zinc-900 dark:text-white">{{ $serviceLabel($booking->service_type) }}</div>
                                <div class="mt-1 truncate text-sm text-zinc-500">{{ $booking->address }}</div>
                            </div>
                            <x-super-admin.table-cell :value="$booking->status" type="status" />
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-3 text-xs text-zinc-500">
                            <span>{{ $booking->reference }}</span>
                            <span>{{ $formatDate($booking->scheduled_at ?: $booking->created_at) }}</span>
                        </div>
                        </button>
                        @if (in_array($booking->status, \App\Models\Booking::ACTIVE_STATUSES, true))
                            <button type="button" wire:click="prepareCancellation({{ $booking->id }})" class="mt-3 min-h-10 w-full rounded-xl bg-red-500 px-3 text-xs font-semibold text-white transition hover:bg-red-600 dark:bg-red-600 dark:hover:bg-red-500">Cancel booking</button>
                        @endif
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-zinc-300 px-5 py-14 text-center dark:border-zinc-700">
                        <flux:icon name="clipboard-document-list" class="mx-auto size-9 text-zinc-400" />
                        <flux:heading size="lg" class="mt-4">Nothing here yet</flux:heading>
                        <flux:text class="mt-1 text-zinc-500">Your bookings will appear here.</flux:text>
                        <button type="button" wire:click="startBooking" class="mt-5 rounded-xl bg-zinc-900 px-4 py-2.5 text-sm font-semibold text-white dark:bg-white dark:text-zinc-900">Book a service</button>
                    </div>
                @endforelse
            </div>
        @elseif (isset($content['quotations']))
            <div class="space-y-3">
                @forelse ($mobileQuotations as $quotation)
                    <div class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-zinc-900">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="font-semibold text-zinc-900 dark:text-white">{{ $quotation->booking?->service?->name ?: \Illuminate\Support\Str::headline($quotation->booking?->service_type) }}</div>
                                <div class="mt-1 text-sm text-zinc-500">{{ $quotation->booking?->reference }}</div>
                            </div>
                            <x-super-admin.table-cell :value="$quotation->status" type="status" />
                        </div>
                        <div class="mt-4 flex items-end justify-between gap-3">
                            <span class="text-lg font-semibold text-zinc-900 dark:text-white">₱{{ number_format((float) $quotation->total_amount, 2) }}</span>
                            @if ($quotation->status === 'awaiting_approval')
                                <div class="flex gap-2">
                                    <button type="button" wire:click="respondToQuotation({{ $quotation->id }}, 'approved')" class="rounded-lg bg-zinc-900 px-3 py-2 text-xs font-semibold text-white dark:bg-white dark:text-zinc-900">Approve</button>
                                    <button type="button" wire:click="respondToQuotation({{ $quotation->id }}, 'rejected')" class="rounded-lg border border-zinc-200 px-3 py-2 text-xs font-semibold text-zinc-700 dark:border-white/10 dark:text-zinc-200">Decline</button>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-zinc-300 px-5 py-14 text-center dark:border-zinc-700"><flux:icon name="document-text" class="mx-auto size-9 text-zinc-400" /><flux:heading size="lg" class="mt-4">No quotations yet</flux:heading><flux:text class="mt-1 text-zinc-500">Technician assessments will appear here.</flux:text></div>
                @endforelse
            </div>
        @elseif (isset($content['payments']))
            <div class="grid grid-cols-3 gap-2">
                @foreach ($content['stats'] as $stat)
                    <div class="rounded-xl border border-zinc-200 bg-white p-3 dark:border-white/10 dark:bg-zinc-900">
                        <div class="truncate text-[10px] font-medium uppercase tracking-wide text-zinc-500">{{ $stat['label'] }}</div>
                        <div class="mt-1 truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $stat['value'] }}</div>
                    </div>
                @endforeach
            </div>
            <div class="space-y-3">
                @forelse ($mobilePayments as $payment)
                    <article wire:key="mobile-payment-{{ $payment->id }}" class="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-zinc-900">
                        <button type="button" wire:click="openPayment({{ $payment->id }})" class="w-full text-start">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate font-semibold text-zinc-900 dark:text-white">{{ $payment->booking?->reference ?: 'Payment' }}</div>
                                    <div class="mt-1 truncate text-sm text-zinc-500">{{ $serviceLabel($payment->booking?->service_type) }}</div>
                                </div>
                                <x-super-admin.table-cell :value="$payment->status" type="status" />
                            </div>
                            <div class="mt-3 flex items-end justify-between gap-3">
                                <div class="text-xs text-zinc-500">{{ $paymentMethodLabel($payment->method) }}</div>
                                <div class="font-semibold tabular-nums text-zinc-900 dark:text-white">₱{{ number_format((float) $payment->amount, 2) }}</div>
                            </div>
                            <div class="mt-3 flex items-center justify-between gap-3 text-xs text-zinc-400">
                                <span>{{ $formatDate($payment->paid_at ?: $payment->created_at) }}</span>
                                <span>View details <flux:icon name="chevron-right" class="inline size-3.5" /></span>
                            </div>
                        </button>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-zinc-300 px-5 py-14 text-center dark:border-zinc-700"><flux:icon name="credit-card" class="mx-auto size-9 text-zinc-400" /><flux:heading size="lg" class="mt-4">No payments yet</flux:heading><flux:text class="mt-1 text-zinc-500">Payment records will appear after a completed service.</flux:text></div>
                @endforelse
            </div>
        @else
            <div class="rounded-2xl border border-dashed border-zinc-300 px-5 py-14 text-center dark:border-zinc-700"><flux:heading size="lg">Activity</flux:heading><flux:text class="mt-1 text-zinc-500">Your latest service activity will appear here.</flux:text></div>
        @endif
    @elseif ($tab === 'messages')
        <header class="flex items-end justify-between gap-4">
            <div>
                <flux:text class="text-sm text-zinc-500">Stay up to date</flux:text>
                <flux:heading size="xl" class="mt-1 tracking-tight">Messages</flux:heading>
            </div>
            <a href="{{ route('customer.module', ['module' => 'support']) }}" wire:navigate class="flex size-10 items-center justify-center rounded-full bg-zinc-100 text-zinc-800 dark:bg-white/10 dark:text-white" aria-label="Contact support"><flux:icon name="plus" class="size-5" /></a>
        </header>

        <div class="flex items-center gap-1 rounded-xl bg-zinc-100 p-1 text-sm dark:bg-white/10">
            <span class="flex-1 rounded-lg bg-white px-3 py-2 text-center font-semibold text-zinc-900 shadow-sm dark:bg-zinc-800 dark:text-white">Updates</span>
            <a href="{{ route('customer.module', ['module' => 'support']) }}" wire:navigate class="flex-1 px-3 py-2 text-center font-medium text-zinc-500">Support</a>
        </div>

        <div class="space-y-2">
            @if (isset($content['items']))
                @forelse ($mobileUpdates as $item)
                    <div class="flex items-start gap-3 rounded-2xl border border-zinc-200 bg-white p-4 dark:border-white/10 dark:bg-zinc-900">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-violet-50 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"><flux:icon name="bell" class="size-5" /></span>
                        <div class="min-w-0 flex-1"><div class="flex items-start justify-between gap-3"><div class="font-medium text-zinc-900 dark:text-white">{{ $item['title'] }}</div><span class="shrink-0 text-xs text-zinc-500">{{ $formatDate($item['date']) }}</span></div><div class="mt-1 truncate text-sm text-zinc-500">{{ $item['detail'] }}</div></div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-zinc-300 px-5 py-14 text-center dark:border-zinc-700"><flux:icon name="chat-bubble-left-right" class="mx-auto size-9 text-zinc-400" /><flux:heading size="lg" class="mt-4">No new messages</flux:heading><flux:text class="mt-1 text-zinc-500">You are all caught up.</flux:text></div>
                @endforelse
            @else
                @forelse ($mobileTickets as $ticket)
                    <div class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-white/10 dark:bg-zinc-900"><div class="flex items-center justify-between gap-3"><div class="font-semibold">{{ $ticket->subject }}</div><x-super-admin.table-cell :value="$ticket->status" type="status" /></div><div class="mt-1 text-sm text-zinc-500">{{ $ticket->reference }}</div><div class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">{{ $ticket->latest_message }}</div></div>
                @empty
                    <div class="rounded-2xl border border-dashed border-zinc-300 px-5 py-14 text-center dark:border-zinc-700"><flux:icon name="chat-bubble-left-right" class="mx-auto size-9 text-zinc-400" /><flux:heading size="lg" class="mt-4">No support requests</flux:heading><flux:text class="mt-1 text-zinc-500">Need help? Start a conversation with FixTrack.</flux:text></div>
                @endforelse
            @endif
        </div>
    @elseif ($tab === 'account')
        <x-profile-photo-editor :user="$mobileUser" :pending-photo="$profilePhoto" input-id="customer-profile-photo" />

        @foreach ([
            ['title' => 'My account', 'items' => [
                ['label' => 'Payments', 'icon' => 'credit-card', 'href' => route('customer.module', ['module' => 'payments'])],
                ['label' => 'Ratings & reviews', 'icon' => 'star', 'href' => route('customer.module', ['module' => 'ratings-reviews'])],
            ]],
            ['title' => 'General', 'items' => [
                ['label' => 'Help center', 'icon' => 'chat-bubble-left-right', 'href' => route('customer.module', ['module' => 'support'])],
                ['label' => 'Settings', 'icon' => 'cog-6-tooth', 'href' => route('profile.edit')],
            ]],
        ] as $section)
            <div>
                <flux:heading size="sm" class="mb-2 px-1 text-zinc-900 dark:text-white">{{ $section['title'] }}</flux:heading>
                <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-white/10 dark:bg-zinc-900">
                    @foreach ($section['items'] as $item)
                        <a href="{{ $item['href'] }}" wire:navigate class="flex min-h-14 items-center gap-3 border-b border-zinc-100 px-4 last:border-b-0 dark:border-white/10">
                            <flux:icon name="{{ $item['icon'] }}" class="size-5 text-zinc-500" />
                            <span class="flex-1 text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $item['label'] }}</span>
                            <flux:icon name="chevron-right" class="size-4 text-zinc-400" />
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach

        <form method="POST" action="{{ route('logout') }}" class="pt-2">
            @csrf
            <button type="submit" class="flex min-h-12 w-full items-center justify-center rounded-xl border border-zinc-200 bg-white text-sm font-semibold text-zinc-700 dark:border-white/10 dark:bg-zinc-900 dark:text-zinc-200">Log out</button>
        </form>
    @endif
</section>
