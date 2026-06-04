@php
  $labels = [
    'pending' => 'Menunggu',
    'approved' => 'Disetujui',
    'rejected' => 'Ditolak',
  ];

  $classes = [
    'pending' => 'pending',
    'approved' => 'done',
    'rejected' => 'missing',
  ];
@endphp

<span class="status-pill {{ $classes[$status] ?? 'pending' }}">{{ $labels[$status] ?? $status }}</span>
