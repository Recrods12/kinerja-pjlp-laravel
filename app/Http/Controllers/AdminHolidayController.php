<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AdminHolidayController extends Controller
{
    public function index()
    {
        return view('admin.holidays.index', [
            'holidays' => Holiday::query()->orderByDesc('holiday_date')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'holiday_date' => ['required', 'date', 'unique:holidays,holiday_date'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        Holiday::create($data);

        return redirect()->route('admin.holidays.index')->with('status', 'Tanggal libur berhasil ditambahkan.');
    }

    public function syncNational(Request $request)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $year = (int) $data['year'];
        try {
            $response = Http::timeout(15)
                ->withoutVerifying()
                ->acceptJson()
                ->get('https://api-hari-libur.vercel.app/api', ['year' => $year]);
        } catch (ConnectionException) {
            return back()->withErrors([
                'sync' => 'Gagal terhubung ke server libur nasional. Periksa koneksi internet lalu coba lagi.',
            ]);
        }

        if (! $response->ok() || $response->json('status') !== 'success') {
            return back()->withErrors([
                'sync' => 'Gagal mengambil data libur nasional. Coba lagi beberapa saat.',
            ]);
        }

        $holidays = collect($response->json('data', []))
            ->filter(fn ($holiday) => filled($holiday['date'] ?? null) && filled($holiday['description'] ?? null))
            ->filter(fn ($holiday) => Carbon::parse($holiday['date'])->year === $year);

        foreach ($holidays as $holiday) {
            Holiday::updateOrCreate(
                ['holiday_date' => Carbon::parse($holiday['date'])->toDateString()],
                ['name' => $holiday['description']]
            );
        }

        return redirect()
            ->route('admin.holidays.index')
            ->with('status', $holidays->count() . ' tanggal libur nasional tahun ' . $year . ' berhasil disinkronkan.');
    }

    public function destroy(Holiday $holiday)
    {
        $holiday->delete();

        return redirect()->route('admin.holidays.index')->with('status', 'Tanggal libur berhasil dihapus.');
    }
}
