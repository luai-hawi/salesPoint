(function () {
    function uid() {
        return 'img_' + Math.random().toString(36).slice(2, 10);
    }

    function clamp(value, min, max) {
        return Math.min(max, Math.max(min, value));
    }

    function humanSize(bytes) {
        const size = Number(bytes || 0);
        if (size < 1024) {
            return size + ' B';
        }
        if (size < 1024 * 1024) {
            return (size / 1024).toFixed(1) + ' KB';
        }
        return (size / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function hasDataTransfer() {
        return typeof DataTransfer !== 'undefined';
    }

    function toast(message, tone) {
        if (window.SP && typeof window.SP.toast === 'function' && message) {
            window.SP.toast(message, tone || 'info');
        }
    }

    function createEditorState(source) {
        return {
            zoom: 1,
            panX: 0,
            panY: 0,
            rotate: 0,
            flipX: 1,
            flipY: 1,
            brightness: 0,
            contrast: 0,
            saturation: 0,
            fitSquare: false,
            cropPreset: 'free',
            crop: {
                x: source.width * 0.12,
                y: source.height * 0.12,
                width: source.width * 0.76,
                height: source.height * 0.76,
            },
        };
    }

    function cloneState(state) {
        return JSON.parse(JSON.stringify(state));
    }

    function translateMatrix(tx, ty) {
        return [1, 0, 0, 1, tx, ty];
    }

    function scaleMatrix(sx, sy) {
        return [sx, 0, 0, sy, 0, 0];
    }

    function rotateMatrix(degrees) {
        const radians = (degrees * Math.PI) / 180;
        const cos = Math.cos(radians);
        const sin = Math.sin(radians);

        return [cos, sin, -sin, cos, 0, 0];
    }

    function multiplyMatrix(left, right) {
        return [
            (left[0] * right[0]) + (left[2] * right[1]),
            (left[1] * right[0]) + (left[3] * right[1]),
            (left[0] * right[2]) + (left[2] * right[3]),
            (left[1] * right[2]) + (left[3] * right[3]),
            (left[0] * right[4]) + (left[2] * right[5]) + left[4],
            (left[1] * right[4]) + (left[3] * right[5]) + left[5],
        ];
    }

    function applyMatrix(matrix, point) {
        return {
            x: (matrix[0] * point.x) + (matrix[2] * point.y) + matrix[4],
            y: (matrix[1] * point.x) + (matrix[3] * point.y) + matrix[5],
        };
    }

    function invertMatrix(matrix) {
        const determinant = (matrix[0] * matrix[3]) - (matrix[1] * matrix[2]);
        if (!determinant) {
            return null;
        }

        return [
            matrix[3] / determinant,
            -matrix[1] / determinant,
            -matrix[2] / determinant,
            matrix[0] / determinant,
            ((matrix[2] * matrix[5]) - (matrix[3] * matrix[4])) / determinant,
            ((matrix[1] * matrix[4]) - (matrix[0] * matrix[5])) / determinant,
        ];
    }

    function buildViewportMetrics(source, state, canvasWidth, canvasHeight) {
        const baseScale = Math.min(canvasWidth / source.width, canvasHeight / source.height) * 0.88;
        let matrix = translateMatrix(canvasWidth / 2 + state.panX, canvasHeight / 2 + state.panY);
        matrix = multiplyMatrix(matrix, rotateMatrix(state.rotate));
        matrix = multiplyMatrix(matrix, scaleMatrix(baseScale * state.zoom * state.flipX, baseScale * state.zoom * state.flipY));
        matrix = multiplyMatrix(matrix, translateMatrix(-source.width / 2, -source.height / 2));

        return {
            matrix: matrix,
            inverse: invertMatrix(matrix),
            baseScale: baseScale,
        };
    }

    function cropCorners(crop) {
        return [
            { x: crop.x, y: crop.y },
            { x: crop.x + crop.width, y: crop.y },
            { x: crop.x + crop.width, y: crop.y + crop.height },
            { x: crop.x, y: crop.y + crop.height },
        ];
    }

    function cropBounds(points) {
        const xs = points.map((point) => point.x);
        const ys = points.map((point) => point.y);

        return {
            minX: Math.min.apply(null, xs),
            maxX: Math.max.apply(null, xs),
            minY: Math.min.apply(null, ys),
            maxY: Math.max.apply(null, ys),
        };
    }

    class ProductImageUploader {
        constructor(root) {
            this.root = root;
            this.config = JSON.parse(root.dataset.imageUploaderConfig || '{}');
            this.input = root.querySelector('[data-image-input]');
            this.grid = root.querySelector('[data-image-grid]');
            this.warning = root.querySelector('[data-image-warning]');
            this.remaining = root.querySelector('[data-remaining-slot-count]');
            this.template = root.querySelector('[data-image-item-template]');
            this.existingInputs = root.querySelector('[data-existing-inputs]');
            this.orderInputs = root.querySelector('[data-order-inputs]');
            this.modal = root.querySelector('[data-image-editor-modal]');
            this.editorCanvas = root.querySelector('[data-editor-canvas]');
            this.previewCanvas = root.querySelector('[data-preview-canvas]');
            this.queueLabel = root.querySelector('[data-editor-queue]');
            this.items = [];
            this.pending = [];
            this.currentTask = null;
            this.history = [];
            this.dragItemId = null;
            this.activePointerMode = null;
            this.pointerStart = null;
            this.editorMetrics = null;
            this.applyInFlight = false;

            this.canvasContext = this.editorCanvas ? this.editorCanvas.getContext('2d') : null;
            this.previewContext = this.previewCanvas ? this.previewCanvas.getContext('2d') : null;

            this.loadExistingItems();
            this.bindEvents();
            this.renderGrid();
            this.syncFormState();
        }

        loadExistingItems() {
            (this.config.existing || []).forEach((path) => {
                this.items.push({
                    id: uid(),
                    token: null,
                    type: 'existing',
                    kindLabel: this.config.strings.existing_image || 'Existing image',
                    path: path,
                    previewUrl: (this.config.storageBaseUrl || '').replace(/\/$/, '') + '/' + path.replace(/^\//, ''),
                    file: null,
                    size: 0,
                });
            });
        }

        bindEvents() {
            const openFileButton = this.root.querySelector('[data-open-file-dialog]');
            if (openFileButton) {
                openFileButton.addEventListener('click', () => {
                    if (this.input && typeof this.input.click === 'function') {
                        this.input.click();
                    }
                });
            }

            if (this.input) {
                this.input.addEventListener('change', (event) => {
                    this.queueFiles(Array.from(event.target.files || []));
                });
            }

            const dropZone = this.root.matches('[data-image-uploader]') ? this.root : this.root.querySelector('[data-image-uploader]');
            if (dropZone) {
                ['dragenter', 'dragover'].forEach((name) => {
                    dropZone.addEventListener(name, (event) => {
                        event.preventDefault();
                        dropZone.classList.add('ring-2', 'ring-indigo-500');
                    });
                });
                ['dragleave', 'drop'].forEach((name) => {
                    dropZone.addEventListener(name, (event) => {
                        event.preventDefault();
                        dropZone.classList.remove('ring-2', 'ring-indigo-500');
                    });
                });
                dropZone.addEventListener('drop', (event) => {
                    this.queueFiles(Array.from(event.dataTransfer ? event.dataTransfer.files : []));
                });
            }

            if (this.modal) {
                this.modal.querySelectorAll('[data-close-editor]').forEach((button) => {
                    button.addEventListener('click', () => this.cancelEditorSession());
                });
                const keepOriginal = this.modal.querySelector('[data-keep-original]');
                if (keepOriginal) {
                    keepOriginal.addEventListener('click', () => this.keepOriginal());
                }
                const applyEditor = this.modal.querySelector('[data-apply-editor]');
                if (applyEditor) {
                    applyEditor.addEventListener('click', () => this.applyEditor());
                }
                const autoEnhance = this.modal.querySelector('[data-auto-enhance]');
                if (autoEnhance) {
                    autoEnhance.addEventListener('click', () => this.applyAutoEnhance());
                }
                const reset = this.modal.querySelector('[data-reset-editor]');
                if (reset) {
                    reset.addEventListener('click', () => this.resetEditor());
                }
                const undo = this.modal.querySelector('[data-undo-editor]');
                if (undo) {
                    undo.addEventListener('click', () => this.undoEditor());
                }
                const rotateLeft = this.modal.querySelector('[data-rotate-left]');
                const rotateRight = this.modal.querySelector('[data-rotate-right]');
                if (rotateLeft) {
                    rotateLeft.addEventListener('click', () => this.updateEditorState((state) => { state.rotate -= 90; }));
                }
                if (rotateRight) {
                    rotateRight.addEventListener('click', () => this.updateEditorState((state) => { state.rotate += 90; }));
                }
                const flipHorizontal = this.modal.querySelector('[data-flip-horizontal]');
                const flipVertical = this.modal.querySelector('[data-flip-vertical]');
                if (flipHorizontal) {
                    flipHorizontal.addEventListener('click', () => this.updateEditorState((state) => { state.flipX *= -1; }));
                }
                if (flipVertical) {
                    flipVertical.addEventListener('click', () => this.updateEditorState((state) => { state.flipY *= -1; }));
                }
                this.modal.querySelectorAll('[data-editor-slider]').forEach((slider) => {
                    slider.addEventListener('input', () => {
                        const key = slider.dataset.editorSlider;
                        this.updateEditorState((state) => {
                            if (key === 'rotation') {
                                state.rotate = Number(slider.value || 0);
                            } else {
                                state[key] = Number(slider.value || 0);
                            }
                        }, false);
                    });
                });
                this.modal.querySelectorAll('[data-crop-preset]').forEach((button) => {
                    button.addEventListener('click', () => this.setCropPreset(button.dataset.cropPreset || 'free'));
                });
                const fitSquare = this.modal.querySelector('[data-fit-square]');
                if (fitSquare) {
                    fitSquare.addEventListener('change', () => {
                        this.updateEditorState((state) => {
                            state.fitSquare = !!fitSquare.checked;
                        }, false);
                    });
                }

                if (this.editorCanvas && typeof this.editorCanvas.addEventListener === 'function') {
                    this.editorCanvas.addEventListener('wheel', (event) => {
                        event.preventDefault();
                        if (!this.currentTask) {
                            return;
                        }
                        this.updateEditorState((state) => {
                            state.zoom = clamp(state.zoom + (event.deltaY < 0 ? 0.08 : -0.08), 0.3, 5);
                        }, false);
                    }, { passive: false });

                    this.editorCanvas.addEventListener('pointerdown', (event) => this.onPointerDown(event));
                    this.editorCanvas.addEventListener('pointermove', (event) => this.onPointerMove(event));
                    this.editorCanvas.addEventListener('pointerup', (event) => this.onPointerUp(event));
                    this.editorCanvas.addEventListener('pointercancel', (event) => this.onPointerUp(event));
                    this.editorCanvas.addEventListener('lostpointercapture', () => this.onPointerUp());
                    this.editorCanvas.addEventListener('keydown', (event) => this.onCanvasKeydown(event));
                }

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && this.modal && !this.modal.classList.contains('hidden')) {
                        this.cancelEditorSession();
                    }
                });
            }
        }

        queueFiles(files, replaceItemId) {
            if (!files || !files.length) {
                return;
            }

            const maxFileBytes = Number(this.config.maxUploadKb || 0) * 1024;
            const maxRequestBytes = Number(this.config.maxRequestKb || 0) * 1024;
            const maxFilesPerRequest = Number(this.config.maxFilesPerRequest || 0);
            const totalBytes = files.reduce(function (sum, file) {
                return sum + Number(file && file.size ? file.size : 0);
            }, 0);

            if (maxFilesPerRequest > 0 && files.length > maxFilesPerRequest) {
                if (this.warning) {
                    this.warning.textContent = this.config.strings.too_many_images || '';
                    this.warning.classList.toggle('hidden', false);
                }
                return;
            }

            if (maxRequestBytes > 0 && totalBytes > maxRequestBytes) {
                if (this.warning) {
                    this.warning.textContent = this.config.strings.request_too_large || this.config.strings.file_too_large || '';
                    this.warning.classList.toggle('hidden', false);
                }
                return;
            }

            if (maxFileBytes > 0 && files.some((file) => Number(file && file.size ? file.size : 0) > maxFileBytes)) {
                if (this.warning) {
                    this.warning.textContent = this.config.strings.file_too_large || '';
                    this.warning.classList.toggle('hidden', false);
                }
                return;
            }

            files.forEach((file) => {
                this.pending.push({
                    file: file,
                    replaceItemId: replaceItemId || null,
                });
            });

            if (!this.currentTask) {
                this.openNextTask();
            }
        }

        async openNextTask() {
            this.releaseTaskSource(this.currentTask);
            this.currentTask = this.pending.shift() || null;
            if (!this.currentTask) {
                this.closeEditor();
                this.renderGrid();
                this.syncFormState();
                return;
            }

            try {
                const source = await this.loadSource(this.currentTask.file);
                this.currentTask.source = source;
                this.currentTask.state = createEditorState(source);
                this.history = [cloneState(this.currentTask.state)];
                this.openEditor();
                this.refreshEditorSliders();
                this.drawEditor();
            } catch (error) {
                toast(this.config.strings.load_failed || 'The image could not be opened for editing.', 'error');
                this.releaseTaskSource(this.currentTask);
                this.currentTask = null;
                this.openNextTask();
            }
        }

        async loadSource(input) {
            if (typeof createImageBitmap === 'function') {
                try {
                    const bitmap = await createImageBitmap(input, { imageOrientation: 'from-image' });
                    return bitmap;
                } catch (error) {
                    // fall through to Image element
                }
            }

            return await new Promise((resolve, reject) => {
                const image = new Image();
                const objectUrl = URL.createObjectURL(input);
                image.onload = () => {
                    URL.revokeObjectURL(objectUrl);
                    resolve(image);
                };
                image.onerror = (error) => {
                    URL.revokeObjectURL(objectUrl);
                    reject(error);
                };
                image.src = objectUrl;
            });
        }

        releaseTaskSource(task) {
            if (!task || !task.source) {
                return;
            }

            if (typeof task.source.close === 'function') {
                task.source.close();
            }

            task.source = null;
        }

        revokeItemPreview(item) {
            if (!item || item.type !== 'new' || !item.previewUrl || typeof URL.revokeObjectURL !== 'function') {
                return;
            }

            URL.revokeObjectURL(item.previewUrl);
        }

        openEditor() {
            if (!this.modal) {
                return;
            }
            this.modal.classList.remove('hidden');
            this.modal.classList.add('flex');
            if (this.editorCanvas && typeof this.editorCanvas.focus === 'function') {
                this.editorCanvas.focus();
            }
            if (this.queueLabel) {
                this.queueLabel.textContent = (this.config.strings.queue || 'Image :current of :total')
                    .replace(':current', String((this.config.existing || []).length + this.items.filter((item) => item.type === 'new').length + 1))
                    .replace(':total', String(this.pending.length + 1));
            }
        }

        closeEditor() {
            if (!this.modal) {
                return;
            }
            this.modal.classList.add('hidden');
            this.modal.classList.remove('flex');
            this.releaseTaskSource(this.currentTask);
            this.currentTask = null;
            this.activePointerMode = null;
            this.pointerStart = null;
            this.editorMetrics = null;
            this.applyInFlight = false;
        }

        cancelEditorSession() {
            this.pending = [];
            this.closeEditor();
            this.renderGrid();
            this.syncFormState();
        }

        keepOriginal() {
            if (!this.currentTask) {
                return;
            }

            if (this.currentTask.replaceItemId) {
                this.openNextTask();
                return;
            }

            this.insertNewItem(this.currentTask.file, this.currentTask.file.type, null, this.currentTask.replaceItemId);
            this.openNextTask();
        }

        applyAutoEnhance() {
            this.updateEditorState((state) => {
                state.brightness = 8;
                state.contrast = 12;
                state.saturation = 10;
            });
        }

        resetEditor() {
            if (!this.currentTask) {
                return;
            }
            this.currentTask.state = createEditorState(this.currentTask.source);
            this.history = [cloneState(this.currentTask.state)];
            this.refreshEditorSliders();
            this.drawEditor();
        }

        undoEditor() {
            if (this.history.length <= 1) {
                return;
            }
            this.history.pop();
            this.currentTask.state = cloneState(this.history[this.history.length - 1]);
            this.refreshEditorSliders();
            this.drawEditor();
        }

        updateEditorState(mutation, pushHistory) {
            if (!this.currentTask) {
                return;
            }
            mutation(this.currentTask.state);
            this.currentTask.state.crop.width = clamp(this.currentTask.state.crop.width, 20, this.currentTask.source.width);
            this.currentTask.state.crop.height = clamp(this.currentTask.state.crop.height, 20, this.currentTask.source.height);
            this.currentTask.state.crop.x = clamp(this.currentTask.state.crop.x, 0, this.currentTask.source.width - this.currentTask.state.crop.width);
            this.currentTask.state.crop.y = clamp(this.currentTask.state.crop.y, 0, this.currentTask.source.height - this.currentTask.state.crop.height);
            if (pushHistory !== false) {
                this.history.push(cloneState(this.currentTask.state));
                if (this.history.length > 30) {
                    this.history.shift();
                }
            }
            this.drawEditor();
        }

        refreshEditorSliders() {
            if (!this.currentTask || !this.modal) {
                return;
            }
            const state = this.currentTask.state;
            this.modal.querySelectorAll('[data-editor-slider]').forEach((slider) => {
                const key = slider.dataset.editorSlider;
                slider.value = key === 'rotation' ? String(state.rotate) : String(state[key]);
            });
            const fitSquare = this.modal.querySelector('[data-fit-square]');
            if (fitSquare) {
                fitSquare.checked = !!state.fitSquare;
            }
        }

        setCropPreset(preset) {
            if (!this.currentTask) {
                return;
            }

            this.updateEditorState((state) => {
                state.cropPreset = preset;
                if (preset === 'free') {
                    return;
                }

                const parts = preset.split(':').map(Number);
                if (parts.length !== 2 || !parts[0] || !parts[1]) {
                    return;
                }
                const ratio = parts[0] / parts[1];
                let width = state.crop.width;
                let height = width / ratio;
                if (height > this.currentTask.source.height) {
                    height = this.currentTask.source.height * 0.8;
                    width = height * ratio;
                }
                if (width > this.currentTask.source.width) {
                    width = this.currentTask.source.width * 0.8;
                    height = width / ratio;
                }
                state.crop.width = width;
                state.crop.height = height;
                state.crop.x = (this.currentTask.source.width - width) / 2;
                state.crop.y = (this.currentTask.source.height - height) / 2;
            });
        }

        onPointerDown(event) {
            if (!this.currentTask || !this.editorMetrics || (event.button !== undefined && event.button !== 0)) {
                return;
            }

            event.preventDefault();
            const pointer = this.canvasToImagePoint(event);
            const canvasPoint = this.eventToCanvasPoint(event);
            const crop = this.currentTask.state.crop;
            const corner = applyMatrix(this.editorMetrics.matrix, { x: crop.x + crop.width, y: crop.y + crop.height });
            const nearCorner = Math.hypot(canvasPoint.x - corner.x, canvasPoint.y - corner.y) <= 14;
            const insideCrop = pointer.x >= crop.x && pointer.x <= crop.x + crop.width && pointer.y >= crop.y && pointer.y <= crop.y + crop.height;

            this.pointerStart = {
                x: pointer.x,
                y: pointer.y,
                canvasX: canvasPoint.x,
                canvasY: canvasPoint.y,
                inverse: this.editorMetrics.inverse.slice(),
                pointerId: event.pointerId,
                crop: cloneState(crop),
                panX: this.currentTask.state.panX,
                panY: this.currentTask.state.panY,
            };

            if (nearCorner) {
                this.activePointerMode = 'resize';
            } else if (insideCrop) {
                this.activePointerMode = 'moveCrop';
            } else {
                this.activePointerMode = 'panImage';
            }
            if (typeof this.editorCanvas.setPointerCapture === 'function') {
                this.editorCanvas.setPointerCapture(event.pointerId);
            }
        }

        onPointerMove(event) {
            if (!this.currentTask || !this.pointerStart || !this.activePointerMode) {
                return;
            }

            if (event.pointerId !== this.pointerStart.pointerId) {
                return;
            }
            event.preventDefault();
            const canvasPoint = this.eventToCanvasPoint(event);
            const pointer = applyMatrix(this.pointerStart.inverse, canvasPoint);
            const deltaX = pointer.x - this.pointerStart.x;
            const deltaY = pointer.y - this.pointerStart.y;

            if (this.activePointerMode === 'resize') {
                this.updateEditorState((state) => {
                    state.crop.width = this.pointerStart.crop.width + deltaX;
                    state.crop.height = this.pointerStart.crop.height + deltaY;
                }, false);
            } else if (this.activePointerMode === 'moveCrop') {
                this.updateEditorState((state) => {
                    state.crop.x = this.pointerStart.crop.x + deltaX;
                    state.crop.y = this.pointerStart.crop.y + deltaY;
                }, false);
            } else {
                this.updateEditorState((state) => {
                    state.panX = this.pointerStart.panX + canvasPoint.x - this.pointerStart.canvasX;
                    state.panY = this.pointerStart.panY + canvasPoint.y - this.pointerStart.canvasY;
                }, false);
            }
        }

        onPointerUp(event) {
            if (!this.pointerStart || (event && event.pointerId !== this.pointerStart.pointerId)) {
                return;
            }
            const pointerId = this.pointerStart.pointerId;
            if (this.currentTask) {
                this.history.push(cloneState(this.currentTask.state));
            }
            this.activePointerMode = null;
            this.pointerStart = null;
            if (typeof this.editorCanvas.hasPointerCapture === 'function' && this.editorCanvas.hasPointerCapture(pointerId)) {
                this.editorCanvas.releasePointerCapture(pointerId);
            }
        }

        onCanvasKeydown(event) {
            if (!this.currentTask) {
                return;
            }

            const step = event.shiftKey ? 10 : 4;
            const keys = ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'];
            if (!keys.includes(event.key)) {
                return;
            }

            event.preventDefault();
            this.updateEditorState((state) => {
                if (event.key === 'ArrowUp') {
                    state.crop.y -= step;
                } else if (event.key === 'ArrowDown') {
                    state.crop.y += step;
                } else if (event.key === 'ArrowLeft') {
                    state.crop.x -= step;
                } else if (event.key === 'ArrowRight') {
                    state.crop.x += step;
                }
            }, false);
        }

        canvasToImagePoint(event) {
            const inverse = this.editorMetrics && this.editorMetrics.inverse ? this.editorMetrics.inverse : null;
            const canvasPoint = this.eventToCanvasPoint(event);

            if (!inverse) {
                return canvasPoint;
            }

            return applyMatrix(inverse, canvasPoint);
        }

        eventToCanvasPoint(event) {
            const rect = this.editorCanvas.getBoundingClientRect();
            return {
                x: (event.clientX - rect.left) * this.editorCanvas.width / rect.width,
                y: (event.clientY - rect.top) * this.editorCanvas.height / rect.height,
            };
        }

        drawEditor() {
            if (!this.currentTask || !this.canvasContext) {
                return;
            }

            const canvas = this.editorCanvas;
            const rect = canvas.getBoundingClientRect();
            canvas.width = Math.max(480, Math.round(rect.width || 900));
            canvas.height = Math.max(360, Math.round(rect.height || 640));

            const ctx = this.canvasContext;
            const state = this.currentTask.state;
            const source = this.currentTask.source;
            this.editorMetrics = buildViewportMetrics(source, state, canvas.width, canvas.height);
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#111827';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.save();
            ctx.setTransform(
                this.editorMetrics.matrix[0],
                this.editorMetrics.matrix[1],
                this.editorMetrics.matrix[2],
                this.editorMetrics.matrix[3],
                this.editorMetrics.matrix[4],
                this.editorMetrics.matrix[5]
            );
            ctx.filter =
                'brightness(' + (100 + state.brightness) + '%) ' +
                'contrast(' + (100 + state.contrast) + '%) ' +
                'saturate(' + (100 + state.saturation) + '%)';
            ctx.drawImage(source, 0, 0, source.width, source.height);
            ctx.restore();

            this.drawCropOverlay();
            this.drawPreview();
        }

        drawCropOverlay() {
            if (!this.currentTask || !this.canvasContext) {
                return;
            }

            const ctx = this.canvasContext;
            const crop = this.currentTask.state.crop;
            const points = cropCorners(crop).map((point) => applyMatrix(this.editorMetrics.matrix, point));

            ctx.save();
            ctx.strokeStyle = 'rgba(255,255,255,0.95)';
            ctx.lineWidth = 2;
            ctx.beginPath();
            points.forEach(function (point, index) {
                if (index === 0) {
                    ctx.moveTo(point.x, point.y);
                } else {
                    ctx.lineTo(point.x, point.y);
                }
            });
            ctx.closePath();
            ctx.stroke();
            ctx.fillStyle = 'rgba(255,255,255,0.85)';
            points.forEach(function (point) {
                ctx.fillRect(point.x - 4, point.y - 4, 8, 8);
            });
            ctx.restore();
        }

        drawPreview() {
            if (!this.previewContext || !this.currentTask) {
                return;
            }

            const preview = this.previewCanvas;
            preview.width = 320;
            preview.height = this.currentTask.state.fitSquare ? 320 : 240;
            const ctx = this.previewContext;
            ctx.clearRect(0, 0, preview.width, preview.height);

            const rendered = this.renderOutputCanvas(2);
            const fitSquare = this.currentTask.state.fitSquare;

            if (fitSquare) {
                const size = Math.max(rendered.width, rendered.height);
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, preview.width, preview.height);
                const drawWidth = preview.width;
                const drawHeight = preview.height;
                const ratio = Math.min(drawWidth / size, drawHeight / size);
                const width = rendered.width * ratio;
                const height = rendered.height * ratio;
                ctx.drawImage(rendered, (preview.width - width) / 2, (preview.height - height) / 2, width, height);
            } else {
                ctx.drawImage(rendered, 0, 0, preview.width, preview.height);
            }
        }

        renderOutputCanvas(multiplier, ignoreSquare) {
            const state = this.currentTask.state;
            const crop = state.crop;
            const source = this.currentTask.source;
            const metrics = this.editorMetrics || buildViewportMetrics(source, state, this.editorCanvas.width, this.editorCanvas.height);
            const points = cropCorners(crop).map((point) => applyMatrix(metrics.matrix, point));
            const bounds = cropBounds(points);
            const canvas = document.createElement('canvas');
            const drawWidth = Math.max(1, Math.round((bounds.maxX - bounds.minX) * multiplier));
            const drawHeight = Math.max(1, Math.round((bounds.maxY - bounds.minY) * multiplier));
            canvas.width = drawWidth;
            canvas.height = drawHeight;
            const ctx = canvas.getContext('2d');
            if (!ctx) {
                return canvas;
            }

            if (state.fitSquare && !ignoreSquare) {
                const side = Math.max(drawWidth, drawHeight);
                const square = document.createElement('canvas');
                square.width = side;
                square.height = side;
                const squareCtx = square.getContext('2d');
                if (!squareCtx) {
                    return square;
                }
                squareCtx.fillStyle = '#ffffff';
                squareCtx.fillRect(0, 0, side, side);
                squareCtx.drawImage(this.renderOutputCanvas(multiplier, true), (side - drawWidth) / 2, (side - drawHeight) / 2, drawWidth, drawHeight);
                return square;
            }

            ctx.save();
            ctx.beginPath();
            points.forEach(function (point, index) {
                const x = (point.x - bounds.minX) * multiplier;
                const y = (point.y - bounds.minY) * multiplier;
                if (index === 0) {
                    ctx.moveTo(x, y);
                } else {
                    ctx.lineTo(x, y);
                }
            });
            ctx.closePath();
            ctx.clip();
            ctx.filter =
                'brightness(' + (100 + state.brightness) + '%) ' +
                'contrast(' + (100 + state.contrast) + '%) ' +
                'saturate(' + (100 + state.saturation) + '%)';
            const matrix = metrics.matrix;
            ctx.setTransform(
                matrix[0] * multiplier,
                matrix[1] * multiplier,
                matrix[2] * multiplier,
                matrix[3] * multiplier,
                (matrix[4] - bounds.minX) * multiplier,
                (matrix[5] - bounds.minY) * multiplier
            );
            ctx.drawImage(source, 0, 0, source.width, source.height);
            ctx.restore();
            return canvas;
        }

        async applyEditor() {
            if (!this.currentTask || this.applyInFlight) {
                return;
            }

            const task = this.currentTask;
            this.applyInFlight = true;
            const output = this.renderOutputCanvas(2);
            let mime = 'image/jpeg';
            if (task.file.type === 'image/png') {
                mime = 'image/png';
            } else if (task.file.type === 'image/webp') {
                mime = 'image/webp';
            }

            const dimensions = this.scaleOutput(output.width, output.height, 1600);
            const resized = document.createElement('canvas');
            resized.width = dimensions.width;
            resized.height = dimensions.height;
            const resizedContext = resized.getContext('2d');
            if (!resizedContext) {
                this.applyInFlight = false;
                this.openNextTask();
                return;
            }
            resizedContext.drawImage(output, 0, 0, dimensions.width, dimensions.height);

            const blob = await new Promise((resolve) => resized.toBlob(resolve, mime, 0.85));
            if (!blob) {
                this.applyInFlight = false;
                this.openNextTask();
                return;
            }

            if (this.currentTask !== task) {
                this.applyInFlight = false;
                return;
            }

            const fileName = task.file.name.replace(/\.(jpg|jpeg|png|gif|webp)$/i, '') + (mime === 'image/png' ? '.png' : (mime === 'image/webp' ? '.webp' : '.jpg'));
            const file = new File([blob], fileName, { type: mime, lastModified: Date.now() });
            this.insertNewItem(file, mime, task.replaceItemId, task.replaceItemId);
            this.applyInFlight = false;
            this.openNextTask();
        }

        scaleOutput(width, height, maxSide) {
            const ratio = Math.min(1, maxSide / Math.max(width, height));
            return {
                width: Math.max(1, Math.round(width * ratio)),
                height: Math.max(1, Math.round(height * ratio)),
            };
        }

        insertNewItem(file, mime, replaceItemId) {
            const newItem = {
                id: uid(),
                token: uid(),
                type: 'new',
                kindLabel: this.config.strings.new_image || 'New image',
                path: null,
                previewUrl: URL.createObjectURL(file),
                file: file,
                size: file.size,
                mime: mime || file.type,
            };

            if (replaceItemId) {
                const index = this.items.findIndex((item) => item.id === replaceItemId);
                if (index >= 0) {
                    this.revokeItemPreview(this.items[index]);
                    this.items.splice(index, 1, newItem);
                } else {
                    this.items.push(newItem);
                }
            } else {
                this.items.push(newItem);
            }

            this.renderGrid();
            this.syncFormState();
        }

        renderGrid() {
            if (!this.grid || !this.template) {
                return;
            }

            this.grid.innerHTML = '';
            this.items.forEach((item, index) => {
                const fragment = this.template.content.cloneNode(true);
                const container = fragment.querySelector('[data-image-item]');
                const image = fragment.querySelector('[data-image-preview]');
                const kind = fragment.querySelector('[data-image-kind]');
                const size = fragment.querySelector('[data-image-size]');
                const badge = fragment.querySelector('[data-primary-badge]');
                const remove = fragment.querySelector('[data-remove-image]');
                const edit = fragment.querySelector('[data-edit-image]');

                if (container) {
                    container.dataset.itemId = item.id;
                    container.addEventListener('dragstart', () => {
                        this.dragItemId = item.id;
                    });
                    container.addEventListener('dragover', (event) => event.preventDefault());
                    container.addEventListener('drop', (event) => {
                        event.preventDefault();
                        this.reorderItems(this.dragItemId, item.id);
                    });
                }
                if (image) {
                    image.src = item.previewUrl;
                }
                if (kind) {
                    kind.textContent = item.kindLabel;
                }
                if (size) {
                    size.textContent = item.size ? humanSize(item.size) : '';
                }
                if (badge) {
                    badge.textContent = index === 0 ? (this.config.strings.primary || 'Primary') : '';
                    badge.classList.toggle('hidden', index !== 0);
                }
                if (remove) {
                    remove.addEventListener('click', () => this.removeItem(item.id));
                }
                if (edit) {
                    edit.addEventListener('click', () => this.editItem(item));
                }

                this.grid.appendChild(fragment);
            });

            const totalAllowed = Number(this.config.maxTotal || 0);
            if (this.remaining) {
                if (totalAllowed > 0) {
                    this.remaining.textContent = String(Math.max(totalAllowed - this.items.length, 0));
                } else {
                    this.remaining.textContent = String(Math.max(Number(this.config.remainingSlots || 0) - this.items.filter((item) => item.type === 'new').length, 0));
                }
            }

            if (this.warning) {
                const overLimit = totalAllowed > 0 && this.items.length > totalAllowed;
                this.warning.textContent = overLimit ? (this.config.strings.too_many_images || '') : '';
                this.warning.classList.toggle('hidden', !overLimit);
            }
        }

        editItem(item) {
            if (item.type === 'existing') {
                fetch(item.previewUrl)
                    .then((response) => response.blob())
                    .then((blob) => {
                        const extension = item.previewUrl.split('.').pop() || 'jpg';
                        const file = new File([blob], 'existing-' + item.id + '.' + extension, { type: blob.type || 'image/jpeg' });
                        this.queueFiles([file], item.id);
                    })
                    .catch(() => {
                        if (window.SP && typeof window.SP.toast === 'function') {
                            window.SP.toast(this.config.strings.load_failed || 'The image could not be opened for editing.', 'error');
                        }
                    });
                return;
            }

            this.queueFiles([item.file], item.id);
        }

        removeItem(itemId) {
            const removed = this.items.find((item) => item.id === itemId);
            this.revokeItemPreview(removed);
            this.items = this.items.filter((item) => item.id !== itemId);
            this.renderGrid();
            this.syncFormState();
        }

        reorderItems(sourceId, targetId) {
            if (!sourceId || !targetId || sourceId === targetId) {
                return;
            }

            const sourceIndex = this.items.findIndex((item) => item.id === sourceId);
            const targetIndex = this.items.findIndex((item) => item.id === targetId);
            if (sourceIndex < 0 || targetIndex < 0) {
                return;
            }

            const moved = this.items.splice(sourceIndex, 1)[0];
            this.items.splice(targetIndex, 0, moved);
            this.renderGrid();
            this.syncFormState();
        }

        syncFormState() {
            if (this.existingInputs) {
                this.existingInputs.innerHTML = '';
            }
            if (this.orderInputs) {
                this.orderInputs.innerHTML = '';
            }

            const uploadItems = this.items.filter((item) => item.type === 'new');
            const existingItems = this.items.filter((item) => item.type === 'existing');

            existingItems.forEach((item) => {
                this.appendHiddenInput(this.existingInputs, 'existing_pictures[]', item.path);
            });

            this.items.forEach((item) => {
                this.appendHiddenInput(this.orderInputs, 'picture_order[]', item.type === 'existing' ? 'existing:' + item.path : 'upload:' + item.token);
            });

            uploadItems.forEach((item) => {
                this.appendHiddenInput(this.orderInputs, 'new_picture_tokens[]', item.token);
            });

            if (!this.input || !hasDataTransfer()) {
                if (this.warning) {
                    this.warning.textContent = this.config.strings.fallback || '';
                    this.warning.classList.toggle('hidden', false);
                }
                return;
            }

            const dataTransfer = new DataTransfer();
            uploadItems.forEach((item) => {
                if (item.file) {
                    dataTransfer.items.add(item.file);
                }
            });
            this.input.files = dataTransfer.files;
        }

        appendHiddenInput(container, name, value) {
            if (!container) {
                return;
            }
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            container.appendChild(input);
        }
    }

    window.SalesPointImageEditor = {
        mountAll(scope) {
            const root = scope || document;
            if (!root || typeof root.querySelectorAll !== 'function') {
                return;
            }
            root.querySelectorAll('[data-image-uploader]').forEach((element) => {
                if (!element.__productImageUploader) {
                    element.__productImageUploader = new ProductImageUploader(element);
                }
            });
        },
    };

    window.SalesPointImageEditorMath = {
        buildViewportMetrics: buildViewportMetrics,
        applyMatrix: applyMatrix,
        invertMatrix: invertMatrix,
        cropCorners: cropCorners,
        cropBounds: cropBounds,
    };

    document.addEventListener('DOMContentLoaded', function () {
        window.SalesPointImageEditor.mountAll(document);
    });
}());
