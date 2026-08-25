<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

class DemoTechniciansSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();
        $technicians = [
            [
                'name' => 'Jivan',
                'email' => 'jivan@fixtrack.test',
                'phone' => '+639171000001',
                'service_categories' => ['plumbing', 'aircon'],
                'years_experience' => 4,
                'address' => '29 Zapote Street, Barangay 132, Bagong Barrio, Caloocan City, Metro Manila',
            ],
            [
                'name' => 'Kingjames',
                'email' => 'kingjames@fixtrack.test',
                'phone' => '+639171000002',
                'service_categories' => ['plumbing', 'electrical'],
                'years_experience' => 5,
                'address' => '58 Zapote Street, Barangay 132, Bagong Barrio, Caloocan City, Metro Manila',
            ],
        ];

        foreach ($technicians as $technicianData) {
            $technician = User::query()->firstOrNew(['email' => $technicianData['email']]);

            if ($technician->exists && ! $technician->isTechnician()) {
                throw new RuntimeException("The account {$technicianData['email']} already belongs to a non-technician user.");
            }

            $technician->fill([
                'name' => $technicianData['name'],
                'role' => 'technician',
                'phone' => $technicianData['phone'],
                'account_status' => 'active',
                'availability_status' => 'available',
                'last_seen_at' => $now,
            ]);

            if (! $technician->exists) {
                $technician->password = 'password';
            }

            $technician->email_verified_at = $now;
            $technician->save();

            $technician->technicianVerification()->updateOrCreate([], [
                'status' => 'approved',
                'risk_level' => 'low',
                'service_categories' => $technicianData['service_categories'],
                'years_experience' => $technicianData['years_experience'],
                'service_area' => 'Caloocan, Metro Manila',
                'phone' => $technicianData['phone'],
                'address' => $technicianData['address'],
                'decision_reason' => 'Approved demo technician account.',
                'submitted_at' => $now,
                'reviewed_at' => $now,
            ]);
        }
    }
}
