<?php
namespace Screenart\Musedock\Controllers\Tenant;

use Screenart\Musedock\View;
use Screenart\Musedock\Models\Slider;
use Screenart\Musedock\Models\Slide;
use Screenart\Musedock\Database;
use Screenart\Musedock\Security\SessionSecurity;

class SliderController
{
    private function tenantId(): int
    {
        return (int) ($GLOBALS['tenant']['id'] ?? 0);
    }

    // --- Gestión de Sliders ---

    public function index()
    {
        SessionSecurity::startSession();

        $sliders = Slider::query()
            ->where('tenant_id', $this->tenantId())
            ->orderBy('name')
            ->get();

        return View::renderTenantAdmin('sliders.index', [
            'title' => 'Sliders',
            'sliders' => $sliders
        ]);
    }

    public function create()
    {
        SessionSecurity::startSession();

        return View::renderTenantAdmin('sliders.create', [
            'title' => 'Crear Nuevo Slider',
            'slider' => new Slider(),
            'form_mode' => 'create'
        ]);
    }

    public function store()
    {
        SessionSecurity::startSession();


        if (empty($_POST['name'])) {
            flash('error', 'El nombre del slider es obligatorio.');
            $_SESSION['_old_input'] = $_POST;
            header('Location: /' . admin_path() . '/sliders/create');
            exit;
        }

        $data = [
            'name' => trim($_POST['name']),
            'tenant_id' => $this->tenantId(),
        ];

        $slider = Slider::create($data);

        if ($slider) {
            flash('success', 'Slider creado correctamente.');
            header('Location: /' . admin_path() . '/sliders/' . $slider->id . '/edit');
        } else {
            flash('error', 'Error al crear el slider.');
            $_SESSION['_old_input'] = $_POST;
            header('Location: /' . admin_path() . '/sliders/create');
        }
        exit;
    }

    public function edit($id)
    {
        SessionSecurity::startSession();

        $slider = Slider::query()
            ->where('id', (int)$id)
            ->where('tenant_id', $this->tenantId())
            ->first();

        if (!$slider) {
            flash('error', 'Slider no encontrado.');
            header('Location: /' . admin_path() . '/sliders');
            exit;
        }

        $slides = $slider->slides();
        $settings = is_array($slider->settings) ? $slider->settings : json_decode($slider->settings ?? '{}', true);
        $settings['engine'] = $slider->engine ?? 'swiper';

        return View::renderTenantAdmin('sliders.edit', [
            'title' => 'Editar Slider: ' . e($slider->name),
            'slider' => $slider,
            'slides' => $slides,
            'settings' => $settings,
            'form_mode' => 'edit'
        ]);
    }

    public function update($id)
    {
        SessionSecurity::startSession();


        $slider = Slider::query()
            ->where('id', (int)$id)
            ->where('tenant_id', $this->tenantId())
            ->first();

        if (!$slider) {
            flash('error', 'Slider no encontrado.');
            header('Location: /' . admin_path() . '/sliders');
            exit;
        }

        if (empty($_POST['name'])) {
            flash('error', 'El nombre del slider es obligatorio.');
            header('Location: /' . admin_path() . '/sliders/' . $slider->id . '/edit');
            exit;
        }

        $settings = $_POST['settings'] ?? [];
        $engine = isset($settings['engine']) ? trim($settings['engine']) : 'swiper';
        unset($settings['engine']);

        // Limpieza de settings
        $cleanSettings = [];
        foreach ($settings as $key => $value) {
            if ($key === 'caption_bg') {
                $cleanSettings[$key] = $value;
                continue;
            }
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

        if (!isset($cleanSettings['caption_bg'])) {
            $cleanSettings['caption_bg'] = 'rgba(0,0,0,0.6)';
        }

        $settingsJson = json_encode($cleanSettings,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_PRESERVE_ZERO_FRACTION
        );

        $data = [
            'name' => trim($_POST['name']),
            'engine' => $engine,
            'settings' => $settingsJson
        ];

        if ($slider->update($data)) {
            flash('success', 'Slider actualizado correctamente.');
        } else {
            flash('error', 'Error al actualizar el slider.');
        }

        header('Location: /' . admin_path() . '/sliders/' . $slider->id . '/edit');
        exit;
    }

    public function destroy($id)
    {
        SessionSecurity::startSession();


        $slider = Slider::query()
            ->where('id', (int)$id)
            ->where('tenant_id', $this->tenantId())
            ->first();

        if ($slider) {
            if ($slider->delete()) {
                flash('success', 'Slider eliminado.');
            } else {
                flash('error', 'Error al eliminar slider.');
            }
        } else {
            flash('error', 'Slider no encontrado.');
        }
        header('Location: /' . admin_path() . '/sliders');
        exit;
    }

    // ==================================================
    // --- GESTIÓN DE SLIDES ---
    // ==================================================

    public function createSlide($sliderId)
    {
        SessionSecurity::startSession();

        $slider = Slider::query()
            ->where('id', (int)$sliderId)
            ->where('tenant_id', $this->tenantId())
            ->first();

        if (!$slider) {
            flash('error', 'Slider padre no encontrado.');
            header('Location: /' . admin_path() . '/sliders');
            exit;
        }

        return View::renderTenantAdmin('slides.create', [
            'title' => 'Añadir Diapositiva a: ' . e($slider->name),
            'slide' => new Slide(['slider_id' => $sliderId, 'is_active' => true]),
            'sliderId' => $sliderId,
            'sliderName' => $slider->name
        ]);
    }

    public function storeSlide($sliderId)
    {
        SessionSecurity::startSession();


        $slider = Slider::query()
            ->where('id', (int)$sliderId)
            ->where('tenant_id', $this->tenantId())
            ->first();

        if (!$slider) {
            flash('error', 'Slider padre no encontrado.');
            header('Location: /' . admin_path() . '/sliders');
            exit;
        }

        $data = $_POST;
        unset($data['_token'], $data['_csrf']);

        if (empty($data['image_url'])) {
            flash('error', 'La URL de la imagen es obligatoria.');
            $_SESSION['_old_input'] = $data;
            header('Location: /' . admin_path() . '/sliders/' . $sliderId . '/slides/create');
            exit;
        }
        unset($_SESSION['_old_input']);

        $maxOrder = Slide::where('slider_id', $sliderId)->max('sort_order');
        $nextOrder = is_null($maxOrder) ? 0 : $maxOrder + 1;

        $insertData = $this->buildSlideData($data, (int)$sliderId, $nextOrder);
        $insertData['tenant_id'] = $this->tenantId();

        $slide = Slide::create($insertData);

        if ($slide) {
            flash('success', 'Diapositiva añadida correctamente.');
        } else {
            flash('error', 'Error al guardar la diapositiva.');
        }

        header('Location: /' . admin_path() . '/sliders/' . $sliderId . '/edit');
        exit;
    }

    public function editSlide($slideId)
    {
        SessionSecurity::startSession();

        $slide = Slide::query()
            ->where('id', (int)$slideId)
            ->where('tenant_id', $this->tenantId())
            ->first();

        if (!$slide) {
            flash('error', 'Diapositiva no encontrada.');
            header('Location: /' . admin_path() . '/sliders');
            exit;
        }

        $slider = Slider::find($slide->slider_id);

        return View::renderTenantAdmin('slides.edit', [
            'title' => 'Editar Diapositiva' . ($slider ? ' para: ' . e($slider->name) : ''),
            'slide' => $slide,
        ]);
    }

    public function updateSlide($slideId)
    {
        SessionSecurity::startSession();


        $slide = Slide::query()
            ->where('id', (int)$slideId)
            ->where('tenant_id', $this->tenantId())
            ->first();

        if (!$slide) {
            flash('error', 'Diapositiva no encontrada.');
            header('Location: /' . admin_path() . '/sliders');
            exit;
        }

        $sliderId = $slide->slider_id;
        $data = $_POST;
        unset($data['_token'], $data['_csrf'], $data['_method']);

        if (empty($data['image_url'])) {
            flash('error', 'La URL de la imagen es obligatoria.');
            $_SESSION['_old_input'] = $data;
            header('Location: /' . admin_path() . '/sliders/slides/' . $slideId . '/edit');
            exit;
        }
        unset($_SESSION['_old_input']);

        $updateData = $this->buildSlideData($data, null, isset($data['sort_order']) ? (int)$data['sort_order'] : 0);

        if ($slide->update($updateData)) {
            flash('success', 'Diapositiva actualizada correctamente.');
        } else {
            flash('error', 'Error al actualizar la diapositiva.');
        }

        header('Location: /' . admin_path() . '/sliders/' . $sliderId . '/edit');
        exit;
    }

    public function destroySlide($slideId)
    {
        SessionSecurity::startSession();


        $slide = Slide::query()
            ->where('id', (int)$slideId)
            ->where('tenant_id', $this->tenantId())
            ->first();

        if ($slide) {
            $sliderId = $slide->slider_id;
            if ($slide->delete()) {
                flash('success', 'Diapositiva eliminada.');
            } else {
                flash('error', 'Error al eliminar la diapositiva.');
            }
            header('Location: /' . admin_path() . '/sliders/' . $sliderId . '/edit');
            exit;
        }

        flash('error', 'Diapositiva no encontrada.');
        header('Location: /' . admin_path() . '/sliders');
        exit;
    }

    public function updateOrder($sliderId)
    {
        SessionSecurity::startSession();

        header('Content-Type: application/json');

        // Verify slider belongs to tenant
        $slider = Slider::query()
            ->where('id', (int)$sliderId)
            ->where('tenant_id', $this->tenantId())
            ->first();

        if (!$slider) {
            echo json_encode(['success' => false, 'message' => 'Slider no encontrado.']);
            exit;
        }

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
                $stmt = $pdo->prepare("UPDATE slider_slides SET sort_order = ? WHERE id = ? AND slider_id = ? AND tenant_id = ?");
                $stmt->execute([
                    (int)$item['order'],
                    (int)$item['id'],
                    (int)$sliderId,
                    $this->tenantId()
                ]);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Orden actualizado.']);
            exit;

        } catch (\Exception $e) {
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error actualizando orden slides (tenant): " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error interno.']);
            exit;
        }
    }

    /**
     * Build slide data array from POST data
     */
    private function buildSlideData(array $data, ?int $sliderId, int $sortOrder): array
    {
        $result = [
            'image_url' => trim($data['image_url']),
            'image_position' => isset($data['image_position']) && preg_match('/^\d+%\s+\d+%$/', trim($data['image_position'])) ? trim($data['image_position']) : 'center center',
            'title' => isset($data['title']) ? trim($data['title']) : null,
            'description' => isset($data['description']) ? trim($data['description']) : null,
            'link_url' => isset($data['link_url']) && trim($data['link_url']) !== '' ? trim($data['link_url']) : null,
            'link_target' => isset($data['link_target']) && in_array($data['link_target'], ['_self', '_blank'], true) ? $data['link_target'] : '_self',
            'link_text' => isset($data['link_text']) && trim($data['link_text']) !== '' ? trim($data['link_text']) : null,
            'link2_url' => isset($data['link2_url']) && trim($data['link2_url']) !== '' ? trim($data['link2_url']) : null,
            'link2_target' => isset($data['link2_target']) && in_array($data['link2_target'], ['_self', '_blank'], true) ? $data['link2_target'] : '_self',
            'link2_text' => isset($data['link2_text']) && trim($data['link2_text']) !== '' ? trim($data['link2_text']) : null,
            'title_bold' => isset($data['title_bold']) && $data['title_bold'] == '1' ? 1 : 0,
            'title_font' => isset($data['title_font']) && trim($data['title_font']) !== '' ? trim($data['title_font']) : null,
            'description_font' => isset($data['description_font']) && trim($data['description_font']) !== '' ? trim($data['description_font']) : null,
            'title_color' => isset($data['title_color']) && trim($data['title_color']) !== '' ? trim($data['title_color']) : null,
            'description_color' => isset($data['description_color']) && trim($data['description_color']) !== '' ? trim($data['description_color']) : null,
            'button_custom' => isset($data['button_custom']) && $data['button_custom'] == '1' ? 1 : 0,
            'button_bg_color' => isset($data['button_bg_color']) && trim($data['button_bg_color']) !== '' ? trim($data['button_bg_color']) : null,
            'button_text_color' => isset($data['button_text_color']) && trim($data['button_text_color']) !== '' ? trim($data['button_text_color']) : null,
            'button_border_color' => isset($data['button_border_color']) && trim($data['button_border_color']) !== '' ? trim($data['button_border_color']) : null,
            'button2_custom' => isset($data['button2_custom']) && $data['button2_custom'] == '1' ? 1 : 0,
            'button2_bg_color' => isset($data['button2_bg_color']) && trim($data['button2_bg_color']) !== '' ? trim($data['button2_bg_color']) : null,
            'button2_text_color' => isset($data['button2_text_color']) && trim($data['button2_text_color']) !== '' ? trim($data['button2_text_color']) : null,
            'button2_border_color' => isset($data['button2_border_color']) && trim($data['button2_border_color']) !== '' ? trim($data['button2_border_color']) : null,
            'button_shape' => isset($data['button_shape']) && in_array($data['button_shape'], ['rounded', 'square'], true) ? $data['button_shape'] : 'rounded',
            'sort_order' => $sortOrder,
            'is_active' => isset($data['is_active']) && $data['is_active'] == '1' ? 1 : 0,
        ];

        if ($sliderId !== null) {
            $result['slider_id'] = $sliderId;
        }

        return $result;
    }
}
