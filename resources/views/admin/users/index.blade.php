@extends('layouts.app')

@section('content')
  <section class="panel">
    <div class="panel-header">
      <div>
        <h2>Kelola User</h2>
        <p class="muted">Atur akun admin dan PJLP, termasuk password, email, ID, profil, dan role.</p>
      </div>
      <div class="actions-row">
        <a class="ghost-action" href="{{ route('dashboard') }}">Kembali</a>
        <a class="primary-action" href="{{ route('admin.users.create') }}">Tambah User</a>
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
