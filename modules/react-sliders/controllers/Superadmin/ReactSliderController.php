<?php
namespace ReactSliders\Controllers\Superadmin;

use Screenart\Musedock\View;
use ReactSliders\Models\ReactSlider;
use ReactSliders\Models\ReactSlide;
use Screenart\Musedock\Traits\RequiresPermission;
use Screenart\Musedock\Security\SessionSecurity;
use Screenart\Musedock\Services\MediaUploadService;

class ReactSliderController
{
    use RequiresPermission;

    public function index()
    {
        SessionSecurity::startSession();
        $this->checkPermission('react_sliders.view');

        $sliders = ReactSlider::getByTenant(null); // Sliders globales

        return View::renderModule('react-sliders', 'superadmin/sliders/index', [
            'title' => __rs('slider.title'),
            'sliders' => $sliders
        ]);
    }

    public function create()
    {
        SessionSecurity::startSession();
        $this->checkPermission('react_sliders.create');

        return View::renderModule('react-sliders', 'superadmin/sliders/create', [
            'title' => __rs('slider.create'),
            'slider' => new ReactSlider()
        ]);
    }

    public function store()
    {
        SessionSecurity::startSession();
        $this->checkPermission('react_sliders.create');

        // Validación
        $errors = [];
        if (empty($_POST['name'])) {
            $errors[] = __rs('validation.name_required');
        }
        if (empty($_POST['identifier'])) {
            $errors[] = __rs('validation.identifier_required');
        } elseif (!preg_match('/^[a-z0-9-]+$/', $_POST['identifier'])) {
            $errors[] = __rs('validation.identifier_format');
        }

        if (!empty($errors)) {
            flash('error', implode('<br>', $errors));
            $_SESSION['_old_input'] = $_POST;
            header('Location: ' . route('react-sliders.create'));
            exit;
        }

        $settings = [
            'autoplay' => isset($_POST['autoplay']),
            'autoplay_delay' => (int)($_POST['autoplay_delay'] ?? 5000),
            'loop' => isset($_POST['loop']),
            'navigation' => isset($_POST['navigation']),
            'pagination' => isset($_POST['pagination']),
            'animation' => $_POST['animation'] ?? 'slide',
            'slides_per_view' => (int)($_POST['slides_per_view'] ?? 1),
            'space_between' => (int)($_POST['space_between'] ?? 0),
            'speed' => (int)($_POST['speed'] ?? 500)
        ];

        $data = [
            'name' => $_POST['name'],
            'identifier' => $_POST['identifier'],
            'engine' => $_POST['engine'] ?? 'swiper',
            'settings' => $settings,
            'is_active' => isset($_POST['is_active']),
            'tenant_id' => null // Global
        ];

        $slider = ReactSlider::create($data);

        if ($slider) {
            flash('success', __rs('messages.slider_created'));
            header('Location: ' . route('react-sliders.edit', ['id' => $slider->id]));
        } else {
            flash('error', __rs('messages.error_creating_slider'));
            $_SESSION['_old_input'] = $_POST;
            header('Location: ' . route('react-sliders.create'));
        }
        exit;
    }

    public function edit($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('react_sliders.edit');

        $slider = ReactSlider::find($id);
        if (!$slider) {
            flash('error', __rs('messages.error_slider_not_found'));
            header('Location: ' . route('react-sliders.index'));
            exit;
        }

        $slides = $slider->slides();

        return View::renderModule('react-sliders', 'superadmin/sliders/edit', [
            'title' => __rs('slider.edit') . ': ' . e($slider->name),
            'slider' => $slider,
            'slides' => $slides,
            'settings' => $slider->getFullSettings()
        ]);
    }

    public function update($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('react_sliders.edit');

        $slider = ReactSlider::find($id);
        if (!$slider) {
            flash('error', __rs('messages.error_slider_not_found'));
            header('Location: ' . route('react-sliders.index'));
            exit;
        }

        $settings = [
            'autoplay' => isset($_POST['autoplay']),
            'autoplay_delay' => (int)($_POST['autoplay_delay'] ?? 5000),
            'loop' => isset($_POST['loop']),
            'navigation' => isset($_POST['navigation']),
            'pagination' => isset($_POST['pagination']),
            'animation' => $_POST['animation'] ?? 'slide',
            'slides_per_view' => (int)($_POST['slides_per_view'] ?? 1),
            'space_between' => (int)($_POST['space_between'] ?? 0),
            'speed' => (int)($_POST['speed'] ?? 500)
        ];

        $data = [
            'name' => $_POST['name'],
            'engine' => $_POST['engine'] ?? 'swiper',
            'settings' => $settings,
            'is_active' => isset($_POST['is_active'])
        ];

        $updated = $slider->update($data);

        if ($updated) {
            flash('success', __rs('messages.slider_updated'));
        } else {
            flash('error', __rs('messages.error_updating_slider'));
        }

        header('Location: ' . route('react-sliders.edit', ['id' => $id]));
        exit;
    }

    public function destroy($id)
    {
        SessionSecurity::startSession();
        $this->checkPermission('react_sliders.delete');

        $slider = ReactSlider::find($id);
        if (!$slider) {
            flash('error', __rs('messages.error_slider_not_found'));
            header('Location: ' . route('react-sliders.index'));
            exit;
        }

        // Verificar si tiene slides
        $slides = $slider->slides();
        if (count($slides) > 0) {
            flash('error', __rs('messages.cannot_delete_with_slides'));
            header('Location: ' . route('react-sliders.index'));
            exit;
        }

        $deleted = $slider->delete();

        if ($deleted) {
            flash('success', __rs('messages.slider_deleted'));
        } else {
            flash('error', __rs('messages.error_deleting_slider'));
        }

        header('Location: ' . route('react-sliders.index'));
        exit;
    }
}
