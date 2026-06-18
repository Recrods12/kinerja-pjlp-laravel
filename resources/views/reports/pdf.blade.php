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
    width: 297mm;
    height: 210mm;
    page-break-after: always;
    overflow: hidden;
    background: #fff;
    padding: 10mm 12mm 8mm;
  }
  .pdf-page:last-child { page-break-after: auto; }
  .pdf-title {
    text-align: center;
    font-weight: 700;
    font-size: 12px;
    line-height: 1.35;
    margin-bottom: 5mm;
  }
  .pdf-meta {
    display: flex;
    gap: 12mm;
    font-size: 11px;
    margin-bottom: 3mm;
  }
  .pdf-meta-left, .pdf-meta-right {
    flex: 1;
  }
  .pdf-meta-line {
    display: flex;
    min-height: 5mm;
  }
  .pdf-meta-lbl { width: 26mm; }
  .pdf-meta-colon { width: 4mm; }
  .pdf-meta-val { flex: 1; }
  .pdf-columns {
    display: flex;
    gap: 10mm;
  }
  .pdf-column {
    flex: 1;
    min-width: 0;
  }
  .pdf-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 8px;
  }
  .pdf-table th,
  .pdf-table td {
    border: 1px solid #1d2623;
    height: 7.2mm;
    padding: 0.5mm 1.2mm;
    text-align: center;
    vertical-align: middle;
    overflow: hidden;
  }
  .pdf-table th {
    height: 5mm;
    font-size: 7px;
    font-weight: 700;
  }
  .pdf-table .no-col { width: 9mm; }
  .pdf-table .time-col { width: 19mm; }
  .pdf-table .note-col { width: 24mm; }
  .pdf-table td:nth-child(3) { text-align: left; }
  .pdf-task-text {
    display: block;
    max-height: 1.15em;
    overflow: hidden;
    line-height: 1.15;
    white-space: nowrap;
    font-size: 9px;
  }
  .pdf-signature-row {
    display: flex;
    gap: 10mm;
    margin-top: 6mm;
    text-align: center;
    font-size: 10px;
  }
  .pdf-signature-block {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 28mm;
  }
  .pdf-signature-name {
    font-weight: 700;
    text-decoration: underline;
    margin-top: 18mm;
  }
  .pdf-signature-image {
    display: block;
    width: 40mm;
    height: 13mm;
    object-fit: contain;
    margin: 4mm auto -16mm;
  }
</style>
</head>
<body>
  @php
    $imageData = function (?string $path): ?string {
        if (! $path) { return null; }
        $fullPath = storage_path('app/public/' . $path);
        if (! is_file($fullPath)) { return null; }
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
          <div class="pdf-meta-line"><span class="pdf-meta-lbl">NAMA</span><span class="pdf-meta-colon">:</span><span class="pdf-meta-val"><strong>{{ $targetName }}</strong></span></div>
          <div class="pdf-meta-line"><span class="pdf-meta-lbl">HARI</span><span class="pdf-meta-colon">:</span><span class="pdf-meta-val">{{ $leftDate->translatedFormat('l') }}</span></div>
          <div class="pdf-meta-line"><span class="pdf-meta-lbl">TANGGAL</span><span class="pdf-meta-colon">:</span><span class="pdf-meta-val">{{ $leftDate->translatedFormat('d F Y') }}</span></div>
        </div>
        <div class="pdf-meta-right">
          <div class="pdf-meta-line"><span class="pdf-meta-lbl">&nbsp;</span><span class="pdf-meta-colon">&nbsp;</span><span class="pdf-meta-val">&nbsp;</span></div>
          <div class="pdf-meta-line"><span class="pdf-meta-lbl">HARI</span><span class="pdf-meta-colon">:</span><span class="pdf-meta-val">{{ $rightDate ? $rightDate->translatedFormat('l') : '' }}</span></div>
          <div class="pdf-meta-line"><span class="pdf-meta-lbl">TANGGAL</span><span class="pdf-meta-colon">:</span><span class="pdf-meta-val">{{ $rightDate ? $rightDate->translatedFormat('d F Y') : '' }}</span></div>
        </div>
      </div>

      <div class="pdf-columns">
        <div class="pdf-column">
          @include('reports.pdf-table', ['items' => $page['leftEntries']->take(15)->values()])
        </div>
        <div class="pdf-column">
          @include('reports.pdf-table', ['items' => $page['rightEntries']->take(15)->values()])
        </div>
      </div>

      @if ($loop->last)
        <div class="pdf-signature-row">
          <div class="pdf-signature-block">
            <div>
              Mengetahui,<br>
              Kasubbag Umum Sekretariat<br>
              Dinas Tenaga Kerja, Transmigrasi dan Energi<br>
              Provinsi DKI Jakarta
            </div>
            @if ($approverSignature)
              <img class="pdf-signature-image" src="{{ $approverSignature }}" alt="Tanda tangan {{ $approverName }}">
            @endif
            <div class="pdf-signature-name">{{ $approverName }}</div>
            <div>NIP. {{ $approverNip }}</div>
          </div>
          <div class="pdf-signature-block">
            <div>
              PJLP<br>
              Dinas Tenaga Kerja, Transmigrasi dan Energi<br>
              Provinsi DKI Jakarta
            </div>
            @if ($targetSignature)
              <img class="pdf-signature-image" src="{{ $targetSignature }}" alt="Tanda tangan {{ $targetName }}">
            @endif
            <div class="pdf-signature-name">{{ $targetName }}</div>
            <div>ID PJLP {{ $target->nip ?: '........................' }}</div>
          </div>
        </div>
      @endif
    </section>
  @endforeach
</body>
</html>
