@extends('layouts.app')

@php
  $monthNames = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
  $date = $attendanceRecord->work_date;
  $dateLabel = $date->format('d') . ' ' . $monthNames[$date->month] . ' ' . $date->year;
@endphp

@section('content')
  <div class="page-heading">
    <div>
      <p class="eyebrow">Panel Admin</p>
      <h1>Edit Absensi</h1>
      <p class="muted">{{ $attendanceRecord->user->name }} &middot; {{ $labels[$attendanceRecord->type] }} &middot; {{ $dateLabel }}</p>
    </div>
    <div class="page-actions">
      <a class="ghost-action" href="{{ route('admin.attendance.show', $attendanceRecord) }}">Kembali</a>
    </div>
  </div>

  <section class="panel narrow form-panel">
    <div class="panel-header compact">
      <div>
        <h2>Data Absensi</h2>
        <p class="muted">Perbarui data absensi mobile user. Kosongkan foto selfie jika tidak ingin mengganti.</p>
      </div>
    </div>

    <form class="profile-grid" method="post" action="{{ route('admin.attendance.update', $attendanceRecord) }}" enctype="multipart/form-data">
      @csrf
      @method('put')

      <div class="full-width" style="display:flex;align-items:center;gap:12px;padding-bottom:12px;border-bottom:1px solid var(--line,rgba(0,0,0,.06));margin-bottom:4px;">
        <span class="admin-avatar" style="background:{{ $attendanceRecord->user->jabatan === 'Driver' ? '#3b82f6' : ($attendanceRecord->user->jabatan === 'Kebersihan' ? '#0d6f4b' : ($attendanceRecord->user->jabatan === 'Keamanan' ? '#ef4444' : ($attendanceRecord->user->jabatan === 'Mekanikal Enginer' ? '#a855f7' : ($attendanceRecord->user->jabatan === 'Pelayanan Umum' ? '#e8a838' : '#6b7b73')))) }}">{{ strtoupper(substr($attendanceRecord->user->name, 0, 1)) }}</span>
        <div>
          <strong style="font-size:15px;">{{ $attendanceRecord->user->name }}</strong>
          <span class="muted" style="display:block;font-size:12px;">{{ $attendanceRecord->user->jabatan ?: 'PJLP' }} &middot; {{ $attendanceRecord->user->nip ?: '-' }}</span>
        </div>
      </div>

      <label>
        <span>Tipe Absensi</span>
        <select name="type" required>
          @foreach ($labels as $key => $label)
            <option value="{{ $key }}" @selected(old('type', $attendanceRecord->type) === $key)>{{ $label }}</option>
          @endforeach
        </select>
      </label>

      <label>
        <span>Tanggal</span>
        <input name="work_date" type="date" value="{{ old('work_date', $attendanceRecord->work_date->toDateString()) }}" required>
      </label>

      <label>
        <span>Jam Absensi</span>
        <input name="recorded_time" type="time" value="{{ old('recorded_time', $attendanceRecord->recorded_at->format('H:i')) }}" required>
      </label>

      <label>
        <span>Latitude</span>
        <input name="latitude" type="number" step="any" value="{{ old('latitude', $attendanceRecord->latitude) }}" placeholder="-6.12345">
      </label>

      <label>
        <span>Longitude</span>
        <input name="longitude" type="number" step="any" value="{{ old('longitude', $attendanceRecord->longitude) }}" placeholder="106.12345">
      </label>

      <label>
        <span>Akurasi (meter)</span>
        <input name="accuracy" type="number" min="0" value="{{ old('accuracy', $attendanceRecord->accuracy) }}" placeholder="0">
      </label>

      <label class="full-width">
        <span>Alamat / Lokasi</span>
        <input name="address" value="{{ old('address', $attendanceRecord->address) }}" placeholder="Nama jalan atau alamat">
      </label>

      <label class="full-width">
        <span>Keterangan / Tujuan</span>
        <textarea name="note" placeholder="Catatan atau tujuan tugas">{{ old('note', $attendanceRecord->note) }}</textarea>
      </label>

      <label class="full-width">
        <span>Foto Selfie</span>
        @if ($attendanceRecord->selfie_path)
          <div style="margin-bottom:8px;">
            <img src="{{ asset('storage/' . $attendanceRecord->selfie_path) }}" alt="Selfie saat ini" style="width:120px;height:120px;object-fit:cover;border-radius:8px;border:1px solid var(--line,rgba(0,0,0,.06));">
            <small style="display:block;margin-top:4px;color:var(--muted,#6b7b73);">Selfie saat ini. Upload file baru untuk mengganti.</small>
          </div>
        @endif
        <input type="file" name="selfie" accept="image/*">
      </label>

      <div class="actions-row full-width">
        <button class="primary-action" type="submit">Simpan Perubahan</button>
      </div>
    </form>
  </section>
@endsection
