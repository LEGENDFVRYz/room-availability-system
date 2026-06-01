<?php

return [

    /*
    |
    | Temporarily (defaults)
    | - Fixed Current School Year and Semester
    |
    */
    'current_school_year' => env('CURRENT_SCHOOL_YEAR', '2026-2027'),
    
    'current_semester' => env('CURRENT_SEMESTER', '1st Semester'),


    /*
    |
    | Polling Rate Configuration (defaults)
    | - Mostly for optimization for both dev/prod setup
    |
    */
    'room_status_poll_interval' => env('ROOM_STATUS_POLL_INTERVAL', 30000),

    'kiosk_poll_interval' => env('KIOSK_POLL_INTERVAL', 15000),

    'status_warning_minutes' => env('STATUS_WARNING_MINUTES', 15),


    /*
    |
    | Daily Operations Claim Grace Period
    | - Regular scheduled classes are considered claimable until this many minutes
    |
    */
    'claim_grace_minutes' => (int) env('DAILY_OPERATION_CLAIM_GRACE_MINUTES', 60),
];