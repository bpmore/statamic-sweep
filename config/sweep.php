<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Where sweeps are kept
    |--------------------------------------------------------------------------
    |
    | Every search writes a sweep: one YAML file listing each item, field,
    | before value and after value. Applying and undoing read the same file.
    | It is machine data, not something editors author, so it lives under
    | storage/ rather than in content/.
    |
    */

    'path' => storage_path('sweep'),

    /*
    |--------------------------------------------------------------------------
    | Where presets are kept
    |--------------------------------------------------------------------------
    |
    | Saved searches people make are YAML files here, one per preset, so they
    | can be committed and shared like any other site file. The three presets
    | that ship with Sweep (domain change, WordPress shortcode cleanup,
    | rebrand) are read from the addon and need no file here.
    |
    */

    'presets_path' => resource_path('sweep/presets'),

    /*
    |--------------------------------------------------------------------------
    | How long undo stays available
    |--------------------------------------------------------------------------
    |
    | The number of days after a sweep is applied that it can still be undone.
    | After that, `sweep:prune` (on the daily schedule) deletes what undo
    | needs: the list of changed items and their old text. The history keeps
    | the sweep itself: who ran it, when, what it found and replaced, and the
    | counts. A preview that was never applied is pruned the same number of
    | days after it was made.
    |
    */

    'undo_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | Other fieldtypes that hold plain text
    |--------------------------------------------------------------------------
    |
    | Sweep searches Statamic's own text fieldtypes (text, textarea, markdown,
    | bard, link, list, table, array, video). A fieldtype from an addon is
    | only searched when its handle is listed here, because Sweep cannot
    | tell a string of text from a string that is a key, a handle or an id.
    | List only fieldtypes whose saved value is a string a person wrote.
    |
    */

    'fieldtypes' => [
        // 'seo_title',
    ],

];
