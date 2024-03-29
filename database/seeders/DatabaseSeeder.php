<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Hobby;
use App\Models\Speciality;
use App\Models\University;
use App\Models\User;
use App\Models\UserHasHobbies;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run(): void
    {
        City::factory(10)->create()->unique('title');
        Hobby::factory(10)->create()->unique('title');
        University::factory(10)->create()->unique('title');
        Speciality::factory(10)->create()->unique('title');
        User::factory(10)->create();
        UserHasHobbies::factory(10)->create(); //TODO что за фигня? Сделать нормально через createMany, и удали модель..

        $admin = Role::create(['name' => 'admin']);
        $user = Role::create(['name' => 'user']);
        $guest = Role::create(['name' => 'guest']);

        $create_all = Permission::create(['name' => 'create all']);
        $edit_all = Permission::create(['name' => 'edit all']);
        $show_all = Permission::create(['name' => 'show all']);
        $delete_all = Permission::create(['name' => 'delete all']);
        $edit_account = Permission::create(['name' => 'edit account']);

        $admin->givePermissionTo($create_all);
        $admin->givePermissionTo($edit_all);
        $admin->givePermissionTo($show_all);
        $admin->givePermissionTo($delete_all);

        $user->givePermissionTo($edit_account);
        $guest->givePermissionTo($show_all);

    }
}
