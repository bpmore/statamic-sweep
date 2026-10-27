<script setup>
import { router } from '@statamic/cms/inertia';
import { Head } from '@statamic/cms/inertia';
import { Badge, Button, Header, Panel, Table, TableCell, TableColumn, TableColumns, TableRow, TableRows, Text } from '@statamic/cms/ui';

/**
 * Every sweep, newest first: who, when, find, replace, scope, counts, status.
 */
const props = defineProps({
    sweeps: Array,
    page: Number,
    pages: Number,
    searchUrl: String,
});

const go = (page) => router.get(window.location.pathname, { page }, { preserveState: true });
const when = (iso) => (iso ? new Date(iso).toLocaleString() : '');
</script>

<template>
    <Head :title="__('sweep::messages.history_title')" />

    <Header :title="__('sweep::messages.history_title')" icon="replace">
        <Button :href="searchUrl" variant="primary" :text="__('sweep::messages.new_search')" />
    </Header>

    <Text v-if="sweeps.length === 0">{{ __('sweep::messages.history_empty') }}</Text>

    <Panel v-else>
        <Table>
            <caption class="sr-only">{{ __('sweep::messages.history_title') }}</caption>
            <TableColumns>
                <TableColumn>{{ __('sweep::messages.column_when') }}</TableColumn>
                <TableColumn>{{ __('sweep::messages.column_who') }}</TableColumn>
                <TableColumn>{{ __('sweep::messages.field_find') }}</TableColumn>
                <TableColumn>{{ __('sweep::messages.field_replace') }}</TableColumn>
                <TableColumn>{{ __('sweep::messages.where') }}</TableColumn>
                <TableColumn>{{ __('sweep::messages.column_counts') }}</TableColumn>
                <TableColumn>{{ __('sweep::messages.column_status') }}</TableColumn>
                <TableColumn><span class="sr-only">{{ __('sweep::messages.column_actions') }}</span></TableColumn>
            </TableColumns>
            <TableRows>
                <TableRow v-for="sweep in sweeps" :key="sweep.id">
                    <TableCell><time :datetime="sweep.created_at">{{ when(sweep.created_at) }}</time></TableCell>
                    <TableCell>{{ sweep.created_by }}</TableCell>
                    <TableCell><code>{{ sweep.find }}</code></TableCell>
                    <TableCell><code>{{ sweep.replace }}</code></TableCell>
                    <TableCell>{{ sweep.scope }}</TableCell>
                    <TableCell>
                        {{ __('sweep::messages.history_counts', { matches: __n('sweep::messages.n_matches', sweep.tally.matches, { count: sweep.tally.matches }), items: __n('sweep::messages.n_items', sweep.tally.matched_items, { count: sweep.tally.matched_items }) }) }}
                        <template v-if="sweep.apply_tally.applied !== undefined">
                            <br />{{ __('sweep::messages.history_applied_count', { count: sweep.apply_tally.applied }) }}
                        </template>
                    </TableCell>
                    <TableCell><Badge :text="__(`sweep::messages.status_${sweep.status}`)" /></TableCell>
                    <TableCell>
                        <div class="flex gap-2">
                            <Button v-if="sweep.may_open" size="sm" :href="sweep.url" :text="__('sweep::messages.open')" :aria-label="__('sweep::messages.open_sweep', { find: sweep.find })" />
                            <Button size="sm" variant="ghost" :href="sweep.again_url" :text="__('sweep::messages.search_again')" :aria-label="__('sweep::messages.search_again_for', { find: sweep.find })" />
                        </div>
                    </TableCell>
                </TableRow>
            </TableRows>
        </Table>
    </Panel>

    <nav v-if="pages > 1" class="mt-4 flex items-center gap-3" :aria-label="__('sweep::messages.pages')">
        <Button variant="ghost" :disabled="page <= 1" :text="__('sweep::messages.previous')" @click="go(page - 1)" />
        <Text>{{ __('sweep::messages.page_of', { current: page, total: pages }) }}</Text>
        <Button variant="ghost" :disabled="page >= pages" :text="__('sweep::messages.next')" @click="go(page + 1)" />
    </nav>
</template>
