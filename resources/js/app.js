import { Chart } from 'chart.js/auto';

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
