<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use App\Models\ReminderDispatchLog;
use App\Notifications\EmotionLogReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;

class DispatchReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:dispatch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envia notificações push para os lembretes de registro emocional que vencem agora';

    /**
     * Tolerância para cron atrasado (docs/10).
     */
    protected const WINDOW_MINUTES = 15;

    public function handle(): int
    {
        $now = now();

        // Filtra em PHP (não em SQL) para lidar corretamente com a janela de
        // tolerância cruzando a meia-noite (ex.: lembrete às 00:05, cron rodando
        // 23:58) — o volume é mínimo (poucos lembretes por paciente único).
        $reminders = Reminder::query()
            ->where('is_active', true)
            ->with('patient.user')
            ->get()
            ->filter(fn (Reminder $reminder) => $this->isDue($reminder, $now));

        $sent = 0;

        foreach ($reminders as $reminder) {
            $alreadySent = ReminderDispatchLog::where('reminder_id', $reminder->id)
                ->whereDate('sent_date', $now->toDateString())
                ->exists();

            if ($alreadySent) {
                continue;
            }

            try {
                ReminderDispatchLog::create([
                    'reminder_id' => $reminder->id,
                    'sent_date' => $now->toDateString(),
                    'sent_at' => $now,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Outra execução concorrente já registrou o envio de hoje.
                continue;
            }

            $user = $reminder->patient?->user;

            if ($user) {
                $user->notify(new EmotionLogReminderNotification($reminder));
                $sent++;
            }
        }

        $this->info("Lembretes verificados: {$reminders->count()}. Notificações enviadas: {$sent}.");

        return self::SUCCESS;
    }

    protected function isDue(Reminder $reminder, \Carbon\Carbon $now): bool
    {
        $scheduledToday = $now->copy()->setTimeFromTimeString((string) $reminder->time);
        $windowStart = $now->copy()->subMinutes(self::WINDOW_MINUTES);

        if ($scheduledToday->between($windowStart, $now)) {
            return true;
        }

        // Cruza a meia-noite: o horário agendado "de ontem" ainda cai na janela.
        $scheduledYesterday = $scheduledToday->copy()->subDay();

        return $scheduledYesterday->between($windowStart, $now);
    }
}
