<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11pt; color: #1f2937; }
        header { margin-bottom: 20px; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px; }
        header h1 { font-size: 16pt; margin: 0 0 4px; }
        header p { margin: 2px 0; font-size: 10pt; color: #4b5563; }
        .log { margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid #e5e7eb; page-break-inside: avoid; }
        .log .meta { font-size: 9pt; color: #6b7280; margin-bottom: 4px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9pt; margin-right: 4px; }
        .badge-mood { background: #eef2ff; color: #4338ca; }
        .badge-feeling { background: #f3f4f6; color: #374151; }
        .log p { margin: 3px 0; }
        .log .label { font-weight: 600; }
        footer { position: fixed; bottom: -20px; left: 0; right: 0; font-size: 8pt; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <header>
        <h1>Âncora — Relatório de registros emocionais</h1>
        <p><span class="label">Paciente:</span> {{ $patient->full_name }} @if($patient->age) ({{ $patient->age }} anos) @endif</p>
        @if ($patient->professional)
            <p><span class="label">Psicóloga(o):</span> {{ $patient->professional->name }}</p>
        @endif
        <p><span class="label">Período:</span> {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</p>
        <p><span class="label">Gerado em:</span> {{ now()->format('d/m/Y H:i') }} — {{ $logs->count() }} registro(s)</p>
    </header>

    @forelse ($logs as $log)
        <div class="log">
            <div class="meta">{{ $log->occurred_at->format('d/m/Y H:i') }}</div>
            <div>
                <span class="badge badge-mood">{{ $log->moodCategory->label }}@if($log->intensity) · intensidade {{ $log->intensity }}/5 @endif</span>
                @foreach ($log->feelings as $feeling)
                    <span class="badge badge-feeling">{{ $feeling->name }}</span>
                @endforeach
            </div>
            <p><span class="label">Situação:</span> {{ $log->situation }}</p>
            <p><span class="label">Ação:</span> {{ $log->action }}</p>
            @if ($log->automatic_thought)
                <p><span class="label">Pensamento automático:</span> {{ $log->automatic_thought }}</p>
            @endif
        </div>
    @empty
        <p>Nenhum registro encontrado no período selecionado.</p>
    @endforelse

    <footer>Gerado pelo Âncora</footer>
</body>
</html>
