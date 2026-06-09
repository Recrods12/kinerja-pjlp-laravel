@extends('layouts.app')

@section('content')
  <div class="page-heading">
    <div>
      <p class="eyebrow">Pengaturan Kalender</p>
      <h1>Kelola Libur</h1>
      <p class="muted">Tanggal libur reguler tidak dihitung sebagai belum mengisi kinerja. Shift keamanan tetap memakai siklus regu.</p>
    </div>
    <div class="page-actions">
      <a class="ghost-action" href="{{ route('dashboard') }}">Dashboard</a>
    </div>
  </div>

  <div class="settings-grid">
    <section class="panel form-panel">
      <div class="panel-header compact">
        <div>
          <h2>Tambah Libur Manual</h2>
          <p class="muted">Masukkan tanggal khusus yang belum ada di daftar libur nasional.</p>
        </div>
      </div>

      <form class="profile-grid" method="post" action="{{ route('admin.holidays.store') }}">
        @csrf
        <label>
          <span>Tanggal Libur</span>
          <input name="holiday_date" type="date" required>
        </label>
        <label>
          <span>Nama Libur</span>
          <input name="name" placeholder="Contoh: Cuti bersama" required>
        </label>
        <div class="actions-row full-width">
          <button class="primary-action" type="submit">Tambah Libur</button>
        </div>
      </form>
    </section>

    <section class="panel form-panel">
      <div class="panel-header compact">
        <div>
          <h2>Sinkron Nasional</h2>
          <p class="muted">Ambil libur nasional dan cuti bersama Indonesia dari sumber otomatis.</p>
        </div>
      </div>

      <form class="holiday-sync-form" method="post" action="{{ route('admin.holidays.syncNational') }}">
        @csrf
        <label>
          <span>Tahun</span>
          <input name="year" type="number" min="2000" max="2100" value="{{ request('year', now()->year) }}" required>
        </label>
        <button class="primary-action" type="submit">Sinkron Libur Nasional</button>
      </form>
    </section>
  </div>

  <section class="panel management-panel">
    <div class="panel-header compact">
      <div>
        <h2>Daftar Libur</h2>
        <p class="muted">Kelola tanggal libur yang aktif di kalender kinerja.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Tanggal</th>
            <th>Nama Libur</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($holidays as $holiday)
            <tr>
              <td>{{ $holiday->holiday_date->translatedFormat('d F Y') }}</td>
              <td>{{ $holiday->name }}</td>
              <td>
                <form method="post" action="{{ route('admin.holidays.destroy', $holiday) }}" onsubmit="return confirm('Hapus tanggal libur ini?');">
                  @csrf
                  @method('delete')
                  <button class="danger-action" type="submit">Hapus</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="3">Belum ada tanggal libur.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
@endsection
