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
@endsection
