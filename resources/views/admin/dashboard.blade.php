@extends('layouts.app')

@php
  $prevMonth = $month->copy()->subMonth();
  $nextMonth = $month->copy()->addMonth();
  $today = now()->startOfDay();
  $holidayDateSet = array_flip($holidayDates);
  $activeFilters = array_filter(['jabatan' => $selectedRole, 'search' => $search], fn ($value) => filled($value));
  $monthNames = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
  $dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
  $monthLabel = $monthNames[$month->month] . ' ' . $month->year;
  $formatDate = fn ($date) => \Carbon\Carbon::parse($date)->format('d') . ' ' . $monthNames[\Carbon\Carbon::parse($date)->month] . ' ' . \Carbon\Carbon::parse($date)->year;
@endphp

@section('content')
  <section class="page-heading">
    <div>
      <p class="eyebrow">Panel Kinerja</p>
      <h1>Dashboard Admin</h1>
      <p class="muted">Pantau pengisian kinerja PJLP, pengajuan cuti, dan rekap bulanan dalam satu layar.</p>
    </div>
    <div class="page-actions">
      <a class="primary-action" href="{{ route('admin.reports.downloadZip', array_merge($activeFilters, ['month' => $month->month, 'year' => $month->year])) }}">Download ZIP PDF</a>
      <a class="ghost-action" href="{{ route('admin.leave.index') }}">Pengajuan Cuti</a>
    </div>
  </section>

  <section class="summary-strip">
    <article class="card stat modern-stat"><i>PD</i><strong>{{ $adminStats['totalPjlp'] }}</strong><span>PJLP sesuai filter</span><small>Data aktif {{ $monthLabel }}</small></article>
    <article class="card stat modern-stat green"><i>OK</i><strong>{{ $adminStats['doneToday'] }}</strong><span>Sudah isi hari ini</span><small>Terjadwal hari ini</small></article>
    <article class="card stat modern-stat gold"><i>BL</i><strong>{{ $adminStats['missingToday'] }}</strong><span>Belum isi hari ini</span><small>Perlu dipantau</small></article>
    <article class="card stat modern-stat red"><i>LB</i><strong>{{ $adminStats['holidays'] }}</strong><span>Libur bulan ini</span><small>Reguler saja</small></article>
  </section>

  <section class="dashboard-board">
    <article class="panel insight-panel">
      <div class="panel-header compact">
        <div>
          <h2>Kinerja Terbaru</h2>
          <p class="muted">Aktivitas terbaru bulan {{ $monthLabel }}.</p>
        </div>
        <a class="ghost-action" href="#monthly">Lihat Semua</a>
      </div>
      <div class="mini-list">
        @forelse ($recentEntries as $entry)
          <a class="mini-item" href="{{ route('admin.reports.show', ['user' => $entry->user, 'date' => \Carbon\Carbon::parse($entry->work_date)->toDateString()]) }}">
            <span class="avatar small">{{ \Illuminate\Support\Str::substr($entry->user?->name ?? 'P', 0, 1) }}</span>
            <span>
              <strong>{{ $entry->user?->name ?? 'PJLP' }}</strong>
              <small>{{ $entry->user?->jabatan ?: 'PJLP' }} - {{ $formatDate($entry->work_date) }}</small>
            </span>
            <em class="status-pill done">Sudah diisi</em>
          </a>
        @empty
          <p class="muted">Belum ada kinerja terbaru pada bulan ini.</p>
        @endforelse
      </div>
    </article>

    <article class="panel insight-panel">
      <div class="panel-header compact">
        <div>
          <h2>Pengajuan Cuti</h2>
          <p class="muted">Pantau cuti terbaru dari PJLP.</p>
        </div>
        <a class="ghost-action" href="{{ route('admin.leave.index') }}">Kelola</a>
      </div>
      <div class="mini-list">
        @forelse ($recentLeaveRequests as $leave)
          <a class="mini-item" href="{{ route('admin.leave.show', $leave) }}">
            <span class="avatar small">{{ \Illuminate\Support\Str::substr($leave->user?->name ?? 'C', 0, 1) }}</span>
            <span>
              <strong>{{ $leave->user?->name ?? 'PJLP' }}</strong>
              <small>{{ $formatDate($leave->start_date) }} - {{ $formatDate($leave->end_date) }}</small>
            </span>
            <em class="status-pill {{ $leave->status === 'approved' ? 'done' : ($leave->status === 'rejected' ? 'missing' : 'pending') }}">{{ ucfirst($leave->status) }}</em>
          </a>
        @empty
          <p class="muted">Belum ada pengajuan cuti terbaru.</p>
        @endforelse
      </div>
    </article>

    <article class="panel insight-panel">
      <div class="panel-header compact">
        <div>
          <h2>Kategori Kinerja</h2>
          <p class="muted">Persentase pengisian per jabatan.</p>
        </div>
      </div>
      <div class="role-progress-list">
        @foreach ($roleSummaries as $roleSummary)
          <div class="role-progress">
            <span>{{ $roleSummary['name'] }}</span>
            <div><i style="width: {{ $roleSummary['percentage'] }}%"></i></div>
            <strong>{{ $roleSummary['percentage'] }}%</strong>
          </div>
        @endforeach
      </div>
    </article>

    <article class="panel insight-panel">
      <div class="panel-header compact">
        <div>
          <h2>Quick Action</h2>
          <p class="muted">Akses cepat pekerjaan admin.</p>
        </div>
      </div>
      <div class="quick-grid">
        <a class="quick-card green" href="{{ route('admin.reports.downloadZip', array_merge($activeFilters, ['month' => $month->month, 'year' => $month->year])) }}"><strong>Download PDF</strong><span>Semua laporan sesuai filter</span></a>
        <a class="quick-card gold" href="{{ route('admin.export.csv', array_merge($activeFilters, ['month' => $month->month, 'year' => $month->year])) }}"><strong>Export Excel</strong><span>Data kinerja bulanan</span></a>
        <a class="quick-card blue" href="{{ route('admin.users.index') }}"><strong>Kelola User</strong><span>Tambah dan edit PJLP</span></a>
        <a class="quick-card red" href="{{ route('admin.holidays.index') }}"><strong>Kelola Libur</strong><span>Libur nasional dan manual</span></a>
      </div>
    </article>
  </section>

  <section class="panel">
    <div class="panel-header">
      <div>
        <h2>Dashboard Admin</h2>
        <p class="muted">Pantau pengisian kinerja PJLP pada bulan {{ $monthLabel }}.</p>
      </div>
      <div class="admin-toolbar">
        <a class="primary-action" href="{{ route('admin.reports.downloadZip', array_merge($activeFilters, ['month' => $month->month, 'year' => $month->year])) }}">Download ZIP PDF</a>
        <a class="ghost-action" href="{{ route('admin.export.csv', array_merge($activeFilters, ['month' => $month->month, 'year' => $month->year])) }}">Export Excel</a>
        <a class="ghost-action" href="{{ route('admin.holidays.index') }}">Kelola Libur</a>
        <div class="month-nav">
          <a class="icon-action" href="{{ route('dashboard', array_merge($activeFilters, ['month' => $prevMonth->month, 'year' => $prevMonth->year])) }}">&lsaquo;</a>
          <form class="month-jump-form admin-month-jump" method="get" action="{{ route('dashboard') }}">
            @if ($selectedRole)
              <input type="hidden" name="jabatan" value="{{ $selectedRole }}">
            @endif
            @if ($search)
              <input type="hidden" name="search" value="{{ $search }}">
            @endif
            <select name="month" aria-label="Pilih bulan admin">
              @for ($monthNumber = 1; $monthNumber <= 12; $monthNumber++)
                <option value="{{ $monthNumber }}" @selected($month->month === $monthNumber)>{{ $monthNames[$monthNumber] }}</option>
              @endfor
            </select>
            <select name="year" aria-label="Pilih tahun admin">
              @for ($year = now()->year - 3; $year <= now()->year + 2; $year++)
                <option value="{{ $year }}" @selected($month->year === $year)>{{ $year }}</option>
              @endfor
            </select>
          </form>
          <a class="icon-action" href="{{ route('dashboard', array_merge($activeFilters, ['month' => $nextMonth->month, 'year' => $nextMonth->year])) }}">&rsaquo;</a>
        </div>
      </div>
    </div>

    <form class="admin-filter-bar" method="get" action="{{ route('dashboard') }}" id="admin-filter-form">
      <input type="hidden" name="month" value="{{ $month->month }}">
      <input type="hidden" name="year" value="{{ $month->year }}">
      <label class="filter-search">
        <span>Cari Data</span>
        <input name="search" value="{{ $search }}" placeholder="Nama, username, email, NIP PJLP, NIK" autocomplete="off" data-live-search>
      </label>
      <div class="filter-tabs">
        <a class="filter-chip {{ $selectedRole ? '' : 'active' }}" href="{{ route('dashboard', array_filter(['month' => $month->month, 'year' => $month->year, 'search' => $search], fn ($value) => filled($value))) }}">Semua</a>
        @foreach ($jobRoles as $jobRole)
          <a class="filter-chip {{ $selectedRole === $jobRole ? 'active' : '' }}" href="{{ route('dashboard', array_filter(['month' => $month->month, 'year' => $month->year, 'jabatan' => $jobRole, 'search' => $search], fn ($value) => filled($value))) }}">{{ $jobRole }}</a>
        @endforeach
      </div>
      <button class="primary-action" type="submit">Cari</button>
      @if ($selectedRole || $search)
        <a class="ghost-action" href="{{ route('dashboard', ['month' => $month->month, 'year' => $month->year]) }}">Reset</a>
      @endif
    </form>

    <div class="table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>PJLP</th>
            <th>NIP PJLP</th>
            <th>Sudah Diisi</th>
            <th>Belum Diisi</th>
            <th>Terakhir Isi</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($pjlpUsers as $person)
            <tr data-user-row data-search-index="{{ Str::lower($person->name . ' ' . $person->username . ' ' . $person->email . ' ' . $person->nip . ' ' . $person->nik . ' ' . $person->jabatan . ' ' . $person->unit) }}">
              <td><strong>{{ $person->name }}</strong><br><span class="muted">{{ $person->jabatan ?: 'PJLP' }}</span></td>
              <td>{{ $person->nip ?: '-' }}</td>
              <td><span class="status-pill done">{{ $person->stats['done'] }} terisi</span></td>
              <td><span class="status-pill missing">{{ $person->stats['missing'] }} belum</span></td>
              <td>{{ $person->latest_entry_date ? $formatDate($person->latest_entry_date) : '-' }}</td>
              <td>
                @if ($person->latest_entry_date)
                  <a class="ghost-action" href="{{ route('admin.reports.show', ['user' => $person, 'date' => \Carbon\Carbon::parse($person->latest_entry_date)->toDateString()]) }}">Laporan</a>
                @else
                  -
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="6">Tidak ada data PJLP yang cocok.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  <section class="panel" id="monthly">
    <div class="panel-header">
      <div>
        <h2>All Kinerja Bulanan</h2>
        <p class="muted">Lihat semua status kinerja dari tanggal 1 sampai {{ $month->daysInMonth }} {{ $monthLabel }}.</p>
      </div>
      <div class="legend">
        <span><i class="dot green"></i>Sudah diisi</span>
        <span><i class="dot red"></i>Belum diisi</span>
        <span><i class="dot gray"></i>Libur / belum berjalan</span>
      </div>
    </div>

    <div class="table-wrap monthly-matrix-wrap">
      <table class="monthly-matrix">
        <thead>
          <tr>
            <th class="sticky-person">PJLP</th>
            @foreach ($monthDates as $date)
              <th class="{{ $date->isWeekend() ? 'weekend-head' : '' }}">
                <span>{{ $date->day }}</span>
                <small>{{ $dayNames[$date->dayOfWeek] }}</small>
              </th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @forelse ($pjlpUsers as $person)
            @php
              $entryDateSet = array_flip($person->entry_dates);
              $workDateSet = array_flip($person->work_dates);
            @endphp
            <tr data-user-row data-search-index="{{ Str::lower($person->name . ' ' . $person->username . ' ' . $person->email . ' ' . $person->nip . ' ' . $person->nik . ' ' . $person->jabatan . ' ' . $person->unit) }}">
              <td class="sticky-person">
                <strong>{{ $person->name }}</strong>
                <small>{{ $person->jabatan ?: 'PJLP' }}</small>
              </td>
              @foreach ($monthDates as $date)
                @php
                  $iso = $date->toDateString();
                  $hasEntry = isset($entryDateSet[$iso]);
                  $isWorkday = isset($workDateSet[$iso]);
                  $status = ! $isWorkday ? 'weekend' : ($date->greaterThan($today) ? 'future' : ($hasEntry ? 'done' : 'missing'));
                @endphp
                <td class="matrix-cell {{ $status }}">
                  @if ($hasEntry)
                    <a title="Lihat laporan {{ $formatDate($iso) }}"
                       href="{{ route('admin.reports.show', ['user' => $person, 'date' => $iso]) }}">✓</a>
                  @elseif (! $isWorkday)
                    <span>L</span>
                  @elseif ($date->greaterThan($today))
                    <span>-</span>
                  @else
                    <span>×</span>
                  @endif
                </td>
              @endforeach
            </tr>
          @empty
            <tr>
              <td colspan="{{ $month->daysInMonth + 1 }}">Tidak ada data PJLP yang cocok.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  <script>
    const liveSearchInput = document.querySelector('[data-live-search]');
    const searchableRows = document.querySelectorAll('[data-user-row]');

    const applyLiveSearch = () => {
      const keyword = liveSearchInput.value.trim().toLowerCase();
      searchableRows.forEach((row) => {
        row.hidden = keyword !== '' && !row.dataset.searchIndex.includes(keyword);
      });
    };

    liveSearchInput?.addEventListener('input', applyLiveSearch);
    window.addEventListener('DOMContentLoaded', applyLiveSearch);

    document.querySelector('#admin-filter-form')?.addEventListener('submit', () => {
      searchableRows.forEach((row) => row.hidden = false);
    });

    document.querySelectorAll('.admin-month-jump select').forEach((select) => {
      select.addEventListener('change', () => select.closest('form').submit());
    });
  </script>
@endsection
