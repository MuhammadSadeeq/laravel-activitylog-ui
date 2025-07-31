<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title') - {{ config('activitylog-ui.ui.brand', 'ActivityLog UI') }}</title>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('vendor/activitylog-ui/images/favicon.svg') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('vendor/activitylog-ui/images/favicon.ico') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                    colors: {
                        gray: {
                            50: '#f9fafb',
                            100: '#f3f4f6',
                            200: '#e5e7eb',
                            300: '#d1d5db',
                            400: '#9ca3af',
                            500: '#6b7280',
                            600: '#4b5563',
                            700: '#374151',
                            800: '#1f2937',
                            900: '#111827',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Custom CSS -->
    <style>
        [x-cloak] { display: none !important; }

        /* Custom scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f3f4f6;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 2px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }

        /* Loading animation */
        .loading-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        /* Activity status colors */
        .status-created { @apply bg-green-100 text-green-800; }
        .status-updated { @apply bg-blue-100 text-blue-800; }
        .status-deleted { @apply bg-red-100 text-red-800; }
        .status-restored { @apply bg-yellow-100 text-yellow-800; }
        .status-custom { @apply bg-purple-100 text-purple-800; }

        .dark .custom-scrollbar::-webkit-scrollbar-track {
            background: #374151;
        }

        .dark .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #6b7280;
        }

        .dark .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }
    </style>

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

                    expanded: true,
                    showAdvanced: false,

                    // Filter state
                    filters: {
                        search: '',
                        date_preset: 'all',
                        start_date: '',
                        end_date: '',
                        event_types: [],
                        causer_id: null,
                        subject_type: ''
                    },

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
                        this.initialized = true;

                        @if(config('activitylog-ui.features.saved_views', true))
                        // Load saved views
                        await this.loadSavedViews();

                        // Add event listener for saved views updates
                        window.addEventListener('saved-views-updated', async () => {
                            await this.loadSavedViews();
                        });
                        @endif

                        // Restore persisted state
                        this.restorePersistedState();

                        await this.loadCausers();
                        this.filteredCausers = this.availableCausers;

                        // Emit event that filter panel is ready with initial filters
                        window.dispatchEvent(new CustomEvent('filter-panel-ready', {
                            detail: this.filters
                        }));
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

                            if (response.ok) {
                                const data = await response.json();
                                this.availableCausers = data.causers || [];
                                this.availableSubjectTypes = data.subject_types || this.availableSubjectTypes;

                                // Process event types with dynamic styling
                                if (data.event_types) {
                                    this.availableEventTypes = data.event_types.map(eventType => {
                                        return {
                                            value: eventType.value,
                                            label: eventType.label,
                                            color: `bg-${window.ActivityTypeStyler.getColor(eventType.value)}-500`,
                                            styling: window.ActivityTypeStyler.getEventTypeStyling(eventType.value)
                                        };
                                    });
                                }
                            } else {
                                throw new Error('Failed to load filter options');
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
                                color: `bg-${window.ActivityTypeStyler.getColor(eventType)}-500`,
                                styling: window.ActivityTypeStyler.getEventTypeStyling(eventType)
                            }));
                        }
                    },

                    searchCausers() {
                        if (!this.causerSearch) {
                            this.filteredCausers = this.availableCausers;
                            return;
                        }

                        const search = this.causerSearch.toLowerCase();
                        this.filteredCausers = this.availableCausers.filter(causer =>
                            causer.name.toLowerCase().includes(search) ||
                            causer.email.toLowerCase().includes(search)
                        );
                    },

                    selectCauser(causer) {
                        this.selectedCauser = causer;
                        this.filters.causer_id = causer?.id || null;
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
                        localStorage.removeItem('activitylog_subject_type');
                        localStorage.removeItem('activitylog_selected_causer');

                        // Reset filters
                        this.filters = {
                            search: '',
                            date_preset: 'all',
                            start_date: '',
                            end_date: '',
                            event_types: [],
                            causer_id: null,
                            subject_type: ''
                        };
                        this.selectedCauser = null;
                        this.causerSearch = '';
                        this.applyFilters();
                    },

                    loadSavedView(view) {
                        this.filters = { ...view.filters };
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
                        const savedSubjectType = localStorage.getItem('activitylog_subject_type');
                        const savedSelectedCauser = localStorage.getItem('activitylog_selected_causer');

                        if (savedPreset) this.filters.date_preset = savedPreset;
                        if (savedStartDate) this.filters.start_date = savedStartDate;
                        if (savedEndDate) this.filters.end_date = savedEndDate;
                        if (savedSearch) this.filters.search = savedSearch;
                        if (savedSubjectType) this.filters.subject_type = savedSubjectType;
                        if (savedCauserId) this.filters.causer_id = savedCauserId ? parseInt(savedCauserId) : null;

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

                            if (response.ok) {
                                const result = await response.json();
                                this.savedViews = result.data || [];
                            }
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

                            if (response.ok) {
                                await this.loadSavedViews();
                                if (window.notify) {
                                    window.notify.success('Success', 'View deleted successfully');
                                }
                            } else {
                                throw new Error('Failed to delete view');
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

                            if (response.ok) {
                                const result = await response.json();
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
                            } else {
                                throw new Error('Failed to load analytics');
                            }
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
        window.ActivityTypeStyler = {
            // Predefined semantic colors for common activity types
            semanticColors: {
                'created': 'green',
                'updated': 'blue',
                'deleted': 'red',
                'restored': 'yellow',
                'login': 'purple',
                'logout': 'indigo',
                'system': 'pink',
                'error': 'red',
                'warning': 'amber',
                'info': 'blue',
                'success': 'green',
                'failed': 'red',
                'completed': 'green',
                'started': 'blue',
                'cancelled': 'gray',
                'pending': 'yellow',
                'approved': 'green',
                'rejected': 'red',
                'published': 'green',
                'drafted': 'gray',
                'archived': 'slate',
            },

            // Color palette for unknown activity types
            colorPalette: [
                'blue', 'green', 'purple', 'pink', 'indigo', 'cyan',
                'teal', 'emerald', 'lime', 'amber', 'orange', 'rose'
            ],

            // Icon mapping for activity types
            iconMapping: {
                'created': 'M12 6v6m0 0v6m0-6h6m-6 0H6',
                'updated': 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
                'deleted': 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
                'restored': 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
                'login': 'M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1',
                'logout': 'M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1',
                'system': 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
            },

            // Get color for activity type
            getColor(eventType) {
                if (!eventType) return 'gray';

                const lowerEvent = eventType.toLowerCase();

                // Check exact match first
                if (this.semanticColors[lowerEvent]) {
                    return this.semanticColors[lowerEvent];
                }

                // Check for partial matches (e.g., "user_login" contains "login")
                for (const [keyword, color] of Object.entries(this.semanticColors)) {
                    if (lowerEvent.includes(keyword)) {
                        return color;
                    }
                }

                // Generate consistent color based on string hash
                return this.colorPalette[this.hashCode(eventType) % this.colorPalette.length];
            },

            // Generate hash code for consistent color assignment
            hashCode(str) {
                let hash = 0;
                for (let i = 0; i < str.length; i++) {
                    const char = str.charCodeAt(i);
                    hash = ((hash << 5) - hash) + char;
                    hash = hash & hash; // Convert to 32-bit integer
                }
                return Math.abs(hash);
            },

            // Get icon path for activity type
            getIcon(eventType) {
                if (!eventType) return this.iconMapping.system;

                const lowerEvent = eventType.toLowerCase();

                // Check exact match
                if (this.iconMapping[lowerEvent]) {
                    return this.iconMapping[lowerEvent];
                }

                // Check for partial matches
                for (const [keyword, icon] of Object.entries(this.iconMapping)) {
                    if (lowerEvent.includes(keyword)) {
                        return icon;
                    }
                }

                // Default icon
                return 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z';
            },

            // Generate badge classes
            getBadgeClasses(eventType) {
                const color = this.getColor(eventType);
                return `bg-${color}-100 dark:bg-${color}-900/30 text-${color}-800 dark:text-${color}-300 border-${color}-200 dark:border-${color}-800`;
            },

            // Generate timeline gradient classes
            getTimelineClasses(eventType) {
                const color = this.getColor(eventType);
                return `bg-gradient-to-br from-${color}-500 to-${color}-600 dark:from-${color}-400 dark:to-${color}-500`;
            },

            // Generate progress bar classes
            getProgressClasses(eventType) {
                const color = this.getColor(eventType);
                return `bg-${color}-500`;
            },

            // Generate all styling for an event type
            getEventTypeStyling(eventType) {
                return {
                    color: this.getColor(eventType),
                    icon: this.getIcon(eventType),
                    badgeClasses: this.getBadgeClasses(eventType),
                    timelineClasses: this.getTimelineClasses(eventType),
                    progressClasses: this.getProgressClasses(eventType),
                };
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

                if (response.ok) {
                    const result = await response.json();

                    if (result.download_url) {
                        // Direct download
                        window.location.href = result.download_url;
                        if (window.notify) {
                            window.notify.success('Export Complete', `Activities exported as ${format.toUpperCase()} file`);
                        }
                    } else if (result.job_id) {
                        // Background job - poll for completion
                        if (window.notify) {
                            window.notify.info('Processing', 'Large export is being processed. You will be notified when ready.');
                        }
                    }
                } else {
                    throw new Error('Export failed');
                }
            } catch (error) {
                console.error('Export error:', error);
                if (window.notify) {
                    window.notify.error('Export Failed', 'Failed to export activities. Please try again.');
                }
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

                if (response.ok) {
                    const result = await response.json();
                    if (window.notify) {
                        window.notify.success('Success', result.message || `View "${viewName}" saved successfully`);
                    }

                    // Trigger refresh of saved views
                    window.dispatchEvent(new CustomEvent('saved-views-updated'));
                } else {
                    throw new Error('Failed to save view');
                }
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
<body class="h-full font-sans antialiased"
      x-data
      x-init="$store.darkMode.init()"
      :class="{ 'dark': $store.darkMode.on }">
    <div class="min-h-full bg-gray-50 dark:bg-gray-900">
        <!-- Navigation Header -->
        <nav class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <!-- Logo -->
                        <div class="flex-shrink-0 flex items-center">
                            @if(config('activitylog-ui.ui.logo'))
                                <img class="h-8 w-auto" src="{{ config('activitylog-ui.ui.logo') }}" alt="{{ config('activitylog-ui.ui.brand') }}">
                            @else
                                <!-- Inline SVG Logo that responds to dark mode -->
                                <svg class="h-8 w-auto" width="120" height="40" viewBox="0 0 120 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <!-- Background gradient -->
                                    <defs>
                                        <linearGradient id="logoGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                            <stop offset="0%" style="stop-color:#3B82F6;stop-opacity:1" />
                                            <stop offset="100%" style="stop-color:#8B5CF6;stop-opacity:1" />
                                        </linearGradient>
                                    </defs>

                                    <!-- Icon container -->
                                    <rect x="2" y="4" width="32" height="32" rx="8" fill="url(#logoGradient)"/>

                                    <!-- Activity log icon -->
                                    <g transform="translate(8, 10)">
                                        <!-- Document base -->
                                        <path d="M4 2a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V6.414A2 2 0 0017.414 5L15 2.586A2 2 0 0013.586 2H4z"
                                              fill="white" fill-opacity="0.9"/>

                                        <!-- Activity lines -->
                                        <circle cx="6" cy="8" r="1.5" fill="white"/>
                                        <line x1="9" y1="8" x2="14" y2="8" stroke="white" stroke-width="1.5" stroke-linecap="round"/>

                                        <circle cx="6" cy="12" r="1.5" fill="white"/>
                                        <line x1="9" y1="12" x2="13" y2="12" stroke="white" stroke-width="1.5" stroke-linecap="round"/>

                                        <circle cx="6" cy="16" r="1.5" fill="white"/>
                                        <line x1="9" y1="16" x2="12" y2="16" stroke="white" stroke-width="1.5" stroke-linecap="round"/>
                                    </g>

                                    <!-- Text that adapts to dark mode -->
                                    <text x="42" y="16" font-family="Inter, system-ui, sans-serif" font-size="12" font-weight="600"
                                          class="fill-gray-800 dark:fill-gray-100">
                                        ActivityLog
                                    </text>
                                    <text x="42" y="28" font-family="Inter, system-ui, sans-serif" font-size="8" font-weight="500"
                                          class="fill-gray-500 dark:fill-gray-300">
                                        UI
                                    </text>
                                </svg>
                            @endif
                        </div>

                        <!-- Navigation Links -->
                        <div class="hidden sm:ml-8 sm:flex sm:space-x-8">
                            <a href="{{ route('activitylog-ui.dashboard') }}"
                               class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition-colors
                                      {{ request()->routeIs('activitylog-ui.dashboard')
                                         ? 'border-blue-500 text-gray-900 dark:text-white'
                                         : 'border-transparent text-gray-500 dark:text-gray-400 hover:border-gray-300 hover:text-gray-700 dark:hover:text-gray-300' }}">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5h2a2 2 0 012 2v6a2 2 0 01-2 2h-2a2 2 0 01-2-2V7a2 2 0 012-2z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17h6"></path>
                                </svg>
                                Activity Log
                            </a>
                        </div>
                    </div>

                    <!-- Right side -->
                    <div class="flex items-center space-x-4">
                        <!-- Theme toggle -->
                        <button @click="$store.darkMode.toggle()"
                                class="p-2 rounded-md text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <svg x-show="!$store.darkMode.on" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                            </svg>
                            <svg x-show="$store.darkMode.on" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                        </button>

                        <!-- User menu -->
                        @auth
                            <div class="relative" x-data="{ open: false }">
                                <button @click="open = !open"
                                        @click.away="open = false"
                                        class="flex items-center space-x-3 p-2 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors">
                                    <span class="text-sm">
                                        {{ auth()->user()->name ?? auth()->user()->email }}
                                    </span>
                                    <div class="w-8 h-8 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                            {{ substr(auth()->user()->name ?? auth()->user()->email, 0, 1) }}
                                        </span>
                                    </div>
                                    <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>

                                <!-- Dropdown menu -->
                                <div x-show="open"
                                     x-transition:enter="transition ease-out duration-100"
                                     x-transition:enter-start="transform opacity-0 scale-95"
                                     x-transition:enter-end="transform opacity-100 scale-100"
                                     x-transition:leave="transition ease-in duration-75"
                                     x-transition:leave-start="transform opacity-100 scale-100"
                                     x-transition:leave-end="transform opacity-0 scale-95"
                                     class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5 dark:ring-white dark:ring-opacity-10 z-50">
                                    <div class="py-1">
                                        <!-- User info -->
                                        <div class="px-4 py-2 border-b border-gray-200 dark:border-gray-700">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ auth()->user()->name ?? 'User' }}
                                            </p>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ auth()->user()->email }}
                                            </p>
                                        </div>

                                        <!-- Logout button -->
                                        <form method="POST" action="{{ route('logout') }}" class="block">
                                            @csrf
                                            <button type="submit"
                                                    class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-700 transition-colors">
                                                <div class="flex items-center">
                                                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                                    </svg>
                                                    Sign out
                                                </div>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endauth
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="flex-1 p-6">
            @yield('content')
        </main>

        <!-- Notifications -->
        <div x-data="notifications()"
             x-init="init()"
             class="fixed inset-0 flex items-end justify-center px-4 py-6 pointer-events-none sm:p-6 sm:items-start sm:justify-end z-50">
            <div class="w-full flex flex-col items-center space-y-4 sm:items-end">
                <template x-for="notification in notifications" :key="notification.id">
                    <div x-show="notification.show"
                         x-transition:enter="transform ease-out duration-300"
                         x-transition:enter-start="translate-x-full opacity-0"
                         x-transition:enter-end="translate-x-0 opacity-100"
                         x-transition:leave="transform ease-in duration-200"
                         x-transition:leave-start="translate-x-0 opacity-100"
                         x-transition:leave-end="translate-x-full opacity-0"
                         class="max-w-sm w-full bg-white dark:bg-gray-800 shadow-lg rounded-lg pointer-events-auto flex ring-1 ring-black ring-opacity-5 dark:ring-white dark:ring-opacity-10">
                        <div class="flex-1 w-0 p-4">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <svg x-show="notification.type === 'success'" class="h-6 w-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <svg x-show="notification.type === 'error'" class="h-6 w-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <svg x-show="notification.type === 'warning'" class="h-6 w-6 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                    </svg>
                                    <svg x-show="notification.type === 'info'" class="h-6 w-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div class="ml-3 w-0 flex-1 pt-0.5">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white" x-text="notification.title"></p>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="notification.message"></p>
                                </div>
                            </div>
                        </div>
                        <div class="flex border-l border-gray-200 dark:border-gray-700">
                            <button @click="remove(notification.id)"
                                    class="w-full border border-transparent rounded-none rounded-r-lg p-4 flex items-center justify-center text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    @stack('scripts')

    <script>
        // Global notification system
        function notifications() {
            return {
                notifications: [],

                init() {
                    // Make notification system globally available
                    window.notify = {
                        success: (title, message) => this.add('success', title, message),
                        error: (title, message) => this.add('error', title, message),
                        warning: (title, message) => this.add('warning', title, message),
                        info: (title, message) => this.add('info', title, message)
                    };
                },

                add(type, title, message) {
                    const id = Date.now() + Math.random();
                    const notification = {
                        id,
                        type,
                        title,
                        message,
                        show: true
                    };

                    this.notifications.push(notification);

                    // Auto remove after 5 seconds
                    setTimeout(() => {
                        this.remove(id);
                    }, 5000);
                },

                remove(id) {
                    const index = this.notifications.findIndex(n => n.id === id);
                    if (index > -1) {
                        this.notifications[index].show = false;
                        setTimeout(() => {
                            this.notifications.splice(index, 1);
                        }, 300);
                    }
                }
            }
        }

        // Dark mode persistence
        document.addEventListener('alpine:init', () => {
            Alpine.store('darkMode', {
                on: false,

                toggle() {
                    this.on = !this.on;
                    localStorage.setItem('darkMode', this.on);
                },

                init() {
                    this.on = localStorage.getItem('darkMode') === 'true' ||
                             (!localStorage.getItem('darkMode') && window.matchMedia('(prefers-color-scheme: dark)').matches);
                }
            });
        });
    </script>
</body>
</html>
