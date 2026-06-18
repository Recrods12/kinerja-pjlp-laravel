@extends('layouts.app')

@section('content')
  <div class="page-heading">
    <div>
      <p class="eyebrow">Pengaturan Akun</p>
      <h1>Profil {{ strtoupper($user->role) }}</h1>
      <p class="muted">Perbarui data pribadi, kontak, tanda tangan digital, dan password akun.</p>
    </div>
    <div class="page-actions">
      <a class="ghost-action" href="{{ route('dashboard') }}">Kembali</a>
    </div>
  </div>

  <section class="panel narrow form-panel">
    <div class="panel-header compact">
      <div>
        <h2>Data Profil</h2>
        <p class="muted">{{ $user->name }} &middot; {{ strtoupper($user->role) }}</p>
      </div>
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
        <strong>Foto Profil</strong>
        <span>Upload foto profil format JPG, PNG, atau WEBP. Maksimal 5 MB.</span>
      </div>
      <label>
        <span>Upload Foto Profil</span>
        <input name="avatar" type="file" accept="image/jpeg,image/png,image/webp">
      </label>
      <div>
        <span style="display: block; margin-bottom: 7px; color: #33433d; font-size: 13px; font-weight: 800;">Preview</span>
        @if ($user->avatar_path)
          <div class="signature-preview-box">
            <img src="{{ asset('storage/' . $user->avatar_path) }}" alt="Foto {{ $user->name }}" style="width: 120px; height: 120px; object-fit: cover; border-radius: 999px;">
          </div>
          <form method="post" action="{{ route('profile.avatar.delete') }}" style="margin-top:8px;" onsubmit="return confirm('Yakin ingin menghapus foto profil?')">
            @csrf
            <button class="danger-action" type="submit">Hapus Foto Profil</button>
          </form>
        @else
          <p class="muted">Belum ada foto profil.</p>
        @endif
      </div>
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
          <form method="post" action="{{ route('profile.signature.delete') }}" style="margin-top:8px;" onsubmit="return confirm('Yakin ingin menghapus tanda tangan?')">
            @csrf
            <button class="danger-action" type="submit">Hapus Tanda Tangan</button>
          </form>
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
