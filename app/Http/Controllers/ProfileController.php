<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        return view('profile.edit', ['user' => $user]);
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

        /** @var \App\Models\User $user */
        $user = Auth::user();

        try {
            if ($request->hasFile('avatar')) {
                // Delete old avatar if exists
                if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
                    Storage::disk('public')->delete($user->avatar_path);
                }

                // Ensure directory exists
                $avatarsDir = storage_path('app/public/avatars');
                if (!is_dir($avatarsDir)) {
                    mkdir($avatarsDir, 0775, true);
                }

                $data['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
            }

            if ($request->hasFile('signature')) {
                // Delete old signature if exists
                if ($user->signature_path && Storage::disk('public')->exists($user->signature_path)) {
                    Storage::disk('public')->delete($user->signature_path);
                }

                // Ensure directory exists
                $signaturesDir = storage_path('app/public/signatures');
                if (!is_dir($signaturesDir)) {
                    mkdir($signaturesDir, 0775, true);
                }

                $data['signature_path'] = $request->file('signature')->store('signatures', 'public');
            }

            if (filled($data['password'] ?? null)) {
                if (!Hash::check($data['current_password'], $user->password)) {
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
        } catch (\Exception $e) {
            Log::error('Error updating profile for user ' . $user->id . ': ' . $e->getMessage());
            return back()
                ->withErrors(['error' => 'Gagal menyimpan profil: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function deleteSignature(Request $request)
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login')->with('error', 'Tidak terautentikasi.');
        }

        if ($user->signature_path) {
            try {
                // Delete file from storage
                if (Storage::disk('public')->exists($user->signature_path)) {
                    Storage::disk('public')->delete($user->signature_path);
                }
                
                // Update database
                $user->update(['signature_path' => null]);
            } catch (\Exception $e) {
                Log::error('Error deleting signature for user ' . $user->id . ': ' . $e->getMessage());
                return redirect()->route('profile.edit')->with('error', 'Gagal menghapus tanda tangan: ' . $e->getMessage());
            }
        }

        return redirect()->route('profile.edit')->with('status', 'Tanda tangan berhasil dihapus.');
    }

    public function deleteAvatar(Request $request)
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login')->with('error', 'Tidak terautentikasi.');
        }

        if ($user->avatar_path) {
            try {
                // Delete file from storage
                if (Storage::disk('public')->exists($user->avatar_path)) {
                    Storage::disk('public')->delete($user->avatar_path);
                }
                
                // Update database
                $user->update(['avatar_path' => null]);
            } catch (\Exception $e) {
                Log::error('Error deleting avatar for user ' . $user->id . ': ' . $e->getMessage());
                return redirect()->route('profile.edit')->with('error', 'Gagal menghapus foto profil: ' . $e->getMessage());
            }
        }

        return redirect()->route('profile.edit')->with('status', 'Foto profil berhasil dihapus.');
    }
}
