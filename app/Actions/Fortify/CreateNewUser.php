<?php

namespace App\Actions\Fortify;

use App\Academies\Actions\CreateAcademy;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Rules\PassesRecaptcha;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private readonly CreateAcademy $createAcademy) {}

    /** @param array<string, mixed> $input */
    public function create(array $input): User
    {
        /** @var array{name: string, email: string, password: string, academy_name: string, academy_type: string, timezone: string, currency: string} $validated */
        $validated = Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'academy_name' => ['required', 'string', 'max:160'],
            'academy_type' => ['required', 'string', 'max:80'],
            'timezone' => ['required', 'timezone'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'recaptcha_token' => [new PassesRecaptcha('register', request()->ip())],
        ])->validated();

        return DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            $this->createAcademy->handle($user, [
                'name' => $validated['academy_name'],
                'academy_type' => $validated['academy_type'],
                'timezone' => $validated['timezone'],
                'currency' => $validated['currency'],
            ]);

            return $user;
        });
    }
}
