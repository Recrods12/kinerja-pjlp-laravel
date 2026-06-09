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
      @php
        $authUser = auth()->user();
        $isAdmin = $authUser->role === 'admin';
        $initials = collect(explode(' ', trim($authUser->name)))
          ->filter()
          ->map(fn ($part) => \Illuminate\Support\Str::substr($part, 0, 1))
          ->take(2)
          ->implode('') ?: 'K';
        $sidebarGroups = $isAdmin
          ? [
              'Menu' => [
                ['label' => 'Dashboard', 'route' => route('dashboard'), 'active' => request()->routeIs('dashboard')],
              ],
              'Kinerja' => [
                ['label' => 'All Kinerja Bulanan', 'route' => route('dashboard') . '#monthly', 'active' => false],
                ['label' => 'Export Laporan', 'route' => route('admin.export.csv'), 'active' => request()->routeIs('admin.export.csv')],
              ],
              'Cuti' => [
                ['label' => 'Pengajuan Cuti', 'route' => route('admin.leave.index'), 'active' => request()->routeIs('admin.leave.index') || request()->routeIs('admin.leave.show') || request()->routeIs('admin.leave.print')],
                ['label' => 'Kalender Cuti', 'route' => route('admin.leave.calendar'), 'active' => request()->routeIs('admin.leave.calendar')],
              ],
              'Absensi' => [
                ['label' => 'Dashboard Absensi', 'route' => route('admin.attendance.index'), 'active' => request()->routeIs('admin.attendance.*')],
              ],
              'Master Data' => [
                ['label' => 'Kelola User', 'route' => route('admin.users.index'), 'active' => request()->routeIs('admin.users.*')],
                ['label' => 'Kelola Libur', 'route' => route('admin.holidays.index'), 'active' => request()->routeIs('admin.holidays.*')],
                ['label' => 'Data Driver', 'route' => route('dashboard', ['jabatan' => 'Driver']), 'active' => request('jabatan') === 'Driver'],
                ['label' => 'Data Kebersihan', 'route' => route('dashboard', ['jabatan' => 'Kebersihan']), 'active' => request('jabatan') === 'Kebersihan'],
                ['label' => 'Data Keamanan', 'route' => route('dashboard', ['jabatan' => 'Keamanan']), 'active' => request('jabatan') === 'Keamanan'],
                ['label' => 'Data Pelayanan Umum', 'route' => route('dashboard', ['jabatan' => 'Pelayanan Umum']), 'active' => request('jabatan') === 'Pelayanan Umum'],
              ],
            ]
          : [
              'Menu' => [
                ['label' => 'Dashboard', 'route' => route('dashboard'), 'active' => request()->routeIs('dashboard')],
              ],
              'Kinerja' => [
                ['label' => 'Kinerja Harian', 'route' => route('dashboard'), 'active' => request()->routeIs('dashboard')],
                ['label' => 'Lihat Laporan', 'route' => route('reports.show', ['date' => now()->toDateString()]), 'active' => request()->routeIs('reports.show')],
              ],
              'Cuti' => [
                ['label' => 'Ajukan Cuti', 'route' => route('leave.create'), 'active' => request()->routeIs('leave.create')],
                ['label' => 'Riwayat Cuti', 'route' => route('leave.index'), 'active' => request()->routeIs('leave.index') || request()->routeIs('leave.show')],
                ['label' => 'Kalender Cuti', 'route' => route('leave.calendar'), 'active' => request()->routeIs('leave.calendar')],
              ],
              'Absensi' => [
                ['label' => 'Absen Mobile', 'route' => route('attendance.index'), 'active' => request()->routeIs('attendance.*')],
              ],
            ];
      @endphp

      <div class="app-shell">
        <aside class="app-sidebar" id="app-sidebar">
          <a class="sidebar-brand" href="{{ route('dashboard') }}">
            <span class="brand-mark">K</span>
            <span>
              <strong>Kinerja Harian PJLP</strong>
              <small>{{ $authUser->name }} &middot; {{ strtoupper($authUser->role) }}</small>
            </span>
          </a>

          <nav class="sidebar-nav" aria-label="Navigasi utama">
            @foreach ($sidebarGroups as $groupLabel => $links)
              <div class="sidebar-group">
                <p>{{ $groupLabel }}</p>
                @foreach ($links as $link)
                  <a class="sidebar-link {{ $link['active'] ? 'active' : '' }}" href="{{ $link['route'] }}">
                    <span>{{ \Illuminate\Support\Str::substr($link['label'], 0, 1) }}</span>
                    {{ $link['label'] }}
                  </a>
                @endforeach
              </div>
            @endforeach
            <div class="sidebar-group">
              <p>Pengaturan</p>
              <a class="sidebar-link {{ request()->routeIs('profile.*') ? 'active' : '' }}" href="{{ route('profile.edit') }}">
                <span>P</span>
                Profil
              </a>
            </div>
          </nav>

          <form class="sidebar-logout" method="post" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Keluar</button>
          </form>
        </aside>

        <div class="sidebar-backdrop" data-sidebar-close></div>

        <section class="content-shell">
          <header class="content-topbar">
            <button class="sidebar-toggle" type="button" aria-controls="app-sidebar" aria-expanded="false" data-sidebar-toggle>Menu</button>
            <div class="topbar-context">
              <strong>{{ $isAdmin ? 'Dashboard Admin' : 'Dashboard PJLP' }}</strong>
              <span>{{ now()->format('d/m/Y') }}</span>
            </div>
            <a class="user-chip" href="{{ route('profile.edit') }}">
              <span class="avatar">{{ $initials }}</span>
              <span>
                <strong>{{ $authUser->name }}</strong>
                <small>{{ $authUser->jabatan ?: strtoupper($authUser->role) }}</small>
              </span>
            </a>
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
        </section>
      </div>
      <script>
        (() => {
          const toggle = document.querySelector('[data-sidebar-toggle]');
          const backdrop = document.querySelector('[data-sidebar-close]');

          const setOpen = (open) => {
            document.body.classList.toggle('sidebar-open', open);
            toggle?.setAttribute('aria-expanded', String(open));
          };

          toggle?.addEventListener('click', () => {
            setOpen(!document.body.classList.contains('sidebar-open'));
          });

          backdrop?.addEventListener('click', () => setOpen(false));

          document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
              setOpen(false);
            }
          });
        })();
      </script>
    @endauth
  </body>
</html>
