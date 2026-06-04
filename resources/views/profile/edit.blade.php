@extends('layouts.app')

@section('content')
  <section class="panel narrow">
    <div class="panel-header">
      <h2>Profil {{ strtoupper($user->role) }}</h2>
      <a class="ghost-action" href="{{ route('dashboard') }}">Kembali</a>
    </div>
    <form class="profile-grid" method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data">
      @csrf
      @method('put')
      <label>
        <span>Nama</span>
        <input name="name" value="{{ old('name', $user->name) }}" required>
      </label>
      <label>
        <span>NIP / ID PJLP</span>
        <input name="nip" value="{{ old('nip', $user->nip) }}">
      </label>
      <label>
        <span>Jabatan</span>
        <input value="{{ $user->jabatan ?: 'PJLP' }}" disabled>
      </label>
      <label>
        <span>No. Telepon</span>
        <input name="phone" value="{{ old('phone', $user->phone) }}">
      </label>
      <label class="full-width">
        <span>Unit Kerja</span>
        <input name="unit" value="{{ old('unit', $user->unit) }}">
      </label>
      <label class="full-width">
        <span>Alamat</span>
        <textarea name="address">{{ old('address', $user->address) }}</textarea>
      </label>
      <div class="form-divider full-width">
        <strong>Tanda Tangan Digital</strong>
        <span>Upload gambar tanda tangan format PNG, JPG, JPEG, atau WEBP. Gunakan background transparan/putih agar hasil cetak rapi.</span>
      </div>
      <label>
        <span>Upload Tanda Tangan</span>
        <input name="signature" type="file" accept="image/png,image/jpeg,image/webp">
      </label>
      <div>
        <span style="display: block; margin-bottom: 7px; color: #33433d; font-size: 13px; font-weight: 800;">Preview</span>
        @if ($user->signature_path)
          <div class="signature-preview-box">
            <img src="{{ asset('storage/' . $user->signature_path) }}" alt="Tanda tangan {{ $user->name }}">
          </div>
        @else
          <p class="muted">Belum ada tanda tangan.</p>
        @endif
      </div>
      <div class="form-divider full-width">
        <strong>Ganti Password</strong>
        <span>Kosongkan bagian ini jika tidak ingin mengganti password.</span>
      </div>
      <label>
        <span>Password Lama</span>
        <input name="current_password" type="password" autocomplete="current-password">
      </label>
      <label>
        <span>Password Baru</span>
        <input name="password" type="password" autocomplete="new-password" placeholder="Minimal 6 karakter">
      </label>
      <label>
        <span>Konfirmasi Password Baru</span>
        <input name="password_confirmation" type="password" autocomplete="new-password">
      </label>
      <div class="actions-row full-width">
        <button class="primary-action" type="submit">Simpan Profil</button>
      </div>
    </form>
  </section>
@endsection
