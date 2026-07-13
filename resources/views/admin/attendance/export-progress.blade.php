@extends('layouts.app')

@section('content')
<section class="page-heading">
  <div>
    <p class="eyebrow">Export Absensi Bulanan</p>
    <h1>Memproses Export...</h1>
  </div>
</section>

<section class="export-progress-section">
  <article class="card" style="padding: 2rem; max-width: 600px; margin: 2rem auto; text-align: center;">
    <div id="export-status">
      <div class="progress-ring" style="margin: 0 auto 1.5rem; width: 80px; height: 80px; position: relative;">
        <svg width="80" height="80" viewBox="0 0 80 80">
          <circle cx="40" cy="40" r="36" fill="none" stroke="#e9ecef" stroke-width="6"/>
          <circle id="progress-circle" cx="40" cy="40" r="36" fill="none" stroke="#3b82f6" stroke-width="6"
            stroke-linecap="round" stroke-dasharray="226.2" stroke-dashoffset="226.2" transform="rotate(-90 40 40)"/>
        </svg>
        <span id="progress-text" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 1.3rem; font-weight: bold;">0%</span>
      </div>

      <p id="status-message" style="font-size: 1.1rem; color: #555;">Menyiapkan data...</p>
      <p id="status-sub" style="color: #888; font-size: 0.9rem;">&nbsp;</p>
    </div>

    <div id="export-done" style="display: none;">
      <div style="font-size: 3rem; color: #16a34a; margin-bottom: 1rem;">✅</div>
      <h2 style="margin: 0 0 0.5rem;">Export Selesai!</h2>
      <p id="done-message" style="color: #555; margin-bottom: 1.5rem;">ZIP siap diunduh.</p>
      <a id="download-link" class="primary-action" href="#" style="display: inline-block; padding: 0.75rem 2rem;">Download ZIP</a>
      <br><br>
      <a href="{{ route('admin.attendance.index') }}" class="ghost-action">Kembali ke Dashboard</a>
    </div>

    <div id="export-error" style="display: none;">
      <div style="font-size: 3rem; color: #dc2626; margin-bottom: 1rem;">❌</div>
      <h2 style="margin: 0 0 0.5rem;">Export Gagal</h2>
      <p id="error-message" style="color: #555; margin-bottom: 1.5rem;"></p>
      <a href="{{ route('admin.attendance.index') }}" class="primary-action">Kembali ke Dashboard</a>
    </div>
  </article>
</section>
@endsection

@push('scripts')
<script>
(function() {
  const reportJobId = {{ $reportJob->id }};
  const stepUrl = '{{ route('admin.report-jobs.step', $reportJob) }}';
  const csrfToken = '{{ csrf_token() }}';
  let polling = true;

  function updateProgress(percent, message, sub) {
    const circle = document.getElementById('progress-circle');
    const circumference = 226.2;
    const offset = circumference - (circumference * percent / 100);
    circle.style.strokeDashoffset = offset;
    document.getElementById('progress-text').textContent = percent + '%';
    if (message) document.getElementById('status-message').textContent = message;
    if (sub) document.getElementById('status-sub').textContent = sub;
  }

  function showDone(downloadUrl) {
    polling = false;
    document.getElementById('export-status').style.display = 'none';
    document.getElementById('export-done').style.display = 'block';
    document.getElementById('download-link').href = downloadUrl;
  }

  function showError(message) {
    polling = false;
    document.getElementById('export-status').style.display = 'none';
    document.getElementById('export-error').style.display = 'block';
    document.getElementById('error-message').textContent = message || 'Terjadi kesalahan saat memproses export.';
  }

  function processStep() {
    if (!polling) return;

    fetch(stepUrl, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'completed') {
        updateProgress(100, 'Selesai!', '');
        const downloadUrl = '{{ route('admin.report-jobs.download', $reportJob) }}';
        showDone(downloadUrl);
        return;
      }

      if (data.status === 'failed') {
        showError(data.message || 'Gagal memproses export.');
        return;
      }

      // Masih pending/processing
      const progress = data.progress || 0;
      const msg = data.current_user ? 'Memproses: ' + data.current_user : 'Memproses...';
      const sub = data.processed_users + ' dari ' + data.total_users + ' user selesai';
      updateProgress(progress, msg, sub);

      // Lanjut polling setelah jeda
      setTimeout(processStep, 500);
    })
    .catch(err => {
      console.error('Polling error:', err);
      setTimeout(processStep, 1000);
    });
  }

  // Mulai
  setTimeout(processStep, 300);
})();
</script>
@endpush
