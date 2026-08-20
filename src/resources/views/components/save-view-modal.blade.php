<div x-data="{ open: false, viewName: '', filters: {} }"
     x-on:show-save-view-modal.window="filters = $event.detail || {}; viewName = ''; open = true; $nextTick(() => $refs.name?.focus())"
     @keydown.escape.window="open = false"
     x-show="open"
     x-cloak
     x-effect="window.ActivitylogUi.lockScroll(open)"
     class="al-dialog">
    <div class="al-dialog__backdrop" @click="open = false"></div>

    <form class="al-dialog__panel"
          style="max-width:26rem"
          role="dialog"
          aria-modal="true"
          aria-labelledby="al-save-view-title"
          @submit.prevent="if (viewName.trim()) { saveView(viewName, filters); open = false }">
        <div class="al-dialog__header">
            <div>
                <h3 id="al-save-view-title" class="al-card__title">Save this view</h3>
                <p class="al-card__meta">The current filters are stored under a name you can come back to.</p>
            </div>
        </div>

        <div class="al-dialog__body al-stack al-stack--md">
            <div class="al-field">
                <label class="al-label" for="al-view-name">Name</label>
                <input id="al-view-name"
                       x-ref="name"
                       type="text"
                       class="al-input"
                       maxlength="100"
                       required
                       placeholder="Deletions this month"
                       x-model="viewName">
            </div>

            <div x-show="Object.values(filters).some(v => v !== null && v !== '' && !(Array.isArray(v) && v.length === 0))">
                <p class="al-label" style="margin-bottom:.375rem">Filters included</p>
                <div class="al-row al-row--wrap" style="gap:.25rem">
                    <template x-for="[key, value] in Object.entries(filters)" :key="key">
                        <span class="al-chip"
                              x-show="value !== null && value !== '' && !(Array.isArray(value) && value.length === 0)">
                            <span class="al-faint" x-text="key.replace(/_/g, ' ')"></span>
                            <span class="al-chip__text" x-text="Array.isArray(value) ? value.join(', ') : value"></span>
                        </span>
                    </template>
                </div>
            </div>
        </div>

        <div class="al-dialog__footer">
            <button type="button" class="al-btn" @click="open = false">Cancel</button>
            <button type="submit" class="al-btn al-btn--primary" :disabled="!viewName.trim()">Save view</button>
        </div>
    </form>
</div>
