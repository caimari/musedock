<?php

namespace Shop\Controllers\Superadmin;

use Screenart\Musedock\View;
use Shop\Models\Coupon;

class CouponController
{
    private function checkPermission(): void
    {
        if (!userCan('shop.manage')) {
            flash('error', 'No tienes permiso para gestionar cupones.');
            header('Location: /musedock/dashboard');
            exit;
        }
    }

    public function index()
    {
        $this->checkPermission();

        $query = Coupon::query()
            ->whereNull('tenant_id')
            ->orderBy('created_at', 'DESC');

        $coupons = $query->get();
        $coupons = array_map(fn($row) => new Coupon($row), $coupons);

        echo View::renderModule('musedock-shop', 'superadmin.shop.coupons.index', [
            'title' => __('shop.coupons'),
            'coupons' => $coupons,
        ]);
    }

    public function create()
    {
        $this->checkPermission();

        echo View::renderModule('musedock-shop', 'superadmin.shop.coupons.create', [
            'title' => __('shop.coupon.new'),
        ]);
    }

    public function store()
    {
        $this->checkPermission();

        $code = strtoupper(trim($_POST['code'] ?? ''));
        if (empty($code)) {
            flash('error', 'El código es obligatorio.');
            header('Location: ' . route('shop.coupons.create'));
            exit;
        }

        if (Coupon::findByCode($code)) {
            flash('error', 'Ya existe un cupón con ese código.');
            header('Location: ' . route('shop.coupons.create'));
            exit;
        }

        $type = $_POST['type'] ?? 'percentage';
        $value = $type === 'percentage'
            ? (int) ($_POST['value'] ?? 0)
            : (int) round(((float) str_replace(',', '.', $_POST['value'] ?? '0')) * 100);

        Coupon::create([
            'tenant_id' => null,
            'code' => $code,
            'description' => $_POST['description'] ?? null,
            'type' => $type,
            'value' => $value,
            'min_order_amount' => !empty($_POST['min_order_amount'])
                ? (int) round(((float) str_replace(',', '.', $_POST['min_order_amount'])) * 100)
                : null,
            'max_discount_amount' => !empty($_POST['max_discount_amount'])
                ? (int) round(((float) str_replace(',', '.', $_POST['max_discount_amount'])) * 100)
                : null,
            'max_uses' => !empty($_POST['max_uses']) ? (int) $_POST['max_uses'] : null,
            'valid_from' => $_POST['valid_from'] ?: null,
            'valid_until' => $_POST['valid_until'] ?: null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ]);

        flash('success', __('shop.coupon.created'));
        header('Location: ' . route('shop.coupons.index'));
        exit;
    }

    public function edit($id)
    {
        $this->checkPermission();

        $coupon = Coupon::find($id);
        if (!$coupon) {
            flash('error', 'Cupón no encontrado.');
            header('Location: ' . route('shop.coupons.index'));
            exit;
        }

        echo View::renderModule('musedock-shop', 'superadmin.shop.coupons.edit', [
            'title' => __('shop.coupon.edit'),
            'coupon' => $coupon,
        ]);
    }

    public function update($id)
    {
        $this->checkPermission();

        $coupon = Coupon::find($id);
        if (!$coupon) {
            flash('error', 'Cupón no encontrado.');
            header('Location: ' . route('shop.coupons.index'));
            exit;
        }

        $type = $_POST['type'] ?? 'percentage';
        $value = $type === 'percentage'
            ? (int) ($_POST['value'] ?? 0)
            : (int) round(((float) str_replace(',', '.', $_POST['value'] ?? '0')) * 100);

        $coupon->update([
            'code' => strtoupper(trim($_POST['code'] ?? $coupon->code)),
            'description' => $_POST['description'] ?? null,
            'type' => $type,
            'value' => $value,
            'min_order_amount' => !empty($_POST['min_order_amount'])
                ? (int) round(((float) str_replace(',', '.', $_POST['min_order_amount'])) * 100)
                : null,
            'max_discount_amount' => !empty($_POST['max_discount_amount'])
                ? (int) round(((float) str_replace(',', '.', $_POST['max_discount_amount'])) * 100)
                : null,
            'max_uses' => !empty($_POST['max_uses']) ? (int) $_POST['max_uses'] : null,
            'valid_from' => $_POST['valid_from'] ?: null,
            'valid_until' => $_POST['valid_until'] ?: null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ]);

        flash('success', __('shop.coupon.updated'));
        header('Location: ' . route('shop.coupons.index'));
        exit;
    }

    public function destroy($id)
    {
        $this->checkPermission();

        $coupon = Coupon::find($id);
        if ($coupon) {
            $coupon->delete();
            flash('success', __('shop.coupon.deleted'));
        }

        header('Location: ' . route('shop.coupons.index'));
        exit;
    }
}
