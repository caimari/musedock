<div class="modal fade" id="editMediaMetaModal" tabindex="-1" aria-labelledby="editMediaMetaModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Editar Detalles de la Imagen</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <img id="editMetaImagePreview" src="" class="img-fluid mb-3" alt="">
        <div class="mb-3">
            <label class="form-label">URL</label>
            <input type="text" id="editMetaUrl" class="form-control form-control-sm" readonly>
        </div>
        <div class="mb-3">
            <label class="form-label">Texto Alternativo (Alt)</label>
            <input type="text" id="editMetaAlt" class="form-control form-control-sm">
        </div>
        <div class="mb-3">
            <label class="form-label">Leyenda</label>
            <textarea id="editMetaCaption" class="form-control form-control-sm" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button type="button" class="btn btn-primary" id="saveMediaMetaButton">Guardar Cambios</button>
      </div>
    </div>
  </div>
</div>
