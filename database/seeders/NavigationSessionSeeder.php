<?php

namespace Database\Seeders;

use App\Models\NavigationSession;
use Illuminate\Database\Seeder;

class NavigationSessionSeeder extends Seeder
{
    /** Synthetic local dashboard data only; deliberately excluded from DatabaseSeeder. */
    public function run(): void
    {
        if (app()->environment(['local', 'testing'])) {
            NavigationSession::factory()->cancelled()->create();
        }
    }
}
