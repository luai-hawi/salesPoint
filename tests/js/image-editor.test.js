import test from 'node:test';
import assert from 'node:assert/strict';

class StubClassList {
    constructor() {
        this.values = new Set();
    }

    add(...items) {
        items.forEach((item) => this.values.add(item));
    }

    remove(...items) {
        items.forEach((item) => this.values.delete(item));
    }

    toggle(item, force) {
        if (force === undefined ? !this.values.has(item) : force) {
            this.values.add(item);
            return true;
        }

        this.values.delete(item);
        return false;
    }

    contains(item) {
        return this.values.has(item);
    }
}

class StubNode {
    constructor(name = 'div') {
        this.nodeName = name.toUpperCase();
        this.dataset = {};
        this.classList = new StubClassList();
        this.listeners = new Map();
        this.textContent = '';
        this.children = [];
        this.files = [];
        this.value = '';
    }

    addEventListener(name, handler) {
        if (!this.listeners.has(name)) {
            this.listeners.set(name, []);
        }
        this.listeners.get(name).push(handler);
    }

    dispatch(name, event = {}) {
        for (const handler of this.listeners.get(name) || []) {
            handler({
                preventDefault() {},
                target: this,
                ...event,
            });
        }
    }

    appendChild(child) {
        this.children.push(child);
        return child;
    }

    click() {
        this.dispatch('click');
    }
}

class StubRoot extends StubNode {
    constructor(options = {}) {
        super('section');
        this.dataset.imageUploader = '';
        this.dataset.imageUploaderConfig = JSON.stringify(options.config || {
            existing: [],
            storageBaseUrl: 'http://example.test/storage',
            strings: {
                fallback: 'fallback',
                existing_image: 'Existing image',
                new_image: 'New image',
                primary: 'Primary',
                queue: 'Image :current of :total',
            },
        });
        this.matchesValue = true;
        this.map = new Map(options.map || []);
    }

    matches(selector) {
        return selector === '[data-image-uploader]' ? this.matchesValue : false;
    }

    querySelector(selector) {
        return this.map.get(selector) || null;
    }

    querySelectorAll(selector) {
        const value = this.map.get(selector);
        return Array.isArray(value) ? value : [];
    }
}

function createDocument(root) {
    const domListeners = new Map();

    return {
        addEventListener(name, handler) {
            if (!domListeners.has(name)) {
                domListeners.set(name, []);
            }
            domListeners.get(name).push(handler);
        },
        dispatch(name) {
            for (const handler of domListeners.get(name) || []) {
                handler();
            }
        },
        querySelectorAll(selector) {
            return selector === '[data-image-uploader]' ? [root] : [];
        },
        createElement(name) {
            return new StubNode(name);
        },
    };
}

function buildMinimalRoot({ withInput = true, withOpenButton = false, withModal = false } = {}) {
    const warning = new StubNode('p');
    const grid = new StubNode('div');
    const template = new StubNode('template');
    template.content = {
        cloneNode() {
            return {
                querySelector() {
                    return new StubNode('div');
                },
            };
        },
    };

    const map = new Map([
        ['[data-image-grid]', grid],
        ['[data-image-warning]', warning],
        ['[data-remaining-slot-count]', new StubNode('span')],
        ['[data-image-item-template]', template],
        ['[data-existing-inputs]', new StubNode('div')],
        ['[data-order-inputs]', new StubNode('div')],
    ]);

    if (withInput) {
        map.set('[data-image-input]', new StubNode('input'));
    }

    if (withOpenButton) {
        map.set('[data-open-file-dialog]', new StubNode('button'));
    }

    if (withModal) {
        map.set('[data-image-editor-modal]', {
            classList: new StubClassList(),
            querySelectorAll() {
                return [];
            },
            querySelector() {
                return null;
            },
        });
    }

    return new StubRoot({ map });
}

test('image editor mounts when the uploader attribute is on the root element', async () => {
    const root = buildMinimalRoot();
    globalThis.window = globalThis;
    globalThis.document = createDocument(root);
    globalThis.DataTransfer = undefined;
    globalThis.URL = { createObjectURL() { return 'blob:test'; } };

    if (!globalThis.SalesPointImageEditor) {
        await import('../../public/js/image-editor.js');
    }

    globalThis.SalesPointImageEditor.mountAll(globalThis.document);

    assert.ok(root.__productImageUploader);
    assert.ok((root.listeners.get('drop') || []).length >= 1);
});

test('image editor tolerates missing file input and modal canvas bindings', async () => {
    const root = buildMinimalRoot({ withOpenButton: true, withModal: true, withInput: false });
    globalThis.window = globalThis;
    globalThis.document = createDocument(root);
    globalThis.DataTransfer = undefined;
    globalThis.URL = { createObjectURL() { return 'blob:test'; } };

    if (!globalThis.SalesPointImageEditor) {
        await import('../../public/js/image-editor.js');
    }

    globalThis.SalesPointImageEditor.mountAll(globalThis.document);

    const openButton = root.querySelector('[data-open-file-dialog]');
    assert.doesNotThrow(() => openButton.click());
});

test('image editor transform math keeps crop corners reversible across rotations and flips', async () => {
    globalThis.window = globalThis;
    globalThis.document = createDocument(buildMinimalRoot());
    globalThis.DataTransfer = undefined;
    globalThis.URL = { createObjectURL() { return 'blob:test'; }, revokeObjectURL() {} };

    if (!globalThis.SalesPointImageEditorMath) {
        await import('../../public/js/image-editor.js');
    }

    const metrics = globalThis.SalesPointImageEditorMath.buildViewportMetrics(
        { width: 1200, height: 800 },
        { zoom: 1.4, panX: 35, panY: -22, rotate: 270, flipX: -1, flipY: 1 },
        900,
        640
    );

    const point = { x: 220, y: 160 };
    const projected = globalThis.SalesPointImageEditorMath.applyMatrix(metrics.matrix, point);
    const reversed = globalThis.SalesPointImageEditorMath.applyMatrix(metrics.inverse, projected);

    assert.ok(Math.abs(reversed.x - point.x) < 0.001);
    assert.ok(Math.abs(reversed.y - point.y) < 0.001);

    const corners = globalThis.SalesPointImageEditorMath.cropCorners({ x: 120, y: 80, width: 300, height: 180 })
        .map((corner) => globalThis.SalesPointImageEditorMath.applyMatrix(metrics.matrix, corner));
    const bounds = globalThis.SalesPointImageEditorMath.cropBounds(corners);

    assert.ok(bounds.maxX > bounds.minX);
    assert.ok(bounds.maxY > bounds.minY);
});

function pointerEditor() {
    const root = buildMinimalRoot();
    globalThis.document = createDocument(root);
    globalThis.SalesPointImageEditor.mountAll(globalThis.document);
    const editor = root.__productImageUploader;
    editor.editorCanvas = {
        width: 600, height: 400,
        getBoundingClientRect() { return { left: 10, top: 20, width: 300, height: 200 }; },
        setPointerCapture() {}, hasPointerCapture() { return false; },
    };
    editor.currentTask = {
        source: { width: 6000, height: 4000 },
        state: { zoom: 1, panX: 0, panY: 0, rotate: 0, flipX: 1, flipY: 1,
            crop: { x: 1200, y: 800, width: 3000, height: 2000 } },
    };
    editor.drawEditor = function () {
        this.editorMetrics = globalThis.SalesPointImageEditorMath.buildViewportMetrics(
            this.currentTask.source, this.currentTask.state, 600, 400
        );
    };
    editor.drawEditor();
    return editor;
}

function pointerEvent(editor, point, id = 1) {
    return { clientX: 10 + point.x / 2, clientY: 20 + point.y / 2,
        pointerId: id, button: 0, preventDefault() {} };
}

test('dragging inside crop moves the selection without panning a high-resolution image', () => {
    const editor = pointerEditor();
    const math = globalThis.SalesPointImageEditorMath;
    const start = math.applyMatrix(editor.editorMetrics.matrix, { x: 2500, y: 1800 });
    editor.onPointerDown(pointerEvent(editor, start));
    assert.equal(editor.activePointerMode, 'moveCrop');
    editor.onPointerMove(pointerEvent(editor, { x: start.x + 44, y: start.y + 22 }));
    assert.ok(Math.abs(editor.currentTask.state.crop.x - 1700) < 0.01);
    assert.equal(editor.currentTask.state.panX, 0);
    assert.equal(editor.currentTask.state.panY, 0);
    editor.onPointerUp({ pointerId: 1 });
    assert.equal(editor.pointerStart, null);
});

test('panning uses stable canvas pixel deltas even with repeated moves and scaled display', () => {
    const editor = pointerEditor();
    editor.onPointerDown(pointerEvent(editor, { x: 10, y: 10 }));
    assert.equal(editor.activePointerMode, 'panImage');
    for (const offset of [10, 20, 30, 40, 50]) {
        editor.onPointerMove(pointerEvent(editor, { x: 10 + offset, y: 10 + offset }));
    }
    assert.equal(editor.currentTask.state.panX, 50);
    assert.equal(editor.currentTask.state.panY, 50);
    editor.onPointerMove(pointerEvent(editor, { x: 200, y: 200 }, 2));
    assert.equal(editor.currentTask.state.panX, 50);
});

test('crop resize hit target remains in screen pixels and rotated crop movement remains bounded', () => {
    const editor = pointerEditor();
    const math = globalThis.SalesPointImageEditorMath;
    editor.currentTask.state.rotate = 90;
    editor.drawEditor();
    const corner = math.applyMatrix(editor.editorMetrics.matrix, { x: 4200, y: 2800 });
    editor.onPointerDown(pointerEvent(editor, corner));
    assert.equal(editor.activePointerMode, 'resize');
    editor.onPointerMove(pointerEvent(editor, { x: corner.x, y: corner.y + 40 }));
    assert.ok(editor.currentTask.state.crop.width > 3000);
    assert.equal(editor.currentTask.state.panX, 0);
});
