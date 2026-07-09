<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class AdminUserController extends Controller
{
    public function index()
    {
        return view('admin.users.index', [
            'users' => User::query()->orderBy('role')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.users.form', [
            'managedUser' => new User([
                'role' => 'pjlp',
                'jabatan' => 'PJLP',
                'annual_leave_quota' => 12,
                'annual_leave_remaining' => 12,
            ]),
            'mode' => 'create',
        ]);
    }

    public function store(Request $request)
    {
        User::create($this->validatedData($request));

        return redirect()->route('admin.users.index')->with('status', 'User berhasil ditambahkan.');
    }

    public function edit(User $user)
    {
        return view('admin.users.form', [
            'managedUser' => $user,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validatedData($request, $user);
        if (! filled($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('admin.users.index')->with('status', 'User berhasil diperbarui.');
    }

    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->withErrors(['user' => 'Akun admin yang sedang dipakai tidak bisa dihapus.']);
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'User berhasil dihapus.');
    }

    /**
     * Download template Excel untuk import user.
     */
    public function downloadTemplate()
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Import User');

        $headers = [
            'Nama Lengkap*', 'Username*', 'Password*', 'Email', 'Role*',
            'NIP', 'NIK', 'Jabatan', 'Unit', 'No. HP', 'Alamat',
            'Kuota Cuti', 'Tim Keamanan', 'Tanggal Mulai Siklus',
        ];

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0D6F4B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];

        $colLetters = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N'];
        foreach ($headers as $i => $header) {
            $sheet->setCellValue($colLetters[$i] . '1', $header);
        }
        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->getStyle('A1:N1')->applyFromArray($headerStyle);

        $widths = ['A' => 22, 'B' => 18, 'C' => 18, 'D' => 28, 'E' => 12,
                    'F' => 22, 'G' => 22, 'H' => 22, 'I' => 20, 'J' => 16,
                    'K' => 30, 'L' => 14, 'M' => 16, 'N' => 22];
        foreach ($widths as $colLetter => $w) {
            $sheet->getColumnDimension($colLetter)->setWidth($w);
        }

        // Example rows
        $sheet->setCellValue('A2', 'Contoh: John Doe');
        $sheet->setCellValue('B2', 'johndoe');
        $sheet->setCellValue('C2', 'password123');
        $sheet->setCellValue('D2', 'johndoe@example.com');
        $sheet->setCellValue('E2', 'pjlp');
        $sheet->setCellValue('F2', '1234567890');
        $sheet->setCellValue('G2', '3201010101010001');
        $sheet->setCellValue('H2', 'Driver');
        $sheet->setCellValue('I2', 'Subbag Umum');
        $sheet->setCellValue('J2', '08123456789');
        $sheet->setCellValue('K2', 'Jl. Contoh No. 1');
        $sheet->setCellValue('L2', '12');
        $sheet->setCellValue('M2', '');
        $sheet->setCellValue('N2', '');

        $sheet->setCellValue('A3', 'Contoh: Jane Smith');
        $sheet->setCellValue('B3', 'janesmith');
        $sheet->setCellValue('C3', 'pass456');
        $sheet->setCellValue('D3', 'jane@example.com');
        $sheet->setCellValue('E3', 'admin');
        $sheet->setCellValue('F3', '');
        $sheet->setCellValue('G3', '');
        $sheet->setCellValue('H3', 'Admin');
        $sheet->setCellValue('I3', '');
        $sheet->setCellValue('J3', '');
        $sheet->setCellValue('K3', '');
        $sheet->setCellValue('L3', '12');
        $sheet->setCellValue('M3', '');
        $sheet->setCellValue('N3', '');

        $exampleStyle = [
            'font' => ['italic' => true, 'color' => ['rgb' => '888888']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ];
        $sheet->getStyle('A2:N3')->applyFromArray($exampleStyle);

        // Notes
        $sheet->setCellValue('A5', 'Petunjuk:');
        $sheet->setCellValue('A6', '- Kolom dengan tanda * (bintang) wajib diisi.');
        $sheet->setCellValue('A7', '- Role: pjlp atau admin.');
        $sheet->setCellValue('A8', '- Jabatan: Driver, Kebersihan, Keamanan, Mekanikal Enginer, Pelayanan Umum, PJLP, Admin.');
        $sheet->setCellValue('A9', '- Tim Keamanan: A, B, C (hanya untuk jabatan Keamanan).');
        $sheet->setCellValue('A10', '- Tanggal Mulai Siklus: format YYYY-MM-DD (hanya untuk Keamanan).');
        $sheet->setCellValue('A11', '- Kuota Cuti: default 12 jika dikosongkan.');
        $sheet->setCellValue('A12', '- Password minimal 6 karakter.');
        $sheet->getStyle('A5:A12')->getFont()->setBold(true);
        $sheet->getStyle('A6:A12')->getFont()->setBold(false);

        $writer = new Xlsx($spreadsheet);
        $filename = 'template-import-user.xlsx';

        return response()->stream(
            function () use ($writer) {
                $writer->save('php://output');
            },
            200,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename=\"$filename\"",
            ]
        );
    }

    /**
     * Import user dari file Excel.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:2048'],
        ]);

        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        if (count($rows) < 2) {
            return back()->withErrors(['file' => 'File Excel kosong.']);
        }

        // Remove header row
        array_shift($rows);

        $jobRoles = ['Driver', 'Kebersihan', 'Keamanan', 'Mekanikal Enginer', 'Pelayanan Umum', 'PJLP', 'Admin'];
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;

            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            $name = trim((string) ($row[0] ?? ''));
            $username = trim((string) ($row[1] ?? ''));
            $firstCell = strtolower($name);

            // Skip baris petunjuk / contoh / bukan data user
            if (
                str_starts_with($firstCell, 'petunjuk') ||
                str_starts_with($firstCell, '- ') ||
                str_starts_with($firstCell, 'contoh:') ||
                $name === ''
            ) {
                $skipped++;
                continue;
            }

            $password = (string) ($row[2] ?? '');
            $email = trim((string) ($row[3] ?? ''));
            $role = strtolower(trim((string) ($row[4] ?? '')));
            $nip = trim((string) ($row[5] ?? ''));
            $nik = trim((string) ($row[6] ?? ''));
            $jabatan = trim((string) ($row[7] ?? ''));
            $unit = trim((string) ($row[8] ?? ''));
            $phone = trim((string) ($row[9] ?? ''));
            $address = trim((string) ($row[10] ?? ''));
            $leaveQuota = (int) ($row[11] ?? 12);
            $securityTeam = trim((string) ($row[12] ?? ''));
            $cycleStart = trim((string) ($row[13] ?? ''));

            // Validation
            $rowErrors = [];

            if ($name === '') $rowErrors[] = 'Nama wajib diisi';
            if ($username === '') $rowErrors[] = 'Username wajib diisi';
            elseif (User::where('username', $username)->exists()) $rowErrors[] = "Username '$username' sudah terdaftar";
            if (strlen($password) < 6) $rowErrors[] = 'Password minimal 6 karakter';
            if (! in_array($role, ['pjlp', 'admin'], true)) $rowErrors[] = "Role harus 'pjlp' atau 'admin'";
            if ($email !== '' && User::where('email', $email)->exists()) $rowErrors[] = "Email '$email' sudah terdaftar";
            if ($jabatan !== '' && ! in_array($jabatan, $jobRoles, true)) $rowErrors[] = "Jabatan '$jabatan' tidak valid";
            if ($securityTeam !== '' && ! in_array($securityTeam, ['A', 'B', 'C'], true)) $rowErrors[] = "Tim Keamanan harus A, B, atau C";
            if ($cycleStart !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $cycleStart)) $rowErrors[] = "Tanggal Mulai Siklus format YYYY-MM-DD";
            if ($leaveQuota <= 0) $leaveQuota = 12;

            if (! empty($rowErrors)) {
                $errors[] = ($name ?: "Baris $rowNum") . ': ' . implode('; ', $rowErrors);
                continue;
            }

            // Normalize
            if ($role === 'admin') {
                $jabatan = 'Admin';
                $securityTeam = null;
                $cycleStart = null;
            } elseif ($jabatan === '' || $jabatan === 'Admin') {
                $jabatan = 'PJLP';
            }

            if ($jabatan !== 'Keamanan') {
                $securityTeam = null;
                $cycleStart = null;
            }

            User::create([
                'name' => $name,
                'username' => $username,
                'password' => Hash::make($password),
                'email' => $email ?: null,
                'role' => $role,
                'nip' => $nip ?: null,
                'nik' => $nik ?: null,
                'jabatan' => $jabatan,
                'unit' => $unit ?: null,
                'phone' => $phone ?: null,
                'address' => $address ?: null,
                'annual_leave_quota' => $leaveQuota,
                'annual_leave_remaining' => $leaveQuota,
                'security_team' => $securityTeam ?: null,
                'security_cycle_start_date' => $cycleStart ?: null,
            ]);

            $imported++;
        }

        // Build short message
        $message = $imported > 0 ? "$imported user berhasil diimpor." : 'Tidak ada user yang diimpor.';
        if ($skipped > 0) {
            $message .= " $skipped baris petunjuk dilewati.";
        }
        if (! empty($errors)) {
            $message .= ' ' . count($errors) . ' baris gagal.';
        }

        return redirect()->route('admin.users.index')
            ->with('status', $message)
            ->with('import_errors', $errors);
    }

    private function validatedData(Request $request, ?User $user = null): array
    {
        $userId = $user?->id;
        $passwordRule = $user ? ['nullable', 'confirmed', Password::min(6)] : ['required', 'confirmed', Password::min(6)];
        $jobRoles = ['Admin', 'PJLP', 'Driver', 'Kebersihan', 'Keamanan', 'Mekanikal Enginer', 'Pelayanan Umum'];

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($userId)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users')->ignore($userId)],
            'password' => $passwordRule,
            'role' => ['required', Rule::in(['admin', 'pjlp'])],
            'nip' => ['nullable', 'string', 'max:255'],
            'nik' => ['nullable', 'string', 'max:255'],
            'jabatan' => ['nullable', Rule::in($jobRoles)],
            'security_team' => ['nullable', Rule::in(['A', 'B', 'C'])],
            'security_cycle_start_date' => ['nullable', 'date'],
            'unit' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'annual_leave_quota' => ['required', 'integer', 'min:0', 'max:255'],
            'annual_leave_remaining' => ['required', 'integer', 'min:0', 'max:255', 'lte:annual_leave_quota'],
        ]);

        if ($data['role'] === 'admin') {
            $data['jabatan'] = 'Admin';
            $data['security_team'] = null;
            $data['security_cycle_start_date'] = null;
        } elseif (! filled($data['jabatan'] ?? null) || $data['jabatan'] === 'Admin') {
            $data['jabatan'] = 'PJLP';
        }

        if (($data['jabatan'] ?? null) !== 'Keamanan') {
            $data['security_team'] = null;
            $data['security_cycle_start_date'] = null;
        }

        return $data;
    }
}
