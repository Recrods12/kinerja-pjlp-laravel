@extends('layouts.app')

@section('content')
  <section class="summary-strip">
    <article class="card stat"><strong>{{ auth()->user()->annual_leave_quota }}</strong><span>Kuota cuti tahunan</span></article>
    <article class="card stat"><strong>{{ auth()->user()->annual_leave_remaining }}</strong><span>Sisa cuti tersedia</span></article>
    <article class="card stat"><strong>{{ $summary['pending'] }}</strong><span>Menunggu persetujuan</span></article>
    <article class="card stat"><strong>{{ $summary['approved'] }}</strong><span>Pengajuan disetujui</span></article>
  </section>

  <section class="panel">
    <div class="panel-header">
      <div>
        <h2>Cuti Saya</h2>
        <p class="muted">Pantau pengajuan cuti dan sisa cuti Anda.</p>
      </div>
      <div class="row-actions">
        <a class="ghost-action" href="{{ route('dashboard') }}">Dashboard</a>
        <a class="primary-action" href="{{ route('leave.create') }}">Ajukan Cuti</a>
      </div>
    </div>

    @include('partials.leave-table', ['leaveRequests' => $leaveRequests])
    <div class="pagination">{{ $leaveRequests->links() }}</div>
  </section>
@endsection
