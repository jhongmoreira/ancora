<?php

namespace App\Http\Controllers;

use App\Exports\EmotionLogsExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    public function pdf(Request $request)
    {
        [$patient, $logs, $from, $to] = $this->resolveLogs($request);

        $pdf = Pdf::loadView('pdf.emotion-logs-report', [
            'patient' => $patient,
            'logs' => $logs,
            'from' => $from,
            'to' => $to,
        ])->setPaper('a4');

        return $pdf->download($this->filename($patient, $from, $to, 'pdf'));
    }

    public function excel(Request $request)
    {
        [$patient, $logs, $from, $to] = $this->resolveLogs($request);

        return Excel::download(new EmotionLogsExport($logs), $this->filename($patient, $from, $to, 'xlsx'));
    }

    protected function resolveLogs(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $patient = Auth::user()->patient;

        $logs = $patient->emotionLogs()
            ->with(['moodCategory', 'feelings'])
            ->whereDate('occurred_at', '>=', $validated['from'])
            ->whereDate('occurred_at', '<=', $validated['to'])
            ->orderBy('occurred_at')
            ->get();

        return [$patient, $logs, $validated['from'], $validated['to']];
    }

    protected function filename($patient, string $from, string $to, string $extension): string
    {
        $slug = Str::slug($patient->full_name ?: 'paciente');

        return "ancora-relatorio_{$slug}_{$from}_a_{$to}.{$extension}";
    }
}
