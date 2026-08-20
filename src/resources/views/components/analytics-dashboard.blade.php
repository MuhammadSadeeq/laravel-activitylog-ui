{{-- No x-init="init()": Alpine.data() already runs init() automatically, and
     calling it here as well registered every watcher and listener twice. --}}
<div x-data="analyticsData()" class="al-stack al-stack--lg">
    {{-- No second page heading: the toolbar above already says what this is,
         and "Activity over the selected period" restated the control beside it. --}}
    <div class="al-toolbar">
        <div class="al-toolbar__grow"></div>

        <div class="al-segmented" role="group" aria-label="Period">
            <template x-for="period in [
                { value: 'today', label: 'Today' },
                { value: '7', label: '7 days' },
                { value: '30', label: '30 days' },
                { value: '90', label: '90 days' },
                { value: 'custom', label: 'Custom' }
            ]" :key="period.value">
                <button type="button"
                        class="al-segmented__btn"
                        :aria-pressed="selectedPeriod === period.value ? 'true' : 'false'"
                        @click="selectedPeriod = period.value; if (period.value !== 'custom') loadAnalytics()"
                        x-text="period.label"></button>
            </template>
        </div>
    </div>

    <div class="al-card" x-show="selectedPeriod === 'custom'" x-cloak>
        <div class="al-card__body al-row al-row--wrap" style="gap:.75rem">
            <div class="al-field al-grow">
                <label class="al-label" for="al-analytics-from">From</label>
                <input id="al-analytics-from" type="date" class="al-input" x-model="customStartDate">
            </div>
            <div class="al-field al-grow">
                <label class="al-label" for="al-analytics-to">To</label>
                <input id="al-analytics-to" type="date" class="al-input" x-model="customEndDate">
            </div>
            <button type="button"
                    class="al-btn al-btn--primary"
                    style="align-self:flex-end"
                    :disabled="!customStartDate || !customEndDate"
                    @click="loadAnalytics()">
                Apply
            </button>
        </div>
    </div>

    <div class="al-stats">
        <div class="al-stat">
            <p class="al-stat__label">Total</p>
            <p class="al-stat__value" x-text="Number(stats.total || 0).toLocaleString()"></p>
            <p class="al-stat__note">in this period</p>
        </div>
        <div class="al-stat">
            <p class="al-stat__label">Today</p>
            <p class="al-stat__value" x-text="Number(stats.today || 0).toLocaleString()"></p>
            <p class="al-stat__note">since midnight</p>
        </div>
        <div class="al-stat">
            <p class="al-stat__label">This week</p>
            <p class="al-stat__value" x-text="Number(stats.activities_this_week || 0).toLocaleString()"></p>
            <p class="al-stat__note">last 7 days</p>
        </div>
        <div class="al-stat">
            <p class="al-stat__label">This month</p>
            <p class="al-stat__value" x-text="Number(stats.activities_this_month || 0).toLocaleString()"></p>
            <p class="al-stat__note">calendar month</p>
        </div>
    </div>

    <div class="al-card">
        <div class="al-card__header">
            <h3 class="al-card__title">Activity over time</h3>
        </div>
        <div class="al-card__body">
            {{-- Chart.js is fetched the first time this view is opened, not on
                 every page load. It is ~200KB that the table and timeline never
                 touch. --}}
            {{-- Only when there is something to plot. Keyed on chartReady alone
                 it drew an empty grid 340px tall over a period with no activity,
                 which reads as a broken chart rather than as a quiet week. --}}
            <div class="al-chart al-chart--tall" x-show="hasTrendData">
                <canvas x-ref="trendsCanvas" role="img" aria-label="Activity over time"></canvas>
            </div>
            <p x-show="chartError" x-cloak class="al-note al-note--warning" style="margin-top:.75rem">
                The chart library could not be loaded, so the graph is unavailable. The figures above and below are unaffected.
            </p>
            <div x-show="!loading && !hasTrendData" x-cloak class="al-empty">
                <p class="al-empty__title">Nothing recorded in this period</p>
                <p class="al-empty__body">Choose a wider range, or clear the filters.</p>
            </div>
        </div>
    </div>

    <div class="al-layout" style="grid-template-columns:repeat(auto-fit,minmax(min(20rem,100%),1fr))">
        <div class="al-card">
            <div class="al-card__header">
                <h3 class="al-card__title">By event</h3>
            </div>
            <div class="al-card__body">
                <div class="al-bars" x-show="eventTypes.length > 0">
                    <template x-for="type in eventTypes" :key="type.name">
                        <div>
                            <div class="al-bar__head">
                                <span class="al-truncate" x-text="type.name"></span>
                                <span class="al-muted tnum" x-text="Number(type.count).toLocaleString()"></span>
                            </div>
                            <div class="al-bar__track">
                                <div class="al-bar__fill"
                                     :data-event="window.ActivityTypeStyler.getEvent(type.name)"
                                     :style="`width:${type.percentage || 0}%`"></div>
                            </div>
                        </div>
                    </template>
                </div>
                <div x-show="!loading && eventTypes.length === 0" x-cloak class="al-empty">
                    <p class="al-empty__title">No events recorded</p>
                </div>
            </div>
        </div>

        <div class="al-card">
            <div class="al-card__header">
                <h3 class="al-card__title">Most active users</h3>
            </div>
            <div class="al-card__body">
                <div class="al-stack al-stack--sm" x-show="topUsers.length > 0">
                    <template x-for="user in topUsers" :key="user.id">
                        <div class="al-row">
                            <span class="al-avatar" aria-hidden="true" x-text="String(user.name ?? '?').charAt(0).toUpperCase()"></span>
                            <span class="al-grow al-truncate" x-text="user.name"></span>
                            <span class="al-muted tnum" x-text="Number(user.activity_count).toLocaleString()"></span>
                        </div>
                    </template>
                </div>
                <div x-show="!loading && topUsers.length === 0" x-cloak class="al-empty">
                    <p class="al-empty__title">No users recorded</p>
                </div>
            </div>
        </div>

        <div class="al-card">
            <div class="al-card__header">
                <h3 class="al-card__title">Most active subjects</h3>
            </div>
            <div class="al-card__body">
                <div class="al-stack al-stack--sm" x-show="popularModels.length > 0">
                    <template x-for="model in popularModels" :key="model.type">
                        <div class="al-row">
                            <span class="al-grow al-truncate" x-text="model.name"></span>
                            <span class="al-muted tnum" x-text="Number(model.activity_count).toLocaleString()"></span>
                        </div>
                    </template>
                </div>
                <div x-show="!loading && popularModels.length === 0" x-cloak class="al-empty">
                    <p class="al-empty__title">No subjects recorded</p>
                </div>
            </div>
        </div>
    </div>

    <div class="al-card">
        <div class="al-card__header">
            <h3 class="al-card__title">Daily breakdown</h3>
        </div>
        <div class="al-card__body">
            <div class="al-bars" x-show="timeline.length > 0">
                <template x-for="day in timeline" :key="day.date">
                    <div>
                        <div class="al-bar__head">
                            <span>
                                <span x-text="day.date"></span>
                                <span class="al-faint al-hide-sm" x-text="day.day_name"></span>
                            </span>
                            <span class="al-muted tnum" x-text="Number(day.count).toLocaleString()"></span>
                        </div>
                        <div class="al-bar__track">
                            <div class="al-bar__fill" :style="`width:${day.percentage || 0}%`"></div>
                        </div>
                    </div>
                </template>
            </div>
            <div x-show="!loading && timeline.length === 0" x-cloak class="al-empty">
                <p class="al-empty__title">Nothing recorded in this period</p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('analyticsData', () => ({
        stats: {},
        eventTypes: [],
        topUsers: [],
        timeline: [],
        popularModels: [],
        activityTrends: {},
        loading: true,
        selectedPeriod: 'today',
        customStartDate: '',
        customEndDate: '',
        chart: null,
        chartReady: false,
        chartError: false,

        /** Whether any series actually carries a non-zero count. */
        get hasTrendData() {
            const datasets = this.activityTrends?.datasets;

            if (!Array.isArray(datasets) || datasets.length === 0) {
                return false;
            }

            return datasets.some(dataset =>
                Array.isArray(dataset.data) && dataset.data.some(point => Number(point.count) > 0)
            );
        },
        // Filters coming from the shared filter panel, kept separate from this
        // component's own period selection.
        dashboardFilters: {},
        hasLoaded: false,

        init() {
            // This component is rendered on every page load, not just the
            // analytics view, so fetching here unconditionally meant a wasted
            // analytics request behind every table and timeline page.
            if (this.currentView === 'analytics') {
                this.loadAnalytics();
            }

            this.$watch('currentView', view => {
                if (view === 'analytics' && !this.hasLoaded) {
                    this.loadAnalytics();
                }
            });

            // The dashboard used to reach in here through the DOM to push
            // filters, matching on a component name that never existed. Listening
            // directly is both correct and less fragile.
            const onFilters = event => {
                this.dashboardFilters = event.detail || {};

                if (this.currentView === 'analytics') {
                    this.loadAnalytics();
                }
            };

            window.addEventListener('filter-changed', onFilters);
            window.addEventListener('filter-panel-ready', onFilters);
        },

        async loadAnalytics() {
            try {
                this.loading = true;
                let url = '{{ route("activitylog-ui.api.analytics") }}';
                let params = new URLSearchParams();

                // Filter-panel selections first, so analytics reflects the same
                // slice of the log as the table and timeline. Dates are excluded:
                // this component has its own period control, and the endpoint
                // ignores `period` whenever start_date/end_date are present, so
                // forwarding them silently made the period pills inert.
                Object.entries(this.dashboardFilters || {}).forEach(([key, value]) => {
                    if (key === 'start_date' || key === 'end_date' || key === 'date_preset') return;
                    if (value === null || value === undefined || value === '') return;
                    if (Array.isArray(value)) {
                        value.forEach(item => params.append(`${key}[]`, item));
                    } else {
                        params.append(key, value);
                    }
                });

                if (this.selectedPeriod === 'custom') {
                    if (this.customStartDate) params.append('start_date', this.customStartDate);
                    if (this.customEndDate) params.append('end_date', this.customEndDate);
                } else if (this.selectedPeriod === 'today') {
                    params.append('period', 'today');
                } else {
                    params.append('period', this.selectedPeriod);
                }

                const response = await fetch(`${url}?${params.toString()}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    }
                });

                const data = await window.ActivitylogUi.parseJsonResponse(response, 'Loading analytics dashboard');

                if (data.success) {
                    this.stats = {
                        total: data.data.total_activities,
                        today: data.data.activities_today,
                        activities_this_week: data.data.activities_this_week,
                        activities_this_month: data.data.activities_this_month
                    };

                    this.eventTypes = data.data.event_types;
                    this.topUsers = data.data.top_users;
                    this.timeline = data.data.timeline;
                    this.popularModels = data.data.popular_models;
                    this.activityTrends = data.data.activity_trends;

                    if (this.hasTrendData) {
                        this.renderTrendsChart();
                    }

                    // Only a success counts as loaded, so returning to the view
                    // after a transient failure retries instead of staying blank.
                    this.hasLoaded = true;
                }
            } catch (error) {
                console.error('Error loading analytics:', error);
                if (window.notify) {
                    window.notify.error('Error', 'Failed to load analytics data');
                }
            } finally {
                this.loading = false;
            }
        },

        /**
         * Fetches Chart.js the first time a chart is actually needed.
         *
         * It used to be a blocking <script> in the document head, so every
         * table and timeline page paid ~200KB for a library they never used.
         */
        loadChartLibrary() {
            if (window.Chart) {
                return Promise.resolve(window.Chart);
            }

            if (!window.__activitylogChartPromise) {
                window.__activitylogChartPromise = new Promise((resolve, reject) => {
                    const script = document.createElement('script');
                    script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4';
                    script.onload = () => resolve(window.Chart);
                    script.onerror = () => reject(new Error('Chart.js failed to load'));
                    document.head.appendChild(script);
                });
            }

            return window.__activitylogChartPromise;
        },

        async renderTrendsChart() {
            let ChartLib;

            try {
                ChartLib = await this.loadChartLibrary();
            } catch (error) {
                // The figures are all still on the page; only the graph is gone.
                this.chartError = true;
                return;
            }

            // The canvas lives behind x-show, so it may not be laid out yet.
            await this.$nextTick();

            const canvas = this.$refs.trendsCanvas;

            if (!canvas) {
                return;
            }

            if (this.chart) {
                this.chart.destroy();
            }

            const styles = getComputedStyle(document.documentElement);
            const ink = styles.getPropertyValue('--ink-muted').trim();
            const grid = styles.getPropertyValue('--border').trim();
            const series = [
                styles.getPropertyValue('--event-created').trim(),
                styles.getPropertyValue('--event-updated').trim(),
                styles.getPropertyValue('--event-deleted').trim(),
                styles.getPropertyValue('--event-restored').trim(),
                styles.getPropertyValue('--event-neutral').trim(),
            ];

            this.chartReady = true;
            this.chartError = false;

            this.chart = new ChartLib(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: this.activityTrends.dates,
                    // Series colours come from the stylesheet, so the chart
                    // matches the badges beside it and follows the theme.
                    datasets: (this.activityTrends.datasets || []).map((dataset, index) => ({
                        label: dataset.label,
                        data: dataset.data.map(point => point.count),
                        borderColor: series[index % series.length],
                        backgroundColor: 'transparent',
                        borderWidth: 1.75,
                        pointRadius: 0,
                        pointHoverRadius: 3,
                        tension: 0.25,
                    }))
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 240 },
                    interaction: { intersect: false, mode: 'index' },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { color: ink, boxWidth: 8, boxHeight: 8, usePointStyle: true, padding: 16 },
                        },
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: ink, maxRotation: 0, autoSkipPadding: 24 }, border: { color: grid } },
                        y: { beginAtZero: true, grid: { color: grid }, ticks: { color: ink, precision: 0 }, border: { display: false } },
                    },
                }
            });
        },

        // Cleanup method
        destroy() {
            if (this.chart) {
                this.chart.destroy();
                this.chart = null;
            }
        }
    }));
});
</script>
