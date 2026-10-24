<?php

return [
    'trial_days' => max(0, (int) env('FREE_TRIAL_DAYS', 15)),
    'subscription_grace_days' => max(0, (int) env('SUBSCRIPTION_GRACE_DAYS', 3)),
];
