<!doctype html>
<html lang="id">
  <head>
    <meta charset="utf-8">
    <style>
      @page { size: A4 landscape; margin: 0; }
      * { box-sizing: border-box; }
      body {
        margin: 0;
        color: #111;
        font-family: Arial, Helvetica, sans-serif;
      }
      .pdf-page {
        position: relative;
        width: 297mm;
        height: 210mm;
        page-break-after: always;
        overflow: hidden;
        background: #fff;
      }
      .pdf-page:last-child { page-break-after: auto; }
      .pdf-title {
        position: absolute;
        top: 5mm;
        left: 10mm;
        right: 10mm;
        text-align: center;
        font-weight: 700;
        font-size: 12px;
        line-height: 1.25;
      }
      .pdf-meta {
        position: absolute;
        top: 22mm;
        left: 11mm;
        right: 11mm;
        font-size: 10px;
      }
      .pdf-meta-left,
      .pdf-meta-right {
        position: absolute;
        top: 0;
        width: 120mm;
      }
      .pdf-meta-left { left: 0; }
      .pdf-meta-right { left: 146mm; }
      .pdf-meta-line {
        line-height: 4.7mm;
        white-space: nowrap;
      }
      .pdf-meta-label {
        display: inline-block;
        width: 22mm;
      }
      .pdf-meta-separator {
        display: inline-block;
        width: 5mm;
      }
      .pdf-table {
        position: absolute;
        top: 37mm;
        width: 132mm;
        border-collapse: collapse;
        table-layout: fixed;
        font-size: 8px;
      }
      .pdf-table.left { left: 11mm; }
      .pdf-table.right { left: 154mm; }
      .pdf-table th,
      .pdf-table td {
        border: 1px solid #111;
        height: 6.35mm;
        padding: .5mm 1.1mm;
        line-height: 1.05;
        vertical-align: middle;
        overflow: hidden;
      }
      .pdf-table th {
        height: 5.3mm;
        text-align: center;
        font-size: 7px;
        font-weight: 700;
      }
      .pdf-table .no-col { text-align: center; }
      .pdf-table .time-col { text-align: center; }
      .pdf-table .note-col { text-align: center; }
      .pdf-table .task-col { text-align: left; }
      .pdf-task-text {
        display: block;
        width: 100%;
        max-height: 1.15em;
        line-height: 1.05;
        white-space: nowrap;
        overflow: hidden;
        font-size: 12px;
      }
      .pdf-signature {
        position: absolute;
        top: 162mm;
        width: 132mm;
        height: 34mm;
        text-align: center;
        font-size: 11px;
        line-height: 1.1;
      }
      .pdf-signature.left { left: 11mm; }
      .pdf-signature.right { left: 154mm; }
      .pdf-signature-role {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
      }
      .pdf-signature-name {
        position: absolute;
        top: 25mm;
        left: 0;
        right: 0;
        margin: 0;
        font-size: 11px;
        font-weight: 700;
        text-decoration: underline;
      }
      .pdf-signature-id {
        position: absolute;
        top: 29mm;
        left: 0;
        right: 0;
      }
      .pdf-signature-image {
        display: block;
        width: 34mm;
        height: 13mm;
        object-fit: contain;
        position: absolute;
        top: 12mm;
        left: 49mm;
      }
    </style>
  </head>
  <body>
    @php
      $imageData = function (?string $path): ?string {
          if (! $path) {
              return null;
          }

          $fullPath = storage_path('app/public/' . $path);
          if (! is_file($fullPath)) {
              return null;
          }

          $mime = mime_content_type($fullPath) ?: 'image/png';
          return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($fullPath));
      };
      $approverName = $approver?->name ?: 'Andhika Ilviano Rizqullah';
      $approverNip = $approver?->nip ?: '199605192019031003';
      $approverSignature = $imageData($approver?->signature_path);
      $targetSignature = $imageData($target->signature_path);
    @endphp
    @foreach ($reportPages as $page)
      @php
        $leftDate = $page['leftDate'];
        $rightDate = $page['rightDate'];
        $targetName = $target->name;
      @endphp
      <section class="pdf-page">
        <div class="pdf-title">
          LAPORAN KINERJA HARIAN PJLP<br>
          DINAS TENAGA KERJA, TRANSMIGRASI DAN ENERGI PROVINSI DKI JAKARTA<br>
          TAHUN {{ $leftDate->year }}
        </div>

        <div class="pdf-meta">
          <div class="pdf-meta-left">
            <div class="pdf-meta-line"><span class="pdf-meta-label">NAMA</span><span class="pdf-meta-separator">:</span><strong>{{ $targetName }}</strong></div>
            <div class="pdf-meta-line"><span class="pdf-meta-label">HARI</span><span class="pdf-meta-separator">:</span>{{ $leftDate->translatedFormat('l') }}</div>
            <div class="pdf-meta-line"><span class="pdf-meta-label">TANGGAL</span><span class="pdf-meta-separator">:</span>{{ $leftDate->translatedFormat('d F Y') }}</div>
          </div>
          <div class="pdf-meta-right">
            <div class="pdf-meta-line"><span class="pdf-meta-label">HARI</span><span class="pdf-meta-separator">:</span>{{ $rightDate ? $rightDate->translatedFormat('l') : '' }}</div>
            <div class="pdf-meta-line"><span class="pdf-meta-label">TANGGAL</span><span class="pdf-meta-separator">:</span>{{ $rightDate ? $rightDate->translatedFormat('d F Y') : '' }}</div>
          </div>
        </div>

        @include('reports.pdf-table', ['items' => $page['leftEntries']->take(15)->values(), 'side' => 'left'])
        @include('reports.pdf-table', ['items' => $page['rightEntries']->take(15)->values(), 'side' => 'right'])

        <div class="pdf-signature left">
          <div class="pdf-signature-role">
            Mengetahui,<br>
            Kasubbag Umum Sekretariat<br>
            Dinas Tenaga Kerja, Transmigrasi dan Energi<br>
            Provinsi DKI Jakarta
          </div>
          @if ($approverSignature)
            <img class="pdf-signature-image" src="{{ $approverSignature }}" alt="Tanda tangan {{ $approverName }}">
          @endif
          <div class="pdf-signature-name">{{ $approverName }}</div>
          <div class="pdf-signature-id">NIP. {{ $approverNip }}</div>
        </div>

        <div class="pdf-signature right">
          <div class="pdf-signature-role">
            PJLP<br>
            Dinas Tenaga Kerja, Transmigrasi dan Energi<br>
            Provinsi DKI Jakarta
          </div>
          @if ($targetSignature)
            <img class="pdf-signature-image" src="{{ $targetSignature }}" alt="Tanda tangan {{ $targetName }}">
          @endif
          <div class="pdf-signature-name">{{ $targetName }}</div>
          <div class="pdf-signature-id">ID PJLP {{ $target->nip ?: '........................' }}</div>
        </div>
      </section>
    @endforeach
  </body>
</html>
