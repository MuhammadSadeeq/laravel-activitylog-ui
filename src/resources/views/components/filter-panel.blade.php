<!-- Filter Panel Component -->
<div x-data="filterPanel()"
     x-init="init();
         $watch('filters.date_preset', value => {
             localStorage.setItem('activitylog_date_preset', value);
         });
         $watch('filters.start_date', value => {
             localStorage.setItem('activitylog_start_date', value || '');
         });
         $watch('filters.end_date', value => {
             localStorage.setItem('activitylog_end_date', value || '');
         });
         $watch('filters.search', value => {
             localStorage.setItem('activitylog_search', value || '');
         });
         $watch('filters.event_types', value => {
             localStorage.setItem('activitylog_event_types', JSON.stringify(value || []));
         });
         $watch('filters.causer_id', value => {
             localStorage.setItem('activitylog_causer_id', value || '');
         });
         $watch('filters.subject_type', value => {
             localStorage.setItem('activitylog_subject_type', value || '');
         });
         $watch('selectedCauser', value => {
             localStorage.setItem('activitylog_selected_causer', JSON.stringify(value || null));
         });"
     @clear-filters.window="clearAllFilters()"
     class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">

    <!-- Delete Confirmation Modal -->
    <div x-data="{ showDeleteModal: false, viewToDelete: null }"
         x-show="showDeleteModal"
         @delete-view.window="viewToDelete = $event.detail; showDeleteModal = true"
         class="fixed inset-0 z-50 overflow-y-auto"
         x-cloak>
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div x-show="showDeleteModal"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 transition-opacity"
                 aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 dark:bg-gray-900 opacity-75"></div>
            </div>

            <!-- Modal panel -->
            <div x-show="showDeleteModal"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                                Delete Saved View
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Are you sure you want to delete this saved view? This action cannot be undone.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button @click="deleteSavedView(viewToDelete); showDeleteModal = false; viewToDelete = null"
                            type="button"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Delete
                    </button>
                    <button @click="showDeleteModal = false; viewToDelete = null"
                            type="button"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Header -->
    <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between min-w-0">
            <div class="flex items-center space-x-2">
                <h3 class="text-base sm:text-lg font-medium text-gray-900 dark:text-white flex-shrink-0">Filters</h3>
                <span x-show="hasActiveFilters"
                      class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                    Active
                </span>
            </div>

            <!-- Ultra Compact Button Group (md to lg) - MacBook Pro 13" -->
            <div class="hidden md:flex lg:hidden items-center space-x-0.5">
                <!-- Toggle Filters (Ultra Compact) -->
                <button @click="expanded = !expanded"
                        class="inline-flex items-center justify-center p-1 border border-gray-300 dark:border-gray-600 shadow-sm rounded text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-1 focus:ring-blue-500 transition-colors"
                        :title="expanded ? 'Hide Filters' : 'Show Filters'">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.207A1 1 0 013 6.5V4z"></path>
                    </svg>
                </button>

                <!-- Clear Filters (Ultra Compact) -->
                <button @click="clearAllFilters()"
                        :disabled="!hasActiveFilters"
                        class="inline-flex items-center justify-center p-1 border border-gray-300 dark:border-gray-600 shadow-sm rounded text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        title="Clear All Filters">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>

                <!-- Saved Views (Ultra Compact) -->
                @if(config('activitylog-ui.features.saved_views', true))
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open"
                            class="inline-flex items-center justify-center p-1 border border-gray-300 dark:border-gray-600 shadow-sm rounded text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-1 focus:ring-blue-500 transition-colors"
                            title="Saved Views">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
                        </svg>
                    </button>

                    <div x-show="open"
                         x-cloak
                         @click.away="open = false"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="transform opacity-0 scale-95"
                         x-transition:enter-end="transform opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="transform opacity-100 scale-100"
                         x-transition:leave-end="transform opacity-0 scale-95"
                         class="origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 dark:ring-white dark:ring-opacity-10 focus:outline-none z-20">
                        <div class="py-1">
                            <template x-for="savedView in savedViews" :key="savedView.id">
                                <div class="flex items-center justify-between hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                    <button @click="loadSavedView(savedView); open = false"
                                            class="flex-grow text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300"
                                            x-text="savedView.name">
                                    </button>
                                    <button @click.stop="$dispatch('delete-view', savedView.id)"
                                            class="px-2 py-2 text-sm text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 transition-colors"
                                            title="Delete view">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </div>
                            </template>

                            <div x-show="savedViews.length === 0"
                                 class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">
                                No saved views
                            </div>

                            <div class="border-t border-gray-100 dark:border-gray-700">
                                <button @click="showSaveViewModal(); open = false"
                                        class="block w-full text-left px-4 py-2 text-sm text-blue-600 dark:text-blue-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                    + Save Current View
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            <!-- Icon-Only Button Group (lg to xl) - Medium Screens -->
            <div class="hidden lg:flex xl:hidden items-center space-x-1">
                <!-- Toggle Filters (Icon Only) -->
                <button @click="expanded = !expanded"
                        class="inline-flex items-center justify-center p-1.5 border border-gray-300 dark:border-gray-600 shadow-sm rounded text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-1 focus:ring-blue-500 transition-colors"
                        :title="expanded ? 'Hide Filters' : 'Show Filters'">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.207A1 1 0 013 6.5V4z"></path>
                    </svg>
                </button>

                <!-- Clear Filters (Icon Only) -->
                <button @click="clearAllFilters()"
                        :disabled="!hasActiveFilters"
                        class="inline-flex items-center justify-center p-1.5 border border-gray-300 dark:border-gray-600 shadow-sm rounded text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        title="Clear All Filters">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>

                <!-- Saved Views (Icon Only) -->
                @if(config('activitylog-ui.features.saved_views', true))
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open"
                            class="inline-flex items-center justify-center p-1.5 border border-gray-300 dark:border-gray-600 shadow-sm rounded text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-1 focus:ring-blue-500 transition-colors"
                            title="Saved Views">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
                        </svg>
                    </button>

                    <div x-show="open"
                         x-cloak
                         @click.away="open = false"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="transform opacity-0 scale-95"
                         x-transition:enter-end="transform opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="transform opacity-100 scale-100"
                         x-transition:leave-end="transform opacity-0 scale-95"
                         class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 dark:ring-white dark:ring-opacity-10 focus:outline-none z-20">
                        <div class="py-1">
                            <template x-for="savedView in savedViews" :key="savedView.id">
                                <div class="flex items-center justify-between hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                    <button @click="loadSavedView(savedView); open = false"
                                            class="flex-grow text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300"
                                            x-text="savedView.name">
                                    </button>
                                    <button @click.stop="$dispatch('delete-view', savedView.id)"
                                            class="px-2 py-2 text-sm text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 transition-colors"
                                            title="Delete view">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </div>
                            </template>

                            <div x-show="savedViews.length === 0"
                                 class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">
                                No saved views
                            </div>

                            <div class="border-t border-gray-100 dark:border-gray-700">
                                <button @click="showSaveViewModal(); open = false"
                                        class="block w-full text-left px-4 py-2 text-sm text-blue-600 dark:text-blue-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                    + Save Current View
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            <!-- Compact Button Group (xl and up) - MacBook Pro 13" -->
            <div class="hidden xl:flex items-center space-x-1.5">
                <!-- Saved Views -->
                @if(config('activitylog-ui.features.saved_views', true))
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open"
                            class="inline-flex items-center px-2 py-1.5 border border-gray-300 dark:border-gray-600 shadow-sm text-xs rounded text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-1 focus:ring-blue-500 transition-colors">
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
                        </svg>
                        <span class="text-xs">Views</span>
                        <svg class="ml-1 -mr-0.5 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>

                    <div x-show="open"
                         x-cloak
                         @click.away="open = false"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="transform opacity-0 scale-95"
                         x-transition:enter-end="transform opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="transform opacity-100 scale-100"
                         x-transition:leave-end="transform opacity-0 scale-95"
                         class="origin-top-left absolute left-0 mt-2 w-56 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 dark:ring-white dark:ring-opacity-10 focus:outline-none z-20">
                        <div class="py-1">
                            <template x-for="savedView in savedViews" :key="savedView.id">
                                <div class="flex items-center justify-between hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                    <button @click="loadSavedView(savedView); open = false"
                                            class="flex-grow text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300"
                                            x-text="savedView.name">
                                    </button>
                                    <button @click.stop="$dispatch('delete-view', savedView.id)"
                                            class="px-2 py-2 text-sm text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 transition-colors"
                                            title="Delete view">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </div>
                            </template>

                            <div x-show="savedViews.length === 0"
                                 class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">
                                No saved views
                            </div>

                            <div class="border-t border-gray-100 dark:border-gray-700">
                                <button @click="showSaveViewModal(); open = false"
                                        class="block w-full text-left px-4 py-2 text-sm text-blue-600 dark:text-blue-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                    + Save Current View
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Toggle Filters -->
                <button @click="expanded = !expanded"
                        class="inline-flex items-center px-2 py-1.5 border border-gray-300 dark:border-gray-600 shadow-sm text-xs rounded text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-1 focus:ring-blue-500 transition-colors">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.207A1 1 0 013 6.5V4z"></path>
                    </svg>
                    <span class="text-xs" x-text="expanded ? 'Hide' : 'Show'"></span>
                </button>

                <!-- Clear Filters -->
                <button @click="clearAllFilters()"
                        :disabled="!hasActiveFilters"
                        class="inline-flex items-center px-2 py-1.5 border border-gray-300 dark:border-gray-600 shadow-sm text-xs rounded text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    <span class="text-xs">Clear</span>
                </button>
            </div>

            <!-- Small Screen Button Group (sm to md) - Tablet Portrait -->
            <div class="hidden sm:flex md:hidden items-center space-x-1">
                <!-- Toggle Filters (Small Screen) -->
                <button @click="expanded = !expanded"
                        class="inline-flex items-center justify-center p-1.5 border border-gray-300 dark:border-gray-600 shadow-sm rounded text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-1 focus:ring-blue-500 transition-colors"
                        :title="expanded ? 'Hide Filters' : 'Show Filters'">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.207A1 1 0 013 6.5V4z"></path>
                    </svg>
                </button>

                <!-- Clear Filters (Small Screen) -->
                <button @click="clearAllFilters()"
                        :disabled="!hasActiveFilters"
                        class="inline-flex items-center justify-center p-1.5 border border-gray-300 dark:border-gray-600 shadow-sm rounded text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        title="Clear All Filters">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>

                <!-- Saved Views (Small Screen) -->
                @if(config('activitylog-ui.features.saved_views', true))
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open"
                            class="inline-flex items-center justify-center p-1.5 border border-gray-300 dark:border-gray-600 shadow-sm rounded text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-1 focus:ring-blue-500 transition-colors"
                            title="Saved Views">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
                        </svg>
                    </button>

                    <div x-show="open"
                         x-cloak
                         @click.away="open = false"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="transform opacity-0 scale-95"
                         x-transition:enter-end="transform opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="transform opacity-100 scale-100"
                         x-transition:leave-end="transform opacity-0 scale-95"
                         class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 dark:ring-white dark:ring-opacity-10 focus:outline-none z-20">
                        <div class="py-1">
                            <template x-for="savedView in savedViews" :key="savedView.id">
                                <div class="flex items-center justify-between hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                    <button @click="loadSavedView(savedView); open = false"
                                            class="flex-grow text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300"
                                            x-text="savedView.name">
                                    </button>
                                    <button @click.stop="$dispatch('delete-view', savedView.id)"
                                            class="px-2 py-2 text-sm text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 transition-colors"
                                            title="Delete view">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </div>
                            </template>

                            <div x-show="savedViews.length === 0"
                                 class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">
                                No saved views
                            </div>

                            <div class="border-t border-gray-100 dark:border-gray-700">
                                <button @click="showSaveViewModal(); open = false"
                                        class="block w-full text-left px-4 py-2 text-sm text-blue-600 dark:text-blue-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                    + Save Current View
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            <!-- Mobile Button Group -->
            <div class="flex sm:hidden items-center space-x-1">
                <!-- Toggle Filters (Mobile) -->
                <button @click="expanded = !expanded"
                        class="inline-flex items-center justify-center p-2 border border-gray-300 dark:border-gray-600 shadow-sm rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                        :title="expanded ? 'Hide Filters' : 'Show Filters'">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.207A1 1 0 013 6.5V4z"></path>
                    </svg>
                </button>

                <!-- Clear Filters (Mobile) -->
                <button @click="clearAllFilters()"
                        :disabled="!hasActiveFilters"
                        class="inline-flex items-center justify-center p-2 border border-gray-300 dark:border-gray-600 shadow-sm rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        title="Clear Filters">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>

                <!-- Mobile Actions Menu -->
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open"
                            class="inline-flex items-center justify-center p-2 border border-gray-300 dark:border-gray-600 shadow-sm rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                            title="More Actions">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                        </svg>
                    </button>

                    <div x-show="open"
                         x-cloak
                         @click.away="open = false"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="transform opacity-0 scale-95"
                         x-transition:enter-end="transform opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="transform opacity-100 scale-100"
                         x-transition:leave-end="transform opacity-0 scale-95"
                         class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 dark:ring-white dark:ring-opacity-10 focus:outline-none z-20">
                        <div class="py-1">
                            <!-- Saved Views Section -->
                            @if(config('activitylog-ui.features.saved_views', true))
                            <div class="px-4 py-2 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Saved Views
                            </div>

                            <template x-for="savedView in savedViews" :key="savedView.id">
                                <div class="flex items-center justify-between hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                    <button @click="loadSavedView(savedView); open = false"
                                            class="flex-grow text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300"
                                            x-text="savedView.name">
                                    </button>
                                    <button @click.stop="$dispatch('delete-view', savedView.id)"
                                            class="px-2 py-2 text-sm text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 transition-colors"
                                            title="Delete view">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </div>
                            </template>

                            <div x-show="savedViews.length === 0"
                                 class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">
                                No saved views
                            </div>

                            <div class="border-t border-gray-100 dark:border-gray-700">
                                <button @click="showSaveViewModal(); open = false"
                                        class="block w-full text-left px-4 py-2 text-sm text-blue-600 dark:text-blue-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                    <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                    Save Current View
                                </button>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Content -->
    <div x-show="expanded"
         x-collapse
         class="px-4 sm:px-6 py-4 space-y-4 sm:space-y-6">

        <!-- Search -->
        <div>
            <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Search
            </label>
            <div class="relative">
                <input type="text"
                       id="search"
                       x-model="filters.search"
                       @input.debounce.300ms="applyFilters()"
                       placeholder="Search activities, users, descriptions..."
                       class="block w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">

                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Filter Grid -->
        <div class="grid grid-cols-1 gap-4 sm:gap-6">

            <!-- Date Range -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Date Range
                </label>

                    <!-- Date Presets -->
                <div class="flex flex-wrap gap-1 sm:gap-2 mb-3">
                        <template x-for="preset in datePresets" :key="preset.value">
                            <button @click="setDatePreset(preset.value)"
                                :class="{
                                    'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 border-blue-300 dark:border-blue-700': filters.date_preset === preset.value,
                                    'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600': filters.date_preset !== preset.value
                                }"
                                class="inline-flex items-center px-2 sm:px-2.5 py-0.5 rounded-full text-xs font-medium border transition-colors whitespace-nowrap"
                                x-text="preset.label">
                        </button>
                        </template>
                    </div>

                    <!-- Custom Date Range -->
                <div x-show="filters.date_preset === 'custom'"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 transform scale-95"
                     x-transition:enter-end="opacity-100 transform scale-100"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 transform scale-100"
                     x-transition:leave-end="opacity-0 transform scale-95"
                     class="grid grid-cols-1 sm:grid-cols-2 gap-2"
                     x-cloak>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">From Date</label>
                        <input type="date"
                               x-model="filters.start_date"
                               @change="applyFilters()"
                               class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">To Date</label>
                        <input type="date"
                               x-model="filters.end_date"
                               @change="applyFilters()"
                               class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    </div>
                </div>
            </div>

            <!-- Row for Event Types and Causer (side by side on larger screens) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
            <!-- Event Types -->
            <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Event Types
                    </label>
                                                            <div class="space-y-2 max-h-32 sm:max-h-40 overflow-y-auto custom-scrollbar">
                    <template x-for="eventType in availableEventTypes" :key="eventType.value">
                            <label class="flex items-center">
                                <input type="checkbox"
                                       :value="eventType.value"
                                       x-model="filters.event_types"
                                       @change="applyFilters()"
                                       class="rounded border-gray-300 dark:border-gray-600 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300" x-text="eventType.label"></span>
                                <span :class="eventType.color" class="ml-2 inline-block w-2 h-2 rounded-full"></span>
                        </label>
                    </template>
                </div>
            </div>

            <!-- Causer Filter -->
            <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        User/Causer
                    </label>
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open"
                            class="relative w-full bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm pl-3 pr-10 py-2 text-left text-gray-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        <span class="block truncate" x-text="selectedCauserText || 'All users'"></span>
                        <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </span>
                    </button>

                    <div x-show="open"
                         x-cloak
                         @click.away="open = false"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="transform opacity-0 scale-95"
                         x-transition:enter-end="transform opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="transform opacity-100 scale-100"
                         x-transition:leave-end="transform opacity-0 scale-95"
                         class="absolute z-10 mt-1 w-full bg-white dark:bg-gray-800 shadow-lg max-h-60 rounded-md py-1 ring-1 ring-black ring-opacity-5 dark:ring-white dark:ring-opacity-10 overflow-auto focus:outline-none sm:text-sm">

                        <!-- Search within users -->
                        <div class="sticky top-0 z-10 bg-white dark:bg-gray-800 px-3 py-2 border-b border-gray-200 dark:border-gray-700">
                            <input type="text"
                                   x-model="causerSearch"
                                   @input.debounce.300ms="searchCausers()"
                                   placeholder="Search users..."
                                   class="block w-full px-3 py-1 border border-gray-300 dark:border-gray-600 rounded-md text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <button @click="selectCauser(null); open = false"
                                class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                            All users
                        </button>

                        <template x-for="causer in filteredCausers" :key="causer.id">
                            <button @click="selectCauser(causer); open = false"
                                    class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                <div class="flex flex-col">
                                    <span x-text="causer.name"></span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400" x-text="causer.email"></span>
                            </div>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
            </div>

        </div>

        <!-- Advanced Filters Toggle -->
        <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
            <button @click="showAdvanced = !showAdvanced"
                    class="inline-flex items-center text-sm text-blue-600 dark:text-blue-400 hover:text-blue-500 dark:hover:text-blue-300 transition-colors">
                <svg :class="showAdvanced ? 'rotate-90' : ''"
                     class="w-4 h-4 mr-1 transform transition-transform"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
                <span x-text="showAdvanced ? 'Hide Advanced Filters' : 'Show Advanced Filters'"></span>
            </button>
        </div>

        <!-- Advanced Filters -->
        <div x-show="showAdvanced"
             x-collapse
             class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">

            <!-- Subject Type -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Subject Type
                </label>
                <select x-model="filters.subject_type"
                        @change="applyFilters()"
                        class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm min-w-[200px]">
                    <option value="">All Types</option>
                    <template x-for="subjectType in availableSubjectTypes" :key="subjectType.value">
                        <option :value="subjectType.value" x-text="subjectType.label"></option>
                    </template>
                </select>
            </div>

            <!-- Additional advanced filters can be added here -->

        </div>
    </div>
</div>
