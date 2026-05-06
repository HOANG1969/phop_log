<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $keyword = (string) $request->query('q', '');

        $users = User::query()
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where(function ($sub) use ($keyword) {
                    $sub->where('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%")
                        ->orWhere('username', 'like', "%{$keyword}%")
                        ->orWhere('department', 'like', "%{$keyword}%");
                });
            })
            ->orderBy('id', 'desc')
            ->paginate(12)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'keyword'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:80', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'department' => ['required', 'in:KVP,KCTV'],
            'position' => ['nullable', 'string', 'max:120'],
            'role' => ['required', 'in:admin,user'],
            'is_active' => ['nullable', 'boolean'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['department'] = strtoupper((string) $data['department']);
        $data['password'] = Hash::make($data['password']);

        User::create($data);

        return back()->with('success', 'Tạo tài khoản thành công.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:80', 'unique:users,username,' . $user->id],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'department' => ['required', 'in:KVP,KCTV'],
            'position' => ['nullable', 'string', 'max:120'],
            'role' => ['required', 'in:admin,user'],
            'is_active' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['department'] = strtoupper((string) $data['department']);

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return back()->with('success', 'Cập nhật tài khoản thành công.');
    }
}
