@extends('layouts.app')

@section('content')
  <section class="panel narrow">
    <div class="panel-header">
      <div>
        <h2>Approval Cuti</h2>
        <p class="muted">{{ $leaveRequest->user->name }} &middot; Sisa cuti {{ $leaveRequest->user->annual_leave_remaining }} hari kerja.</p>
      </div>
      @include('partials.leave-status', ['status' => $leaveRequest->status])
    </div>

    <div class="detail-grid">
      <div><span>PJLP</span><strong>{{ $leaveRequest->user->name }}</strong></div>
      <div><span>NIP / ID PJLP</span><strong>{{ $leaveRequest->user->nip ?: '-' }}</strong></div>
      <div><span>NIK</span><strong>{{ $leaveRequest->user->nik ?: '-' }}</strong></div>
      <div><span>Jabatan</span><strong>{{ $leaveRequest->user->jabatan ?: 'PJLP' }}</strong></div>
      <div><span>Tanggal Cuti</span><strong>{{ $leaveRequest->start_date->translatedFormat('d F Y') }} - {{ $leaveRequest->end_date->translatedFormat('d F Y') }}</strong></div>
      <div><span>Durasi</span><strong>{{ $leaveRequest->total_days }} {{ ucfirst($leaveRequest->duration_unit ?: 'hari') }}</strong></div>
      <div><span>Diajukan</span><strong>{{ $leaveRequest->created_at->translatedFormat('d F Y H:i') }}</strong></div>
      <div class="full-width"><span>Keperluan</span><p>{{ $leaveRequest->reason }}</p></div>
      @if ($leaveRequest->admin_note)
        <div class="full-width"><span>Catatan Admin</span><p>{{ $leaveRequest->admin_note }}</p></div>
      @endif
      @if ($leaveRequest->approver)
        <div class="full-width"><span>Diproses Oleh</span><strong>{{ $leaveRequest->approver->name }} pada {{ $leaveRequest->approved_at?->translatedFormat('d F Y H:i') }}</strong></div>
      @endif
    </div>

    <form class="form-stack admin-date-editor" method="post" action="{{ route('admin.leave.updateDates', $leaveRequest) }}">
      @csrf
      @method('PUT')
      <div class="panel-subhead">
        <strong>Ubah Tanggal Cuti</strong>
        <span>Admin dapat mengubah tanggal cuti, termasuk tanggal yang sudah lewat.</span>
      </div>
      <div class="profile-grid">
        <label>
          <span>Mulai Tanggal</span>
          <input type="date" name="start_date" value="{{ old('start_date', $leaveRequest->start_date->toDateString()) }}" required>
          @error('start_date') <p class="error-text">{{ $message }}</p> @enderror
        </label>
        <label>
          <span>Sampai Tanggal</span>
          <input type="date" name="end_date" value="{{ old('end_date', $leaveRequest->end_date->toDateString()) }}" required>
          @error('end_date') <p class="error-text">{{ $message }}</p> @enderror
        </label>
      </div>
      <button class="ghost-action" type="submit">Simpan Tanggal</button>
    </form>

    @if ($leaveRequest->isPending())
      <div class="approval-grid">
        <form class="form-stack" method="post" action="{{ route('admin.leave.approve', $leaveRequest) }}" onsubmit="return confirm('Setujui pengajuan cuti ini?')">
          @csrf
          <label>
            <span>Catatan Persetujuan</span>
            <textarea name="admin_note" placeholder="Opsional">{{ old('admin_note') }}</textarea>
          </label>
          <button class="primary-action" type="submit">Setujui dan Kurangi Saldo</button>
        </form>

        <form class="form-stack" method="post" action="{{ route('admin.leave.reject', $leaveRequest) }}" onsubmit="return confirm('Tolak pengajuan cuti ini?')">
          @csrf
          <label>
            <span>Alasan Penolakan</span>
            <textarea name="admin_note" placeholder="Opsional">{{ old('admin_note') }}</textarea>
          </label>
          <button class="danger-action" type="submit">Tolak Pengajuan</button>
        </form>
      </div>
    @endif

    <div class="actions-row">
      @if ($leaveRequest->status === \App\Models\LeaveRequest::STATUS_APPROVED)
        <a class="primary-action" href="{{ route('admin.leave.print', $leaveRequest) }}" target="_blank">Cetak Form</a>
      @endif
      <a class="ghost-action" href="{{ route('admin.leave.index') }}">Kembali</a>
    </div>
  </section>
@endsection
