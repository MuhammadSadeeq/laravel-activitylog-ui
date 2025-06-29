<!-- Save View Modal -->
<div x-data="{ open: false, viewName: '', filters: {} }"
     @@show-save-view-modal.window="filters = $event.detail; open = true"
     @@keydown.escape.window="open = false"
     x-show="open"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto">

    <!-- Backdrop -->
    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"
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
             class="relative w-full max-w-md bg-white dark:bg-gray-800 rounded-lg shadow-xl">

            <!-- Header -->
            <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Save View</h3>
                <button type="button"
                        @@click="open = false"
                        class="rounded-md text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Form -->
            <form @@submit.prevent="if(viewName.trim()) { saveView(viewName, filters); viewName = ''; open = false }">
                <!-- Content -->
                <div class="p-6">
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        Save your current filter settings as a reusable view.
                    </p>

                    <div class="space-y-4">
                        <div>
                            <label for="view-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                View Name
                            </label>
                            <input type="text"
                                   id="view-name"
                                   x-model="viewName"
                                   placeholder="e.g., Last 30 days errors"
                                   class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        </div>

                        <!-- Filter Preview -->
                        <div class="bg-gray-50 dark:bg-gray-900 rounded-md p-3">
                            <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                                Current Filters
                            </h4>
                            <div class="text-sm text-gray-700 dark:text-gray-300">
                                <template x-if="Object.keys(filters).length === 0">
                                    <span class="text-gray-500 dark:text-gray-400 italic">No filters applied</span>
                                </template>
                                <template x-if="Object.keys(filters).length > 0">
                                    <ul class="space-y-1">
                                        <template x-for="[key, value] in Object.entries(filters)" :key="key">
                                            <li x-show="value && value !== '' && (!Array.isArray(value) || value.length > 0)">
                                                <span class="font-medium" x-text="key.replace('_', ' ')"></span>:
                                                <span x-text="Array.isArray(value) ? value.join(', ') : value"></span>
                                            </li>
                                        </template>
                                    </ul>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-end px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 rounded-b-lg space-x-3">
                    <button type="button"
                            @@click="open = false"
                            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-500 dark:hover:text-gray-400">
                        Cancel
                    </button>
                    <button type="submit"
                            :disabled="!viewName.trim()"
                            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                        Save View
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
