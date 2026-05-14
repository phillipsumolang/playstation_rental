<?php

namespace App\Livewire\Pages\Customer;

use App\Models\Booking;
use App\Models\Computer;
use App\Models\Customer;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class BookingReschedule extends Component
{
    public Booking $booking;
    public string $new_computer_id = '';
    public string $new_booking_date = '';
    public array $new_booking_times = [];

    /**
     * Maximum number of timeslots the user can select.
     * Derived from the original booking's booking_hour.
     */
    public int $maxSlots = 1;

    public function mount(Booking $booking): void
    {
        $user = getAuthUser();
        $permissions = $user->getPermissionsViaRoles();

        if (!$permissions->contains('name', 'reschedule-booking')) {
            abort(403);
        }

        $customer = Customer::where('user_id', '=', $user->id)->firstOrFail();

        // Ensure booking belongs to this customer and is eligible for reschedule
        if ($booking->customer_id !== $customer->id) {
            abort(403, 'Booking ini bukan milik Anda.');
        }

        if ($booking->status === 'rescheduled') {
            abort(403, 'Booking ini sudah di-reschedule sebelumnya.');
        }

        if ($booking->status === 'cancelled') {
            abort(403, 'Booking yang dibatalkan tidak bisa di-reschedule.');
        }

        if ($booking->booking_type === 'walk_in') {
            abort(403, 'Booking walk-in tidak bisa di-reschedule.');
        }

        $this->booking = $booking;
        $this->new_computer_id = $booking->computer_id;
        $this->new_booking_date = now()->format('Y-m-d');

        // FIX: Set max selectable slots to match the original booking's duration.
        // Each booking row represents 1 timeslot (booking_hour = 1),
        // so rescheduling 1 booking = select exactly 1 replacement slot.
        $this->maxSlots = (int) $booking->booking_hour;
    }

    /**
     * Toggle timeslot selection with enforcement of maxSlots limit.
     *
     * FIX: When rescheduling a single timeslot (maxSlots = 1),
     * clicking a new slot replaces the current selection instead of
     * appending to it. This prevents multi-select during single-slot reschedule.
     */
    public function toggleTimeSlot(string $time): void
    {
        $selectedSlots = collect($this->new_booking_times);

        // If already selected, deselect it (toggle off)
        if ($selectedSlots->contains($time)) {
            $this->new_booking_times = $selectedSlots
                ->reject(fn(string $slot) => $slot === $time)
                ->values()
                ->all();
            return;
        }

        // FIX: If maxSlots is 1, replace the selection entirely (radio-button behavior)
        if ($this->maxSlots === 1) {
            $this->new_booking_times = [$time];
            return;
        }

        // For multi-slot reschedule (maxSlots > 1), enforce the limit
        if ($selectedSlots->count() >= $this->maxSlots) {
            // Already at limit — do not add more
            return;
        }

        $selectedSlots->push($time);
        $this->new_booking_times = $selectedSlots->unique()->sort()->values()->all();
    }

    public function updatedNewComputerId(): void
    {
        $this->new_booking_times = [];
    }

    public function updatedNewBookingDate(): void
    {
        $this->new_booking_times = [];
    }

    /**
     * Get available timeslots for the new date/computer.
     */
    public function getTimeSlots(): Collection
    {
        if (blank($this->new_computer_id) || blank($this->new_booking_date)) {
            return collect();
        }

        $selectedDate = Carbon::parse($this->new_booking_date)->startOfDay();
        $now = Carbon::now();
        $minimumTime = $now->copy()->addHours(2);

        // Get all active bookings for this computer on this date,
        // EXCLUDING the current booking being rescheduled
        $bookings = Booking::query()
            ->active()
            ->where('computer_id', $this->new_computer_id)
            ->whereDate('booking_start_date', $selectedDate->toDateString())
            ->where('id', '!=', $this->booking->id)
            ->get(['booking_start_date', 'booking_end_date']);

        $isAtLimit = count($this->new_booking_times) >= $this->maxSlots;

        return collect(range(0, 23))->map(function (int $hour) use ($selectedDate, $bookings, $minimumTime, $isAtLimit) {
            $start = $selectedDate->copy()->setTime($hour, 0, 0);
            $end = $start->copy()->addHour();
            $value = $start->format('H:i');

            $isBooked = $bookings->contains(function ($booking) use ($start, $end) {
                return Carbon::parse($booking->booking_start_date)->lt($end)
                    && Carbon::parse($booking->booking_end_date)->gt($start);
            });

            $isTooSoon = $start->lt($minimumTime);
            $isSelected = in_array($value, $this->new_booking_times, true);

            // FIX: When at selection limit, disable unselected available slots
            // (but keep already-selected ones clickable so user can deselect)
            $isLocked = $isAtLimit && !$isSelected;

            return [
                'value' => $value,
                'label' => $start->format('H.i') . ' - ' . $end->format('H.i'),
                'is_available' => !$isBooked && !$isTooSoon && !$isLocked,
                'is_booked' => $isBooked,
                'is_too_soon' => $isTooSoon && !$isBooked,
                'is_locked' => $isLocked && !$isBooked && !$isTooSoon,
            ];
        });
    }

    public function getSelectedSlotLabels(): Collection
    {
        return $this->getTimeSlots()
            ->whereIn('value', $this->new_booking_times)
            ->sortBy('value')
            ->pluck('label')
            ->values();
    }

    public function getTotalPrice(): int
    {
        $computer = Computer::find($this->new_computer_id);
        if (!$computer) {
            return 0;
        }

        return count($this->new_booking_times) * (int) $computer->booking_price_per_hour;
    }

    /**
     * Execute the reschedule.
     */
    public function rescheduleBooking(): void
    {
        // FIX: Validate that selected timeslot count matches the original booking's duration exactly.
        $validator = Validator::make([
            'new_computer_id' => $this->new_computer_id,
            'new_booking_date' => $this->new_booking_date,
            'new_booking_times' => $this->new_booking_times,
        ], [
            'new_computer_id' => 'required|string|exists:computers,id',
            'new_booking_date' => 'required|date|after_or_equal:today',
            'new_booking_times' => 'required|array|min:' . $this->maxSlots . '|max:' . $this->maxSlots,
        ], [
            'new_computer_id.required' => 'Silakan pilih PlayStation.',
            'new_booking_date.required' => 'Silakan pilih tanggal.',
            'new_booking_date.after_or_equal' => 'Tanggal tidak boleh di masa lalu.',
            'new_booking_times.required' => 'Silakan pilih ' . $this->maxSlots . ' timeslot baru.',
            'new_booking_times.min' => 'Anda harus memilih tepat ' . $this->maxSlots . ' timeslot pengganti.',
            'new_booking_times.max' => 'Anda hanya boleh memilih ' . $this->maxSlots . ' timeslot pengganti (sesuai durasi booking asal).',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Validate 2-hour minimum rule
        $now = Carbon::now();
        $minimumTime = $now->copy()->addHours(2);

        foreach ($this->new_booking_times as $time) {
            [$hour] = explode(':', $time);
            $slotStart = Carbon::parse($this->new_booking_date)->setTime((int) $hour, 0, 0);

            if ($slotStart->lt($minimumTime)) {
                $failValidator = Validator::make(
                    ['new_booking_times' => null],
                    ['new_booking_times' => 'required'],
                    ['new_booking_times.required' => 'Timeslot ' . $time . ' tidak dapat dipilih. Booking minimal 2 jam sebelum waktu timeslot dimulai.']
                );
                throw new ValidationException($failValidator);
            }
        }

        $computer = Computer::findOrFail($this->new_computer_id);

        $selectedTimes = collect($this->new_booking_times)->unique()->sort()->values();

        // FIX: Double-check count after dedup to prevent manipulation
        if ($selectedTimes->count() !== $this->maxSlots) {
            $countValidator = Validator::make(
                ['new_booking_times' => null],
                ['new_booking_times' => 'required'],
                ['new_booking_times.required' => 'Jumlah timeslot yang dipilih harus tepat ' . $this->maxSlots . '.']
            );
            throw new ValidationException($countValidator);
        }

        DB::transaction(function () use ($selectedTimes, $computer) {
            // Check conflicts for each new slot
            foreach ($selectedTimes as $bookingTime) {
                [$hour] = explode(':', $bookingTime);
                $start = Carbon::parse($this->new_booking_date)->setTime((int) $hour, 0, 0);
                $end = $start->copy()->addHour();

                $hasConflict = Booking::query()
                    ->active()
                    ->where('computer_id', $this->new_computer_id)
                    ->where('id', '!=', $this->booking->id)
                    ->where('booking_start_date', '<', $end->format('Y-m-d H:i:s'))
                    ->where('booking_end_date', '>', $start->format('Y-m-d H:i:s'))
                    ->exists();

                if ($hasConflict) {
                    $conflictValidator = Validator::make(
                        ['new_booking_times' => null],
                        ['new_booking_times' => 'required'],
                        ['new_booking_times.required' => 'Timeslot ' . $bookingTime . ' sudah dibooking. Silakan pilih slot lain.']
                    );
                    throw new ValidationException($conflictValidator);
                }
            }

            // FIX: Only mark THIS specific booking as rescheduled.
            // Previously, the code used midtrans_order_id to update ALL sibling
            // bookings (e.g. all 3 slots of a grouped booking), which caused
            // rescheduling 1 slot to cancel all slots.
            $this->booking->update(['status' => 'rescheduled']);

            // Create new booking(s) (carry over payment status from original)
            foreach ($selectedTimes as $bookingTime) {
                [$hour] = explode(':', $bookingTime);
                $start = Carbon::parse($this->new_booking_date)->setTime((int) $hour, 0, 0);
                $end = $start->copy()->addHour();

                Booking::create([
                    'booking_type' => 'online',
                    'status' => 'confirmed',
                    'customer_id' => $this->booking->customer_id,
                    'computer_id' => $this->new_computer_id,
                    'booking_start_date' => $start->format('Y-m-d H:i:s'),
                    'booking_end_date' => $end->format('Y-m-d H:i:s'),
                    'booking_hour' => 1,
                    'total_booking_fee' => (int) $computer->booking_price_per_hour,
                    'payment_status' => $this->booking->payment_status,
                    'payment_type' => $this->booking->payment_type,
                    'paid_at' => $this->booking->paid_at,
                    'rescheduled_from_id' => $this->booking->id,
                ]);
            }
        });

        session()->flash('success', 'Booking berhasil di-reschedule!');
        $this->redirectRoute('customer.history');
    }

    public function render()
    {
        return view('livewire.pages.customer.booking-reschedule', [
            'computers' => Computer::query()->orderBy('computer_number')->get(),
            'time_slots' => $this->getTimeSlots(),
            'selected_slot_labels' => $this->getSelectedSlotLabels(),
            'total_price' => $this->getTotalPrice(),
            'max_slots' => $this->maxSlots,
        ]);
    }
}
