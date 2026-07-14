<?php

namespace Screenart\Musedock\Controllers\Frontend;

use Screenart\Musedock\View;

class ContactController
{
    public function index()
    {
        $siteName = site_setting('site_name', 'MuseDock');
        $contactEmail = site_setting('contact_email', '') ?: (string)(getenv('MAIL_FROM_ADDRESS') ?: '');
        $contactPhone = site_setting('contact_phone', '');
        $contactAddress = site_setting('contact_address', '');

        return View::renderTheme('contact', [
            'siteName' => $siteName,
            'contactEmail' => $contactEmail,
            'contactPhone' => $contactPhone,
            'contactAddress' => $contactAddress,
        ]);
    }
}
