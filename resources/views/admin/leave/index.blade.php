@extends('layouts.app')

@php
  $activeFilters = array_filter(['status' => $status, 'search' => $search], fn ($value) => filled($value));
@endphp

@section('content')
  <section class="summary-strip">
    <article class="card stat"><strong>{{ $summary['pending'] }}</strong><span>Menunggu persetujuan</span></article>
    <article class="card stat"><strong>{{ $summary['approved'] }}</strong><span>Pengajuan disetujui</span></article>
    <article class="card stat"><strong>{{ $summary['rejected'] }}</strong><span>Pengajuan ditolak</span></article>
    <article class="card stat"><strong>{{ $leaveRequests->total() }}</strong><span>Data sesuai filter</span></article>
  </section>

  <section class="panel">
    <div class="panel-header">
      <div>
        <h2>Panel Cuti Admin</h2>
        <p class="muted">Kelola pengajuan cuti PJLP dan cetak form yang sudah disetujui.</p>
      </div>
      <div class="admin-toolbar">
        <a class="ghost-action" href="{{ route('dashboard') }}">Dashboard</a>
        <a class="primary-action" href="{{ route('admin.leave.exportExcel', $activeFilters) }}">Export Excel</a>
      </div>
    </div>

    <form class="admin-filter-bar" method="get" action="{{ route('admin.leave.index') }}">
      <label class="filter-search">
        <span>Cari Data</span>
        <input name="search" value="{{ $search }}" placeholder="Nama, username, NIP, NIK, jabatan" autocomplete="off">
      </label>
      <div class="filter-tabs">
        <a class="filter-chip {{ $status ? '' : 'active' }}" href="{{ route('admin.leave.index', array_filter(['search' => $search], fn ($value) => filled($value))) }}">Semua</a>
        <a class="filter-chip {{ $status === 'pending' ? 'active' : '' }}" href="{{ route('admin.leave.index', array_filter(['status' => 'pending', 'search' => $search], fn ($value) => filled($value))) }}">Menunggu</a>
        <a class="filter-chip {{ $status === 'approved' ? 'active' : '' }}" href="{{ route('admin.leave.index', array_filter(['status' => 'approved', 'search' => $search], fn ($value) => filled($value))) }}">Disetujui</a>
        <a class="filter-chip {{ $status === 'rejected' ? 'active' : '' }}" href="{{ route('admin.leave.index', array_filter(['status' => 'rejected', 'search' => $search], fn ($value) => filled($value))) }}">Ditolak</a>
      </div>
      <button class="primary-action" type="submit">Cari</button>
      @if ($status || $search)
        <a class="ghost-action" href="{{ route('admin.leave.index') }}">Reset</a>
      @endif
    </form>

    @include('partials.leave-table', ['leaveRequests' => $leaveRequests, 'adminView' => true])
    <div class="pagination">{{ $leaveRequests->links() }}</div>
  </section>
@endsection
