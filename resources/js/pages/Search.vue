<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@statamic/cms/inertia';
import { Alert, Button, Checkbox, Field, Header, Input, Panel, PanelHeader, Heading, Radio, RadioGroup, Text } from '@statamic/cms/ui';

/**
 * The search screen: what to find, what to put in its place, and where to
 * look. Nothing changes here; the button makes a preview.
 */
const props = defineProps({
    options: Object,
    initial: Object,
    startUrl: String,
    historyUrl: String,
});

const kinds = ['collections', 'taxonomies', 'globals', 'navs', 'containers'];
const all = (kind) => props.options[kind].map((option) => option.value);

const start = props.initial ?? {};
const form = useForm({
    find: start.find ?? '',
    replace: start.replace ?? '',
    mode: start.mode ?? 'literal',
    case_sensitive: start.case_sensitive ?? true,
    preserve_case: start.preserve_case ?? false,
    // A kind left out of a carried-over search is unticked; one that was
    // "all" (null) is every box ticked.
    ...Object.fromEntries(kinds.map((kind) => [kind, start.find === undefined || start[kind] === null ? all(kind) : (start[kind] ?? [])])),
    sites: start.sites ?? [],
    statuses: start.statuses ?? [],
    handles: start.handles ?? '',
    slugs: start.slugs ?? false,
    code: start.code ?? false,
});

const nothingChosen = computed(() => kinds.every((kind) => form[kind].length === 0));
const sitesChosen = computed(() => form.sites.length > 0);

function toggle(list, value, on) {
    const index = form[list].indexOf(value);
    if (on && index === -1) form[list].push(value);
    if (!on && index !== -1) form[list].splice(index, 1);
}

function setAll(kind, on) {
    form[kind] = on ? all(kind) : [];
}

function submit() {
    form.post(props.startUrl);
}
</script>

<template>
    <Head :title="__('sweep::messages.search_title')" />

    <Header :title="__('sweep::messages.search_title')" icon="replace">
        <Button :href="historyUrl" :text="__('sweep::messages.history_title')" />
    </Header>

    <Text class="mb-6">{{ __('sweep::messages.search_intro') }}</Text>

    <form novalidate @submit.prevent="submit">
        <Panel>
            <PanelHeader>
                <Heading level="2">{{ __('sweep::messages.search_what') }}</Heading>
            </PanelHeader>

            <div class="space-y-6 p-4">
                <Field :label="__('sweep::messages.field_find')" :error="form.errors.find" id="sweep-find" required>
                    <Input id="sweep-find" v-model="form.find" autocomplete="off" spellcheck="false" />
                </Field>

                <Field :label="__('sweep::messages.field_replace')" :instructions="form.mode === 'regex' ? __('sweep::messages.field_replace_regex_help') : __('sweep::messages.field_replace_help')" :error="form.errors.replace" id="sweep-replace">
                    <Input id="sweep-replace" v-model="form.replace" autocomplete="off" spellcheck="false" />
                </Field>

                <fieldset>
                    <legend class="mb-2 font-medium">{{ __('sweep::messages.field_mode') }}</legend>
                    <RadioGroup v-model="form.mode" name="mode">
                        <Radio value="literal" :label="__('sweep::messages.mode_literal')" :description="__('sweep::messages.mode_literal_help')" />
                        <Radio value="word" :label="__('sweep::messages.mode_word')" :description="__('sweep::messages.mode_word_help')" />
                        <Radio value="regex" :label="__('sweep::messages.mode_regex')" :description="__('sweep::messages.mode_regex_help')" />
                    </RadioGroup>
                </fieldset>

                <div class="space-y-2">
                    <Checkbox v-model="form.case_sensitive" :label="__('sweep::messages.field_case_sensitive')" />
                    <Checkbox v-model="form.preserve_case" :label="__('sweep::messages.field_preserve_case')" :description="__('sweep::messages.field_preserve_case_help')" />
                </div>
            </div>
        </Panel>

        <Panel class="mt-6">
            <PanelHeader>
                <Heading level="2">{{ __('sweep::messages.search_where') }}</Heading>
            </PanelHeader>

            <div class="space-y-6 p-4">
                <template v-for="kind in kinds" :key="kind">
                    <fieldset v-if="options[kind].length">
                        <legend class="mb-2 font-medium">{{ __(`sweep::messages.kind_${kind}`) }}</legend>
                        <div class="mb-2 flex gap-2">
                            <Button size="sm" variant="ghost" :text="__('sweep::messages.select_all')" @click="setAll(kind, true)" />
                            <Button size="sm" variant="ghost" :text="__('sweep::messages.select_none')" @click="setAll(kind, false)" />
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            <Checkbox
                                v-for="option in options[kind]"
                                :key="option.value"
                                :label="option.label"
                                :model-value="form[kind].includes(option.value)"
                                @update:model-value="(on) => toggle(kind, option.value, on)"
                            />
                        </div>
                    </fieldset>
                </template>

                <p v-if="form.errors.collections" class="text-red-600" role="alert">{{ form.errors.collections }}</p>

                <fieldset v-if="options.sites.length">
                    <legend class="mb-1 font-medium">{{ __('sweep::messages.field_sites') }}</legend>
                    <Text class="mb-2">{{ __('sweep::messages.field_sites_help') }}</Text>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <Checkbox
                            v-for="site in options.sites"
                            :key="site.value"
                            :label="site.label"
                            :model-value="form.sites.includes(site.value)"
                            @update:model-value="(on) => toggle('sites', site.value, on)"
                        />
                    </div>
                    <Alert v-if="sitesChosen && form.containers.length" class="mt-3" type="info" :text="__('sweep::messages.assets_shared_by_sites')" />
                </fieldset>

                <fieldset>
                    <legend class="mb-1 font-medium">{{ __('sweep::messages.field_statuses') }}</legend>
                    <Text class="mb-2">{{ __('sweep::messages.field_statuses_help') }}</Text>
                    <div class="flex flex-wrap gap-4">
                        <Checkbox
                            v-for="status in options.statuses"
                            :key="status.value"
                            :label="status.label"
                            :model-value="form.statuses.includes(status.value)"
                            @update:model-value="(on) => toggle('statuses', status.value, on)"
                        />
                    </div>
                </fieldset>

                <Field :label="__('sweep::messages.field_handles')" :instructions="__('sweep::messages.field_handles_help')" :error="form.errors.handles" id="sweep-handles">
                    <Input id="sweep-handles" v-model="form.handles" autocomplete="off" spellcheck="false" />
                </Field>

                <div class="space-y-2">
                    <Checkbox v-model="form.code" :label="__('sweep::messages.field_code')" />
                    <Checkbox v-model="form.slugs" :label="__('sweep::messages.field_slugs')" />
                    <Alert v-if="form.slugs" type="warning" :text="__('sweep::messages.slugs_warning')" />
                </div>
            </div>
        </Panel>

        <div class="mt-6 flex items-center gap-4">
            <Button type="submit" variant="primary" :loading="form.processing" :disabled="form.processing || nothingChosen" :text="__('sweep::messages.preview_button')" />
            <Text v-if="nothingChosen">{{ __('sweep::messages.error_nothing_in_scope') }}</Text>
        </div>
    </form>
</template>
