<!doctype html>
<html>
  <head>
    <meta charset="utf-8">
    <style>
      table { border-collapse: collapse; width: 100%; }
      th, td { border: 1px solid #111; padding: 6px; vertical-align: top; }
      th { background: #dfeee7; font-weight: bold; text-align: center; }
    </style>
  </head>
  <body>
    <table>
      <thead>
        <tr>
          <th>No</th>
          <th>Nama</th>
          <th>NIP PJLP</th>
          <th>NIK</th>
          <th>Jabatan</th>
          <th>Tanggal Mulai</th>
          <th>Tanggal Selesai</th>
          <th>Durasi</th>
          <th>Keperluan Cuti</th>
          <th>Status</th>
          <th>Diproses Oleh</th>
          <th>Tanggal Proses</th>
          <th>Catatan</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($leaveRequests as $leaveRequest)
          <tr>
            <td>{{ $loop->iteration }}</td>
            <td>{{ $leaveRequest->user->name }}</td>
            <td style="mso-number-format:\@">{{ $leaveRequest->user->nip ?: '-' }}</td>
            <td style="mso-number-format:\@">{{ $leaveRequest->user->nik ?: '-' }}</td>
            <td>{{ $leaveRequest->user->jabatan ?: 'PJLP' }}</td>
            <td>{{ $leaveRequest->start_date->format('d/m/Y') }}</td>
            <td>{{ $leaveRequest->end_date->format('d/m/Y') }}</td>
            <td>{{ $leaveRequest->total_days }} {{ ucfirst($leaveRequest->duration_unit ?: 'hari') }}</td>
            <td>{{ $leaveRequest->reason }}</td>
            <td>{{ ucfirst($leaveRequest->status) }}</td>
            <td>{{ $leaveRequest->approver->name ?? '-' }}</td>
            <td>{{ $leaveRequest->approved_at?->format('d/m/Y H:i') ?? '-' }}</td>
            <td>{{ $leaveRequest->admin_note ?: '-' }}</td>
          </tr>
        @empty
          <tr><td colspan="13">Belum ada data cuti.</td></tr>
        @endforelse
      </tbody>
    </table>
  </body>
</html>
