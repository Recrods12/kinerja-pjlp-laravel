@extends('layouts.app')

@section('content')
  <div class="page-heading">
    <div>
      <p class="eyebrow">Cuti PJLP</p>
      <h1>Cuti Saya</h1>
      <p class="muted">Pantau pengajuan cuti, status persetujuan, dan sisa cuti yang tersedia.</p>
    </div>
    <div class="page-actions">
      <a class="ghost-action" href="{{ route('dashboard') }}">Dashboard</a>
      <a class="primary-action" href="{{ route('leave.create') }}">Ajukan Cuti</a>
    </div>
  </div>

  <section class="summary-strip">
    <article class="card modern-stat blue"><i>QT</i><strong>{{ auth()->user()->annual_leave_quota }}</strong><span>Kuota Tahunan</span><small>Total hak cuti</small></article>
    <article class="card modern-stat green"><i>SC</i><strong>{{ auth()->user()->annual_leave_remaining }}</strong><span>Sisa Cuti</span><small>Masih tersedia</small></article>
    <article class="card modern-stat gold"><i>PN</i><strong>{{ $summary['pending'] }}</strong><span>Menunggu</span><small>Belum diproses admin</small></article>
    <article class="card modern-stat green"><i>OK</i><strong>{{ $summary['approved'] }}</strong><span>Disetujui</span><small>Sudah diproses</small></article>
  </section>

  <section class="panel management-panel">
    <div class="panel-header compact">
      <div>
        <h2>Riwayat Pengajuan</h2>
        <p class="muted">Semua pengajuan cuti Anda ditampilkan dari yang terbaru.</p>
      </div>
    </div>

    @include('partials.leave-table', ['leaveRequests' => $leaveRequests])
    <div class="pagination">{{ $leaveRequests->links() }}</div>
  </section>
@endsection
