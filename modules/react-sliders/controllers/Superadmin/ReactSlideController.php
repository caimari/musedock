<?php
namespace ReactSliders\Controllers\Superadmin;

use Screenart\Musedock\View;
use ReactSliders\Models\ReactSlider;
use ReactSliders\Models\ReactSlide;
use Screenart\Musedock\Traits\RequiresPermission;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Services\MediaUploadService;

class ReactSlideController
{
    use RequiresPermission;

    public function create($sliderId)
    {
        SessionSecurity::startSession();
        $this->checkPermission('react_sliders.edit');

        $slider = ReactSlider::find($sliderId);
        if (!$slider) {
            flash('error', __rs('messages.error_slider_not_found'));
            header('Location: ' . route('react-sliders.index'));
            exit;
        }

        return View::renderModule('react-sliders', 'superadmin/slides/create', [
            'title' => __rs('slide.create'),
            'slider' => $slider,
            'slide' => new ReactSlide()
        ]);
    }

    public function store()
    {
        SessionSecurity::startSession();
        $this->checkPermission('react_sliders.edit');

        $sliderId = (int)$_POST['slider_id'];
        $slider = ReactSlider::find($sliderId);

        if (!$slider) {
            flash('error', __rs('messages.error_slider_not_found'));
            header('Location: ' . route('react-sliders.index'));
            exit;
        }

        // Validación
        $errors = [];
        if (empty($_POST['title'])) {
            $errors[] = __rs('validation.title_required');
        }

        if (!empty($errors)) {
            flash('error', implode('<br>', $errors));
            $_SESSION['_old_input'] = $_POST;
            header('Location: ' . route('react-slides.create', ['slider_id' => $sliderId]));
            exit;
        }

        // Subir imagen si existe
        $imageUrl = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = MediaUploadService::uploadImage($_FILES['image'], 'sliders');
            if ($uploadResult['success']) {
                $imageUrl = $uploadResult['url'];
            } else {
                flash('error', $uploadResult['error']);
                $_SESSION['_old_input'] = $_POST;
                header('Location: ' . route('react-slides.create', ['slider_id' => $sliderId]));
                exit;
            }
        }

        // Obtener el siguiente orden
        $sortOrder = ReactSlide::getNextOrder($sliderId);

        // Preparar custom_data
        $customData = [];
        if (!empty($_POST['custom_data_json'])) {
            $customData = json_decode($_POST['custom_data_json'], true) ?? [];
        }

        $data = [
            'slider_id' => $sliderId,
            'title' => $_POST['title'],
            'subtitle' => $_POST['subtitle'] ?? null,
            'description' => $_POST['description'] ?? null,
            'image_url' => $imageUrl,
            'button_text' => $_POST['button_text'] ?? null,
            'button_link' => $_POST['button_link'] ?? null,
            'button_target' => $_POST['button_target'] ?? '_self',
            'background_color' => $_POST['background_color'] ?? null,
            'text_color' => $_POST['text_color'] ?? null,
            'overlay_opacity' => !empty($_POST['overlay_opacity']) ? (float)$_POST['overlay_opacity'] : 0.3,
            'sort_order' => $sortOrder,
            'is_active' => isset($_POST['is_active']),
            'custom_css' => $_POST['custom_css'] ?? null,
            'custom_data' => $customData
        ];

        $slide = ReactSlide::create($data);

        if ($slide) {
            flash('success', __rs('messages.slide_created'));
            header('Location: ' . route('react-sliders.edit', ['id' => $sliderId]));
        } else {
            flash('error', __rs('messages.error_creating_slide'));
            $_SESSION['_old_input'] = $_POST;
            header('Location: ' . route('react-slides.create', ['slider_id' => $sliderId]));
        }
        exit;
    }

    public function edit($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('react_sliders.edit');

        $slide = ReactSlide::find($id);
        if (!$slide) {
            flash('error', __rs('messages.error_slide_not_found'));
            header('Location: ' . route('react-sliders.index'));
            exit;
        }

        $slider = $slide->slider();
        if (!$slider) {
            flash('error', __rs('messages.error_slider_not_found'));
            header('Location: ' . route('react-sliders.index'));
            exit;
        }

        return View::renderModule('react-sliders', 'superadmin/slides/edit', [
            'title' => __rs('slide.edit') . ': ' . e($slide->title),
            'slider' => $slider,
            'slide' => $slide
        ]);
    }

    public function update($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('react_sliders.edit');

        $slide = ReactSlide::find($id);
        if (!$slide) {
            flash('error', __rs('messages.error_slide_not_found'));
            header('Location: ' . route('react-sliders.index'));
            exit;
        }

        $slider = $slide->slider();
        if (!$slider) {
            flash('error', __rs('messages.error_slider_not_found'));
            header('Location: ' . route('react-sliders.index'));
            exit;
        }

        // Subir nueva imagen si existe
        $imageUrl = $slide->image_url;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = MediaUploadService::uploadImage($_FILES['image'], 'sliders');
            if ($uploadResult['success']) {
                $imageUrl = $uploadResult['url'];
            } else {
                flash('error', $uploadResult['error']);
                header('Location: ' . route('react-slides.edit', ['id' => $id]));
                exit;
            }
        }

        // Preparar custom_data
        $customData = $slide->custom_data ?? [];
        if (!empty($_POST['custom_data_json'])) {
            $customData = json_decode($_POST['custom_data_json'], true) ?? [];
        }

        $data = [
            'title' => $_POST['title'],
            'subtitle' => $_POST['subtitle'] ?? null,
            'description' => $_POST['description'] ?? null,
            'image_url' => $imageUrl,
            'button_text' => $_POST['button_text'] ?? null,
            'button_link' => $_POST['button_link'] ?? null,
            'button_target' => $_POST['button_target'] ?? '_self',
            'background_color' => $_POST['background_color'] ?? null,
            'text_color' => $_POST['text_color'] ?? null,
            'overlay_opacity' => !empty($_POST['overlay_opacity']) ? (float)$_POST['overlay_opacity'] : 0.3,
            'is_active' => isset($_POST['is_active']),
            'custom_css' => $_POST['custom_css'] ?? null,
            'custom_data' => $customData
        ];

        $updated = $slide->update($data);

        if ($updated) {
            flash('success', __rs('messages.slide_updated'));
        } else {
            flash('error', __rs('messages.error_updating_slide'));
        }

        header('Location: ' . route('react-slides.edit', ['id' => $id]));
        exit;
    }

    public function destroy($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('react_sliders.edit');

        $slide = ReactSlide::find($id);
        if (!$slide) {
            flash('error', __rs('messages.error_slide_not_found'));
            header('Location: ' . route('react-sliders.index'));
            exit;
        }

        $sliderId = $slide->slider_id;
        $deleted = $slide->delete();

        if ($deleted) {
            flash('success', __rs('messages.slide_deleted'));
        } else {
            flash('error', __rs('messages.error_deleting_slide'));
        }

        header('Location: ' . route('react-sliders.edit', ['id' => $sliderId]));
        exit;
    }

    public function reorder()
    {
        SessionSecurity::startSession();
        $this->checkPermission('react_sliders.edit');

        // Obtener datos JSON del cuerpo de la petición
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!isset($data['slide_ids']) || !is_array($data['slide_ids'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid data']);
            exit;
        }

        $success = ReactSlide::reorder($data['slide_ids']);

        header('Content-Type: application/json');
        echo json_encode(['success' => $success]);
        exit;
    }
}
