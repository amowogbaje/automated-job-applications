<?php

return [
    // Google Cloud Console → APIs & Services → Credentials → OAuth client ID (Web application).
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    // Leave empty to use https://your-site/agent/google/callback. Whatever it is, it must be listed in Google as an authorised redirect URI.
    'redirect' => env('GOOGLE_REDIRECT_URI'),
];
