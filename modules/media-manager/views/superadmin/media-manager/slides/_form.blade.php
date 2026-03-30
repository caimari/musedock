<div class="input-group mb-3">
    <input type="text" class="form-control" id="image_url" name="image_url" placeholder="URL de la imagen">
    <button type="button" class="btn btn-outline-secondary open-media-modal-button"
            data-bs-toggle="modal"
            data-bs-target="#mediaManagerModal"
            data-input-target="#image_url"
            data-preview-target="#image_preview">
        Seleccionar Imagen
    </button>
</div>

<img id="image_preview" src="https://via.placeholder.com/300x150?text=Sin+imagen" class="img-fluid rounded border mt-2" style="max-height: 100px;">
