<?php

namespace Database\Factories;

use App\Models\Academy;
use App\Models\User;
use App\Platform\Subscriptions\StartAcademyTrial;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Academy> */
class AcademyFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company().' Academy';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'academy_type' => 'general',
            'country_code' => 'NG',
            'timezone' => 'Africa/Lagos',
            'currency' => 'NGN',
            'logo_path' => null,
            'primary_color' => null,
            'secondary_color' => null,
            'heading_color' => null,
            'created_by_user_id' => User::factory(),
        ];
    }

    /** Every academy starts on the platform's free trial, as it does when created at signup. */
    public function configure(): static
    {
        return $this->afterCreating(function (Academy $academy): void {
            app(StartAcademyTrial::class)->handle($academy);
        });
    }

    /** An academy with no subscription at all, so tests can attach their own. */
    public function withoutSubscription(): static
    {
        return $this->afterCreating(fn (Academy $academy) => $academy->subscription()->delete());
    }
}
