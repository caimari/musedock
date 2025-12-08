<?php
namespace Screenart\Musedock\Controllers\Superadmin;

use Screenart\Musedock\View;
use Screenart\Musedock\Models\Slider;
use Screenart\Musedock\Models\Slide;
use Screenart\Musedock\Traits\RequiresPermission;
use Screenart\Musedock\Database;
use Screenart\Musedock\Security\SessionSecurity;

class SliderController
{
    use RequiresPermission;

    // --- Gestión de Sliders ---

    public function index() {
        SessionSecurity::startSession();
        $this->checkPermission('media.manage');

        $sliders = Slider::query()->orderBy('name')->get(); // Obtener todos los sliders
        return View::renderSuperadmin('sliders.index', [
            'title' => 'Sliders',
            'sliders' => $sliders
        ]);
    }

	public function create() {
        SessionSecurity::startSession();
        $this->checkPermission('media.manage');

		return View::renderSuperadmin('sliders.create', [
			'title' => 'Crear Nuevo Slider',
			'slider' => new Slider(),
			'form_mode' => 'create' // <= MODO
		]);
	}


    public function store() {
        SessionSecurity::startSession();
        $this->checkPermission('media.manage');

        // Validación básica (¡Mejorar!)
        if (empty($_POST['name'])) {
            flash('error', 'El nombre del slider es obligatorio.');
            // Guardar old input si tienes el sistema
            $_SESSION['_old_input'] = $_POST;
            header('Location: ' . route('sliders.create')); // Redirigir atrás
            exit;
        }

        $data = [
            'name' => $_POST['name'],
            // 'settings' => json_encode($_POST['settings'] ?? []), // Si tienes campo settings
        ];

        $slider = Slider::create($data);

        if ($slider) {
            flash('success', 'Slider creado correctamente.');
            header('Location: ' . route('sliders.edit', ['id' => $slider->id])); // Ir a editarlo
        } else {
            flash('error', 'Error al crear el slider.');
            $_SESSION['_old_input'] = $_POST;
            header('Location: ' . route('sliders.create'));
        }
        exit;
    }

public function edit($id)
{
        SessionSecurity::startSession();
        $this->checkPermission('media.manage');

    $slider = Slider::find($id);
    if (!$slider) {
        flash('error', 'Slider no encontrado.');
        header('Location: ' . route('sliders.index'));
        exit;
    }

    // Leer slides
    $slides = $slider->slides();

    // Leer settings como array (si no está ya casteado por el modelo)
    $settings = is_array($slider->settings) ? $slider->settings : json_decode($slider->settings ?? '{}', true);

    // IMPORTANTE: Añadir el engine a los settings para que la vista lo reciba
    // El engine se guarda en una columna separada, hay que pasarlo a la vista
    $settings['engine'] = $slider->engine ?? 'swiper';

    // Esto asegura que en la vista tendrás settings como array
    return View::renderSuperadmin('sliders.edit', [
        'title' => 'Editar Slider: ' . e($slider->name),
        'slider' => $slider,
        'slides' => $slides,
        'settings' => $settings, // PASAMOS LOS SETTINGS
        'form_mode' => 'edit'
    ]);
}




public function update($id)
{
        SessionSecurity::startSession();
        $this->checkPermission('media.manage');

    $slider = Slider::find($id);

    if (!$slider) {
        flash('error', 'Slider no encontrado.');
        header('Location: ' . route('sliders.index'));
        exit;
    }

    if (empty($_POST['name'])) {
        flash('error', 'El nombre del slider es obligatorio.');
        header('Location: ' . route('sliders.edit', ['id' => $slider->id]));
        exit;
    }

    // Obtener settings
    $settings = $_POST['settings'] ?? [];
    
    // Depuración - ver qué llega en POST
    error_log("POST settings completo: " . json_encode($_POST['settings']));
    error_log("Caption BG en POST: " . ($settings['caption_bg'] ?? 'no enviado'));

    // Detectar engine
    $engine = isset($settings['engine']) ? trim($settings['engine']) : 'swiper';
    unset($settings['engine']);

    // Limpieza de settings con manejo especial para RGBA
    $cleanSettings = [];

    foreach ($settings as $key => $value) {
        // Preservar valores importantes sin importar su contenido
        if ($key === 'caption_bg') {
            $cleanSettings[$key] = $value;
            error_log("Preservando caption_bg: {$value}");
            continue;
        }
        
        // Procesar según el tipo de valor
        if (is_array($value)) {
            $value = array_filter($value, fn($v) => !is_null($v) && $v !== '' && $v !== [] && $v !== false);
            if (!empty($value)) {
                $cleanSettings[$key] = $value;
            }
        } elseif (is_string($value)) {
            $value = trim($value);
            if ($value !== '' || $value === '0' || stripos($value, 'rgba') !== false || stripos($value, '#') !== false) {
                $cleanSettings[$key] = $value;
            }
        } elseif (!is_null($value) || $value === 0 || $value === '0') {
            $cleanSettings[$key] = $value;
        }
    }
    
    // Asegurar que caption_bg siempre esté presente
    if (!isset($cleanSettings['caption_bg'])) {
        $cleanSettings['caption_bg'] = 'rgba(0,0,0,0.6)';
        error_log("Valor por defecto para caption_bg aplicado");
    }
    
    // Verificar qué hay en cleanSettings antes de codificar
    error_log("Settings antes de codificar: " . json_encode($cleanSettings));
    
    // Convertir a JSON con opciones adecuadas
    $settingsJson = json_encode($cleanSettings, 
        JSON_UNESCAPED_UNICODE | 
        JSON_UNESCAPED_SLASHES | 
        JSON_PRESERVE_ZERO_FRACTION
    );
    
    // Verificar lo que se guardará realmente
    error_log("Settings JSON final: " . $settingsJson);
    
    $data = [
        'name' => trim($_POST['name']),
        'engine' => $engine,
        'settings' => $settingsJson // Guardar como JSON string
    ];

    if ($slider->update($data)) {
        flash('success', 'Slider actualizado correctamente.');
    } else {
        flash('error', 'Error al actualizar el slider.');
    }

    header('Location: ' . route('sliders.edit', ['id' => $slider->id]));
    exit;
}

    public function destroy($id) {
        SessionSecurity::startSession();
        $this->checkPermission('media.manage');

        $slider = Slider::find($id);
        if ($slider) {
            // La BD debería borrar slides en cascada, pero podemos asegurar
            // Slide::query()->where('slider_id', $id)->delete(); // Opcional
            if ($slider->delete()) {
                flash('success', 'Slider eliminado.');
            } else {
                flash('error', 'Error al eliminar slider.');
            }
        } else {
            flash('error', 'Slider no encontrado.');
        }
        header('Location: ' . route('sliders.index'));
        exit;
    }

 // ==================================================
    // --- NUEVOS MÉTODOS PARA GESTIÓN DE SLIDES ---
    // ==================================================

    /**
     * Muestra el formulario para crear una nueva slide para un slider específico.
     */
    public function createSlide($sliderId)
    {
        SessionSecurity::startSession();
        $this->checkPermission('media.manage');

        $slider = Slider::find($sliderId);
        if (!$slider) {
            flash('error', 'Slider padre no encontrado.');
            return header('Location: ' . route('sliders.index')); // Volver al listado de sliders
        }

        return View::renderSuperadmin('slides.create', [
            'title' => 'Añadir Diapositiva a: ' . e($slider->name),
            'slide' => new Slide(['slider_id' => $sliderId, 'is_active' => true]), // Pre-rellenar ID y activa
            'sliderId' => $sliderId, // Pasar ID del slider padre
            'sliderName' => $slider->name // Pasar nombre para la vista
        ]);
    }

    /**
     * Guarda una nueva slide en la base de datos.
     */
    public function storeSlide($sliderId)
{
        SessionSecurity::startSession();
        $this->checkPermission('media.manage');

    if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

    // 1. Validar que el slider padre existe
    $slider = Slider::find($sliderId);
    if (!$slider) {
        flash('error', 'Slider padre no encontrado.');
        return header('Location: ' . route('sliders.index'));
    }

    // 2. Validación de datos
    $data = $_POST;
    unset($data['_token'], $data['_csrf']);
    $errors = [];

    if (empty($data['image_url'])) { $errors['image_url'][] = 'La URL de la imagen es obligatoria.'; }

    if (!empty($errors)) {
        flash('error', 'Por favor corrige los errores.');
        $_SESSION['_old_input'] = $data;
        return header('Location: ' . route('slides.create', ['sliderId' => $sliderId]));
    }
    unset($_SESSION['_old_input']);

    // 3. Calcular sort_order automáticamente (el último + 1)
    $maxOrder = Slide::where('slider_id', $sliderId)->max('sort_order');
    $nextOrder = is_null($maxOrder) ? 0 : $maxOrder + 1;

    // 4. Preparar datos para insertar
    $insertData = [
        'slider_id' => (int)$sliderId,
        'image_url' => trim($data['image_url']),
        'title' => isset($data['title']) ? trim($data['title']) : null,
        'description' => isset($data['description']) ? trim($data['description']) : null,
        'link_url' => isset($data['link_url']) && trim($data['link_url']) !== '' ? trim($data['link_url']) : null,
        'sort_order' => $nextOrder,
        'is_active' => isset($data['is_active']) && $data['is_active'] == '1' ? 1 : 0,
    ];

    // 5. Insertar en base de datos
    $slide = Slide::create($insertData);

    if ($slide) {
        flash('success', 'Diapositiva añadida correctamente.');
    } else {
        flash('error', 'Error al guardar la diapositiva.');
    }

    return header('Location: ' . route('sliders.edit', ['id' => $sliderId]));
}


     /**
      * Muestra el formulario para editar una slide existente.
      */
    public function editSlide($slideId)
    {
        SessionSecurity::startSession();
        $this->checkPermission('media.manage');

        $slide = Slide::find($slideId);
        if (!$slide) {
            flash('error', 'Diapositiva no encontrada.');
            // Intentar encontrar el slider padre para redirigir, o ir al índice
            $sliderId = $_SESSION['_old_input']['slider_id'] ?? null; // Intenta recuperar de old
            return header('Location: ' . ($sliderId ? route('sliders.edit', ['id' => $sliderId]) : route('sliders.index')));
        }

        // Cargar el slider padre para mostrar info en la vista (opcional pero útil)
        $slider = $slide->slider; // Asume que la relación funciona

        return View::renderSuperadmin('slides.edit', [
            'title' => 'Editar Diapositiva' . ($slider ? ' para: ' . e($slider->name) : ''),
            'slide' => $slide,
             // No necesitamos pasar sliderId o sliderName explícitamente si $slide lo tiene
        ]);
    }

    /**
     * Actualiza una slide existente en la base de datos.
     */
    public function updateSlide($slideId)
    {
        SessionSecurity::startSession();
        $this->checkPermission('media.manage');

        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        // 1. Encontrar la slide
        $slide = Slide::find($slideId);
        if (!$slide) {
            flash('error', 'Diapositiva no encontrada.');
            return header('Location: ' . route('sliders.index')); // O redirigir mejor si sabes el sliderId
        }
        $sliderId = $slide->slider_id; // Guardar ID del padre para redirigir

        // 2. Validación (similar a storeSlide)
        $data = $_POST;
        unset($data['_token'], $data['_csrf'], $data['_method']); // Quitar tokens y method
        $errors = [];
        if (empty($data['image_url'])) { $errors['image_url'][] = 'La URL de la imagen es obligatoria.'; }
        // ... más validaciones ...

        if (!empty($errors)) {
            flash('error', 'Por favor corrige los errores.');
            $_SESSION['_old_input'] = $data;
            return header('Location: ' . route('slides.edit', ['slideId' => $slideId])); // Volver al form de edición
        }
         unset($_SESSION['_old_input']);

        // 3. Preparar datos para actualizar
        $updateData = [
            // No actualizamos slider_id
            'image_url' => trim($data['image_url']),
            'title' => isset($data['title']) ? trim($data['title']) : null,
            'description' => isset($data['description']) ? trim($data['description']) : null,
            'link_url' => isset($data['link_url']) && trim($data['link_url']) !== '' ? trim($data['link_url']) : null,
            'sort_order' => isset($data['sort_order']) ? (int)$data['sort_order'] : 0,
            'is_active' => isset($data['is_active']) && $data['is_active'] == '1' ? 1 : 0,
        ];

        // 4. Actualizar
        if ($slide->update($updateData)) {
            flash('success', 'Diapositiva actualizada correctamente.');
        } else {
            flash('error', 'Error al actualizar la diapositiva.');
        }

        // Redirigir de vuelta a la edición del slider padre
        return header('Location: ' . route('sliders.edit', ['id' => $sliderId]));
    }

    /**
     * Elimina una slide específica.
     */
    public function destroySlide($slideId)
    {
        SessionSecurity::startSession();
        $this->checkPermission('media.manage');

        $slide = Slide::find($slideId);
        if ($slide) {
            $sliderId = $slide->slider_id; // Guardar ID para redirigir
            if ($slide->delete()) {
                flash('success', 'Diapositiva eliminada.');
            } else {
                flash('error', 'Error al eliminar la diapositiva.');
            }
            // Redirigir a la edición del slider padre
            return header('Location: ' . route('sliders.edit', ['id' => $sliderId]));
        } else {
            flash('error', 'Diapositiva no encontrada.');
             return header('Location: ' . route('sliders.index')); // O a donde sea apropiado
        }
    }

public function updateOrder($sliderId)
{
        SessionSecurity::startSession();
        $this->checkPermission('media.manage');

    header('Content-Type: application/json');

    // Leer datos de entrada
    $input = file_get_contents('php://input');
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Sin datos recibidos.']);
        exit;
    }

    $orderData = json_decode($input, true);

    if (!isset($orderData['order']) || !is_array($orderData['order'])) {
        echo json_encode(['success' => false, 'message' => 'Datos de orden inválidos.']);
        exit;
    }

    try {
        $pdo = Database::connect();
        $pdo->beginTransaction();

        foreach ($orderData['order'] as $item) {
            $stmt = $pdo->prepare("UPDATE slider_slides SET sort_order = ? WHERE id = ? AND slider_id = ?");
            $stmt->execute([
                (int)$item['order'],
                (int)$item['id'],
                (int)$sliderId
            ]);
        }

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Orden actualizado.']);
        exit;

    } catch (\Exception $e) {
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log("Error actualizando orden slides: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error interno.']);
        exit;
    }
}



}
