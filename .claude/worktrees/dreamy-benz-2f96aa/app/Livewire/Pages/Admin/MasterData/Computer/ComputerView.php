<?php

namespace App\Livewire\Pages\Admin\MasterData\Computer;

use App\Models\Computer;
use Exception;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ComputerView extends Component
{
    public Computer $computer;


    public function delete(): void
    {
        try {
            DB::transaction(function () {
                $this->computer->delete();
            });

            $this->redirectRoute('admin.master-data.computer');
        } catch (Exception $ex) {
            report($ex);
            session()->flash('error', 'Terjadi kesalahan saat menghapus data.');
        }

        
    }

    public function render()
    {
        return view('livewire.pages.admin.master-data.computer.computer-view');
    }
}
