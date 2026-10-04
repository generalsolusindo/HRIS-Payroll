/**
 * CLI: node extract-descriptor.js <photo-path>
 *
 * One-shot wrapper around lib/extractor.js — kept for manual debugging
 * and benchmarking on the server. Laravel talks to server.js (the
 * persistent worker) instead, since this reloads the models from disk
 * on every single invocation (~1.5-2s), which is fine for a one-off
 * check but far too slow for the live preview checks on the capture
 * screen.
 *
 * Prints a single JSON line to stdout and nothing else:
 *   { "ok": true, "descriptor": [...128 floats] }
 *   { "ok": false, "error": "no_face" | "multiple_faces" | "load_failed" | "invalid_input" }
 */

const { extract } = require('./lib/extractor');

async function main() {
    const result = await extract(process.argv[2]);
    console.log(JSON.stringify(result));
}

void main();
