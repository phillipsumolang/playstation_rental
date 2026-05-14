<?php

namespace App\Livewire\Forms;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Validate;
use Livewire\Form;

class RegisterForm extends Form
{
    #[Validate(rule: 'required|string|min:3|max:150')]
    public string $name = '';
    #[Validate(rule: 'required|string|min:3|max:100|unique:users,username')]
    public string $username = '';
    #[Validate(rule: 'required|email|unique:users,email')]
    public string $email = '';
    #[Validate(rule: 'required|string')]
    public string $password = '';

    public function register(): void
    {
        $this->validate();

        $adminPermissions = [
            'create-computer', 'delete-computer', 'edit-computer',
            'list-computer', 'view-computer', 'cancel-booking-computer',
            'list-history-booking', 'create-walkin-booking',
        ];

        foreach ($adminPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions($adminPermissions);

        DB::transaction(function () use ($adminRole) {
            $user = User::create([
                'name' => $this->name,
                'username' => $this->username,
                'email' => $this->email,
                'password' => $this->password
            ]);

            $user->assignRole($adminRole);
        });
    }
}
