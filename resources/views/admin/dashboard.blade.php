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
  // Daily trend data for chart
  $dailyLabels = [];
  $dailyDone = [];
  $dailyMissing = [];
  foreach (range(1, $month->daysInMonth) as $day) {
    $date = $month->copy()->day($day);
    $iso = $date->toDateString();
    $doneCount = 0;
    $missingCount = 0;
    foreach ($pjlpUsers as $person) {
      if (in_array($iso, $person->work_dates ?? [], true)) {
        in_array($iso, $person->entry_dates ?? [], true) ? $doneCount++ : ($date->lte($today) ? $missingCount++ : null);
      }
    }
    $dailyLabels[] = $day;
    $dailyDone[] = $doneCount;
    $dailyMissing[] = $missingCount;
  }

  // Overall totals
  $totalDoneAll = $roleSummaries->sum('done');
  $totalMissingAll = $roleSummaries->sum(fn ($r) => $r['total'] - $r['done']);

  // Chart colors per role
  $chartColors = ['#0d6f4b', '#e8a838', '#3b82f6', '#a855f7', '#ef4444', '#06b6d4', '#f97316', '#84cc16'];

  $formatDate = fn ($date) => \Carbon\Carbon::parse($date)->format('d') . ' ' . $monthNames[\Carbon\Carbon::parse($date)->month] . ' ' . \Carbon\Carbon::parse($date)->year;

  // Relative time helper - pakai Carbon diffForHumans
  $timeAgo = function ($dateTime) {
    if (! $dateTime) return '-';
    return \Carbon\Carbon::parse($dateTime)->diffForHumans(['parts' => 1]);
  };

  // Jabatan color mapping for avatar badges
  $jabatanColors = [
    'Driver' => ['bg' => '#3b82f6', 'soft' => 'rgba(59,130,246,.14)'],
    'Kebersihan' => ['bg' => '#0d6f4b', 'soft' => 'rgba(13,111,75,.14)'],
    'Keamanan' => ['bg' => '#ef4444', 'soft' => 'rgba(239,68,68,.14)'],
    'Mekanikal Enginer' => ['bg' => '#a855f7', 'soft' => 'rgba(168,85,247,.14)'],
    'Pelayanan Umum' => ['bg' => '#e8a838', 'soft' => 'rgba(232,168,56,.14)'],
  ];
  $defaultJabatanColor = ['bg' => '#6b7b73', 'soft' => 'rgba(107,123,115,.14)'];
  $jabatanColor = fn ($jabatan) => $jabatanColors[$jabatan] ?? $defaultJabatanColor;

  // Role count map for filter badges (from unfiltered controller data)
  $roleCountMap = $roleTotalCounts;
  $totalPjlpCount = array_sum($roleCountMap);
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
          @php
            $entryJabatan = $entry->user?->jabatan ?: 'PJLP';
            $entryColor = $jabatanColor($entryJabatan);
            $entryTime = $entry->work_time ?: ($entry->created_at ? $entry->created_at->format('H:i') : '');
          @endphp
          <a class="mini-item" href="{{ route('admin.reports.show', ['user' => $entry->user, 'date' => \Carbon\Carbon::parse($entry->work_date)->toDateString()]) }}">
            @if ($entry->user?->avatar_path)
              <img class="avatar small" src="{{ asset('storage/' . $entry->user->avatar_path) }}" alt="Foto {{ $entry->user->name }}">
            @else
              <span class="avatar small" style="background: {{ $entryColor['bg'] }}">{{ \Illuminate\Support\Str::substr($entry->user?->name ?? 'P', 0, 1) }}</span>
            @endif
            <span>
              <strong>{{ $entry->user?->name ?? 'PJLP' }}</strong>
              <small>{{ $entryJabatan }}</small>
              @if ($entry->task)
                <span class="mini-preview">{{ \Illuminate\Support\Str::limit($entry->task, 50) }}</span>
              @endif
            </span>
            <span class="mini-meta">
              <em class="mini-time">{{ $timeAgo($entry->updated_at ?? $entry->created_at) }}</em>
              @if ($entryTime)
                <em class="mini-clock">{{ $entryTime }} WIB</em>
              @endif
            </span>
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
          @php
            $leaveStatus = $leave->status === 'approved' ? 'done' : ($leave->status === 'rejected' ? 'missing' : 'pending');
            $leaveStatusLabel = $leave->status === 'approved' ? 'Disetujui' : ($leave->status === 'rejected' ? 'Ditolak' : 'Menunggu');
            $leaveDays = $leave->total_days . ' ' . ($leave->duration_unit ?? 'hari');
            $leaveRemaining = $leave->user?->annual_leave_remaining ?? '-';
          @endphp
          <a class="mini-item" href="{{ route('admin.leave.show', $leave) }}">
            @if ($leave->user?->avatar_path)
              <img class="avatar small {{ $leaveStatus }}" src="{{ asset('storage/' . $leave->user->avatar_path) }}" alt="Foto {{ $leave->user->name }}">
            @else
              <span class="avatar small {{ $leaveStatus }}">{{ \Illuminate\Support\Str::substr($leave->user?->name ?? 'C', 0, 1) }}</span>
            @endif
            <span>
              <strong>{{ $leave->user?->name ?? 'PJLP' }}</strong>
              <small>{{ $formatDate($leave->start_date) }} - {{ $formatDate($leave->end_date) }}</small>
              <span class="mini-preview">{{ $leaveDays }} &middot; Sisa {{ $leaveRemaining }} hari</span>
            </span>
            <span class="mini-meta">
              <em class="mini-time">{{ $timeAgo($leave->created_at) }}</em>
              <em class="status-pill {{ $leaveStatus }}">{{ $leaveStatusLabel }}</em>
            </span>
          </a>
        @empty
          <p class="muted">Belum ada pengajuan cuti terbaru.</p>
        @endforelse
      </div>
    </article>    <article class="panel insight-panel rekap-panel">
      <div class="panel-header compact">
        <div>
          <h2>Rekap Bulanan</h2>
          <p class="muted">Kinerja {{ $monthLabel }} &mdash; {{ $adminStats['totalPjlp'] }} PJLP</p>
        </div>
      </div>
      <div class="rekap-grid">
        <div class="rekap-chart-area">
          <div class="chart-donut-box">
            <canvas id="donutChart"></canvas>
            <div class="chart-donut-label">
              <strong>{{ $totalDoneAll + $totalMissingAll > 0 ? round(($totalDoneAll / ($totalDoneAll + $totalMissingAll)) * 100) : 0 }}%</strong>
              <span>terisi</span>
            </div>
          </div>
          <div class="chart-donut-legend">
            <span><i style="background:#0d6f4b"></i> {{ $totalDoneAll }} Sudah Diisi</span>
            <span><i style="background:#d63c3c"></i> {{ $totalMissingAll }} Belum Diisi</span>
          </div>
        </div>
        <div class="rekap-stats-col">
          @php
            $totalPjlp = $adminStats['totalPjlp'];
            $workDaysCount = 0;
            foreach ($monthDates as $d) {
              if (!$d->isWeekend() && !isset($holidayDates[$d->toDateString()]) && $d->lte($today)) {
                $workDaysCount++;
              }
            }
            $avgPerDay = $workDaysCount > 0 ? round($totalDoneAll / $workDaysCount, 1) : 0;
          @endphp
          <div class="rekap-stat">
            <span class="rekap-stat-icon blue"><i>PD</i></span>
            <div>
              <strong>{{ $totalPjlp }}</strong>
              <small>Total PJLP</small>
            </div>
          </div>
          <div class="rekap-stat">
            <span class="rekap-stat-icon green"><i>OK</i></span>
            <div>
              <strong>{{ $totalDoneAll }}</strong>
              <small>Total Entri Terisi</small>
            </div>
          </div>
          <div class="rekap-stat">
            <span class="rekap-stat-icon gold"><i>HK</i></span>
            <div>
              <strong>{{ $workDaysCount }}</strong>
              <small>Hari Kerja (terlampaui)</small>
            </div>
          </div>
          <div class="rekap-stat">
            <span class="rekap-stat-icon info"><i>Ø</i></span>
            <div>
              <strong>{{ $avgPerDay }}</strong>
              <small>Rata-rata isian/hari</small>
            </div>
          </div>
        </div>
      </div>
    </article>

    <article class="panel insight-panel quick-side-panel">
      <div class="panel-header compact">
        <div>
          <h2>Quick Action</h2>
          <p class="muted">Akses cepat admin.</p>
        </div>
      </div>
      <div class="quick-grid sidebar-quick-grid">
        <a class="quick-card green" href="{{ route('admin.attendance.index') }}">
          <span class="quick-icon">📋</span>
          <span class="quick-text">
            <strong>Dashboard Absensi</strong>
            <span>Pantau hadir, dinas luar, izin, dan alfa</span>
          </span>
        </a>
        <a class="quick-card green" href="{{ route('admin.reports.downloadZip', array_merge($activeFilters, ['month' => $month->month, 'year' => $month->year])) }}">
          <span class="quick-icon">📄</span>
          <span class="quick-text">
            <strong>Download PDF</strong>
            <span>Semua laporan sesuai filter</span>
          </span>
        </a>
        <a class="quick-card gold" href="{{ route('admin.export.csv', array_merge($activeFilters, ['month' => $month->month, 'year' => $month->year])) }}">
          <span class="quick-icon">📊</span>
          <span class="quick-text">
            <strong>Export Excel</strong>
            <span>Data kinerja bulanan</span>
          </span>
        </a>
        <a class="quick-card blue" href="{{ route('admin.users.index') }}">
          <span class="quick-icon">👥</span>
          <span class="quick-text">
            <strong>Kelola User</strong>
            <span>Tambah dan edit PJLP</span>
          </span>
        </a>
        <a class="quick-card red" href="{{ route('admin.holidays.index') }}">
          <span class="quick-icon">📅</span>
          <span class="quick-text">
            <strong>Kelola Libur</strong>
            <span>Libur nasional dan manual</span>
          </span>
        </a>
      </div>
    </article>

    <article class="panel insight-panel chart-panel-wide" style="grid-column: span 2;">
      <div class="panel-header compact">
        <div>
          <h2>Kinerja Per Jabatan</h2>
          <p class="muted">Perbandingan pengisian per posisi.</p>
        </div>
      </div>
      <div class="chart-bar-wrap">
        <canvas id="barChart"></canvas>
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
          <a class="filter-chip {{ $selectedRole ? '' : 'active' }}" href="{{ route('dashboard', array_filter(['month' => $month->month, 'year' => $month->year, 'search' => $search], fn ($value) => filled($value))) }}">Semua <span class="badge">{{ $totalPjlpCount }}</span></a>
          @foreach ($jobRoles as $jobRole)
            <a class="filter-chip {{ $selectedRole === $jobRole ? 'active' : '' }}" href="{{ route('dashboard', array_filter(['month' => $month->month, 'year' => $month->year, 'jabatan' => $jobRole, 'search' => $search], fn ($value) => filled($value))) }}">{{ $jobRole }} <span class="badge">{{ $roleCountMap[$jobRole] ?? 0 }}</span></a>
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
              <th>Jabatan</th>
              <th>Progres</th>
              <th>%</th>
              <th>Terakhir Isi</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($pjlpUsers as $person)
              @php
                $doneCount = $person->stats['done'];
                $missingCount = $person->stats['missing'];
                $totalCount = $doneCount + $missingCount;
                $pct = $totalCount > 0 ? round(($doneCount / $totalCount) * 100) : 0;
                $barColor = $pct >= 80 ? '#0d6f4b' : ($pct >= 50 ? '#e8a838' : '#d63c3c');
              @endphp
              <tr data-user-row data-search-index="{{ Str::lower($person->name . ' ' . $person->username . ' ' . $person->email . ' ' . $person->nip . ' ' . $person->nik . ' ' . $person->jabatan . ' ' . $person->unit) }}">
                <td class="admin-user-cell">
                  @if ($person->avatar_path)
                    <img class="admin-avatar" src="{{ asset('storage/' . $person->avatar_path) }}" alt="Foto {{ $person->name }}">
                  @else
                    <span class="admin-avatar" style="background: {{ $jabatanColor($person->jabatan ?: 'PJLP')['bg'] }}">{{ \Illuminate\Support\Str::substr($person->name, 0, 1) }}</span>
                  @endif
                  <strong>{{ $person->name }}</strong>
                </td>
                <td>{{ $person->nip ?: '-' }}</td>
                <td><span class="muted">{{ $person->jabatan ?: 'PJLP' }}</span></td>
                <td class="admin-progress-cell">
                  <div class="admin-progress-bar">
                    <i style="width: {{ $pct }}%; background: {{ $barColor }}"></i>
                  </div>
                  <span class="admin-progress-label">{{ $doneCount }}/{{ $totalCount }}</span>
                </td>
                <td><strong style="color: {{ $barColor }}">{{ $pct }}%</strong></td>
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
              <tr><td colspan="7">Tidak ada data PJLP yang cocok.</td></tr>
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

  <section class="panel insight-panel chart-panel-line">
    <div class="panel-header compact">
      <div>
        <h2>Tren Pengisian Harian</h2>
        <p class="muted">Jumlah PJLP yang sudah vs belum mengisi kinerja per tanggal pada {{ $monthLabel }}.</p>
      </div>
    </div>
    <div class="chart-line-wrap">
      <canvas id="lineChart"></canvas>
    </div>
  </section>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      const textColor = isDark ? '#e2e8f0' : '#33433d';
      const gridColor = isDark ? '#334155' : '#e2e8f0';

      // --- Donut Chart ---
      const donutCtx = document.getElementById('donutChart');
      if (donutCtx) {
        new Chart(donutCtx, {
          type: 'doughnut',
          data: {
            labels: ['Sudah Diisi', 'Belum Diisi'],
            datasets: [{
              data: [{{ $totalDoneAll }}, {{ $totalMissingAll }}],
              backgroundColor: ['#0d6f4b', '#d63c3c'],
              borderWidth: 0,
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '70%',
            plugins: {
              legend: { display: false },
              tooltip: {
                callbacks: {
                  label: (ctx) => ctx.parsed + ' PJLP'
                }
              }
            }
          }
        });
      }

      // --- Bar Chart (Per Jabatan) ---
      const barCtx = document.getElementById('barChart');
      if (barCtx) {
        new Chart(barCtx, {
          type: 'bar',
          data: {
            labels: [
              @foreach ($roleSummaries as $rs)
                '{{ $rs['name'] }}',
              @endforeach
            ],
            datasets: [{
              label: 'Sudah Diisi',
              data: [
                @foreach ($roleSummaries as $rs)
                  {{ $rs['done'] }},
                @endforeach
              ],
              backgroundColor: '#0d6f4b',
              borderRadius: 4,
            }, {
              label: 'Belum Diisi',
              data: [
                @foreach ($roleSummaries as $rs)
                  {{ $rs['total'] - $rs['done'] }},
                @endforeach
              ],
              backgroundColor: '#d63c3c',
              borderRadius: 4,
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: {
                position: 'bottom',
                labels: { color: textColor, font: { size: 12 } }
              }
            },
            scales: {
              x: {
                stacked: true,
                ticks: { color: textColor, font: { size: 11 } },
                grid: { color: gridColor }
              },
              y: {
                stacked: true,
                beginAtZero: true,
                ticks: { color: textColor, font: { size: 11 }, stepSize: 1 },
                grid: { color: gridColor }
              }
            }
          }
        });
      }

      // --- Line Chart (Tren Harian) --- with gradient fill
      const lineCtx = document.getElementById('lineChart');
      if (lineCtx) {
        const lineChart = new Chart(lineCtx, {
          type: 'line',
          data: {
            labels: [{{ implode(', ', $dailyLabels) }}],
            datasets: [{
              label: 'Sudah Diisi',
              data: [{{ implode(', ', $dailyDone) }}],
              borderColor: '#0d6f4b',
              backgroundColor: function(context) {
                const chart = context.chart;
                const {ctx, chartArea} = chart;
                if (!chartArea) return 'rgba(13,111,75,0.1)';
                const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                gradient.addColorStop(0, 'rgba(13,111,75,0.40)');
                gradient.addColorStop(0.5, 'rgba(13,111,75,0.20)');
                gradient.addColorStop(1, 'rgba(13,111,75,0.02)');
                return gradient;
              },
              fill: true,
              tension: 0.35,
              pointRadius: 3,
              pointHoverRadius: 6,
              pointBackgroundColor: '#0d6f4b',
              pointBorderColor: '#fff',
              pointBorderWidth: 2,
            }, {
              label: 'Belum Diisi',
              data: [{{ implode(', ', $dailyMissing) }}],
              borderColor: '#d63c3c',
              backgroundColor: function(context) {
                const chart = context.chart;
                const {ctx, chartArea} = chart;
                if (!chartArea) return 'rgba(214,60,60,0.1)';
                const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                gradient.addColorStop(0, 'rgba(214,60,60,0.40)');
                gradient.addColorStop(0.5, 'rgba(214,60,60,0.20)');
                gradient.addColorStop(1, 'rgba(214,60,60,0.02)');
                return gradient;
              },
              fill: true,
              tension: 0.35,
              pointRadius: 3,
              pointHoverRadius: 6,
              pointBackgroundColor: '#d63c3c',
              pointBorderColor: '#fff',
              pointBorderWidth: 2,
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
              intersect: false,
              mode: 'index',
            },
            plugins: {
              legend: {
                position: 'bottom',
                labels: { color: textColor, font: { size: 12 } }
              },
              tooltip: {
                callbacks: {
                  label: (ctx) => ctx.dataset.label + ': ' + ctx.parsed.y + ' PJLP'
                }
              }
            },
            scales: {
              x: {
                title: { display: true, text: 'Tanggal', color: textColor, font: { size: 11 } },
                ticks: { color: textColor, font: { size: 10 } },
                grid: { color: gridColor }
              },
              y: {
                beginAtZero: true,
                ticks: { color: textColor, font: { size: 11 }, stepSize: 1 },
                grid: { color: gridColor }
              }
            }
          }
        });
      }
    });

    // --- Existing Live Search ---
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
