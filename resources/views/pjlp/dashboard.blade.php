@extends('layouts.app')

@php
  $prevMonth = $month->copy()->subMonth();
  $nextMonth = $month->copy()->addMonth();
  $entryDateSet = array_flip($entryDates);
  $holidayDateSet = array_flip($holidayDates);
  $workDateSet = array_flip($workDates);
  $today = now()->startOfDay();
  $rows = $entries->count() ? $entries : collect([(object) ['work_time' => '', 'task' => '', 'note' => '']]);
  $monthNames = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
  $dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
  $shortDayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
  $monthLabel = $monthNames[$month->month] . ' ' . $month->year;
  $selectedDateLabel = $selectedDate->format('d') . ' ' . $monthNames[$selectedDate->month] . ' ' . $selectedDate->year;
  $formatDate = fn ($date) => \Carbon\Carbon::parse($date)->format('d') . ' ' . $monthNames[\Carbon\Carbon::parse($date)->month] . ' ' . \Carbon\Carbon::parse($date)->year;
  $timeAgo = function ($dateTime) {
    if (! $dateTime) return '-';
    return \Carbon\Carbon::parse($dateTime)->diffForHumans(['parts' => 1]);
  };
@endphp

@section('content')
  <section class="page-heading">
    <div>
      <p class="eyebrow">Dashboard PJLP</p>
      <h1>Selamat datang, {{ $user->name }}</h1>
      <p class="muted">Pantau kalender, catat kinerja harian, dan cek status cuti dari satu panel.</p>
    </div>
    <div class="page-actions">
      <a class="primary-action" href="{{ route('leave.create') }}">Ajukan Cuti</a>
      <a class="ghost-action" href="{{ route('leave.index') }}">Riwayat Cuti</a>
    </div>
  </section>

  <section class="summary-strip">
    <article class="card stat modern-stat green"><i>OK</i><strong>{{ $stats['done'] }}</strong><span>Hari sudah diisi bulan ini</span><small>{{ $monthLabel }}</small></article>
    <article class="card stat modern-stat gold"><i>BL</i><strong>{{ $stats['missing'] }}</strong><span>Hari belum diisi sampai hari ini</span><small>Perlu dilengkapi</small></article>
    <article class="card stat modern-stat blue"><i>KG</i><strong>{{ $entries->count() }}</strong><span>Kegiatan pada {{ $selectedDateLabel }}</span><small>{{ $dayNames[$selectedDate->dayOfWeek] }}</small></article>
    <article class="card stat modern-stat red"><i>CT</i><strong>{{ $leaveSummary['pending'] ?? 0 }}</strong><span>Pengajuan cuti menunggu</span><small>Perlu dipantau</small></article>
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
                <option value="{{ $monthNumber }}" @selected($month->month === $monthNumber)>{{ $monthNames[$monthNumber] }}</option>
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
        @foreach ($shortDayNames as $day)
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
          <h2>Catatan {{ $selectedDateLabel }}</h2>
          @if ($canEdit)
            <p class="muted">Isi uraian tugas harian, lalu simpan. Baris kosong tidak akan disimpan.</p>
          @else
            <p class="muted">Hanya dapat melihat — pengisian kinerja hanya untuk bulan berjalan.</p>
          @endif
        </div>
      </div>

      @if ($canEdit)
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
              <button class="danger-action remove-row" type="button">Hapus</button>
            </div>
          @endforeach
        </div>
        <div class="actions-row">
          <button class="ghost-action" type="button" id="add-row">Tambah Baris</button>
          <button class="primary-action" type="submit">Simpan Kinerja</button>
          <a class="ghost-action" href="{{ route('reports.show', ['date' => $selectedDate->toDateString()]) }}">Lihat Laporan</a>
        </div>
      </form>
      @else
        <div class="task-list">
          @forelse ($rows as $row)
            <div class="task-row" style="opacity:0.7; pointer-events:none;">
              <label><span>Jam Kerja</span><input value="{{ $row->work_time }}" disabled></label>
              <label><span>Uraian Tugas</span><textarea disabled>{{ $row->task }}</textarea></label>
              <label><span>Keterangan</span><input value="{{ $row->note }}" disabled></label>
            </div>
          @empty
            <p class="muted" style="padding:16px 0;">Tidak ada catatan kinerja untuk tanggal ini.</p>
          @endforelse
        </div>
        <div class="actions-row">
          <a class="ghost-action" href="{{ route('reports.show', ['date' => $selectedDate->toDateString()]) }}">Lihat Laporan</a>
        </div>
      @endif
    </section>
  </section>

  <section class="dashboard-board user-board">
    <article class="panel insight-panel">
      <div class="panel-header compact">
        <div>
          <h2>Status Pengajuan Cuti</h2>
          <p class="muted">Riwayat cuti terbaru Anda.</p>
        </div>
        <a class="ghost-action" href="{{ route('leave.index') }}">Lihat Semua</a>
      </div>
      <div class="mini-list">
        @forelse ($recentLeaveRequests as $leave)
          @php
            $leaveDays = $leave->total_days . ' ' . ($leave->duration_unit ?? 'hari');
          @endphp
          <a class="mini-item" href="{{ route('leave.show', $leave) }}">
            <span class="avatar small {{ $leave->status === 'approved' ? 'done' : ($leave->status === 'rejected' ? 'missing' : 'pending') }}">{{ \Illuminate\Support\Str::substr($leave->reason, 0, 1) }}</span>
            <span>
              <strong>{{ $leave->reason }}</strong>
              <small>{{ $formatDate($leave->start_date) }} - {{ $formatDate($leave->end_date) }}</small>
              <span class="mini-preview">{{ $leaveDays }}</span>
            </span>
            <span class="mini-meta">
              <em class="mini-time">{{ $timeAgo($leave->created_at) }}</em>
              <em class="status-pill {{ $leave->status === 'approved' ? 'done' : ($leave->status === 'rejected' ? 'missing' : 'pending') }}">
                {{ $leave->status === 'approved' ? 'Disetujui' : ($leave->status === 'rejected' ? 'Ditolak' : 'Menunggu') }}
              </em>
            </span>
          </a>
        @empty
          <p class="muted">Belum ada pengajuan cuti.</p>
        @endforelse
      </div>
    </article>

    <article class="panel insight-panel">
      <div class="panel-header compact">
        <div>
          <h2>Quick Action</h2>
          <p class="muted">Akses cepat untuk pekerjaan harian.</p>
        </div>
      </div>
      <div class="quick-grid">
        <a class="quick-card green" href="{{ route('attendance.index') }}">
          <span class="quick-icon">📋</span>
          <span class="quick-text">
            <strong>Absen Mobile</strong>
            <span>Absen awal, akhir, atau dinas luar</span>
          </span>
        </a>
        <a class="quick-card green" href="{{ route('reports.show', ['date' => $selectedDate->toDateString()]) }}">
          <span class="quick-icon">📄</span>
          <span class="quick-text">
            <strong>Lihat Laporan</strong>
            <span>Preview kinerja tanggal ini</span>
          </span>
        </a>
        <a class="quick-card gold" href="{{ route('leave.create') }}">
          <span class="quick-icon">✈️</span>
          <span class="quick-text">
            <strong>Ajukan Cuti</strong>
            <span>Buat pengajuan cuti baru</span>
          </span>
        </a>
        <a class="quick-card blue" href="{{ route('leave.index') }}">
          <span class="quick-icon">📋</span>
          <span class="quick-text">
            <strong>Riwayat Cuti</strong>
            <span>{{ $leaveSummary['approved'] ?? 0 }} disetujui, {{ $leaveSummary['rejected'] ?? 0 }} ditolak</span>
          </span>
        </a>
        <a class="quick-card red" href="#entry-form">
          <span class="quick-icon">✏️</span>
          <span class="quick-text">
            <strong>Isi Kinerja</strong>
            <span>Simpan catatan harian</span>
          </span>
        </a>
      </div>
    </article>
  </section>

  <div class="confirm-overlay" id="delete-row-confirm" hidden>
    <div class="confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="delete-row-title">
      <div>
        <p class="confirm-kicker">Konfirmasi Hapus</p>
        <h3 id="delete-row-title">Yakin ingin menghapus kolom ini?</h3>
        <p>Data pada baris ini akan dihapus dari catatan sebelum Anda menyimpan kinerja.</p>
      </div>
      <div class="confirm-actions">
        <button class="ghost-action" type="button" data-confirm-cancel>Batal</button>
        <button class="danger-action" type="button" data-confirm-delete>Hapus</button>
      </div>
    </div>
  </div>

  <div class="confirm-overlay" id="leave-page-confirm" hidden>
    <div class="confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="leave-page-title">
      <div>
        <p class="confirm-kicker">Kinerja Belum Disimpan</p>
        <h3 id="leave-page-title">Simpan kinerja sebelum meninggalkan halaman?</h3>
        <p>Perubahan pada catatan tanggal ini belum tersimpan. Simpan sekarang agar data kinerja tidak hilang.</p>
      </div>
      <div class="confirm-actions">
        <button class="ghost-action" type="button" data-leave-cancel>Tetap di Halaman</button>
        <button class="danger-action" type="button" data-leave-discard>Lanjut tanpa Simpan</button>
        <button class="primary-action" type="button" data-leave-save>Simpan Kinerja</button>
      </div>
    </div>
  </div>

  <script>
    const entryForm = document.querySelector('#entry-form');
    const list = document.querySelector('#task-list');
    const confirmOverlay = document.querySelector('#delete-row-confirm');
    const confirmCancel = confirmOverlay.querySelector('[data-confirm-cancel]');
    const confirmDelete = confirmOverlay.querySelector('[data-confirm-delete]');
    const leaveOverlay = document.querySelector('#leave-page-confirm');
    const leaveCancel = leaveOverlay.querySelector('[data-leave-cancel]');
    const leaveDiscard = leaveOverlay.querySelector('[data-leave-discard]');
    const leaveSave = leaveOverlay.querySelector('[data-leave-save]');
    let pendingDeleteRow = null;
    let pendingNavigationUrl = null;
    let pendingNavigationForm = null;
    let isSubmittingEntry = false;

    const formSnapshot = () => JSON.stringify(Array.from(new FormData(entryForm).entries()));
    let initialEntrySnapshot = formSnapshot();

    const hasUnsavedEntryChanges = () => formSnapshot() !== initialEntrySnapshot;

    const openLeaveConfirm = (url, form = null) => {
      pendingNavigationUrl = url;
      pendingNavigationForm = form;
      leaveOverlay.hidden = false;
      leaveSave.focus();
    };

    const closeLeaveConfirm = () => {
      pendingNavigationUrl = null;
      pendingNavigationForm = null;
      leaveOverlay.hidden = true;
    };

    const openDeleteConfirm = (row) => {
      pendingDeleteRow = row;
      confirmOverlay.hidden = false;
      confirmDelete.focus();
    };

    const closeDeleteConfirm = () => {
      pendingDeleteRow = null;
      confirmOverlay.hidden = true;
    };

    const deletePendingRow = () => {
      if (!pendingDeleteRow) return;

      if (list.querySelectorAll('.task-row').length === 1) {
        pendingDeleteRow.querySelectorAll('input, textarea').forEach((field) => field.value = '');
      } else {
        pendingDeleteRow.remove();
      }

      closeDeleteConfirm();
    };

    entryForm.addEventListener('submit', () => {
      isSubmittingEntry = true;
    });

    window.addEventListener('beforeunload', (event) => {
      if (!isSubmittingEntry && hasUnsavedEntryChanges()) {
        event.preventDefault();
        event.returnValue = '';
      }
    });

    document.addEventListener('click', (event) => {
      const link = event.target.closest('a[href]');
      if (!link || event.defaultPrevented || event.button !== 0) return;
      if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
      if (link.target === '_blank' || link.hasAttribute('download')) return;

      const href = link.getAttribute('href');
      if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) return;

      const nextUrl = new URL(href, window.location.href);
      if (nextUrl.href === window.location.href || !hasUnsavedEntryChanges()) return;

      event.preventDefault();
      openLeaveConfirm(nextUrl.href);
    });

    document.addEventListener('submit', (event) => {
      const form = event.target;
      if (form === entryForm || isSubmittingEntry || !hasUnsavedEntryChanges()) return;

      event.preventDefault();
      openLeaveConfirm(null, form);
    });

    document.querySelectorAll('.month-jump-form select').forEach((select) => {
      select.addEventListener('change', () => {
        const form = select.closest('form');
        const month = form.querySelector('[name="month"]').value;
        const year = form.querySelector('[name="year"]').value;
        form.querySelector('[name="date"]').value = `${year}-${String(month).padStart(2, '0')}-01`;

        if (!hasUnsavedEntryChanges()) {
          form.submit();
          return;
        }

        const nextUrl = new URL(form.action, window.location.href);
        new FormData(form).forEach((value, key) => nextUrl.searchParams.set(key, value));
        openLeaveConfirm(nextUrl.href);
      });
    });

    document.querySelector('#add-row').addEventListener('click', () => {
      list.insertAdjacentHTML('beforeend', `
        <div class="task-row">
          <label><span>Jam Kerja</span><input name="work_time[]" placeholder="08.00"></label>
          <label><span>Uraian Tugas</span><textarea name="task[]" placeholder="Tuliskan kegiatan"></textarea></label>
          <label><span>Keterangan</span><input name="note[]" placeholder="Selesai"></label>
          <button class="danger-action remove-row" type="button">Hapus</button>
        </div>
      `);
    });
    list.addEventListener('click', (event) => {
      const removeButton = event.target.closest('.remove-row');
      if (!removeButton) return;
      openDeleteConfirm(removeButton.closest('.task-row'));
    });

    confirmCancel.addEventListener('click', closeDeleteConfirm);
    confirmDelete.addEventListener('click', deletePendingRow);
    confirmOverlay.addEventListener('click', (event) => {
      if (event.target === confirmOverlay) closeDeleteConfirm();
    });
    leaveCancel.addEventListener('click', closeLeaveConfirm);
    leaveDiscard.addEventListener('click', () => {
      isSubmittingEntry = true;

      if (pendingNavigationForm) {
        pendingNavigationForm.submit();
        return;
      }

      if (pendingNavigationUrl) {
        window.location.href = pendingNavigationUrl;
      }
    });
    leaveSave.addEventListener('click', () => {
      isSubmittingEntry = true;
      entryForm.requestSubmit();
    });
    leaveOverlay.addEventListener('click', (event) => {
      if (event.target === leaveOverlay) closeLeaveConfirm();
    });
    document.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape') return;
      if (!confirmOverlay.hidden) closeDeleteConfirm();
      if (!leaveOverlay.hidden) closeLeaveConfirm();
    });
  </script>
@endsection
