<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6 border border-gray-200 dark:border-gray-700">
    <!-- Timeline Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Activity Timeline</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    <span x-text="totalActivities"></span> activities in chronological order
                </p>
                <!-- Helpful context about timeline behavior -->
                <div x-show="activities.length > 0 && activities.length < totalActivities" class="mt-2">
                    <div class="inline-flex items-center px-2 py-1 rounded-md bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-300 text-xs">
                        <svg class="w-3 h-3 mr-1 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Timeline shows activities from newest first - scroll down and click "Load More" for older activities
                    </div>
                </div>
                    </div>

            <!-- Initial Load Size Selector -->
                                <div class="flex items-center space-x-2">
                <label for="timelinePerPage" class="text-sm text-gray-700 dark:text-gray-300 font-medium">Initial load:</label>
                <select id="timelinePerPage"
                        x-model="perPage"
                        @change="currentPage = 1; loadActivities(1)"
                        class="border border-gray-300 dark:border-gray-600 rounded-md px-3 py-1 text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm dark:shadow-gray-900/20">
                    @foreach(config('activitylog-ui.ui.per_page_options', [10, 25, 50, 100]) as $option)
                    <option value="{{ $option }}">{{ $option }} activities</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- Timeline -->
    <div class="space-y-4">
        <template x-for="(activity, index) in activities" :key="activity.id">
            <div class="relative group">
                <!-- Timeline line -->
                <div x-show="index < activities.length - 1"
                     class="absolute left-4 top-10 bottom-0 w-0.5 bg-gray-200 dark:bg-gray-700 -z-10"></div>

                <div class="flex items-start space-x-4">
                    <!-- Enhanced timeline icon -->
                    <div class="relative flex-shrink-0">
                        <div class="h-8 w-8 rounded-full flex items-center justify-center ring-4 ring-white dark:ring-gray-800 shadow-sm"
                             :class="window.ActivityTypeStyler?.getTimelineClasses(activity.event) || 'bg-gradient-to-br from-gray-500 to-gray-600 dark:from-gray-400 dark:to-gray-500'">
                            <!-- Dynamic SVG icon -->
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="window.ActivityTypeStyler?.getIcon(activity.event) || 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'"></path>
                            </svg>
                        </div>
                    </div>

                    <!-- Enhanced timeline content -->
                    <div class="min-w-0 flex-1 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 hover:shadow-md dark:hover:shadow-lg dark:hover:shadow-gray-900/20 transition-all duration-200 group-hover:border-gray-300 dark:group-hover:border-gray-600">
                        <!-- Enhanced header with better spacing and contrast -->
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center space-x-3 min-w-0 flex-1">
                                <!-- Enhanced event badge -->
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold flex-shrink-0 border"
                                      :class="window.ActivityTypeStyler?.getBadgeClasses(activity.event) || 'bg-gray-100 dark:bg-gray-900/30 text-gray-800 dark:text-gray-300 border-gray-200 dark:border-gray-700'">
                                    <span x-text="activity.event ? activity.event.toUpperCase() : 'UNKNOWN'"></span>
                                </span>

                                <!-- Enhanced subject info -->
                                <span class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">
                                    <span x-text="activity.subject_type"></span>
                                    <span class="text-gray-500 dark:text-gray-400 font-normal">#<span x-text="activity.subject_id"></span></span>
                                    </span>

                                <!-- Enhanced user info with better styling -->
                                <span x-show="activity.causer" class="text-xs text-gray-600 dark:text-gray-400 flex-shrink-0 bg-gray-50 dark:bg-gray-700/50 px-2 py-1 rounded-md">
                                    by <span class="font-medium text-gray-700 dark:text-gray-300" x-text="activity.causer?.name || 'Unknown'"></span>
                                </span>
                                <span x-show="!activity.causer" class="text-xs text-gray-600 dark:text-gray-400 flex-shrink-0 bg-gray-50 dark:bg-gray-700/50 px-2 py-1 rounded-md font-medium">
                                    by System
                                </span>
                            </div>

                            <!-- Enhanced time and actions section -->
                            <div class="flex items-center space-x-3 flex-shrink-0">
                                <time class="text-xs text-gray-500 dark:text-gray-400 font-medium bg-gray-50 dark:bg-gray-700/50 px-2 py-1 rounded-md"
                                      x-text="new Date(activity.created_at).toLocaleString('en-US', {
                                          month: 'short',
                                          day: 'numeric',
                                          hour: '2-digit',
                                          minute: '2-digit'
                                      })"></time>
                                <button @click="showActivityDetail(activity)"
                                        class="text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 p-1.5 rounded-md hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors duration-150">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Enhanced description -->
                        <div class="text-sm text-gray-700 dark:text-gray-300 font-medium leading-relaxed" x-text="activity.description"></div>

                        <!-- Enhanced properties toggle -->
                        <div x-show="activity.properties && Object.keys(activity.properties).length > 0"
                             x-data="{ expanded: false }"
                             class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                            <button @click="expanded = !expanded"
                                    class="flex items-center text-xs text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 font-medium bg-blue-50 dark:bg-blue-900/20 px-2 py-1 rounded-md transition-colors duration-150">
                                <svg :class="expanded ? 'rotate-90' : ''"
                                     class="w-3 h-3 mr-1 transform transition-transform duration-150"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                                <span x-text="expanded ? 'Hide' : 'Show'"></span> Details
                            </button>

                            <div x-show="expanded"
                                 x-collapse
                                 class="mt-3 space-y-3">
                                <template x-if="activity.properties.old">
                                    <div>
                                        <h5 class="text-xs font-semibold text-red-600 dark:text-red-400 mb-2 flex items-center">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                                        </svg>
                                            Previous Values
                                        </h5>
                                        <pre class="text-xs bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-3 rounded-md text-red-800 dark:text-red-300 overflow-x-auto"
                                             x-text="JSON.stringify(activity.properties.old, null, 2)"></pre>
                                    </div>
                                </template>

                                <template x-if="activity.properties.attributes">
                                    <div>
                                        <h5 class="text-xs font-semibold text-green-600 dark:text-green-400 mb-2 flex items-center">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                            New Values
                                        </h5>
                                        <pre class="text-xs bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-3 rounded-md text-green-800 dark:text-green-300 overflow-x-auto"
                                             x-text="JSON.stringify(activity.properties.attributes, null, 2)"></pre>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
        </div>

    <!-- Enhanced empty State -->
    <div x-show="activities.length === 0" class="text-center py-16">
        <div class="inline-flex items-center justify-center w-20 h-20 mx-auto mb-6 bg-gray-100 dark:bg-gray-700 rounded-full">
            <svg class="w-10 h-10 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No activities found</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 max-w-md mx-auto">No activities match your current filters. Try adjusting your search criteria or date range.</p>
    </div>

    <!-- Enhanced load More section -->
    <div x-show="currentPage < totalPages" class="mt-8 text-center">
        <button @click="loadMoreActivities()"
                :disabled="loading"
                class="inline-flex items-center px-6 py-3 border border-gray-300 dark:border-gray-600 shadow-sm text-base font-medium rounded-lg text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700/50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 hover:shadow-md dark:hover:shadow-lg dark:hover:shadow-gray-900/10">
            <svg x-show="loading" class="animate-spin -ml-1 mr-3 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <svg x-show="!loading" class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            <span x-show="!loading">Load More Activities</span>
            <span x-show="loading">Loading...</span>
        </button>

        <!-- Enhanced progress indicator -->
        <div class="mt-4">
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                Showing <span class="font-semibold text-gray-700 dark:text-gray-300" x-text="activities.length"></span> of <span class="font-semibold text-gray-700 dark:text-gray-300" x-text="totalActivities"></span> activities
            </p>
            <div class="w-full max-w-xs mx-auto bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 dark:from-blue-400 dark:to-blue-500 h-2 rounded-full transition-all duration-300 shadow-sm"
                     :style="`width: ${totalActivities > 0 ? (activities.length / totalActivities) * 100 : 0}%`"></div>
            </div>
        </div>
    </div>

    <!-- Enhanced all loaded message -->
    <div x-show="activities.length > 0 && currentPage >= totalPages" class="mt-8 text-center">
        <div class="inline-flex items-center px-4 py-3 rounded-lg bg-green-100 dark:bg-green-900/30 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-300">
            <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <span class="text-sm font-semibold">All activities loaded</span>
        </div>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
            <span class="font-medium text-gray-700 dark:text-gray-300" x-text="activities.length"></span> activities displayed in total
        </p>
    </div>
</div>

<style>
/* Enhanced timeline styles */
.timeline-view {
    /* Custom styles for timeline view */
}

/* Enhanced hover effects for timeline items */
.timeline-view .group:hover .bg-white {
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

.dark .timeline-view .group:hover .bg-gray-800 {
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.4), 0 4px 6px -2px rgba(0, 0, 0, 0.3);
}

/* Enhanced scrollbar for code blocks */
.dark pre::-webkit-scrollbar {
    height: 6px;
}

.dark pre::-webkit-scrollbar-track {
    background: rgba(75, 85, 99, 0.3);
    border-radius: 3px;
}

.dark pre::-webkit-scrollbar-thumb {
    background: rgba(156, 163, 175, 0.5);
    border-radius: 3px;
}

.dark pre::-webkit-scrollbar-thumb:hover {
    background: rgba(156, 163, 175, 0.7);
}
</style>
