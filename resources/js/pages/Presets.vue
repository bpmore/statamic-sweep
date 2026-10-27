<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@statamic/cms/inertia';
import { Badge, Button, Card, Field, Header, Heading, Text, Textarea } from '@statamic/cms/ui';

/**
 * Saved searches: download one to share it, import one from another site,
 * or delete one of the site's own.
 */
const props = defineProps({
    presets: Array,
    mayManage: Boolean,
    storeUrl: String,
    searchUrl: String,
});

const imported = useForm({ yaml: '' });
const deleting = ref(null);

function importPreset() {
    imported.post(props.storeUrl, { preserveScroll: true, onSuccess: () => imported.reset() });
}

function destroy(preset) {
    deleting.value = null;
    router.delete(preset.delete_url, { preserveScroll: true });
}
</script>

<template>
    <Head :title="__('sweep::messages.presets_title')" />

    <Header :title="__('sweep::messages.presets_title')" icon="replace">
        <Button :href="searchUrl" variant="primary" :text="__('sweep::messages.new_search')" />
    </Header>

    <Text class="mb-6">{{ __('sweep::messages.presets_intro') }}</Text>

    <Card v-for="preset in presets" :key="preset.handle" class="mb-4">
        <article :aria-labelledby="`sweep-preset-${preset.handle}`">
            <header class="flex flex-wrap items-center gap-3">
                <Heading level="2" :id="`sweep-preset-${preset.handle}`">{{ preset.title }}</Heading>
                <Badge v-if="preset.built_in" :text="__('sweep::messages.preset_built_in')" />
            </header>
            <Text v-if="preset.description" class="mt-1">{{ preset.description }}</Text>
            <pre class="sweep-yaml mt-3 text-sm"><code>{{ preset.yaml }}</code></pre>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <Button size="sm" :href="preset.export_url" :text="__('sweep::messages.preset_download')" :aria-label="__('sweep::messages.preset_download_named', { title: preset.title })" />
                <template v-if="mayManage && preset.delete_url">
                    <Button v-if="deleting !== preset.handle" size="sm" variant="ghost" :text="__('sweep::messages.preset_delete')" :aria-label="__('sweep::messages.preset_delete_named', { title: preset.title })" @click="deleting = preset.handle" />
                    <template v-else>
                        <Button size="sm" variant="danger" :text="__('sweep::messages.preset_delete_named', { title: preset.title })" @click="destroy(preset)" />
                        <Button size="sm" variant="ghost" :text="__('sweep::messages.cancel')" @click="deleting = null" />
                    </template>
                </template>
            </div>
        </article>
    </Card>

    <Card v-if="mayManage" class="mt-6">
        <form @submit.prevent="importPreset">
            <Heading level="2" class="mb-3">{{ __('sweep::messages.preset_import') }}</Heading>
            <Field :label="__('sweep::messages.preset_import')" :instructions="__('sweep::messages.preset_import_help')" :error="imported.errors.yaml" id="sweep-preset-yaml">
                <Textarea id="sweep-preset-yaml" v-model="imported.yaml" rows="8" spellcheck="false" />
            </Field>
            <Button class="mt-3" type="submit" :loading="imported.processing" :text="__('sweep::messages.preset_import_button')" />
        </form>
    </Card>
    <Text v-else class="mt-6">{{ __('sweep::messages.presets_manage_note') }}</Text>
</template>
