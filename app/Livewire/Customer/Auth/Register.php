<?php

namespace App\Livewire\Customer\Auth;

use App\Livewire\Forms\CustomerRegisterForm;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.blank')]
class Register extends Component
{
    public CustomerRegisterForm $form;

    public function mount()
    {
        //
    }

    public function register(): void
    {
        try {
            $this->form->register();
            $this->form->reset();

            $user = Auth::user();
            if ($user && $user->hasVerifiedEmail()) {
                $this->redirectRoute('customer.booking');
            } else {
                $this->redirectRoute('verification.notice');
            }
        } catch (ValidationException $ex) {
            throw $ex;
        } catch (Exception $ex) {
            $this->form->reset();
            throw $ex;
        }
    }

    public function render()
    {
        return view('livewire.customer.auth.register');
    }
}
