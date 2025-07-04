<!-- Export Modal -->
<div x-data="{
        open: false,
        selectedFormat: '',
        currentFilters: {},

        init() {

        }
     }"
     @@show-export-modal.window="open = true; currentFilters = $event.detail?.filters || {}"
     @@keydown.escape.window="open = false"
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto">

    <!-- Enhanced backdrop -->
    <div class="fixed inset-0 bg-black/60 dark:bg-black/80 backdrop-blur-sm transition-opacity"
         @@click="open = false"></div>

    <!-- Modal -->
    <div class="flex min-h-full items-center justify-center p-4">
        <div x-show="open"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="relative w-full max-w-lg bg-white dark:bg-gray-800 rounded-xl shadow-2xl dark:shadow-gray-900/50 border border-gray-200 dark:border-gray-700">

            <!-- Enhanced header -->
            <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-green-600 dark:from-green-400 dark:to-green-500 rounded-lg flex items-center justify-center shadow-sm">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M7 7h10a2 2 0 012 2v8a2 2 0 01-2 2H7a2 2 0 01-2-2V9a2 2 0 012-2z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Export Activities</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Download your activity data</p>
                    </div>
                </div>
                <button @@click="open = false"
                        class="rounded-lg text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 p-2 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-150">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Enhanced content -->
            <div class="p-6">
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                    Choose the format for exporting your activity data. All current filters will be applied to the export.
                </p>

                @php($enabledFormats = config('activitylog-ui.exports.enabled_formats', []))
                <div class="space-y-3">
                    @if(in_array('xlsx', $enabledFormats))
                    <!-- Excel Export Option -->
                    <button @@click="selectedFormat = 'xlsx'; exportData('xlsx', currentFilters)"
                            :class="selectedFormat === 'xlsx' ? 'ring-2 ring-green-500 dark:ring-green-400 bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800' : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700/50'"
                            class="w-full flex items-center justify-between p-4 border rounded-lg transition-all duration-200 group">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-green-600 dark:from-green-400 dark:to-green-500 rounded-lg flex items-center justify-center mr-4 shadow-sm">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <div class="text-left">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Excel Spreadsheet</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Structured data with formatting (.xlsx)</p>
                                <p class="text-xs text-green-600 dark:text-green-400 mt-1">Recommended for analysis</p>
                            </div>
                        </div>
                        <svg class="h-5 w-5 text-gray-400 group-hover:text-gray-500 dark:group-hover:text-gray-300 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>
                    @endif

                    @if(in_array('csv', $enabledFormats))
                    <!-- CSV Export Option -->
                    <button @@click="selectedFormat = 'csv'; exportData('csv', currentFilters)"
                            :class="selectedFormat === 'csv' ? 'ring-2 ring-blue-500 dark:ring-blue-400 bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800' : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700/50'"
                            class="w-full flex items-center justify-between p-4 border rounded-lg transition-all duration-200 group">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 rounded-lg flex items-center justify-center mr-4 shadow-sm">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <div class="text-left">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">CSV File</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Comma-separated values (.csv)</p>
                                <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">Compatible with all software</p>
                            </div>
                        </div>
                        <svg class="h-5 w-5 text-gray-400 group-hover:text-gray-500 dark:group-hover:text-gray-300 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>
                    @endif

                    @if(in_array('pdf', $enabledFormats))
                    <!-- PDF Export Option -->
                    <button @@click="selectedFormat = 'pdf'; exportData('pdf', currentFilters)"
                            :class="selectedFormat === 'pdf' ? 'ring-2 ring-red-500 dark:ring-red-400 bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800' : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700/50'"
                            class="w-full flex items-center justify-between p-4 border rounded-lg transition-all duration-200 group">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-gradient-to-br from-red-500 to-red-600 dark:from-red-400 dark:to-red-500 rounded-lg flex items-center justify-center mr-4 shadow-sm">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <div class="text-left">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">PDF Report</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Formatted report document (.pdf)</p>
                                <p class="text-xs text-red-600 dark:text-red-400 mt-1">Great for printing & sharing</p>
                            </div>
                        </div>
                        <svg class="h-5 w-5 text-gray-400 group-hover:text-gray-500 dark:group-hover:text-gray-300 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>
                    @endif

                    @if(in_array('json', $enabledFormats))
                    <!-- JSON Export Option -->
                    <button @@click="selectedFormat = 'json'; exportData('json', currentFilters)"
                            :class="selectedFormat === 'json' ? 'ring-2 ring-purple-500 dark:ring-purple-400 bg-purple-50 dark:bg-purple-900/20 border-purple-200 dark:border-purple-800' : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700/50'"
                            class="w-full flex items-center justify-between p-4 border rounded-lg transition-all duration-200 group">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 rounded-lg flex items-center justify-center mr-4 shadow-sm">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M7 7h10a2 2 0 012 2v8a2 2 0 01-2 2H7a2 2 0 01-2-2V9a2 2 0 012-2z"></path>
                                </svg>
                            </div>
                            <div class="text-left">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">JSON Data</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Machine-readable format (.json)</p>
                                <p class="text-xs text-purple-600 dark:text-purple-400 mt-1">Perfect for developers</p>
                            </div>
                        </div>
                        <svg class="h-5 w-5 text-gray-400 group-hover:text-gray-500 dark:group-hover:text-gray-300 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>
                    @endif
                </div>

                <!-- Active Filters Display -->
                <div x-show="Object.keys(currentFilters).length > 0"
                     class="mt-4 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.707A1 1 0 013 7V4z"></path>
                        </svg>
                        <div class="flex-1">
                            <h4 class="text-sm font-semibold text-amber-900 dark:text-amber-300">Active Filters</h4>
                            <p class="text-xs text-amber-700 dark:text-amber-400 mt-1">The following filters will be applied to your export:</p>
                            <div class="mt-2 flex flex-wrap gap-1">
                                <template x-for="[key, value] in Object.entries(currentFilters)" :key="key">
                                    <span x-show="value && value !== '' && !(Array.isArray(value) && value.length === 0)"
                                          class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-800 dark:text-amber-200">
                                        <span x-text="key.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())"></span>:
                                        <span x-text="Array.isArray(value) ? value.join(', ') : value" class="ml-1 font-normal"></span>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- No Filters Warning -->
                <div x-show="Object.keys(currentFilters).length === 0 || !Object.values(currentFilters).some(v => v && v !== '' && !(Array.isArray(v) && v.length === 0))"
                     class="mt-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-red-600 dark:text-red-400 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 13.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                        <div>
                            <h4 class="text-sm font-semibold text-red-900 dark:text-red-300">No Filters Applied</h4>
                            <p class="text-xs text-red-700 dark:text-red-400 mt-1">
                                This will export ALL activity records, which may take a long time and create a large file. Consider applying filters first.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Export Info -->
                <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <h4 class="text-sm font-semibold text-blue-900 dark:text-blue-300">Export Information</h4>
                            <p class="text-xs text-blue-700 dark:text-blue-400 mt-1">
                                The export will include only activities that match your filters. Large exports may take a few moments to process.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced footer -->
            <div class="flex items-center justify-end px-6 py-4 bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 rounded-b-xl space-x-3">
                <button @@click="open = false"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-500 dark:hover:text-gray-400 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>
