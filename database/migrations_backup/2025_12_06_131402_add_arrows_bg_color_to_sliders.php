<?php

use Screenart\Musedock\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->addColumn('sliders', 'arrows_bg_color', "VARCHAR(50) DEFAULT 'rgba(255,255,255,0.9)' AFTER arrows_color");
    }

    public function down(): void
    {
        $this->dropColumn('sliders', 'arrows_bg_color');
    }
};
