<?php

return [
    /*
    |--------------------------------------------------------------------------
    | New savings box defaults
    |--------------------------------------------------------------------------
    |
    | These values are consulted only while a new savings box is created.
    | Every calculated value is copied to the savings_boxes table so changing
    | this file never changes an existing box.
    |
    */
    'months_available' => [1, 2, 3],
    'days_per_cycle' => 14,

    /* The scheduler only decides when the settlement service is invoked. */
    'settlement_time' => '23:59',
];
