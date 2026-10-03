<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Face Descriptor Extraction
    |--------------------------------------------------------------------------
    |
    | node-face/ is a standalone Node.js CLI (kept out of the main npm
    | project so it's never pulled into the Vite/browser bundle) that
    | extracts a 128-value face descriptor from a photo using
    | @vladmandic/face-api. It has its own node_modules — run
    | `npm install` inside node-face/ once per deploy, separately from
    | the main frontend build.
    |
    */

    'node_binary' => env('FACE_NODE_BINARY', 'node'),

    'script_path' => base_path('node-face/extract-descriptor.js'),

    'timeout_seconds' => env('FACE_EXTRACT_TIMEOUT', 30),

    // face-api.js's documented threshold: a Euclidean distance at or
    // below this is considered the same person.
    'match_threshold' => env('FACE_MATCH_THRESHOLD', 0.6),

];
