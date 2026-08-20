@php($enabledFormats = config('activitylog-ui.exports.enabled_formats', []))
@php($formatMeta = [
    'csv' => ['label' => 'CSV', 'note' => 'Opens in any spreadsheet.'],
    'xlsx' => ['label' => 'Excel', 'note' => 'Formatted workbook.'],
    'pdf' => ['label' => 'PDF', 'note' => 'For printing or sharing.'],
    'json' => ['label' => 'JSON', 'note' => 'For scripts and pipelines.'],
])

<div x-data="{ open: false, currentFilters: {}, format: '' }"
     x-on:show-export-modal.window="currentFilters = $event.detail?.filters || {}; format = ''; open = true"
     @keydown.escape.window="open = false"
     x-show="open"
     x-cloak
     x-effect="window.ActivitylogUi.lockScroll(open)"
     class="al-dialog">
    <div class="al-dialog__backdrop" @click="open = false"></div>

    <div class="al-dialog__panel"
         style="max-width:30rem"
         role="dialog"
         aria-modal="true"
         aria-labelledby="al-export-title">
        <div class="al-dialog__header">
            <div>
                <h3 id="al-export-title" class="al-card__title">Export activities</h3>
                <p class="al-card__meta">Your current filters are applied to the export.</p>
            </div>
        </div>

        <div class="al-dialog__body al-stack al-stack--md">
            {{-- Filters first, and stated plainly. Exporting the whole log by
                 accident is the expensive mistake here, so the panel says which
                 it will be before offering a format. --}}
            <div x-show="Object.values(currentFilters).some(v => v !== null && v !== '' && !(Array.isArray(v) && v.length === 0))">
                <p class="al-label" style="margin-bottom:.375rem">Filters applied</p>
                <div class="al-row al-row--wrap" style="gap:.25rem">
                    <template x-for="[key, value] in Object.entries(currentFilters)" :key="key">
                        <span class="al-chip"
                              x-show="value !== null && value !== '' && !(Array.isArray(value) && value.length === 0)">
                            <span class="al-faint" x-text="key.replace(/_/g, ' ')"></span>
                            <span class="al-chip__text" x-text="Array.isArray(value) ? value.join(', ') : value"></span>
                        </span>
                    </template>
                </div>
            </div>

            <div class="al-note al-note--warning"
                 x-show="!Object.values(currentFilters).some(v => v !== null && v !== '' && !(Array.isArray(v) && v.length === 0))">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" style="margin-top:.125rem">
                    <path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>
                </svg>
                <span>No filters are set, so this exports the entire log. That may take a while and produce a large file.</span>
            </div>

            <div class="al-stack al-stack--sm">
                <p class="al-label">Format</p>
                @foreach($enabledFormats as $format)
                    @continue(!isset($formatMeta[$format]))
                    <button type="button"
                            class="al-btn al-btn--block"
                            style="justify-content:flex-start;padding:.625rem .75rem"
                            @click="format = '{{ $format }}'; open = false; exportData('{{ $format }}', currentFilters)">
                        <span class="al-grow" style="text-align:left">
                            <span style="display:block;font-weight:600">{{ $formatMeta[$format]['label'] }}</span>
                            <span class="al-small al-muted">{{ $formatMeta[$format]['note'] }}</span>
                        </span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 3v12M8 11l4 4 4-4M4 21h16"/>
                        </svg>
                    </button>
                @endforeach
            </div>
        </div>

        <div class="al-dialog__footer">
            <button type="button" class="al-btn" @click="open = false">Cancel</button>
        </div>
    </div>
</div>
