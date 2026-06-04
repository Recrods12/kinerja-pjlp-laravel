@extends('layouts.app')

@section('content')
  <section class="panel narrow">
    <div class="panel-header">
      <div>
        <h2>Kelola Libur</h2>
        <p class="muted">Tanggal libur tidak dihitung sebagai belum mengisi kinerja.</p>
      </div>
      <a class="ghost-action" href="{{ route('dashboard') }}">Kembali</a>
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

  <section class="panel narrow">
    <div class="panel-header">
      <div>
        <h2>Sinkron Libur Nasional</h2>
        <p class="muted">Ambil libur nasional dan cuti bersama Indonesia, lalu simpan ke daftar libur.</p>
      </div>
    </div>

    <form class="actions-row" method="post" action="{{ route('admin.holidays.syncNational') }}">
      @csrf
      <label style="min-width: 180px;">
        <span>Tahun</span>
        <input name="year" type="number" min="2000" max="2100" value="{{ request('year', now()->year) }}" required>
      </label>
      <button class="primary-action" type="submit">Sinkron Libur Nasional</button>
    </form>
  </section>

  <section class="panel narrow">
    <div class="panel-header">
      <h2>Daftar Libur</h2>
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
