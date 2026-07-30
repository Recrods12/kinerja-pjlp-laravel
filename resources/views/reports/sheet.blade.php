<article class="report-sheet">
  @php
    $approverName = $approver?->name ?: 'Andhika Ilviano Rizqullah';
    $approverNip = $approver?->nip ?: '199605192019031003';
  @endphp
  <div class="report-title">
    LAPORAN KINERJA HARIAN PJLP<br>
    DINAS TENAGA KERJA, TRANSMIGRASI DAN ENERGI PROVINSI DKI JAKARTA<br>
    TAHUN {{ $leftDate->year }}
  </div>

  <div class="report-meta">
    <div>
      <div class="meta-line"><span>NAMA</span><span>:</span><strong>{{ $target->name }}</strong></div>
      <div class="meta-line"><span>HARI</span><span>:</span><span>{{ $leftDate->translatedFormat('l') }}</span></div>
      <div class="meta-line"><span>TANGGAL</span><span>:</span><span>{{ $leftDate->translatedFormat('d F Y') }}</span></div>
    </div>
    <div>
      <div class="meta-line"><span>HARI</span><span>:</span><span>{{ $rightDate ? $rightDate->translatedFormat('l') : '' }}</span></div>
      <div class="meta-line"><span>TANGGAL</span><span>:</span><span>{{ $rightDate ? $rightDate->translatedFormat('d F Y') : '' }}</span></div>
    </div>
  </div>

  <div class="report-columns">
    <div class="report-column">
      @include('reports.table', ['items' => $leftEntries->take(15)->values()])
    </div>
    <div class="report-column">
      @include('reports.table', ['items' => $rightEntries->take(15)->values()])
    </div>
  </div>

  @if ($isLastPage ?? true)
  <div class="signature-row">
    <div class="signature-block">
      <div>
        Mengetahui,<br>
        Kasubbag Umum Sekretariat<br>
        Dinas Tenaga Kerja, Transmigrasi dan Energi<br>
        Provinsi DKI Jakarta
        @if ($approver?->signature_path)
          <img class="signature-image" src="{{ asset('storage/' . $approver->signature_path) }}" alt="Tanda tangan {{ $approverName }}">
        @endif
      </div>
      <div>
        <div class="signature-name">{{ $approverName }}</div>
        NIP. {{ $approverNip }}
      </div>
    </div>
    <div class="signature-block">
      <div>
        PJLP<br>
        Dinas Tenaga Kerja, Transmigrasi dan Energi<br>
        Provinsi DKI Jakarta
        @if ($target->signature_path)
          <img class="signature-image" src="{{ asset('storage/' . $target->signature_path) }}" alt="Tanda tangan {{ $target->name }}">
        @endif
      </div>
      <div>
        <div class="signature-name">{{ $target->name }}</div>
        ID PJLP {{ $target->nip ?: '........................' }}
      </div>
    </div>
  </div>
  @endif
</article>
