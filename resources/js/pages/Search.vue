<script setup>
import { Head } from '@statamic/cms/inertia';
import { Header, Text } from '@statamic/cms/ui';

/**
 * The search screen. The form and results arrive in the control panel phase;
 * this is the page they will live on.
 */
defineProps({
    mayApply: Boolean,
});
</script>

<template>
    <Head :title="__('sweep::messages.search_title')" />
    <Header :title="__('sweep::messages.search_title')" icon="replace" />
    <Text>{{ __('sweep::messages.search_intro') }}</Text>
</template>
