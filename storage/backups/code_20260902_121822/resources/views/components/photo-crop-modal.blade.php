<!-- Photo crop modal (used by js/photo-crop.js) -->
<div class="modal fade" id="photoCropModal" tabindex="-1" aria-hidden="true" data-crop-modal>
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-crop me-1"></i>Crop Profile Photo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="crop-stage" data-crop-stage>
                    <div class="crop-frame" data-crop-frame></div>
                    <img class="crop-img" data-crop-img alt="Crop preview">
                </div>
                <div class="crop-controls mt-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-crop-zoom-out title="Zoom out" aria-label="Zoom out"><i class="bi bi-zoom-out"></i></button>
                    <input type="range" class="form-range flex-grow-1" min="1" max="4" step="0.1" value="1" data-crop-zoom aria-label="Zoom">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-crop-zoom-in title="Zoom in" aria-label="Zoom in"><i class="bi bi-zoom-in"></i></button>
                </div>
                <div class="text-muted small text-center mt-2">Drag to position, zoom to fit the face. Output is a 7:9 portrait (350 &times; 450 px).</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-crop-reset><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" data-crop-apply disabled><i class="bi bi-check-lg me-1"></i>Crop &amp; Upload</button>
            </div>
        </div>
    </div>
</div>