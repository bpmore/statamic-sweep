<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@statamic/cms/inertia';
import { Alert, Button, Checkbox, Field, Header, Input, Panel, PanelHeader, Heading, Radio, RadioGroup, Select, Text } from '@statamic/cms/ui';

/**
 * The search screen: what to find, what to put in its place, and where to
 * look. Nothing changes here; the button makes a preview.
 */
const props = defineProps({
    options: Object,
    initial: Object,
    presets: Array,
    mayManagePresets: Boolean,
    presetsUrl: String,
    savePresetUrl: String,
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

// A preset fills in the search; where to look stays as it is.
const preset = ref(null);
const presetOptions = computed(() => props.presets.map((p) => ({ value: p.handle, label: p.title })));
const chosenPreset = computed(() => props.presets.find((p) => p.handle === preset.value) ?? null);

function usePreset(handle) {
    preset.value = handle;
    const chosen = props.presets.find((p) => p.handle === handle);
    if (!chosen) return;
    for (const key of ['find', 'replace', 'mode', 'case_sensitive', 'preserve_case']) form[key] = chosen[key];
}

const saving = ref(false);
const presetTitle = ref('');
const presetErrors = ref({});

function savePreset() {
    router.post(props.savePresetUrl, {
        title: presetTitle.value,
        find: form.find,
        replace: form.replace,
        mode: form.mode,
        case_sensitive: form.case_sensitive,
        preserve_case: form.preserve_case,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { saving.value = false; presetTitle.value = ''; presetErrors.value = {}; },
        onError: (errors) => { presetErrors.value = errors; },
    });
}
</script>

<template>
    <Head :title="__('sweep::messages.search_title')" />

    <Header :title="__('sweep::messages.search_title')" icon="replace">
        <Button :href="presetsUrl" :text="__('sweep::messages.presets_title')" />
        <Button :href="historyUrl" :text="__('sweep::messages.history_title')" />
    </Header>

    <Text class="mb-6">{{ __('sweep::messages.search_intro') }}</Text>

    <form novalidate @submit.prevent="submit">
        <Panel>
            <PanelHeader>
                <Heading level="2">{{ __('sweep::messages.search_what') }}</Heading>
            </PanelHeader>

            <div class="space-y-6 p-4">
                <Field v-if="presets.length" :label="__('sweep::messages.preset_start')" :instructions="chosenPreset?.description" id="sweep-preset">
                    <Select id="sweep-preset" :model-value="preset" :options="presetOptions" :placeholder="__('sweep::messages.preset_none')" clearable @update:model-value="usePreset" />
                </Field>

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
                        <div class="sweep-options">
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
                    <div class="sweep-options">
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

        <div class="mt-6 flex flex-wrap items-center gap-4">
            <Button type="submit" variant="primary" :loading="form.processing" :disabled="form.processing || nothingChosen" :text="__('sweep::messages.preview_button')" />
            <Button v-if="mayManagePresets && !saving" variant="ghost" :text="__('sweep::messages.preset_save')" @click="saving = true" />
            <Text v-if="nothingChosen">{{ __('sweep::messages.error_nothing_in_scope') }}</Text>
        </div>

        <div v-if="saving" class="mt-4 flex flex-wrap items-end gap-3">
            <Field :label="__('sweep::messages.preset_title')" :error="presetErrors.title" id="sweep-preset-title">
                <Input id="sweep-preset-title" v-model="presetTitle" @keydown.enter.prevent="savePreset" />
            </Field>
            <Button :text="__('sweep::messages.preset_save')" @click="savePreset" />
            <Button variant="ghost" :text="__('sweep::messages.cancel')" @click="saving = false" />
        </div>
    </form>
</template>
