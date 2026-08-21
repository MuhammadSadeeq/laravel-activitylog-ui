@extends('activitylog-ui::layouts.app')

@section('title')
{{ config('activitylog-ui.ui.title', 'Activity Log') }}
@endsection

@section('content')
<div x-data="activityDashboard()" x-init="init()" class="al-stack al-stack--lg">
    {{-- The page title lives in the toolbar rather than in a block of its own.
         A standalone heading plus a sentence of explanation cost about 90px of
         vertical space above a table whose whole job is to show rows. --}}
    <div class="al-toolbar">
        <div class="al-toolbar__grow al-row" style="gap:.625rem">
            <h1 style="font-size:var(--step-3)">{{ config('activitylog-ui.ui.title', 'Activity Log') }}</h1>
            <span class="al-chip tnum" x-show="hasLoaded && !loadError" x-cloak>
                <span class="al-chip__text" x-text="totalActivities.toLocaleString()"></span>
            </span>
        </div>

        <div class="al-segmented" role="group" aria-label="View">
            <button type="button"
                    class="al-segmented__btn"
                    :aria-pressed="currentView === 'table' ? 'true' : 'false'"
                    @click="switchView('table')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                    <path d="M3 6h18M3 12h18M3 18h18"/>
                </svg>
                Table
            </button>
            <button type="button"
                    class="al-segmented__btn"
                    :aria-pressed="currentView === 'timeline' ? 'true' : 'false'"
                    @click="switchView('timeline')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                    <circle cx="6" cy="6" r="2"/><circle cx="6" cy="18" r="2"/><path d="M6 8v8M11 6h9M11 18h9"/>
                </svg>
                Timeline
            </button>
            @if(config('activitylog-ui.features.analytics', true))
            <button type="button"
                    class="al-segmented__btn"
                    :aria-pressed="currentView === 'analytics' ? 'true' : 'false'"
                    @click="switchView('analytics')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                    <path d="M4 20V10M10 20V4M16 20v-6M22 20H2"/>
                </svg>
                Analytics
            </button>
            @endif
        </div>

        @if(config('activitylog-ui.features.exports', true))
        <button type="button" class="al-btn" @click="exportActivities()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 3v12M8 11l4 4 4-4M4 21h16"/>
            </svg>
            Export
        </button>
        @endif
    </div>

    <div class="al-layout" :class="currentView === 'analytics' ? '' : 'al-layout--with-sidebar'">
        <div x-show="currentView !== 'analytics'" x-cloak>
            @include('activitylog-ui::components.filter-panel')
        </div>

        <div style="min-width:0">
            {{-- Skeleton rows rather than a spinner: the page keeps its shape
                 while loading, so the content does not jump when it arrives. --}}
            <div x-show="(loading || !hasLoaded) && currentView !== 'analytics'" class="al-card">
                <div class="al-card__body al-stack al-stack--md" aria-hidden="true">
                    <template x-for="n in 6" :key="n">
                        <div class="al-row" style="gap:.75rem">
                            <div class="al-skeleton" style="height:1.25rem;width:4.5rem;flex:none"></div>
                            <div class="al-skeleton al-grow" style="height:1.25rem"></div>
                            <div class="al-skeleton al-hide-sm" style="height:1.25rem;width:5rem;flex:none"></div>
                        </div>
                    </template>
                </div>
                <span class="al-visually-hidden" role="status">Loading activities…</span>
            </div>

            <div x-show="currentView === 'table' && !loading && hasLoaded" x-cloak>
                @include('activitylog-ui::components.table-view')
            </div>

            <div x-show="currentView === 'timeline' && !loading && hasLoaded" x-cloak>
                @include('activitylog-ui::components.timeline-view')
            </div>

            @if(config('activitylog-ui.features.analytics', true))
            <div x-show="currentView === 'analytics'" x-cloak>
                @include('activitylog-ui::components.analytics-dashboard')
            </div>
            @endif
        </div>
    </div>

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

        /**
         * Activities bucketed by calendar day, so the table can state a date
         * once per group instead of repeating it on every row.
         */
        get groupedActivities() {
            const groups = [];

            for (const activity of this.activities) {
                const date = new Date(activity.created_at);
                const key = Number.isNaN(date.getTime()) ? 'unknown' : date.toDateString();

                // The key must be unique per GROUP, not per day. Grouping is
                // run-length, so one date can open several groups on a page —
                // and a keyed x-for collapses duplicates to a single node,
                // silently dropping every other group's rows. That is row loss
                // with no error, in an audit log.
                if (!groups.length || groups[groups.length - 1].date !== key) {
                    groups.push({
                        key: key + '#' + groups.length,
                        date: key,
                        label: window.ActivitylogUi.formatDayHeading(activity.created_at),
                        items: [],
                    });
                }

                groups[groups.length - 1].items.push(activity);
            }

            return groups;
        },

        showActivityDetail(activity) {
            window.dispatchEvent(new CustomEvent('show-activity-detail', {
                detail: activity
            }));
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
