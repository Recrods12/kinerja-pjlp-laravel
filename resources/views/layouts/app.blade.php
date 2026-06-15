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
            @if ($authUser->avatar_path)
              <img class="brand-mark" src="{{ asset('storage/' . $authUser->avatar_path) }}" alt="Foto {{ $authUser->name }}">
            @else
              <span class="brand-mark">K</span>
            @endif
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
            <div class="topbar-right">
              <div class="notif-wrap">
                <button class="notif-bell" type="button" id="notif-toggle" aria-label="Notifikasi">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                  </svg>
                  <span class="notif-badge" id="notif-badge" style="display:none">0</span>
                </button>
                <div class="notif-dropdown" id="notif-dropdown">
                  <div class="notif-header">
                    <strong>Notifikasi</strong>
                    <button type="button" class="notif-mark-all" id="notif-mark-all">Baca Semua</button>
                  </div>
                  <div class="notif-list" id="notif-list">
                    <div class="notif-empty">Tidak ada notifikasi</div>
                  </div>
                </div>
              </div>
              <a class="user-chip" href="{{ route('profile.edit') }}">
                @if ($authUser->avatar_path)
                  <img class="avatar" src="{{ asset('storage/' . $authUser->avatar_path) }}" alt="Foto {{ $authUser->name }}">
                @else
                  <span class="avatar">{{ $initials }}</span>
                @endif
                <span>
                  <strong>{{ $authUser->name }}</strong>
                  <small>{{ $authUser->jabatan ?: strtoupper($authUser->role) }}</small>
                </span>
              </a>
            </div>
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
      <script>
        (() => {
          const notifToggle = document.getElementById('notif-toggle');
          const notifDropdown = document.getElementById('notif-dropdown');
          const notifList = document.getElementById('notif-list');
          const notifBadge = document.getElementById('notif-badge');
          const markAllBtn = document.getElementById('notif-mark-all');

          if (!notifToggle) return;

          const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

          // Load notifications
          const loadNotifications = () => {
            fetch('/notifications')
              .then(r => r.json())
              .then(data => {
                renderNotifications(data.notifications);
                updateBadge(data.unread_count);
              })
              .catch(() => {});
          };

          // Render notification list
          const renderNotifications = (notifications) => {
            if (!notifications || notifications.length === 0) {
              notifList.innerHTML = '<div class="notif-empty">Tidak ada notifikasi</div>';
              return;
            }

            notifList.innerHTML = notifications.map(n => {
              const isUnread = !n.read_at;
              const timeAgo = timeSince(new Date(n.created_at));
              const icon = n.type === 'leave_approved' ? '&#10003;'
                : n.type === 'leave_rejected' ? '&#10007;'
                : n.type === 'leave_submitted' ? '&#128221;'
                : '&#8505;';
              return '<div class="notif-item ' + (isUnread ? 'unread' : '') + '" data-id="' + n.id + '"' + (n.link ? ' data-link="' + n.link + '"' : '') + '>' +
                '<span class="notif-icon">' + icon + '</span>' +
                '<div class="notif-content">' +
                  '<div class="notif-title">' + escapeHtml(n.title) + '</div>' +
                  (n.body ? '<div class="notif-body">' + escapeHtml(n.body) + '</div>' : '') +
                  '<div class="notif-time">' + timeAgo + '</div>' +
                '</div>' +
              '</div>';
            }).join('');

            // Click handler per item
            notifList.querySelectorAll('.notif-item').forEach(el => {
              el.addEventListener('click', function() {
                const id = this.dataset.id;
                const link = this.dataset.link;

                // Mark as read
                fetch('/notifications/' + id + '/read', {
                  method: 'POST',
                  headers: { 'X-CSRF-TOKEN': csrfToken },
                }).catch(() => {});

                this.classList.remove('unread');

                if (link) {
                  window.location.href = link;
                }
              });
            });
          };

          // Update badge count
          const updateBadge = (count) => {
            if (count > 0) {
              notifBadge.textContent = count > 99 ? '99+' : count;
              notifBadge.style.display = '';
            } else {
              notifBadge.style.display = 'none';
            }
          };

          // Toggle dropdown
          let isNotifOpen = false;

          notifToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            isNotifOpen = !isNotifOpen;
            notifDropdown.classList.toggle('open', isNotifOpen);
            notifToggle.classList.toggle('active', isNotifOpen);

            if (isNotifOpen) {
              loadNotifications();
            }
          });

          // Close dropdown when clicking outside
          document.addEventListener('click', (e) => {
            if (isNotifOpen && !notifDropdown.contains(e.target) && e.target !== notifToggle) {
              isNotifOpen = false;
              notifDropdown.classList.remove('open');
              notifToggle.classList.remove('active');
            }
          });

          // Mark all as read
          markAllBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            fetch('/notifications/read-all', {
              method: 'POST',
              headers: { 'X-CSRF-TOKEN': csrfToken },
            }).then(() => {
              loadNotifications();
              updateBadge(0);
            }).catch(() => {});
          });

          // Poll unread count every 60 seconds
          const pollUnread = () => {
            if (!isNotifOpen) {
              fetch('/notifications/unread-count')
                .then(r => r.json())
                .then(data => updateBadge(data.count))
                .catch(() => {});
            }
          };

          setInterval(pollUnread, 60000);
          // Initial load for badge
          pollUnread();

          // Utility: time since
          function timeSince(date) {
            const seconds = Math.floor((new Date() - date) / 1000);
            if (seconds < 60) return 'baru saja';
            const minutes = Math.floor(seconds / 60);
            if (minutes < 60) return minutes + ' menit lalu';
            const hours = Math.floor(minutes / 60);
            if (hours < 24) return hours + ' jam lalu';
            const days = Math.floor(hours / 24);
            if (days < 7) return days + ' hari lalu';
            return date.toLocaleDateString('id-ID');
          }

          // Utility: escape HTML
          function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
          }
        })();
      </script>
    @endauth
  </body>
</html>
