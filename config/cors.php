<?php

/* Only the portfolio brief API is called from another origin. No cookies, so credentials stay off. */
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['POST', 'OPTIONS'],
    'allowed_origins' => ['https://www.amowogbaje.com', 'https://amowogbaje.com', 'http://localhost:5173'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Accept'],
    'exposed_headers' => [],
    'max_age' => 86400,
    'supports_credentials' => false,
];
