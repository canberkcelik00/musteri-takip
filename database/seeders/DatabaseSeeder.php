<?php

namespace Database\Seeders;

use App\Actions\EnsureAdminUser;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with local sample data.
     *
     * Refuses to run outside the local and testing environments, before writing anything. The
     * panel user is the one from the ADMIN_* environment values, created by the same Action the
     * deploy uses (nothing is created when they are empty); the seeder holds no user or password
     * of its own.
     */
    public function run(EnsureAdminUser $ensureAdminUser): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Örnek veri yalnızca yerel ortamda oluşturulabilir.');
        }

        $ensureAdminUser->handle();

        $this->call(SampleDataSeeder::class);
    }
}
