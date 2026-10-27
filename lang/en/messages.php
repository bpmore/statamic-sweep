<?php

declare(strict_types=1);

// Every string a person sees, in English. Used as `sweep::messages.<key>` in
// PHP and in the control panel's Vue code alike.
return [
    'sweep' => 'Sweep',

    'permission_use' => 'Use Sweep',
    'permission_use_description' => 'Search the site and preview changes. Results only list items this person may edit.',
    'permission_apply' => 'Apply sweeps',
    'permission_apply_description' => 'Write a previewed sweep to the site.',
    'permission_undo' => 'Undo sweeps',
    'permission_undo_description' => 'Put an applied sweep back, where nobody has edited the field since.',
    'permission_manage_presets' => 'Manage presets',
    'permission_manage_presets_description' => 'Create, edit and delete saved searches that everyone with Sweep can use.',

    'search_title' => 'Search and replace',
    'search_intro' => 'Find text across the site, preview every change, then apply the ones you want.',

    'error_starter_gone' => 'The person who started this sweep no longer has an account, so it was not searched. Start a new sweep.',

    'count_searched' => 'Items searched',
    'count_matched_items' => 'Items matched',
    'count_matches' => 'Matches',
    'count_splits' => 'Split matches (edit by hand)',
    'count_skipped_for_permission' => 'Items you may not edit (not searched)',

    'cli_regex_and_word' => 'Choose --regex or --word, not both. In a regular expression, use \\b for a word boundary.',
    'cli_previewed' => 'Preview saved as sweep :id. Nothing has been changed yet.',
    'cli_item_failed' => 'Could not search ":title": :message',
];
