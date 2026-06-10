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
  $statusNotes = [
    'hadir' => 'Awal + akhir / absen akhir',
    'dinas_luar' => 'Tugas luar aktif',
    'izin' => 'Cuti disetujui',
    'alfa' => 'Belum ada absensi',
    'belum_lengkap' => 'Baru absen awal',
  ];
  $selectedUserId = (int) request('user_id');
  $selectedRow = $selectedUserId
    ? $rows->first(fn ($row) => (int) $row['user']->id === $selectedUserId)
    : null;
  $selectedRow = $selectedRow ?? $rows->first(fn ($row) => $row['latestRecord']) ?? $rows->first();
  $selectedUser = $selectedRow['user'] ?? null;
  $selectedRecords = $selectedRow['records'] ?? collect();
  $selectedLatest = $selectedRow['latestRecord'] ?? null;
  $selectedField = $selectedRecords->get(AttendanceRecord::TYPE_FIELD);
  $selectedLeave = $selectedRow['leave'] ?? null;
@endphp

@section('content')
  <section class="page-heading attendance-admin-heading">
    <div>
      <p class="eyebrow">Dashboard Absensi</p>
      <h1>Dashboard Absensi</h1>
      <p class="muted">Pantau kehadiran pegawai secara real-time pada {{ $dateLabel }}.</p>
    </div>
    <div class="page-actions">
      <a class="ghost-action attendance-export-action" href="{{ route('admin.attendance.exportExcel', array_filter(['date' => $date->toDateString(), 'status' => $status, 'search' => $search], fn ($value) => filled($value))) }}">Export Excel</a>
      <a class="ghost-action" href="{{ route('dashboard') }}">Dashboard</a>
      <form class="inline-date-form" method="get" action="{{ route('admin.attendance.index') }}">
        <input type="hidden" name="search" value="{{ $search }}">
        @if ($status)
          <input type="hidden" name="status" value="{{ $status }}">
        @endif
        <input type="date" name="date" value="{{ $date->toDateString() }}" onchange="this.form.submit()">
      </form>
    </div>
  </section>

  <section class="attendance-kpi-grid">
    <article class="attendance-kpi green"><i>HD</i><span>Hadir</span><strong>{{ $summary['hadir'] }}</strong><small>Awal + akhir</small></article>
    <article class="attendance-kpi blue"><i>DL</i><span>Dinas Luar</span><strong>{{ $summary['dinas_luar'] }}</strong><small>Tugas lapangan</small></article>
    <article class="attendance-kpi gold"><i>IZ</i><span>Izin / Sakit</span><strong>{{ $summary['izin'] }}</strong><small>Cuti disetujui</small></article>
    <article class="attendance-kpi red"><i>AF</i><span>Alfa</span><strong>{{ $summary['alfa'] }}</strong><small>Belum absen</small></article>
  </section>

  <section class="attendance-admin-board">
    <article class="panel attendance-table-panel">
      <div class="panel-header">
        <div>
          <h2>Daftar Absensi</h2>
          <p class="muted">Gunakan tanggal, status, dan pencarian untuk memantau pegawai lapangan.</p>
        </div>
      </div>

      <form id="attendance-filter" class="attendance-toolbar" method="get" action="{{ route('admin.attendance.index') }}">
        <input type="hidden" name="date" value="{{ $date->toDateString() }}">
        <input type="hidden" name="status" value="{{ $status }}" data-attendance-status-input>
        <label class="filter-search">
          <span>Cari Data</span>
          <input name="search" value="{{ $search }}" placeholder="Nama, username, NIP, NIK, jabatan" autocomplete="off" data-attendance-live-search>
        </label>
        <div class="filter-tabs">
          <button class="filter-chip {{ $status ? '' : 'active' }}" type="button" data-attendance-status="">Semua</button>
          @foreach ($statusLabels as $key => $label)
            <button class="filter-chip {{ $status === $key ? 'active' : '' }}" type="button" data-attendance-status="{{ $key }}">{{ $label }}</button>
          @endforeach
        </div>
        <button class="primary-action" type="submit">Filter</button>
      </form>

      <div class="table-wrap">
        <table class="admin-table attendance-table">
          <thead>
            <tr>
              <th>No</th>
              <th>Nama Pegawai</th>
              <th>Jabatan / Bidang</th>
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
              @php
                $detailUrl = route('admin.attendance.index', array_filter([
                  'date' => $date->toDateString(),
                  'status' => $status,
                  'search' => $search,
                  'user_id' => $user->id,
                ], fn ($value) => filled($value)));
              @endphp
              <tr class="{{ $selectedUser && $selectedUser->id === $user->id ? 'is-selected' : '' }}">
                <td>{{ $loop->iteration }}</td>
                <td>
                  <a class="attendance-user-link" href="{{ $detailUrl }}" title="Lihat detail absensi {{ $user->name }}">
                    <strong>{{ $user->name }}</strong>
                    <span>{{ $user->nip ?: '-' }}</span>
                  </a>
                </td>
                <td>{{ $user->jabatan ?: 'PJLP' }}</td>
                <td class="attendance-time-cell">
                  @if ($start)
                    <span class="time-check">&check;</span>{{ $start->recorded_at->format('H:i') }} WIB
                  @else
                    -
                  @endif
                </td>
                <td class="attendance-time-cell">
                  @if ($end)
                    <span class="time-check">&check;</span>{{ $end->recorded_at->format('H:i') }} WIB
                  @else
                    -
                  @endif
                </td>
                <td class="attendance-time-cell">
                  @if ($field)
                    <span class="time-check field">&check;</span>{{ $field->recorded_at->format('H:i') }} WIB
                  @else
                    -
                  @endif
                </td>
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
                    <a class="mini-action" href="{{ route('admin.attendance.show', $latest) }}">Lihat</a>
                  @else
                    -
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="9">Tidak ada data absensi yang cocok.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="attendance-table-footer">
        <span>Menampilkan {{ $rows->count() }} data sesuai filter.</span>
      </div>
    </article>

    <aside class="panel attendance-side-card">
      <h2>Detail Pegawai</h2>
      @if ($selectedUser)
        <div class="attendance-profile">
          <div class="attendance-avatar">{{ strtoupper(substr($selectedUser->name, 0, 1)) }}</div>
          <div>
            <strong>{{ $selectedUser->name }}</strong>
            <span>{{ $selectedUser->jabatan ?: 'PJLP' }}</span>
          </div>
        </div>

        <dl class="detail-list">
          <div>
            <dt>Status</dt>
            <dd><span class="status-pill attendance-{{ $selectedRow['status'] }}">{{ $statusLabels[$selectedRow['status']] }}</span></dd>
          </div>
          <div>
            <dt>Catatan</dt>
            <dd>{{ $statusNotes[$selectedRow['status']] }}</dd>
          </div>
          <div>
            <dt>Aktif sejak</dt>
            <dd>{{ $selectedLatest ? $selectedLatest->recorded_at->format('H:i') . ' WIB' : '-' }}</dd>
          </div>
          <div>
            <dt>Tujuan Tugas</dt>
            <dd>{{ $selectedField?->note ?: ($selectedLatest?->note ?: ($selectedLeave?->reason ?: '-')) }}</dd>
          </div>
        </dl>

        <div class="admin-map attendance-map-card">
          <span>{{ $selectedLatest ? ($selectedLatest->address ?: 'Koordinat lokasi tersimpan') : 'Belum ada lokasi absensi' }}</span>
          @if ($selectedLatest && $selectedLatest->latitude && $selectedLatest->longitude)
            <a href="https://www.google.com/maps?q={{ $selectedLatest->latitude }},{{ $selectedLatest->longitude }}" target="_blank" rel="noopener">Buka Peta</a>
          @endif
        </div>

        @if ($selectedLatest)
          <a class="primary-action full-action" href="{{ route('admin.attendance.show', $selectedLatest) }}">Lihat Riwayat</a>
        @endif
      @else
        <p class="muted">Belum ada pegawai yang sesuai filter.</p>
      @endif
    </aside>
  </section>

  <section class="attendance-info-banner">
    <i>OK</i>
    <span>Sistem absensi dilengkapi verifikasi lokasi, waktu, dan foto selfie untuk memastikan akurasi kehadiran.</span>
  </section>

  <script>
    (() => {
      const form = document.querySelector('#attendance-filter');
      const searchInput = form?.querySelector('[data-attendance-live-search]');
      const statusInput = form?.querySelector('[data-attendance-status-input]');
      const statusButtons = form?.querySelectorAll('[data-attendance-status]') ?? [];
      let searchTimer;
      let isComposing = false;

      const submitFilter = () => {
        if (!form) return;

        if (form.requestSubmit) {
          form.requestSubmit();
          return;
        }

        form.submit();
      };

      const submitSearch = () => {
        if (isComposing) return;

        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(submitFilter, 650);
      };

      statusButtons.forEach((button) => {
        button.addEventListener('click', () => {
          if (statusInput) {
            statusInput.value = button.dataset.attendanceStatus || '';
          }

          submitFilter();
        });
      });

      searchInput?.addEventListener('compositionstart', () => {
        isComposing = true;
      });

      searchInput?.addEventListener('compositionend', () => {
        isComposing = false;
        submitSearch();
      });

      searchInput?.addEventListener('input', submitSearch);
    })();
  </script>
@endsection
