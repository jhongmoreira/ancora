@use('App\Services\Insights\InsightSchema')
@use('App\Services\Stats\EmotionStats')

@php
    $report = $this->report;
    $content = $report?->content;
    $stats = $report?->stats;
    $evidence = $this->evidence;
@endphp

<div class="space-y-6">
    {{-- Barra de ações (só o paciente gera) --}}
    @unless ($readOnly)
        <div class="bg-white shadow-sm sm:rounded-lg px-4 py-3 flex flex-wrap items-end gap-3">
            <div class="w-full sm:w-auto">
                <label for="insights-period" class="block text-xs font-medium text-gray-500 mb-1">Período</label>
                <select id="insights-period" wire:model.live="period" class="w-full sm:w-auto text-sm py-1.5 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach (\App\Services\Insights\InsightDataBuilder::PERIODS as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-1 sm:flex-none gap-3">
                <div class="flex-1">
                    <label for="insights-from" class="block text-xs font-medium text-gray-500 mb-1">De</label>
                    <input id="insights-from" type="date" wire:model.live="from" max="{{ today()->toDateString() }}" class="w-full text-sm py-1.5 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                </div>
                <div class="flex-1">
                    <label for="insights-to" class="block text-xs font-medium text-gray-500 mb-1">Até</label>
                    <input id="insights-to" type="date" wire:model.live="to" max="{{ today()->toDateString() }}" class="w-full text-sm py-1.5 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                </div>
            </div>

            <div class="flex w-full sm:w-auto sm:ms-auto" x-data>
                @if ($patient->hasAiConsent())
                    <button
                        type="button"
                        wire:click="generate"
                        wire:loading.attr="disabled"
                        wire:target="generate,acceptConsent"
                        class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 h-9 px-4 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 disabled:opacity-60"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />
                        </svg>
                        Gerar relatório
                    </button>
                @else
                    <button
                        type="button"
                        x-on:click="$dispatch('open-modal', 'insights-consent')"
                        class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 h-9 px-4 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
                    >
                        Gerar relatório
                    </button>
                @endif
            </div>
        </div>

        @if ($message)
            <div class="rounded-lg bg-amber-50 ring-1 ring-inset ring-amber-200 px-4 py-3 text-sm text-amber-800" role="status">{{ $message }}</div>
        @endif

        @if ($this->lastFailure)
            <div class="rounded-lg bg-red-50 ring-1 ring-inset ring-red-200 px-4 py-3 text-sm text-red-800" role="alert">
                <span class="font-medium">Não foi possível gerar o último relatório.</span> {{ $this->lastFailure->error }}
            </div>
        @endif

        {{-- Carregamento: a IA leva de 10 a 30 segundos --}}
        <div
            wire:loading.flex
            wire:target="generate,acceptConsent"
            class="fixed inset-0 z-50 items-center justify-center bg-gray-900/40 backdrop-blur-sm px-4"
            x-data="{ steps: ['Reunindo seus registros do período…', 'Removendo nomes e dados pessoais…', 'Analisando padrões e pensamentos…', 'Organizando o relatório…'], i: 0 }"
            x-init="setInterval(() => i = Math.min(i + 1, steps.length - 1), 5000)"
        >
            <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-sm text-center">
                <svg class="w-10 h-10 mx-auto text-indigo-600 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
                    <path class="opacity-90" fill="currentColor" d="M12 2a10 10 0 0 1 10 10h-3a7 7 0 0 0-7-7V2Z" />
                </svg>
                <p class="mt-4 font-medium text-gray-800" x-text="steps[i]"></p>
                <p class="mt-1 text-xs text-gray-500">Isso pode levar até 30 segundos.</p>
            </div>
        </div>

        {{-- Consentimento, antes do primeiro relatório --}}
        <x-modal name="insights-consent" maxWidth="lg" focusable>
            <div class="p-6">
                <h2 class="text-lg font-semibold text-gray-900">Antes de usar a IA</h2>
                <div class="mt-3 space-y-3 text-sm text-gray-600">
                    <p>O relatório é gerado pelo <span class="font-medium text-gray-800">Gemini, do Google</span>, a partir dos seus registros do período que você escolher.</p>
                    <ul class="list-disc ps-5 space-y-1.5">
                        <li><span class="font-medium text-gray-800">O que é enviado:</span> os textos dos registros e estatísticas do período. Antes do envio, removemos seu nome, o da sua psicóloga, e-mails, telefones, documentos e prováveis nomes próprios. A remoção é automática e pode deixar passar algo.</li>
                        <li><span class="font-medium text-gray-800">Serviço gratuito:</span> nessa modalidade, o Google pode usar o conteúdo enviado para melhorar os produtos dele, e pessoas podem revisá-lo.</li>
                        <li><span class="font-medium text-gray-800">Gerado por IA:</span> o relatório pode conter erros, não é diagnóstico e não substitui sua psicóloga. Use-o como ponto de partida para a conversa na sessão.</li>
                    </ul>
                    <p>Você pode retirar este consentimento quando quiser, na página "Meus dados".</p>
                </div>
                <div class="mt-6 flex flex-wrap justify-end gap-2">
                    <x-secondary-button x-on:click="$dispatch('close')">Agora não</x-secondary-button>
                    <x-primary-button wire:click="acceptConsent" x-on:click="$dispatch('close')">Concordo e gerar</x-primary-button>
                </div>
            </div>
        </x-modal>
    @endunless

    @if (! $report)
        <div class="bg-white shadow-sm sm:rounded-lg px-6 py-14 text-center">
            <svg class="w-12 h-12 mx-auto text-indigo-200" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
            </svg>
            @if ($readOnly)
                <p class="mt-4 text-gray-600">O paciente ainda não gerou insights.</p>
            @else
                <p class="mt-4 font-medium text-gray-800">Descubra padrões nos seus registros</p>
                <p class="mt-1 text-sm text-gray-500 max-w-md mx-auto">A IA lê seus registros emocionais e de compulsões e organiza o que se repete: gatilhos, pensamentos, o que ajudou e temas para levar à sessão.</p>
            @endif
        </div>
    @else
        {{-- Aviso de IA + histórico --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="inline-flex items-center gap-1.5 text-xs text-gray-500">
                <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                </svg>
                Gerado por IA em {{ $report->created_at->translatedFormat('d/m/Y \à\s H:i') }} · {{ $report->periodLabel() }} ({{ $report->period_start->translatedFormat('d/m') }} a {{ $report->period_end->translatedFormat('d/m') }}). Pode conter erros — converse sobre ele na sessão.
            </p>

            @if ($this->reports->where('status', 'completed')->count() > 1)
                <label class="flex items-center gap-2 text-xs text-gray-500">
                    <span>Relatório</span>
                    <select x-data x-on:change="$wire.select(Number($event.target.value))" class="text-sm py-1.5 border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($this->reports->where('status', 'completed') as $option)
                            <option value="{{ $option->id }}" @selected($option->id === $report->id)>
                                {{ $option->period_start->format('d/m') }}–{{ $option->period_end->format('d/m') }} ({{ $option->periodLabel() }}) · gerado {{ $option->created_at->format('d/m H:i') }}
                            </option>
                        @endforeach
                    </select>
                </label>
            @endif
        </div>

        {{-- Alerta de risco (detectado pelo app, não pela IA) --}}
        @if ($report->risk_flag)
            <div class="rounded-lg bg-red-50 ring-1 ring-inset ring-red-200 p-4 sm:p-5" role="alert">
                <div class="flex gap-3">
                    <svg class="w-6 h-6 flex-shrink-0 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z" />
                    </svg>
                    <div class="text-sm text-red-900">
                        @if ($readOnly)
                            <p class="font-semibold">Atenção: registros com conteúdo de risco neste período</p>
                            <p class="mt-1">Alguns registros contêm expressões associadas a ideação suicida ou autolesão (detecção automática por palavras, pode haver falso positivo):</p>
                        @else
                            <p class="font-semibold">Percebemos que alguns registros falam de momentos muito difíceis.</p>
                            <p class="mt-1">Você não precisa passar por isso sozinho. Se estiver pensando em se machucar, ligue agora para o <a href="tel:188" class="font-semibold underline">CVV — 188</a> (gratuito, 24h) ou para o <a href="tel:192" class="font-semibold underline">SAMU — 192</a>, e fale com sua psicóloga.</p>
                        @endif
                        @if ($readOnly)
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($report->risk_record_ids as $recordId)
                                    @include('livewire.insights.partials.evidence-chip', ['id' => $recordId])
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- Números do período (calculados pelo app) --}}
        @php
            $e = $stats['emotion'];
            $dominant = collect($e['moods'])->sortByDesc('count')->first();
            $resisted = collect($stats['compulsions'])->sum('resisted');
            $gaveIn = collect($stats['compulsions'])->sum('gave_in');
            $tiles = array_filter([
                ['label' => 'Registros emocionais', 'value' => $e['total'], 'hint' => "em {$e['days_with_logs']} de {$e['total_days']} dias"],
                $e['total'] ? ['label' => 'Humor predominante', 'value' => $dominant['label'], 'hint' => round($dominant['count'] / $e['total'] * 100).'% dos registros'] : null,
                $e['avg_intensity'] ? ['label' => 'Intensidade média', 'value' => number_format($e['avg_intensity'], 1, ',', '').'/5', 'hint' => 'nos registros que informaram'] : null,
                $resisted + $gaveIn ? ['label' => 'Impulsos resistidos', 'value' => "{$resisted} de ".($resisted + $gaveIn), 'hint' => 'no mapa de compulsões'] : null,
            ]);
        @endphp
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            @foreach ($tiles as $tile)
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <p class="text-xs text-gray-500">{{ $tile['label'] }}</p>
                    <p class="mt-1 text-xl font-semibold text-gray-900">{{ $tile['value'] }}</p>
                    <p class="text-xs text-gray-400">{{ $tile['hint'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Visão geral --}}
        <div class="rounded-lg bg-gradient-to-br from-indigo-50 to-white ring-1 ring-inset ring-indigo-100 p-5 sm:p-6">
            <h3 class="text-xs font-semibold uppercase tracking-wider text-indigo-700">Visão geral do período</h3>
            <p class="mt-2 text-gray-800 leading-relaxed">{{ $content['visao_geral'] }}</p>
        </div>

        {{-- Ciclo mais frequente (modelo cognitivo) --}}
        @if (array_filter($content['ciclo']))
            <section class="bg-white shadow-sm sm:rounded-lg p-5 sm:p-6">
                <h3 class="text-sm font-semibold text-gray-800">Ciclo mais frequente</h3>
                <p class="text-xs text-gray-500">Como situação, pensamento, emoção e comportamento costumaram se encadear.</p>
                <ol class="mt-4 grid gap-3 md:grid-cols-4">
                    @foreach ([
                        'situacao' => ['Situação', 'bg-slate-50 ring-slate-200 text-slate-700'],
                        'pensamento' => ['Pensamento', 'bg-violet-50 ring-violet-200 text-violet-700'],
                        'emocao' => ['Emoção', 'bg-rose-50 ring-rose-200 text-rose-700'],
                        'comportamento' => ['Comportamento', 'bg-amber-50 ring-amber-200 text-amber-800'],
                    ] as $key => [$label, $style])
                        <li class="relative rounded-lg ring-1 ring-inset p-3 {{ $style }}">
                            <p class="text-[11px] font-semibold uppercase tracking-wider">{{ $loop->iteration }}. {{ $label }}</p>
                            <p class="mt-1 text-sm text-gray-800">{{ $content['ciclo'][$key] }}</p>
                            @unless ($loop->last)
                                <span class="hidden md:flex absolute -right-3 top-1/2 -translate-y-1/2 z-10 w-5 h-5 items-center justify-center rounded-full bg-white ring-1 ring-gray-200 text-gray-400 text-xs" aria-hidden="true">›</span>
                            @endunless
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Padrões --}}
            @if ($content['padroes'])
                <section class="bg-white shadow-sm sm:rounded-lg p-5 sm:p-6 lg:col-span-2">
                    <h3 class="text-sm font-semibold text-gray-800">Padrões identificados</h3>
                    <p class="text-xs text-gray-500">Hipóteses a partir dos registros. Toque em um código para ver o registro.</p>
                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        @foreach ($content['padroes'] as $pattern)
                            <article class="rounded-lg border border-gray-100 bg-gray-50/60 p-4">
                                <h4 class="font-medium text-gray-900">{{ $pattern['titulo'] }}</h4>
                                <p class="mt-1 text-sm text-gray-600">{{ $pattern['descricao'] }}</p>
                                @if ($pattern['evidencias'])
                                    <div class="mt-3 flex flex-wrap gap-1.5">
                                        @foreach ($pattern['evidencias'] as $recordId)
                                            @include('livewire.insights.partials.evidence-chip', ['id' => $recordId])
                                        @endforeach
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Pensamentos automáticos --}}
            @if ($content['pensamentos'])
                <section class="bg-white shadow-sm sm:rounded-lg p-5 sm:p-6 lg:col-span-2">
                    <h3 class="text-sm font-semibold text-gray-800">Pensamentos automáticos</h3>
                    <p class="text-xs text-gray-500">Com a possível distorção cognitiva de cada um, para explorar na reestruturação.</p>
                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        @foreach ($content['pensamentos'] as $thought)
                            <figure class="rounded-lg border-l-4 border-violet-300 bg-violet-50/50 p-4">
                                <blockquote class="text-gray-900 italic">“{{ $thought['citacao'] }}”</blockquote>
                                <figcaption class="mt-2 flex flex-wrap items-center gap-1.5">
                                    <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-violet-100 text-violet-800">{{ InsightSchema::DISTORTIONS[$thought['possivel_distorcao']] }}</span>
                                    @include('livewire.insights.partials.evidence-chip', ['id' => $thought['registro']])
                                </figcaption>
                                @if ($thought['explicacao'])
                                    <p class="mt-2 text-sm text-gray-600">{{ $thought['explicacao'] }}</p>
                                @endif
                            </figure>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Gatilhos --}}
            @if ($content['gatilhos'])
                <section class="bg-white shadow-sm sm:rounded-lg p-5 sm:p-6">
                    <h3 class="text-sm font-semibold text-gray-800">Gatilhos e situações de risco</h3>
                    <ul class="mt-3 divide-y divide-gray-100">
                        @foreach ($content['gatilhos'] as $trigger)
                            <li class="py-2.5">
                                <p class="text-sm text-gray-800">{{ $trigger['descricao'] }}</p>
                                @if ($trigger['emocoes'])
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @foreach ($trigger['emocoes'] as $emotion)
                                            <span class="text-xs px-2 py-0.5 rounded-full bg-rose-50 text-rose-700">{{ $emotion }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- Compulsões --}}
            @if (array_filter($content['compulsoes']))
                <section class="bg-white shadow-sm sm:rounded-lg p-5 sm:p-6">
                    <h3 class="text-sm font-semibold text-gray-800">Compulsões</h3>
                    <dl class="mt-3 space-y-3 text-sm">
                        @foreach (['ciclo' => 'Como o ciclo aparece', 'alto_risco' => 'Momentos de maior risco', 'o_que_ajudou' => 'O que ajudou a resistir'] as $key => $label)
                            @if ($content['compulsoes'][$key])
                                <div>
                                    <dt class="text-xs font-medium text-gray-500">{{ $label }}</dt>
                                    <dd class="mt-0.5 text-gray-800">{{ $content['compulsoes'][$key] }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>
                </section>
            @endif

            {{-- Estratégias --}}
            @if ($content['estrategias']['funcionaram'] || $content['estrategias']['pouco_efetivas'])
                <section class="bg-white shadow-sm sm:rounded-lg p-5 sm:p-6 lg:col-span-2">
                    <h3 class="text-sm font-semibold text-gray-800">Estratégias de enfrentamento</h3>
                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-medium text-green-700">Pareceram funcionar</p>
                            <ul class="mt-2 space-y-1.5">
                                @forelse ($content['estrategias']['funcionaram'] as $item)
                                    <li class="flex gap-2 text-sm text-gray-800"><span class="text-green-600" aria-hidden="true">✓</span>{{ $item }}</li>
                                @empty
                                    <li class="text-sm text-gray-400">—</li>
                                @endforelse
                            </ul>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500">Pareceram pouco efetivas</p>
                            <ul class="mt-2 space-y-1.5">
                                @forelse ($content['estrategias']['pouco_efetivas'] as $item)
                                    <li class="flex gap-2 text-sm text-gray-800"><span class="text-gray-400" aria-hidden="true">–</span>{{ $item }}</li>
                                @empty
                                    <li class="text-sm text-gray-400">—</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                </section>
            @endif

            {{-- Reconhecimentos --}}
            @if ($content['reconhecimentos'])
                <section class="rounded-lg bg-green-50 ring-1 ring-inset ring-green-200 p-5 sm:p-6">
                    <h3 class="text-sm font-semibold text-green-900">Reconhecimentos</h3>
                    <ul class="mt-3 space-y-2">
                        @foreach ($content['reconhecimentos'] as $item)
                            <li class="flex gap-2 text-sm text-green-900">
                                <svg class="w-4 h-4 mt-0.5 flex-shrink-0 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                                </svg>
                                {{ $item }}
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- Para levar à sessão --}}
            @if ($content['perguntas_sessao'])
                <section class="bg-white shadow-sm sm:rounded-lg p-5 sm:p-6">
                    <h3 class="text-sm font-semibold text-gray-800">Para levar à sessão</h3>
                    <ol class="mt-3 space-y-2">
                        @foreach ($content['perguntas_sessao'] as $question)
                            <li class="flex gap-3 text-sm text-gray-800">
                                <span class="flex-shrink-0 w-6 h-6 rounded-full bg-indigo-50 text-indigo-700 text-xs font-semibold flex items-center justify-center">{{ $loop->iteration }}</span>
                                <span class="pt-0.5">{{ $question }}</span>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endif
        </div>

        {{-- Limitações --}}
        @if ($content['limitacoes'])
            <div class="text-xs text-gray-500">
                <p class="font-medium text-gray-600">Limitações desta leitura</p>
                <ul class="mt-1 list-disc ps-5 space-y-0.5">
                    @foreach ($content['limitacoes'] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Transparência: o que foi enviado (só o paciente) --}}
        @unless ($readOnly)
            <details class="bg-white shadow-sm sm:rounded-lg p-4 text-sm group">
                <summary class="cursor-pointer text-gray-600 hover:text-gray-900 select-none">Ver o que foi enviado à IA</summary>
                <p class="mt-3 text-xs text-gray-500">Estes foram os dados enviados ao Gemini, já sem nomes e dados pessoais. Datas aparecem só como o dia dentro do período ("dia 1", "dia 2"…).</p>
                <pre class="mt-2 max-h-96 overflow-auto rounded-md bg-gray-50 p-3 text-xs text-gray-700 whitespace-pre-wrap break-words">{{ json_encode($report->payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
            </details>
        @endunless
    @endif
</div>
