# node-face

Standalone Node.js project (separate `node_modules`, kept out of the main
`package.json` so it's never pulled into the Vite/browser bundle) that
extracts a 128-value face descriptor from a photo using
`@vladmandic/face-api`, on the WASM TensorFlow backend (no native
compilation, no CPU-specific instruction requirement — the native
`@tensorflow/tfjs-node` addon crashed with SIGILL on a real deploy target
whose CPU has no AVX).

## Files

- `server.js` — the persistent worker Laravel actually talks to. Loads
  the models once at startup and serves extraction requests over local
  HTTP, so a request costs ~0.5s instead of the ~1.5-2s a fresh process
  would cost to reload the models every time.
- `extract-descriptor.js` — a one-shot CLI wrapper around the same logic,
  kept only for manual debugging/benchmarking on the server. Laravel does
  not use this.
- `lib/extractor.js` — the shared model-loading/detection logic both of
  the above call into.

## First-time setup on a server

```bash
cd node-face
npm install
```

Create `node-face/.env.worker` (not committed — add your own):

```
FACE_WORKER_PORT=7755
FACE_WORKER_SECRET=<generate a random string, e.g. `openssl rand -hex 32`>
```

Put the **same** `FACE_WORKER_SECRET` value in the main Laravel `.env`
(`FACE_WORKER_SECRET=...`) so `config/face.php` sends it on every
request. If `FACE_WORKER_URL` isn't the default
`http://127.0.0.1:7755`, set that in the main `.env` too.

## Running it as a service (systemd)

```bash
sudo cp node-face/face-worker.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now face-worker
sudo systemctl status face-worker
```

Edit `WorkingDirectory`/`User`/`Group` in `face-worker.service` first if
your deploy path or system user differs from the placeholders in it.

Check it's actually serving:

```bash
curl http://127.0.0.1:7755/health
```

## After every deploy that touches node-face/

```bash
cd node-face
npm install                      # only if package.json changed
sudo systemctl restart face-worker
```

The worker holds the loaded models in memory — a code change under
`node-face/` (including `lib/extractor.js`) needs a restart to take
effect, `php artisan` cache-clearing commands don't touch it at all.

## Security notes

- The worker binds to `127.0.0.1` only — never route a public domain or
  reverse-proxy path to it.
- The shared-secret header is defense in depth on top of that, mainly
  useful if this machine is ever shared with other local users/processes.
- A failed/unreachable worker must never be treated as a face match —
  `FaceDescriptorExtractor` fails closed (`process_failed`) if it can't
  reach the worker or gets a non-2xx response.
