@php
    $realtimeInterval = match ($moduleSlug) {
        'dispatch-monitor', 'walk-in-queue' => 5000,
        'service-bookings', 'payments-revenue', 'technician-verification' => 60000,
        default => null,
    };
    $realtimeScope = $realtimeInterval ? "admin-{$moduleSlug}" : null;
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
            <flux:breadcrumbs.item :href="route('admin.dashboard')" wire:navigate>Super Admin</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $module['label'] }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-x-6 gap-y-4">
            <div class="min-w-0">
                <flux:heading size="xl" level="1">{{ $module['label'] }}</flux:heading>
                <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">{{ $module['description'] }}</flux:text>
            </div>

            <div class="flex items-center gap-2">
                @if ($moduleConnections !== [])
                    <x-super-admin.section-menu :actions="$moduleConnections" :label="__('Related modules')" />
                @endif
                <flux:badge :color="$moduleState['color']" size="lg" data-super-admin-module-state="{{ $moduleState['label'] }}">{{ $moduleState['label'] }}</flux:badge>
            </div>
        </div>

        @if ($moduleSlug === 'system-health')
            <div class="mt-4 flex flex-wrap gap-2">
                <flux:button size="sm" variant="primary" icon="arrow-path" wire:click="requestConfirmation('run-scheduler')">
                    Run scheduler
                </flux:button>
                <flux:button size="sm" variant="primary" icon="trash" wire:click="requestConfirmation('clear-cache')">
                    Clear cache
                </flux:button>
                <flux:button size="sm" variant="primary" icon="arrow-path-rounded-square" wire:click="requestConfirmation('retry-failed-jobs')">
                    Retry failed jobs
                </flux:button>
            </div>
        @endif

        @if ($moduleSlug === 'platform-settings')
            <div class="mt-4 flex flex-wrap gap-2">
                <flux:button size="sm" variant="primary" icon="sparkles" wire:click="requestConfirmation('initialize-settings')">
                    Initialize controls
                </flux:button>
            </div>
        @endif

        @if ($moduleSlug === 'service-catalog')
            <div class="mt-4 flex flex-wrap gap-2">
                <flux:button size="sm" variant="primary" icon="plus" wire:click="openCatalogEditor">
                    Add service
                </flux:button>
            </div>
        @endif
    </div>

    <div class="dashboard-stagger grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($content['stats'] as $stat)
            @php
                $statLabel = strtolower($stat['label']);
                $statValue = strtolower($stat['value']);
                $statTone = match (true) {
                    str_contains($statLabel, 'failed'), str_contains($statLabel, 'cancelled'), str_contains($statLabel, 'urgent'), str_contains($statLabel, 'high risk'), str_contains($statLabel, 'flagged') => [
                        'border' => '!border-s-rose-400',
                        'icon' => 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400',
                    ],
                    str_contains($statLabel, 'pending'), str_contains($statLabel, 'unassigned'), str_contains($statLabel, 'waiting'), str_contains($statLabel, 'quotation'), str_contains($statLabel, 'suspended') => [
                        'border' => '!border-s-amber-400',
                        'icon' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400',
                    ],
                    str_contains($statLabel, 'active'), str_contains($statLabel, 'completed'), str_contains($statLabel, 'approved'), str_contains($statLabel, 'available'), str_contains($statLabel, 'healthy'), str_contains($statLabel, 'online'), str_contains($statLabel, 'published'), str_contains($statLabel, 'resolved'), str_contains($statValue, 'online'), str_contains($statValue, 'healthy'), str_contains($statValue, 'ready') => [
                        'border' => '!border-s-emerald-400',
                        'icon' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400',
                    ],
                    default => [
                        'border' => '!border-s-violet-400',
                        'icon' => 'bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400',
                    ],
                };
            @endphp
            <flux:card
                class="dashboard-reveal card-lift shadow-sm !border-s-4 {{ $statTone['border'] }}"
            >
                <div class="flex items-center gap-4">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-lg {{ $statTone['icon'] }}">
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

    @if ($moduleSlug === 'reports-analytics' && isset($content['chart']))
        <flux:card class="dashboard-reveal card-lift flex flex-col shadow-sm" data-super-admin-analytics-chart data-super-admin-analytics-layout="compact">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <flux:heading size="lg">Booking performance trend</flux:heading>
                    <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">Seven-day view of active, completed, and cancelled bookings.</flux:text>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2">
                    <div class="flex flex-wrap gap-3 text-xs text-zinc-500 dark:text-zinc-400" data-super-admin-analytics-legend>
                        <span class="inline-flex items-center gap-1.5"><span class="size-1.5 shrink-0 rounded-[1px] bg-blue-500"></span>Active</span>
                        <span class="inline-flex items-center gap-1.5"><span class="size-1.5 shrink-0 rounded-[1px] bg-emerald-500"></span>Completed</span>
                        <span class="inline-flex items-center gap-1.5"><span class="size-1.5 shrink-0 rounded-[1px] bg-red-500"></span>Cancelled</span>
                    </div>
                    <flux:button
                        variant="primary"
                        size="sm"
                        icon="arrow-down-tray"
                        wire:click="exportAnalytics"
                        wire:loading.attr="disabled"
                        wire:target="exportAnalytics"
                        data-super-admin-analytics-export
                    >
                        Export results
                    </flux:button>
                    <x-super-admin.section-menu :actions="[
                        ['label' => __('Refresh section'), 'icon' => 'arrow-path', 'wire' => '$refresh'],
                    ]" />
                </div>
            </div>

            <div class="relative mt-4 h-48 overflow-hidden rounded-xl border border-zinc-200/80 bg-zinc-50/70 p-3 dark:border-white/10 dark:bg-white/[0.03]" data-super-admin-analytics-chart-plot>
                <svg viewBox="0 0 700 220" class="h-full w-full" role="img" aria-label="Seven-day analytics trend for active, completed, and cancelled bookings" preserveAspectRatio="none">
                    <g data-super-admin-analytics-horizontal-grid>
                        <line x1="42" y1="180" x2="660" y2="180" class="stroke-zinc-200/80 dark:stroke-white/[0.08]" />
                        <line x1="42" y1="108" x2="660" y2="108" class="stroke-zinc-200/60 dark:stroke-white/[0.06]" />
                        <line x1="42" y1="36" x2="660" y2="36" class="stroke-zinc-200/60 dark:stroke-white/[0.06]" />
                        <text x="26" y="184" text-anchor="end" class="fill-zinc-400 text-[10px] dark:fill-zinc-500">0</text>
                        <text x="26" y="112" text-anchor="end" class="fill-zinc-400 text-[10px] dark:fill-zinc-500">{{ (int) ceil($content['chart']['maxValue'] / 2) }}</text>
                        <text x="26" y="40" text-anchor="end" class="fill-zinc-400 text-[10px] dark:fill-zinc-500">{{ $content['chart']['maxValue'] }}</text>
                    </g>

                    <polygon data-super-admin-analytics-area="active" points="{{ $content['chart']['areas']['active'] }}" class="fill-blue-500 dark:fill-blue-400" fill-opacity="0.09" />
                    <polygon data-super-admin-analytics-area="completed" points="{{ $content['chart']['areas']['completed'] }}" class="fill-emerald-500 dark:fill-emerald-400" fill-opacity="0.09" />
                    <polygon data-super-admin-analytics-area="cancelled" points="{{ $content['chart']['areas']['cancelled'] }}" class="fill-red-500 dark:fill-red-400" fill-opacity="0.09" />

                    <polyline data-super-admin-analytics-series="active" points="{{ $content['chart']['points']['active'] }}" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="stroke-blue-500 dark:stroke-blue-400" vector-effect="non-scaling-stroke" />
                    <polyline data-super-admin-analytics-series="completed" points="{{ $content['chart']['points']['completed'] }}" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="stroke-emerald-500 dark:stroke-emerald-400" vector-effect="non-scaling-stroke" />
                    <polyline data-super-admin-analytics-series="cancelled" points="{{ $content['chart']['points']['cancelled'] }}" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="stroke-red-500 dark:stroke-red-400" vector-effect="non-scaling-stroke" />

                    @foreach ($content['chart']['days'] as $day)
                        <circle cx="{{ $day['x'] }}" cy="{{ $day['activeY'] }}" r="3" stroke-width="1.5" class="fill-zinc-50 stroke-blue-500 dark:fill-zinc-900 dark:stroke-blue-400" />
                        <circle cx="{{ $day['x'] }}" cy="{{ $day['completedY'] }}" r="3" stroke-width="1.5" class="fill-zinc-50 stroke-emerald-500 dark:fill-zinc-900 dark:stroke-emerald-400" />
                        <circle cx="{{ $day['x'] }}" cy="{{ $day['cancelledY'] }}" r="3" stroke-width="1.5" class="fill-zinc-50 stroke-red-500 dark:fill-zinc-900 dark:stroke-red-400" />
                        <text x="{{ $day['x'] }}" y="207" text-anchor="middle" class="fill-zinc-400 text-[11px] dark:fill-zinc-500">{{ $day['label'] }}</text>
                    @endforeach
                </svg>
            </div>
        </flux:card>
    @endif

    <div class="dashboard-stagger space-y-6">
        @foreach ($content['sections'] as $section)
            <div class="dashboard-reveal" style="animation-delay: {{ ($loop->index + 1) * 60 }}ms">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <flux:heading size="lg">{{ $section['title'] }}</flux:heading>
                        <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">{{ $section['subtitle'] }}</flux:text>
                    </div>

                    @php
                        $sectionActions = [
                            ['label' => __('Refresh section'), 'icon' => 'arrow-path', 'wire' => '$refresh'],
                        ];
                    @endphp

                    @if ($moduleSlug === 'system-health' && $section['title'] === 'Service checks')
                        @php($sectionActions[] = ['label' => __('Clear cache'), 'icon' => 'trash', 'wire' => "requestConfirmation('clear-cache')"])
                    @endif

                    <div class="flex items-center gap-1">
                        <flux:badge color="zinc" size="sm">{{ count($section['rows']) }} shown</flux:badge>
                        <x-super-admin.section-menu :actions="$sectionActions" />
                    </div>
                </div>

                <div class="app-table-shell super-admin-scroll-table mt-4 overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-white/10 dark:bg-zinc-900" data-app-table-shell data-super-admin-module-table>
                    <flux:table class="w-full">
                        <flux:table.columns>
                            @foreach ($section['columns'] as $column)
                                <flux:table.column
                                    class="sticky top-0 z-10 bg-white dark:bg-zinc-900 first:!ps-6 last:!pe-6 first:min-w-44 last:w-32"
                                    data-super-admin-column="{{ $column['key'] }}"
                                    data-super-admin-column-type="{{ $column['type'] }}"
                                >{{ $column['label'] }}</flux:table.column>
                            @endforeach
                            @if (! empty($section['action']))
                                <flux:table.column align="end" class="sticky top-0 z-10 w-24 !pe-8 bg-white dark:bg-zinc-900" data-app-table-actions>Actions</flux:table.column>
                            @endif
                        </flux:table.columns>

                        <flux:table.rows>
                            @forelse ($section['rows'] as $row)
                                <flux:table.row :key="$moduleSlug.'-'.$loop->index" class="transition-colors hover:bg-zinc-500/[0.05] dark:hover:bg-white/[0.05]">
                                    @php($rowId = $section['rowIds'][$loop->index] ?? null)
                                    @foreach ($section['columns'] as $columnIndex => $column)
                                        @if ($loop->first)
                                            <flux:table.cell variant="strong" class="whitespace-normal break-words align-top first:!ps-6 last:!pe-6">
                                                <x-super-admin.table-cell :value="$row[$columnIndex] ?? '—'" :type="$column['type']" />
                                            </flux:table.cell>
                                        @else
                                            <flux:table.cell class="whitespace-normal break-words align-top text-zinc-500 dark:text-zinc-400 first:!ps-6 last:!pe-6">
                                                <x-super-admin.table-cell :value="$row[$columnIndex] ?? '—'" :type="$column['type']" />
                                            </flux:table.cell>
                                        @endif
                                    @endforeach
                                    @if (! empty($section['action']) && $rowId)
                                        <flux:table.cell class="w-24 !pe-8 text-right" data-app-table-actions>
                                            <x-table-actions>
                                                    @switch($section['action'])
                                                        @case('users')
                                                            <flux:menu.item as="button" type="button" icon="shield-check" wire:click="requestConfirmation('make-admin', {{ $rowId }})">Make admin</flux:menu.item>
                                                            <flux:menu.item as="button" type="button" icon="wrench-screwdriver" wire:click="requestConfirmation('make-technician', {{ $rowId }})">Make technician</flux:menu.item>
                                                            <flux:menu.item as="button" type="button" icon="user" wire:click="requestConfirmation('make-customer', {{ $rowId }})">Make customer</flux:menu.item>
                                                            <flux:menu.item as="button" type="button" icon="check-circle" wire:click="updateUserStatus({{ $rowId }}, 'active')">Activate account</flux:menu.item>
                                                            <flux:menu.item as="button" type="button" icon="no-symbol" wire:click="requestConfirmation('suspend-user', {{ $rowId }})">Suspend account</flux:menu.item>
                                                            <flux:menu.separator />
                                                            <flux:menu.item as="button" type="button" icon="arrow-left-start-on-rectangle" wire:click="requestConfirmation('revoke-user-sessions', {{ $rowId }})">Revoke sessions</flux:menu.item>
                                                            @break
                                                        @case('verification')
                                                            <flux:menu.item as="button" type="button" icon="clock" wire:click="updateVerificationStatus({{ $rowId }}, 'under_review')">Mark under review</flux:menu.item>
                                                            <flux:menu.item as="button" type="button" icon="check-circle" wire:click="requestConfirmation('approve-verification', {{ $rowId }})">Approve verification</flux:menu.item>
                                                            <flux:menu.item as="button" type="button" icon="information-circle" wire:click="requestConfirmation('request-verification-information', {{ $rowId }})">Request information</flux:menu.item>
                                                            <flux:menu.item as="button" type="button" icon="x-circle" wire:click="requestConfirmation('reject-verification', {{ $rowId }})">Reject verification</flux:menu.item>
                                                            <flux:menu.item as="button" type="button" icon="no-symbol" wire:click="requestConfirmation('suspend-verification', {{ $rowId }})">Suspend verification</flux:menu.item>
                                                            @break
                                                        @case('bookings')
                                                        @case('dispatch-bookings')
                                                            @if ($section['action'] === 'dispatch-bookings')
                                                                <flux:menu.item as="button" type="button" icon="user-plus" wire:click="openAssignment({{ $rowId }})">Assign technician</flux:menu.item>
                                                            @endif
                                                            <flux:menu.item as="button" type="button" icon="arrow-path" wire:click="updateBookingStatus({{ $rowId }}, 'matching')">Return to matching</flux:menu.item>
                                                            <flux:menu.item as="button" type="button" icon="play" wire:click="requestConfirmation('start-booking', {{ $rowId }})">Start job</flux:menu.item>
                                                            <flux:menu.item as="button" type="button" icon="check-circle" wire:click="requestConfirmation('complete-booking', {{ $rowId }})">Complete job</flux:menu.item>
                                                            <flux:menu.item as="button" type="button" icon="x-circle" wire:click="requestConfirmation('cancel-booking', {{ $rowId }})">Cancel booking</flux:menu.item>
                                                            <flux:menu.item as="button" type="button" icon="exclamation-triangle" wire:click="requestConfirmation('mark-no-show', {{ $rowId }})">Mark no-show</flux:menu.item>
                                                            @break
                                                        @case('technicians')
                                                            <flux:menu.item as="button" type="button" icon="user-plus" wire:click="updateTechnicianAvailability({{ $rowId }}, 'available')">Set available</flux:menu.item>
                                                            <flux:menu.item as="button" type="button" icon="bolt" wire:click="updateTechnicianAvailability({{ $rowId }}, 'busy')">Set busy</flux:menu.item>
                                                            <flux:menu.item as="button" type="button" icon="pause-circle" wire:click="updateTechnicianAvailability({{ $rowId }}, 'offline')">Set offline</flux:menu.item>
                                                            @break
                                                        @case('walk-in')
                                                            <flux:menu.item as="button" type="button" icon="phone" wire:click="updateWalkInStatus({{ $rowId }}, 'called')">Call customer</flux:menu.item>
                                                            <flux:menu.item as="button" type="button" icon="play" wire:click="updateWalkInStatus({{ $rowId }}, 'serving')">Start service</flux:menu.item>
                                                            <flux:menu.item as="button" type="button" icon="check-circle" wire:click="updateWalkInStatus({{ $rowId }}, 'completed')">Complete service</flux:menu.item>
                                                            <flux:menu.item as="button" type="button" icon="star" wire:click="updateWalkInPriority({{ $rowId }}, 'priority')">Mark priority</flux:menu.item>
                                                            @break
                                                        @case('payments')
                                                            <flux:menu.item as="button" type="button" icon="check-circle" wire:click="requestConfirmation('mark-payment-paid', {{ $rowId }})">Mark paid</flux:menu.item>
                                                            <flux:menu.item as="button" type="button" icon="arrow-uturn-left" wire:click="requestConfirmation('refund-payment', {{ $rowId }})">Refund payment</flux:menu.item>
                                                            <flux:menu.item as="button" type="button" icon="exclamation-triangle" wire:click="updatePaymentStatus({{ $rowId }}, 'failed')">Mark failed</flux:menu.item>
                                                            @break
                                                        @case('reviews')
                                                            <flux:menu.item as="button" type="button" icon="eye" wire:click="updateReviewStatus({{ $rowId }}, 'published')">Publish review</flux:menu.item>
                                                            <flux:menu.item as="button" type="button" icon="flag" wire:click="updateReviewStatus({{ $rowId }}, 'flagged')">Flag review</flux:menu.item>
                                                            <flux:menu.item as="button" type="button" icon="eye-slash" wire:click="requestConfirmation('hide-review', {{ $rowId }})">Hide review</flux:menu.item>
                                                            @break
                                                        @case('support')
                                                            <flux:menu.item as="button" type="button" icon="pencil-square" wire:click="openSupportEditor({{ $rowId }})">Update ticket</flux:menu.item>
                                                            <flux:menu.item as="button" type="button" icon="arrow-path" wire:click="updateSupportStatus({{ $rowId }}, 'in_progress')">Start handling</flux:menu.item>
                                                            <flux:menu.item as="button" type="button" icon="check-circle" wire:click="updateSupportStatus({{ $rowId }}, 'resolved')">Resolve ticket</flux:menu.item>
                                                            <flux:menu.item as="button" type="button" icon="user-plus" wire:click="assignSupportTicket({{ $rowId }})">Assign to me</flux:menu.item>
                                                            @break
                                                        @case('catalog')
                                                            <flux:menu.item as="button" type="button" icon="pencil-square" wire:click="openCatalogEditor({{ $rowId }})">Edit service</flux:menu.item>
                                                            <flux:menu.item as="button" type="button" icon="check-circle" wire:click="updateCatalogStatus({{ $rowId }}, true)">Activate service</flux:menu.item>
                                                            <flux:menu.item as="button" type="button" icon="pause-circle" wire:click="requestConfirmation('deactivate-service', {{ $rowId }})">Deactivate service</flux:menu.item>
                                                            @break
                                                        @case('settings')
                                                            <flux:menu.item as="button" type="button" icon="pencil-square" wire:click="openSettingEditor({{ $rowId }})">Edit setting</flux:menu.item>
                                                            @break
                                                    @endswitch
                                            </x-table-actions>
                                        </flux:table.cell>
                                    @endif
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="{{ count($section['columns']) + (! empty($section['action']) ? 1 : 0) }}" class="py-0">
                                        <div class="flex flex-col items-center justify-center px-6 py-20 text-center">
                                            <div class="flex size-14 items-center justify-center rounded-2xl border border-dashed border-zinc-300 bg-zinc-50 text-zinc-400 dark:border-zinc-600 dark:bg-zinc-800/60 dark:text-zinc-500">
                                                <flux:icon :name="$module['icon']" class="size-6" />
                                            </div>
                                            <flux:heading size="lg" class="mt-5">Nothing to show yet</flux:heading>
                                            <flux:text class="mt-1.5 max-w-sm text-zinc-500 dark:text-zinc-400">{{ $section['empty'] }}</flux:text>
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>
            </div>
        @endforeach
    </div>

    <flux:modal
        name="super-admin-confirmation-modal"
        class="max-w-md"
        @close="cancelConfirmation"
        wire:model="showConfirmation"
        data-super-admin-confirmation-modal
    >
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">{{ $confirmationTitle }}</flux:heading>
                <flux:text>{{ $confirmationDescription }}</flux:text>
            </div>

            @if ($confirmationRequiresReason)
                <div class="space-y-1.5">
                    <flux:textarea wire:model="confirmationReason" label="Decision reason" rows="3" placeholder="Explain the decision for the activity history." />
                    @error('reason')
                        <flux:text class="text-sm text-rose-600 dark:text-rose-400">{{ $message }}</flux:text>
                    @enderror
                </div>
            @endif

            <div class="flex justify-end gap-3">
                <flux:button variant="outline" wire:click="cancelConfirmation">
                    Cancel
                </flux:button>
                <flux:button
                    :variant="$confirmationVariant"
                    wire:click="executeConfirmedAction"
                    wire:loading.attr="disabled"
                    wire:target="executeConfirmedAction"
                >
                    {{ $confirmationActionLabel }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal
        name="super-admin-assignment-modal"
        class="max-w-lg"
        @close="cancelAssignment"
        wire:model="showAssignmentModal"
    >
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">Assign technician</flux:heading>
                <flux:text>Only active technicians with approved verification are available for dispatch.</flux:text>
            </div>

            @if (! empty($content['technicianOptions']))
                <flux:select wire:model="assignmentTechnicianId" label="Technician" placeholder="Choose a technician">
                    @foreach ($content['technicianOptions'] as $technician)
                        <flux:select.option value="{{ $technician['id'] }}">{{ $technician['name'] }} · {{ $technician['availability'] }}</flux:select.option>
                    @endforeach
                </flux:select>
            @else
                <flux:text class="rounded-lg border border-dashed border-zinc-300 p-4 text-zinc-500 dark:border-white/10 dark:text-zinc-400">No verified active technicians are available for assignment.</flux:text>
            @endif

            <div class="flex justify-end gap-3">
                <flux:button variant="outline" wire:click="cancelAssignment">Cancel</flux:button>
                <flux:button variant="primary" wire:click="saveAssignment" wire:loading.attr="disabled" wire:target="saveAssignment">Assign technician</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal
        name="super-admin-setting-editor"
        class="max-w-lg"
        @close="cancelSettingEditor"
        wire:model="showSettingEditor"
    >
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">Update platform setting</flux:heading>
                <flux:text>{{ $settingLabel }} · {{ $settingType }}</flux:text>
            </div>

            <flux:input wire:model="settingValue" label="Value" />

            <div class="flex justify-end gap-3">
                <flux:button variant="outline" wire:click="cancelSettingEditor">Cancel</flux:button>
                <flux:button variant="primary" wire:click="saveSetting" wire:loading.attr="disabled" wire:target="saveSetting">Save setting</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal
        name="super-admin-support-editor"
        class="max-w-lg"
        @close="cancelSupportEditor"
        wire:model="showSupportEditor"
    >
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">Update support ticket</flux:heading>
                <flux:text>Keep the customer-facing response and the operational status connected in one update.</flux:text>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="supportStatus" label="Status">
                    <flux:select.option value="open">Open</flux:select.option>
                    <flux:select.option value="in_progress">In progress</flux:select.option>
                    <flux:select.option value="resolved">Resolved</flux:select.option>
                    <flux:select.option value="closed">Closed</flux:select.option>
                </flux:select>
                <flux:select wire:model="supportPriority" label="Priority">
                    <flux:select.option value="low">Low</flux:select.option>
                    <flux:select.option value="normal">Normal</flux:select.option>
                    <flux:select.option value="high">High</flux:select.option>
                    <flux:select.option value="urgent">Urgent</flux:select.option>
                </flux:select>
            </div>
            <flux:textarea wire:model="supportMessage" label="Latest response" rows="5" placeholder="Add the latest response or internal handoff note." />

            <div class="flex justify-end gap-3">
                <flux:button variant="outline" wire:click="cancelSupportEditor">Cancel</flux:button>
                <flux:button variant="primary" wire:click="saveSupportTicket" wire:loading.attr="disabled" wire:target="saveSupportTicket">Save ticket</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal
        name="super-admin-catalog-editor"
        class="max-w-lg"
        @close="cancelCatalogEditor"
        wire:model="showCatalogEditor"
    >
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">{{ $editingCatalogId ? 'Edit service' : 'Add service' }}</flux:heading>
                <flux:text>Active catalog entries are available to customers when they book a service.</flux:text>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="catalogCode" label="Code" placeholder="AC-CLEAN" />
                <flux:input wire:model="catalogName" label="Service name" placeholder="Aircon Cleaning" />
                <flux:input wire:model="catalogCategory" label="Category" placeholder="Aircon" />
                <flux:input wire:model="catalogBasePrice" label="Base price" type="number" min="0" step="0.01" placeholder="850" />
            </div>
            <flux:textarea wire:model="catalogDescription" label="Description" rows="3" placeholder="Describe what this service includes." />

            <div class="flex justify-end gap-3">
                <flux:button variant="outline" wire:click="cancelCatalogEditor">Cancel</flux:button>
                <flux:button variant="primary" wire:click="saveCatalogService" wire:loading.attr="disabled" wire:target="saveCatalogService">Save service</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
