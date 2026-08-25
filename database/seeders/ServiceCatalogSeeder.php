<?php

namespace Database\Seeders;

use App\Models\ServiceCatalog;
use Illuminate\Database\Seeder;

class ServiceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ServiceCatalog::defaultCatalog() as $service) {
            ServiceCatalog::query()->firstOrCreate(['code' => $service['code']], $service);
        }
    }
}
