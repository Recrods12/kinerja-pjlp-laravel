@extends('layouts.app')

@php
  use App\Models\AttendanceRecord;

  $monthNames = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
  $dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
  $dateLabel = $dayNames[$date->dayOfWeek] . ', ' . $date->format('d') . ' ' . $monthNames[$date->month] . ' ' . $date->year;
  $formatTime = fn ($record) => $record ? $record->recorded_at->format('H:i') . ' WIB' : 'Belum Absen';
@endphp

@section('content')
  <section class="page-heading">
    <div>
      <p class="eyebrow">Absensi Mobile</p>
      <h1>Selamat Pagi, {{ $user->name }}</h1>
      <p class="muted">Pilih jenis absensi sesuai kondisi kerja lapangan hari ini.</p>
    </div>
    <div class="page-actions">
      <a class="ghost-action" href="{{ route('dashboard') }}">Dashboard</a>
    </div>
  </section>

  <section class="attendance-hero">
    <div>
      <span>{{ $dateLabel }}</span>
      <strong data-server-clock data-start="{{ now()->toIso8601String() }}">{{ now()->format('H:i:s') }}</strong>
      <small>WIB - Jam server</small>
    </div>
    <p>{{ $user->unit ?: 'Dinas Tenaga Kerja, Transmigrasi dan Energi Provinsi DKI Jakarta' }}</p>
  </section>

  <section class="attendance-grid">
    <a class="attendance-action-card green" href="{{ $summary['start'] ? route('attendance.show', $summary['start']) : route('attendance.create', AttendanceRecord::TYPE_START) }}">
      <i>IN</i>
      <span>
        <strong>Absen Awal</strong>
        <small>Mulai kerja pagi / tugas lapangan</small>
      </span>
      <em>{{ $formatTime($summary['start']) }}</em>
    </a>
    <a class="attendance-action-card red" href="{{ $summary['end'] ? route('attendance.show', $summary['end']) : route('attendance.create', AttendanceRecord::TYPE_END) }}">
      <i>OUT</i>
      <span>
        <strong>Absen Akhir</strong>
        <small>Pulang / selesai tugas setelah 12.00</small>
      </span>
      <em>{{ $formatTime($summary['end']) }}</em>
    </a>
    <a class="attendance-action-card blue" href="{{ $summary['field'] ? route('attendance.show', $summary['field']) : route('attendance.create', AttendanceRecord::TYPE_FIELD) }}">
      <i>DL</i>
      <span>
        <strong>Dinas Luar</strong>
        <small>1x absen jika tidak kembali ke kantor</small>
      </span>
      <em>{{ $formatTime($summary['field']) }}</em>
    </a>
  </section>

  <section class="dashboard-board">
    <article class="panel insight-panel">
      <div class="panel-header compact">
        <div>
          <h2>Status Hari Ini</h2>
          <p class="muted">Ringkasan absensi {{ $dateLabel }}.</p>
        </div>
      </div>
      <div class="attendance-status-list">
        @if ($summary['start'])
          <a class="attendance-status-row" href="{{ route('attendance.show', $summary['start']) }}"><span class="status-dot done"></span><strong>Absen Awal</strong><em>{{ $formatTime($summary['start']) }}</em></a>
        @else
          <div><span class="status-dot neutral"></span><strong>Absen Awal</strong><em>Belum Absen</em></div>
        @endif

        @if ($summary['end'])
          <a class="attendance-status-row" href="{{ route('attendance.show', $summary['end']) }}"><span class="status-dot done"></span><strong>Absen Akhir</strong><em>{{ $formatTime($summary['end']) }}</em></a>
        @else
          <div><span class="status-dot neutral"></span><strong>Absen Akhir</strong><em>Belum Absen</em></div>
        @endif

        @if ($summary['field'])
          <a class="attendance-status-row" href="{{ route('attendance.show', $summary['field']) }}"><span class="status-dot field"></span><strong>Dinas Luar</strong><em>{{ $formatTime($summary['field']) }}</em></a>
        @else
          <div><span class="status-dot neutral"></span><strong>Dinas Luar</strong><em>Tidak Aktif</em></div>
        @endif
      </div>
    </article>

    <article class="panel insight-panel">
      <div class="panel-header compact">
        <div>
          <h2>Riwayat Absensi</h2>
          <p class="muted">Aktivitas absensi terbaru.</p>
        </div>
      </div>
      <div class="mini-list">
        @forelse ($recentRecords as $record)
          <a class="mini-item" href="{{ route('attendance.show', $record) }}">
            <span class="avatar small">{{ \Illuminate\Support\Str::substr($record->label(), 0, 1) }}</span>
            <span>
              <strong>{{ $record->label() }}</strong>
              <small>{{ $record->work_date->format('d') }} {{ $monthNames[$record->work_date->month] }} {{ $record->work_date->year }} - {{ $record->recorded_at->format('H:i') }} WIB</small>
            </span>
            <em class="status-pill done">Tersimpan</em>
          </a>
        @empty
          <p class="muted">Belum ada riwayat absensi.</p>
        @endforelse
      </div>
    </article>
  </section>

  <script>
    const clock = document.querySelector('[data-server-clock]');
    if (clock) {
      const start = new Date(clock.dataset.start).getTime();
      const clientStart = Date.now();
      setInterval(() => {
        const current = new Date(start + (Date.now() - clientStart));
        clock.textContent = current.toLocaleTimeString('id-ID', { hour12: false });
      }, 1000);
    }
  </script>
@endsection
