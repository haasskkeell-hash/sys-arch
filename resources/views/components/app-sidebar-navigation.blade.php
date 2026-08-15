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
                        ['label' => __('Verification & Profile'), 'icon' => 'identification', 'slug' => 'verification-profile'],
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
                        ['label' => __('Addresses'), 'icon' => 'map-pin', 'slug' => 'addresses'],
                        ['label' => __('Ratings & Reviews'), 'icon' => 'star', 'slug' => 'ratings-reviews'],
                        ['label' => __('Notifications'), 'icon' => 'bell', 'slug' => 'notifications'],
                        ['label' => __('Support & Help'), 'icon' => 'chat-bubble-left-right', 'slug' => 'support'],
                        ['label' => __('Settings'), 'icon' => 'cog', 'slug' => 'settings'],
                    ],
                ],
            ]);
@endphp

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
