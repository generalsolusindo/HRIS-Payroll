/**
 * CLI: node extract-descriptor.js <photo-path>
 *
 * Detects faces in the given photo and prints a single JSON line to
 * stdout — never throws past main() and never prints anything else to
 * stdout, so the Laravel side can parse it directly:
 *
 *   { "ok": true, "descriptor": [...128 floats] }
 *   { "ok": false, "error": "no_face" | "multiple_faces" | "load_failed" | "invalid_input" }
 *
 * Deliberately a short-lived CLI process (invoked once per verification)
 * rather than a long-running server — simpler to deploy/operate, and
 * attendance/enrollment traffic for a single company is far too low to
 * need a persistent process.
 */

// @tensorflow/tfjs-node's compiled backend still calls the long-deprecated
// util.isNullOrUndefined, which Node removed outright in v23 — restore a
// trivial polyfill before requiring tfjs-node, or tf.node.decodeImage
// throws "isNullOrUndefined is not a function" on current Node.
require('util').isNullOrUndefined =
    require('util').isNullOrUndefined ||
    ((value) => value === null || value === undefined);

const fs = require('fs');
const path = require('path');
const tf = require('@tensorflow/tfjs-node');
const faceapi = require('@vladmandic/face-api');

const MODEL_PATH = path.join(
    __dirname,
    'node_modules/@vladmandic/face-api/model',
);

function fail(error) {
    console.log(JSON.stringify({ ok: false, error }));
    process.exitCode = 0;
}

async function main() {
    const photoPath = process.argv[2];

    if (!photoPath || !fs.existsSync(photoPath)) {
        fail('invalid_input');
        return;
    }

    let models;
    try {
        await tf.ready();
        await faceapi.nets.ssdMobilenetv1.loadFromDisk(MODEL_PATH);
        await faceapi.nets.faceLandmark68Net.loadFromDisk(MODEL_PATH);
        await faceapi.nets.faceRecognitionNet.loadFromDisk(MODEL_PATH);
        models = true;
    } catch {
        models = false;
    }

    if (!models) {
        fail('load_failed');
        return;
    }

    let tensor;
    try {
        const buffer = fs.readFileSync(photoPath);
        tensor = tf.node.decodeImage(buffer, 3);
    } catch {
        fail('invalid_input');
        return;
    }

    try {
        const options = new faceapi.SsdMobilenetv1Options({
            minConfidence: 0.5,
        });
        const faces = await faceapi
            .detectAllFaces(tensor, options)
            .withFaceLandmarks()
            .withFaceDescriptors();

        if (faces.length === 0) {
            fail('no_face');
            return;
        }
        if (faces.length > 1) {
            fail('multiple_faces');
            return;
        }

        console.log(
            JSON.stringify({
                ok: true,
                descriptor: Array.from(faces[0].descriptor),
            }),
        );
    } catch {
        fail('load_failed');
    } finally {
        tf.dispose(tensor);
    }
}

void main();
