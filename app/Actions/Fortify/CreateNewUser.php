<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\ServiceCatalog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        if (! array_key_exists('service_categories', $input) && isset($input['service_category'])) {
            $input['service_categories'] = [$input['service_category']];
        }

        $serviceCodes = ServiceCatalog::activeCodes();

        $validated = Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'role' => ['required', Rule::in(['customer', 'technician'])],
            'phone' => ['nullable', 'required_if:role,technician', 'string', 'max:30'],
            'service_category' => ['nullable', 'string', Rule::in($serviceCodes)],
            'service_categories' => ['nullable', 'required_if:role,technician', 'array', 'min:1'],
            'service_categories.*' => ['string', Rule::in($serviceCodes)],
            'service_area' => ['nullable', 'required_if:role,technician', 'string', 'max:255'],
            'years_experience' => ['nullable', 'required_if:role,technician', 'integer', 'min:0', 'max:60'],
        ])->validate();

        return DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => $validated['role'],
            ]);

            if ($user->isTechnician()) {
                $user->technicianVerification()->create([
                    'status' => 'submitted',
                    'risk_level' => 'low',
                    'service_categories' => ServiceCatalog::normalizeCodes($validated['service_categories']),
                    'years_experience' => (int) $validated['years_experience'],
                    'service_area' => $validated['service_area'],
                    'phone' => $validated['phone'],
                    'submitted_at' => now(),
                ]);
            }

            return $user;
        });
    }
}
