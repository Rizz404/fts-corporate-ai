<?php

namespace Database\Factories;

use App\Models\SupportTeam;
use App\Modules\Identity\Enums\SupportTeamCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Support teams are reference data; prefer ReferenceSeeder.
 *
 * @extends Factory<SupportTeam>
 */
class SupportTeamFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = fake()->randomElement(SupportTeamCode::cases());

        return [
            'code' => $code,
            'name' => strtoupper($code->value),
            'is_active' => true,
        ];
    }
}
