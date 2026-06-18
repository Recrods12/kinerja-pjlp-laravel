<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('profile.edit', ['user' => Auth::user()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'nip' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'signature' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'current_password' => ['nullable', 'required_with:password', 'string'],
            'password' => ['nullable', 'confirmed', Password::min(6)],
        ]);

        $user = Auth::user();

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $data['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        if ($request->hasFile('signature')) {
            if ($user->signature_path) {
                Storage::disk('public')->delete($user->signature_path);
            }

            $data['signature_path'] = $request->file('signature')->store('signatures', 'public');
        }

        if (filled($data['password'] ?? null)) {
            if (! Hash::check($data['current_password'], $user->password)) {
                return back()
                    ->withErrors(['current_password' => 'Password lama tidak sesuai.'])
                    ->withInput($request->except(['current_password', 'password', 'password_confirmation']));
            }

            $data['password'] = $data['password'];
        } else {
            unset($data['password']);
        }

        unset($data['current_password'], $data['password_confirmation'], $data['avatar'], $data['signature']);

        $user->update($data);

        return redirect()->route('profile.edit')->with('status', 'Profil berhasil disimpan.');
    }

    public function deleteSignature(Request $request)
    {
        $user = $request->user();

        if ($user->signature_path) {
            Storage::disk('public')->delete($user->signature_path);
            $user->forceFill(['signature_path' => null])->save();
        }

        return redirect()->route('profile.edit')->with('status', 'Tanda tangan berhasil dihapus.');
    }
}
