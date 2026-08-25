<?php

namespace App\Livewire\SuperAdmin;

use App\Models\Booking;
use App\Models\ServiceCatalog;
use App\Models\TechnicianVerification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Super Admin Dashboard')]
class Dashboard extends Component
{
    /** @var array<string, bool> */
    private array $tableAvailability = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function render(): View
    {
        return view('livewire.super-admin.dashboard', [
            'firstName' => Str::before(auth()->user()->name, ' '),
            'stats' => $this->stats(),
            'bookingTrend' => $this->bookingTrend(),
            'statusBreakdown' => $this->statusBreakdown(),
            'recentBookings' => $this->recentBookings(),
            'topServices' => $this->topServices(),
            'needsAttention' => $this->needsAttention(),
            'systemStatus' => $this->systemStatus(),
        ]);
    }

    /** @return array<int, array{label: string, value: string, icon: string}> */
    private function stats(): array
    {
        $version = $this->latestTableTimestamp('bookings');

        return Cache::remember(
            "fixtrack:dashboard:stats:{$version}",
            now()->addSeconds(15),
            fn (): array => $this->calculateStats(),
        );
    }

    /** @return array<int, array{label: string, value: string, icon: string}> */
    private function calculateStats(): array
    {
        $totalBookings = 0;
        $activeBookings = 0;
        $bookingsToday = 0;
        $completedBookings = 0;
        $actionRequired = 0;

        if ($this->tableExists('bookings')) {
            $totalBookings = Booking::query()->count();
            $activeStatuses = ['pending', 'matching', 'assigned', 'en_route', 'in_progress'];
            $activeBookings = Booking::query()->whereIn('status', $activeStatuses)->count();
            $bookingsToday = Booking::query()->whereDate('created_at', today())->count();
            $completedBookings = Booking::query()->where('status', 'completed')->count();
            $actionRequired = Booking::query()->whereIn('status', ['pending', 'matching'])->count();
        }

        $completionRate = $totalBookings > 0
            ? Number::format($completedBookings / $totalBookings * 100, 1).'%'
            : '0.0%';

        return [
            ['label' => 'Active bookings', 'value' => (string) Number::format($activeBookings), 'icon' => 'clipboard-document-list'],
            ['label' => 'Bookings today', 'value' => (string) Number::format($bookingsToday), 'icon' => 'calendar-days'],
            ['label' => 'Completion rate', 'value' => $completionRate, 'icon' => 'chart-bar'],
            ['label' => 'Action required', 'value' => (string) Number::format($actionRequired), 'icon' => 'exclamation-triangle'],
        ];
    }

    private function latestTableTimestamp(string $table): string
    {
        if (! $this->tableExists($table) || ! Schema::hasColumn($table, 'updated_at')) {
            return 'none';
        }

        $updatedAt = $table === 'bookings' ? Booking::query()->max('updated_at') : null;

        return $updatedAt ? Carbon::parse($updatedAt)->toISOString() : 'none';
    }

    /**
     * @return array{
     *     days: array<int, array{label: string, active: int, completed: int, cancelled: int, x: int, activeY: int, completedY: int, cancelledY: int}>,
     *     paths: array{active: string, completed: string, cancelled: string},
     *     area: string,
     *     maxValue: int,
     *     summary: array{total: int, peakLabel: string, peakValue: int},
     * }
     */
    private function bookingTrend(): array
    {
        $trend = [];

        foreach (range(6, 0) as $daysAgo) {
            $date = now()->subDays($daysAgo)->startOfDay();
            $trend[$date->toDateString()] = [
                'label' => $date->format('D'),
                'active' => 0,
                'completed' => 0,
                'cancelled' => 0,
            ];
        }

        if ($this->tableExists('bookings')) {
            $rows = Booking::query()
                ->selectRaw('DATE(created_at) as day, status, COUNT(*) as total')
                ->where('created_at', '>=', now()->subDays(6)->startOfDay())
                ->groupByRaw('DATE(created_at), status')
                ->get();

            foreach ($rows as $row) {
                $day = (string) $row->day;

                if (! isset($trend[$day])) {
                    continue;
                }

                $bucket = match ((string) $row->status) {
                    'completed' => 'completed',
                    'cancelled' => 'cancelled',
                    'pending', 'matching', 'assigned', 'en_route', 'in_progress' => 'active',
                    default => null,
                };

                if ($bucket !== null) {
                    $trend[$day][$bucket] += (int) $row->total;
                }
            }
        }

        $maxValue = max(1, ...array_map(
            fn (array $day): int => max($day['active'], $day['completed'], $day['cancelled']),
            array_values($trend),
        ));

        $days = array_values($trend);

        foreach ($days as $index => &$day) {
            $day['x'] = 44 + $index * 102;
            $day['activeY'] = 180 - (int) round($day['active'] / $maxValue * 144);
            $day['completedY'] = 180 - (int) round($day['completed'] / $maxValue * 144);
            $day['cancelledY'] = 180 - (int) round($day['cancelled'] / $maxValue * 144);
        }
        unset($day);

        $paths = [];

        foreach (['active', 'completed', 'cancelled'] as $key) {
            $paths[$key] = $this->smoothChartPath($days, $key);
        }

        $total = 0;
        $peakLabel = '—';
        $peakValue = 0;

        foreach ($days as $day) {
            $dayTotal = $day['active'] + $day['completed'] + $day['cancelled'];
            $total += $dayTotal;

            if ($dayTotal > $peakValue) {
                $peakLabel = $day['label'];
                $peakValue = $dayTotal;
            }
        }

        return [
            'days' => $days,
            'paths' => [
                'active' => $paths['active'],
                'completed' => $paths['completed'],
                'cancelled' => $paths['cancelled'],
            ],
            'area' => $this->chartAreaPath($days, 'active'),
            'maxValue' => $maxValue,
            'summary' => [
                'total' => $total,
                'peakLabel' => $peakLabel,
                'peakValue' => $peakValue,
            ],
        ];
    }

    /**
     * @param  array<int, array{label: string, active: int, completed: int, cancelled: int, x: int, activeY: int, completedY: int, cancelledY: int}>  $days
     */
    private function smoothChartPath(array $days, string $series): string
    {
        $firstDay = $days[0];
        $path = 'M '.$firstDay['x'].' '.$firstDay[$series.'Y'];

        for ($index = 1, $count = count($days); $index < $count; $index++) {
            $previousDay = $days[$index - 1];
            $day = $days[$index];
            $middleX = (int) round(($previousDay['x'] + $day['x']) / 2);

            $path .= ' C '.$middleX.' '.$previousDay[$series.'Y'].', '.$middleX.' '.$day[$series.'Y'].', '.$day['x'].' '.$day[$series.'Y'];
        }

        return $path;
    }

    /**
     * @param  array<int, array{label: string, active: int, completed: int, cancelled: int, x: int, activeY: int, completedY: int, cancelledY: int}>  $days
     */
    private function chartAreaPath(array $days, string $series): string
    {
        $lastDay = $days[count($days) - 1];

        return $this->smoothChartPath($days, $series)
            .' L '.$lastDay['x'].' 180 L '.$days[0]['x'].' 180 Z';
    }

    /** @return array<int, array{key: string, label: string, value: int, percentage: int, color: string}> */
    private function statusBreakdown(): array
    {
        $counts = collect();

        if ($this->tableExists('bookings')) {
            $counts = Booking::query()
                ->select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status')
                ->map(fn (mixed $total): int => (int) $total);
        }

        $statuses = [
            ['key' => 'waiting', 'label' => 'Waiting', 'statuses' => ['pending', 'matching'], 'color' => 'violet'],
            ['key' => 'active', 'label' => 'Active', 'statuses' => ['assigned', 'en_route', 'in_progress'], 'color' => 'blue'],
            ['key' => 'completed', 'label' => 'Completed', 'statuses' => ['completed'], 'color' => 'emerald'],
            ['key' => 'cancelled', 'label' => 'Cancelled', 'statuses' => ['cancelled'], 'color' => 'rose'],
        ];
        $total = max(1, (int) collect($statuses)->sum(
            fn (array $status): int => (int) $counts->only($status['statuses'])->sum(),
        ));

        return array_map(function (array $status) use ($counts, $total): array {
            $value = (int) $counts->only($status['statuses'])->sum();

            return [
                'key' => $status['key'],
                'label' => $status['label'],
                'value' => $value,
                'percentage' => (int) round($value / $total * 100),
                'color' => $status['color'],
            ];
        }, $statuses);
    }

    /** @return array<int, array<string, mixed>> */
    private function recentBookings(): array
    {
        if (! $this->tableExists('bookings')) {
            return [];
        }

        return Booking::query()
            ->with('customer:id,name')
            ->select(['id', 'user_id', 'reference', 'service_type', 'status', 'is_priority', 'created_at'])
            ->latest('created_at')
            ->limit(6)
            ->get()
            ->map(fn (Booking $booking): array => [
                'reference' => (string) $booking->reference,
                'customer' => (string) data_get($booking->customer, 'name', '—'),
                'service' => $this->serviceLabel($booking->service_type),
                'status' => $this->statusLabel((string) $booking->status),
                'statusColor' => $this->statusColor((string) $booking->status),
                'priority' => (bool) $booking->is_priority,
                'createdAt' => $booking->created_at ? Carbon::parse($booking->created_at)->diffForHumans() : '—',
            ])
            ->all();
    }

    /** @return array<int, array{label: string, value: int, percentage: int}> */
    private function topServices(): array
    {
        if (! $this->tableExists('bookings')) {
            return [];
        }

        $services = Booking::query()
            ->select('service_type', DB::raw('COUNT(*) as total'))
            ->groupBy('service_type')
            ->orderByDesc('total')
            ->limit(5)
            ->get();
        $maxValue = max(1, (int) ($services->max('total') ?? 0));

        return $services->map(fn (Booking $service): array => [
            'label' => $this->serviceLabel($service->service_type),
            'value' => (int) $service->total,
            'percentage' => (int) round((int) $service->total / $maxValue * 100),
        ])->all();
    }

    /** @return array<int, array{title: string, detail: string, status: string, color: string}> */
    private function needsAttention(): array
    {
        $items = [];

        if ($this->tableExists('bookings')) {
            $items = Booking::query()
                ->with('customer:id,name')
                ->select(['id', 'user_id', 'reference', 'service_type', 'status'])
                ->whereIn('status', ['pending', 'matching'])
                ->oldest('created_at')
                ->limit(4)
                ->get()
                ->map(fn (Booking $booking): array => [
                    'title' => (string) $booking->reference,
                    'detail' => $this->serviceLabel($booking->service_type).' · '.((string) data_get($booking->customer, 'name', '—')),
                    'status' => $this->statusLabel((string) $booking->status),
                    'color' => $this->statusColor((string) $booking->status),
                ])
                ->all();
        }

        if ($this->tableExists('technician_verifications')) {
            $verificationItems = TechnicianVerification::query()
                ->with('technician:id,name')
                ->select(['id', 'user_id', 'status'])
                ->whereIn('technician_verifications.status', ['submitted', 'under_review'])
                ->oldest('submitted_at')
                ->limit(2)
                ->get()
                ->map(fn (TechnicianVerification $verification): array => [
                    'title' => 'Technician verification',
                    'detail' => (string) data_get($verification->technician, 'name', '—'),
                    'status' => 'Review',
                    'color' => 'violet',
                ])
                ->all();

            $items = [...$items, ...$verificationItems];
        }

        return array_slice($items, 0, 5);
    }

    /** @return array<int, array{label: string, value: string, detail: string, color: string}> */
    private function systemStatus(): array
    {
        $queueCount = $this->tableExists('jobs') ? DB::table('jobs')->count() : 0;
        $failedCount = $this->tableExists('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $schemaReady = $this->tableExists('bookings');

        return [
            [
                'label' => 'Application',
                'value' => 'Online',
                'detail' => 'Laravel '.app()->version(),
                'color' => 'emerald',
            ],
            [
                'label' => 'Operations schema',
                'value' => $schemaReady ? 'Ready' : 'Pending',
                'detail' => $schemaReady ? 'FixTrack tables available' : 'Waiting for migrated tables',
                'color' => $schemaReady ? 'emerald' : 'amber',
            ],
            [
                'label' => 'Queue pipeline',
                'value' => $failedCount > 0 ? 'Attention' : 'Healthy',
                'detail' => Number::format($queueCount).' pending · '.Number::format($failedCount).' failed',
                'color' => $failedCount > 0 ? 'amber' : 'emerald',
            ],
        ];
    }

    private function tableExists(string $table): bool
    {
        return $this->tableAvailability[$table] ??= Schema::hasTable($table);
    }

    private function statusLabel(string $status): string
    {
        return Str::headline($status);
    }

    private function serviceLabel(?string $service): string
    {
        $code = (string) $service;

        if (! $this->tableExists('service_catalog')) {
            return Str::headline($code);
        }

        $labels = once(fn (): array => ServiceCatalog::query()->pluck('name', 'code')->all());

        return (string) ($labels[$code] ?? Str::headline($code));
    }

    private function statusColor(string $status): string
    {
        return match ($status) {
            'completed' => 'emerald',
            'cancelled' => 'rose',
            'assigned', 'en_route', 'in_progress' => 'sky',
            'matching' => 'violet',
            default => 'amber',
        };
    }
}
