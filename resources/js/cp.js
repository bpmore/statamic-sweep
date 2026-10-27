import { inertia } from '@statamic/cms/api';

import History from './pages/History.vue';
import Search from './pages/Search.vue';
import Sweep from './pages/Sweep.vue';

/**
 * The addon's control panel pages. Names start `sweep/`, which no other
 * bpmore addon uses.
 */
inertia.register('sweep/Search', Search);
inertia.register('sweep/Sweep', Sweep);
inertia.register('sweep/History', History);
