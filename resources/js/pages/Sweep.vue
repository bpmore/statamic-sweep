<script setup>
import { computed, getCurrentInstance, ref, watch } from 'vue';
import { Head, router, usePoll } from '@statamic/cms/inertia';
import { Alert, Badge, Button, Card, Checkbox, Header, Heading, Panel, PanelHeader, Text } from '@statamic/cms/ui';

/**
 * One sweep: its progress while a job works on it, every match found as an
 * inline diff, and applying or undoing it.
 */
const props = defineProps({
    sweep: Object,
    items: Object,
    mayApply: Boolean,
    mayUndo: Boolean,
    urls: Object,
});

const t = getCurrentInstance().proxy.__;
const working = computed(() => props.sweep.working);
const confirming = ref(null); // 'apply' | 'undo' | null

// While a job works on the sweep, the page asks for fresh counts and the
// current page of results every two seconds, so results appear as each
// chunk is written.
const { start, stop } = usePoll(2000, { only: ['sweep', 'items', 'mayApply'] }, { autoStart: false });
watch(working, (now) => (now ? start() : stop()), { immediate: true });

const percent = computed(() => {
    const { done, total } = props.sweep.progress ?? {};
    return total ? Math.min(100, Math.round((done / total) * 100)) : null;
});

// One sentence about where the sweep is, read out by screen readers as it
// changes (the region is aria-live).
const statusText = computed(() => {
    const s = props.sweep;
    const p = s.progress ?? {};
    switch (s.status) {
        case 'searching':
            return t('sweep::messages.live_searching', { done: p.done ?? 0, found: s.items });
        case 'applying':
            return t('sweep::messages.live_applying', { done: p.done ?? 0, total: p.total ?? s.items });
        case 'undoing':
            return t('sweep::messages.live_undoing', { done: p.done ?? 0, total: p.total ?? s.items });
        case 'previewed':
            return t('sweep::messages.live_previewed', { matches: s.tally.matches, items: s.tally.matched_items });
        case 'applied':
            return t('sweep::messages.live_applied', { count: s.apply_tally.applied ?? 0 });
        case 'undone':
            return t('sweep::messages.live_undone');
        case 'partially_undone':
            return t('sweep::messages.live_partially_undone');
        case 'failed':
            return t('sweep::messages.live_failed', { error: s.error ?? '' });
    }
    return '';
});

const counts = computed(() => {
    const tally = props.sweep.tally;
    return [
        ['count_searched', tally.searched],
        ['count_matched_items', tally.matched_items],
        ['count_matches', tally.matches],
        ['count_splits', tally.splits],
        ['count_skipped_for_permission', tally.skipped_for_permission],
    ];
});

const outcomes = computed(() => Object.entries({ ...props.sweep.apply_tally, ...props.sweep.undo_tally }).filter(([, n]) => n > 0));

const editable = computed(() => props.sweep.status === 'previewed' && props.mayApply);

function select(payload) {
    router.patch(props.urls.select, payload, { preserveScroll: true, preserveState: true, only: ['items'] });
}

function go(page) {
    router.get(window.location.pathname, { page }, { preserveState: true, preserveScroll: false, only: ['items'] });
}

function act(which) {
    confirming.value = null;
    router.post(props.urls[which], {}, { preserveScroll: true });
}

const badge = (status) => ({
    applied: 'green', restored: 'green', previewed: 'blue', searching: 'blue', applying: 'blue', undoing: 'blue',
    undone: 'default', partially_undone: 'amber', partial: 'amber', conflict: 'amber', changed_since_preview: 'amber',
    working_copy: 'amber', not_permitted: 'amber', deselected: 'default', missing: 'default', failed: 'red',
}[status] ?? 'default');
</script>

<template>
    <Head :title="__('sweep::messages.sweep_title', { find: sweep.find })" />

    <Header :title="__('sweep::messages.sweep_title', { find: sweep.find })" icon="replace">
        <Button :href="urls.history" :text="__('sweep::messages.history_title')" />
        <Button :href="urls.again" :text="__('sweep::messages.search_again')" />
    </Header>

    <Panel>
        <PanelHeader class="flex flex-wrap items-center gap-3">
            <Heading level="2">{{ __('sweep::messages.sweep_summary') }}</Heading>
            <Badge :color="badge(sweep.status)" :text="__(`sweep::messages.status_${sweep.status}`)" />
        </PanelHeader>
        <div class="space-y-4 p-4">
            <dl class="sweep-facts">
                <dt class="font-medium">{{ __('sweep::messages.field_find') }}</dt>
                <dd><code>{{ sweep.find }}</code> <span class="text-gray-600">({{ __(`sweep::messages.mode_${sweep.mode}`) }}{{ sweep.case_sensitive ? '' : ', ' + __('sweep::messages.any_case') }}{{ sweep.preserve_case ? ', ' + __('sweep::messages.case_kept') : '' }})</span></dd>
                <dt class="font-medium">{{ __('sweep::messages.field_replace') }}</dt>
                <dd><code>{{ sweep.replace }}</code></dd>
                <dt class="font-medium">{{ __('sweep::messages.where') }}</dt>
                <dd>{{ sweep.scope }}</dd>
                <dt class="font-medium">{{ __('sweep::messages.started') }}</dt>
                <dd>{{ sweep.created_by }}, <time :datetime="sweep.created_at">{{ new Date(sweep.created_at).toLocaleString() }}</time></dd>
                <template v-if="sweep.applied_at">
                    <dt class="font-medium">{{ __('sweep::messages.applied') }}</dt>
                    <dd>{{ sweep.applied_by }}, <time :datetime="sweep.applied_at">{{ new Date(sweep.applied_at).toLocaleString() }}</time></dd>
                </template>
                <template v-if="sweep.undone_at">
                    <dt class="font-medium">{{ __('sweep::messages.undone') }}</dt>
                    <dd>{{ sweep.undone_by }}, <time :datetime="sweep.undone_at">{{ new Date(sweep.undone_at).toLocaleString() }}</time></dd>
                </template>
            </dl>

            <dl class="flex flex-wrap gap-6">
                <div v-for="[key, value] in counts" :key="key">
                    <dt class="text-sm text-gray-600">{{ __(`sweep::messages.${key}`) }}</dt>
                    <dd class="text-xl font-medium">{{ value.toLocaleString() }}</dd>
                </div>
                <div v-for="[key, value] in outcomes" :key="key">
                    <dt class="text-sm text-gray-600">{{ __(`sweep::messages.outcome_${key}`) }}</dt>
                    <dd class="text-xl font-medium">{{ value.toLocaleString() }}</dd>
                </div>
            </dl>

            <div aria-live="polite" role="status">
                <Text>{{ statusText }}</Text>
            </div>

            <div v-if="working">
                <div
                    role="progressbar"
                    :aria-label="__('sweep::messages.progress_label')"
                    :aria-valuenow="percent ?? undefined"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    :aria-valuetext="statusText"
                    class="h-2 w-full overflow-hidden rounded bg-gray-200"
                >
                    <div class="h-full bg-blue-600 transition-all" :class="percent === null ? 'w-1/3 animate-pulse' : ''" :style="percent === null ? {} : { width: percent + '%' }" />
                </div>
            </div>

            <Alert v-if="sweep.status === 'failed'" type="error" :text="sweep.error" />
            <Alert v-if="sweep.pruned" type="info" :text="__('sweep::messages.pruned_notice')" />
            <Alert v-for="failure in sweep.tally.failures" :key="failure.reference" type="warning" :text="__('sweep::messages.cli_item_failed', { title: failure.title, message: failure.message })" />

            <div class="flex flex-wrap items-center gap-3">
                <template v-if="mayApply && sweep.status === 'previewed' && sweep.items > 0">
                    <Button v-if="confirming !== 'apply'" variant="primary" :text="__('sweep::messages.apply_button')" @click="confirming = 'apply'" />
                    <template v-else>
                        <Text>{{ __('sweep::messages.apply_confirm', { items: sweep.tally.matched_items }) }}</Text>
                        <Button variant="primary" :text="__('sweep::messages.apply_confirm_button')" @click="act('apply')" />
                        <Button variant="ghost" :text="__('sweep::messages.cancel')" @click="confirming = null" />
                    </template>
                </template>
                <template v-if="mayUndo && sweep.undoable">
                    <Button v-if="confirming !== 'undo'" :text="__('sweep::messages.undo_button')" @click="confirming = 'undo'" />
                    <template v-else>
                        <Text>{{ __('sweep::messages.undo_confirm') }}</Text>
                        <Button variant="danger" :text="__('sweep::messages.undo_confirm_button')" @click="act('undo')" />
                        <Button variant="ghost" :text="__('sweep::messages.cancel')" @click="confirming = null" />
                    </template>
                </template>
            </div>
        </div>
    </Panel>

    <section class="mt-6" :aria-label="__('sweep::messages.results')">
        <Heading level="2" class="mb-3">{{ __('sweep::messages.results') }}</Heading>

        <Text v-if="items.total === 0 && !working">{{ __('sweep::messages.no_results') }}</Text>

        <Card v-for="item in items.data" :key="item.index" class="mb-4">
            <article :aria-labelledby="`sweep-item-${item.index}`">
                <header class="flex flex-wrap items-center gap-3">
                    <Heading level="3" :id="`sweep-item-${item.index}`">
                        <a v-if="item.edit_url" :href="item.edit_url" class="underline">{{ item.title }}</a>
                        <template v-else>{{ item.title }}</template>
                    </Heading>
                    <Badge :text="__(`sweep::messages.kind_one_${item.kind}`)" />
                    <Badge v-if="item.site" :text="item.site" />
                    <Badge v-if="item.outcome" :color="badge(item.outcome)" :text="__(`sweep::messages.outcome_${item.outcome}`)" />
                    <Badge v-if="item.undo" :color="badge(item.undo)" :text="__(`sweep::messages.outcome_${item.undo}`)" />
                </header>
                <Text v-if="item.outcome_message" class="mt-1">{{ item.outcome_message }}</Text>

                <Checkbox
                    v-if="editable && item.changes.length"
                    class="mt-3"
                    :label="__('sweep::messages.include_item')"
                    :model-value="item.selected"
                    @update:model-value="(on) => select({ item: item.index, selected: on })"
                />

                <div v-for="(change, c) in item.changes" :key="c" class="mt-3">
                    <p class="text-sm font-medium">
                        {{ change.label }}
                        <span v-if="change.undo" class="font-normal">({{ __(`sweep::messages.outcome_${change.undo}`) }})</span>
                    </p>
                    <ol class="mt-1 space-y-2">
                        <li v-for="(hit, h) in change.hits" :key="h" class="flex items-start gap-3">
                            <Checkbox
                                v-if="editable"
                                solo
                                :label="__('sweep::messages.include_match', { n: h + 1, text: hit.text })"
                                :model-value="item.selected && hit.selected"
                                @update:model-value="(on) => select({ item: item.index, change: c, hit: h, selected: on })"
                            />
                            <p class="sweep-diff font-mono text-sm" :class="{ 'opacity-60': !item.selected || !hit.selected }">
                                <span>{{ hit.before }}</span><del><span class="sr-only">{{ __('sweep::messages.diff_removed') }}</span>{{ hit.text }}</del><ins><span class="sr-only">{{ __('sweep::messages.diff_added') }}</span>{{ hit.replacement }}</ins><span>{{ hit.after }}</span>
                            </p>
                        </li>
                    </ol>
                </div>

                <div v-if="item.splits.length" class="mt-3">
                    <Alert type="warning" :heading="__('sweep::messages.split_heading')" :text="__('sweep::messages.split_help')" />
                    <ul class="mt-2 space-y-1">
                        <li v-for="(split, s) in item.splits" :key="s" class="text-sm">
                            <span class="font-medium">{{ split.label }}:</span>
                            <span class="font-mono">{{ split.context }}</span>
                            <a v-if="item.edit_url" :href="item.edit_url" class="ml-2 underline">{{ __('sweep::messages.edit_by_hand') }}</a>
                        </li>
                    </ul>
                </div>
            </article>
        </Card>

        <nav v-if="items.pages > 1" class="mt-4 flex items-center gap-3" :aria-label="__('sweep::messages.pages')">
            <Button variant="ghost" :disabled="items.page <= 1" :text="__('sweep::messages.previous')" @click="go(items.page - 1)" />
            <Text>{{ __('sweep::messages.page_of', { page: items.page, pages: items.pages }) }}</Text>
            <Button variant="ghost" :disabled="items.page >= items.pages" :text="__('sweep::messages.next')" @click="go(items.page + 1)" />
        </nav>
    </section>
</template>
