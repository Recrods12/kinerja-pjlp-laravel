@extends('layouts.app')

@php
  $weekdayLabels = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
  $monthLabel = $monthNames[$month->month] . ' ' . $month->year;
  $previousMonth = $month->copy()->subMonth();
  $nextMonth = $month->copy()->addMonth();
@endphp

@section('content')
  <div class="page-heading">
    <div>
      <p class="eyebrow">Kalender Cuti</p>
      <h1>Jadwal Cuti Admin</h1>
      <p class="muted">Lihat jadwal cuti PJLP yang sudah disetujui dalam tampilan kalender bulanan.</p>
    </div>
    <div class="page-actions">
      <a class="ghost-action" href="{{ route('admin.leave.index') }}">Pengajuan Cuti</a>
      <a class="ghost-action" href="{{ route('dashboard') }}">Dashboard</a>
    </div>
  </div>

  <section class="summary-strip">
    <article class="card modern-stat green"><i>OK</i><strong>{{ $summary['approved'] }}</strong><span>Cuti Disetujui</span><small>{{ $monthLabel }}</small></article>
    <article class="card modern-stat blue"><i>PJ</i><strong>{{ $summary['people'] }}</strong><span>PJLP Cuti</span><small>Orang berbeda</small></article>
    <article class="card modern-stat gold"><i>HR</i><strong>{{ $summary['active_today'] }}</strong><span>Cuti Hari Ini</span><small>Sedang berjalan</small></article>
    <article class="card modern-stat red"><i>PN</i><strong>{{ $summary['pending'] }}</strong><span>Menunggu</span><small>Perlu diproses</small></article>
  </section>

  <section class="panel management-panel leave-calendar-panel">
    <div class="panel-header compact">
      <div>
        <h2>{{ $monthLabel }}</h2>
        <p class="muted">Tanggal yang berisi nama berarti ada cuti yang sudah disetujui.</p>
      </div>
      <div class="month-nav">
        <a class="ghost-action" href="{{ route('admin.leave.calendar', ['month' => $previousMonth->month, 'year' => $previousMonth->year]) }}">&lsaquo;</a>
        <form class="month-jump-form admin-month-jump" method="get" action="{{ route('admin.leave.calendar') }}">
          <select name="month" aria-label="Pilih bulan kalender cuti">
            @foreach ($monthNames as $monthNumber => $name)
              <option value="{{ $monthNumber }}" @selected($month->month === $monthNumber)>{{ $name }}</option>
            @endforeach
          </select>
          <select name="year" aria-label="Pilih tahun kalender cuti">
            @for ($year = now()->year - 4; $year <= now()->year + 4; $year++)
              <option value="{{ $year }}" @selected($month->year === $year)>{{ $year }}</option>
            @endfor
          </select>
        </form>
        <a class="ghost-action" href="{{ route('admin.leave.calendar', ['month' => $nextMonth->month, 'year' => $nextMonth->year]) }}">&rsaquo;</a>
      </div>
    </div>

    <div class="leave-calendar-grid">
      @foreach ($weekdayLabels as $label)
        <div class="leave-weekday">{{ $label }}</div>
      @endforeach

      @foreach ($calendarDays as $day)
        @if (! $day['date'])
          <div class="leave-calendar-day empty" aria-hidden="true"></div>
        @else
          <article class="leave-calendar-day {{ $day['date']->isToday() ? 'today' : '' }} {{ $day['requests']->isNotEmpty() ? 'has-leave' : '' }}">
            <div class="leave-day-head">
              <strong>{{ $day['date']->day }}</strong>
              @if ($day['requests']->isNotEmpty())
                <span>{{ $day['requests']->count() }} cuti</span>
              @endif
            </div>
            <div class="leave-day-list">
              @forelse ($day['requests']->take(3) as $leaveRequest)
                <a href="{{ route('admin.leave.show', $leaveRequest) }}">{{ $leaveRequest->user->name }} <small>{{ $leaveRequest->user->jabatan ?: 'PJLP' }}</small></a>
              @empty
                <span class="muted">-</span>
              @endforelse
              @if ($day['requests']->count() > 3)
                <em>+{{ $day['requests']->count() - 3 }} lainnya</em>
              @endif
            </div>
          </article>
        @endif
      @endforeach
    </div>
  </section>

  <section class="panel management-panel">
    <div class="panel-header compact">
      <div>
        <h2>Daftar Cuti {{ $monthLabel }}</h2>
        <p class="muted">Detail cuti yang masuk ke kalender bulan ini.</p>
      </div>
    </div>

    @if ($leaveRequests->isEmpty())
      <p class="muted">Belum ada cuti yang disetujui pada bulan ini.</p>
    @else
      <div class="leave-calendar-list">
        @foreach ($leaveRequests as $leaveRequest)
          <a class="leave-list-card" href="{{ route('admin.leave.show', $leaveRequest) }}">
            <span class="avatar small">{{ \Illuminate\Support\Str::substr($leaveRequest->user->name, 0, 1) }}</span>
            <span>
              <strong>{{ $leaveRequest->user->name }}</strong>
              <small>{{ $leaveRequest->user->jabatan ?: 'PJLP' }} &middot; {{ $leaveRequest->user->nip ?: '-' }}</small>
            </span>
            <span>
              <strong>{{ $leaveRequest->start_date->translatedFormat('d F Y') }} - {{ $leaveRequest->end_date->translatedFormat('d F Y') }}</strong>
              <small>{{ $leaveRequest->total_days }} {{ ucfirst($leaveRequest->duration_unit ?: 'hari') }}</small>
            </span>
          </a>
        @endforeach
      </div>
    @endif
  </section>

  <script>
    document.querySelectorAll('.leave-calendar-panel .month-jump-form select').forEach((select) => {
      select.addEventListener('change', () => select.form.submit());
    });
  </script>
@endsection
