<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Instance generation horizon
    |--------------------------------------------------------------------------
    |
    | When a series has no end_date, occurrences are generated up to this many
    | months ahead from today (in the series timezone).
    |
    */
    'generation_horizon_months' => (int) env('RECURRING_GENERATION_HORIZON_MONTHS', 12),

    /*
    |--------------------------------------------------------------------------
    | Safety cap per generation run
    |--------------------------------------------------------------------------
    */
    'max_instances_per_run' => (int) env('RECURRING_MAX_INSTANCES_PER_RUN', 500),

    /*
    |--------------------------------------------------------------------------
    | Queue generation
    |--------------------------------------------------------------------------
    |
    | When true, instance generation is dispatched to the queue after series
    | create/update/regenerate. When false, runs synchronously in the request.
    |
    */
    'queue_generation' => (bool) env('RECURRING_QUEUE_GENERATION', false),

    /*
    |--------------------------------------------------------------------------
    | Queue invitations & update notifications
    |--------------------------------------------------------------------------
    |
    | When true, bulk invitation creation and participant update notifications
    | are dispatched to the queue instead of running synchronously.
    |
    */
    'queue_invitations' => (bool) env('RECURRING_QUEUE_INVITATIONS', true),

    'queue_update_notifications' => (bool) env('RECURRING_QUEUE_UPDATE_NOTIFICATIONS', true),

    'invitation_chunk_size' => (int) env('RECURRING_INVITATION_CHUNK_SIZE', 25),

    'update_notification_chunk_size' => (int) env('RECURRING_UPDATE_NOTIFICATION_CHUNK_SIZE', 25),

];
