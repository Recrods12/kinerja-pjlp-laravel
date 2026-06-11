<script>
  (() => {
    const targets = Array.from(document.querySelectorAll('[data-reverse-geocode]'));
    if (!targets.length) {
      return;
    }

    const cache = new Map();

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
      const key = `${latitude},${longitude}`;
      if (cache.has(key)) {
        return cache.get(key);
      }

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

      const streetName = pickStreetName(await response.json());
      cache.set(key, streetName);
      return streetName;
    };

    targets.forEach(async (target) => {
      const latitude = target.dataset.lat;
      const longitude = target.dataset.lng;
      if (!latitude || !longitude) {
        return;
      }

      try {
        const streetName = await resolveStreetName(latitude, longitude);
        if (streetName) {
          target.textContent = streetName;
        }
      } catch (error) {
        // Keep the saved coordinate text when the map service is unavailable.
      }
    });
  })();
</script>
