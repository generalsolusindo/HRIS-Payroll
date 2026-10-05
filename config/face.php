<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kill switch
    |--------------------------------------------------------------------------
    |
    | Set FACE_VERIFICATION_ENABLED=false in .env to turn the entire face
    | recognition feature off without a code change: enrollment stops being
    | required to reach the dashboard/attendance, and clock-in/out no longer
    | checks or blocks on a face at all. Everything else in this file (the
    | worker, threshold, etc.) is simply unused while this is false.
    |
    */

    'verification_enabled' => env('FACE_VERIFICATION_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Face Descriptor Extraction
    |--------------------------------------------------------------------------
    |
    | node-face/ is a standalone Node.js project (kept out of the main npm
    | project so it's never pulled into the Vite/browser bundle) that
    | extracts a 128-value face descriptor from a photo using
    | @vladmandic/face-api. It has its own node_modules — run
    | `npm install` inside node-face/ once per deploy, separately from
    | the main frontend build.
    |
    | Laravel talks to node-face/server.js, a persistent worker process
    | that loads its models once and stays running (see
    | node-face/README.md for the systemd unit to keep it alive) rather
    | than spawning a fresh `node` process per request. It only ever
    | listens on 127.0.0.1 — never put it behind a public URL.
    |
    */

    'worker_url' => env('FACE_WORKER_URL', 'http://127.0.0.1:7755'),

    'worker_secret' => env('FACE_WORKER_SECRET'),

    'timeout_seconds' => env('FACE_EXTRACT_TIMEOUT', 20),

    // face-api.js's documented threshold: a Euclidean distance at or
    // below this is considered the same person.
    'match_threshold' => env('FACE_MATCH_THRESHOLD', 0.6),

    // How long a "matched" live-preview receipt stays usable. A
    // prototype value, not yet production-tuned against real network/
    // worker latency — see docs/face-verification-plan.md section 5.
    'receipt_ttl_seconds' => env('FACE_RECEIPT_TTL_SECONDS', 10),

];
