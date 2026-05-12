<?php

namespace App\Livewire\Pages\Customer;

use App\Models\Booking;
use App\Models\Customer;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class HistoryList extends Component
{
    use WithPagination;

    public function mount()
    {
        $user = getAuthUser();
        $permissions = $user->getPermissionsViaRoles();

        if (
            !$permissions->contains('name', 'list-history-booking')
            && !$permissions->contains('name', 'cancel-booking-computer')
        ) {
            abort(403);
        }
    }

    public function cancel_booking($booking)
    {
        try {
            $user = getAuthUser();
            $customer = Customer::where('user_id', '=', $user->id)->firstOrFail();

            DB::transaction(function () use ($booking, $customer) {
                $booking = Booking::where('customer_id', $customer->id)
                    ->findOrFail($booking['id']);
                $booking->update(['status' => 'cancelled']);
                $booking->delete();
            });

            $this->redirectRoute('customer.history');
        } catch (Exception $ex) {
            throw $ex;
        }
    }

    public function render()
    {
        $user = getAuthUser();
        $customer = Customer::where('user_id', '=', $user->id)->first();
        $now = Carbon::now();

        return view('livewire.pages.customer.history-list', [
            // Active bookings: not cancelled/rescheduled AND end time is in the future
            'current_bookings' => Booking::with('computer')
                ->whereNull('deleted_at')
                ->where('customer_id', '=', $customer->id)
                ->whereNotIn('status', ['cancelled', 'rescheduled'])
                ->where('booking_end_date', '>', $now)
                ->orderBy('booking_start_date', 'desc')
                ->paginate(10, ['*'], 'currentPage'),

            // Completed bookings: not cancelled/rescheduled AND end time has passed
            'completed_bookings' => Booking::with('computer')
                ->whereNull('deleted_at')
                ->where('customer_id', '=', $customer->id)
                ->whereNotIn('status', ['cancelled', 'rescheduled'])
                ->where('booking_end_date', '<=', $now)
                ->orderBy('booking_start_date', 'desc')
                ->paginate(10, ['*'], 'completedPage'),
        ]);
    }
}
