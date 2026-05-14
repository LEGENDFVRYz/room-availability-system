<?php

return [

    /*
    |
    | Production Variables Configuration (defaults)
    | - Mostly configuration for KIOSK behaviuor
    |
    */
    'display_name' => env('KIOSK_DISPLAY_NAME', 'CPE Room Availability Board'),

    'show_clock' => env('KIOSK_SHOW_CLOCK', true),

    'notice_rotation_seconds' => env('KIOSK_NOTICE_ROTATION_SECONDS', 10),
];