<div class="al-card">
    <div class="al-card__header">
        <div class="al-grow al-row" style="gap:.5rem">
            <h3 class="al-card__title">Activities</h3>
            <span class="al-chip" x-show="hasActiveFilters" x-cloak>filtered</span>
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
    <div class="al-table-wrap al-scroll al-card--clip" x-show="!loadError && activities.length > 0" x-cloak>
        <table class="al-table">
            {{-- Four columns, not six. Subject had been repeating whatever the
                 description column fell back to, and a per-row button column
                 held nothing but the same word over and over. --}}
            <colgroup>
                <col class="al-col-event">
                <col class="al-col-record">
                <col>
                <col class="al-col-time">
            </colgroup>
            <thead>
                <tr>
                    <th scope="col">Event</th>
                    <th scope="col">Record</th>
                    <th scope="col">User</th>
                    <th scope="col" class="al-table__num">Time</th>
                </tr>
            </thead>
            {{-- One tbody per day, which is valid HTML and lets the date be
                 stated once instead of repeated down every row. --}}
            <template x-for="group in groupedActivities" :key="group.key">
                <tbody>
                    <tr class="al-daybreak">
                        <td colspan="4" x-text="group.label"></td>
                    </tr>

                    <template x-for="activity in group.items" :key="activity.id">
                        {{-- No role="button" here. It replaced the row in the
                             accessibility tree, so 25 rows and 100 cells stopped
                             being a table and became 25 unlabelled buttons. The
                             row still opens the detail panel on click for the
                             mouse; the keyboard path is the real button in the
                             Record cell below. --}}
                        <tr class="al-rowlink" @click="showActivityDetail(activity)">
                            <td data-cell="Event">
                                {{-- The tint already carries the meaning; a dot
                                     inside a coloured pill said it twice. --}}
                                <span class="al-badge" :data-event="window.ActivityTypeStyler.getEvent(activity.event)">
                                    <span class="al-badge__label" x-text="activity.event || 'unknown'"></span>
                                </span>
                            </td>

                            {{-- The record acted upon is the line a reader scans
                                 for. The description sits underneath it, and only
                                 when it says something the event badge has not
                                 already said — on a stock Spatie install it is
                                 just the event name again. --}}
                            <td data-cell="Record">
                                <button type="button"
                                        class="al-recordlink al-cell-primary al-truncate"
                                        @click.stop="showActivityDetail(activity)"
                                        :aria-label="`Show detail for ${window.ActivitylogUi.recordLabel(activity)}`"
                                        x-text="window.ActivitylogUi.recordLabel(activity)"></button>
                                <div class="al-cell-secondary al-truncate"
                                     x-show="window.ActivitylogUi.extraDescription(activity)"
                                     :title="activity.description"
                                     x-text="window.ActivitylogUi.extraDescription(activity)"></div>
                            </td>

                            {{-- No avatar. It stamped the same initial down the column
                                 for every row one person caused, which is decoration
                                 standing where information should be. --}}
                            <td data-cell="User">
                                <span class="al-truncate" x-show="activity.causer_type" :title="activity.causer_name" x-text="activity.causer_name || 'Unknown'"></span>
                                <span class="al-muted al-truncate" x-show="!activity.causer_type">System</span>
                            </td>

                            <td data-cell="Time" class="al-table__num">
                                <div class="al-row" style="justify-content:flex-end;gap:.5rem">
                                    <time class="al-mono al-muted"
                                          :datetime="activity.created_at"
                                          :title="window.ActivitylogUi.formatDateTime(activity.created_at)"
                                          x-text="window.ActivitylogUi.formatTime(activity.created_at)"></time>
                                    <svg class="al-rowlink__chevron" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="m9 18 6-6-6-6"/>
                                    </svg>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </template>
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
                 jumpTo: '',

                 get pages() {
                     const total = totalPages;
                     const current = currentPage;
                     // Fixed, not measured: reading window.innerWidth here is not
                     // reactive, so the control kept whatever width it saw first
                     // and never updated on resize or rotation.
                     const span = 2;
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
            {{-- With 186 pages, first/last plus two either side cannot reach the
                 middle. The old design had a "go to page" box; removing it made
                 most of the log unreachable except by repeated clicking. --}}
            <form class="al-row" style="gap:.375rem"
                  @submit.prevent="const n = parseInt(jumpTo, 10); if (n >= 1 && n <= totalPages) { changePage(n); jumpTo = '' }">
                <label class="al-small al-muted" for="al-page-jump">Page</label>
                <input id="al-page-jump"
                       type="number"
                       class="al-input tnum"
                       style="width:5rem"
                       min="1"
                       :max="totalPages"
                       :placeholder="currentPage"
                       x-model="jumpTo">
                <span class="al-small al-muted">of <span x-text="totalPages.toLocaleString()"></span></span>
            </form>

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
