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
 *
 * Runs on the WASM TensorFlow backend rather than @tensorflow/tfjs-node's
 * native addon — the native build is compiled assuming the host CPU has
 * AVX, and crashes with SIGILL on servers whose CPU doesn't (this was
 * discovered the hard way on the real deploy target). WASM has no such
 * requirement.
 */

const fs = require('fs');
const path = require('path');
const tf = require('@tensorflow/tfjs');
const wasm = require('@tensorflow/tfjs-backend-wasm');
const image = require('@canvas/image');
const faceapi = require('@vladmandic/face-api/dist/face-api.node-wasm.js');

const MODEL_PATH = path.join(
    __dirname,
    'node_modules/@vladmandic/face-api/model',
);
const WASM_PATH =
    path.join(__dirname, 'node_modules/@tensorflow/tfjs-backend-wasm/dist') +
    path.sep;

function fail(error) {
    console.log(JSON.stringify({ ok: false, error }));
    process.exitCode = 0;
}

async function readImageAsTensor(photoPath) {
    const buffer = fs.readFileSync(photoPath);
    const canvas = await image.imageFromBuffer(buffer);
    const imageData = image.getImageData(canvas);

    return tf.tidy(() => {
        const data = tf.tensor(
            Array.from(imageData.data),
            [canvas.height, canvas.width, 4],
            'int32',
        );
        const channels = tf.split(data, 4, 2);
        const rgb = tf.stack([channels[0], channels[1], channels[2]], 2);
        return tf.squeeze(rgb);
    });
}

async function main() {
    const photoPath = process.argv[2];

    if (!photoPath || !fs.existsSync(photoPath)) {
        fail('invalid_input');
        return;
    }

    let models;
    try {
        wasm.setWasmPaths(WASM_PATH);
        await tf.setBackend('wasm');
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
        tensor = await readImageAsTensor(photoPath);
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
