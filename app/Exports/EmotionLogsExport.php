<?php

namespace App\Exports;

use App\Models\EmotionLog;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EmotionLogsExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @param  Collection<int, EmotionLog>  $logs
     */
    public function __construct(protected Collection $logs) {}

    public function collection(): Collection
    {
        return $this->logs;
    }

    public function headings(): array
    {
        return ['Data', 'Hora', 'Humor', 'Intensidade', 'Sentimentos', 'Situação', 'Ação', 'Pensamento automático'];
    }

    public function map($log): array
    {
        return [
            $log->occurred_at->format('d/m/Y'),
            $log->occurred_at->format('H:i'),
            $log->moodCategory->label,
            $log->intensity ?? '',
            $log->feelings->pluck('name')->join(', '),
            $log->situation,
            $log->action,
            $log->automatic_thought ?? '',
        ];
    }
}
