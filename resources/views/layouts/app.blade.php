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
        <button class="topbar-toggle" type="button" aria-expanded="true" aria-controls="site-nav" aria-label="Tutup menu" data-topbar-toggle>
          <span aria-hidden="true"></span>
        </button>
        <nav class="topbar-actions" id="site-nav">
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
    @auth
      <script>
        (() => {
          const topbar = document.querySelector('.topbar');
          const toggle = document.querySelector('[data-topbar-toggle]');
          const nav = document.querySelector('#site-nav');

          if (!topbar || !toggle || !nav) {
            return;
          }

          const storageKey = 'pjlp-mobile-menu-collapsed';

          const setCollapsed = (collapsed) => {
            topbar.classList.toggle('is-collapsed', collapsed);
            toggle.setAttribute('aria-expanded', String(!collapsed));
            toggle.setAttribute('aria-label', collapsed ? 'Buka menu' : 'Tutup menu');
          };

          setCollapsed(sessionStorage.getItem(storageKey) === '1');

          toggle.addEventListener('click', () => {
            const collapsed = !topbar.classList.contains('is-collapsed');
            sessionStorage.setItem(storageKey, collapsed ? '1' : '0');
            setCollapsed(collapsed);
          });
        })();
      </script>
    @endauth
  </body>
</html>
