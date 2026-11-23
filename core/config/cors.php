<?php

/*
| Cross-origin requests (audit L7). Core's API is called by the Next.js server only, with the server key: no browser
| page of any site, ours included, calls it directly. So no path answers a cross-origin request: a browser preflight
| gets no Access-Control-Allow-Origin and stops there. The dashboard is same-origin and needs nothing here.
*/

return [
    'paths' => [],
    'allowed_methods' => [],
    'allowed_origins' => [],
    'allowed_origins_patterns' => [],
    'allowed_headers' => [],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
