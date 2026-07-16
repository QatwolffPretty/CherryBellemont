import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    const newsletter = document.querySelector('[data-newsletter-feature]');

    if (newsletter && ! window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        if (! ('IntersectionObserver' in window)) {
            newsletter.classList.add('newsletter-visible');
        } else {
            newsletter.classList.add('newsletter-hidden');

            const observer = new IntersectionObserver(([entry]) => {
                if (! entry.isIntersecting) {
                    return;
                }

                newsletter.classList.remove('newsletter-hidden');
                newsletter.classList.add('newsletter-visible');
                observer.disconnect();
            }, { threshold: 0.12 });

            observer.observe(newsletter);
        }
    }

    const chartData = document.getElementById('admin-dashboard-chart-data');
    const revenueCanvas = document.getElementById('admin-revenue-chart');
    const statusCanvas = document.getElementById('admin-status-chart');

    if (! chartData || ! revenueCanvas || ! statusCanvas) {
        return;
    }

    let payload;

    try {
        payload = JSON.parse(chartData.textContent || '{}');
    } catch {
        return;
    }

    void import('chart.js/auto').then(({ default: Chart }) => {
        const gold = '#C8A96B';
        const wine = '#5B1E2D';
        const muted = 'rgba(248, 244, 239, .55)';

    new Chart(revenueCanvas, {
        type: 'line',
        data: {
            labels: payload.revenue?.labels || [],
            datasets: [{
                label: 'Paid revenue',
                data: payload.revenue?.revenue || [],
                borderColor: gold,
                backgroundColor: 'rgba(200, 169, 107, .13)',
                fill: true,
                tension: 0.24,
                pointRadius: 3,
                pointHoverRadius: 4,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label(context) {
                            const count = payload.revenue?.order_counts?.[context.dataIndex] || 0;
                            return `RM ${Number(context.raw || 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} · ${count} paid order${count === 1 ? '' : 's'}`;
                        },
                    },
                },
            },
            scales: {
                x: { ticks: { color: muted }, grid: { color: 'rgba(248, 244, 239, .08)' } },
                y: { ticks: { color: muted, callback: (value) => `RM ${Number(value).toLocaleString('en-MY')}` }, grid: { color: 'rgba(248, 244, 239, .08)' } },
            },
        },
    });

        new Chart(statusCanvas, {
        type: 'bar',
        data: {
            labels: payload.statuses?.labels || [],
            datasets: [{
                label: 'Orders',
                data: payload.statuses?.values || [],
                backgroundColor: [gold, '#9D6D37', '#7A4633', '#6B3044', '#4A1023', wine],
                borderWidth: 0,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { color: muted }, grid: { display: false } },
                y: { beginAtZero: true, ticks: { color: muted, precision: 0 }, grid: { color: 'rgba(248, 244, 239, .08)' } },
            },
        },
        });
    }).catch(() => {});
});

document.addEventListener('DOMContentLoaded', () => {
    const chartData = document.getElementById('admin-reports-chart-data');

    if (! chartData) {
        return;
    }

    let payload;

    try {
        payload = JSON.parse(chartData.textContent || '{}');
    } catch {
        return;
    }

    void import('chart.js/auto').then(({ default: Chart }) => {
        const gold = '#C8A96B';
        const wine = '#5B1E2D';
        const muted = 'rgba(248, 244, 239, .55)';
        const grid = 'rgba(248, 244, 239, .08)';
        const canvas = (id) => document.getElementById(id);

        const lineChart = (id, labels, values, label, color = gold, currency = false) => {
            const target = canvas(id);

            if (! target) {
                return;
            }

            new Chart(target, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label,
                        data: values,
                        borderColor: color,
                        backgroundColor: 'rgba(200, 169, 107, .12)',
                        fill: true,
                        tension: 0.24,
                        pointRadius: 2,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: muted }, grid: { color: grid } },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: muted,
                                precision: currency ? undefined : 0,
                                callback: (value) => currency ? `RM ${Number(value).toLocaleString('en-MY')}` : value,
                            },
                            grid: { color: grid },
                        },
                    },
                },
            });
        };

        const barChart = (id, labels, values, label, colors, currency = false) => {
            const target = canvas(id);

            if (! target) {
                return;
            }

            new Chart(target, {
                type: 'bar',
                data: { labels, datasets: [{ label, data: values, backgroundColor: colors, borderWidth: 0 }] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: muted }, grid: { display: false } },
                        y: { beginAtZero: true, ticks: { color: muted, precision: currency ? undefined : 0, callback: (value) => currency ? `RM ${Number(value).toLocaleString('en-MY')}` : value }, grid: { color: grid } },
                    },
                },
            });
        };

        lineChart('admin-reports-revenue-chart', payload.revenue?.labels || [], payload.revenue?.revenue || [], 'Net paid revenue', gold, true);
        barChart('admin-reports-status-chart', payload.statuses?.labels || [], payload.statuses?.values || [], 'Orders', [gold, '#9D6D37', '#7A4633', '#6B3044', '#4A1023', wine]);
        barChart('admin-reports-payment-chart', payload.payments?.labels || [], payload.payments?.values || [], 'Paid revenue', [gold, wine], true);
        barChart('admin-reports-product-chart', payload.products?.labels || [], payload.products?.values || [], 'Units sold', [gold, '#9D6D37', '#7A4633', '#6B3044', wine]);
        lineChart('admin-reports-customer-chart', payload.customers?.labels || [], payload.customers?.values || [], 'New customers', '#B89246');
        lineChart('admin-reports-newsletter-chart', payload.newsletter?.labels || [], payload.newsletter?.values || [], 'New subscribers', '#B89246');
    }).catch(() => {});
});
