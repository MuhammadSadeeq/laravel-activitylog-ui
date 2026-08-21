<div x-data="{ open: false, activity: null }"
     x-on:show-activity-detail.window="activity = $event.detail; open = true"
     @keydown.escape.window="open = false"
     x-show="open"
     x-cloak
     x-effect="window.ActivitylogUi.lockScroll(open); window.ActivitylogUi.trapFocus($refs.panel, open)"
     @keydown.tab="window.ActivitylogUi.keepTabInside($event, $refs.panel)"
     class="al-dialog">
    <div class="al-dialog__backdrop" @click="open = false"></div>

    <div x-ref="panel" class="al-dialog__panel al-dialog__panel--wide"
         role="dialog"
         aria-modal="true"
         aria-labelledby="al-detail-title">
        <div class="al-dialog__header">
            <div class="al-grow" style="min-width:0">
                <h3 id="al-detail-title" class="al-card__title">Activity detail</h3>
                <p class="al-card__meta al-mono" x-text="'#' + (activity?.id ?? '')"></p>
            </div>
            <button type="button" class="al-btn al-btn--ghost al-btn--icon" @click="open = false" aria-label="Close">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <path d="M18 6 6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="al-dialog__body al-scroll al-stack al-stack--lg">
            <template x-if="activity">
                <div class="al-stack al-stack--lg">
                    <div class="al-row al-row--wrap" style="gap:.5rem">
                        <span class="al-badge" :data-event="window.ActivityTypeStyler.getEvent(activity.event)">
                            <span class="al-badge__label" x-text="activity.event || 'unknown'"></span>
                        </span>
                        <span class="al-chip" x-show="activity.log_name">
                            <span class="al-chip__text" x-text="activity.log_name"></span>
                        </span>
                    </div>

                    <p class="al-break" style="font-size:var(--step-1)" x-text="activity.description"></p>

                    <dl class="al-diff">
                        <div class="al-diff__row">
                            <dt class="al-diff__key">When</dt>
                            <dd class="al-break" x-text="window.ActivitylogUi.formatDateTime(activity.created_at)"></dd>
                        </div>
                        <div class="al-diff__row">
                            <dt class="al-diff__key">User</dt>
                            <dd class="al-break">
                                <template x-if="activity.causer_type">
                                    <span>
                                        <span x-text="activity.causer_name || 'Unknown'"></span>
                                        <span class="al-faint al-mono" x-text="' · ' + activity.causer_type.split('\\').pop() + '#' + activity.causer_id"></span>
                                    </span>
                                </template>
                                <template x-if="!activity.causer_type"><span class="al-muted">System</span></template>
                            </dd>
                        </div>
                        <div class="al-diff__row" x-show="activity.subject_type">
                            <dt class="al-diff__key">Subject</dt>
                            <dd class="al-break">
                                <span x-text="activity.subject_type"></span>
                                <span class="al-faint al-mono" x-text="'#' + activity.subject_id"></span>
                            </dd>
                        </div>
                    </dl>

                    {{-- Changes are rendered as a field-by-field diff rather than
                         dumped as JSON. The whole point of the record is what
                         went from what to what. --}}
                    <div x-data="{
                             get changes() {
                                 if (activity.attribute_changes) return activity.attribute_changes;
                                 const props = activity.properties;
                                 if (props && (props.old || props.attributes)) {
                                     return { old: props.old, attributes: props.attributes };
                                 }
                                 return null;
                             }
                         }"
                         x-show="changes">
                        <h4 style="margin-bottom:.5rem">Changes</h4>
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

                    <div x-data="{
                             get extra() {
                                 const props = activity.properties || {};
                                 const rest = Object.fromEntries(
                                     Object.entries(props).filter(([key]) => key !== 'old' && key !== 'attributes')
                                 );
                                 return Object.keys(rest).length > 0 ? rest : null;
                             }
                         }"
                         x-show="extra">
                        <h4 style="margin-bottom:.5rem">Properties</h4>
                        <pre class="al-code al-scroll" x-text="JSON.stringify(extra, null, 2)"></pre>
                    </div>
                </div>
            </template>
        </div>

        <div class="al-dialog__footer">
            <button type="button" class="al-btn" @click="open = false">Close</button>
        </div>
    </div>
</div>
