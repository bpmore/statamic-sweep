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
    | How long undo stays available
    |--------------------------------------------------------------------------
    |
    | The number of days after a sweep is applied that it can still be undone.
    | After that its file is deleted on the daily schedule, and the history no
    | longer lists it.
    |
    */

    'undo_days' => 30,

];
