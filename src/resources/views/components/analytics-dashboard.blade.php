<!-- Analytics Dashboard Component -->
<div x-data="analyticsData()"
     x-init="init()"
     class="space-y-6">

    <!-- Analytics Header -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 border border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Analytics Overview</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Activity insights and trends over time
                </p>
            </div>

            <!-- Time Period Selector -->
            <div class="flex flex-col space-y-2">
                <div class="flex flex-wrap gap-1 sm:gap-2">
                    <button @click="selectedPeriod = 'today'; loadAnalytics()"
                            :class="{
                                'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 border-blue-300 dark:border-blue-700': selectedPeriod === 'today',
                                'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600': selectedPeriod !== 'today'
                            }"
                            class="inline-flex items-center px-2 sm:px-2.5 py-0.5 rounded-full text-xs font-medium border transition-colors whitespace-nowrap">
                        Today
                    </button>
                    <button @click="selectedPeriod = '7'; loadAnalytics()"
                            :class="{
                                'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 border-blue-300 dark:border-blue-700': selectedPeriod === '7',
                                'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600': selectedPeriod !== '7'
                            }"
                            class="inline-flex items-center px-2 sm:px-2.5 py-0.5 rounded-full text-xs font-medium border transition-colors whitespace-nowrap">
                        7 Days
                    </button>
                    <button @click="selectedPeriod = '30'; loadAnalytics()"
                            :class="{
                                'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 border-blue-300 dark:border-blue-700': selectedPeriod === '30',
                                'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600': selectedPeriod !== '30'
                            }"
                            class="inline-flex items-center px-2 sm:px-2.5 py-0.5 rounded-full text-xs font-medium border transition-colors whitespace-nowrap">
                        30 Days
                    </button>
                    <button @click="selectedPeriod = '90'; loadAnalytics()"
                            :class="{
                                'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 border-blue-300 dark:border-blue-700': selectedPeriod === '90',
                                'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600': selectedPeriod !== '90'
                            }"
                            class="inline-flex items-center px-2 sm:px-2.5 py-0.5 rounded-full text-xs font-medium border transition-colors whitespace-nowrap">
                        90 Days
                    </button>
                    <button @click="selectedPeriod = 'custom'; showCustomDateRange = true"
                            :class="{
                                'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 border-blue-300 dark:border-blue-700': selectedPeriod === 'custom',
                                'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600': selectedPeriod !== 'custom'
                            }"
                            class="inline-flex items-center px-2 sm:px-2.5 py-0.5 rounded-full text-xs font-medium border transition-colors whitespace-nowrap">
                        Custom Range
                    </button>
                </div>

                <!-- Custom Date Range -->
                <div x-show="selectedPeriod === 'custom'"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 transform scale-95"
                     x-transition:enter-end="opacity-100 transform scale-100"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 transform scale-100"
                     x-transition:leave-end="opacity-0 transform scale-95"
                     class="grid grid-cols-2 gap-2">
                    <div>
                        <input type="date"
                               x-model="customStartDate"
                               @change="loadAnalytics()"
                               class="block w-full px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <input type="date"
                               x-model="customEndDate"
                               @change="loadAnalytics()"
                               class="block w-full px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 border border-gray-200 dark:border-gray-700 hover:shadow-md dark:hover:shadow-lg dark:hover:shadow-gray-900/20 transition-all duration-200">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 rounded-lg flex items-center justify-center shadow-sm">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Activities</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white" x-text="stats.total || '0'"></p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">All time</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 border border-gray-200 dark:border-gray-700 hover:shadow-md dark:hover:shadow-lg dark:hover:shadow-gray-900/20 transition-all duration-200">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-green-600 dark:from-green-400 dark:to-green-500 rounded-lg flex items-center justify-center shadow-sm">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Today's Activities</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white" x-text="stats.today || '0'"></p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Last 24 hours</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 border border-gray-200 dark:border-gray-700 hover:shadow-md dark:hover:shadow-lg dark:hover:shadow-gray-900/20 transition-all duration-200">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 rounded-lg flex items-center justify-center shadow-sm">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">This Week</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white" x-text="stats.activities_this_week || '0'"></p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Last 7 days</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 border border-gray-200 dark:border-gray-700 hover:shadow-md dark:hover:shadow-lg dark:hover:shadow-gray-900/20 transition-all duration-200">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-orange-600 dark:from-orange-400 dark:to-orange-500 rounded-lg flex items-center justify-center shadow-sm">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">This Month</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white" x-text="stats.activities_this_month || '0'"></p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Last 30 days</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Event Types Chart -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 border border-gray-200 dark:border-gray-700">
            <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Activity by Type</h4>
            <div class="space-y-3">
                <template x-for="type in eventTypes" :key="type.name">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-3 h-3 rounded-full mr-3"
                                 :class="`bg-${window.ActivityTypeStyler?.getColor(type.name) || 'gray'}-500`"></div>
                            <span class="text-sm font-medium text-gray-900 dark:text-white capitalize" x-text="type.name"></span>
                        </div>
                        <span class="text-sm text-gray-500 dark:text-gray-400" x-text="type.count"></span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                        <div class="h-2 rounded-full transition-all duration-300"
                             :class="`bg-${window.ActivityTypeStyler?.getColor(type.name) || 'gray'}-500`"
                             :style="`width: ${type.percentage}%`"></div>
                    </div>
                </template>

                <!-- Empty state -->
                <div x-show="!loading && eventTypes.length === 0" class="text-center py-8">
                    <p class="text-sm text-gray-500 dark:text-gray-400">No activity types found</p>
                </div>
            </div>
        </div>

        <!-- Top Users -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 border border-gray-200 dark:border-gray-700">
            <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Most Active Users</h4>
            <div class="space-y-4">
                <template x-for="user in topUsers" :key="user.id">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-8 w-8">
                                <div class="h-8 w-8 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center">
                                    <span class="text-xs font-medium text-gray-700 dark:text-gray-300"
                                          x-text="user.name?.charAt(0) || '?'"></span>
                                </div>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-900 dark:text-white" x-text="user.name"></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400" x-text="user.email"></p>
                            </div>
                        </div>
                        <span class="text-sm font-medium text-gray-900 dark:text-white" x-text="user.activity_count"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Popular Models -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 border border-gray-200 dark:border-gray-700">
        <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Popular Models</h4>
        <div class="space-y-4">
            <template x-for="model in popularModels" :key="model.type">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 h-8 w-8">
                            <div class="h-8 w-8 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center">
                                <span class="text-xs font-medium text-indigo-700 dark:text-indigo-300"
                                      x-text="model.name?.charAt(0) || '?'"></span>
                            </div>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-gray-900 dark:text-white" x-text="model.name"></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400" x-text="model.type"></p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="text-sm font-medium text-gray-900 dark:text-white" x-text="model.activity_count"></span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">activities</span>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Activity Trends Chart -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 border border-gray-200 dark:border-gray-700">
        <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Activity Trends</h4>
        <div class="h-64">
            <canvas id="activityTrendsChart"></canvas>
        </div>
    </div>

    <!-- Recent Activity Timeline -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Activity Timeline</h4>
        <div class="space-y-3">
            <template x-for="day in timeline" :key="day.date">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4 w-1/4">
                        <div>
                            <span class="text-sm font-medium text-gray-900 dark:text-white" x-text="day.date"></span>
                            <span class="ml-2 text-xs text-gray-500 dark:text-gray-400" x-text="day.day_name"></span>
                        </div>
                    </div>
                    <div class="flex-1 flex items-center space-x-4">
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                            <div class="h-3 rounded-full transition-all duration-300 relative"
                                 :class="day.count > 0 ? 'bg-blue-500' : 'bg-gray-400'"
                                 :style="`width: ${Math.max(day.percentage, day.count === 0 ? 2 : 0)}%`">
                                <span class="absolute -right-4 -top-6 text-xs font-medium text-gray-700 dark:text-gray-300"
                                      x-text="day.count"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Empty state -->
            <div x-show="!loading && timeline.length === 0" class="text-center py-8">
                <p class="text-sm text-gray-500 dark:text-gray-400">No timeline data available</p>
            </div>
        </div>
    </div>

    <!-- Loading State -->
    <div x-show="loading" class="text-center py-12">
        <div class="inline-flex items-center space-x-2 text-gray-500 dark:text-gray-400">
            <svg class="animate-spin h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            <span class="font-medium">Loading analytics...</span>
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

        init() {
            this.loadAnalytics();
        },

        async loadAnalytics() {
            try {
                this.loading = true;
                let url = '{{ route("activitylog-ui.api.analytics") }}';
                let params = new URLSearchParams();

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

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

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

                    if (this.activityTrends && document.getElementById('activityTrendsChart')) {
                        this.initActivityTrendsChart();
                    }
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

        initActivityTrendsChart() {
            const canvas = document.getElementById('activityTrendsChart');
            if (!canvas) return;

            // Destroy existing chart if it exists
            if (this.chart instanceof Chart) {
                this.chart.destroy();
            }

            const ctx = canvas.getContext('2d');

            this.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: this.activityTrends.dates,
                    datasets: this.activityTrends.datasets.map(dataset => ({
                        label: dataset.label,
                        data: dataset.data.map(d => d.count),
                        borderColor: dataset.color,
                        backgroundColor: `${dataset.color}20`,
                        tension: 0.4,
                        fill: true
                    }))
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },

        // Cleanup method
        destroy() {
            if (this.chart instanceof Chart) {
                this.chart.destroy();
                this.chart = null;
            }
        }
    }));
});
</script>

<style>
/* Enhanced analytics dashboard styling */
.analytics-card {
    transition: all 0.2s ease-in-out;
}

.analytics-card:hover {
    transform: translateY(-1px);
}

/* Dark mode chart adjustments */
.dark canvas {
    filter: brightness(0.9);
}

/* Enhanced user list styling */
.user-item {
    transition: all 0.15s ease-in-out;
}

.user-item:hover {
    transform: translateX(2px);
}
</style>
