@extends('layouts.app')

@section('content')
  <div class="page-heading">
    <div>
      <p class="eyebrow">Master Data</p>
      <h1>Kelola User</h1>
      <p class="muted">Atur akun admin dan PJLP, termasuk password, email, ID, profil, role, dan tanda tangan digital.</p>
    </div>
    <div class="page-actions">
      <a class="ghost-action" href="{{ route('dashboard') }}">Dashboard</a>
      <a class="ghost-action" href="#" onclick="document.getElementById('importModal').classList.add('open'); return false;">Import Excel</a>
      <a class="primary-action" href="{{ route('admin.users.create') }}">Tambah User</a>
    </div>
  </div>

  <section class="panel management-panel">
    <div class="panel-header compact">
      <div>
        <h2>Daftar Pengguna</h2>
        <p class="muted">{{ $users->count() }} akun terdaftar pada sistem kinerja PJLP.</p>
      </div>
    </div>

    <div class="table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Nama</th>
            <th>Login</th>
            <th>Role</th>
            <th>ID / NIK / Jabatan</th>
            <th>Kontak</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($users as $user)
            <tr>
              <td><strong>{{ $user->name }}</strong><br><span class="muted">{{ $user->unit ?: '-' }}</span></td>
              <td>
                <strong>{{ $user->username }}</strong><br>
                <span class="muted">{{ $user->email ?: 'Email belum diisi' }}</span>
              </td>
              <td><span class="status-pill {{ $user->role === 'admin' ? 'admin' : 'done' }}">{{ strtoupper($user->role) }}</span></td>
              <td>
                NIP PJLP: {{ $user->nip ?: '-' }}<br>
                NIK: {{ $user->nik ?: '-' }}<br>
                <span class="muted">{{ $user->jabatan ?: '-' }}</span>
              </td>
              <td>{{ $user->phone ?: '-' }}</td>
              <td>
                <div class="row-actions">
                  <a class="ghost-action" href="{{ route('admin.users.edit', $user) }}">Edit</a>
                  <form method="post" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Hapus user ini? Semua kinerja milik user ini juga akan terhapus.');">
                    @csrf
                    @method('delete')
                    <button class="danger-action" type="submit" @disabled($user->id === auth()->id())>Hapus</button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="6">Belum ada user.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  {{-- Modal Import Excel --}}
  <div class="modal-overlay" id="importModal" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="modal-box">
      <div class="modal-header">
        <h3>Import User dari Excel</h3>
        <button class="modal-close" onclick="document.getElementById('importModal').classList.remove('open')">&times;</button>
      </div>
      <div class="modal-body">
        <p class="muted">Upload file Excel dengan data user. Download template terlebih dahulu untuk melihat format yang benar.</p>
        <div class="form-group" style="margin:16px 0">
          <a class="primary-action" href="{{ route('admin.users.importTemplate') }}" target="_blank">📥 Download Template</a>
        </div>
        <form method="post" action="{{ route('admin.users.import') }}" enctype="multipart/form-data">
          @csrf
          <div class="form-group">
            <label for="importFile">Pilih File Excel (.xlsx, .xls, .csv)</label>
            <input type="file" name="file" id="importFile" accept=".xlsx,.xls,.csv" required>
          </div>
          <div class="form-actions" style="margin-top:16px">
            <button type="submit" class="primary-action">🚀 Import</button>
            <button type="button" class="ghost-action" onclick="document.getElementById('importModal').classList.remove('open')">Batal</button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection
