<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ServiceCatalog extends Model
{
    protected $table = 'service_catalog';

    protected $fillable = [
        'code',
        'name',
        'category',
        'base_price',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return array<int, array{code: string, name: string, category: string, base_price: float|null, is_active: bool, description: string}>
     */
    public static function defaultCatalog(): array
    {
        return [
            [
                'code' => 'aircon',
                'name' => 'Aircon cleaning',
                'category' => 'Cooling',
                'base_price' => null,
                'is_active' => true,
                'description' => 'Cleaning and maintenance for household air-conditioning units.',
            ],
            [
                'code' => 'aircon-diagnosis',
                'name' => 'Aircon diagnosis',
                'category' => 'Cooling',
                'base_price' => null,
                'is_active' => true,
                'description' => 'Find the cause of an air-conditioning problem before repair.',
            ],
            [
                'code' => 'appliance',
                'name' => 'Appliance repair',
                'category' => 'Appliances',
                'base_price' => null,
                'is_active' => true,
                'description' => 'Repair support for common household appliances.',
            ],
            [
                'code' => 'appliance-diagnosis',
                'name' => 'Appliance diagnosis',
                'category' => 'Appliances',
                'base_price' => null,
                'is_active' => true,
                'description' => 'Find the cause of an appliance problem before repair.',
            ],
            [
                'code' => 'electrical',
                'name' => 'Electrical repair',
                'category' => 'Electrical',
                'base_price' => null,
                'is_active' => true,
                'description' => 'Diagnosis and repair for common household electrical issues.',
            ],
            [
                'code' => 'electrical-diagnosis',
                'name' => 'Electrical diagnosis',
                'category' => 'Electrical',
                'base_price' => null,
                'is_active' => true,
                'description' => 'Find the cause of an electrical problem before repair.',
            ],
            [
                'code' => 'electronics-battery',
                'name' => 'Battery replacement',
                'category' => 'Electronics',
                'base_price' => null,
                'is_active' => true,
                'description' => 'Replace weak or damaged batteries in phones, tablets, and devices.',
            ],
            [
                'code' => 'electronics-diagnosis',
                'name' => 'Not sure — diagnose my device',
                'category' => 'Electronics',
                'base_price' => null,
                'is_active' => true,
                'description' => 'Have a technician inspect the device and identify the problem.',
            ],
            [
                'code' => 'electronics-lcd',
                'name' => 'LCD / screen replacement',
                'category' => 'Electronics',
                'base_price' => null,
                'is_active' => true,
                'description' => 'Replace cracked, broken, or malfunctioning device screens.',
            ],
            [
                'code' => 'plumbing',
                'name' => 'Plumbing repair',
                'category' => 'Plumbing',
                'base_price' => null,
                'is_active' => true,
                'description' => 'Repair support for common household plumbing issues.',
            ],
            [
                'code' => 'plumbing-diagnosis',
                'name' => 'Plumbing diagnosis',
                'category' => 'Plumbing',
                'base_price' => null,
                'is_active' => true,
                'description' => 'Find the cause of a plumbing problem before repair.',
            ],
        ];
    }

    /** @return Collection<int, ServiceCatalog> */
    public static function activeCatalog(): Collection
    {
        if (! Schema::hasTable((new self)->getTable())) {
            return collect();
        }

        $catalog = self::query()
            ->get(['code', 'name', 'category', 'base_price', 'is_active', 'description'])
            ->keyBy('code');
        $defaults = collect(self::defaultCatalog())
            ->map(static fn (array $default): ServiceCatalog => new self($default))
            ->keyBy('code');

        return $defaults
            ->map(function (ServiceCatalog $default, string $code) use ($catalog): ?ServiceCatalog {
                $service = $catalog->get($code);

                return $service === null
                    ? $default
                    : ($service->is_active ? $service : null);
            })
            ->filter()
            ->merge($catalog->filter(fn (ServiceCatalog $service): bool => $service->is_active && ! $defaults->has($service->code)))
            ->sortBy('name')
            ->values();
    }

    /** @return array<int, string> */
    public static function activeCodes(): array
    {
        return self::activeCatalog()
            ->pluck('code')
            ->map(static fn (mixed $code): string => (string) $code)
            ->all();
    }

    /**
     * @param  array<int, mixed>  $codes
     * @return array<int, string>
     */
    public static function normalizeCodes(array $codes): array
    {
        $availableCodes = collect(self::activeCodes())
            ->mapWithKeys(static fn (string $code): array => [Str::lower($code) => $code]);

        return collect($codes)
            ->map(static function (mixed $code) use ($availableCodes): ?string {
                $key = Str::lower(trim((string) $code));

                if ($key === '') {
                    return null;
                }

                $key = self::legacyAliases()[$key] ?? $key;

                return $availableCodes->get($key);
            })
            ->filter(static fn (?string $code): bool => $code !== null)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $categories
     * @return array<int, string>
     */
    public static function matchingCodes(array $categories): array
    {
        $canonicalCodes = self::normalizeCodes($categories);
        $activeCodes = self::activeCodes();

        return collect($canonicalCodes)
            ->flatMap(function (string $code) use ($activeCodes): array {
                $specializedCodes = collect($activeCodes)
                    ->filter(fn (string $activeCode): bool => $activeCode === $code || Str::startsWith($activeCode, "{$code}-"))
                    ->all();
                $legacyCodes = collect(self::legacyAliases())
                    ->filter(fn (string $target): bool => Str::lower($target) === Str::lower($code))
                    ->keys()
                    ->flatMap(static fn (string $alias): array => [$alias, Str::upper($alias)])
                    ->all();

                return [...$specializedCodes, ...$legacyCodes];
            })
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<string, string> */
    private static function legacyAliases(): array
    {
        return [
            'ac-clean' => 'aircon',
            'pl-repair' => 'plumbing',
            'el-repair' => 'electrical',
        ];
    }
}
