<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MeetingBooking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class BookingApprovalController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'pending');

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
}
