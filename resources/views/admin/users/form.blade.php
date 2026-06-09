@extends('layouts.app')

@php
  $isEdit = $mode === 'edit';
  $action = $isEdit ? route('admin.users.update', $managedUser) : route('admin.users.store');
@endphp

@section('content')
  <div class="page-heading">
    <div>
      <p class="eyebrow">Master Data</p>
      <h1>{{ $isEdit ? 'Edit User' : 'Tambah User' }}</h1>
      <p class="muted">{{ $isEdit ? 'Perbarui akun, jabatan, NIP PJLP, NIK, kuota cuti, dan akses pengguna.' : 'Buat akun baru untuk admin atau PJLP dengan data kerja yang lengkap.' }}</p>
    </div>
    <div class="page-actions">
      <a class="ghost-action" href="{{ route('admin.users.index') }}">Kembali</a>
    </div>
  </div>

  <section class="panel narrow form-panel">
    <div class="panel-header compact">
      <div>
        <h2>Data Akun</h2>
        <p class="muted">{{ $isEdit ? 'Kosongkan password jika tidak ingin mengganti password.' : 'Buat akun baru untuk admin atau PJLP.' }}</p>
      </div>
    </div>

    <form class="profile-grid" method="post" action="{{ $action }}">
      @csrf
      @if ($isEdit)
        @method('put')
      @endif

      <label>
        <span>Nama</span>
        <input name="name" value="{{ old('name', $managedUser->name) }}" required>
      </label>
      <label>
        <span>Username</span>
        <input name="username" value="{{ old('username', $managedUser->username) }}" required>
      </label>
      <label>
        <span>Email</span>
        <input name="email" type="email" value="{{ old('email', $managedUser->email) }}">
      </label>
      <label>
        <span>Password</span>
        <input name="password" type="password" {{ $isEdit ? '' : 'required' }} placeholder="{{ $isEdit ? 'Biarkan kosong jika tidak diganti' : 'Minimal 6 karakter' }}">
      </label>
      <label>
        <span>Konfirmasi Password</span>
        <input name="password_confirmation" type="password" {{ $isEdit ? '' : 'required' }}>
      </label>
      <label>
        <span>Role</span>
        <select name="role" required id="user-role-select">
          <option value="pjlp" @selected(old('role', $managedUser->role) === 'pjlp')>PJLP</option>
          <option value="admin" @selected(old('role', $managedUser->role) === 'admin')>Admin</option>
        </select>
      </label>
      <label>
        <span>NIP / ID PJLP</span>
        <input name="nip" value="{{ old('nip', $managedUser->nip) }}">
      </label>
      <label>
        <span>NIK</span>
        <input name="nik" value="{{ old('nik', $managedUser->nik) }}">
      </label>
      <label>
        <span>Role Kerja / Jabatan</span>
        <input type="hidden" name="jabatan" value="{{ old('jabatan', $managedUser->jabatan) }}" id="jabatan-hidden" @disabled(old('role', $managedUser->role) !== 'admin')>
        <select name="jabatan" id="jabatan-select">
          @foreach (['Admin', 'PJLP', 'Driver', 'Kebersihan', 'Keamanan', 'Mekanikal Enginer', 'Pelayanan Umum'] as $jobRole)
            <option value="{{ $jobRole }}" @selected(old('jabatan', $managedUser->jabatan) === $jobRole) @if ($jobRole === 'Admin') data-admin-option @endif>{{ $jobRole }}</option>
          @endforeach
        </select>
      </label>
      <label class="security-shift-field">
        <span>Regu Keamanan</span>
        <select name="security_team" id="security-team-select">
          <option value="">Tidak pakai shift</option>
          @foreach (['A', 'B', 'C'] as $team)
            <option value="{{ $team }}" @selected(old('security_team', $managedUser->security_team) === $team)>Regu {{ $team }}</option>
          @endforeach
        </select>
      </label>
      <label class="security-shift-field">
        <span>Tanggal Awal Siklus</span>
        <input name="security_cycle_start_date" id="security-cycle-start" type="date" value="{{ old('security_cycle_start_date', $managedUser->security_cycle_start_date?->toDateString()) }}">
      </label>
      <label>
        <span>No. Telepon</span>
        <input name="phone" value="{{ old('phone', $managedUser->phone) }}">
      </label>
      <label>
        <span>Kuota Cuti Tahunan</span>
        <input name="annual_leave_quota" type="number" min="0" max="255" value="{{ old('annual_leave_quota', $managedUser->annual_leave_quota ?? 12) }}" required>
      </label>
      <label>
        <span>Sisa Cuti</span>
        <input name="annual_leave_remaining" type="number" min="0" max="255" value="{{ old('annual_leave_remaining', $managedUser->annual_leave_remaining ?? 12) }}" required>
      </label>
      <label class="full-width">
        <span>Unit Kerja</span>
        <input name="unit" value="{{ old('unit', $managedUser->unit) }}">
      </label>
      <label class="full-width">
        <span>Alamat</span>
        <textarea name="address">{{ old('address', $managedUser->address) }}</textarea>
      </label>

      <div class="actions-row full-width">
        <button class="primary-action" type="submit">{{ $isEdit ? 'Simpan Perubahan' : 'Tambah User' }}</button>
      </div>
    </form>
  </section>

  <script>
    const roleSelect = document.querySelector('#user-role-select');
    const jabatanSelect = document.querySelector('#jabatan-select');
    const jabatanHidden = document.querySelector('#jabatan-hidden');
    const securityFields = document.querySelectorAll('.security-shift-field');
    const securityInputs = document.querySelectorAll('#security-team-select, #security-cycle-start');

    const syncJabatanByRole = () => {
      const isAdmin = roleSelect.value === 'admin';
      const isSecurity = !isAdmin && jabatanSelect.value === 'Keamanan';
      jabatanSelect.disabled = isAdmin;
      jabatanHidden.disabled = !isAdmin;

      if (isAdmin) {
        jabatanSelect.value = 'Admin';
        jabatanHidden.value = 'Admin';
      } else if (jabatanSelect.value === 'Admin') {
        jabatanSelect.value = 'PJLP';
      }

      jabatanSelect.querySelector('[data-admin-option]')?.toggleAttribute('hidden', !isAdmin);
      securityFields.forEach((field) => field.hidden = !isSecurity);
      securityInputs.forEach((field) => field.disabled = !isSecurity);
    };

    roleSelect?.addEventListener('change', syncJabatanByRole);
    jabatanSelect?.addEventListener('change', syncJabatanByRole);
    window.addEventListener('DOMContentLoaded', syncJabatanByRole);
  </script>
@endsection
