<script>
  (() => {
    const form = document.querySelector('[data-attendance-form]');
    if (!form) return;

    const selfieInput = form.querySelector('[data-selfie-input]');
    const selfiePreview = form.querySelector('[data-selfie-preview]');
    const cameraTrigger = form.querySelector('[data-camera-trigger]');
    const selfieName = form.querySelector('[data-selfie-name]');
    const latitudeInput = form.querySelector('[data-latitude]');
    const longitudeInput = form.querySelector('[data-longitude]');
    const accuracyInput = form.querySelector('[data-accuracy]');
    const locationText = form.querySelector('[data-location-text]');
    const typeLabel = form.dataset.attendanceLabel || 'Absensi';

    let originalFile = null;
    let objectUrl = null;

    cameraTrigger?.addEventListener('click', () => selfieInput?.click());

    selfieInput?.addEventListener('change', () => {
      const file = selfieInput.files?.[0];
      if (!file) return;

      originalFile = file;
      stampSelfie(file);
    });

    window.refreshAttendanceSelfieTimestamp = () => {
      if (originalFile) stampSelfie(originalFile);
    };

    async function stampSelfie(file) {
      try {
        if (selfieName) selfieName.textContent = 'Memproses timestamp foto...';

        const image = await loadImage(file);
        const canvas = document.createElement('canvas');
        const sourceWidth = image.naturalWidth || image.width;
        const sourceHeight = image.naturalHeight || image.height;
        const scale = Math.min(1, 1400 / Math.max(sourceWidth, sourceHeight));

        canvas.width = Math.max(1, Math.round(sourceWidth * scale));
        canvas.height = Math.max(1, Math.round(sourceHeight * scale));

        const ctx = canvas.getContext('2d');
        ctx.drawImage(image, 0, 0, canvas.width, canvas.height);
        drawTimestamp(ctx, canvas);

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.86));
        if (!blob || typeof DataTransfer === 'undefined') {
          throw new Error('Browser tidak mendukung penggantian file otomatis.');
        }

        const stampedFile = new File([blob], `absensi-${Date.now()}.jpg`, { type: 'image/jpeg' });
        const transfer = new DataTransfer();
        transfer.items.add(stampedFile);
        selfieInput.files = transfer.files;
        setPreview(blob, stampedFile.name);
      } catch (error) {
        console.error(error);
        setPreview(file, file.name || 'Foto selfie sudah dipilih.');
      }
    }

    function loadImage(file) {
      return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const image = new Image();
        image.onload = () => {
          URL.revokeObjectURL(url);
          resolve(image);
        };
        image.onerror = () => {
          URL.revokeObjectURL(url);
          reject(new Error('Foto tidak bisa diproses.'));
        };
        image.src = url;
      });
    }

    function drawTimestamp(ctx, canvas) {
      const padding = Math.max(18, Math.round(canvas.width * 0.025));
      const fontSize = Math.max(24, Math.round(canvas.width * 0.032));
      const smallFont = Math.max(18, Math.round(canvas.width * 0.024));
      const lines = timestampLines();
      const boxHeight = padding * 2 + fontSize + (lines.length - 1) * (smallFont + 8);
      const y = canvas.height - boxHeight;

      ctx.fillStyle = 'rgba(0, 0, 0, .58)';
      ctx.fillRect(0, y, canvas.width, boxHeight);

      ctx.fillStyle = '#ffffff';
      ctx.font = `700 ${fontSize}px Arial, sans-serif`;
      ctx.fillText(typeLabel, padding, y + padding + fontSize);

      ctx.font = `500 ${smallFont}px Arial, sans-serif`;
      lines.slice(1).forEach((line, index) => {
        ctx.fillText(line, padding, y + padding + fontSize + ((index + 1) * (smallFont + 8)));
      });
    }

    function timestampLines() {
      const lat = latitudeInput?.value;
      const lng = longitudeInput?.value;
      const accuracy = accuracyInput?.value;
      const address = locationText?.value?.trim();
      const time = new Date().toLocaleString('id-ID', {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
      }).replace(/\./g, ':');

      const location = address || (lat && lng ? `Lat ${lat}, Lng ${lng}` : 'Lokasi belum terbaca');
      const precision = accuracy ? `Akurasi sekitar ${accuracy} meter` : 'Akurasi belum tersedia';

      return [typeLabel, time, location, precision];
    }

    function setPreview(source, name) {
      if (!selfiePreview) return;

      if (objectUrl) URL.revokeObjectURL(objectUrl);
      objectUrl = URL.createObjectURL(source);
      selfiePreview.src = objectUrl;
      selfiePreview.hidden = false;
      if (selfieName) selfieName.textContent = `${name} - timestamp otomatis ditambahkan.`;
    }
  })();
</script>
