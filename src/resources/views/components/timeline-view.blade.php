<div class="al-card">
    <div class="al-card__header">
        <div class="al-grow">
            <h3 class="al-card__title">Timeline</h3>
            <p class="al-card__meta">
                Newest first ·
                <span x-text="activities.length.toLocaleString()"></span> of
                <span x-text="totalActivities.toLocaleString()"></span> shown
            </p>
        </div>

        <div class="al-row">
            <label class="al-label" for="al-timeline-per-page">Load</label>
            <select id="al-timeline-per-page"
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

    <div class="al-card__body">
        {{-- Grouped by day like the table, so the two views tell the same
             story in the same shape. --}}
        <div x-show="activities.length > 0" x-cloak class="al-stack al-stack--md">
            <template x-for="group in groupedActivities" :key="group.key">
                <section>
                    <h4 class="al-timeline__date" x-text="group.label"></h4>

                    <div class="al-timeline">
                        <template x-for="activity in group.items" :key="activity.id">
                            <article class="al-timeline__item">
                                <span class="al-timeline__marker"
                                      :data-event="window.ActivityTypeStyler.getEvent(activity.event)"
                                      aria-hidden="true"></span>

                                <div class="al-row al-row--wrap" style="gap:.4375rem">
                                    <span class="al-badge" :data-event="window.ActivityTypeStyler.getEvent(activity.event)">
                                        <span class="al-badge__label" x-text="activity.event || 'unknown'"></span>
                                    </span>

                                    <span class="al-cell-primary al-truncate" x-text="window.ActivitylogUi.recordLabel(activity)"></span>

                                    <span class="al-grow"></span>

                                    <time class="al-mono al-faint al-small"
                                          :datetime="activity.created_at"
                                          :title="window.ActivitylogUi.formatDateTime(activity.created_at)"
                                          x-text="window.ActivitylogUi.formatTime(activity.created_at)"></time>
                                </div>

                                <p class="al-cell-secondary al-break"
                                   x-show="window.ActivitylogUi.extraDescription(activity)"
                                   x-text="window.ActivitylogUi.extraDescription(activity)"></p>

                                <div class="al-row al-row--wrap al-small al-muted" style="margin-top:.25rem;gap:.5rem">
                                    <span x-show="activity.causer_type">
                                        by <span style="color:var(--ink)" x-text="activity.causer_name || 'Unknown'"></span>
                                    </span>
                                    <span x-show="!activity.causer_type">by System</span>

                                    {{-- The change block reads attribute_changes when present
                                         and falls back to the old/attributes pair inside
                                         properties, which is where Spatie v4 kept them. --}}
                                    <div x-data="{
                                             expanded: false,
                                             get changes() {
                                                 if (activity.attribute_changes) return activity.attribute_changes;
                                                 const props = activity.properties;
                                                 if (props && (props.old || props.attributes)) {
                                                     return { old: props.old, attributes: props.attributes };
                                                 }
                                                 return null;
                                             }
                                         }"
                                         {{-- The class, not an inline style:
                                              x-show removes the inline display
                                              property when it shows an element,
                                              which took display:contents with
                                              it and broke the row layout. --}}
                                         class="al-contents"
                                         x-show="changes">
                                        <button type="button"
                                                class="al-btn al-btn--ghost al-btn--sm"
                                                @click="expanded = !expanded"
                                                :aria-expanded="expanded">
                                            <span x-text="expanded ? 'Hide changes' : 'Changes'"></span>
                                        </button>

                                        <div x-show="expanded" x-cloak style="flex-basis:100%;margin-top:.375rem">
                                            <template x-if="changes">
                                                <div class="al-diff">
                                                    <template x-for="key in Object.keys(changes.attributes || changes.old || {})" :key="key">
                                                        <div class="al-diff__row">
                                                            <div class="al-diff__key" x-text="key"></div>
                                                            <div class="al-diff__values">
                                                                <template x-if="changes.old && changes.old[key] !== undefined">
                                                                    <span class="al-diff__old" x-text="window.ActivitylogUi.stringify(changes.old[key])"></span>
                                                                </template>
                                                                <template x-if="changes.old && changes.old[key] !== undefined && changes.attributes && changes.attributes[key] !== undefined">
                                                                    <span class="al-diff__arrow" aria-hidden="true">→</span>
                                                                </template>
                                                                <template x-if="changes.attributes && changes.attributes[key] !== undefined">
                                                                    <span class="al-diff__new" x-text="window.ActivitylogUi.stringify(changes.attributes[key])"></span>
                                                                </template>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    <span class="al-grow"></span>

                                    <button type="button" class="al-btn al-btn--ghost al-btn--sm" @click="showActivityDetail(activity)">
                                        Details
                                    </button>
                                </div>
                            </article>
                        </template>
                    </div>
                </section>
            </template>
        </div>

        <div x-show="loadError" x-cloak class="al-empty">
            <p class="al-empty__title">Could not load activities</p>
            <p class="al-empty__body">The request failed. Check your connection or the application logs, then try again.</p>
            <button type="button" class="al-btn" style="margin-top:1rem" @click="loadActivities(1)">Try again</button>
        </div>

        <div x-show="!loadError && activities.length === 0" x-cloak class="al-empty">
            <p class="al-empty__title">No activities match these filters</p>
            <p class="al-empty__body">Try widening the date range, or clearing a filter.</p>
        </div>
    </div>

    <div class="al-card__footer" x-show="currentPage < totalPages" x-cloak>
        <div class="al-row al-row--between al-row--wrap">
            <p class="al-small al-muted">
                Showing <span x-text="activities.length.toLocaleString()"></span> of
                <span x-text="totalActivities.toLocaleString()"></span>
            </p>
            <button type="button" class="al-btn" @click="loadMoreActivities()" :disabled="loadingMore">
                <span x-show="loadingMore" class="al-spinner" x-cloak aria-hidden="true"></span>
                <span x-text="loadingMore ? 'Loading…' : 'Load older activities'"></span>
            </button>
        </div>
    </div>
</div>
