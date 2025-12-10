/**
 * Custom Forms - Form Builder JavaScript
 * Gestión de campos del formulario con SweetAlert2
 */

let currentFieldType = null;
let editingFieldId = null;

/**
 * Muestra el modal de SweetAlert2 para seleccionar tipo de campo
 */
async function showAddFieldModal() {
    const fieldTypes = {
        'text': { icon: 'bi-fonts', label: 'Texto' },
        'email': { icon: 'bi-envelope', label: 'Email' },
        'number': { icon: 'bi-123', label: 'Número' },
        'phone': { icon: 'bi-telephone', label: 'Teléfono' },
        'textarea': { icon: 'bi-text-paragraph', label: 'Área de texto' },
        'select': { icon: 'bi-menu-button-wide', label: 'Desplegable' },
        'radio': { icon: 'bi-ui-radios', label: 'Opciones (radio)' },
        'checkbox': { icon: 'bi-check-square', label: 'Casilla única' },
        'date': { icon: 'bi-calendar', label: 'Fecha' },
        'time': { icon: 'bi-clock', label: 'Hora' },
        'url': { icon: 'bi-link-45deg', label: 'URL' },
        'file': { icon: 'bi-paperclip', label: 'Archivo' },
        'hidden': { icon: 'bi-eye-slash', label: 'Campo oculto' }
    };

    // Crear HTML para selector de tipos
    let html = '<div class="row g-2">';
    for (const [key, type] of Object.entries(fieldTypes)) {
        html += `
            <div class="col-4">
                <button type="button" class="btn btn-outline-primary w-100 p-3 field-type-selector" data-type="${key}">
                    <i class="bi ${type.icon} d-block mb-2" style="font-size: 1.5rem;"></i>
                    <small>${type.label}</small>
                </button>
            </div>
        `;
    }
    html += '</div>';

    const result = await Swal.fire({
        title: '<i class="bi bi-plus-circle me-2"></i>Selecciona el tipo de campo',
        html: html,
        width: '600px',
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: 'Cancelar',
        didOpen: () => {
            // Agregar eventos a los botones
            document.querySelectorAll('.field-type-selector').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const type = btn.dataset.type;
                    Swal.close();
                    await showFieldConfigForm(type);
                });
            });
        }
    });
}

/**
 * Muestra el formulario de configuración del campo
 */
async function showFieldConfigForm(fieldType) {
    const needsOptions = ['select', 'radio', 'checkbox'];
    const showOptions = needsOptions.includes(fieldType);

    const html = `
        <div class="text-start">
            <div class="mb-3">
                <label class="form-label fw-bold">Etiqueta <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="swal-field-label" required>
                <small class="text-muted">Texto visible que verá el usuario en el formulario (ej: "Tu nombre", "Email", "Mensaje")</small>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Nombre del campo <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="swal-field-name" pattern="[a-z0-9_]+" required>
                <small class="text-muted">Identificador técnico interno (ej: "nombre_usuario", "email_contacto"). Solo letras minúsculas, números y guiones bajos</small>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Placeholder</label>
                <input type="text" class="form-control" id="swal-field-placeholder">
            </div>
            ${showOptions ? `
            <div class="mb-3">
                <label class="form-label fw-bold">Opciones <span class="text-danger">*</span></label>
                <textarea class="form-control" id="swal-field-options" rows="3" placeholder="valor1|Etiqueta 1&#10;valor2|Etiqueta 2" required></textarea>
                <small class="text-muted">Una opción por línea. Formato: valor|etiqueta</small>
            </div>
            ` : ''}
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="swal-field-required">
                <label class="form-check-label" for="swal-field-required">Campo requerido</label>
            </div>
        </div>
    `;

    const result = await Swal.fire({
        title: '<i class="bi bi-pencil me-2"></i>Configurar campo',
        html: html,
        width: '500px',
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-check-lg me-1"></i> Agregar Campo',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const label = document.getElementById('swal-field-label').value.trim();
            const name = document.getElementById('swal-field-name').value.trim();
            const placeholder = document.getElementById('swal-field-placeholder').value.trim();
            const options = showOptions ? document.getElementById('swal-field-options').value.trim() : '';
            const isRequired = document.getElementById('swal-field-required').checked;

            if (!label) {
                Swal.showValidationMessage('La etiqueta es requerida');
                return false;
            }

            if (!name || !/^[a-z0-9_]+$/.test(name)) {
                Swal.showValidationMessage('El nombre del campo solo puede contener letras minúsculas, números y guiones bajos');
                return false;
            }

            if (showOptions && !options) {
                Swal.showValidationMessage('Las opciones son requeridas para este tipo de campo');
                return false;
            }

            return {
                field_type: fieldType,
                field_label: label,
                field_name: name,
                placeholder: placeholder,
                options: options,
                is_required: isRequired
            };
        },
        didOpen: () => {
            // Auto-generar nombre del campo desde la etiqueta
            const labelInput = document.getElementById('swal-field-label');
            const nameInput = document.getElementById('swal-field-name');
            let manualName = false;

            labelInput.addEventListener('input', () => {
                if (!manualName) {
                    nameInput.value = labelInput.value.toLowerCase()
                        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                        .replace(/[^a-z0-9\s]/g, '')
                        .replace(/\s+/g, '_')
                        .substring(0, 50);
                }
            });

            nameInput.addEventListener('input', () => {
                manualName = nameInput.value.length > 0;
            });
        }
    });

    if (result.isConfirmed && result.value) {
        await addFieldToForm(result.value);
    }
}

/**
 * Agrega un nuevo campo al formulario (envío al servidor)
 */
async function addFieldToForm(fieldData) {
    try {
        Swal.fire({
            title: 'Agregando campo...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const response = await fetch(`/musedock/custom-forms/${formId}/fields`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(fieldData)
        });

        const result = await response.json();

        if (result.success) {
            await Swal.fire({
                icon: 'success',
                title: 'Campo agregado',
                text: 'El campo se agregó correctamente',
                timer: 1500,
                showConfirmButton: false
            });
            window.location.reload();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: result.error || 'Error al agregar el campo'
            });
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error al agregar el campo'
        });
    }
}

/**
 * Edita un campo existente
 */
async function editField(fieldId) {
    try {
        // Obtener datos del campo
        const response = await fetch(`/musedock/custom-forms/fields/${fieldId}`);
        const result = await response.json();

        if (!result.success) {
            showToast('Error al cargar el campo', 'danger');
            return;
        }

        const field = result.field;
        editingFieldId = fieldId;

        // Llenar el formulario de edición
        document.getElementById('editFieldId').value = field.id;
        document.getElementById('editFieldTypeDisplay').value = field.field_type;
        document.getElementById('editFieldName').value = field.field_name;
        document.getElementById('editFieldLabel').value = field.field_label;
        document.getElementById('editFieldPlaceholder').value = field.placeholder || '';
        document.getElementById('editFieldOptions').value = field.options || '';
        document.getElementById('editFieldDefault').value = field.default_value || '';
        document.getElementById('editFieldCss').value = field.field_class || '';
        document.getElementById('editMinLength').value = field.min_length || '';
        document.getElementById('editMaxLength').value = field.max_length || '';
        document.getElementById('editPattern').value = field.validation_rules?.pattern || '';
        document.getElementById('editFieldRequired').checked = field.is_required;
        document.getElementById('editFieldActive').checked = field.is_active;

        // Mostrar/ocultar opciones según el tipo
        const needsOptions = ['select', 'radio', 'checkbox'];
        const editOptionsContainer = document.getElementById('editOptionsContainer');
        if (needsOptions.includes(field.field_type)) {
            editOptionsContainer.style.display = 'block';
        } else {
            editOptionsContainer.style.display = 'none';
        }

        // Abrir modal
        const modal = new bootstrap.Modal(document.getElementById('editFieldModal'));
        modal.show();
    } catch (error) {
        console.error('Error:', error);
        showToast('Error al cargar el campo', 'danger');
    }
}

/**
 * Guarda los cambios del campo editado
 */
async function updateField() {
    const form = document.getElementById('editFieldForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);
    const data = Object.fromEntries(formData);

    try {
        const response = await fetch(`/musedock/custom-forms/fields/${editingFieldId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.success) {
            // Cerrar modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('editFieldModal'));
            modal.hide();

            // Mostrar mensaje de éxito
            showToast('Campo actualizado correctamente', 'success');

            // Recargar la página
            setTimeout(() => window.location.reload(), 500);
        } else {
            showToast(result.error || 'Error al actualizar el campo', 'danger');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Error al actualizar el campo', 'danger');
    }
}

/**
 * Elimina un campo con confirmación SweetAlert2
 */
async function deleteField(fieldId) {
    const result = await Swal.fire({
        title: '¿Eliminar campo?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="bi bi-trash me-1"></i> Eliminar',
        cancelButtonText: 'Cancelar'
    });

    if (!result.isConfirmed) {
        return;
    }

    try {
        const response = await fetch(`/musedock/custom-forms/fields/${fieldId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const data = await response.json();

        if (data.success) {
            showToast('Campo eliminado correctamente', 'success');
            setTimeout(() => window.location.reload(), 500);
        } else {
            showToast(data.error || 'Error al eliminar el campo', 'danger');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Error al eliminar el campo', 'danger');
    }
}

/**
 * Inicializa Sortable para reordenar campos
 */
document.addEventListener('DOMContentLoaded', function() {
    const fieldList = document.getElementById('fieldList');
    if (fieldList && fieldList.children.length > 0) {
        new Sortable(fieldList, {
            handle: '.field-handle',
            animation: 150,
            onEnd: async function(evt) {
                const order = Array.from(fieldList.children)
                    .filter(el => el.dataset.fieldId)
                    .map(el => parseInt(el.dataset.fieldId));

                try {
                    const response = await fetch(`/musedock/custom-forms/${formId}/fields/reorder`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ order })
                    });

                    const result = await response.json();

                    if (result.success) {
                        showToast('Orden actualizado', 'success');
                    } else {
                        showToast('Error al reordenar', 'danger');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showToast('Error al reordenar', 'danger');
                }
            }
        });
    }

    // Modal de agregar campo ahora usa SweetAlert2
});
