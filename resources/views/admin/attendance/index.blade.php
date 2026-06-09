@extends('layouts.app')

@php
  use App\Models\AttendanceRecord;

  $monthNames = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
  $dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
  $dateLabel = $dayNames[$date->dayOfWeek] . ', ' . $date->format('d') . ' ' . $monthNames[$date->month] . ' ' . $date->year;
  $statusLabels = [
    'hadir' => 'Hadir',
    'dinas_luar' => 'Dinas Luar',
    'izin' => 'Izin / Sakit',
    'alfa' => 'Alfa',
    'belum_lengkap' => 'Belum Lengkap',
  ];
@endphp

@section('content')
  <section class="page-heading">
    <div>
      <p class="eyebrow">Dashboard Absensi</p>
      <h1>Absensi Mobile</h1>
      <p class="muted">Pantau kehadiran, dinas luar, izin, dan alfa pada {{ $dateLabel }}.</p>
    </div>
    <div class="page-actions">
      <a class="ghost-action" href="{{ route('dashboard') }}">Dashboard</a>
    </div>
  </section>

  <section class="summary-strip">
    <article class="card stat modern-stat green"><i>HD</i><strong>{{ $summary['hadir'] }}</strong><span>Hadir</span><small>Awal + akhir</small></article>
    <article class="card stat modern-stat blue"><i>DL</i><strong>{{ $summary['dinas_luar'] }}</strong><span>Dinas Luar</span><small>1x absen lapangan</small></article>
    <article class="card stat modern-stat gold"><i>IZ</i><strong>{{ $summary['izin'] }}</strong><span>Izin / Sakit</span><small>Cuti disetujui</small></article>
    <article class="card stat modern-stat red"><i>AF</i><strong>{{ $summary['alfa'] }}</strong><span>Alfa</span><small>Belum ada absensi</small></article>
  </section>

  <section class="panel">
    <div class="panel-header">
      <div>
        <h2>Daftar Absensi</h2>
        <p class="muted">Gunakan tanggal, status, dan pencarian untuk memantau pegawai lapangan.</p>
      </div>
    </div>

    <form class="admin-filter-bar" method="get" action="{{ route('admin.attendance.index') }}">
      <label class="filter-search">
        <span>Tanggal</span>
        <input type="date" name="date" value="{{ $date->toDateString() }}">
      </label>
      <label class="filter-search">
        <span>Cari Data</span>
        <input name="search" value="{{ $search }}" placeholder="Nama, username, NIP, NIK, jabatan">
      </label>
      <div class="filter-tabs">
        <a class="filter-chip {{ $status ? '' : 'active' }}" href="{{ route('admin.attendance.index', array_filter(['date' => $date->toDateString(), 'search' => $search], fn ($value) => filled($value))) }}">Semua</a>
        @foreach ($statusLabels as $key => $label)
          <a class="filter-chip {{ $status === $key ? 'active' : '' }}" href="{{ route('admin.attendance.index', array_filter(['date' => $date->toDateString(), 'status' => $key, 'search' => $search], fn ($value) => filled($value))) }}">{{ $label }}</a>
        @endforeach
      </div>
      <button class="primary-action" type="submit">Cari</button>
    </form>

    <div class="table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>PJLP</th>
            <th>Absen Awal</th>
            <th>Absen Akhir</th>
            <th>Dinas Luar</th>
            <th>Status</th>
            <th>Lokasi Terakhir</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($rows as $row)
            @php
              $user = $row['user'];
              $records = $row['records'];
              $start = $records->get(AttendanceRecord::TYPE_START);
              $end = $records->get(AttendanceRecord::TYPE_END);
              $field = $records->get(AttendanceRecord::TYPE_FIELD);
              $latest = $row['latestRecord'];
            @endphp
            <tr>
              <td><strong>{{ $user->name }}</strong><br><span class="muted">{{ $user->jabatan ?: 'PJLP' }} &middot; {{ $user->nip ?: '-' }}</span></td>
              <td>{{ $start ? $start->recorded_at->format('H:i') . ' WIB' : '-' }}</td>
              <td>{{ $end ? $end->recorded_at->format('H:i') . ' WIB' : '-' }}</td>
              <td>{{ $field ? $field->recorded_at->format('H:i') . ' WIB' : '-' }}</td>
              <td><span class="status-pill attendance-{{ $row['status'] }}">{{ $statusLabels[$row['status']] }}</span></td>
              <td>
                @if ($latest)
                  {{ $latest->address ?: 'Koordinat tersimpan' }}
                @elseif ($row['leave'])
                  {{ $row['leave']->reason }}
                @else
                  -
                @endif
              </td>
              <td>
                @if ($latest)
                  <a class="ghost-action" href="{{ route('admin.attendance.show', $latest) }}">Detail</a>
                @else
                  -
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7">Tidak ada data absensi yang cocok.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
@endsection
