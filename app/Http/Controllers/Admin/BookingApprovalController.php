<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MeetingBooking;
use App\Services\ZaloZnsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class BookingApprovalController extends Controller
{
    public function index(Request $request): View
    {
        $allowedStatuses = ['pending', 'approved', 'rejected', 'cancelled', 'all'];
        $status = strtolower((string) $request->query('status', 'pending'));
        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'pending';
        }

        $bookings = MeetingBooking::query()
            ->with(['room', 'requester', 'approver'])
            ->when(in_array($status, ['pending', 'approved', 'rejected', 'cancelled'], true), function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->orderBy('start_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.bookings.index', compact('bookings', 'status'));
    }

    public function approve(MeetingBooking $booking): RedirectResponse
    {
        if ($booking->status !== 'pending') {
            return back()->withErrors(['booking' => 'Lịch họp này đã được xử lý trước đó.']);
        }

        $booking->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => Carbon::now(),
            'rejection_reason' => null,
        ]);

        $this->notifyRequesterApproved($booking->fresh(['requester', 'room']));

        return back()->with('success', 'Đã phê duyệt lịch họp.');
    }

    public function reject(Request $request, MeetingBooking $booking): RedirectResponse
    {
        if ($booking->status !== 'pending') {
            return back()->withErrors(['booking' => 'Lịch họp này đã được xử lý trước đó.']);
        }

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        $booking->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => Carbon::now(),
            'rejection_reason' => $data['rejection_reason'],
        ]);

        return back()->with('success', 'Đã từ chối lịch họp.');
    }

    public function cancel(MeetingBooking $booking): RedirectResponse
    {
        if ($booking->status !== 'approved') {
            return back()->withErrors(['booking' => 'Chỉ có thể hủy lịch đã được phê duyệt từ trang admin.']);
        }

        $booking->update(['status' => 'cancelled']);

        return back()->with('success', 'Đã hủy lịch họp.');
    }

    private function notifyRequesterApproved(MeetingBooking $booking): void
    {
        $phone = trim((string) ($booking->organizer_phone ?: $booking->requester?->phone ?: ''));
        if ($phone === '') {
            Log::warning('Skip requester approval notification due to missing phone.', [
                'booking_id' => $booking->id,
            ]);

            return;
        }

        $templateData = [
            'name' => (string) $booking->organizer_name,
            'datetime' => $booking->start_at?->copy()->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y')
                ?? now()->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y'),
            'department' => (string) $booking->organizer_department,
            'content' => 'Da duoc phe duyet.',
        ];

        $trackingId = sprintf('booking_%d_requester_%s', $booking->id, preg_replace('/\D+/', '', $phone));
        $sent = app(ZaloZnsService::class)->sendBookingConfirmation($phone, $templateData, $trackingId);

        if (! $sent) {
            Log::warning('Unable to send approval notification via Zalo ZNS to requester.', [
                'booking_id' => $booking->id,
                'phone' => $phone,
            ]);
        }
    }
}
