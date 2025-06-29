<div x-data="{ open: false, activity: null }"
     @@show-activity-detail.window="activity = $event.detail; open = true"
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     x-transition:enter="ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">

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
             class="relative w-full max-w-4xl bg-white dark:bg-gray-800 rounded-xl shadow-2xl dark:shadow-gray-900/50 border border-gray-200 dark:border-gray-700">

                <!-- Enhanced header -->
            <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                        <div class="flex items-center space-x-3">
                    <!-- Activity type icon -->
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center shadow-sm"
                             x-show="activity"
                             :class="window.ActivityTypeStyler?.getTimelineClasses(activity?.event) || 'bg-gradient-to-br from-gray-500 to-gray-600 dark:from-gray-400 dark:to-gray-500'">
                            <!-- Dynamic SVG icon -->
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="window.ActivityTypeStyler?.getIcon(activity?.event) || 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'"></path>
                            </svg>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Activity Details</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400" x-text="activity?.description || 'Loading...'"></p>
                    </div>
                </div>

                <button @@click="open = false"
                        class="rounded-lg text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500 p-2 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-150">
                    <span class="sr-only">Close</span>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                            </button>
                    </div>

            <!-- Enhanced content -->
            <div class="p-6 max-h-96 overflow-y-auto custom-scrollbar">
                <template x-if="activity">
                    <div class="space-y-6">
                        <!-- Enhanced basic Information -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 border border-gray-200 dark:border-gray-600">
                                <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                    Event Information
                                </h4>
                                <dl class="space-y-3">
                                                <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Event Type</dt>
                                        <dd class="mt-1">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border"
                                                  :class="window.ActivityTypeStyler?.getBadgeClasses(activity.event) || 'bg-gray-100 dark:bg-gray-900/30 text-gray-800 dark:text-gray-300 border-gray-200 dark:border-gray-700'">
                                                <span x-text="activity.event?.toUpperCase() || 'UNKNOWN'"></span>
                                            </span>
                                        </dd>
                                                </div>

                                                <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Description</dt>
                                        <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100" x-text="activity.description"></dd>
                        </div>

                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Date & Time</dt>
                                        <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100" x-text="new Date(activity.created_at).toLocaleString()"></dd>
                                    </div>
                                </dl>
                        </div>

                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 border border-gray-200 dark:border-gray-600">
                                <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                    </svg>
                                    Context
                                </h4>
                                <dl class="space-y-3">
                                    <div x-show="activity.causer">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Performed by</dt>
                                        <dd class="mt-1 flex items-center">
                                            <div class="w-6 h-6 bg-gradient-to-br from-blue-500 to-purple-600 dark:from-blue-400 dark:to-purple-500 rounded-full flex items-center justify-center mr-2">
                                                <span class="text-xs font-semibold text-white" x-text="activity.causer?.name?.charAt(0) || '?'"></span>
                                            </div>
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="activity.causer?.name || 'Unknown'"></span>
                                        </dd>
                                </div>

                                    <div x-show="!activity.causer">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Performed by</dt>
                                        <dd class="mt-1 flex items-center">
                                            <div class="w-6 h-6 bg-gradient-to-br from-gray-500 to-gray-600 dark:from-gray-400 dark:to-gray-500 rounded-full flex items-center justify-center mr-2">
                                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                </svg>
                                            </div>
                                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">System</span>
                                        </dd>
                                </div>

                                    <div x-show="activity.subject_type">
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Subject</dt>
                                        <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">
                                            <span x-text="activity.subject_type"></span>
                                            <span class="text-gray-500 dark:text-gray-400">#<span x-text="activity.subject_id"></span></span>
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <!-- Enhanced properties -->
                        <div x-show="activity.properties && Object.keys(activity.properties).length > 0"
                             class="bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600">
                            <div class="p-4 border-b border-gray-200 dark:border-gray-600">
                                <h4 class="text-sm font-semibold text-gray-900 dark:text-white flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                    Activity Properties
                                </h4>
                            </div>
                            <div class="p-4">
                                <div class="bg-gray-900 dark:bg-gray-900 rounded-lg p-4 border border-gray-300 dark:border-gray-600">
                                    <pre class="text-sm text-green-400 dark:text-green-300 whitespace-pre-wrap overflow-x-auto font-mono"
                                         x-text="JSON.stringify(activity.properties, null, 2)"></pre>
                                    </div>
                            </div>
                        </div>
                    </div>
                </template>
                </div>

            <!-- Enhanced footer -->
            <div class="flex items-center justify-end px-6 py-4 bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 rounded-b-xl">
                <button @@click="open = false"
                        class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        Close
                    </button>
            </div>
        </div>
    </div>
</div>
