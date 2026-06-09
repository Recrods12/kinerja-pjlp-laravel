@extends('layouts.app')

@section('content')
  <div class="page-heading">
    <div>
      <p class="eyebrow">Pengajuan Cuti</p>
      <h1>Ajukan Cuti</h1>
      <p class="muted">Isi tanggal dan keperluan cuti. Pengajuan akan masuk ke panel admin untuk diproses.</p>
    </div>
    <div class="page-actions">
      <a class="ghost-action" href="{{ route('leave.index') }}">Kembali</a>
    </div>
  </div>

  <section class="panel narrow form-panel">
    <div class="panel-header compact">
      <div>
        <h2>Form Pengajuan</h2>
        <p class="muted">Sisa cuti Anda saat ini {{ $user->annual_leave_remaining }} hari kerja.</p>
      </div>
    </div>

    <form class="form-stack" method="post" action="{{ route('leave.store') }}">
      @csrf
      <div class="leave-form-title">
        <strong>Formulir Permohonan Cuti PJLP</strong>
        <span>Dinas Tenaga Kerja, Transmigrasi dan Energi Provinsi DKI Jakarta</span>
      </div>

      <div class="profile-grid">
        <label>
          <span>Nama PJLP</span>
          <input value="{{ $user->name }}" readonly>
        </label>
        <label>
          <span>NIP / ID PJLP</span>
          <input value="{{ $user->nip ?: '-' }}" readonly>
        </label>
        <label>
          <span>Jabatan</span>
          <input value="{{ $user->jabatan ?: 'PJLP' }}" readonly>
        </label>
        <label>
          <span>Sisa Cuti</span>
          <input value="{{ $user->annual_leave_remaining }} hari kerja" readonly>
        </label>
        <label>
          <span>Mulai Tanggal</span>
          <input type="date" name="start_date" value="{{ old('start_date') }}" min="{{ now()->toDateString() }}" required>
          @error('start_date') <p class="error-text">{{ $message }}</p> @enderror
        </label>
        <label>
          <span>Sampai Tanggal</span>
          <input type="date" name="end_date" value="{{ old('end_date') }}" min="{{ now()->toDateString() }}" required>
          @error('end_date') <p class="error-text">{{ $message }}</p> @enderror
        </label>
        <label class="full-width">
          <span>Jenis Lamanya Cuti</span>
          <div class="segmented-options">
            @foreach (['hari' => 'Hari', 'bulan' => 'Bulan', 'tahun' => 'Tahun'] as $value => $label)
              <label>
                <input type="radio" name="duration_unit" value="{{ $value }}" @checked(old('duration_unit', 'hari') === $value)>
                <span>{{ $label }}</span>
              </label>
            @endforeach
          </div>
          @error('duration_unit') <p class="error-text">{{ $message }}</p> @enderror
        </label>
        <label class="full-width">
          <span>Keperluan Cuti</span>
          <textarea name="reason" required>{{ old('reason') }}</textarea>
          @error('reason') <p class="error-text">{{ $message }}</p> @enderror
        </label>
      </div>

      <div class="soft-alert">
        <strong>Catatan Koordinator</strong>
        <span>Sebelum melaksanakan cuti, koordinasikan backup tugas harian dengan rekan kerja.</span>
      </div>

      <div class="actions-row">
        <button class="primary-action" type="submit">Kirim Pengajuan</button>
        <a class="ghost-action" href="{{ route('leave.index') }}">Batal</a>
      </div>
    </form>
  </section>
@endsection
