<?php

return [
    'trial_days' => max(0, (int) env('FREE_TRIAL_DAYS', 15)),
];
