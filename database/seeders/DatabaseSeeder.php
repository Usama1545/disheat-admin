<?php

namespace Database\Seeders;

use App\Enums\DefaultSystemRolesEnum;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CountriesSeeder::class,
//            CategoriesSeeder::class,
            DefaultRolesSeeder::class,
        ]);
//        super_admin
//        try {
//            $user = User::create([
//                'name' => 'super admin',
//                'email' => 'admin@gmail.com',
//                'status' => 'active',
//                'access_panel' => 'admin',
//                'password' => bcrypt('12345678'),
//                'mobile' => '9876543210',
//            ]);
//            $user->assignRole(DefaultSystemRolesEnum::SUPER_ADMIN());
//
//        } catch (\Throwable $th) {
//            dd($th->getMessage());
//        }
    }
}
