import Chart from 'chart.js/auto';


function fmtIt(n) { return Number(n).toLocaleString('it-IT'); }

function fmtDate(s) {
    const [y, m, d] = s.split('-');
    return d + '/' + m + '/' + y;
}

// GA-style abbreviated number: 14000 -> "14K", 9420 -> "9,4K", 1250000 -> "1,25M"
function fmtCompact(n) {
    n = Number(n) || 0;
    if( n < 1000 )      return fmtIt(n);
    if( n < 1000000 ) {
        const v = n / 1000;
        return v.toLocaleString('it-IT', { maximumFractionDigits: v < 10 ? 1 : 0 }) + 'K';
    }
    const v = n / 1000000;
    return v.toLocaleString('it-IT', { maximumFractionDigits: v < 10 ? 2 : 1 }) + 'M';
}

function escapeHtml(s) {
    return String(s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}


function init() {
    const selector = document.getElementById('tli-stats-range-selector');
    if( !selector ) return; // not on the stats page (or stats not configured)

    const ajaxBaseUrl = selector.dataset.ajaxUrl;

    const chartConfigs = [
        {
            key: 'pageViews',
            canvasId: 'tli-chart-pageviews',
            cardId:   'tli-stats-card-pageviews',
            currentColor: '#1091ff',
            lastYearColor: '#9aa5ad'
        },
        {
            key: 'activeUsers',
            canvasId: 'tli-chart-activeusers',
            cardId:   'tli-stats-card-activeusers',
            currentColor: '#26a269',
            lastYearColor: '#9aa5ad'
        },
        {
            key: 'registeredUsers',
            canvasId: 'tli-chart-registeredusers',
            cardId:   'tli-stats-card-registeredusers',
            currentColor: '#1c9099',
            lastYearColor: '#9aa5ad',
            noLastYear: true,
            // for this card the "current" cell shows the cumulative total at the end of the period
            cardCurrentField: 'totalAtEnd',
            // cumulative series: don't force the y-axis to start at zero, otherwise small daily growth on a
            // ~9000-user baseline looks completely flat. Instead, anchor the bottom of the y-axis at
            // (firstValue - 5) so the rising line has a bit of context below it.
            beginAtZero: false,
            yMinOffsetFromFirst: -5,
            // The default `fill: true` fills from the line down to y=0; with yMin lifted high above zero,
            // that leaves only a tiny sliver at the bottom for early days, making the chart look "empty".
            // A plain line (no fill) reads better for a cumulative series.
            fill: false
        },
        {
            // Newsletter subscribers: same cumulative pattern as registered users
            key: 'newsletterSubscribers',
            canvasId: 'tli-chart-newsletter',
            cardId:   'tli-stats-card-newsletter',
            currentColor: '#e66100',
            lastYearColor: '#9aa5ad',
            noLastYear: true,
            cardCurrentField: 'totalAtEnd',
            beginAtZero: false,
            yMinOffsetFromFirst: -5,
            fill: false
        },
        {
            // Forum posts per day: regular line chart with last-year overlay
            key: 'forumPosts',
            canvasId: 'tli-chart-forumposts',
            cardId:   'tli-stats-card-forum',
            currentColor: '#9141ac',
            lastYearColor: '#9aa5ad'
        },
        {
            // Articles published per day: regular line chart with last-year overlay
            key: 'articlesPublished',
            canvasId: 'tli-chart-articlespublished',
            cardId:   'tli-stats-card-articlespublished',
            currentColor: '#7068b1',
            lastYearColor: '#9aa5ad'
        }
    ];

    const charts = {};
    const currentDataset = {};


    // Returns the y-axis min for the given cfg + dataset, or undefined for auto-scale
    function computeYMin(cfg, dataset) {
        if( cfg.yMinOffsetFromFirst === undefined || !dataset || dataset.length === 0 ) {
            return undefined;
        }
        const first = Number(dataset[0].current) || 0;
        return Math.max(0, first + cfg.yMinOffsetFromFirst);
    }


    function buildChartData(cfg, dataset) {
        const datasets = [{
            label: 'Periodo attuale',
            data: dataset.map(d => d.current),
            borderColor: cfg.currentColor,
            backgroundColor: cfg.currentColor + '22',
            borderWidth: 2.5,
            pointRadius: 2,
            pointHoverRadius: 5,
            tension: 0.25,
            fill: cfg.fill !== false
        }];

        if( !cfg.noLastYear ) {
            datasets.push({
                label: 'Stesso giorno, anno scorso',
                data: dataset.map(d => d.lastYear),
                borderColor: cfg.lastYearColor,
                backgroundColor: 'transparent',
                borderWidth: 1.5,
                borderDash: [5, 4],
                pointRadius: 1.5,
                pointHoverRadius: 4,
                tension: 0.25,
                fill: false
            });
        }

        return { labels: dataset.map(d => d.label), datasets };
    }


    function buildChart(cfg, dataset) {
        const ctx = document.getElementById(cfg.canvasId);
        if( !ctx ) return null;

        return new Chart(ctx, {
            type: 'line',
            data: buildChartData(cfg, dataset),
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 400 },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 18, padding: 14 } },
                    tooltip: {
                        callbacks: {
                            title: function(items) {
                                if( !items.length ) return '';
                                const idx = items[0].dataIndex;
                                const ds  = currentDataset[cfg.key];
                                return ds[idx].lastYearDate
                                    ? ds[idx].date + '  (vs ' + ds[idx].lastYearDate + ')'
                                    : ds[idx].date;
                            },
                            label: function(item) {
                                return ' ' + item.dataset.label + ': ' + fmtIt(item.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } },
                    y: {
                        beginAtZero: cfg.beginAtZero !== false,
                        min: computeYMin(cfg, dataset),
                        ticks: {
                            precision: 0,
                            callback: function(v) { return Number.isInteger(v) ? fmtIt(v) : null; }
                        }
                    }
                }
            }
        });
    }


    function renderTopPages(stats) {
        const grid = document.querySelector('[data-tli-toppages-grid]');
        if( !grid ) return; // section is staff-only and not rendered for this user

        const meta = document.querySelector('[data-tli-toppages-meta]');
        if( meta ) {
            meta.textContent =
                'Dal ' + fmtDate(stats.range.start) +
                ' al ' + fmtDate(stats.range.end);
        }

        const list  = stats.topPages || [];
        let empty   = document.querySelector('[data-tli-toppages-empty]');

        if( list.length === 0 ) {
            if( !empty ) {
                empty = document.createElement('div');
                empty.className = 'tli-stats-toppages-empty';
                empty.setAttribute('data-tli-toppages-empty', '');
                empty.textContent = 'Nessun dato disponibile per il periodo selezionato.';
                grid.parentNode.insertBefore(empty, grid);
            }
            empty.style.display = '';
            grid.style.display  = 'none';
            return;
        }

        if( empty ) empty.style.display = 'none';
        grid.style.display = '';

        const splitAt = Math.ceil(list.length / 2);
        const cols    = [list.slice(0, splitAt), list.slice(splitAt)];

        cols.forEach((col, colIdx) => {
            const tbody = document.querySelector('[data-tli-toppages-body="' + colIdx + '"]');
            if( !tbody ) return;

            const fromIdx = colIdx * splitAt;
            tbody.innerHTML = col.map((p, i) => {
                const displayTitle = p.displayTitle || p.title || p.path;
                const titleEsc     = escapeHtml(displayTitle);
                const pathEsc      = escapeHtml(p.path);
                const iconHtml     = p.iconClass
                    ? '<i class="fa-solid ' + escapeHtml(p.iconClass) + '" style="color: ' + escapeHtml(p.iconColor || '') + ';"></i> '
                    : '';
                return '' +
                    '<tr>' +
                        '<td class="tli-stats-toppages-rank">' + (fromIdx + i + 1) + '</td>' +
                        '<td>' +
                            '<span class="tli-stats-toppages-page-title">' +
                                '<a href="' + pathEsc + '" target="_blank" rel="noopener">' + iconHtml + titleEsc + '</a>' +
                            '</span>' +
                        '</td>' +
                        '<td class="tli-stats-toppages-views">' + fmtIt(p.views) + '</td>' +
                    '</tr>';
            }).join('');
        });
    }


    function renderTopTags(stats) {
        const grid = document.querySelector('[data-tli-toptags-grid]');
        if( !grid ) return; // section is staff-only and not rendered for this user

        const meta = document.querySelector('[data-tli-toptags-meta]');
        if( meta ) {
            meta.textContent =
                'Dal ' + fmtDate(stats.range.start) +
                ' al ' + fmtDate(stats.range.end);
        }

        const list  = stats.topTags || [];
        let empty   = document.querySelector('[data-tli-toptags-empty]');

        if( list.length === 0 ) {
            if( !empty ) {
                empty = document.createElement('div');
                empty.className = 'tli-stats-toppages-empty';
                empty.setAttribute('data-tli-toptags-empty', '');
                empty.textContent = 'Nessun dato disponibile per il periodo selezionato.';
                grid.parentNode.insertBefore(empty, grid);
            }
            empty.style.display = '';
            grid.style.display  = 'none';
            return;
        }

        if( empty ) empty.style.display = 'none';
        grid.style.display = '';

        const splitAt = Math.ceil(list.length / 2);
        const cols    = [list.slice(0, splitAt), list.slice(splitAt)];

        cols.forEach((col, colIdx) => {
            const tbody = document.querySelector('[data-tli-toptags-body="' + colIdx + '"]');
            if( !tbody ) return;

            const fromIdx = colIdx * splitAt;
            tbody.innerHTML = col.map((t, i) => {
                const titleStr = t.title && t.title.trim() !== '' ? t.title : t.path;
                const titleEsc = escapeHtml(titleStr);
                const pathEsc  = escapeHtml(t.path);
                return '' +
                    '<tr>' +
                        '<td class="tli-stats-toppages-rank">' + (fromIdx + i + 1) + '</td>' +
                        '<td>' +
                            '<span class="tli-stats-toppages-page-title">' +
                                '<a href="' + pathEsc + '" target="_blank" rel="noopener">' +
                                    '<i class="fa-solid fa-tag" style="color: #e5a50a;"></i> ' + titleEsc +
                                '</a>' +
                            '</span>' +
                        '</td>' +
                        '<td class="tli-stats-toppages-views">' + fmtIt(t.views) + '</td>' +
                    '</tr>';
            }).join('');
        });
    }


    function renderTopPosters(stats) {
        const list  = stats.topPosters || [];
        const grid  = document.querySelector('[data-tli-topposters-grid]');
        let empty   = document.querySelector('[data-tli-topposters-empty]');

        if( list.length === 0 ) {
            if( !empty ) {
                empty = document.createElement('div');
                empty.className = 'tli-stats-toppages-empty';
                empty.setAttribute('data-tli-topposters-empty', '');
                empty.textContent = 'Nessun dato disponibile per il periodo selezionato.';
                grid.parentNode.insertBefore(empty, grid);
            }
            empty.style.display = '';
            grid.style.display  = 'none';
            return;
        }

        if( empty ) empty.style.display = 'none';
        grid.style.display = '';

        const splitAt = Math.ceil(list.length / 2);
        const cols    = [list.slice(0, splitAt), list.slice(splitAt)];

        cols.forEach((col, colIdx) => {
            const tbody = document.querySelector('[data-tli-topposters-body="' + colIdx + '"]');
            if( !tbody ) return;

            const fromIdx = colIdx * splitAt;
            tbody.innerHTML = col.map((p, i) => {
                const usernameEsc = escapeHtml(p.username || ('User #' + p.userId));
                const userId      = parseInt(p.userId, 10) || 0;
                const profileUrl  = '/forum/memberlist.php?mode=viewprofile&u=' + userId;
                const rawColor    = (p.colour || '').trim();
                // Apply an inline color ONLY when the user has a custom phpBB colour.
                // Allow only hex strings (CSS-injection guard).
                const styleAttr   = /^[0-9a-fA-F]{3,8}$/.test(rawColor)
                    ? ' style="color: #' + rawColor + ';"'
                    : '';
                return '' +
                    '<tr>' +
                        '<td class="tli-stats-toppages-rank">' + (fromIdx + i + 1) + '</td>' +
                        '<td>' +
                            '<span class="tli-stats-toppages-page-title">' +
                                '<a href="' + profileUrl + '" target="_blank" rel="noopener"' + styleAttr + '>' +
                                    '<i class="fa-solid fa-user"></i> ' + usernameEsc +
                                '</a>' +
                            '</span>' +
                        '</td>' +
                        '<td class="tli-stats-toppages-views">' + fmtIt(p.posts) + '</td>' +
                    '</tr>';
            }).join('');
        });
    }


    function renderTopReferrers(stats) {
        const grid = document.querySelector('[data-tli-topreferrers-grid]');
        if( !grid ) return; // section is staff-only and not rendered for this user

        const meta = document.querySelector('[data-tli-topreferrers-meta]');
        if( meta ) {
            meta.textContent =
                'Dal ' + fmtDate(stats.range.start) +
                ' al ' + fmtDate(stats.range.end);
        }

        const list  = stats.topReferrers || [];
        let empty   = document.querySelector('[data-tli-topreferrers-empty]');

        if( list.length === 0 ) {
            if( !empty ) {
                empty = document.createElement('div');
                empty.className = 'tli-stats-toppages-empty';
                empty.setAttribute('data-tli-topreferrers-empty', '');
                empty.textContent = 'Nessun dato disponibile per il periodo selezionato.';
                grid.parentNode.insertBefore(empty, grid);
            }
            empty.style.display = '';
            grid.style.display  = 'none';
            return;
        }

        if( empty ) empty.style.display = 'none';
        grid.style.display = '';

        const splitAt = Math.ceil(list.length / 2);
        const cols    = [list.slice(0, splitAt), list.slice(splitAt)];

        cols.forEach((col, colIdx) => {
            const tbody = document.querySelector('[data-tli-topreferrers-body="' + colIdx + '"]');
            if( !tbody ) return;

            const fromIdx = colIdx * splitAt;
            tbody.innerHTML = col.map((r, i) => {
                const url        = r.url || '';
                const displayUrl = r.displayUrl || url;
                const isDirect   = url.trim() === '';
                const dispEsc    = escapeHtml(displayUrl);
                const cellHtml   = isDirect
                    ? '<span class="tli-stats-toppages-page-title tli-stats-direct">' +
                        '<i class="fa-solid fa-arrow-right" style="color: #9aa5ad;"></i> Traffico diretto' +
                      '</span>'
                    : '<span class="tli-stats-toppages-page-title tli-stats-referrer-link">' +
                        dispEsc +
                      '</span>';
                return '' +
                    '<tr>' +
                        '<td class="tli-stats-toppages-rank">' + (fromIdx + i + 1) + '</td>' +
                        '<td>' + cellHtml + '</td>' +
                        '<td class="tli-stats-toppages-views">' + fmtIt(r.views) + '</td>' +
                    '</tr>';
            }).join('');
        });
    }


    function applyStats(stats) {
        currentDataset.pageViews             = stats.pageViews;
        currentDataset.activeUsers           = stats.activeUsers;
        currentDataset.registeredUsers       = stats.registeredUsers;
        currentDataset.newsletterSubscribers = stats.newsletterSubscribers;
        currentDataset.forumPosts            = stats.forumPosts;
        currentDataset.articlesPublished     = stats.articlesPublished;

        const pvTot = stats.totals.pageViews.current;
        const auTot = stats.totals.activeUsers.current;
        const ruTot = stats.totals.registeredUsers.current;
        const apTot = stats.totals.articlesPublished.current;
        const days  = stats.range.days || 1;

        document.querySelector('[data-tli-headline="pageViews"]').textContent         = fmtCompact(pvTot);
        document.querySelector('[data-tli-headline="activeUsers"]').textContent       = fmtCompact(auTot);
        document.querySelector('[data-tli-headline="pageViewsAvg"]').textContent      = fmtCompact(Math.round(pvTot / days));
        document.querySelector('[data-tli-headline="activeUsersAvg"]').textContent    = fmtCompact(Math.round(auTot / days));
        document.querySelector('[data-tli-headline="registeredUsers"]').textContent   = fmtCompact(ruTot);
        document.querySelector('[data-tli-headline="articlesPublished"]').textContent = fmtCompact(apTot);

        document.querySelector('[data-tli-headline-range]').textContent =
            fmtDate(stats.range.start) + ' – ' + fmtDate(stats.range.end);

        renderTopPages(stats);
        renderTopTags(stats);
        renderTopReferrers(stats);
        renderTopPosters(stats);

        chartConfigs.forEach(cfg => {

            const card = document.getElementById(cfg.cardId);
            const meta = card.querySelector('[data-tli-stats-meta]');
            if( meta ) {
                meta.textContent = cfg.noLastYear
                    ? 'Dal ' + fmtDate(stats.range.start) + ' al ' + fmtDate(stats.range.end)
                    : 'Dal ' + fmtDate(stats.range.start) + ' al ' + fmtDate(stats.range.end) +
                      ' — confronto con ' + fmtDate(stats.range.lastYearStart) +
                      '–' + fmtDate(stats.range.lastYearEnd);
            }

            const totals    = stats.totals[cfg.key];
            const curField  = cfg.cardCurrentField || 'current';
            const cur       = totals[curField];
            const ly        = totals.lastYear;

            const curEl = card.querySelector('[data-tli-stats-current]');
            if( curEl ) curEl.textContent = fmtIt(cur);

            const lyEl = card.querySelector('[data-tli-stats-lastyear]');
            if( lyEl ) lyEl.textContent = fmtIt(ly || 0);

            const newInPeriodEl = card.querySelector('[data-tli-stats-new-in-period]');
            if( newInPeriodEl ) newInPeriodEl.textContent = fmtIt(totals.current || 0);

            const deltaWrap = card.querySelector('[data-tli-stats-delta-wrap]');
            const deltaEl   = card.querySelector('[data-tli-stats-delta]');

            if( deltaWrap && deltaEl ) {
                if( ly > 0 ) {
                    const pct  = (cur - ly) / ly * 100;
                    const trend = pct > 0.5 ? 'up' : (pct < -0.5 ? 'down' : 'flat');
                    const icon = trend === 'up' ? 'arrow-trend-up' : (trend === 'down' ? 'arrow-trend-down' : 'minus');
                    const sign = pct > 0 ? '+' : '';

                    deltaEl.classList.remove('tli-trend-up', 'tli-trend-down', 'tli-trend-flat');
                    deltaEl.classList.add('tli-trend-' + trend);
                    deltaEl.innerHTML =
                        '<i class="fa-solid fa-' + icon + '"></i> ' +
                        sign + pct.toLocaleString('it-IT', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%';
                    deltaWrap.style.display = '';

                } else {
                    deltaWrap.style.display = 'none';
                }
            }

            // First applyStats() call: build the chart. Subsequent calls: update its dataset in place.
            if( !charts[cfg.key] ) {
                charts[cfg.key] = buildChart(cfg, currentDataset[cfg.key]);
            } else {
                charts[cfg.key].data = buildChartData(cfg, currentDataset[cfg.key]);
                charts[cfg.key].options.scales.y.min = computeYMin(cfg, currentDataset[cfg.key]);
                charts[cfg.key].update();
            }
        });
    }


    function setLoading(loading) {
        selector.classList.toggle('tli-loading', loading);
        selector.querySelectorAll('.tli-stats-range-pill').forEach(b => {
            b.disabled = loading;
        });
        chartConfigs.forEach(cfg => {
            const card = document.getElementById(cfg.cardId);
            if( card ) card.classList.toggle('tli-loading', loading);
        });
        ['tli-stats-card-toppages', 'tli-stats-card-toptags', 'tli-stats-card-topreferrers'].forEach(id => {
            const card = document.getElementById(id);
            if( card ) card.classList.toggle('tli-loading', loading);
        });
    }


    function showError(message) {
        chartConfigs.forEach(cfg => {
            const meta = document.querySelector('#' + cfg.cardId + ' [data-tli-stats-meta]');
            if( meta ) {
                meta.innerHTML = '<span style="color: #cc1f1a;"><i class="fa-solid fa-circle-exclamation"></i> ' +
                                 (message || 'Errore nel recupero dei dati') + '</span>';
            }
        });
    }


    async function fetchAndApply(days) {
        setLoading(true);

        try {
            const url  = ajaxBaseUrl + '?days=' + encodeURIComponent(days);
            const res  = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();

            if( !res.ok ) {
                showError(json.error || ('HTTP ' + res.status));
                return;
            }

            applyStats(json);

        } catch(e) {
            showError(e.message || String(e));

        } finally {
            setLoading(false);
        }
    }


    function onPillClick(ev) {
        const btn = ev.currentTarget;
        if( btn.classList.contains('tli-active') || btn.disabled ) return;

        document.querySelectorAll('.tli-stats-range-pill').forEach(b => b.classList.remove('tli-active'));
        btn.classList.add('tli-active');

        fetchAndApply( parseInt(btn.dataset.days, 10) );
    }


    document.querySelectorAll('.tli-stats-range-pill').forEach(btn => {
        btn.addEventListener('click', onPillClick);
    });

    // No data is rendered server-side anymore: fire the default-range AJAX immediately so charts
    // and headlines populate as soon as the cached JSON returns.
    const activeBtn = selector.querySelector('.tli-stats-range-pill.tli-active');
    const initialDays = activeBtn ? parseInt(activeBtn.dataset.days, 10) : 30;
    fetchAndApply(initialDays);
}


if( document.readyState === 'loading' ) {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
