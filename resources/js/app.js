import { Chart } from 'chart.js/auto';

// Base da URL do app (respeita deploys em subpasta, ex.: /ancoraweb/public).
const appBaseUrl = (document.querySelector('meta[name="app-base-url"]')?.content ?? '').replace(/\/$/, '');

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register(`${appBaseUrl}/sw.js`, { scope: `${appBaseUrl}/` }).catch((error) => {
            console.error('Falha ao registrar o service worker do Âncora:', error);
        });
    });
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);

    return Uint8Array.from([...rawData].map((char) => char.charCodeAt(0)));
}

document.addEventListener('alpine:init', () => {
    Alpine.data('ancoraPushSubscription', () => ({
        supported: 'serviceWorker' in navigator && 'PushManager' in window,
        permission: typeof Notification !== 'undefined' ? Notification.permission : 'denied',
        subscribed: false,
        loading: false,

        async init() {
            if (!this.supported) {
                return;
            }

            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            this.subscribed = subscription !== null;
        },

        async subscribe() {
            this.loading = true;

            try {
                const permission = await Notification.requestPermission();
                this.permission = permission;

                if (permission !== 'granted') {
                    return;
                }

                const registration = await navigator.serviceWorker.ready;
                const vapidKey = document.querySelector('meta[name="vapid-public-key"]').content;

                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(vapidKey),
                });

                await fetch('/push-subscriptions', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(subscription.toJSON()),
                });

                this.subscribed = true;
            } finally {
                this.loading = false;
            }
        },

        async unsubscribe() {
            this.loading = true;

            try {
                const registration = await navigator.serviceWorker.ready;
                const subscription = await registration.pushManager.getSubscription();

                if (subscription) {
                    await fetch('/push-subscriptions', {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ endpoint: subscription.endpoint }),
                    });

                    await subscription.unsubscribe();
                }

                this.subscribed = false;
            } finally {
                this.loading = false;
            }
        },
    }));
});

document.addEventListener('alpine:init', () => {
    Alpine.data('ancoraDashboard', (initial) => ({
        charts: {},

        init() {
            this.charts.evolution = new Chart(this.$refs.evolutionCanvas, this.evolutionConfig(initial));
            this.charts.feelings = new Chart(this.$refs.feelingsCanvas, this.feelingsConfig(initial));
            this.charts.distribution = new Chart(this.$refs.distributionCanvas, this.distributionConfig(initial));

            // O Livewire empacota parâmetros nomeados do dispatch() num objeto
            // ({ data: {...} }), não manda o valor direto nem envolvido num array.
            Livewire.on('dashboard-updated', ({ data }) => {
                this.updateCharts(data);
            });
        },

        updateCharts(data) {
            // Destruir e recriar em vez de mutar: trocar o array de datasets
            // inteiro (mudando o número de pontos/rótulos) deixa o estado
            // interno do Chart.js (legenda/layout) inconsistente ao chamar
            // update() — reconstruir do zero é simples e o volume de dados
            // aqui é pequeno o suficiente pra isso não pesar.
            this.charts.evolution.destroy();
            this.charts.evolution = new Chart(this.$refs.evolutionCanvas, this.evolutionConfig(data));

            this.charts.feelings.destroy();
            this.charts.feelings = new Chart(this.$refs.feelingsCanvas, this.feelingsConfig(data));

            this.charts.distribution.destroy();
            this.charts.distribution = new Chart(this.$refs.distributionCanvas, this.distributionConfig(data));
        },

        evolutionConfig(data) {
            return {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: data.moodSeries.map((series) => ({
                        label: series.label,
                        data: series.data,
                        borderColor: series.color,
                        backgroundColor: series.color,
                        borderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 4,
                        tension: 0.3,
                    })),
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
                },
            };
        },

        feelingsConfig(data) {
            return {
                type: 'bar',
                data: {
                    labels: data.feelingLabels,
                    datasets: [{ label: 'Ocorrências', data: data.feelingCounts, backgroundColor: data.feelingColors }],
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } },
                },
            };
        },

        distributionConfig(data) {
            return {
                type: 'doughnut',
                data: {
                    labels: data.distributionLabels,
                    datasets: [{ data: data.distribution, backgroundColor: data.distributionColors }],
                },
                options: { responsive: true },
            };
        },
    }));
});

document.addEventListener('alpine:init', () => {
    // Visão compartilhada com a psicóloga (docs/13): o servidor já barra
    // qualquer nova requisição depois que o link expira, é revogado ou o
    // prazo do PIN acaba, mas a página que já está na tela
    // continuaria visível. Aqui ela é apagada quando o prazo acaba (com uma
    // contagem discreta no cabeçalho) e sempre que o servidor disser que o
    // acesso acabou.
    Alpine.data('ancoraShareGuard', ({ statusUrl, lockUrl, expiresInSeconds, pinRemainingSeconds, pinValidityMinutes, expiresAtLabel }) => ({
        now: Date.now(),
        expiresAt: Date.now() + expiresInSeconds * 1000,
        // Prazo fixo contado de quando o PIN foi digitado: navegar ou recarregar não prorroga.
        pinEndsAt: Date.now() + pinRemainingSeconds * 1000,
        checking: false,
        locked: false,

        get closesAt() {
            return Math.min(this.expiresAt, this.pinEndsAt);
        },

        get remainingLabel() {
            const total = Math.max(0, Math.ceil((this.closesAt - this.now) / 1000));
            const hours = Math.floor(total / 3600);
            const minutes = Math.floor((total % 3600) / 60);
            const seconds = String(total % 60).padStart(2, '0');

            return hours > 0 ? `${hours}:${String(minutes).padStart(2, '0')}:${seconds}` : `${minutes}:${seconds}`;
        },

        get remainingTitle() {
            return this.expiresAt <= this.pinEndsAt
                ? `O link expira em ${expiresAtLabel}.`
                : `Por segurança, o PIN vale por ${pinValidityMinutes} minutos. Depois disso, será pedido novamente.`;
        },

        init() {
            this.tick = setInterval(() => {
                this.now = Date.now();

                // Antes de fechar, confirma com o servidor: o PIN pode ter sido
                // digitado de novo em outra aba.
                if (this.now >= this.closesAt) {
                    this.check();
                }
            }, 1000);
            this.poll = setInterval(() => this.check(), 60000);

            this.onVisible = () => document.visibilityState === 'visible' && this.check();
            this.onPageShow = (event) => event.persisted && this.check();
            document.addEventListener('visibilitychange', this.onVisible);
            window.addEventListener('pageshow', this.onPageShow);
        },

        destroy() {
            clearInterval(this.tick);
            clearInterval(this.poll);
            document.removeEventListener('visibilitychange', this.onVisible);
            window.removeEventListener('pageshow', this.onPageShow);
        },

        async check() {
            if (this.checking || this.locked) {
                return;
            }

            this.checking = true;

            try {
                const response = await fetch(statusUrl, {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                    credentials: 'same-origin',
                });

                // 429 ou erro do servidor: mantém a página, a menos que o prazo
                // local já tenha acabado.
                if (!response.ok) {
                    return this.lockIfPastDeadline();
                }

                const status = await response.json();

                if (!status.valid) {
                    return this.lock();
                }

                this.expiresAt = Date.now() + status.remaining_seconds * 1000;
                this.pinEndsAt = Date.now() + status.pin_remaining_seconds * 1000;
            } catch {
                // Sem conexão: idem.
                this.lockIfPastDeadline();
            } finally {
                this.checking = false;
            }
        },

        lockIfPastDeadline() {
            if (Date.now() >= this.closesAt) {
                this.lock();
            }
        },

        lock() {
            if (this.locked) {
                return;
            }

            this.locked = true;
            this.destroy();
            document.body.innerHTML = '';
            window.location.replace(lockUrl);
        },
    }));
});
