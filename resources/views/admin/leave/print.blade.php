<!doctype html>
<html lang="id">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Form Cuti {{ $leaveRequest->user->name }}</title>
    <style>
      * { box-sizing: border-box; }
      body {
        margin: 0;
        font-family: Arial, Helvetica, sans-serif;
        color: #000;
        background: #9f9f9f;
      }
      .actions {
        width: 287mm;
        margin: 14px auto;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
      }
      button,
      a {
        border: 1px solid #147a55;
        border-radius: 6px;
        padding: 9px 14px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
      }
      button { color: #fff; background: #147a55; }
      a { color: #111; background: #fff; }
      .sheet {
        width: 287mm;
        min-height: 181mm;
        margin: 0 auto 24px;
        padding: 4mm;
        background:
          linear-gradient(#d8d8d8 1px, transparent 1px),
          linear-gradient(90deg, #d8d8d8 1px, transparent 1px),
          #fff;
        background-size: 27mm 7mm;
        box-shadow: 0 18px 42px rgba(0,0,0,.28);
      }
      .cuti-form {
        width: 100%;
        height: 171mm;
        border: 3px solid #000;
        border-collapse: collapse;
        table-layout: fixed;
        background: rgba(255,255,255,.78);
        font-size: 14px;
      }
      .cuti-form td {
        border: 1px solid #000;
        padding: 4px 6px;
        vertical-align: middle;
      }
      .title {
        height: 19mm;
        text-align: center;
        font-size: 18px;
        font-weight: 800;
        letter-spacing: .2px;
      }
      .label { width: 42mm; }
      .value { font-weight: 600; }
      .center { text-align: center; }
      .date-middle {
        width: 46mm;
        text-align: center;
        background: #dedac8;
        font-weight: 700;
      }
      .note-cell {
        font-size: 14px;
        white-space: nowrap;
      }
      .approval-title {
        text-align: center;
        font-weight: 800;
      }
      .jabatan-cell {
        text-align: center;
        font-size: 13px;
        line-height: 1.5;
        padding: 6px 4px;
        font-weight: 700;
      }
      .signature-head {
        height: 12mm;
        text-align: center;
      }
      .signature-body {
        height: 40mm;
        text-align: center;
        vertical-align: top;
        line-height: 1.45;
      }
      .cuti-signature-image {
        display: block;
        width: 64mm;
        height: 24mm;
        object-fit: contain;
        margin: 5mm auto 0;
      }
      .signature-name {
        height: 8mm;
        text-align: center;
        font-size: 14px;
      }
      .signature-name strong {
        font-weight: 400;
      }
      .signature-nip {
        height: 8mm;
        text-align: center;
        font-size: 14px;
      }
      .right-nik {
        text-align: center;
      }
      .struck {
        text-decoration: line-through;
        text-decoration-thickness: 1.5px;
      }
      @media print {
        body { background: #fff; }
        .actions { display: none; }
        .sheet {
          width: auto;
          min-height: auto;
          margin: 0;
          padding: 0;
          box-shadow: none;
        }
        .cuti-form {
          height: 181mm;
        }
        @page { size: A4 landscape; margin: 5mm; }
      }
    </style>
  </head>
  <body>
    <div class="actions">
      <a href="{{ auth()->user()?->role === 'admin' ? route('admin.leave.show', $leaveRequest) : route('leave.show', $leaveRequest) }}">Kembali</a>
      <button onclick="window.print()">Cetak / Simpan PDF</button>
    </div>

    <main class="sheet">
      @php
        $durationUnit = $leaveRequest->duration_unit ?: 'hari';
        $approver = $approver ?? $leaveRequest->approver;
        $approverName = $approver?->name ?: 'Andhika Ilviano Rizqullah';
        $approverNip = $approver?->nip ?: '199605192019031003';
      @endphp
      <table class="cuti-form">
        <colgroup>
          <col style="width: 42mm">
          <col>
          <col style="width: 46mm">
          <col>
        </colgroup>
        <tbody>
          <tr>
            <td class="title" colspan="4">DINAS TENAGA KERJA, TRANSMIGRASI DAN ENERGI PROVINSI DKI JAKARTA</td>
          </tr>
          <tr>
            <td class="label">Nama Pegawai</td>
            <td class="value" colspan="3">{{ $leaveRequest->user->name }}</td>
          </tr>
          <tr>
            <td class="label">Petugas</td>
            <td class="value" colspan="3">{{ $leaveRequest->user->jabatan ?: 'PJLP' }}</td>
          </tr>
          <tr>
            <td class="label">ID PJLP</td>
            <td class="value" colspan="3">{{ $leaveRequest->user->nip ?: '-' }}</td>
          </tr>
          <tr>
            <td class="label">Keperluan Cuti</td>
            <td colspan="3">{{ $leaveRequest->reason }}</td>
          </tr>
          <tr>
            <td class="label">Lamanya Cuti</td>
            <td colspan="3">
              <span class="value">{{ $leaveRequest->total_days }}</span>
              <span style="margin-left: 54mm;">
                (
                <span class="{{ $durationUnit === 'hari' ? '' : 'struck' }}">Hari</span>
                /
                <span class="{{ $durationUnit === 'bulan' ? '' : 'struck' }}">Bulan</span>
                /
                <span class="{{ $durationUnit === 'tahun' ? '' : 'struck' }}">Tahun</span>
                )
              </span>
            </td>
          </tr>
          <tr>
            <td class="label">Mulai Tanggal</td>
            <td class="center value">{{ $leaveRequest->start_date->translatedFormat('d F Y') }}</td>
            <td class="date-middle">s.d</td>
            <td class="center value">{{ $leaveRequest->end_date->translatedFormat('d F Y') }}</td>
          </tr>
          <tr>
            <td class="label">Catt Koordinator *</td>
            <td class="note-cell" colspan="3">Sebelum melaksanakan cuti agar berkoordinasi dengan rekan kerjanya untuk memback up tugas sehari-hari.</td>
          </tr>
          <tr>
            <td class="approval-title" colspan="2">PERSETUJUAN</td>
            <td colspan="2" class="center">Jakarta, {{ $leaveRequest->approved_at?->translatedFormat('d F Y') ?? now()->translatedFormat('d F Y') }}</td>
          </tr>
          <tr>
            <td colspan="2" class="signature-head">Tgl : {{ $leaveRequest->approved_at?->translatedFormat('d F Y') ?? '-' }}</td>
            <td colspan="2"></td>
          </tr>
          <tr>
            <td colspan="2" class="jabatan-cell">
              Kepala Sub Bagian Umum<br>
              Dinas Tenaga Kerja, Transmigrasi dan Energi<br>
              Provinsi DKI Jakarta
            </td>
            <td colspan="2" class="signature-head">Pemohon,</td>
          </tr>
          <tr>
            <td colspan="2" class="signature-body">
              @if ($approver?->signature_path)
                <img class="cuti-signature-image" src="{{ asset('storage/' . $approver->signature_path) }}" alt="Tanda tangan {{ $approverName }}">
              @endif
            </td>
            <td colspan="2" class="signature-body">
              @if ($leaveRequest->user->signature_path)
                <img class="cuti-signature-image" src="{{ asset('storage/' . $leaveRequest->user->signature_path) }}" alt="Tanda tangan {{ $leaveRequest->user->name }}">
              @endif
            </td>
          </tr>
          <tr>
            <td colspan="2" class="signature-name"><strong>{{ $approverName }}</strong></td>
            <td colspan="2"></td>
          </tr>
          <tr>
            <td colspan="2" class="signature-nip">NIP {{ $approverNip }}</td>
            <td colspan="2" class="signature-nip">NIK. {{ $leaveRequest->user->nik ?: '-' }}</td>
          </tr>
        </tbody>
      </table>
    </main>
  </body>
</html>
