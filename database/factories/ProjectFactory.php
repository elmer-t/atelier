<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Enums\ProjectVisibility;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->company(),
            'status' => ProjectStatus::Active,
            'visibility' => ProjectVisibility::Private,
            'password_hash' => Hash::make('secret'),
        ];
    }

    public function public(): static
    {
        return $this->state(fn () => [
            'visibility' => ProjectVisibility::Public,
            'password_hash' => null,
        ]);
    }

    public function private(string $password = 'secret'): static
    {
        return $this->state(fn () => [
            'visibility' => ProjectVisibility::Private,
            'password_hash' => Hash::make($password),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => ProjectStatus::Archived]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function expiresAt(\DateTimeInterface $when): static
    {
        return $this->state(fn () => ['expires_at' => $when]);
    }
}
