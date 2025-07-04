@extends('activitylog-ui::layouts.app')

@section('title')
{{ config('activitylog-ui.ui.title', 'Activity Log') }} Dashboard
@endsection

@section('content')
<div x-data="activityDashboard()" x-init="init()" class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">{{ config('activitylog-ui.ui.title', 'Activity Log') }}</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Monitor and analyze all system activities</p>

            <!-- Context indicator for pagination state -->
            <div x-show="currentView === 'table' && currentPage > 1" class="mt-2">
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Currently on page &nbsp;<span x-text="currentPage"></span>&nbsp;of&nbsp;<span x-text="totalPages"></span>
                </span>
            </div>
            </div>

        <!-- View Switcher & Export -->
        <div class="mt-4 sm:mt-0 flex items-center space-x-4">
            <!-- Export Button -->
            @if(config('activitylog-ui.features.exports', true))
            <button @click="exportActivities()"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M7 7h10a2 2 0 012 2v8a2 2 0 01-2 2H7a2 2 0 01-2-2V9a2 2 0 012-2z"></path>
                </svg>
                Export
            </button>
            @endif

            <div class="flex rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-1">
                    <button @click="switchView('table')"
                        :class="currentView === 'table' ? 'bg-blue-500 text-white' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
                        class="flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 6h18m-9 8h9"></path>
                        </svg>
                    Table
                    </button>
                    <button @click="switchView('timeline')"
                        :class="currentView === 'timeline' ? 'bg-blue-500 text-white' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
                        class="flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors ml-1">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    Timeline
                    </button>
                    @if(config('activitylog-ui.features.analytics', true))
                    <button @click="switchView('analytics')"
                        :class="currentView === 'analytics' ? 'bg-blue-500 text-white' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
                        class="flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors ml-1">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Analytics
                </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Content -->
    <div class="flex flex-col lg:flex-row gap-6">
        <!-- Single Unified Filter Panel -->
        <div x-show="currentView !== 'analytics'" class="w-full lg:w-72 xl:w-80 lg:flex-shrink-0">
            @include('activitylog-ui::components.filter-panel')
        </div>

        <!-- Main Content -->
        <div class="w-full lg:flex-1 lg:min-w-0" :class="{ 'lg:ml-0': currentView === 'analytics' }">
            <!-- Loading State -->
            <div x-show="loading" class="flex items-center justify-center py-12">
                <div class="flex items-center space-x-2 text-gray-500 dark:text-gray-400">
                    <svg class="animate-spin h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    <span>Loading activities...</span>
                </div>
            </div>

            <!-- Table View -->
            <div x-show="currentView === 'table' && !loading">
                @include('activitylog-ui::components.table-view')
            </div>

            <!-- Timeline View -->
            <div x-show="currentView === 'timeline' && !loading">
                @include('activitylog-ui::components.timeline-view')
            </div>

            <!-- Analytics View -->
            @if(config('activitylog-ui.features.analytics', true))
            <div x-show="currentView === 'analytics' && !loading">
                @include('activitylog-ui::components.analytics-dashboard')
            </div>
            @endif
        </div>
    </div>

    <!-- Modals -->
    @include('activitylog-ui::components.activity-detail-modal')
    @if(config('activitylog-ui.features.exports', true))
    @include('activitylog-ui::components.export-modal')
    @endif
    @if(config('activitylog-ui.features.saved_views', true))
    @include('activitylog-ui::components.save-view-modal')
    @endif
</div>
@endsection

@push('scripts')
<script>
function activityDashboard() {
    return {
        // State
        initialized: false,
        currentView: '{{ $view }}',
        loading: false,
        activities: [],
        totalActivities: 0,
        currentPage: 1,
        perPage: {{ config('activitylog-ui.ui.default_per_page', 25) }},
        totalPages: 1,
        showExportModal: false,
        @if(config('activitylog-ui.features.saved_views', true))
        showSaveViewModal: false,
        @endif
        selectedActivity: null,
        currentFilters: {},

        init() {
            // Prevent multiple initializations
            if (this.initialized) return;
            this.initialized = true;

            // Initialize event listeners first
            this.filterChangedHandler = (event) => {
                this.currentFilters = event.detail;
                this.currentPage = 1; // Reset to first page
                this.loadActivities();

                // Also reload analytics if on analytics view
                if (this.currentView === 'analytics') {
                    this.reloadAnalytics();
                }
            };

            this.showExportModalHandler = () => {
                this.showExportModal = true;
            };

            this.showActivityDetailHandler = (event) => {
                this.selectedActivity = event.detail;
            };

            @if(config('activitylog-ui.features.saved_views', true))
            this.showSaveViewModalHandler = (event) => {
                this.showSaveViewModal = true;
            };
            @endif

            // Remove any existing listeners first
            window.removeEventListener('filter-changed', this.filterChangedHandler);
            window.removeEventListener('show-export-modal', this.showExportModalHandler);
            window.removeEventListener('show-activity-detail', this.showActivityDetailHandler);
            @if(config('activitylog-ui.features.saved_views', true))
            window.removeEventListener('show-save-view-modal', this.showSaveViewModalHandler);
            @endif
            window.removeEventListener('filter-panel-ready', this.filterChangedHandler);

            // Add event listeners
            window.addEventListener('filter-changed', this.filterChangedHandler);
            window.addEventListener('show-export-modal', this.showExportModalHandler);
            window.addEventListener('show-activity-detail', this.showActivityDetailHandler);
            @if(config('activitylog-ui.features.saved_views', true))
            window.addEventListener('show-save-view-modal', this.showSaveViewModalHandler);
            @endif
            window.addEventListener('filter-panel-ready', this.filterChangedHandler);

            // Load initial data based on the default view
            if (this.currentView === 'analytics') {
                this.reloadAnalytics();
            } else {
                this.loadActivities();
            }
        },

        async loadActivities(page = 1) {
            // Prevent multiple simultaneous calls
            if (this.loading) {
                return;
            }

            this.loading = true;
            this.currentPage = page;

            try {
                // Build query parameters
                const params = new URLSearchParams();
                params.append('page', page);
                params.append('per_page', this.perPage);

                // Add filters to params
                Object.keys(this.currentFilters).forEach(key => {
                    const value = this.currentFilters[key];
                    if (value !== null && value !== undefined && value !== '') {
                        if (Array.isArray(value)) {
                            // Handle arrays properly (like event_types)
                            value.forEach(item => {
                                params.append(`${key}[]`, item);
                            });
                        } else {
                            params.append(key, value);
                        }
                    }
                });

                // Make API call to get activities
                const response = await fetch(`{{ route('activitylog-ui.api.activities.index') }}?${params}`, {
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

                const result = await response.json();

                this.activities = result.data || [];
                this.totalActivities = result.total || 0;
                this.totalPages = result.last_page || 1;

                if (window.notify) {
                    window.notify.success('Success', `Loaded ${this.activities.length} activities`);
                }

            } catch (error) {
                this.activities = [];
                this.totalActivities = 0;

                if (window.notify) {
                    window.notify.error('Error', 'Failed to load activities');
                }
            } finally {
                this.loading = false;
            }
        },

        changePage(page) {
            if (page >= 1 && page <= this.totalPages) {
                this.loadActivities(page);
            }
        },

        get hasActiveFilters() {
            return Object.keys(this.currentFilters).some(key => {
                const value = this.currentFilters[key];
                return value !== '' && value !== null &&
                       (Array.isArray(value) ? value.length > 0 : true) &&
                       !(key === 'date_preset' && value === 'all');
            });
        },

        exportActivities() {
            window.dispatchEvent(new CustomEvent('show-export-modal', {
                detail: { filters: this.currentFilters }
            }));
        },

        showActivityDetail(activity) {
            window.dispatchEvent(new CustomEvent('show-activity-detail', {
                detail: activity
            }));
        },

        getEventTypeColor(event) {
            const colors = {
                'created': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                'updated': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                'deleted': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                'restored': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
            };
            return colors[event] || 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200';
        },

        getEventIcon(event) {
            const icons = {
                'created': 'M12 6v6m0 0v6m0-6h6m-6 0H6',
                'updated': 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
                'deleted': 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
                'restored': 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15'
            };
            return icons[event] || 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z';
        },

        getUserInitials(name) {
            if (!name) return '?';
            return name.split(' ').map(word => word[0]).join('').toUpperCase().slice(0, 2);
        },

        formatDate(dateString) {
            return new Date(dateString).toLocaleString();
        },

        // Load more activities for timeline view
        async loadMoreActivities() {
            if (this.currentView !== 'timeline' || this.loading || this.currentPage >= this.totalPages) {
                return;
            }

            const nextPage = this.currentPage + 1;

            this.loading = true;

            try {
                // Build query parameters
                const params = new URLSearchParams();
                params.append('page', nextPage);
                params.append('per_page', this.perPage);

                // Add filters to params
                Object.keys(this.currentFilters).forEach(key => {
                    const value = this.currentFilters[key];
                    if (value !== null && value !== undefined && value !== '') {
                        if (Array.isArray(value)) {
                            value.forEach(item => {
                                params.append(`${key}[]`, item);
                            });
                        } else {
                            params.append(key, value);
                        }
                    }
                });

                // Make API call to get activities
                const response = await fetch(`{{ route('activitylog-ui.api.activities.index') }}?${params}`, {
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

                const result = await response.json();

                // Append new activities to existing ones for timeline view
                if (result.data && result.data.length > 0) {
                    this.activities = [...this.activities, ...result.data];
                    this.currentPage = nextPage;
                    this.totalPages = result.last_page || 1;

                    if (window.notify) {
                        window.notify.success('Success', `Loaded ${result.data.length} more activities`);
                    }
                }

            } catch (error) {
                console.error('Error loading more activities:', error);
                if (window.notify) {
                    window.notify.error('Error', 'Failed to load more activities');
                }
            } finally {
                this.loading = false;
            }
        },

        // Smart view switching with context preservation
        switchView(view) {
            const previousView = this.currentView;
            this.currentView = view;

            // Smart view switching logic based on UX requirements
            if (view === 'timeline') {
                // Timeline needs chronological context - reset to show from beginning
                // This provides the full chronological story, which is essential for timeline UX
                const wasOnLaterPage = this.currentPage > 1;
                this.currentPage = 1;
                this.activities = []; // Clear for fresh load

            this.loadActivities();

                if (window.notify && wasOnLaterPage && previousView === 'table') {
                    const message = `Switched to timeline view. Loading activities from the beginning for chronological context.`;
                    window.notify.info('Timeline View', message);
                }
            } else if (view === 'table') {
                // Table view can handle any page - maintain current pagination
            this.loadActivities();

                if (window.notify && previousView === 'timeline' && this.currentPage > 1) {
                    window.notify.info('Table View', `Showing page ${this.currentPage} of activities`);
                }
            } else if (view === 'analytics') {
                // Analytics doesn't use pagination
                this.reloadAnalytics();
            }
        },

        // Reload analytics with current filters
        reloadAnalytics() {
            // Find the analytics component and reload it
            const analyticsComponent = document.querySelector('[x-data*="analyticsDashboard"]');
            if (analyticsComponent && analyticsComponent._x_dataStack) {
                const component = analyticsComponent._x_dataStack[0];
                if (component && component.loadAnalytics) {
                    component.loadAnalytics(this.currentFilters);
                }
            }
        }
    }
}
</script>
@endpush
