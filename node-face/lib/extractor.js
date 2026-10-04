/**
 * Shared face-descriptor extraction logic, used by both:
 *   - server.js   — the persistent worker (models loaded once, fast)
 *   - cli.js       — a one-shot wrapper kept around for manual debugging/
 *                     benchmarking on the server, not used by Laravel
 *
 * Runs on the WASM TensorFlow backend rather than @tensorflow/tfjs-node's
 * native addon — the native build is compiled assuming the host CPU has
 * AVX, and crashes with SIGILL on servers whose CPU doesn't (discovered
 * the hard way on the real deploy target). WASM has no such requirement.
 */

const fs = require('fs');
const path = require('path');
const tf = require('@tensorflow/tfjs');
const wasm = require('@tensorflow/tfjs-backend-wasm');
const image = require('@canvas/image');
const faceapi = require('@vladmandic/face-api/dist/face-api.node-wasm.js');

const MODEL_PATH = path.join(
    __dirname,
    '..',
    'node_modules/@vladmandic/face-api/model',
);
const WASM_PATH =
    path.join(
        __dirname,
        '..',
        'node_modules/@tensorflow/tfjs-backend-wasm/dist',
    ) + path.sep;

let modelsLoaded = false;

async function loadModels() {
    if (modelsLoaded) {
        return;
    }

    wasm.setWasmPaths(WASM_PATH);
    await tf.setBackend('wasm');
    await tf.ready();
    await faceapi.nets.ssdMobilenetv1.loadFromDisk(MODEL_PATH);
    await faceapi.nets.faceLandmark68Net.loadFromDisk(MODEL_PATH);
    await faceapi.nets.faceRecognitionNet.loadFromDisk(MODEL_PATH);
    modelsLoaded = true;
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

/**
 * @param {string} photoPath
 * @returns {Promise<{ok: true, descriptor: number[]} | {ok: false, error: string}>}
 */
async function extract(photoPath) {
    if (!photoPath || !fs.existsSync(photoPath)) {
        return { ok: false, error: 'invalid_input' };
    }

    try {
        await loadModels();
    } catch {
        return { ok: false, error: 'load_failed' };
    }

    let tensor;
    try {
        tensor = await readImageAsTensor(photoPath);
    } catch {
        return { ok: false, error: 'invalid_input' };
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
            return { ok: false, error: 'no_face' };
        }
        if (faces.length > 1) {
            return { ok: false, error: 'multiple_faces' };
        }

        return { ok: true, descriptor: Array.from(faces[0].descriptor) };
    } catch {
        return { ok: false, error: 'load_failed' };
    } finally {
        tf.dispose(tensor);
    }
}

module.exports = { extract, loadModels, isReady: () => modelsLoaded };
