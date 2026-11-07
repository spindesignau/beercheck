<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Demo\DemoContentService;
use Illuminate\Database\Seeder;

/** Local-only: demo admin + the same fictional demo content used on staging. Admin login: admin@beercheck.test / password */
class DemoSeeder extends Seeder
{
    public function run(DemoContentService $demo): void
    {
        User::factory()->admin()->create(['username' => 'admin', 'email' => 'admin@beercheck.test']);
        $demo->seed();
    }
}
