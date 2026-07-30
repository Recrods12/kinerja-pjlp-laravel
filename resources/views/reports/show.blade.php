@extends('layouts.app')

@section('content')
  @php
    $previousUrl = auth()->user()->role === 'admin'
      ? route('admin.reports.show', ['user' => $target, 'date' => $previousPairDate->toDateString()])
      : route('reports.show', ['date' => $previousPairDate->toDateString()]);
    $nextUrl = auth()->user()->role === 'admin'
      ? route('admin.reports.show', ['user' => $target, 'date' => $nextPairDate->toDateString()])
      : route('reports.show', ['date' => $nextPairDate->toDateString()]);
    $allUrl = auth()->user()->role === 'admin'
      ? route('admin.reports.show', ['user' => $target, 'date' => $selectedDate->toDateString(), 'all' => 1])
      : route('reports.show', ['date' => $selectedDate->toDateString(), 'all' => 1]);
    $singleUrl = auth()->user()->role === 'admin'
      ? route('admin.reports.show', ['user' => $target, 'date' => $selectedDate->toDateString()])
      : route('reports.show', ['date' => $selectedDate->toDateString()]);
  @endphp

  <div class="report-actions">
    @unless ($showAll)
      <a class="ghost-action report-nav previous" data-slide="previous" href="{{ $previousUrl }}"><span>&lsaquo;</span> Sebelumnya</a>
      <a class="ghost-action report-nav next" data-slide="next" href="{{ $nextUrl }}">Selanjutnya <span>&rsaquo;</span></a>
      <a class="ghost-action" href="{{ $allUrl }}">Semua Halaman</a>
    @else
      <a class="ghost-action" href="{{ $singleUrl }}">Satu Halaman</a>
    @endunless
    <a class="ghost-action" href="{{ route('dashboard', ['month' => $selectedDate->month, 'year' => $selectedDate->year, 'date' => $selectedDate->toDateString()]) }}">Kembali</a>
    <button class="primary-action" type="button" onclick="window.print()">Cetak / Simpan PDF</button>
    @if ($showAll)
      <a class="primary-action" href="{{ auth()->user()->role === 'admin' ? route('admin.reports.downloadZip', ['month' => $selectedDate->month, 'year' => $selectedDate->year]) : route('reports.downloadPdf', ['month' => $selectedDate->month, 'year' => $selectedDate->year]) }}">Download Kinerja</a>
    @endif
  </div>

  <section id="print-area" class="report-preview {{ $showAll ? 'all-pages' : '' }}">
    @foreach ($reportPages as $page)
      @include('reports.sheet', [
        'target' => $target,
        'leftDate' => $page['leftDate'],
        'rightDate' => $page['rightDate'],
        'leftEntries' => $page['leftEntries'],
        'rightEntries' => $page['rightEntries'],
        'isLastPage' => $loop->last,
      ])
    @endforeach
  </section>

  <script>
    const reportPreview = document.querySelector('#print-area');
    const storedDirection = sessionStorage.getItem('report-slide-direction');

    if (storedDirection) {
      reportPreview?.classList.add(`slide-in-${storedDirection}`);
      sessionStorage.removeItem('report-slide-direction');
    }

    document.querySelectorAll('[data-slide]').forEach((link) => {
      link.addEventListener('click', (event) => {
        event.preventDefault();
        const direction = link.dataset.slide;
        const leaveClass = direction === 'next' ? 'slide-out-left' : 'slide-out-right';
        const enterDirection = direction === 'next' ? 'right' : 'left';

        sessionStorage.setItem('report-slide-direction', enterDirection);
        reportPreview?.classList.add(leaveClass);

        window.setTimeout(() => {
          window.location.href = link.href;
        }, 170);
      });
    });
  </script>
@endsection
