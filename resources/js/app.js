import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-sidebar-group]').forEach((group) => {
        const summary = group.querySelector(':scope > summary');

        if (! summary) {
            return;
        }

        summary.setAttribute('aria-expanded', group.open ? 'true' : 'false');
        group.addEventListener('toggle', () => summary.setAttribute('aria-expanded', group.open ? 'true' : 'false'));
    });

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
    const overviewData = document.getElementById('accounting-chart-data');
    const salesData = document.getElementById('accounting-sales-summary-chart-data');

    if (! overviewData && ! salesData) return;

    const parse = (node) => {
        try { return JSON.parse(node?.textContent || '{}'); } catch { return {}; }
    };
    const line = (Chart, id, labels, data, label, colour = '#C8A96B') => {
        const canvas = document.getElementById(id);
        if (! canvas) return;
        new Chart(canvas, { type: 'line', data: { labels, datasets: [{ label, data, borderColor: colour, backgroundColor: 'rgba(200, 169, 107, .12)', fill: true, tension: .24, pointRadius: 2 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { ticks: { color: 'rgba(248,244,239,.6)' }, grid: { color: 'rgba(248,244,239,.08)' } }, y: { beginAtZero: true, ticks: { color: 'rgba(248,244,239,.6)', callback: (value) => `RM ${Number(value).toLocaleString('en-MY')}` }, grid: { color: 'rgba(248,244,239,.08)' } } } } });
    };
    const bar = (Chart, id, labels, data, label, colour = '#5B1E2D') => {
        const canvas = document.getElementById(id);
        if (! canvas) return;
        new Chart(canvas, { type: 'bar', data: { labels, datasets: [{ label, data, backgroundColor: colour, borderWidth: 0 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { ticks: { color: 'rgba(248,244,239,.6)' }, grid: { display: false } }, y: { beginAtZero: true, ticks: { color: 'rgba(248,244,239,.6)' }, grid: { color: 'rgba(248,244,239,.08)' } } } } });
    };

    void import('chart.js/auto').then(({ default: Chart }) => {
        if (overviewData) {
            const payload = parse(overviewData);
            line(Chart, 'accounting-sales-chart', payload.labels || [], (payload.sales || []).map((value) => value / 100), 'Gross sales');
            line(Chart, 'accounting-expenses-chart', payload.labels || [], (payload.expenses || []).map((value) => value / 100), 'Expenses', '#B89246');
        }
        if (salesData) {
            const payload = parse(salesData);
            line(Chart, 'accounting-gross-sales-chart', payload.labels || [], (payload.gross || []).map((value) => value / 100), 'Gross sales');
            bar(Chart, 'accounting-refunds-chart', payload.labels || [], payload.orders || [], 'Paid orders', '#B89246');
        }
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

document.addEventListener('DOMContentLoaded', () => {
    const data = document.getElementById('accounting-cash-flow-chart-data');

    if (! data) {
        return;
    }

    let payload;

    try {
        payload = JSON.parse(data.textContent || '{}');
    } catch {
        return;
    }

    void import('chart.js/auto').then(({ default: Chart }) => {
        const common = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { labels: { color: 'rgba(248, 244, 239, .72)' } } },
            scales: {
                x: { ticks: { color: 'rgba(248, 244, 239, .6)' }, grid: { color: 'rgba(248, 244, 239, .08)' } },
                y: { ticks: { color: 'rgba(248, 244, 239, .6)', callback: (value) => `RM ${Number(value).toLocaleString('en-MY')}` }, grid: { color: 'rgba(248, 244, 239, .08)' } },
            },
        };
        const labels = payload.labels || [];
        const inOut = document.getElementById('accounting-cash-flow-in-out-chart');
        if (inOut) new Chart(inOut, { type: 'bar', data: { labels, datasets: [{ label: 'Cash In', data: (payload.inflow || []).map((value) => value / 100), backgroundColor: '#C8A96B' }, { label: 'Cash Out', data: (payload.outflow || []).map((value) => value / 100), backgroundColor: '#5B1E2D' }] }, options: common });
        const net = document.getElementById('accounting-cash-flow-net-chart');
        if (net) new Chart(net, { type: 'line', data: { labels, datasets: [{ label: 'Net Cash Movement', data: (payload.net || []).map((value) => value / 100), borderColor: '#C8A96B', backgroundColor: 'rgba(200,169,107,.14)', fill: true, tension: .24, pointRadius: 2 }] }, options: common });
        const closing = document.getElementById('accounting-cash-flow-closing-chart');
        if (closing) new Chart(closing, { type: 'line', data: { labels, datasets: [{ label: 'Closing Cash', data: (payload.closing || []).map((value) => value / 100), borderColor: '#B89246', backgroundColor: 'rgba(184,146,70,.14)', fill: true, tension: .24, pointRadius: 2 }] }, options: common });
        const classification = document.getElementById('accounting-cash-flow-classification-chart');
        if (classification) new Chart(classification, { type: 'bar', data: { labels: payload.classifications?.labels || [], datasets: [{ label: 'Net Cash Flow', data: (payload.classifications?.values || []).map((value) => value / 100), backgroundColor: ['#C8A96B', '#9D6D37', '#5B1E2D'] }] }, options: common });
        const outflow = document.getElementById('accounting-cash-flow-outflow-chart');
        if (outflow) {
            const rows = payload.outflow_categories || {};
            new Chart(outflow, { type: 'bar', data: { labels: Object.keys(rows), datasets: [{ label: 'Cash Outflow', data: Object.values(rows).map((value) => value / 100), backgroundColor: '#5B1E2D' }] }, options: common });
        }
    }).catch(() => {});
});

document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-account-form]');
    const data = document.getElementById('account-form-data');

    if (! form || ! data) {
        return;
    }

    let payload;

    try {
        payload = JSON.parse(data.textContent || '{}');
    } catch {
        return;
    }

    const type = form.querySelector('[data-account-type]');
    const subtype = form.querySelector('[data-account-subtype]');
    const normalBalance = form.querySelector('[data-normal-balance]');
    const parent = form.querySelector('[data-account-parent]');
    const warning = form.querySelector('[data-contra-warning]');

    if (! type || ! subtype || ! normalBalance || ! parent) {
        return;
    }

    const filterChoices = (select, selectedType) => {
        [...select.options].forEach((option) => {
            if (! option.dataset.type) {
                return;
            }

            const available = option.dataset.type === selectedType;
            option.hidden = ! available;
            option.disabled = ! available;
        });
    };

    const sync = (preserveBalance = false) => {
        const selectedType = type.value;
        filterChoices(subtype, selectedType);
        filterChoices(parent, selectedType);

        if (subtype.selectedOptions[0]?.disabled) {
            subtype.value = '';
        }
        if (parent.selectedOptions[0]?.disabled) {
            parent.value = '';
        }

        const isContra = (payload.contraSubtypes || []).includes(subtype.value);
        const suggested = isContra ? 'debit' : (payload.defaults || {})[selectedType];

        if (! preserveBalance || isContra) {
            normalBalance.value = suggested || normalBalance.value;
        }

        warning?.classList.toggle('hidden', ! isContra);
    };

    type.addEventListener('change', () => sync(false));
    subtype.addEventListener('change', () => sync(false));
    sync(true);
});

document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-journal-form]');

    if (! form) {
        return;
    }

    const list = form.querySelector('[data-journal-lines-list]');
    const template = form.querySelector('[data-journal-line-template]');
    const add = form.querySelector('[data-add-journal-line]');
    const debitTotal = form.querySelector('[data-journal-debit-total]');
    const creditTotal = form.querySelector('[data-journal-credit-total]');
    const balanceMessage = form.querySelector('[data-journal-balance-message]');

    if (! list || ! template || ! add || ! debitTotal || ! creditTotal || ! balanceMessage) {
        return;
    }

    let nextIndex = list.querySelectorAll('[data-journal-line]').length;
    const money = (value) => new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR' }).format(value);
    const number = (field) => Math.max(0, Number.parseFloat(field?.value || '0') || 0);

    const updateTotals = () => {
        const debits = [...list.querySelectorAll('[data-journal-debit]')].reduce((total, field) => total + number(field), 0);
        const credits = [...list.querySelectorAll('[data-journal-credit]')].reduce((total, field) => total + number(field), 0);
        const balanced = debits > 0 && Math.abs(debits - credits) < 0.005;

        debitTotal.textContent = money(debits);
        creditTotal.textContent = money(credits);
        balanceMessage.textContent = balanced ? 'Balanced and ready to save.' : 'Debits and credits must balance.';
        balanceMessage.classList.toggle('text-gold', balanced);
        balanceMessage.classList.toggle('text-red-200', ! balanced);
    };

    add.addEventListener('click', () => {
        const fragment = template.content.cloneNode(true);
        fragment.querySelectorAll('[name]').forEach((field) => {
            field.name = field.name.replaceAll('__INDEX__', String(nextIndex));
        });
        nextIndex += 1;
        list.appendChild(fragment);
        updateTotals();
    });

    list.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-journal-line]');

        if (! button) {
            return;
        }

        const line = button.closest('[data-journal-line]');
        if (list.querySelectorAll('[data-journal-line]').length <= 2) {
            balanceMessage.textContent = 'A journal entry needs at least two lines.';
            balanceMessage.classList.add('text-red-200');
            return;
        }

        line?.remove();
        updateTotals();
    });

    list.addEventListener('input', (event) => {
        const field = event.target;
        const line = field.closest('[data-journal-line]');

        if (field.matches('[data-journal-debit]') && number(field) > 0) {
            const credit = line?.querySelector('[data-journal-credit]');
            if (credit) credit.value = '';
        }
        if (field.matches('[data-journal-credit]') && number(field) > 0) {
            const debit = line?.querySelector('[data-journal-debit]');
            if (debit) debit.value = '';
        }

        updateTotals();
    });

    updateTotals();
});
