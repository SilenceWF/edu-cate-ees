<?php

return [
    // Email + password login. Meant for first setup and testing; production normally uses Microsoft SSO.
    'local_auth' => (bool) env('ENABLE_LOCAL_AUTH', false),

    // Kiosk: how many wrong PIN / employee-code attempts are allowed per 5 minutes.
    'kiosk_max_attempts' => (int) env('KIOSK_MAX_ATTEMPTS', 5),
];
