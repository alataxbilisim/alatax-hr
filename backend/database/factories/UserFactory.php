<?php

namespace Database\Factories;

use App\Enums\UserType;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'type' => UserType::User,
            'is_active' => true,
            'preferences' => ['theme' => 'dark', 'locale' => 'tr'],
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => UserType::SuperAdmin,
            'company_id' => null,
        ]);
    }

    public function companyAdmin(?Company $company = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => UserType::CompanyAdmin,
            'company_id' => $company?->id ?? Company::factory(),
        ]);
    }

    /**
     * Normal portal kullanıcısı (UserType::User). Personel kaydı ayrı factory ile eklenir.
     */
    public function regularUser(?Company $company = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => UserType::User,
            'company_id' => $company?->id ?? Company::factory(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
