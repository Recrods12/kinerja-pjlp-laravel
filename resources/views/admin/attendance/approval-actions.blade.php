@if ($record->approval_status === 'pending')
  <div class="attendance-review-actions">
    <form method="post" action="{{ route('admin.attendance.approve', $record) }}">
      @csrf
      <input type="hidden" name="submission_version" value="{{ $record->submission_version }}">
      <button class="primary-action" type="submit" aria-label="Setujui {{ $record->label() }} {{ $record->user->name }}">Setujui</button>
    </form>
    <button class="attendance-reject-trigger" type="button" data-attendance-reject
      data-version="{{ $record->submission_version }}"
      data-action="{{ route('admin.attendance.reject', $record) }}"
      data-description="{{ $record->user->name }} &middot; {{ $record->label() }} &middot; {{ $record->recorded_at->format('d/m/Y H:i') }} WIB"
      aria-label="Tolak {{ $record->label() }} {{ $record->user->name }}">Tolak</button>
  </div>
  @once
    @push('scripts')
      <dialog class="attendance-reject-dialog" id="attendance-reject-dialog" aria-labelledby="attendance-reject-title" aria-describedby="attendance-reject-description">
        <form method="post" class="attendance-reject-form">
          @csrf
          <input type="hidden" name="submission_version" value="">
          <div class="attendance-dialog-heading"><h2 id="attendance-reject-title">Tolak pengajuan absensi</h2><button type="button" data-reject-cancel aria-label="Tutup dialog">&times;</button></div>
          <p id="attendance-reject-description" class="muted"></p>
          <label for="attendance-reject-reason">Alasan penolakan <span>(opsional)</span></label>
          <textarea id="attendance-reject-reason" name="rejection_reason" maxlength="1000" rows="4" placeholder="Contoh: Foto kurang jelas. Silakan unggah ulang." aria-describedby="attendance-reject-hint"></textarea>
          <p class="muted" id="attendance-reject-hint">Boleh dikosongkan. Jika diisi, alasan akan dikirim ke pegawai.</p>
          <div class="attendance-dialog-footer"><button class="ghost-action" type="button" data-reject-cancel>Batal</button><button class="danger-action" type="submit">Tolak pengajuan</button></div>
        </form>
      </dialog>
      <script>
        (() => {
          const dialog = document.getElementById('attendance-reject-dialog');
          const form = dialog.querySelector('form');
          const reason = dialog.querySelector('textarea');
          let trigger;
          document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-attendance-reject]');
            if (!button) return;
            trigger = button;
            form.reset();
            reason.setCustomValidity('');
            form.action = button.dataset.action;
            form.elements.submission_version.value = button.dataset.version;
            document.getElementById('attendance-reject-description').textContent = button.dataset.description;
            dialog.showModal();
            reason.focus();
          });
          dialog.querySelectorAll('[data-reject-cancel]').forEach(button => button.addEventListener('click', () => dialog.close()));
          dialog.addEventListener('close', () => trigger?.focus());
        })();
      </script>
    @endpush
  @endonce
@endif
