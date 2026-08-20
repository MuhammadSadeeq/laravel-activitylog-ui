<div class="al-card">
    <div class="al-card__header">
        <div class="al-grow">
            <h3 class="al-card__title">Activities</h3>
            <p class="al-card__meta">
                <span x-text="totalActivities.toLocaleString()"></span>
                <span x-text="totalActivities === 1 ? 'record' : 'records'"></span>
                <template x-if="hasActiveFilters"><span> · filtered</span></template>
            </p>
        </div>

        <div class="al-row">
            <label class="al-label" for="al-per-page">Rows</label>
            <select id="al-per-page"
                    class="al-select"
                    style="width:auto"
                    x-model="perPage"
                    @change="loadActivities(1)">
                @foreach(config('activitylog-ui.ui.per_page_options', [10, 25, 50, 100]) as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- The wrapper scrolls, not the page: a wide table must never push the
         whole document sideways. Below 60rem the rows become blocks instead
         (see .al-table in the stylesheet), because six columns cannot be read
         on a phone however far you scroll. --}}
    <div class="al-table-wrap al-scroll" x-show="!loadError && activities.length > 0" x-cloak>
        <table class="al-table">
            <thead>
                <tr>
                    <th scope="col">Event</th>
                    <th scope="col">Description</th>
                    <th scope="col">Subject</th>
                    <th scope="col">User</th>
                    <th scope="col">When</th>
                    <th scope="col"><span class="al-visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="activity in activities" :key="activity.id">
                    <tr>
                        <td data-cell="Event">
                            <span class="al-badge" :data-event="window.ActivityTypeStyler.getEvent(activity.event)">
                                <span class="al-badge__dot" aria-hidden="true"></span>
                                <span class="al-badge__label" x-text="activity.event || 'unknown'"></span>
                            </span>
                        </td>

                        <td data-cell="Description">
                            <div class="al-cell-primary al-break" x-text="activity.description"></div>
                        </td>

                        <td data-cell="Subject">
                            <template x-if="activity.subject_type">
                                <span class="al-chip">
                                    <span class="al-chip__text" x-text="activity.subject_type.split('\\').pop()"></span>
                                    <span class="al-faint al-mono" x-text="'#' + activity.subject_id"></span>
                                </span>
                            </template>
                            <template x-if="!activity.subject_type">
                                <span class="al-faint">—</span>
                            </template>
                        </td>

                        <td data-cell="User">
                            <template x-if="activity.causer_type">
                                <div class="al-row">
                                    <span class="al-avatar" aria-hidden="true"
                                          x-text="(activity.causer_name || '?').charAt(0).toUpperCase()"></span>
                                    <span class="al-truncate" x-text="activity.causer_name || 'Unknown'"></span>
                                </div>
                            </template>
                            <template x-if="!activity.causer_type">
                                <span class="al-muted">System</span>
                            </template>
                        </td>

                        <td data-cell="When">
                            <div class="al-truncate" :title="new Date(activity.created_at).toLocaleString()">
                                <span x-text="window.ActivitylogUi.formatDate(activity.created_at)"></span>
                            </div>
                            <div class="al-cell-secondary al-hide-sm" x-text="window.ActivitylogUi.formatTime(activity.created_at)"></div>
                        </td>

                        <td class="al-table__actions">
                            <button type="button" class="al-btn al-btn--sm" @click="showActivityDetail(activity)">
                                Details
                            </button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    {{-- A request that failed and a search that matched nothing are different
         things, and the second is not an error. --}}
    <div x-show="loadError" x-cloak class="al-empty">
        <svg class="al-empty__icon" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>
        </svg>
        <p class="al-empty__title">Could not load activities</p>
        <p class="al-empty__body">The request failed. Check your connection or the application logs, then try again.</p>
        <button type="button" class="al-btn" style="margin-top:1rem" @click="loadActivities(currentPage)">Try again</button>
    </div>

    <div x-show="!loadError && activities.length === 0" x-cloak class="al-empty">
        <svg class="al-empty__icon" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 6h16M4 12h10M4 18h7"/>
        </svg>
        <p class="al-empty__title">No activities match these filters</p>
        <p class="al-empty__body">Try widening the date range, or clearing a filter.</p>
        <button type="button" x-show="currentPage > 1" class="al-btn" style="margin-top:1rem" @click="changePage(1)">
            Back to the first page
        </button>
    </div>

    <div class="al-card__footer" x-show="totalPages > 1" x-cloak>
        {{-- One pagination control at every width. The previous version rendered
             a separate mobile bar and desktop bar, plus a "go to page" input and
             a getPageNumbers() helper whose result was never used. --}}
        <div class="al-pagination"
             x-data="{
                 get pages() {
                     const total = totalPages;
                     const current = currentPage;
                     const span = window.innerWidth < 640 ? 1 : 2;
                     const out = [];
                     const push = p => { if (!out.includes(p)) out.push(p); };

                     push(1);
                     if (current - span > 2) out.push('gap-start');
                     for (let p = Math.max(2, current - span); p <= Math.min(total - 1, current + span); p++) push(p);
                     if (current + span < total - 1) out.push('gap-end');
                     if (total > 1) push(total);

                     return out;
                 }
             }">
            <p class="al-small al-muted">
                Page <span x-text="currentPage"></span> of <span x-text="totalPages.toLocaleString()"></span>
            </p>

            <nav class="al-pagination__pages" aria-label="Pagination">
                <button type="button"
                        class="al-page-btn"
                        :disabled="currentPage <= 1 || loading"
                        @click="changePage(currentPage - 1)"
                        aria-label="Previous page">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto">
                        <path d="m15 18-6-6 6-6"/>
                    </svg>
                </button>

                <template x-for="page in pages" :key="page">
                    <span>
                        <template x-if="typeof page === 'number'">
                            <button type="button"
                                    class="al-page-btn"
                                    :disabled="loading"
                                    :aria-current="page === currentPage ? 'page' : null"
                                    @click="changePage(page)"
                                    x-text="page"></button>
                        </template>
                        <template x-if="typeof page !== 'number'">
                            <span class="al-page-btn" aria-hidden="true">…</span>
                        </template>
                    </span>
                </template>

                <button type="button"
                        class="al-page-btn"
                        :disabled="currentPage >= totalPages || loading"
                        @click="changePage(currentPage + 1)"
                        aria-label="Next page">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto">
                        <path d="m9 18 6-6-6-6"/>
                    </svg>
                </button>
            </nav>
        </div>
    </div>
</div>
