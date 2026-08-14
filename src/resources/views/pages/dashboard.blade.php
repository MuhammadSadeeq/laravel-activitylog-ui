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
            <div x-show="(loading || !hasLoaded) && currentView !== 'analytics'" class="flex items-center justify-center py-12">
                <div class="flex items-center space-x-2 text-gray-500 dark:text-gray-400">
                    <svg class="animate-spin h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    <span>Loading activities...</span>
                </div>
            </div>

            <!-- Table View -->
            <div x-show="currentView === 'table' && !loading && hasLoaded">
                @include('activitylog-ui::components.table-view')
            </div>

            <!-- Timeline View -->
            <div x-show="currentView === 'timeline' && !loading && hasLoaded">
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
        // False until the first activities request settles. Without it the views
        // render their "no activities found" state while the filter panel is still
        // initialising, before any request has even been made.
        hasLoaded: false,
        // Set when the last load failed. Without it an empty `activities` reads as
        // "nothing matched your filters", so a 500 looks like a successful search
        // once the error toast has faded.
        loadError: false,
        // Page number of a reload that arrived while one was already running.
        pendingReload: null,
        // Kept separate from `loading` so appending to the timeline does not
        // hide the list the user is currently scrolled into — x-show collapses
        // the document height, and the browser then clamps scrollTop to 0.
        loadingMore: false,
        // Bumped by every full reload. An in-flight "load more" compares against
        // it so a superseded page is discarded instead of appended.
        requestToken: 0,
        activities: [],
        totalActivities: 0,
        currentPage: 1,
        perPage: {{ config('activitylog-ui.ui.default_per_page', 25) }},
        totalPages: 1,
        // Id of the newest row this listing is pinned to. Taken from the first
        // page and sent with every page after it, so activities recorded while
        // the user reads do not shift the offsets underneath them.
        anchorId: null,
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
                this.currentFilters = event.detail || {};
                this.currentPage = 1; // Reset to first page

                // Analytics listens for this event itself, and does not render
                // the activity list, so there is nothing to fetch for it here.
                if (this.currentView !== 'analytics') {
                    this.loadActivities();
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

            // The initial load is driven by 'filter-panel-ready', which fires once
            // the panel has restored the user's saved filters and carries them in
            // its payload. Loading here as well would issue a second request whose
            // result is immediately discarded, and show the user two toasts.
        },

        async loadActivities(page = 1) {
            // A reload arriving while one is in flight used to be dropped outright,
            // so changing a filter during a slow request left the rows showing the
            // previous filter with nothing pending. Remember it and run it after.
            if (this.loading) {
                this.pendingReload = page;
                return;
            }

            this.loading = true;
            this.loadError = false;
            this.currentPage = page;
            // Supersede any "load more" still in flight, and release its lock so
            // the button is usable as soon as this reload lands rather than
            // whenever the abandoned request happens to settle.
            this.requestToken++;
            this.loadingMore = false;

            // Page 1 is always a fresh look at the newest rows, so it re-anchors;
            // every other page is a move within the listing page 1 established.
            if (page === 1) {
                this.anchorId = null;
            }

            try {
                // Build query parameters
                const params = new URLSearchParams();
                params.append('page', page);
                params.append('per_page', this.perPage);

                if (this.anchorId !== null && this.anchorId !== undefined) {
                    params.append('anchor_id', this.anchorId);
                }

                // Add filters to params
                Object.keys(this.currentFilters || {}).forEach(key => {
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

                const result = await window.ActivitylogUi.parseJsonResponse(response, 'Loading activities');

                this.activities = result.data || [];
                this.totalActivities = result.total || 0;
                this.totalPages = result.last_page || 1;
                this.anchorId = result.anchor_id ?? null;

                // No success toast: the rows appearing is the confirmation.
            } catch (error) {
                this.activities = [];
                this.totalActivities = 0;
                this.anchorId = null;
                this.loadError = true;

                if (window.notify) {
                    // A refused filter names itself, and the user can act on that
                    // — usually by clearing filters. "Failed to load activities"
                    // gives them nothing to go on.
                    error?.isInvalidInput
                        ? window.notify.error('Filter not accepted', error.message, { timeout: 0 })
                        : window.notify.error('Error', 'Failed to load activities');
                }
            } finally {
                this.loading = false;
                this.hasLoaded = true;

                // Run the most recent superseded request, if any. Only the last
                // one matters: earlier ones are already stale.
                if (this.pendingReload !== null) {
                    const next = this.pendingReload;
                    this.pendingReload = null;
                    this.loadActivities(next);
                }
            }
        },

        changePage(page) {
            if (page >= 1 && page <= this.totalPages) {
                this.loadActivities(page);
            }
        },

        get hasActiveFilters() {
            const filters = this.currentFilters || {};

            return Object.keys(filters).some(key => {
                const value = filters[key];
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
            if (this.currentView !== 'timeline' || this.loading || this.loadingMore || this.currentPage >= this.totalPages) {
                return;
            }

            const nextPage = this.currentPage + 1;
            const token = this.requestToken;

            this.loadingMore = true;

            try {
                // Build query parameters
                const params = new URLSearchParams();
                params.append('page', nextPage);
                params.append('per_page', this.perPage);

                // Without this the timeline was the worst case of all: each Load
                // More re-read an offset into a list that had grown since the
                // previous one, so the rows it appended overlapped the rows
                // already on screen.
                if (this.anchorId !== null && this.anchorId !== undefined) {
                    params.append('anchor_id', this.anchorId);
                }

                // Add filters to params
                Object.keys(this.currentFilters || {}).forEach(key => {
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

                const result = await window.ActivitylogUi.parseJsonResponse(response, 'Loading more activities');

                // A filter change or view switch may have reloaded the list while
                // this request was in flight; appending a stale page would
                // duplicate or interleave rows.
                if (token !== this.requestToken) {
                    return;
                }

                if (result.data && result.data.length > 0) {
                    // Append new activities to existing ones for timeline view.
                    // No success toast: the appended rows and the "showing X of Y"
                    // counter below the button already report the result.
                    this.activities = [...this.activities, ...result.data];
                    this.currentPage = nextPage;
                    this.totalPages = result.last_page || 1;
                } else {
                    // Empty page: stop here rather than ignore it, which left the
                    // button visible re-requesting the same page forever. Clamp
                    // downward only — claiming pages we never appended would skip
                    // rows that are still there.
                    this.currentPage = Math.min(this.currentPage, result.last_page || 1);
                    this.totalPages = this.currentPage;
                }

            } catch (error) {
                // A reload already replaced what this request was appending to,
                // so its failure is no longer something the user can act on.
                if (token !== this.requestToken) {
                    return;
                }

                console.error('Error loading more activities:', error);
                if (window.notify) {
                    window.notify.error('Error', 'Failed to load more activities');
                }
            } finally {
                // Only release the lock if this request still owns it. A reload
                // may have cleared it and a newer "load more" may already hold it.
                if (token === this.requestToken) {
                    this.loadingMore = false;
                }
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
                // Starts at page 1. In timeline, currentPage is an append cursor —
                // after three Load Mores it is 3 while the user is looking at rows
                // 1-75 — so carrying it over would drop them on rows 51-75 of a page
                // they never chose. Table paging needs its own state to do better.
                this.loadActivities();
            } else if (view === 'analytics') {
                // Nothing to do: analytics has no pagination, and the component
                // watches currentView to load itself the first time it is shown.
            }
        },

        // Reload analytics with current filters
        // reloadAnalytics() used to live here, querying the DOM for
        // [x-data*="analyticsDashboard"] — a component name that does not exist,
        // so it matched nothing and silently did nothing. The analytics component
        // now listens for filter-changed itself.
    }
}
</script>
@endpush
