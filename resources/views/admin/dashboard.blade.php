@extends('layouts.app')

@php
  $prevMonth = $month->copy()->subMonth();
  $nextMonth = $month->copy()->addMonth();
  $today = now()->startOfDay();
  $holidayDateSet = array_flip($holidayDates);
  $activeFilters = array_filter(['jabatan' => $selectedRole, 'search' => $search], fn ($value) => filled($value));
@endphp

@section('content')
  <section class="summary-strip">
    <article class="card stat"><strong>{{ $adminStats['totalPjlp'] }}</strong><span>PJLP sesuai filter</span></article>
    <article class="card stat"><strong>{{ $adminStats['doneToday'] }}</strong><span>Sudah isi hari ini</span></article>
    <article class="card stat"><strong>{{ $adminStats['missingToday'] }}</strong><span>Belum isi hari ini</span></article>
    <article class="card stat"><strong>{{ $adminStats['holidays'] }}</strong><span>Libur bulan ini</span></article>
  </section>

  <section class="panel">
    <div class="panel-header">
      <div>
        <h2>Dashboard Admin</h2>
        <p class="muted">Pantau pengisian kinerja PJLP pada bulan {{ $month->translatedFormat('F Y') }}.</p>
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
                <option value="{{ $monthNumber }}" @selected($month->month === $monthNumber)>{{ \Carbon\Carbon::create($month->year, $monthNumber, 1)->translatedFormat('F') }}</option>
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
              <td>{{ $person->latest_entry_date ? \Carbon\Carbon::parse($person->latest_entry_date)->translatedFormat('d F Y') : '-' }}</td>
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

  <section class="panel">
    <div class="panel-header">
      <div>
        <h2>All Kinerja Bulanan</h2>
        <p class="muted">Lihat semua status kinerja dari tanggal 1 sampai {{ $month->daysInMonth }} {{ $month->translatedFormat('F Y') }}.</p>
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
                <small>{{ $date->translatedFormat('D') }}</small>
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
                    <a title="Lihat laporan {{ $date->translatedFormat('d F Y') }}"
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
