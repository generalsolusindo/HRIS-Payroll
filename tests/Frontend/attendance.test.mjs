import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { afterEach, beforeEach, test } from 'node:test';
import ts from 'typescript';

const source = readFileSync(
    new URL('../../resources/js/lib/attendance.ts', import.meta.url),
    'utf8',
);
const { outputText } = ts.transpileModule(source, {
    compilerOptions: {
        module: ts.ModuleKind.ESNext,
        target: ts.ScriptTarget.ES2022,
    },
});
const { watchBestPosition, distanceInMeters, nearestOffice } = await import(
    `data:text/javascript;base64,${Buffer.from(outputText).toString('base64')}`
);

let success, failure, cleared, options;
const originalNavigator = Object.getOwnPropertyDescriptor(
    globalThis,
    'navigator',
);

beforeEach(() => {
    cleared = [];
    Object.defineProperty(globalThis, 'navigator', {
        configurable: true,
        value: {
            geolocation: {
                watchPosition(onSuccess, onFailure, settings) {
                    success = onSuccess;
                    failure = onFailure;
                    options = settings;
                    return 7;
                },
                clearWatch(id) {
                    cleared.push(id);
                },
            },
        },
    });
});

afterEach(() => {
    if (originalNavigator)
        Object.defineProperty(globalThis, 'navigator', originalNavigator);
    else delete globalThis.navigator;
});

const position = (accuracy, latitude = -7, longitude = 112) => ({
    coords: { latitude, longitude, accuracy },
});

test('reports each improving fix but not a worse one', () => {
    const updates = [];
    watchBestPosition(
        (p) => updates.push(p.coords.accuracy),
        () => {},
    );

    success(position(180));
    success(position(90));
    success(position(150)); // worse than the current best (90) — ignored

    assert.deepEqual(updates, [180, 90]);
    assert.equal(options.enableHighAccuracy, true);
    assert.equal(options.maximumAge, 0);
});

test('the returned stop function clears the underlying watch', () => {
    const stop = watchBestPosition(
        () => {},
        () => {},
    );
    stop();
    assert.deepEqual(cleared, [7]);
});

test('surfaces permission denial', () => {
    const errors = [];
    watchBestPosition(
        () => {},
        (message) => errors.push(message),
    );

    failure({ code: 1 });
    assert.equal(errors.length, 1);
    assert.match(errors[0], /Izin lokasi ditolak/);
});

test('stays quiet on a transient position-unavailable or timeout error', () => {
    const updates = [];
    const errors = [];
    watchBestPosition(
        (p) => updates.push(p.coords.accuracy),
        (message) => errors.push(message),
    );

    failure({ code: 2 });
    failure({ code: 3 });
    success(position(30));

    assert.deepEqual(errors, []);
    assert.deepEqual(updates, [30]);
});

test('distanceInMeters is zero for the same point and grows with separation', () => {
    assert.equal(distanceInMeters(-7, 112, -7, 112), 0);
    // Roughly 1 degree of latitude is on the order of 111km.
    const oneDegreeLatitude = distanceInMeters(-7, 112, -6, 112);
    assert.ok(oneDegreeLatitude > 110_000 && oneDegreeLatitude < 112_000);
});

test('nearestOffice picks the closest office and returns null when there are none', () => {
    const offices = [
        {
            id: 1,
            name: 'Jauh',
            latitude: -6,
            longitude: 112,
            radius_meter: 100,
        },
        {
            id: 2,
            name: 'Dekat',
            latitude: -7.001,
            longitude: 112,
            radius_meter: 100,
        },
    ];

    const result = nearestOffice(offices, -7, 112);
    assert.equal(result.office.name, 'Dekat');
    assert.ok(result.distance < 200);

    assert.equal(nearestOffice([], -7, 112), null);
});
