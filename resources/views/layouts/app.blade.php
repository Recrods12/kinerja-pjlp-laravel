<!doctype html>
<html lang="id">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
  </head>
  <body>
    @auth
      <header class="topbar">
        <a class="topbar-title" href="{{ route('dashboard') }}">
          <span class="brand-mark">K</span>
          <span>
            <strong>Kinerja Harian PJLP</strong>
            <small>{{ auth()->user()->name }} &middot; {{ strtoupper(auth()->user()->role) }}</small>
          </span>
        </a>
        <nav class="topbar-actions">
          @if (auth()->user()->role === 'pjlp')
            <a class="ghost-action" href="{{ route('dashboard') }}">Dashboard</a>
            <a class="ghost-action" href="{{ route('leave.index') }}">Cuti</a>
            <a class="ghost-action" href="{{ route('profile.edit') }}">Profil</a>
          @elseif (auth()->user()->role === 'admin')
            <a class="ghost-action" href="{{ route('dashboard') }}">Dashboard</a>
            <a class="ghost-action" href="{{ route('admin.leave.index') }}">Cuti</a>
            <a class="ghost-action" href="{{ route('admin.users.index') }}">Kelola User</a>
            <a class="ghost-action" href="{{ route('profile.edit') }}">Profil</a>
          @endif
          <form method="post" action="{{ route('logout') }}">
            @csrf
            <button class="ghost-action" type="submit">Keluar</button>
          </form>
        </nav>
      </header>
    @endauth

    <main class="@auth workspace @else auth-shell @endauth">
      @if (session('status'))
        <div class="notice">{{ session('status') }}</div>
      @endif
      @if ($errors->any())
        <div class="notice error-notice">{{ $errors->first() }}</div>
      @endif
      @yield('content')
    </main>
  </body>
</html>
