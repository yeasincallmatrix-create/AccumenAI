(function () {
    'use strict';

    var OUT_W = 350;
    var OUT_H = 450;
    var MAX_BYTES = 100 * 1024;
    var TARGET_BYTES = 50 * 1024;
    var MIN_ZOOM = 1;
    var MAX_ZOOM = 4;

    function hasCropUi() {
        return !!document.getElementById('photoCropModal');
    }

    /**
     * Generic entry point bound to file inputs: opens the crop modal for the
     * selected image and, on Apply, replaces the input's files with the
     * cropped JPEG and (optionally) submits the enclosing form.
     */
    window.openPhotoCropper = function (input) {
        var file = input && input.files && input.files[0];
        if (!file) { return; }

        if (window.MonetixPhotoCrop && hasCropUi()) {
            MonetixPhotoCrop.open(input, function (cropped) {
                var dt = new DataTransfer();
                dt.items.add(cropped);
                input.files = dt.files;

                var preview = document.querySelector('[data-photo-preview]');
                if (preview && preview.tagName === 'IMG') {
                    if (preview.src && preview.src.indexOf('blob:') === 0) { try { URL.revokeObjectURL(preview.src); } catch (e) {} }
                    preview.src = URL.createObjectURL(cropped);
                    preview.classList.remove('d-none');
                    preview.style.display = '';
                    preview.style.visibility = 'visible';
                }

                if (input.hasAttribute('data-crop-auto-submit') && input.form) {
                    if (input.form.requestSubmit) { input.form.requestSubmit(); } else { input.form.submit(); }
                }
            });
        } else if (window.resizePhoto) {
            resizePhoto(input);
        }
    };

    var modal = document.getElementById('photoCropModal');
    if (!modal) { return; }

    var stage = modal.querySelector('[data-crop-stage]');
    var img = modal.querySelector('[data-crop-img]');
    var zoomInput = modal.querySelector('[data-crop-zoom]');
    var zoomInBtn = modal.querySelector('[data-crop-zoom-in]');
    var zoomOutBtn = modal.querySelector('[data-crop-zoom-out]');
    var applyBtn = modal.querySelector('[data-crop-apply]');
    var resetBtn = modal.querySelector('[data-crop-reset]');

    var bsModal = null;
    if (window.bootstrap && bootstrap.Modal) {
        bsModal = bootstrap.Modal.getOrCreateInstance(modal);
    }
    if (!bsModal) { return; }

    var state = null;      // { natW, natH, zoom, baseScale, displayW, displayH, x, y, src }
    var currentInput = null; // { input, onApply }
    var applied = false;
    var pendingLoad = false;
    var drag = { active: false, sx: 0, sy: 0, ox: 0, oy: 0 };

    function computeDim() {
        var scale = state.baseScale * state.zoom;
        state.displayW = state.natW * scale;
        state.displayH = state.natH * scale;
    }

    function clamp() {
        var stageW = stage.clientWidth;
        var stageH = stage.clientHeight;
        state.x = Math.max(stageW - state.displayW, Math.min(0, state.x));
        state.y = Math.max(stageH - state.displayH, Math.min(0, state.y));
    }

    function render(anchor) {
        var stageW = stage.clientWidth;
        var stageH = stage.clientHeight;

        if (anchor && state.displayW) {
            var oldScale = state.displayW / state.natW;
            var ax = (stageW / 2 - state.x) / oldScale;
            var ay = (stageH / 2 - state.y) / oldScale;
            computeDim();
            var newScale = state.displayW / state.natW;
            state.x = stageW / 2 - ax * newScale;
            state.y = stageH / 2 - ay * newScale;
        } else {
            computeDim();
        }

        clamp();

        img.style.width = state.displayW + 'px';
        img.style.height = state.displayH + 'px';
        img.style.transform = 'translate(' + state.x + 'px,' + state.y + 'px)';
    }

    function centerCover() {
        if (!state) { return; }
        state.baseScale = Math.max(stage.clientWidth / state.natW, stage.clientHeight / state.natH);
        state.zoom = MIN_ZOOM;
        zoomInput.value = MIN_ZOOM;
        computeDim();
        state.x = (stage.clientWidth - state.displayW) / 2;
        state.y = (stage.clientHeight - state.displayH) / 2;
        render(false);
        applyBtn.disabled = false;
    }

    function setZoom(z) {
        if (!state) { return; }
        state.zoom = Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, z));
        zoomInput.value = state.zoom;
        render(true);
    }

    stage.addEventListener('pointerdown', function (e) {
        if (!state) { return; }
        drag.active = true;
        drag.sx = e.clientX;
        drag.sy = e.clientY;
        drag.ox = state.x;
        drag.oy = state.y;
        stage.classList.add('dragging');
        if (stage.setPointerCapture) { stage.setPointerCapture(e.pointerId); }
    });

    stage.addEventListener('pointermove', function (e) {
        if (!drag.active || !state) { return; }
        state.x = drag.ox + (e.clientX - drag.sx);
        state.y = drag.oy + (e.clientY - drag.sy);
        render(false);
    });

    function endDrag() {
        drag.active = false;
        stage.classList.remove('dragging');
    }
    stage.addEventListener('pointerup', endDrag);
    stage.addEventListener('pointercancel', endDrag);

    zoomInput.addEventListener('input', function () {
        setZoom(parseFloat(zoomInput.value));
    });
    zoomInBtn.addEventListener('click', function () { setZoom(state ? state.zoom + 0.25 : MIN_ZOOM); });
    zoomOutBtn.addEventListener('click', function () { setZoom(state ? state.zoom - 0.25 : MIN_ZOOM); });
    resetBtn.addEventListener('click', centerCover);

    function writeBlob(canvas, quality) {
        canvas.toBlob(function (blob) {
            if (blob && blob.size <= MAX_BYTES) {
                var file = new File([blob], 'photo.jpg', { type: 'image/jpeg' });
                applied = true;
                applyBtn.disabled = false;
                bsModal.hide();
                if (currentInput && currentInput.onApply) { currentInput.onApply(file); }
            } else if (quality > 0.3) {
                writeBlob(canvas, quality - 0.1);
            } else {
                applyBtn.disabled = false;
                if (window.Monetix && Monetix.toast) {
                    Monetix.toast('The photo could not be compressed below 100 KB.', 'danger');
                }
            }
        }, 'image/jpeg', quality);
    }

    applyBtn.addEventListener('click', function () {
        if (!state) { return; }
        applyBtn.disabled = true;

        var scale = state.displayW / state.natW;
        var srcX = -state.x / scale;
        var srcY = -state.y / scale;
        var srcW = stage.clientWidth / scale;
        var srcH = stage.clientHeight / scale;

        var canvas = document.createElement('canvas');
        canvas.width = OUT_W;
        canvas.height = OUT_H;
        var ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, OUT_W, OUT_H);
        ctx.drawImage(img, srcX, srcY, srcW, srcH, 0, 0, OUT_W, OUT_H);

        writeBlob(canvas, 0.85);
    });

    modal.addEventListener('shown.bs.modal', function () {
        if (state && !pendingLoad) { centerCover(); }
    });

    modal.addEventListener('hidden.bs.modal', function () {
        if (applied) {
            applied = false;
            currentInput = null;
            return;
        }
        if (currentInput && currentInput.input && currentInput.input.value) {
            currentInput.input.value = '';
        }
        currentInput = null;
    });

    window.MonetixPhotoCrop = {
        open: function (input, onApply) {
            var file = input && input.files && input.files[0];
            if (!file) { return; }

            // Pre-validate type/size to give clear feedback before blob load
            if (file.type && file.type.indexOf('image/') !== 0) {
                if (window.Monetix && Monetix.toast) { Monetix.toast('Please select a JPG, PNG or WebP image (got ' + (file.type || 'unknown') + ').', 'danger'); }
                input.value = '';
                return;
            }
            if (file.size > 10 * 1024 * 1024) {
                if (window.Monetix && Monetix.toast) { Monetix.toast('Image is too large (>10 MB). Please choose a smaller photo.', 'danger'); }
                input.value = '';
                return;
            }

            currentInput = { input: input, onApply: onApply };
            applied = false;
            pendingLoad = true;
            applyBtn.disabled = true;

            var oldSrc = state && state.src ? state.src : null;
            var url = URL.createObjectURL(file);
            var settled = false;

            img.onload = function () {
                if (settled) return;
                settled = true;
                pendingLoad = false;
                if (oldSrc) { try { URL.revokeObjectURL(oldSrc); } catch (e) {} }
                // Guard against 0 naturalWidth (broken decode)
                if (!img.naturalWidth || !img.naturalHeight) {
                    URL.revokeObjectURL(url);
                    currentInput = null;
                    if (window.Monetix && Monetix.toast) { Monetix.toast('Could not decode the image. Try a JPG/PNG export.', 'danger'); }
                    bsModal.hide();
                    return;
                }
                state = { natW: img.naturalWidth, natH: img.naturalHeight, src: url };
                if (bsModal.isShown()) { centerCover(); } else if (modal) { /* ensure centered even if shown event missed */ setTimeout(centerCover, 80); }
            };
            img.onerror = function () {
                if (settled) return;
                settled = true;
                pendingLoad = false;
                // Try FileReader dataURL fallback (handles some blob: edge cases)
                var reader = new FileReader();
                reader.onload = function (ev) {
                    var dataUrl = ev.target.result;
                    // Second attempt with dataURL
                    var retry = new Image();
                    retry.onload = function () {
                        try { URL.revokeObjectURL(url); } catch (e) {}
                        img.onload = null; img.onerror = null;
                        img.src = dataUrl;
                        // Reuse same success path: set state after dataUrl loads into main img
                        // Use retry dimensions
                        state = { natW: retry.naturalWidth, natH: retry.naturalHeight, src: dataUrl };
                        pendingLoad = false;
                        if (bsModal.isShown()) { centerCover(); }
                    };
                    retry.onerror = function () {
                        try { URL.revokeObjectURL(url); } catch (e) {}
                        currentInput = null;
                        if (window.Monetix && Monetix.toast) { Monetix.toast('Could not read the selected image. Please export as JPG/PNG and retry (type: ' + file.type + ').', 'danger'); }
                        bsModal.hide();
                    };
                    retry.src = dataUrl;
                };
                reader.onerror = function () {
                    try { URL.revokeObjectURL(url); } catch (e) {}
                    currentInput = null;
                    if (window.Monetix && Monetix.toast) { Monetix.toast('Could not read the selected image.', 'danger'); }
                    bsModal.hide();
                };
                try { reader.readAsDataURL(file); } catch (e) {
                    try { URL.revokeObjectURL(url); } catch (ex) {}
                    currentInput = null;
                    if (window.Monetix && Monetix.toast) { Monetix.toast('Could not read the selected image.', 'danger'); }
                    bsModal.hide();
                }
            };

            // Reset img handlers before assigning src to avoid stale handlers
            img.removeAttribute('src');
            // Small delay ensures revoked old URL not interfering with new load in some browsers
            setTimeout(function () { img.src = url; }, 0);
            bsModal.show();
        }
    };
})();