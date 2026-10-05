<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Reference data only. Accounts are created with corporate:create-admin
     * (and corporate:demo-accounts in local/testing).
     */
    public function run(): void
    {
        $this->call(ReferenceSeeder::class);
    }
}
