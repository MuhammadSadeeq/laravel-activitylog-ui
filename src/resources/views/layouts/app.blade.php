<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">

    <title>@yield('title') - {{ config('activitylog-ui.ui.brand', 'ActivityLog UI') }}</title>

    <!-- Favicon -->
    @if(file_exists(public_path('vendor/activitylog-ui/images/favicon.svg')))
    <link rel="icon" type="image/svg+xml" href="{{ asset('vendor/activitylog-ui/images/favicon.svg') }}">
    @endif
    @if(file_exists(public_path('vendor/activitylog-ui/images/favicon.ico')))
    <link rel="icon" type="image/x-icon" href="{{ asset('vendor/activitylog-ui/images/favicon.ico') }}">
    @endif

    {{--
        One stylesheet, served by the package and cached for a year against a
        content hash. It replaces the Tailwind Play CDN, which shipped a ~400KB
        script that generated the page's styles in the browser on every load —
        a visible flash of unstyled content before an audit log could be read,
        and a dependency on a third-party CDN staying up.

        The typeface is the system stack for the same reason: no webfont
        request, no swap, and it looks native wherever it runs.
    --}}
    <link rel="stylesheet" href="{{ route('activitylog-ui.assets.css') }}">

    <script>
        // Applied before first paint so the page never flashes light then dark.
        (function () {
            try {
                var stored = localStorage.getItem('darkMode');
                var on = stored === null
                    ? window.matchMedia('(prefers-color-scheme: dark)').matches
                    : stored === 'true';
                document.documentElement.classList.toggle('dark', on);
            } catch (e) {}
        })();
    </script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Global Alpine.js Functions -->
    <script>
        // Global Alpine.js component functions
        window.AlpineComponents = {
            // Filter Panel Component
            filterPanel() {
                return {
                    // Initialization state
                    initialized: false,
                    filterTimeout: null,

                    // Open on a wide screen, where the panel is a sidebar beside
                    // the results. Closed on a narrow one, where it would
                    // otherwise push the activities the user came to read off
                    // the bottom of the screen.
                    expanded: typeof window !== 'undefined' && window.matchMedia
                        ? window.matchMedia('(min-width: 64rem)').matches
                        : true,
                    showAdvanced: false,

                    defaultFilters() {
                        return {
                            search: '',
                            date_preset: 'all',
                            start_date: '',
                            end_date: '',
                            event_types: [],
                            causer_id: null,
                            causer_type: null,
                            subject_type: '',
                        };
                    },

                    // Filter state
                    filters: {},

                    // Data
                    @if(config('activitylog-ui.features.saved_views', true))
                    savedViews: [],
                    @endif
                    availableEventTypes: [],
                    availableSubjectTypes: [],
                    availableCausers: [],
                    filteredCausers: [],

                    // Date presets loaded from config
                    datePresets: {!! collect(config('activitylog-ui.filters.date_presets', []))
                        ->map(function($label, $value) { return ['value' => $value, 'label' => $label]; })
                        ->values()
                        ->toJson() !!},

                    // Causer management
                    causerSearch: '',
                    selectedCauser: null,

                    // Initialization
                    async init() {
                        if (this.initialized) return;

                        // init state
                        this.filters = this.defaultFilters();
                        
                        this.initialized = true;

                        @if(config('activitylog-ui.features.saved_views', true))
                        // Load saved views
                        await this.loadSavedViews();

                        // Add event listener for saved views updates
                        window.addEventListener('saved-views-updated', async () => {
                            await this.loadSavedViews();
                        });
                        @endif

                        try {
                            // Restore persisted state
                            this.restorePersistedState();

                            await this.loadCausers();
                            this.filteredCausers = this.availableCausers;
                        } catch (error) {
                            // Reading localStorage can throw outright (opaque origin,
                            // storage disabled). Carry on with default filters rather
                            // than leaving the panel half-initialised.
                            console.error('Filter panel initialization failed:', error);
                        } finally {
                            // This event is the dashboard's only trigger for its first
                            // data load, so it has to fire even when the above failed —
                            // otherwise the page sits empty having never made a request.
                            window.dispatchEvent(new CustomEvent('filter-panel-ready', {
                                detail: this.filters
                            }));
                        }
                    },

                    async loadCausers() {
                        try {
                            const response = await fetch('{{ route("activitylog-ui.api.filter.options") }}', {
                                method: 'GET',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                                }
                            });

                            const data = await window.ActivitylogUi.parseJsonResponse(response, 'Loading filter options');
                            this.availableCausers = data.causers || [];
                            this.availableSubjectTypes = data.subject_types || this.availableSubjectTypes;

                            // Process event types with dynamic styling
                            if (data.event_types) {
                                this.availableEventTypes = data.event_types.map(eventType => {
                                    return {
                                        value: eventType.value,
                                        label: eventType.label,
                                        event: window.ActivityTypeStyler.getEvent(eventType.value),
                                        styling: window.ActivityTypeStyler.getEventTypeStyling(eventType.value)
                                    };
                                });
                            }

                            this.filteredCausers = this.availableCausers;
                        } catch (error) {
                            if (window.notify) {
                                window.notify.error('Error', 'Failed to load filter options');
                            }

                            // Set empty defaults
                            this.availableCausers = [];
                            this.filteredCausers = [];
                            this.availableEventTypes = ['created', 'updated', 'deleted', 'restored'].map(eventType => ({
                                value: eventType,
                                label: eventType.charAt(0).toUpperCase() + eventType.slice(1),
                                event: eventType,
                                styling: window.ActivityTypeStyler.getEventTypeStyling(eventType)
                            }));
                        } finally {
                            // The restored causer_id was applied before this request
                            // finished, so the label it should carry only becomes
                            // knowable here.
                            this.resolveSelectedCauser();
                        }
                    },

                    /**
                     * Bring the causer control in step with filters.causer_id.
                     *
                     * The selection is restored from localStorage, and a saved view
                     * may be loaded, before the causer list has arrived — and the
                     * stored copy is a snapshot that can name someone who has since
                     * been renamed or deleted. Resolving against the loaded list
                     * keeps the label truthful; the fallback matters just as much,
                     * because leaving it null showed "All users" over a filtered
                     * result set, which reads as a broken filter rather than as a
                     * causer the dropdown cannot name.
                     */
                    resolveSelectedCauser() {
                        // Tested the way the backend tests it, which accepts every
                        // value but null and ''. A truthiness check treated the
                        // integer id 0 as no filter at all, so a saved view holding
                        // one showed "All users" over a filtered list — the exact
                        // mismatch this method exists to prevent.
                        const causerId = this.filters.causer_id;

                        if (causerId === null || causerId === undefined || causerId === '') {
                            this.selectedCauser = null;
                            return;
                        }

                        const match = this.availableCausers.find(c =>
                            String(c.id) === String(causerId) &&
                            (!this.filters.causer_type || c.type === this.filters.causer_type)
                        );

                        if (match) {
                            this.selectedCauser = match;
                            return;
                        }

                        // Nothing to match against — a failed options request, or a
                        // causer no longer in the list. Name it from the filter
                        // itself rather than claiming no filter is set.
                        const type = this.filters.causer_type
                            ? String(this.filters.causer_type).split('\\').pop()
                            : 'Causer';

                        this.selectedCauser = {
                            id: causerId,
                            type: this.filters.causer_type,
                            name: `${type} #${causerId}`,
                            email: null,
                            label: `${type} #${causerId}`,
                        };
                    },

                    searchCausers() {
                        if (!this.causerSearch) {
                            this.filteredCausers = this.availableCausers;
                            return;
                        }

                        const search = this.causerSearch.toLowerCase();
                        this.filteredCausers = this.availableCausers.filter(causer =>
                            String(causer.name ?? '').toLowerCase().includes(search) ||
                            String(causer.email ?? '').toLowerCase().includes(search)
                        );
                    },

                    selectCauser(causer) {
                        this.selectedCauser = causer;
                        this.filters.causer_id = causer?.id ?? null;
                        // Sent alongside the id: without it, picking one causer
                        // matches every model type that happens to share that id.
                        this.filters.causer_type = causer?.type ?? null;
                        this.applyFilters();
                    },

                    get selectedCauserText() {
                        return this.selectedCauser?.name || null;
                    },

                    setDatePreset(preset) {
                        this.filters.date_preset = preset;

                        const today = new Date();
                        const yesterday = new Date(today);
                        yesterday.setDate(yesterday.getDate() - 1);

                        switch (preset) {
                            case 'today':
                                this.filters.start_date = today.toISOString().split('T')[0];
                                this.filters.end_date = today.toISOString().split('T')[0];
                                break;
                            case 'yesterday':
                                this.filters.start_date = yesterday.toISOString().split('T')[0];
                                this.filters.end_date = yesterday.toISOString().split('T')[0];
                                break;
                            case 'last_7_days':
                                const weekStart = new Date(today);
                                weekStart.setDate(today.getDate() - 7);
                                this.filters.start_date = weekStart.toISOString().split('T')[0];
                                this.filters.end_date = today.toISOString().split('T')[0];
                                break;
                            case 'this_month':
                                const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
                                this.filters.start_date = monthStart.toISOString().split('T')[0];
                                this.filters.end_date = today.toISOString().split('T')[0];
                                break;
                            case 'last_month':
                                const lastMonthStart = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                                const lastMonthEnd = new Date(today.getFullYear(), today.getMonth(), 0);
                                this.filters.start_date = lastMonthStart.toISOString().split('T')[0];
                                this.filters.end_date = lastMonthEnd.toISOString().split('T')[0];
                                break;
                            case 'custom':
                                // Don't clear dates when switching to custom - preserve existing values
                                break;
                            case 'all':
                            default:
                                this.filters.start_date = '';
                                this.filters.end_date = '';
                                break;
                        }

                        if (preset !== 'custom') {
                            this.applyFilters();
                        } else if (preset === 'custom' && (this.filters.start_date || this.filters.end_date)) {
                            // Apply filters immediately if custom is selected and dates are already set
                            this.applyFilters();
                        }
                    },

                    get hasActiveFilters() {
                        return Object.keys(this.filters).some(key => {
                            const value = this.filters[key];
                            return value !== '' && value !== null &&
                                   (Array.isArray(value) ? value.length > 0 : true) &&
                                   !(key === 'date_preset' && value === 'all');
                        });
                    },

                    applyFilters() {
                        // Debounce filter application to prevent multiple rapid calls
                        clearTimeout(this.filterTimeout);
                        this.filterTimeout = setTimeout(() => {
                            window.dispatchEvent(new CustomEvent('filter-changed', {
                                detail: this.filters
                            }));
                        }, 300); // 300ms debounce
                    },

                    clearAllFilters() {
                        // Clear localStorage
                        localStorage.removeItem('activitylog_date_preset');
                        localStorage.removeItem('activitylog_start_date');
                        localStorage.removeItem('activitylog_end_date');
                        localStorage.removeItem('activitylog_search');
                        localStorage.removeItem('activitylog_event_types');
                        localStorage.removeItem('activitylog_causer_id');
                        localStorage.removeItem('activitylog_causer_type');
                        localStorage.removeItem('activitylog_subject_type');
                        localStorage.removeItem('activitylog_selected_causer');

                        // Reset filters
                        this.filters = this.defaultFilters();
                        this.selectedCauser = null;
                        this.causerSearch = '';
                        this.applyFilters();
                    },

                    loadSavedView(view) {
                        this.filters = {
                            ...this.defaultFilters(),
                            ...(view.filters || {}),
                        };

                        // Bring the causer control in step with the restored filter,
                        // or the dropdown keeps showing whoever was picked last —
                        // or "All users" — while the results are filtered.
                        this.resolveSelectedCauser();

                        this.applyFilters();
                        if (window.notify) {
                            window.notify.success('View Loaded', `Loaded "${view.name}" view`);
                        }
                    },

                    @if(config('activitylog-ui.features.saved_views', true))
                    showSaveViewModal() {
                        window.dispatchEvent(new CustomEvent('show-save-view-modal', {
                            detail: this.filters
                        }));
                    },
                    @endif

                    // Restore persisted state from localStorage
                    restorePersistedState() {
                        const savedPreset = localStorage.getItem('activitylog_date_preset');
                        const savedStartDate = localStorage.getItem('activitylog_start_date');
                        const savedEndDate = localStorage.getItem('activitylog_end_date');
                        const savedSearch = localStorage.getItem('activitylog_search');
                        const savedEventTypes = localStorage.getItem('activitylog_event_types');
                        const savedCauserId = localStorage.getItem('activitylog_causer_id');
                        const savedCauserType = localStorage.getItem('activitylog_causer_type');
                        const savedSubjectType = localStorage.getItem('activitylog_subject_type');
                        const savedSelectedCauser = localStorage.getItem('activitylog_selected_causer');

                        if (savedPreset) this.filters.date_preset = savedPreset;
                        if (savedStartDate) this.filters.start_date = savedStartDate;
                        if (savedEndDate) this.filters.end_date = savedEndDate;
                        if (savedSearch) this.filters.search = savedSearch;
                        if (savedSubjectType) this.filters.subject_type = savedSubjectType;
                        // Kept as-is: causer ids may be UUIDs or ULIDs, which parseInt would mangle.
                        if (savedCauserId) this.filters.causer_id = savedCauserId;
                        // Restored with the id, or a polymorphic causer filter loses
                        // its type and matches every model sharing that id.
                        if (savedCauserType) {
                            this.filters.causer_type = savedCauserType;
                        } else if (savedCauserId && savedSelectedCauser) {
                            // Storage written before causer_type was persisted still
                            // holds the type on the selected-causer object; recover it
                            // rather than silently widening the restored filter.
                            try {
                                this.filters.causer_type = JSON.parse(savedSelectedCauser)?.type ?? null;
                            } catch (e) {
                                this.filters.causer_type = null;
                            }
                        }

                        if (savedEventTypes) {
                            try {
                                this.filters.event_types = JSON.parse(savedEventTypes);
                            } catch (e) {
                                this.filters.event_types = [];
                            }
                        }

                        if (savedSelectedCauser) {
                            try {
                                this.selectedCauser = JSON.parse(savedSelectedCauser);
                            } catch (e) {
                                this.selectedCauser = null;
                            }
                        }
                    },

                    @if(config('activitylog-ui.features.saved_views', true))
                    // Load saved views from the server
                    async loadSavedViews() {
                        try {
                            const response = await fetch('{{ route("activitylog-ui.api.views.index") }}', {
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                                }
                            });

                            const result = await window.ActivitylogUi.parseJsonResponse(response, 'Loading saved views');
                            this.savedViews = result.data || [];
                        } catch (error) {
                            console.error('Failed to load saved views:', error);
                            if (window.notify) {
                                window.notify.error('Error', 'Failed to load saved views');
                            }
                        }
                    },

                    // Delete saved view
                    async deleteSavedView(viewId) {
                        try {
                            const response = await fetch('{{ route("activitylog-ui.api.views.delete") }}', {
                                method: 'DELETE',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                                },
                                body: JSON.stringify({ view_id: viewId })
                            });

                            await window.ActivitylogUi.parseJsonResponse(response, 'Deleting saved view');
                            await this.loadSavedViews();
                            if (window.notify) {
                                window.notify.success('Success', 'View deleted successfully');
                            }
                        } catch (error) {
                            console.error('Delete view error:', error);
                            if (window.notify) {
                                window.notify.error('Error', 'Failed to delete view');
                            }
                        }
                    }
                    @endif
                }
            },

            // Analytics Dashboard Component
            analyticsDashboard() {
                return {
                    // Initialization state
                    initialized: false,

                    loading: true,
                    stats: {},
                    eventTypes: [],
                    topUsers: [],
                    timeline: [],

                    // Period selection
                    selectedPeriod: '7',

                    // Filter state
                    currentFilters: {},
                    hasActiveFilters: false,

                    init() {
                        // Prevent multiple initializations
                        if (this.initialized) return;
                        this.initialized = true;

                        this.loadAnalytics();
                    },

                    async loadAnalytics(filters = {}) {
                        this.loading = true;

                        // Update filter state
                        this.currentFilters = filters;
                        this.hasActiveFilters = Object.keys(filters).some(key => {
                            const value = filters[key];
                            return value !== '' && value !== null && value !== undefined &&
                                   (Array.isArray(value) ? value.length > 0 : true) &&
                                   !(key === 'date_preset' && value === 'all');
                        });

                        try {
                            // Build URL with filters
                            const params = new URLSearchParams();

                            // Add filters to URL parameters
                            Object.keys(filters).forEach(key => {
                                const value = filters[key];
                                if (value !== null && value !== '' && value !== undefined) {
                                    if (Array.isArray(value)) {
                                        value.forEach(item => params.append(`${key}[]`, item));
                                    } else {
                                        params.append(key, value);
                                    }
                                }
                            });

                            const url = '{{ route("activitylog-ui.api.analytics") }}' + (params.toString() ? '?' + params.toString() : '');

                            const response = await fetch(url, {
                                method: 'GET',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                                }
                            });

                            const result = await window.ActivitylogUi.parseJsonResponse(response, 'Loading analytics');
                            const data = result.data || {};

                            this.stats = data.stats || {
                                total: '0',
                                today: '0',
                                active_users: '0',
                                this_week: '0'
                            };

                            this.eventTypes = data.event_types || [];
                            this.topUsers = data.top_users || [];
                            this.timeline = data.timeline || [];
                        } catch (error) {
                            console.error('Error loading analytics:', error);
                            // Fallback to mock data
                            this.stats = {
                                total: '12,543',
                                today: '127',
                                active_users: '45',
                                this_week: '1,234'
                            };

                            this.eventTypes = [
                                { name: 'created', count: 150, percentage: 35 },
                                { name: 'updated', count: 200, percentage: 47 },
                                { name: 'deleted', count: 50, percentage: 12 },
                                { name: 'restored', count: 25, percentage: 6 }
                            ];

                            this.topUsers = [
                                { id: 1, name: 'John Doe', email: 'john@example.com', activity_count: 45 },
                                { id: 2, name: 'Jane Smith', email: 'jane@example.com', activity_count: 38 },
                                { id: 3, name: 'Bob Johnson', email: 'bob@example.com', activity_count: 29 }
                            ];

                            this.timeline = [
                                { date: '2024-01-15', day_name: 'Monday', count: 85, percentage: 70 },
                                { date: '2024-01-14', day_name: 'Sunday', count: 45, percentage: 37 },
                                { date: '2024-01-13', day_name: 'Saturday', count: 120, percentage: 100 },
                                { date: '2024-01-12', day_name: 'Friday', count: 95, percentage: 79 },
                                { date: '2024-01-11', day_name: 'Thursday', count: 110, percentage: 92 },
                                { date: '2024-01-10', day_name: 'Wednesday', count: 88, percentage: 73 },
                                { date: '2024-01-09', day_name: 'Tuesday', count: 75, percentage: 63 }
                            ];
                        } finally {
                            this.loading = false;
                        }
                    }
                }
            }
        };

        // Make components globally available
        window.filterPanel = () => window.AlpineComponents.filterPanel();
        window.analyticsDashboard = () => window.AlpineComponents.analyticsDashboard();

        // Dynamic Activity Type Styling System
        /**
         * Maps a recorded event name onto the small set of things the interface
         * knows how to say about it.
         *
         * This used to build Tailwind class strings by interpolation —
         * `bg-${color}-100`, and a hash of the event name picked one of twelve
         * hues for anything unrecognised. Two problems with that. Classes
         * assembled at runtime cannot be in a compiled stylesheet, so they only
         * ever worked because a CSS compiler was running in the browser. And a
         * rainbow keyed on a string hash looks like information while carrying
         * none: two unrelated events get different colours for no reason, and a
         * reader learns to ignore colour entirely.
         *
         * So colour now means one of four things — something was created,
         * changed, removed, or brought back — and everything else is neutral.
         * The stylesheet holds the palette; this returns the key.
         */
        window.ActivityTypeStyler = {
            // Ordered: the first match wins, so 'restored' is tested before the
            // 'store' that would otherwise catch it.
            semantics: [
                ['created',  ['created', 'create', 'added', 'add', 'registered', 'published', 'approved', 'completed', 'success', 'login', 'started']],
                ['updated',  ['updated', 'update', 'edited', 'edit', 'changed', 'change', 'modified', 'moved', 'renamed', 'info']],
                ['deleted',  ['deleted', 'delete', 'removed', 'remove', 'destroyed', 'revoked', 'rejected', 'failed', 'error', 'cancelled', 'logout']],
                ['restored', ['restored', 'restore', 'reverted', 'undeleted', 'reopened', 'pending', 'warning', 'archived', 'drafted']],
            ],

            iconMapping: {
                'created': 'M12 5v14M5 12h14',
                'updated': 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z',
                'deleted': 'M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2m3 0-1 14a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1L5 6M10 11v6M14 11v6',
                'restored': 'M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5',
                'neutral': 'M12 8h.01M11 12h1v4h1M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
            },

            /**
             * One of 'created', 'updated', 'deleted', 'restored', or '' for
             * anything the interface has no opinion about.
             */
            getEvent(eventType) {
                if (!eventType) {
                    return '';
                }

                const value = String(eventType).toLowerCase();

                for (const [key, keywords] of this.semantics) {
                    if (keywords.some(keyword => value.includes(keyword))) {
                        return key;
                    }
                }

                return '';
            },

            getIcon(eventType) {
                return this.iconMapping[this.getEvent(eventType) || 'neutral'];
            },

            /**
             * Kept for views published before the palette was reworked. They
             * expect a colour name; give them one that still reads correctly
             * rather than a hue chosen by hashing the event name.
             *
             * @deprecated Style from the data-event attribute instead.
             */
            getColor(eventType) {
                return { created: 'green', updated: 'blue', deleted: 'red', restored: 'amber' }[this.getEvent(eventType)] || 'gray';
            },

            /** @deprecated Use data-event with the .al-badge class. */
            getBadgeClasses(eventType) {
                return 'al-badge';
            },

            /** @deprecated Use data-event with the .al-timeline__marker class. */
            getTimelineClasses(eventType) {
                return 'al-timeline__marker';
            },

            /** @deprecated Use data-event with the .al-bar__fill class. */
            getProgressClasses(eventType) {
                return 'al-bar__fill';
            },

            getEventTypeStyling(eventType) {
                const event = this.getEvent(eventType);

                return {
                    event: event,
                    color: this.getColor(eventType),
                    icon: this.getIcon(eventType),
                    badgeClasses: 'al-badge',
                    timelineClasses: 'al-timeline__marker',
                    progressClasses: 'al-bar__fill',
                };
            }
        };

        window.ActivitylogUi = {
            /**
             * Dates are formatted in one place so the table, the timeline and
             * the detail panel agree, and so an unparseable value shows as a
             * dash rather than "Invalid Date".
             */
            _date(value) {
                if (!value) {
                    return null;
                }

                const date = new Date(value);

                return Number.isNaN(date.getTime()) ? null : date;
            },

            formatDate(value) {
                const date = this._date(value);

                if (!date) {
                    return '—';
                }

                const now = new Date();
                const sameYear = date.getFullYear() === now.getFullYear();

                return date.toLocaleDateString(undefined, {
                    day: 'numeric',
                    month: 'short',
                    year: sameYear ? undefined : 'numeric',
                });
            },

            formatTime(value) {
                const date = this._date(value);

                return date ? date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' }) : '';
            },

            /**
             * Renders a logged value for display. Objects and arrays are shown
             * as compact JSON rather than "[object Object]", and null is shown
             * as a word rather than as an empty gap.
             */
            stringify(value) {
                if (value === null) return 'null';
                if (value === undefined) return '—';
                if (typeof value === 'object') return JSON.stringify(value);
                if (value === '') return '(empty)';

                return String(value);
            },

            sameDay(a, b) {
                const x = this._date(a), y = this._date(b);

                return !!x && !!y && x.toDateString() === y.toDateString();
            },

            /** "Today", "Yesterday", else the full date. */
            formatDayHeading(value) {
                const date = this._date(value);

                if (!date) {
                    return 'Unknown date';
                }

                const today = new Date();
                const yesterday = new Date(today);
                yesterday.setDate(yesterday.getDate() - 1);

                if (date.toDateString() === today.toDateString()) return 'Today';
                if (date.toDateString() === yesterday.toDateString()) return 'Yesterday';

                return date.toLocaleDateString(undefined, {
                    weekday: 'short', day: 'numeric', month: 'long',
                    year: date.getFullYear() === today.getFullYear() ? undefined : 'numeric',
                });
            },

            /** The record an activity was recorded against. */
            recordLabel(activity) {
                if (activity.subject_type) {
                    return `${activity.subject_type.split('\\').pop()} #${activity.subject_id}`;
                }

                return (activity.description || '').trim() || activity.event || '—';
            },

            /**
             * The description, but only when it adds something.
             *
             * Spatie's default description is the event name, so on a stock
             * install it repeats the badge beside it word for word. Returning
             * empty here keeps those rows one line tall.
             */
            extraDescription(activity) {
                const description = (activity.description || '').trim();
                const event = (activity.event || '').trim();

                if (!description || description.toLowerCase() === event.toLowerCase()) {
                    return '';
                }

                return description === this.recordLabel(activity) ? '' : description;
            },

            formatDateTime(value) {
                const date = this._date(value);

                return date ? date.toLocaleString() : '—';
            },

            /**
             * "3 minutes ago" and friends, for the timeline where the exact
             * timestamp is a title attribute away.
             */
            formatRelative(value) {
                const date = this._date(value);

                if (!date) {
                    return '—';
                }

                const seconds = Math.round((date.getTime() - Date.now()) / 1000);
                const units = [
                    ['year', 31536000], ['month', 2592000], ['week', 604800],
                    ['day', 86400], ['hour', 3600], ['minute', 60],
                ];

                // Beyond a week "last year" and "2 years ago" stop being useful
                // and start hiding the answer: on an audit trail the reader wants
                // the date. Relative time earns its place only while it is more
                // legible than the timestamp.
                if (Math.abs(seconds) > 7 * 86400) {
                    return this.formatDate(value);
                }

                for (const [unit, size] of units) {
                    if (Math.abs(seconds) >= size) {
                        return new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' })
                            .format(Math.round(seconds / size), unit);
                    }
                }

                return 'just now';
            },

            /**
             * Counted rather than a boolean: the export dialog can open the
             * save-view dialog behind it, and the first one to close must not
             * unlock the page while another is still up.
             */
            _scrollLocks: 0,

            lockScroll(locked) {
                this._scrollLocks = Math.max(0, this._scrollLocks + (locked ? 1 : -1));
                document.documentElement.classList.toggle('al-scroll-locked', this._scrollLocks > 0);
            },

            async parseJsonResponse(response, context) {
                const body = await response.text();
                const contentType = response.headers.get('content-type') || '';
                const preview = body.trim().slice(0, 240);

                // An expired session answers every request with 401 JSON rather
                // than a redirect, which otherwise surfaced as a generic "failed
                // to load" and left the user staring at an empty dashboard.
                if (response.status === 401 || response.status === 419) {
                    window.ActivitylogUi.handleUnauthenticated();
                    throw new Error(`${context} failed: your session has expired.`);
                }

                // A refused parameter carries an explanation worth repeating.
                // Reporting it as a generic failure left the user with a filter
                // the server would not accept and nothing saying which one.
                if (response.status === 422) {
                    let detail = '';

                    try {
                        const parsed = JSON.parse(body);
                        detail = Object.values(parsed.errors || {}).flat().join(' ') || parsed.message || '';
                    } catch (error) {
                        detail = preview;
                    }

                    const rejection = new Error(detail || `${context} was refused.`);
                    rejection.isInvalidInput = true;
                    throw rejection;
                }

                if (!response.ok) {
                    throw new Error(`${context} failed with HTTP ${response.status}${preview ? `: ${preview}` : ''}`);
                }

                if (!contentType.includes('application/json')) {
                    throw new Error(`${context} returned non-JSON content${preview ? `: ${preview}` : ''}`);
                }

                try {
                    return JSON.parse(body);
                } catch (error) {
                    throw new Error(`${context} returned invalid JSON${preview ? `: ${preview}` : ''}`);
                }
            },

            // Guarded so that a page full of parallel requests all failing at once
            // does not fire a reload per request.
            _reauthenticating: false,

            handleUnauthenticated() {
                if (this._reauthenticating) {
                    return;
                }

                this._reauthenticating = true;

                if (window.notify) {
                    window.notify.error('Session expired', 'Reloading so you can sign in again…');
                }

                // Reloading lets Laravel's auth middleware do the redirect, rather
                // than this package guessing at the application's login route.
                setTimeout(() => window.location.reload(), 1200);
            }
        };

        // Global utility functions
        window.exportData = async function(format, filters = {}) {
            try {
                if (window.notify) {
                    window.notify.success('Export Started', `Exporting activities in ${format.toUpperCase()} format...`);
                }

                const response = await fetch('{{ route("activitylog-ui.api.export") }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({
                        format: format,
                        filters: filters
                    })
                });

                const result = await window.ActivitylogUi.parseJsonResponse(response, 'Exporting activities');

                if (result.download_url) {
                    // Direct download
                    window.location.href = result.download_url;
                    if (window.notify) {
                        window.notify.success('Export Complete', `Activities exported as ${format.toUpperCase()} file`);
                    }
                } else if (result.job_id) {
                    if (window.notify) {
                        window.notify.info('Processing', 'This export is large enough to run in the background. Keep this page open and the download will appear here.');
                    }

                    window.pollExportProgress(result.job_id, format);
                }
            } catch (error) {
                console.error('Export error:', error);
                if (window.notify) {
                    window.notify.error('Export Failed', 'Failed to export activities. Please try again.');
                }
            }
        };

        // Job ids currently being polled, so a second click on the same export
        // does not start a second poll loop against it.
        window.exportProgressPolls = new Set();

        // Pending jobs are also written to storage and picked up again on load.
        // A job id lives only on the page that started it, and the server has no
        // way to list a user's jobs — so a refresh, a navigation, or this
        // package's own session-expiry reload used to strand the export: still
        // running, still cached, and no longer reachable by anything. The
        // messages telling the user to reload and check later depend on this.
        window.ActivitylogUiExports = {
            key: 'activitylog_pending_exports',

            all() {
                try {
                    const stored = JSON.parse(localStorage.getItem(this.key) || '[]');
                    if (!Array.isArray(stored)) return [];

                    // Anything older than the poll deadline is not worth resuming.
                    const cutoff = Date.now() - (20 * 60 * 1000);
                    return stored.filter(job => job && job.jobId && (job.startedAt || 0) > cutoff);
                } catch (error) {
                    return [];
                }
            },

            add(jobId, format) {
                try {
                    const jobs = this.all().filter(job => job.jobId !== jobId);
                    jobs.push({ jobId, format, startedAt: Date.now() });
                    localStorage.setItem(this.key, JSON.stringify(jobs));
                } catch (error) {
                    // Storage can be unavailable or full. Losing the ability to
                    // resume is not a reason to fail the export in progress.
                    console.error('Could not record the pending export:', error);
                }
            },

            remove(jobId) {
                try {
                    localStorage.setItem(this.key, JSON.stringify(this.all().filter(job => job.jobId !== jobId)));
                } catch (error) {
                    console.error('Could not clear the pending export:', error);
                }
            },

            resume() {
                this.all().forEach(job => window.pollExportProgress(job.jobId, job.format));
            }
        };

        window.addEventListener('DOMContentLoaded', () => window.ActivitylogUiExports.resume());

        /**
         * Follow a queued export to completion.
         *
         * The job has always written its progress to a cache the API exposes, but
         * nothing ever read it: a queued export told the user it was "processing"
         * and then went quiet forever, leaving the finished file reachable only by
         * an email that is off by default — and unmentioned when it fails to send.
         */
        window.pollExportProgress = async function (jobId, format) {
            if (window.exportProgressPolls.has(jobId)) {
                return;
            }

            window.exportProgressPolls.add(jobId);
            window.ActivitylogUiExports.add(jobId, format);

            const endpoint = '{{ route("activitylog-ui.api.export.progress") }}';
            // Comfortably past the default job timeout (300s) times its retries,
            // so a slow-but-healthy export is not abandoned early.
            const deadline = Date.now() + (20 * 60 * 1000);
            const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));

            let delay = 2000;
            let consecutiveErrors = 0;
            let consecutiveMissing = 0;

            try {
                while (Date.now() < deadline) {
                    await sleep(delay);
                    // Backs off so a long export is not polled every two seconds
                    // for twenty minutes.
                    delay = Math.min(Math.round(delay * 1.4), 15000);

                    let status;

                    try {
                        const response = await fetch(`${endpoint}?job_id=${encodeURIComponent(jobId)}`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                            }
                        });

                        const result = await window.ActivitylogUi.parseJsonResponse(response, 'Checking export progress');
                        status = result.data || {};
                        consecutiveErrors = 0;
                    } catch (error) {
                        console.error('Export progress check failed:', error);

                        // A blip should not abandon an export that is still running;
                        // a persistent failure means nothing useful is coming.
                        if (++consecutiveErrors >= 3) {
                            if (window.notify) {
                                window.notify.warning('Export status unavailable', 'We stopped checking on this export. Reload the page to see whether it finished.');
                            }
                            return;
                        }

                        continue;
                    }

                    if (status.status === 'completed') {
                        if (window.notify) {
                            window.notify.success(
                                'Export ready',
                                `Your ${String(format).toUpperCase()} export has finished.`,
                                {
                                    link: status.download_url ? { href: status.download_url, label: 'Download' } : null,
                                    timeout: 0
                                }
                            );

                            // Recorded by the job when the completion email could not
                            // be sent. Saying so matters: the user was told to expect
                            // one, and the file itself is fine.
                            if (status.notification === 'failed') {
                                window.notify.warning('Email not sent', status.notification_error || 'The export is ready to download, but we could not email you about it.', { timeout: 0 });
                            }
                        }

                        window.ActivitylogUiExports.remove(jobId);

                        return;
                    }

                    if (status.status === 'failed') {
                        if (window.notify) {
                            window.notify.error('Export failed', status.message || 'The export could not be completed.', { timeout: 0 });
                        }

                        window.ActivitylogUiExports.remove(jobId);

                        return;
                    }

                    // The status is written before the job is dispatched and kept for
                    // 24 hours, so this means the cache dropped it — the export may
                    // well still be running, but its progress is no longer knowable.
                    if (status.status === 'not_found') {
                        if (++consecutiveMissing >= 3) {
                            if (window.notify) {
                                window.notify.warning('Export status unavailable', 'We lost track of this export. If it completes you will still receive the email, if notifications are enabled.');
                            }

                            // Dropped rather than kept for a later reload: there is
                            // no status left to poll, so resuming would only repeat
                            // this. A network failure is different, and is kept.
                            window.ActivitylogUiExports.remove(jobId);

                            return;
                        }
                    } else {
                        consecutiveMissing = 0;
                    }
                }

                if (window.notify) {
                    window.notify.warning('Still exporting', 'This export is taking longer than expected. Reload the page later to check on it.');
                }
            } finally {
                window.exportProgressPolls.delete(jobId);
            }
        };

        @if(config('activitylog-ui.features.saved_views', true))
        window.saveView = async function(viewName, filters) {
            if (!viewName.trim()) return;

            try {
                const response = await fetch('{{ route("activitylog-ui.api.views.save") }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({
                        name: viewName,
                        filters: filters
                    })
                });

                const result = await window.ActivitylogUi.parseJsonResponse(response, 'Saving view');
                if (window.notify) {
                    window.notify.success('Success', result.message || `View "${viewName}" saved successfully`);
                }

                // Trigger refresh of saved views
                window.dispatchEvent(new CustomEvent('saved-views-updated'));
            } catch (error) {
                console.error('Save view error:', error);
                if (window.notify) {
                    window.notify.error('Error', 'Failed to save view. Please try again.');
                }
            }
        };
        @endif
    </script>

    @stack('head')
</head>
<body>
<div class="al-shell">
    <header class="al-header">
        <div class="al-container al-header__inner">
            <a href="{{ route('activitylog-ui.dashboard') }}" class="al-brand">
                @if(config('activitylog-ui.ui.logo'))
                    <img src="{{ config('activitylog-ui.ui.logo') }}" alt="" style="height:1.625rem;width:auto;flex:none">
                @else
                    <span class="al-brand__mark" aria-hidden="true">
                        <svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
                            <path d="M2 8h2.5l1.5 4 3-9 1.75 5H14"/>
                        </svg>
                    </span>
                @endif
                <span class="al-brand__text">{{ config('activitylog-ui.ui.brand', 'ActivityLog UI') }}</span>
            </a>

            <div class="al-header__spacer"></div>

            <button type="button"
                    class="al-btn al-btn--ghost al-btn--icon"
                    x-data
                    @click="$store.darkMode.toggle()"
                    :aria-pressed="$store.darkMode.on ? 'true' : 'false'"
                    aria-label="Toggle dark mode">
                <svg x-show="!$store.darkMode.on" x-cloak width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                </svg>
                <svg x-show="$store.darkMode.on" x-cloak width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="4"/>
                    <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
                </svg>
            </button>

            @auth
                <div class="al-user" x-data="{ open: false }" style="position:relative">
                    <button type="button"
                            class="al-btn al-btn--ghost"
                            @click="open = !open"
                            @click.away="open = false"
                            @keydown.escape.window="open = false"
                            :aria-expanded="open ? 'true' : 'false'"
                            aria-haspopup="menu">
                        <span class="al-user__name al-hide-sm">{{ auth()->user()->name ?? auth()->user()->email }}</span>
                        <span class="al-avatar" aria-hidden="true">{{ strtoupper(substr(auth()->user()->name ?? auth()->user()->email ?? '?', 0, 1)) }}</span>
                    </button>

                    <div x-show="open"
                         x-cloak
                         x-transition.opacity.duration.120ms
                         role="menu"
                         class="al-card"
                         style="position:absolute;right:0;top:calc(100% + .375rem);width:15rem;box-shadow:var(--shadow);z-index:40">
                        <div style="padding:.625rem .75rem;border-bottom:1px solid var(--border)">
                            <p class="al-truncate" style="font-weight:600">{{ auth()->user()->name ?? 'Signed in' }}</p>
                            <p class="al-truncate al-small al-muted">{{ auth()->user()->email }}</p>
                        </div>
                        @if(Route::has('logout'))
                            {{-- Guarded: not every application names its logout route 'logout',
                                 and calling route() for one that does not exist threw a
                                 RouteNotFoundException that took the whole page down. --}}
                            <form method="POST" action="{{ route('logout') }}" style="padding:.375rem">
                                @csrf
                                <button type="submit" class="al-btn al-btn--ghost al-btn--block" role="menuitem" style="justify-content:flex-start">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>
                                    </svg>
                                    Sign out
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endauth
        </div>
    </header>

    <main class="al-main">
        <div class="al-container">
            @yield('content')
        </div>
    </main>
</div>

<div x-data="notifications()" x-init="init()" class="al-toasts" role="status" aria-live="polite">
    <template x-for="notification in notifications" :key="notification.id">
        <div x-show="notification.show"
             x-transition.opacity.duration.150ms
             class="al-toast"
             :data-type="notification.type">
            <div class="al-grow">
                <p class="al-toast__title" x-text="notification.title"></p>
                <p class="al-toast__body" x-text="notification.message"></p>
                <a x-show="notification.link"
                   :href="notification.link?.href"
                   @click="remove(notification.id)"
                   class="al-toast__link"
                   x-text="notification.link?.label"></a>
            </div>
            <button type="button"
                    class="al-btn al-btn--ghost al-btn--icon al-btn--sm"
                    @click="remove(notification.id)"
                    aria-label="Dismiss">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <path d="M18 6 6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </template>
</div>

@stack('scripts')

    <script>
        // Global notification system
        function notifications() {
            return {
                notifications: [],

                init() {
                    // Make notification system globally available. The options
                    // argument is optional throughout, so existing two- and
                    // three-argument calls are unaffected.
                    window.notify = {
                        success: (title, message, options) => this.add('success', title, message, options),
                        error: (title, message, options) => this.add('error', title, message, options),
                        warning: (title, message, options) => this.add('warning', title, message, options),
                        info: (title, message, options) => this.add('info', title, message, options)
                    };
                },

                /**
                 * @param options.link    {href, label} rendered as a link in the body
                 * @param options.timeout ms before auto-dismissal; 0 keeps it until dismissed
                 */
                add(type, title, message, options = {}) {
                    const id = Date.now() + Math.random();

                    // Sticky ones do not expire, and a run of failing exports
                    // raises two apiece. Past a handful they cover the page and
                    // bury the newest, which is the one worth reading.
                    //
                    // Only those still shown are counted: removal hides one now
                    // and splices it after the transition, so counting the array
                    // alone meant several additions in a row all picked the same
                    // already-dismissed entry and the cap never bit.
                    const sticky = this.notifications.filter(n => n.sticky && n.show);

                    if (sticky.length >= 5) {
                        this.remove(sticky[0].id);
                    }

                    this.notifications.push({
                        id,
                        type,
                        title,
                        message,
                        link: options.link || null,
                        sticky: options.timeout === 0,
                        show: true
                    });

                    // A finished background export is the one thing here worth
                    // interrupting for, and its notification carries the only link
                    // to the file — so it stays until dismissed rather than
                    // vanishing after five seconds while the user is elsewhere.
                    const timeout = options.timeout === undefined ? 5000 : options.timeout;

                    if (timeout > 0) {
                        setTimeout(() => this.remove(id), timeout);
                    }
                },

                remove(id) {
                    const notification = this.notifications.find(n => n.id === id);

                    if (!notification) {
                        return;
                    }

                    notification.show = false;

                    // Looked up again after the transition: the index captured now
                    // is stale if anything else was dismissed in between, and
                    // splicing it removed a notification the user was still reading.
                    setTimeout(() => {
                        const index = this.notifications.findIndex(n => n.id === id);

                        if (index > -1) {
                            this.notifications.splice(index, 1);
                        }
                    }, 300);
                }
            }
        }

        // Dark mode persistence
        document.addEventListener('alpine:init', () => {
            Alpine.store('darkMode', {
                // Seeded from the class the head script already applied, so the
                // store and the document never disagree on the first frame.
                on: document.documentElement.classList.contains('dark'),

                toggle() {
                    this.apply(!this.on);
                },

                apply(on) {
                    this.on = on;
                    document.documentElement.classList.toggle('dark', on);

                    try {
                        localStorage.setItem('darkMode', on ? 'true' : 'false');
                    } catch (e) {
                        // Storage can be unavailable; the toggle still works for
                        // this page view.
                    }
                },

                init() {
                    this.on = document.documentElement.classList.contains('dark');
                }
            });
        });
    </script>
</body>
</html>
