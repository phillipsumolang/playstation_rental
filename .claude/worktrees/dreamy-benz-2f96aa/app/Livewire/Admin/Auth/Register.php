<?php

namespace App\Livewire\Admin\Auth;

use App\Livewire\Forms\RegisterForm;
use App\Models\Role;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.blank')]
class Register extends Component
{
    public RegisterForm $form;

    public function mount()
    {
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        if ($adminRole) {
            $hasAdmin = DB::table('model_has_roles')
                ->where('role_id', $adminRole->getKey())
                ->where('model_type', \App\Models\User::class)
                ->exists();
            if ($hasAdmin) {
                abort(403, 'Admin sudah terdaftar.');
            }
        }
    }

    public function register(): void
    {
        try {
            $this->form->register();
            $this->form->reset();
            $this->redirectRoute('admin.auth.login');
        } catch (ValidationException $ex) {
            throw $ex;
        } catch (Exception $ex) {
            $this->form->reset();
            report($ex);
            session()->flash('auth.error', 'Terjadi kesalahan saat registrasi.');
        }
    }

    public function render()
    {
        return view('livewire.admin.auth.register');
    }
}
