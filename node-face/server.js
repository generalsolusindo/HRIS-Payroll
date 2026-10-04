/**
 * Persistent face-extraction worker.
 *
 * Loads the models once at startup and keeps them in memory, then serves
 * extraction requests over a local-only HTTP server — avoids the ~1.5-2s
 * per-call model-load cost of spawning extract-descriptor.js fresh every
 * time, which matters once the frontend starts polling for a live preview
 * every ~1-1.5s during the capture screen.
 *
 * Security: binds to 127.0.0.1 only (never reachable from outside this
 * machine) and additionally requires a shared-secret header, so another
 * local process/user on a shared host still can't call it. Run this as
 * its own long-lived service (systemd unit — see node-face/README.md),
 * independent of php-fpm's lifecycle. Never put this behind a public
 * reverse-proxy path.
 */

const http = require('http');
const { extract, loadModels, isReady } = require('./lib/extractor');

const HOST = '127.0.0.1';
const PORT = Number(process.env.FACE_WORKER_PORT || 7755);
const SECRET = process.env.FACE_WORKER_SECRET || '';
const REQUEST_TIMEOUT_MS = 15000;
const MAX_QUEUE_DEPTH = 10;

// Process one extraction at a time — this is a small, single-purpose
// worker on a modest server, not a render farm. A bounded FIFO queue
// keeps a burst of concurrent requests from piling up unbounded memory;
// anything past the limit is rejected immediately with 503 rather than
// left to queue forever.
let queueDepth = 0;
let tail = Promise.resolve();

function enqueue(fn) {
    if (queueDepth >= MAX_QUEUE_DEPTH) {
        return Promise.resolve({ ok: false, error: 'queue_full' });
    }

    queueDepth++;
    const run = tail.then(fn, fn).finally(() => {
        queueDepth--;
    });
    tail = run.catch(() => {});

    return run;
}

function withTimeout(promise, ms) {
    return Promise.race([
        promise,
        new Promise((resolve) => {
            setTimeout(() => resolve({ ok: false, error: 'timeout' }), ms);
        }),
    ]);
}

function readJsonBody(req) {
    return new Promise((resolve, reject) => {
        let body = '';
        req.on('data', (chunk) => {
            body += chunk;
            // A malformed/oversized body is more likely abuse than a
            // real photo path string, which is always tiny.
            if (body.length > 10_000) {
                reject(new Error('body_too_large'));
                req.destroy();
            }
        });
        req.on('end', () => {
            try {
                resolve(JSON.parse(body || '{}'));
            } catch {
                reject(new Error('invalid_json'));
            }
        });
        req.on('error', reject);
    });
}

function isAuthorized(req) {
    if (!SECRET) {
        return true;
    }
    return req.headers['x-worker-secret'] === SECRET;
}

function sendJson(res, status, payload) {
    res.writeHead(status, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify(payload));
}

const server = http.createServer(async (req, res) => {
    if (req.method === 'GET' && req.url === '/health') {
        sendJson(res, isReady() ? 200 : 503, { ok: isReady() });
        return;
    }

    if (req.method !== 'POST' || req.url !== '/extract') {
        sendJson(res, 404, { ok: false, error: 'not_found' });
        return;
    }

    if (!isAuthorized(req)) {
        sendJson(res, 401, { ok: false, error: 'unauthorized' });
        return;
    }

    if (!isReady()) {
        sendJson(res, 503, { ok: false, error: 'load_failed' });
        return;
    }

    let body;
    try {
        body = await readJsonBody(req);
    } catch {
        sendJson(res, 400, { ok: false, error: 'invalid_input' });
        return;
    }

    const result = await withTimeout(
        enqueue(() => extract(body.path)),
        REQUEST_TIMEOUT_MS,
    );

    sendJson(res, 200, result);
});

loadModels()
    .then(() => {
        server.listen(PORT, HOST, () => {
            console.log(`face worker ready on http://${HOST}:${PORT}`);
        });
    })
    .catch((error) => {
        console.error('face worker failed to load models:', error);
        process.exitCode = 1;
    });
