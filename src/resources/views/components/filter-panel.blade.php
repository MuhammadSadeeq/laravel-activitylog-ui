{{--
    Filter panel.

    The previous version rendered this header five times — one copy each for
    mobile, sm–md, md–lg, lg–xl and xl+ — and hid four of them with `hidden`.
    Around 400 of its 692 lines were those duplicates, all of them in the DOM
    on every page load and all of them needing the same edit whenever anything
    changed. There is one copy now; the stylesheet handles the widths.
--}}
<aside class="al-card" x-data="filterPanel()" x-init="init()">
    <div class="al-card__header">
        <div class="al-grow al-row" style="gap:.5rem">
            <h3 class="al-card__title">Filters</h3>
            <span class="al-chip" x-show="hasActiveFilters" x-cloak>active</span>
        </div>

        <div class="al-row" style="gap:.25rem">
            @if(config('activitylog-ui.features.saved_views', true))
                <div style="position:relative" x-data="{ open: false }">
                    <button type="button"
                            class="al-btn al-btn--ghost al-btn--sm"
                            @click="open = !open"
                            @keydown.escape.window="open = false"
                            :aria-expanded="open ? 'true' : 'false'"
                            aria-label="Saved views">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
                        </svg>
                        <span class="al-hide-sm">Views</span>
                    </button>

                    <div x-show="open"
                         x-cloak
                         x-transition.opacity.duration.120ms
                         @click.outside="open = false"
                         class="al-card"
                         style="position:absolute;right:0;top:calc(100% + .375rem);width:16rem;max-height:20rem;overflow:auto;box-shadow:var(--shadow);z-index:20">
                        <div style="padding:.375rem">
                            <button type="button"
                                    class="al-btn al-btn--ghost al-btn--block al-btn--sm"
                                    style="justify-content:flex-start"
                                    @click="showSaveViewModal(); open = false">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                    <path d="M12 5v14M5 12h14"/>
                                </svg>
                                Save current filters
                            </button>
                        </div>

                        <div x-show="savedViews.length > 0" style="border-top:1px solid var(--border);padding:.375rem">
                            <template x-for="savedView in savedViews" :key="savedView.id">
                                <div class="al-row" style="gap:.125rem">
                                    <button type="button"
                                            class="al-btn al-btn--ghost al-btn--sm al-grow"
                                            style="justify-content:flex-start"
                                            @click="loadSavedView(savedView); open = false">
                                        <span class="al-truncate" x-text="savedView.name"></span>
                                    </button>
                                    <button type="button"
                                            class="al-btn al-btn--ghost al-btn--sm al-btn--icon al-btn--danger"
                                            @click.stop="pendingDelete = savedView; open = false"
                                            :aria-label="'Delete the view ' + savedView.name">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                            <path d="M3 6h18M8 6V4h8v2m-9 0 1 14h8l1-14"/>
                                        </svg>
                                    </button>
                                </div>
                            </template>
                        </div>

                        <p x-show="savedViews.length === 0" class="al-small al-faint" style="padding:.5rem .625rem;border-top:1px solid var(--border)">
                            No saved views yet.
                        </p>
                    </div>
                </div>
            @endif

            <button type="button"
                    class="al-btn al-btn--ghost al-btn--sm"
                    x-show="hasActiveFilters"
                    x-cloak
                    @click="clearAllFilters()">
                Clear
            </button>

            {{-- On a phone the panel sits above the results, so it starts closed
                 and this opens it. On a wide screen it is a sidebar and stays
                 open, which is why the control is hidden there. --}}
            <button type="button"
                    class="al-btn al-btn--ghost al-btn--sm al-panel-toggle"
                    @click="expanded = !expanded"
                    :aria-expanded="expanded ? 'true' : 'false'">
                <span x-text="expanded ? 'Hide' : 'Show'"></span>
            </button>
        </div>
    </div>

    <div class="al-card__body al-stack al-stack--lg" x-show="expanded" x-cloak>
        <div class="al-field">
            <label class="al-label" for="al-filter-search">Search</label>
            <div class="al-search">
                <svg class="al-search__icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
                </svg>
                <input id="al-filter-search"
                       type="search"
                       class="al-input al-input--search"
                       placeholder="Description, properties, user…"
                       x-model="filters.search"
                       @input.debounce.400ms="applyFilters()">
            </div>
        </div>

        {{-- Eight presets as pills wrapped into three ragged rows and cost about
             90px of a narrow sidebar. A select states the same choice in one. --}}
        <div class="al-field">
            <label class="al-label" for="al-filter-range">Date range</label>
            <select id="al-filter-range"
                    class="al-select"
                    x-model="filters.date_preset"
                    @change="setDatePreset(filters.date_preset)">
                <template x-for="preset in datePresets" :key="preset.value">
                    <option :value="preset.value" x-text="preset.label"></option>
                </template>
            </select>

            <div x-show="filters.date_preset === 'custom'" x-cloak class="al-row" style="gap:.5rem;margin-top:.5rem">
                <div class="al-field al-grow">
                    <label class="al-label al-small" for="al-filter-from">From</label>
                    <input id="al-filter-from" type="date" class="al-input" x-model="filters.start_date" @change="applyFilters()">
                </div>
                <div class="al-field al-grow">
                    <label class="al-label al-small" for="al-filter-to">To</label>
                    <input id="al-filter-to" type="date" class="al-input" x-model="filters.end_date" @change="applyFilters()">
                </div>
            </div>
        </div>

        <div class="al-field" x-show="availableEventTypes.length > 0">
            <span class="al-label">Event</span>
            <div class="al-stack al-scroll" style="gap:.0625rem;max-height:11rem;overflow:auto">
                <template x-for="eventType in availableEventTypes" :key="eventType.value">
                    <label class="al-check">
                        <input type="checkbox" :value="eventType.value" x-model="filters.event_types" @change="applyFilters()">
                        <span class="al-badge" :data-event="eventType.event" style="border:0;background:transparent;padding:0">
                            <span class="al-badge__dot" aria-hidden="true"></span>
                        </span>
                        <span x-text="eventType.label"></span>
                    </label>
                </template>
            </div>
        </div>

        <div class="al-field">
            <span class="al-label">User</span>
            <div style="position:relative" x-data="{ open: false }">
                <button type="button"
                        class="al-btn al-btn--block"
                        style="justify-content:space-between"
                        @click="open = !open; if (open) $nextTick(() => $refs.causerSearch?.focus())"
                        @keydown.escape.window="open = false"
                        :aria-expanded="open ? 'true' : 'false'">
                    <span class="al-truncate" x-text="selectedCauserText || 'All users'"></span>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m6 9 6 6 6-6"/>
                    </svg>
                </button>

                <div x-show="open"
                     x-cloak
                     x-transition.opacity.duration.120ms
                     @click.outside="open = false"
                     class="al-card"
                     style="position:absolute;left:0;right:0;top:calc(100% + .375rem);box-shadow:var(--shadow);z-index:20">
                    <div style="padding:.375rem;border-bottom:1px solid var(--border)">
                        <input type="search"
                               class="al-input"
                               aria-label="Search users"
                               x-ref="causerSearch"
                               placeholder="Search users…"
                               x-model="causerSearch"
                               @input="searchCausers()">
                    </div>

                    <div class="al-scroll" style="max-height:14rem;overflow:auto;padding:.375rem">
                        <button type="button"
                                class="al-btn al-btn--ghost al-btn--block al-btn--sm"
                                style="justify-content:flex-start"
                                @click="selectCauser(null); open = false">
                            All users
                        </button>

                        <template x-for="causer in filteredCausers" :key="`${causer.type}#${causer.id}`">
                            <button type="button"
                                    class="al-btn al-btn--ghost al-btn--block al-btn--sm"
                                    style="justify-content:flex-start"
                                    @click="selectCauser(causer); open = false">
                                <span class="al-avatar" aria-hidden="true" x-text="String(causer.name ?? '?').charAt(0).toUpperCase()"></span>
                                <span class="al-truncate al-grow" style="text-align:left" x-text="causer.name"></span>
                                <span class="al-small al-faint" x-text="causer.type.split('\\').pop()"></span>
                            </button>
                        </template>

                        <p x-show="filteredCausers.length === 0" class="al-small al-faint" style="padding:.5rem .625rem">
                            No users match that search.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="al-field" x-show="availableSubjectTypes.length > 0">
            <label class="al-label" for="al-filter-subject">Subject type</label>
            <select id="al-filter-subject" class="al-select" x-model="filters.subject_type" @change="applyFilters()">
                <option value="">All subject types</option>
                <template x-for="subjectType in availableSubjectTypes" :key="subjectType.value ?? subjectType">
                    <option :value="subjectType.value ?? subjectType" x-text="subjectType.label ?? subjectType"></option>
                </template>
            </select>
        </div>
    </div>

    {{-- aria-modal="true" tells a screen reader nothing outside this panel
         exists. Without a trap the keyboard disagreed: Tab walked straight out
         into the filter fields behind it, which the reader was no longer
         announcing. The other three dialogs already use these helpers. --}}
    <div x-show="pendingDelete"
         x-cloak
         x-effect="window.ActivitylogUi.lockScroll(!!pendingDelete); window.ActivitylogUi.trapFocus($refs.deleteViewPanel, !!pendingDelete)"
         @keydown.tab="window.ActivitylogUi.keepTabInside($event, $refs.deleteViewPanel)"
         @keydown.escape.window="pendingDelete = null"
         class="al-dialog">
        <div class="al-dialog__backdrop" @click="pendingDelete = null"></div>
        <div x-ref="deleteViewPanel" class="al-dialog__panel" style="max-width:24rem" role="dialog" aria-modal="true" aria-labelledby="al-delete-view-title">
            <div class="al-dialog__header">
                <div>
                    <h3 id="al-delete-view-title" class="al-card__title">Delete this view?</h3>
                    <p class="al-card__meta">
                        <span x-text="pendingDelete?.name"></span> will be removed. The activities themselves are untouched.
                    </p>
                </div>
            </div>
            <div class="al-dialog__footer">
                <button type="button" class="al-btn" @click="pendingDelete = null">Cancel</button>
                <button type="button"
                        class="al-btn al-btn--primary"
                        @click="deleteSavedView(pendingDelete.id); pendingDelete = null">
                    Delete
                </button>
            </div>
        </div>
    </div>
</aside>
