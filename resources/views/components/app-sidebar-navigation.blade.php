@props([
    'mobile' => false,
])

@php
    $isAdmin = auth()->user()->isAdmin();
    $isTechnician = auth()->user()->isTechnician();
    $moduleRoute = $isAdmin ? 'admin.module' : ($isTechnician ? 'technician.module' : 'customer.module');
    $dashboardRoute = $isAdmin ? route('admin.dashboard') : ($isTechnician ? route('technician.module') : route('customer.module'));

    $groups = $isAdmin
        ? [
            [
                'heading' => __('Platform Oversight'),
                'items' => [
                    ['label' => __('Dashboard'), 'icon' => 'home', 'href' => $dashboardRoute, 'current' => request()->routeIs('dashboard', 'admin.dashboard')],
                    ['label' => __('System Health'), 'icon' => 'server-stack', 'slug' => 'system-health'],
                ],
            ],
            [
                'heading' => __('People & Access'),
                'items' => [
                    ['label' => __('Users & Roles'), 'icon' => 'users', 'slug' => 'users-roles'],
                    ['label' => __('Technician Verification'), 'icon' => 'identification', 'slug' => 'technician-verification'],
                ],
            ],
            [
                'heading' => __('Operations Monitor'),
                'items' => [
                    ['label' => __('Service Bookings'), 'icon' => 'clipboard-document-list', 'slug' => 'service-bookings'],
                    ['label' => __('Dispatch Monitor'), 'icon' => 'map', 'slug' => 'dispatch-monitor'],
                    ['label' => __('Walk-in Queue'), 'icon' => 'queue-list', 'slug' => 'walk-in-queue'],
                ],
            ],
            [
                'heading' => __('Finance & Quality'),
                'items' => [
                    ['label' => __('Payments & Revenue'), 'icon' => 'banknotes', 'slug' => 'payments-revenue'],
                    ['label' => __('Ratings & Reviews'), 'icon' => 'star', 'slug' => 'ratings-reviews'],
                    ['label' => __('Reports & Analytics'), 'icon' => 'chart-bar', 'slug' => 'reports-analytics'],
                ],
            ],
            [
                'heading' => __('Governance'),
                'items' => [
                    ['label' => __('Support & Disputes'), 'icon' => 'chat-bubble-left-right', 'slug' => 'support-disputes'],
                    ['label' => __('Audit Logs'), 'icon' => 'document-text', 'slug' => 'audit-logs'],
                    ['label' => __('Service Catalog'), 'icon' => 'wrench-screwdriver', 'slug' => 'service-catalog'],
                    ['label' => __('Platform Settings'), 'icon' => 'cog-6-tooth', 'slug' => 'platform-settings'],
                ],
            ],
        ]
        : ($isTechnician
            ? [
                [
                    'heading' => __('Technician Workspace'),
                    'items' => [
                        ['label' => __('Dashboard'), 'icon' => 'home', 'href' => $dashboardRoute, 'current' => request()->routeIs('technician.module') && ! request()->route('module')],
                        ['label' => __('Job Requests'), 'icon' => 'queue-list', 'slug' => 'job-requests'],
                        ['label' => __('My Jobs'), 'icon' => 'clipboard-document-list', 'slug' => 'my-jobs'],
                        ['label' => __('Schedule'), 'icon' => 'calendar-days', 'slug' => 'schedule'],
                    ],
                ],
                [
                    'heading' => __('Operations'),
                    'items' => [
                        ['label' => __('Dispatch & Routes'), 'icon' => 'map', 'slug' => 'dispatch-routes'],
                    ],
                ],
                [
                    'heading' => __('Finance & Performance'),
                    'items' => [
                        ['label' => __('Earnings'), 'icon' => 'banknotes', 'slug' => 'earnings'],
                        ['label' => __('Ratings & Reviews'), 'icon' => 'star', 'slug' => 'ratings-reviews'],
                    ],
                ],
                [
                    'heading' => __('Account'),
                    'items' => [
                        ['label' => __('Verification & Profile'), 'icon' => 'user-circle', 'slug' => 'verification-profile'],
                        ['label' => __('Notifications'), 'icon' => 'bell', 'slug' => 'notifications'],
                        ['label' => __('Support & Help'), 'icon' => 'chat-bubble-left-right', 'slug' => 'support'],
                    ],
                ],
            ]
            : [
                [
                    'heading' => __('Home'),
                    'items' => [
                        ['label' => __('Dashboard'), 'icon' => 'home', 'href' => $dashboardRoute, 'current' => request()->routeIs('customer.module') && ! request()->route('module')],
                    ],
                ],
                [
                    'heading' => __('Bookings'),
                    'items' => [
                        ['label' => __('Book a Service'), 'icon' => 'plus-circle', 'slug' => 'book-service'],
                        ['label' => __('My Bookings'), 'icon' => 'clipboard-document-list', 'slug' => 'my-bookings'],
                        ['label' => __('Walk-in Queue'), 'icon' => 'queue-list', 'slug' => 'walk-in-queue'],
                    ],
                ],
                [
                    'heading' => __('Service Journey'),
                    'items' => [
                        ['label' => __('Quotations'), 'icon' => 'document-text', 'slug' => 'quotations'],
                        ['label' => __('Payments'), 'icon' => 'credit-card', 'slug' => 'payments'],
                    ],
                ],
                [
                    'heading' => __('Account'),
                    'items' => [
                        ['label' => __('Ratings & Reviews'), 'icon' => 'star', 'slug' => 'ratings-reviews'],
                        ['label' => __('Notifications'), 'icon' => 'bell', 'slug' => 'notifications'],
                        ['label' => __('Support & Help'), 'icon' => 'chat-bubble-left-right', 'slug' => 'support'],
                        ['label' => __('Settings'), 'icon' => 'cog', 'slug' => 'settings'],
                    ],
                ],
            ]);

    $navigationItems = collect($groups)
        ->flatMap(fn ($group) => $group['items'])
        ->map(function (array $item) use ($moduleRoute): array {
            $item['key'] = $item['slug'] ?? 'dashboard';
            $item['href'] = $item['href'] ?? route($moduleRoute, ['module' => $item['slug']]);
            $item['current'] = $item['current'] ?? (isset($item['slug']) && request()->routeIs($moduleRoute) && request()->route('module') === $item['slug']);

            return $item;
        })
        ->values();

    $mobilePrimaryKeys = $isAdmin
        ? ['dashboard', 'service-bookings', 'dispatch-monitor', 'users-roles']
        : ($isTechnician
            ? ['dashboard', 'job-requests', 'my-jobs', 'dispatch-routes', 'verification-profile']
            : ['dashboard', 'book-service', 'my-bookings', 'quotations']);
    $isCustomer = ! $isAdmin && ! $isTechnician;
    $customerMobileItems = collect([
        [
            'key' => 'home',
            'label' => __('Home'),
            'icon' => 'home',
            'href' => $dashboardRoute,
            'current' => request()->routeIs('customer.module') && in_array(request()->route('module'), [null, 'overview'], true),
        ],
        [
            'key' => 'activity',
            'label' => __('Activity'),
            'icon' => 'clipboard-document-list',
            'href' => route($moduleRoute, ['module' => 'my-bookings']),
            'current' => request()->routeIs('customer.module') && in_array(request()->route('module'), ['my-bookings', 'walk-in-queue', 'quotations', 'payments'], true),
        ],
        [
            'key' => 'messages',
            'label' => __('Messages'),
            'icon' => 'chat-bubble-left-right',
            'href' => route($moduleRoute, ['module' => 'notifications']),
            'current' => request()->routeIs('customer.module') && in_array(request()->route('module'), ['notifications', 'support'], true),
        ],
        [
            'key' => 'account',
            'label' => __('Account'),
            'icon' => 'user-circle',
            'href' => route($moduleRoute, ['module' => 'settings']),
            'current' => request()->routeIs('customer.module') && in_array(request()->route('module'), ['settings', 'ratings-reviews'], true),
        ],
    ]);
    $mobileLabels = [
        'dashboard' => __('Home'),
        'service-bookings' => __('Bookings'),
        'dispatch-monitor' => __('Dispatch'),
        'users-roles' => __('Users'),
        'job-requests' => __('Requests'),
        'my-jobs' => __('Jobs'),
        'dispatch-routes' => __('Routes'),
        'verification-profile' => __('Profile'),
        'book-service' => __('Book'),
        'my-bookings' => __('Bookings'),
        'quotations' => __('Quotes'),
    ];
    $mobileItems = $isCustomer
        ? $customerMobileItems
        : $navigationItems
            ->filter(fn (array $item): bool => in_array($item['key'], $mobilePrimaryKeys, true))
            ->map(function (array $item) use ($mobileLabels): array {
                $item['mobileLabel'] = $mobileLabels[$item['key']] ?? $item['label'];

                return $item;
            });
    $mobileMoreItems = $isCustomer
        ? collect()
        : $navigationItems->reject(fn (array $item): bool => in_array($item['key'], $mobilePrimaryKeys, true));
    $mobileMoreCurrent = $mobileMoreItems->contains(fn (array $item): bool => $item['current']);
@endphp

@if ($mobile)
    <nav
        class="fixed inset-x-0 bottom-0 z-40 border-t border-zinc-200 bg-white shadow-[0_-8px_24px_-20px_rgb(0_0_0_/_0.35)] dark:border-white/10 dark:bg-zinc-950 lg:hidden"
        style="padding-bottom: max(0.5rem, env(safe-area-inset-bottom));"
        data-app-mobile-navigation
    >
        <flux:navbar class="mx-auto flex h-[4.5rem] max-w-screen-sm items-stretch justify-between gap-1 px-2 !py-2">
            @foreach ($mobileItems as $item)
                <flux:navbar.item
                    :href="$item['href']"
                    :current="$item['current']"
                    :icon="$item['icon']"
                    wire:navigate
                    data-app-mobile-nav-item="{{ $item['key'] }}"
                    class="!h-full min-w-0 flex-1 flex-col justify-center gap-1 rounded-xl px-1 py-2 text-center text-[11px] leading-none data-current:bg-zinc-100 dark:data-current:bg-white/10 [&>div>svg]:size-5 [&_[data-content]]:ms-0 [&_[data-content]]:flex-none [&_[data-content]]:text-[11px] [&_[data-content]]:font-medium"
                >
                    {{ $item['mobileLabel'] ?? $item['label'] }}
                </flux:navbar.item>
            @endforeach

            @if (! $isCustomer && ! $isTechnician)
                <flux:dropdown position="top" align="end">
                <flux:navbar.item
                    icon="ellipsis-horizontal"
                    :current="$mobileMoreCurrent"
                    data-app-mobile-nav-item="more"
                    class="!h-full min-w-0 flex-1 flex-col justify-center gap-1 rounded-xl px-1 py-2 text-center text-[11px] leading-none data-current:bg-zinc-100 dark:data-current:bg-white/10 [&>div>svg]:size-5 [&_[data-content]]:ms-0 [&_[data-content]]:flex-none [&_[data-content]]:text-[11px] [&_[data-content]]:font-medium"
                >
                    {{ __('More') }}
                </flux:navbar.item>

                <flux:menu class="mb-2 max-h-[70vh] overflow-y-auto">
                    @foreach ($mobileMoreItems as $item)
                        <flux:menu.item :href="$item['href']" :icon="$item['icon']" wire:navigate>
                            {{ $item['label'] }}
                        </flux:menu.item>
                    @endforeach

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full" data-app-mobile-logout>
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
                </flux:dropdown>
            @endif
        </flux:navbar>
    </nav>
@else
    @foreach ($groups as $group)
        <flux:sidebar.group :heading="$group['heading']" class="grid">
            @foreach ($group['items'] as $item)
                @php
                    $href = $item['href'] ?? (isset($item['slug']) ? route($moduleRoute, ['module' => $item['slug']]) : null);
                    $current = $item['current'] ?? (isset($item['slug']) && request()->routeIs($moduleRoute) && request()->route('module') === $item['slug']);
                    $sidebarItemKey = $item['slug'] ?? 'dashboard';
                @endphp

                @if ($href !== null)
                    <flux:sidebar.item
                        :icon="$item['icon']"
                        :href="$href"
                        :current="$current"
                        data-super-admin-sidebar-item="{{ $sidebarItemKey }}"
                        data-technician-sidebar-item="{{ $sidebarItemKey }}"
                        data-customer-sidebar-item="{{ $sidebarItemKey }}"
                        data-sidebar-item="{{ $sidebarItemKey }}"
                        wire:navigate
                    >
                        {{ $item['label'] }}
                    </flux:sidebar.item>
                @endif
            @endforeach
        </flux:sidebar.group>
    @endforeach
@endif
