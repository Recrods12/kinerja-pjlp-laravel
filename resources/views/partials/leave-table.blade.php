@php($adminView = $adminView ?? false)

@if ($leaveRequests->isEmpty())
  <p class="muted">Belum ada data pengajuan cuti.</p>
@else
  <div class="table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          @if ($adminView)
            <th>PJLP</th>
          @endif
          <th>Tanggal Cuti</th>
          <th>Durasi</th>
          <th>Status</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($leaveRequests as $leaveRequest)
          <tr>
            @if ($adminView)
              <td>
                <strong>{{ $leaveRequest->user->name }}</strong><br>
                <span class="muted">{{ $leaveRequest->user->jabatan ?: 'PJLP' }} &middot; {{ $leaveRequest->user->nip ?: '-' }}</span>
              </td>
            @endif
            <td>
              {{ $leaveRequest->start_date->translatedFormat('d F Y') }} - {{ $leaveRequest->end_date->translatedFormat('d F Y') }}<br>
              <span class="muted">{{ Str::limit($leaveRequest->reason, 82) }}</span>
            </td>
            <td>{{ $leaveRequest->total_days }} {{ ucfirst($leaveRequest->duration_unit ?: 'hari') }}</td>
            <td>@include('partials.leave-status', ['status' => $leaveRequest->status])</td>
            <td>
              @if ($adminView)
                <div class="row-actions">
                  <a class="ghost-action" href="{{ route('admin.leave.show', $leaveRequest) }}">Detail</a>
                  @if ($leaveRequest->isPending())
                    <button class="primary-action" type="button" data-approval-toggle="approve-{{ $leaveRequest->id }}">Setujui</button>
                    <button class="danger-action" type="button" data-approval-toggle="reject-{{ $leaveRequest->id }}">Tolak</button>
                  @elseif ($leaveRequest->status === \App\Models\LeaveRequest::STATUS_APPROVED)
                    <a class="ghost-action" href="{{ route('admin.leave.print', $leaveRequest) }}" target="_blank">Cetak</a>
                  @endif
                </div>
                @if ($leaveRequest->isPending())
                  <div class="quick-approval-panel" id="approve-{{ $leaveRequest->id }}" hidden>
                    <form method="post" action="{{ route('admin.leave.approve', $leaveRequest) }}">
                      @csrf
                      <label>
                        <span>Catatan Persetujuan</span>
                        <textarea name="admin_note" placeholder="Opsional"></textarea>
                      </label>
                      <div class="row-actions">
                        <button class="primary-action" type="submit">Setujui dan Kurangi Saldo</button>
                        <button class="ghost-action" type="button" data-approval-close>Tutup</button>
                      </div>
                    </form>
                  </div>
                  <div class="quick-approval-panel danger" id="reject-{{ $leaveRequest->id }}" hidden>
                    <form method="post" action="{{ route('admin.leave.reject', $leaveRequest) }}">
                      @csrf
                      <label>
                        <span>Alasan Penolakan</span>
                        <textarea name="admin_note" placeholder="Opsional"></textarea>
                      </label>
                      <div class="row-actions">
                        <button class="danger-action" type="submit">Tolak Pengajuan</button>
                        <button class="ghost-action" type="button" data-approval-close>Tutup</button>
                      </div>
                    </form>
                  </div>
                @endif
              @else
                <a class="ghost-action" href="{{ route('leave.show', $leaveRequest) }}">Detail</a>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @if ($adminView)
    <script>
      document.querySelectorAll('[data-approval-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
          const target = document.getElementById(button.dataset.approvalToggle);
          if (!target) return;

          button.closest('td').querySelectorAll('.quick-approval-panel').forEach((panel) => {
            panel.hidden = panel !== target || !panel.hidden;
          });

          if (!target.hidden) {
            target.querySelector('textarea')?.focus();
          }
        });
      });

      document.querySelectorAll('[data-approval-close]').forEach((button) => {
        button.addEventListener('click', () => {
          button.closest('.quick-approval-panel').hidden = true;
        });
      });
    </script>
  @endif
@endif
