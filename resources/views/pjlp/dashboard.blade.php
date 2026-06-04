@extends('layouts.app')

@php
  $prevMonth = $month->copy()->subMonth();
  $nextMonth = $month->copy()->addMonth();
  $entryDateSet = array_flip($entryDates);
  $holidayDateSet = array_flip($holidayDates);
  $workDateSet = array_flip($workDates);
  $today = now()->startOfDay();
  $rows = $entries->count() ? $entries : collect([(object) ['work_time' => '', 'task' => '', 'note' => '']]);
@endphp

@section('content')
  <section class="summary-strip">
    <article class="card stat"><strong>{{ $stats['done'] }}</strong><span>Hari sudah diisi bulan ini</span></article>
    <article class="card stat"><strong>{{ $stats['missing'] }}</strong><span>Hari belum diisi sampai hari ini</span></article>
    <article class="card stat"><strong>{{ $entries->count() }}</strong><span>Kegiatan pada {{ $selectedDate->translatedFormat('d F Y') }}</span></article>
  </section>

  <section class="dashboard-grid">
    <aside class="panel">
      <div class="panel-header">
        <h2>Kalender Kinerja</h2>
        <div class="month-nav">
          <a class="icon-action" href="{{ route('dashboard', ['month' => $prevMonth->month, 'year' => $prevMonth->year, 'date' => $prevMonth->toDateString()]) }}">&lsaquo;</a>
          <form class="month-jump-form" method="get" action="{{ route('dashboard') }}">
            <select name="month" aria-label="Pilih bulan">
              @for ($monthNumber = 1; $monthNumber <= 12; $monthNumber++)
                <option value="{{ $monthNumber }}" @selected($month->month === $monthNumber)>{{ \Carbon\Carbon::create($month->year, $monthNumber, 1)->translatedFormat('F') }}</option>
              @endfor
            </select>
            <select name="year" aria-label="Pilih tahun">
              @for ($year = now()->year - 3; $year <= now()->year + 2; $year++)
                <option value="{{ $year }}" @selected($month->year === $year)>{{ $year }}</option>
              @endfor
            </select>
            <input type="hidden" name="date" value="{{ $month->copy()->startOfMonth()->toDateString() }}">
          </form>
          <a class="icon-action" href="{{ route('dashboard', ['month' => $nextMonth->month, 'year' => $nextMonth->year, 'date' => $nextMonth->toDateString()]) }}">&rsaquo;</a>
        </div>
      </div>

      <div class="calendar-grid">
        @foreach (['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'] as $day)
          <div class="weekday">{{ $day }}</div>
        @endforeach
        @for ($i = 0; $i < $month->copy()->startOfMonth()->dayOfWeek; $i++)
          <div class="day-cell empty"></div>
        @endfor
        @for ($day = 1; $day <= $month->daysInMonth; $day++)
          @php
            $date = $month->copy()->day($day);
            $iso = $date->toDateString();
            $isWorkday = isset($workDateSet[$iso]);
            $status = ! $isWorkday ? 'weekend' : ($date->greaterThan($today) ? 'future' : (isset($entryDateSet[$iso]) ? 'done' : 'missing'));
          @endphp
          @if (! $isWorkday)
            <span class="day-cell {{ $status }}">{{ $day }}</span>
          @else
            <a class="day-cell {{ $status }} {{ $iso === $selectedDate->toDateString() ? 'selected' : '' }}"
               href="{{ route('dashboard', ['date' => $iso, 'month' => $month->month, 'year' => $month->year]) }}">{{ $day }}</a>
          @endif
        @endfor
      </div>

      <div class="legend">
        <span><i class="dot green"></i>Sudah diisi</span>
        <span><i class="dot red"></i>Belum diisi</span>
        <span><i class="dot gray"></i>Libur / belum berjalan</span>
      </div>
    </aside>

    <section class="panel">
      <div class="panel-header">
        <div>
          <h2>Catatan {{ $selectedDate->translatedFormat('d F Y') }}</h2>
          <p class="muted">Isi uraian tugas harian, lalu simpan. Baris kosong tidak akan disimpan.</p>
        </div>
      </div>

      <form method="post" action="{{ route('entries.store') }}" id="entry-form">
        @csrf
        <input type="hidden" name="work_date" value="{{ $selectedDate->toDateString() }}">
        <div class="task-list" id="task-list">
          @foreach ($rows as $row)
            <div class="task-row">
              <label>
                <span>Jam Kerja</span>
                <input name="work_time[]" value="{{ old('work_time.' . $loop->index, $row->work_time) }}" placeholder="08.00">
              </label>
              <label>
                <span>Uraian Tugas</span>
                <textarea name="task[]" placeholder="Tuliskan kegiatan">{{ old('task.' . $loop->index, $row->task) }}</textarea>
              </label>
              <label>
                <span>Keterangan</span>
                <input name="note[]" value="{{ old('note.' . $loop->index, $row->note) }}" placeholder="Selesai">
              </label>
              <button class="icon-action remove-row" type="button" title="Hapus baris">&times;</button>
            </div>
          @endforeach
        </div>
        <div class="actions-row">
          <button class="ghost-action" type="button" id="add-row">Tambah Baris</button>
          <button class="primary-action" type="submit">Simpan Kinerja</button>
          <a class="ghost-action" href="{{ route('reports.show', ['date' => $selectedDate->toDateString()]) }}">Lihat Laporan</a>
        </div>
      </form>
    </section>
  </section>

  <script>
    const list = document.querySelector('#task-list');
    document.querySelectorAll('.month-jump-form select').forEach((select) => {
      select.addEventListener('change', () => {
        const form = select.closest('form');
        const month = form.querySelector('[name="month"]').value;
        const year = form.querySelector('[name="year"]').value;
        form.querySelector('[name="date"]').value = `${year}-${String(month).padStart(2, '0')}-01`;
        form.submit();
      });
    });

    document.querySelector('#add-row').addEventListener('click', () => {
      list.insertAdjacentHTML('beforeend', `
        <div class="task-row">
          <label><span>Jam Kerja</span><input name="work_time[]" placeholder="08.00"></label>
          <label><span>Uraian Tugas</span><textarea name="task[]" placeholder="Tuliskan kegiatan"></textarea></label>
          <label><span>Keterangan</span><input name="note[]" placeholder="Selesai"></label>
          <button class="icon-action remove-row" type="button" title="Hapus baris">&times;</button>
        </div>
      `);
    });
    list.addEventListener('click', (event) => {
      if (!event.target.classList.contains('remove-row')) return;
      if (list.querySelectorAll('.task-row').length === 1) {
        list.querySelectorAll('input, textarea').forEach((field) => field.value = '');
        return;
      }
      event.target.closest('.task-row').remove();
    });
  </script>
@endsection
