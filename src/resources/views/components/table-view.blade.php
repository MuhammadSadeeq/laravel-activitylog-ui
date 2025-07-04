<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden border border-gray-200 dark:border-gray-700">
    <!-- Table Header -->
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-base sm:text-lg font-medium text-gray-900 dark:text-white">Activity Log</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    <span x-text="totalActivities"></span> activities found
                    <span x-show="hasActiveFilters" class="text-blue-600 dark:text-blue-400 font-medium">(filtered)</span>
                </p>
                </div>

                <!-- Per Page Selector -->
            <div class="flex items-center space-x-2">
                <label for="perPage" class="text-sm text-gray-700 dark:text-gray-300">Show:</label>
                <select id="perPage"
                        x-model="perPage"
                        @change="currentPage = 1; loadActivities(1)"
                        class="border border-gray-300 dark:border-gray-600 rounded-md px-3 py-1 text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm dark:shadow-gray-900/20">
                    @foreach(config('activitylog-ui.ui.per_page_options', [10, 25, 50, 100]) as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
                <span class="text-sm text-gray-700 dark:text-gray-300">per page</span>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900/50">
                <tr>
                    <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Event
                    </th>
                    <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Description
                    </th>
                    <th class="hidden md:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Subject
                    </th>
                    <th class="hidden sm:table-cell px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        User
                    </th>
                    <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Date
                    </th>
                    <th class="px-3 sm:px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>

            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                <template x-for="activity in activities" :key="activity.id">
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-all duration-150 group">
                        <!-- Event Type -->
                        <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2 sm:px-2.5 py-0.5 rounded-full text-xs font-medium border"
                                  :class="window.ActivityTypeStyler?.getBadgeClasses(activity.event) || 'bg-gray-100 dark:bg-gray-900/30 text-gray-800 dark:text-gray-300 border-gray-200 dark:border-gray-700'">
                                <span class="w-1.5 h-1.5 mr-1 sm:mr-1.5 rounded-full"
                                      :class="`bg-${window.ActivityTypeStyler?.getColor(activity.event) || 'gray'}-500 dark:bg-${window.ActivityTypeStyler?.getColor(activity.event) || 'gray'}-400`"></span>
                                <span class="hidden sm:inline" x-text="activity.event || 'unknown'"></span>
                                <span class="sm:hidden" x-text="activity.event ? activity.event.charAt(0).toUpperCase() : '?'"></span>
                            </span>
                        </td>

                        <!-- Description -->
                        <td class="px-3 sm:px-6 py-4">
                            <div class="text-sm text-gray-900 dark:text-gray-100">
                                <div class="font-medium" x-text="activity.description"></div>
                                <!-- Show subject and user info on mobile -->
                                <div class="sm:hidden mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    <span class="font-medium" x-text="activity.subject_type"></span><span class="text-gray-400 dark:text-gray-500">#</span><span x-text="activity.subject_id"></span>
                                    <span x-show="activity.causer" class="text-gray-400 dark:text-gray-500"> • </span><span x-show="activity.causer" x-text="activity.causer?.name || 'Unknown'"></span>
                                </div>
                            </div>
                        </td>

                        <!-- Subject (hidden on mobile) -->
                        <td class="hidden md:table-cell px-3 sm:px-6 py-4 whitespace-nowrap">
                            <div class="text-sm">
                                <span class="font-medium text-gray-900 dark:text-gray-100" x-text="activity.subject_type"></span>
                                <span class="text-gray-500 dark:text-gray-400">#<span x-text="activity.subject_id"></span></span>
                            </div>
                        </td>

                        <!-- User (hidden on small mobile) -->
                        <td class="hidden sm:table-cell px-3 sm:px-6 py-4 whitespace-nowrap">
                            <div x-show="activity.causer" class="flex items-center">
                                <div class="flex-shrink-0 h-6 sm:h-8 w-6 sm:w-8">
                                    <div class="h-6 sm:h-8 w-6 sm:w-8 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 dark:from-blue-400 dark:to-purple-500 flex items-center justify-center shadow-sm">
                                        <span class="text-xs font-medium text-white"
                                              x-text="activity.causer?.name?.charAt(0) || '?'"></span>
                                    </div>
                                </div>
                                <div class="ml-2 sm:ml-3">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="activity.causer?.name || 'Unknown'"></div>
                                    <div class="hidden sm:block text-sm text-gray-500 dark:text-gray-400" x-text="activity.causer?.email || ''"></div>
                                </div>
                            </div>
                            <div x-show="!activity.causer" class="text-sm text-gray-500 dark:text-gray-400 flex items-center">
                                <div class="flex-shrink-0 h-6 sm:h-8 w-6 sm:w-8 mr-2 sm:mr-3">
                                    <div class="h-6 sm:h-8 w-6 sm:w-8 rounded-full bg-gradient-to-br from-gray-500 to-gray-600 dark:from-gray-400 dark:to-gray-500 flex items-center justify-center shadow-sm">
                                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                    </div>
                                </div>
                                System
                            </div>
                        </td>

                        <!-- Date -->
                        <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            <div class="sm:hidden text-xs font-medium" x-text="new Date(activity.created_at).toLocaleDateString('en-US', {month: 'short', day: 'numeric'})"></div>
                            <div class="hidden sm:block font-medium" x-text="new Date(activity.created_at).toLocaleDateString()"></div>
                            <div class="hidden sm:block text-xs text-gray-400 dark:text-gray-500 mt-0.5" x-text="new Date(activity.created_at).toLocaleTimeString()"></div>
                        </td>

                        <!-- Actions -->
                        <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <button @click="showActivityDetail(activity)"
                                    class="text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 transition-colors duration-150 p-1 rounded-md hover:bg-blue-50 dark:hover:bg-blue-900/20">
                                <span class="hidden sm:inline font-medium">View Details</span>
                                <svg class="sm:hidden w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>

        <!-- Empty State with Error -->
        <div x-show="!loading && activities.length === 0"
             class="text-center py-12 bg-white dark:bg-gray-800">
            <div class="inline-flex items-center justify-center w-16 h-16 mx-auto mb-4 bg-red-100 dark:bg-red-900/20 rounded-full">
                <svg class="w-8 h-8 text-red-500 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <h3 class="text-base font-medium text-gray-900 dark:text-white mb-1">No activities found</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">The requested page could not be loaded. Please try a different page number.</p>
            <button @click="pageInput = currentPage; changePage(currentPage)"
                    class="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Return to Previous Page
            </button>
        </div>
    </div>

    <!-- Pagination -->
    <div x-show="totalPages > 1"
         x-data="{
             pageInput: currentPage,
             loading: false,
             error: false,
             errorMessage: '',

             getPageNumbers() {
                 const pages = [];
                 const sideNumbers = 2;

                 // Always add first page
                 pages.push(1);

                 let start = Math.max(2, currentPage - sideNumbers);
                 let end = Math.min(totalPages - 1, currentPage + sideNumbers);

                 // Add dots after 1 if needed
                 if (start > 2) {
                     pages.push('dots1');
                 }

                 // Add all pages in range
                 for (let i = start; i <= end; i++) {
                     pages.push(i);
                 }

                 // Add dots before last page if needed
                 if (end < totalPages - 1) {
                     pages.push('dots2');
                 }

                 // Add last page if we have more than one page
                 if (totalPages > 1) {
                     pages.push(totalPages);
                 }

                 return pages;
             },

             async goToPage(page) {
                 const targetPage = parseInt(page);
                 if (isNaN(targetPage) || targetPage < 1 || targetPage > totalPages || targetPage === currentPage) {
                     this.pageInput = currentPage;
                     return;
                 }

                 this.loading = true;
                 this.error = false;

                 try {
                     changePage(targetPage);
                     await new Promise(resolve => setTimeout(resolve, 300));

                     if (!activities || activities.length === 0) {
                         throw new Error('No activities found on this page');
                     }

                     this.pageInput = targetPage;
                 } catch (err) {
                     this.error = true;
                     this.errorMessage = err.message;
                     this.pageInput = currentPage;
                     changePage(currentPage);
                 } finally {
                     this.loading = false;
                 }
             }
         }"
         x-init="$watch('currentPage', () => getPageNumbers())"
         class="relative bg-white dark:bg-gray-800 px-4 py-3 border-t border-gray-200 dark:border-gray-700 sm:px-6">

         <!-- Loading Overlay -->
         <div x-show="loading"
              x-transition:enter="transition ease-out duration-200"
              x-transition:enter-start="opacity-0"
              x-transition:enter-end="opacity-100"
              x-transition:leave="transition ease-in duration-150"
              x-transition:leave-start="opacity-100"
              x-transition:leave-end="opacity-0"
              class="absolute inset-0 bg-white/70 dark:bg-gray-800/70 flex items-center justify-center z-10">
             <div class="flex items-center space-x-3 px-4 py-2 rounded-lg bg-white dark:bg-gray-700 shadow-sm">
                 <div class="animate-spin rounded-full h-5 w-5 border-2 border-blue-600 dark:border-blue-400 border-t-transparent"></div>
                 <span class="text-sm text-gray-600 dark:text-gray-300">Loading page <span x-text="pageInput"></span>...</span>
             </div>
         </div>

         <!-- Error Message -->
         <div x-show="error"
              x-transition:enter="transition ease-out duration-200"
              x-transition:enter-start="opacity-0 transform -translate-y-2"
              x-transition:enter-end="opacity-100 transform translate-y-0"
              class="absolute top-0 left-0 right-0 px-4 py-2 bg-red-50 dark:bg-red-900/20 border-b border-red-100 dark:border-red-800">
             <div class="flex items-center justify-between">
                 <div class="flex items-center space-x-2">
                     <svg class="h-5 w-5 text-red-400 dark:text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                     </svg>
                     <p class="text-sm text-red-600 dark:text-red-400" x-text="errorMessage || 'Failed to load page'"></p>
                 </div>
                 <button @click="error = false" class="text-red-400 dark:text-red-500 hover:text-red-500 dark:hover:text-red-400">
                     <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                     </svg>
                 </button>
             </div>
         </div>

         <div class="flex flex-col sm:flex-row items-center space-y-4 sm:space-y-0 sm:space-x-4">
             <!-- Mobile Previous/Next -->
             <div class="flex items-center space-x-2 sm:hidden">
                 <button @click="goToPage(currentPage - 1)"
                         :disabled="currentPage <= 1 || loading"
                         class="relative inline-flex items-center justify-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 rounded-full transition-colors duration-200 disabled:opacity-50">
                     <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                     </svg>
                     Previous
                 </button>
                 <span class="text-sm text-gray-600 dark:text-gray-400">
                     Page <span class="font-medium text-gray-900 dark:text-white" x-text="currentPage"></span>
                 </span>
                 <button @click="goToPage(currentPage + 1)"
                         :disabled="currentPage >= totalPages || loading"
                         class="relative inline-flex items-center justify-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 rounded-full transition-colors duration-200 disabled:opacity-50">
                     Next
                     <svg class="h-4 w-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                     </svg>
                 </button>
             </div>

             <!-- Page Input (Responsive) -->
             <div class="flex items-center space-x-2 order-first sm:order-last">
                 <label class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 hidden sm:inline">Go to:</label>
                 <div class="relative">
                     <input type="number"
                            x-model="pageInput"
                            @keydown.enter="goToPage(pageInput)"
                            :disabled="loading"
                            min="1"
                            :max="totalPages"
                            class="w-20 sm:w-24 px-2 py-1 text-center text-xs sm:text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:opacity-50">
                     <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                         <span class="text-xs text-gray-400 dark:text-gray-500" x-text="'/' + totalPages"></span>
                     </div>
                 </div>
                 <button @click="goToPage(pageInput)"
                         :disabled="loading"
                         class="inline-flex items-center px-2 sm:px-2.5 py-1 sm:py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 transition-colors duration-200">
                     Go
                 </button>
             </div>

             <!-- Desktop Pagination -->
             <nav class="hidden sm:flex items-center space-x-1" aria-label="Pagination">
                 <!-- First Page (show only when not current) -->
                 <button x-show="currentPage !== 1"
                         @click="goToPage(1)"
                         :disabled="loading"
                         class="relative inline-flex items-center justify-center w-8 h-8 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 rounded-full transition-colors duration-200">
                     1
                 </button>

                 <!-- Dots if needed -->
                 <span x-show="currentPage > 4" class="relative inline-flex items-center justify-center w-8 h-8 text-xs font-medium text-gray-700 dark:text-gray-300">...</span>

                 <!-- Previous 3 numbers if available -->
                 <template x-for="page in Array.from({length: 3}, (_, i) => currentPage - (3 - i)).filter(p => p > 1 && p < currentPage)">
                     <button @click="goToPage(page)"
                             :disabled="loading"
                             class="relative inline-flex items-center justify-center w-8 h-8 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 rounded-full transition-colors duration-200">
                         <span x-text="page"></span>
                     </button>
                 </template>

                 <!-- Current Page -->
                 <button :disabled="loading"
                         class="z-10 bg-blue-50 dark:bg-blue-900/50 border-blue-500 dark:border-blue-400 text-blue-600 dark:text-blue-300 relative inline-flex items-center justify-center w-8 h-8 border text-xs font-medium rounded-full shadow-sm">
                     <span x-text="currentPage"></span>
                 </button>

                 <!-- Next 3 numbers if available -->
                 <template x-for="page in Array.from({length: 3}, (_, i) => currentPage + (i + 1)).filter(p => p < totalPages)">
                     <button @click="goToPage(page)"
                             :disabled="loading"
                             class="relative inline-flex items-center justify-center w-8 h-8 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 rounded-full transition-colors duration-200">
                         <span x-text="page"></span>
                     </button>
                 </template>

                 <!-- Dots if needed -->
                 <span x-show="currentPage < totalPages - 3" class="relative inline-flex items-center justify-center w-8 h-8 text-xs font-medium text-gray-700 dark:text-gray-300">...</span>

                 <!-- Last Page (show only when not current) -->
                 <button x-show="totalPages > 1 && currentPage !== totalPages"
                         @click="goToPage(totalPages)"
                         :disabled="loading"
                         class="relative inline-flex items-center justify-center w-8 h-8 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-xs font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 rounded-full transition-colors duration-200">
                     <span x-text="totalPages"></span>
                 </button>
             </nav>
         </div>
    </div>
</div>
