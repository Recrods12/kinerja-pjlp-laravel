<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminUserController extends Controller
{
    public function index()
    {
        return view('admin.users.index', [
            'users' => User::query()->orderBy('role')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.users.form', [
            'managedUser' => new User([
                'role' => 'pjlp',
                'jabatan' => 'PJLP',
                'annual_leave_quota' => 12,
                'annual_leave_remaining' => 12,
            ]),
            'mode' => 'create',
        ]);
    }

    public function store(Request $request)
    {
        User::create($this->validatedData($request));

        return redirect()->route('admin.users.index')->with('status', 'User berhasil ditambahkan.');
    }

    public function edit(User $user)
    {
        return view('admin.users.form', [
            'managedUser' => $user,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validatedData($request, $user);
        if (! filled($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('admin.users.index')->with('status', 'User berhasil diperbarui.');
    }

    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->withErrors(['user' => 'Akun admin yang sedang dipakai tidak bisa dihapus.']);
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'User berhasil dihapus.');
    }

    private function validatedData(Request $request, ?User $user = null): array
    {
        $userId = $user?->id;
        $passwordRule = $user ? ['nullable', 'confirmed', Password::min(6)] : ['required', 'confirmed', Password::min(6)];
        $jobRoles = ['Admin', 'PJLP', 'Driver', 'Kebersihan', 'Keamanan', 'Mekanikal Enginer', 'Pelayanan Umum'];

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($userId)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users')->ignore($userId)],
            'password' => $passwordRule,
            'role' => ['required', Rule::in(['admin', 'pjlp'])],
            'nip' => ['nullable', 'string', 'max:255'],
            'nik' => ['nullable', 'string', 'max:255'],
            'jabatan' => ['nullable', Rule::in($jobRoles)],
            'security_team' => ['nullable', Rule::in(['A', 'B', 'C'])],
            'security_cycle_start_date' => ['nullable', 'date'],
            'unit' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'annual_leave_quota' => ['required', 'integer', 'min:0', 'max:255'],
            'annual_leave_remaining' => ['required', 'integer', 'min:0', 'max:255', 'lte:annual_leave_quota'],
        ]);

        if ($data['role'] === 'admin') {
            $data['jabatan'] = 'Admin';
            $data['security_team'] = null;
            $data['security_cycle_start_date'] = null;
        } elseif (! filled($data['jabatan'] ?? null) || $data['jabatan'] === 'Admin') {
            $data['jabatan'] = 'PJLP';
        }

        if (($data['jabatan'] ?? null) !== 'Keamanan') {
            $data['security_team'] = null;
            $data['security_cycle_start_date'] = null;
        }

        return $data;
    }

}
