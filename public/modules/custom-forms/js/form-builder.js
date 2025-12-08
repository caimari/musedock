/**
 * Custom Forms - Form Builder Script
 *
 * Maneja la interfaz drag & drop del constructor de formularios
 */

(function() {
    'use strict';

    // Configuration
    const config = {
        formId: null,
        isTenant: false,
        baseUrl: '/admin/custom-forms',
        csrfToken: ''
    };

    // Field types that need options
    const optionFieldTypes = ['select', 'radio', 'checkbox'];

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        // Get form ID from data attribute
        const fieldList = document.getElementById('fieldList');
        if (fieldList) {
            config.formId = fieldList.dataset.formId;
        }

        // Detect tenant view
        config.isTenant = typeof isTenanView !== 'undefined' && isTenanView;
        if (config.isTenant) {
            config.baseUrl = '/panel/custom-forms';
        }

        // Get CSRF token
        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        if (tokenMeta) {
            config.csrfToken = tokenMeta.content;
        } else {
            const tokenInput = document.querySelector('input[name="_token"]');
            if (tokenInput) {
                config.csrfToken = tokenInput.value;
            }
        }

        // Initialize Sortable
        initSortable();

        // Initialize preview
        updatePreview();
    });

    /**
     * Initialize SortableJS for drag & drop
     */
    function initSortable() {
        const fieldList = document.getElementById('fieldList');
        if (!fieldList || !window.Sortable) return;

        new Sortable(fieldList, {
            animation: 150,
            handle: '.field-handle',
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            onEnd: function(evt) {
                // Get new order
                const items = fieldList.querySelectorAll('.field-item');
                const order = Array.from(items).map(item => item.dataset.fieldId);

                // Save new order
                saveFieldOrder(order);
            }
        });
    }

    /**
     * Save field order via AJAX
     */
    async function saveFieldOrder(order) {
        try {
            const response = await fetch(`${config.baseUrl}/${config.formId}/fields/reorder`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ order: order })
            });

            const result = await response.json();

            if (!result.success) {
                showToast('Error al guardar el orden', 'danger');
            }
        } catch (error) {
            console.error('Error saving order:', error);
            showToast('Error de conexión', 'danger');
        }
    }

    /**
     * Select field type in add modal
     */
    window.selectFieldType = function(type) {
        // Update selection UI
        document.querySelectorAll('.field-type-btn').forEach(btn => {
            btn.classList.toggle('selected', btn.dataset.type === type);
        });

        // Set hidden input
        document.getElementById('newFieldType').value = type;

        // Show config form
        document.getElementById('fieldConfigForm').style.display = 'block';

        // Show/hide options container
        const optionsContainer = document.getElementById('optionsContainer');
        if (optionsContainer) {
            optionsContainer.style.display = optionFieldTypes.includes(type) ? 'block' : 'none';
        }

        // Enable add button
        document.getElementById('addFieldBtn').disabled = false;

        // Clear form
        document.getElementById('addFieldForm').reset();
        document.getElementById('newFieldType').value = type;
    };

    /**
     * Add new field
     */
    window.addField = async function() {
        const form = document.getElementById('addFieldForm');
        const formData = new FormData(form);

        // Validation
        if (!formData.get('field_label') || !formData.get('field_name')) {
            showToast('Por favor complete los campos requeridos', 'warning');
            return;
        }

        // Check if field type requires options
        const fieldType = formData.get('field_type');
        if (optionFieldTypes.includes(fieldType) && !formData.get('options')) {
            showToast('Por favor agregue las opciones', 'warning');
            return;
        }

        try {
            const response = await fetch(`${config.baseUrl}/${config.formId}/fields`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(Object.fromEntries(formData))
            });

            const result = await response.json();

            if (result.success) {
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('addFieldModal'));
                if (modal) modal.hide();

                // Reload page to show new field
                location.reload();
            } else {
                showToast(result.message || 'Error al agregar el campo', 'danger');
            }
        } catch (error) {
            console.error('Error adding field:', error);
            showToast('Error de conexión', 'danger');
        }
    };

    /**
     * Edit field - Load field data into modal
     */
    window.editField = async function(fieldId) {
        try {
            const response = await fetch(`${config.baseUrl}/fields/${fieldId}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();

            if (result.success && result.field) {
                const field = result.field;

                // Populate form
                document.getElementById('editFieldId').value = field.id;
                document.getElementById('editFieldTypeDisplay').value = field.field_type;
                document.getElementById('editFieldName').value = field.field_name;
                document.getElementById('editFieldLabel').value = field.field_label;
                document.getElementById('editFieldPlaceholder').value = field.placeholder || '';
                document.getElementById('editFieldDefault').value = field.default_value || '';
                document.getElementById('editFieldCss').value = field.css_class || '';
                document.getElementById('editFieldRequired').checked = field.is_required == 1;
                document.getElementById('editFieldActive').checked = field.is_active == 1;

                // Validation rules
                const rules = field.validation_rules || {};
                if (document.getElementById('editMinLength')) {
                    document.getElementById('editMinLength').value = rules.min_length || '';
                }
                if (document.getElementById('editMaxLength')) {
                    document.getElementById('editMaxLength').value = rules.max_length || '';
                }
                if (document.getElementById('editPattern')) {
                    document.getElementById('editPattern').value = rules.pattern || '';
                }

                // Options
                const optionsContainer = document.getElementById('editOptionsContainer');
                const optionsTextarea = document.getElementById('editFieldOptions');
                if (optionFieldTypes.includes(field.field_type)) {
                    optionsContainer.style.display = 'block';
                    if (field.options) {
                        // Parse options to text format
                        let optionsText = '';
                        try {
                            const opts = typeof field.options === 'string' ? JSON.parse(field.options) : field.options;
                            optionsText = opts.map(o => `${o.value}|${o.label}`).join('\n');
                        } catch (e) {
                            optionsText = field.options;
                        }
                        optionsTextarea.value = optionsText;
                    }
                } else {
                    optionsContainer.style.display = 'none';
                }

                // Show modal
                new bootstrap.Modal(document.getElementById('editFieldModal')).show();
            } else {
                showToast('Error al cargar el campo', 'danger');
            }
        } catch (error) {
            console.error('Error loading field:', error);
            showToast('Error de conexión', 'danger');
        }
    };

    /**
     * Update field
     */
    window.updateField = async function() {
        const fieldId = document.getElementById('editFieldId').value;
        const form = document.getElementById('editFieldForm');
        const formData = new FormData(form);

        // Build validation rules
        const validationRules = {};
        const minLength = document.getElementById('editMinLength')?.value;
        const maxLength = document.getElementById('editMaxLength')?.value;
        const pattern = document.getElementById('editPattern')?.value;

        if (minLength) validationRules.min_length = parseInt(minLength);
        if (maxLength) validationRules.max_length = parseInt(maxLength);
        if (pattern) validationRules.pattern = pattern;

        const data = {
            field_label: formData.get('field_label'),
            placeholder: formData.get('placeholder'),
            default_value: formData.get('default_value'),
            css_class: formData.get('css_class'),
            is_required: formData.get('is_required') ? 1 : 0,
            is_active: formData.get('is_active') ? 1 : 0,
            options: formData.get('options'),
            validation_rules: validationRules
        };

        try {
            const response = await fetch(`${config.baseUrl}/fields/${fieldId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('editFieldModal'));
                if (modal) modal.hide();

                // Reload to show changes
                location.reload();
            } else {
                showToast(result.message || 'Error al actualizar el campo', 'danger');
            }
        } catch (error) {
            console.error('Error updating field:', error);
            showToast('Error de conexión', 'danger');
        }
    };

    /**
     * Delete field
     */
    window.deleteField = async function(fieldId) {
        if (!confirm('¿Estás seguro de que deseas eliminar este campo?')) {
            return;
        }

        try {
            const response = await fetch(`${config.baseUrl}/fields/${fieldId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': config.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();

            if (result.success) {
                // Remove from DOM
                const fieldItem = document.querySelector(`.field-item[data-field-id="${fieldId}"]`);
                if (fieldItem) {
                    fieldItem.remove();
                }

                // Update count
                updateFieldCount();

                // Show empty message if no fields
                const fieldList = document.getElementById('fieldList');
                if (fieldList && fieldList.querySelectorAll('.field-item').length === 0) {
                    fieldList.innerHTML = `
                        <div class="empty-fields" id="emptyFieldsMsg">
                            <i class="bi bi-inbox display-4 text-muted"></i>
                            <p class="mt-3 mb-0">No hay campos en este formulario</p>
                            <small class="text-muted">Haz clic en "Agregar Campo" para comenzar</small>
                        </div>
                    `;
                }

                showToast('Campo eliminado correctamente', 'success');
            } else {
                showToast(result.message || 'Error al eliminar el campo', 'danger');
            }
        } catch (error) {
            console.error('Error deleting field:', error);
            showToast('Error de conexión', 'danger');
        }
    };

    /**
     * Update field count badge
     */
    function updateFieldCount() {
        const badge = document.getElementById('fieldCount');
        if (badge) {
            const count = document.querySelectorAll('.field-item').length;
            badge.textContent = count + ' campos';
        }
    }

    /**
     * Update form preview
     */
    function updatePreview() {
        const preview = document.getElementById('formPreview');
        if (!preview) return;

        const fields = document.querySelectorAll('.field-item');

        if (fields.length === 0) {
            preview.innerHTML = '<p class="text-muted text-center">Agrega campos para ver la vista previa</p>';
            return;
        }

        let html = '';
        fields.forEach(field => {
            const label = field.querySelector('.field-label')?.textContent || '';
            const type = field.querySelector('.field-type-badge')?.textContent || '';
            const required = field.querySelector('.badge.bg-danger') ? '<span class="required">*</span>' : '';

            html += `<div class="form-group mb-3">
                <label class="form-label">${label} ${required}</label>
                <input type="text" class="form-control" placeholder="${type}" disabled>
            </div>`;
        });

        preview.innerHTML = html;
    }

    /**
     * Show toast notification
     */
    function showToast(message, type = 'info') {
        // Use Bootstrap toast if available
        if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type} border-0 position-fixed bottom-0 end-0 m-3`;
            toast.setAttribute('role', 'alert');
            toast.style.zIndex = '9999';
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            document.body.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            toast.addEventListener('hidden.bs.toast', () => toast.remove());
        } else {
            // Fallback to alert
            alert(message);
        }
    }

    // Expose showToast globally
    window.showToast = showToast;

})();
