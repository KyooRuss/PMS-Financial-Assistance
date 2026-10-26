<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use App\Models\WaterDistrict;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Every seeded account uses the password "password".
     */
    public function run(): void
    {
        $this->call(RequirementSeeder::class);

        // One sample water district per category, so both regular and emergency rules can be tested.
        $districts = collect([
            ['name' => 'Bicol Water District', 'category' => 'A'],
            ['name' => 'La Union Water District', 'category' => 'B'],
            ['name' => 'Baguio Water District', 'category' => 'C'],
            ['name' => 'Rizal Water District', 'category' => 'D'],
        ])->map(fn (array $district) => WaterDistrict::firstOrCreate(['name' => $district['name']], $district));

        // LWD accounts: lwd.a@pms.test ... lwd.d@pms.test
        foreach ($districts as $district) {
            $this->account(
                email: 'lwd.'.strtolower($district->category).'@pms.test',
                role: Role::Lwd,
                firstName: 'Juan',
                lastName: 'Dela Cruz',
                waterDistrictId: $district->id,
            );
        }

        // One account per LWUA office: wsudd@pms.test, lwrd@pms.test, ...
        foreach (Role::cases() as $role) {
            if ($role->isLwuaOffice()) {
                $this->account(email: "{$role->value}@pms.test", role: $role, firstName: $role->label(), lastName: 'Account');
            }
        }

        if (app()->isLocal()) {
            $this->call(SampleProjectSeeder::class);
        }
    }

    private function account(string $email, Role $role, string $firstName, string $lastName, ?int $waterDistrictId = null): void
    {
        User::firstOrCreate(['email' => $email], User::factory()->raw([
            'email' => $email,
            'first_name' => $firstName,
            'middle_name' => null,
            'last_name' => $lastName,
            'role' => $role,
            'water_district_id' => $waterDistrictId,
        ]));
    }
}
