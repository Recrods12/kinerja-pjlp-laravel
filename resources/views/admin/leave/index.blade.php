@extends('layouts.app')

@php
  $activeFilters = array_filter(['status' => $status, 'search' => $search], fn ($value) => filled($value));
@endphp

@section('content')
  <div class="page-heading">
    <div>
      <p class="eyebrow">Administrasi Cuti</p>
      <h1>Panel Cuti Admin</h1>
      <p class="muted">Kelola pengajuan cuti PJLP, proses persetujuan, dan cetak form yang sudah disetujui.</p>
    </div>
    <div class="page-actions">
      <a class="ghost-action" href="{{ route('dashboard') }}">Dashboard</a>
      <a class="primary-action" href="{{ route('admin.leave.exportExcel', $activeFilters) }}">Export Excel</a>
    </div>
  </div>

  <section class="summary-strip">
    <article class="card modern-stat gold"><i>PN</i><strong>{{ $summary['pending'] }}</strong><span>Menunggu</span><small>Perlu keputusan admin</small></article>
    <article class="card modern-stat green"><i>OK</i><strong>{{ $summary['approved'] }}</strong><span>Disetujui</span><small>Siap dicetak</small></article>
    <article class="card modern-stat red"><i>RJ</i><strong>{{ $summary['rejected'] }}</strong><span>Ditolak</span><small>Sudah diproses</small></article>
    <article class="card modern-stat blue"><i>FT</i><strong>{{ $leaveRequests->total() }}</strong><span>Data Filter</span><small>Sesuai pencarian</small></article>
  </section>

  <section class="panel management-panel">
    <div class="panel-header compact">
      <div>
        <h2>Daftar Pengajuan</h2>
        <p class="muted">Gunakan filter status dan kolom cari untuk menemukan pengajuan tertentu.</p>
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
