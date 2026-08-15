<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $validated = Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'role' => ['required', Rule::in(['customer', 'technician'])],
            'phone' => ['nullable', 'required_if:role,technician', 'string', 'max:30'],
            'service_category' => ['nullable', 'required_if:role,technician', Rule::in(['aircon', 'plumbing', 'electrical', 'appliance'])],
            'service_area' => ['nullable', 'required_if:role,technician', 'string', 'max:255'],
            'years_experience' => ['nullable', 'required_if:role,technician', 'integer', 'min:0', 'max:60'],
        ])->validate();

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
                'service_categories' => [$validated['service_category']],
                'years_experience' => (int) $validated['years_experience'],
                'service_area' => $validated['service_area'],
                'phone' => $validated['phone'],
                'submitted_at' => now(),
            ]);
        }

        return $user;
    }
}
