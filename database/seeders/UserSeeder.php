<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = collect([
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@mohssila.com',
                'mobile' => '+966554355290',
                'merchant_name' => 'Super Admin',
                'plan' => 'Super Admin',
                'domain' => 'https://mohssilh.com/',
                'salla_user_id' => '1',

            ],
        ]);

        $users->map(function ($user) {
            $user = collect($user);

            $userData = $user->except('role')->toArray();

            $newUser = User::create($userData);
            $newUser->assignRole(Role::where('name', 'super-admin')->where('guard_name', 'api')->first());



        });
    }

}
