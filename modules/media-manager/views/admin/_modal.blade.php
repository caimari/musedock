<div class="modal fade" id="mediaManagerModal" tabindex="-1" aria-labelledby="mediaManagerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="mediaManagerModalLabel">Biblioteca de Medios</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div id="modal-media-library-grid" class="row g-3">
          <div id="modal-media-loading" class="text-center w-100 p-5">
            <div class="spinner-border" role="status"><span class="visually-hidden">Cargando...</span></div>
            <p>Cargando medios...</p>
          </div>
        </div>

        <nav aria-label="Media Modal Navigation" class="mt-3 d-flex justify-content-center">
          <ul class="pagination pagination-sm" id="modal-media-pagination"></ul>
        </nav>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="selectMediaButton" disabled>Seleccionar Imagen</button>
      </div>
    </div>
  </div>
</div>
