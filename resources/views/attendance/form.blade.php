@extends('layouts.app')

@php
  use App\Models\AttendanceRecord;

  $monthNames = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
  $dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
  $dateLabel = $dayNames[$date->dayOfWeek] . ', ' . $date->format('d') . ' ' . $monthNames[$date->month] . ' ' . $date->year;
  $tone = match ($type) {
    AttendanceRecord::TYPE_END => 'red',
    AttendanceRecord::TYPE_FIELD => 'blue',
    default => 'green',
  };
@endphp

@section('content')
  <section class="page-heading">
    <div>
      <p class="eyebrow">Absensi Mobile</p>
      <h1>{{ $typeLabel }}</h1>
      <p class="muted">{{ $dateLabel }}. Pastikan lokasi dan foto selfie sudah benar sebelum mengirim.</p>
    </div>
    <div class="page-actions">
      <a class="ghost-action" href="{{ route('attendance.index') }}">Kembali</a>
    </div>
  </section>

  <section class="attendance-form-shell">
    <article class="panel attendance-phone">
      <div class="attendance-notice {{ $tone }}">
        @foreach ($rules as $rule)
          <p>{{ $rule }}</p>
        @endforeach
      </div>

      <div class="attendance-time">
        <span>Jam Server</span>
        <strong data-server-clock data-start="{{ now()->toIso8601String() }}">{{ now()->format('H:i:s') }}</strong>
        <small>{{ $dateLabel }}</small>
      </div>

      <form class="form-stack" method="post" action="{{ route('attendance.store', $type) }}" enctype="multipart/form-data" data-attendance-form data-attendance-label="{{ $typeLabel }}">
        @csrf
        <input type="hidden" name="latitude" data-latitude>
        <input type="hidden" name="longitude" data-longitude>
        <input type="hidden" name="accuracy" data-accuracy>

        @if ($type === AttendanceRecord::TYPE_FIELD)
          <label>
            <span>Tujuan / Keterangan Tugas</span>
            <textarea name="note" required placeholder="Contoh: Pengiriman barang ke Dinas Pendidikan Jakarta Selatan">{{ old('note') }}</textarea>
          </label>
        @else
          <label>
            <span>Keterangan</span>
            <textarea name="note" placeholder="Opsional">{{ old('note') }}</textarea>
          </label>
        @endif

        <label>
          <span>Lokasi Terdeteksi</span>
          <input name="address" value="{{ old('address') }}" placeholder="Menunggu GPS atau isi alamat manual" data-location-text>
        </label>

        <div class="map-preview">
          <span data-location-status>Mengambil lokasi perangkat...</span>
          <a href="#" target="_blank" rel="noopener" data-map-link hidden>Lihat Peta</a>
        </div>

        <label>
          <span>Foto Selfie</span>
          <div class="camera-upload">
            <button class="ghost-action camera-trigger" type="button" data-camera-trigger>Ambil Foto Selfie</button>
            <small data-selfie-name>Belum ada foto. Di HP tombol ini akan membuka kamera.</small>
          </div>
          <input class="sr-only-file" type="file" name="selfie" accept="image/*" capture="user" required data-selfie-input>
        </label>
        <img class="selfie-preview" alt="Preview selfie" data-selfie-preview hidden>

        <button class="primary-action attendance-submit {{ $tone }}" type="submit" data-attendance-submit disabled>Menunggu Lokasi GPS</button>
      </form>
    </article>
  </section>

  <script>
    const clock = document.querySelector('[data-server-clock]');
    if (clock) {
      const start = new Date(clock.dataset.start).getTime();
      const clientStart = Date.now();
      setInterval(() => {
        const current = new Date(start + (Date.now() - clientStart));
        clock.textContent = current.toLocaleTimeString('id-ID', { hour12: false });
      }, 1000);
    }

    const latitudeInput = document.querySelector('[data-latitude]');
    const longitudeInput = document.querySelector('[data-longitude]');
    const accuracyInput = document.querySelector('[data-accuracy]');
    const locationText = document.querySelector('[data-location-text]');
    const locationStatus = document.querySelector('[data-location-status]');
    const mapLink = document.querySelector('[data-map-link]');
    const submitButton = document.querySelector('[data-attendance-submit]');
    const submitLabel = @json($typeLabel);

    const coordinateFallback = (latitude, longitude) => `Lat ${latitude.toFixed(5)}, Lng ${longitude.toFixed(5)}`;

    const pickStreetName = (payload) => {
      const address = payload?.address || {};

      return address.road
        || address.pedestrian
        || address.footway
        || address.path
        || address.neighbourhood
        || address.suburb
        || address.village
        || address.city_district
        || (payload?.display_name ? payload.display_name.split(',').slice(0, 2).join(',').trim() : '');
    };

    const resolveStreetName = async (latitude, longitude) => {
      const url = new URL('https://nominatim.openstreetmap.org/reverse');
      url.search = new URLSearchParams({
        format: 'jsonv2',
        lat: latitude,
        lon: longitude,
        zoom: '18',
        addressdetails: '1',
        'accept-language': 'id',
      });

      const response = await fetch(url.toString(), { headers: { Accept: 'application/json' } });
      if (!response.ok) {
        return '';
      }

      return pickStreetName(await response.json());
    };

    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition((position) => {
        const { latitude, longitude, accuracy } = position.coords;
        const fallback = coordinateFallback(latitude, longitude);

        latitudeInput.value = latitude.toFixed(7);
        longitudeInput.value = longitude.toFixed(7);
        accuracyInput.value = Math.round(accuracy);
        locationText.value = locationText.value || fallback;
        locationStatus.textContent = `GPS aktif, akurasi sekitar ${Math.round(accuracy)} meter`;
        mapLink.href = `https://www.google.com/maps?q=${latitude},${longitude}`;
        mapLink.hidden = false;
        submitButton.disabled = false;
        submitButton.textContent = submitLabel;

        resolveStreetName(latitude, longitude).then((streetName) => {
          if (streetName && (!locationText.value || locationText.value === fallback)) {
            locationText.value = streetName;
          }
        }).catch(() => {});
      }, () => {
        locationStatus.textContent = 'GPS belum aktif. Izinkan lokasi atau isi alamat manual.';
      }, {
        enableHighAccuracy: true,
        timeout: 12000,
        maximumAge: 0,
      });
    } else {
      locationStatus.textContent = 'Browser tidak mendukung GPS. Isi alamat manual.';
    }

    window.refreshAttendanceSelfieTimestamp?.();
  </script>

  @include('attendance.partials.selfie-timestamp')
@endsection
