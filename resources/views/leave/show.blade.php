@extends('layouts.app')

@section('content')
  <section class="panel narrow">
    <div class="panel-header">
      <div>
        <h2>Detail Cuti</h2>
        <p class="muted">Pengajuan dibuat pada {{ $leaveRequest->created_at->translatedFormat('d F Y H:i') }}.</p>
      </div>
      @include('partials.leave-status', ['status' => $leaveRequest->status])
    </div>

    <div class="detail-grid">
      <div><span>Tanggal Cuti</span><strong>{{ $leaveRequest->start_date->translatedFormat('d F Y') }} - {{ $leaveRequest->end_date->translatedFormat('d F Y') }}</strong></div>
      <div><span>Durasi</span><strong>{{ $leaveRequest->total_days }} {{ ucfirst($leaveRequest->duration_unit ?: 'hari') }}</strong></div>
      <div class="full-width"><span>Keperluan</span><p>{{ $leaveRequest->reason }}</p></div>
      @if ($leaveRequest->admin_note)
        <div class="full-width"><span>Catatan Admin</span><p>{{ $leaveRequest->admin_note }}</p></div>
      @endif
      @if ($leaveRequest->approver)
        <div class="full-width"><span>Diproses Oleh</span><strong>{{ $leaveRequest->approver->name }} pada {{ $leaveRequest->approved_at?->translatedFormat('d F Y H:i') }}</strong></div>
      @endif
    </div>

    <div class="actions-row">
      <a class="ghost-action" href="{{ route('leave.index') }}">Kembali</a>
    </div>
  </section>
@endsection
