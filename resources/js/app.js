import { Chart } from 'chart.js/auto';

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch((error) => {
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

            Livewire.on('dashboard-updated', (event) => {
                const data = Array.isArray(event) ? event[0] : event;
                this.updateCharts(data);
            });
        },

        updateCharts(data) {
            this.charts.evolution.data.labels = data.labels;
            this.charts.evolution.data.datasets = data.moodSeries.map((series) => ({
                label: series.label,
                data: series.data,
                borderColor: series.color,
                backgroundColor: series.color,
                tension: 0.3,
            }));
            this.charts.evolution.update();

            this.charts.feelings.data.labels = data.feelingLabels;
            this.charts.feelings.data.datasets[0].data = data.feelingCounts;
            this.charts.feelings.update();

            this.charts.distribution.data.labels = data.distributionLabels;
            this.charts.distribution.data.datasets[0].data = data.distribution;
            this.charts.distribution.data.datasets[0].backgroundColor = data.distributionColors;
            this.charts.distribution.update();
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
                        tension: 0.3,
                    })),
                },
                options: {
                    responsive: true,
                    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
                },
            };
        },

        feelingsConfig(data) {
            return {
                type: 'bar',
                data: {
                    labels: data.feelingLabels,
                    datasets: [{ label: 'Ocorrências', data: data.feelingCounts, backgroundColor: '#6366f1' }],
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
