<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Enums\ProjectVisibility;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;
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
            'user_id' => fn (): int => $this->firstCreatorId(),
            'title' => fake()->unique()->company(),
            'status' => ProjectStatus::Active,
            'visibility' => ProjectVisibility::Private,
            'password_hash' => Hash::make('secret'),
        ];
    }

    /**
     * Own the project by the Creator already in the database, minting one only if
     * none exists. Reusing rather than always creating keeps a test that makes
     * several projects from also silently making several operators.
     */
    private function firstCreatorId(): int
    {
        return User::query()->where('role', UserRole::Creator)->orderBy('id')->value('id')
            ?? User::factory()->create(['role' => UserRole::Creator])->id;
    }

    /**
     * A project nobody owns — the shape of a row that predates ownership or whose
     * owner was deleted.
     */
    public function unowned(): static
    {
        return $this->state(fn () => ['user_id' => null]);
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
