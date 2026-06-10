@extends('layouts.app')

@php
  $monthNames = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
  $dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
  $date = $attendanceRecord->work_date;
  $dateLabel = $dayNames[$date->dayOfWeek] . ', ' . $date->format('d') . ' ' . $monthNames[$date->month] . ' ' . $date->year;
@endphp

@section('content')
  <section class="page-heading">
    <div>
      <p class="eyebrow">Detail Absensi</p>
      <h1>{{ $attendanceRecord->label() }}</h1>
      <p class="muted">{{ $dateLabel }} - {{ $attendanceRecord->recorded_at->format('H:i:s') }} WIB</p>
    </div>
    <div class="page-actions">
      <a class="ghost-action" href="{{ route('attendance.index', ['date' => $date->toDateString()]) }}">Kembali</a>
    </div>
  </section>

  <section class="dashboard-board">
    @foreach ($records as $record)
      <article class="panel attendance-detail-card">
        <div class="panel-header compact">
          <div>
            <p class="eyebrow">{{ $record->is($attendanceRecord) ? 'Absensi Dipilih' : 'Absensi Hari Ini' }}</p>
            <h2>{{ $record->label() }}</h2>
            <p class="muted">{{ $record->recorded_at->format('H:i:s') }} WIB</p>
          </div>
          <span class="status-pill done">Tersimpan</span>
        </div>

        <dl class="detail-list">
          <div>
            <dt>Tanggal</dt>
            <dd>{{ $dateLabel }}</dd>
          </div>
          <div>
            <dt>Lokasi</dt>
            <dd>{{ $record->address ?: 'Koordinat tersimpan' }}</dd>
          </div>
          <div>
            <dt>Koordinat</dt>
            <dd>{{ $record->latitude && $record->longitude ? $record->latitude . ', ' . $record->longitude : '-' }}</dd>
          </div>
          <div>
            <dt>Akurasi</dt>
            <dd>{{ $record->accuracy ? $record->accuracy . ' meter' : '-' }}</dd>
          </div>
          <div>
            <dt>Keterangan</dt>
            <dd>{{ $record->note ?: '-' }}</dd>
          </div>
        </dl>

        @if ($record->latitude && $record->longitude)
          <div class="map-preview admin-map">
            <span>{{ $record->latitude }}, {{ $record->longitude }}</span>
            <a href="https://www.google.com/maps?q={{ $record->latitude }},{{ $record->longitude }}" target="_blank" rel="noopener">Lihat Peta</a>
          </div>
        @endif

        @if ($record->selfie_path)
          <img class="attendance-selfie" src="{{ asset('storage/' . $record->selfie_path) }}" alt="Foto selfie {{ $record->label() }}">
        @else
          <p class="muted">Foto selfie belum tersedia.</p>
        @endif
      </article>
    @endforeach
  </section>
@endsection
