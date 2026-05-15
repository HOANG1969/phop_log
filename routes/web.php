<?php

use App\Http\Controllers\Admin\BookingApprovalController;
use App\Http\Controllers\Admin\MeetingRoomManagementController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MeetingScheduleController;
use App\Http\Controllers\Webhook\ZaloOaWebhookController;
use Illuminate\Support\Facades\Route;

Route::match(['GET', 'POST'], '/webhooks/zalo/oa', [ZaloOaWebhookController::class, 'handle'])
	->name('webhooks.zalo.oa');

Route::middleware('guest')->group(function (): void {
	Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
	Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::middleware(['auth', 'idle.logout'])->group(function (): void {
	Route::get('/', [MeetingScheduleController::class, 'index'])->name('schedule.index');
	Route::get('/my-bookings', [MeetingScheduleController::class, 'myBookings'])->name('schedule.my-bookings');
	Route::get('/work-schedule', [MeetingScheduleController::class, 'workSchedule'])->name('schedule.work-schedule');
	Route::post('/bookings', [MeetingScheduleController::class, 'store'])->name('schedule.bookings.store');
	Route::delete('/bookings/{booking}', [MeetingScheduleController::class, 'cancel'])->name('schedule.bookings.cancel');
	Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

	Route::middleware('admin')->prefix('admin')->name('admin.')->group(function (): void {
		Route::get('/rooms', [MeetingRoomManagementController::class, 'index'])->name('rooms.index');
		Route::post('/rooms', [MeetingRoomManagementController::class, 'store'])->name('rooms.store');
		Route::put('/rooms/{room}', [MeetingRoomManagementController::class, 'update'])->name('rooms.update');
		Route::delete('/rooms/{room}', [MeetingRoomManagementController::class, 'destroy'])->name('rooms.destroy');
		Route::get('/work-schedule', [MeetingScheduleController::class, 'workSchedule'])->name('schedule.work-schedule');
		Route::post('/work-schedule', [MeetingScheduleController::class, 'storeWorkSchedule'])->name('schedule.work-schedule.store');
		Route::put('/work-schedule/{schedule}', [MeetingScheduleController::class, 'updateWorkSchedule'])->name('schedule.work-schedule.update');
		Route::delete('/work-schedule/{schedule}', [MeetingScheduleController::class, 'destroyWorkSchedule'])->name('schedule.work-schedule.destroy');
		Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
		Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
		Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
		Route::post('/users/{user}/test-zalo', [UserManagementController::class, 'testZaloNotification'])->name('users.test-zalo');

		Route::get('/bookings', [BookingApprovalController::class, 'index'])->name('bookings.index');
		Route::post('/bookings/{booking}/approve', [BookingApprovalController::class, 'approve'])->name('bookings.approve');
		Route::post('/bookings/{booking}/reject', [BookingApprovalController::class, 'reject'])->name('bookings.reject');
		Route::delete('/bookings/{booking}/cancel', [BookingApprovalController::class, 'cancel'])->name('bookings.cancel');
	});
});
