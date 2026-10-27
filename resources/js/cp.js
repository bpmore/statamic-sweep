import { inertia } from '@statamic/cms/api';

import Search from './pages/Search.vue';

/**
 * The addon's control panel pages. Names start `sweep/`, which no other
 * bpmore addon uses.
 */
inertia.register('sweep/Search', Search);
