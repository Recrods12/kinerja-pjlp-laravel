@extends('layouts.app')

@php
  $weekdayLabels = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
  $monthLabel = $monthNames[$month->month] . ' ' . $month->year;
  $previousMonth = $month->copy()->subMonth();
  $nextMonth = $month->copy()->addMonth();
  $statusClass = [
    \App\Models\LeaveRequest::STATUS_PENDING => 'pending',
    \App\Models\LeaveRequest::STATUS_APPROVED => 'done',
    \App\Models\LeaveRequest::STATUS_REJECTED => 'missing',
  ];
@endphp

@section('content')
  <div class="page-heading">
    <div>
      <p class="eyebrow">Kalender Cuti</p>
      <h1>Kalender Cuti Saya</h1>
      <p class="muted">Lihat posisi pengajuan cuti Anda dalam kalender bulanan.</p>
    </div>
    <div class="page-actions">
      <a class="ghost-action" href="{{ route('leave.index') }}">Riwayat Cuti</a>
      <a class="primary-action" href="{{ route('leave.create') }}">Ajukan Cuti</a>
    </div>
  </div>

  <section class="summary-strip">
    <article class="card modern-stat gold"><i>PN</i><strong>{{ $summary['pending'] }}</strong><span>Menunggu</span><small>{{ $monthLabel }}</small></article>
    <article class="card modern-stat green"><i>OK</i><strong>{{ $summary['approved'] }}</strong><span>Disetujui</span><small>Sudah diproses</small></article>
    <article class="card modern-stat red"><i>RJ</i><strong>{{ $summary['rejected'] }}</strong><span>Ditolak</span><small>Sudah diproses</small></article>
    <article class="card modern-stat blue"><i>SC</i><strong>{{ auth()->user()->annual_leave_remaining }}</strong><span>Sisa Cuti</span><small>Masih tersedia</small></article>
  </section>

  <section class="panel management-panel leave-calendar-panel">
    <div class="panel-header compact">
      <div>
        <h2>{{ $monthLabel }}</h2>
        <p class="muted">Warna kalender mengikuti status pengajuan cuti Anda.</p>
      </div>
      <div class="month-nav">
        <a class="ghost-action" href="{{ route('leave.calendar', ['month' => $previousMonth->month, 'year' => $previousMonth->year]) }}">&lsaquo;</a>
        <form class="month-jump-form admin-month-jump" method="get" action="{{ route('leave.calendar') }}">
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
        <a class="ghost-action" href="{{ route('leave.calendar', ['month' => $nextMonth->month, 'year' => $nextMonth->year]) }}">&rsaquo;</a>
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
          @php($firstRequest = $day['requests']->first())
          <article class="leave-calendar-day {{ $day['date']->isToday() ? 'today' : '' }} {{ $firstRequest ? ($statusClass[$firstRequest->status] ?? '') : '' }}">
            <div class="leave-day-head">
              <strong>{{ $day['date']->day }}</strong>
              @if ($day['requests']->isNotEmpty())
                <span>{{ $day['requests']->count() }} cuti</span>
              @endif
            </div>
            <div class="leave-day-list">
              @forelse ($day['requests']->take(2) as $leaveRequest)
                <a href="{{ route('leave.show', $leaveRequest) }}">{{ $leaveRequest->reason }} <small>{{ ucfirst($leaveRequest->status) }}</small></a>
              @empty
                <span class="muted">-</span>
              @endforelse
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
        <p class="muted">Semua pengajuan cuti Anda yang bersinggungan dengan bulan ini.</p>
      </div>
    </div>

    @if ($leaveRequests->isEmpty())
      <p class="muted">Belum ada pengajuan cuti pada bulan ini.</p>
    @else
      <div class="leave-calendar-list">
        @foreach ($leaveRequests as $leaveRequest)
          <a class="leave-list-card" href="{{ route('leave.show', $leaveRequest) }}">
            <span class="avatar small">{{ \Illuminate\Support\Str::substr($leaveRequest->reason, 0, 1) }}</span>
            <span>
              <strong>{{ $leaveRequest->reason }}</strong>
              <small>{{ $leaveRequest->total_days }} {{ ucfirst($leaveRequest->duration_unit ?: 'hari') }}</small>
            </span>
            <span>
              <strong>{{ $leaveRequest->start_date->translatedFormat('d F Y') }} - {{ $leaveRequest->end_date->translatedFormat('d F Y') }}</strong>
              <small>@include('partials.leave-status', ['status' => $leaveRequest->status])</small>
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
