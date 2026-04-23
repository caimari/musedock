<?php

namespace FestivalDirectory\Requests;

class FestivalRequest
{
    /**
     * Validate festival form data.
     * Returns array of error messages (empty = valid).
     */
    public static function validate(array $data): array
    {
        $errors = [];

        if (empty(trim($data['name'] ?? ''))) {
            $errors[] = 'El nombre es obligatorio.';
        }

        if (empty(trim($data['slug'] ?? ''))) {
            $errors[] = 'El slug es obligatorio.';
        } elseif (!preg_match('/^[a-z0-9\-]+$/', $data['slug'])) {
            $errors[] = 'El slug solo puede contener letras minúsculas, números y guiones.';
        }

        if (empty(trim($data['country'] ?? ''))) {
            $errors[] = 'El país es obligatorio.';
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El email no es válido.';
        }

        if (!empty($data['contact_email']) && !filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El email de contacto no es válido.';
        }

        $urlFields = ['website_url', 'social_facebook', 'social_instagram', 'social_twitter',
            'social_youtube', 'social_vimeo', 'social_linkedin',
            'submission_filmfreeway_url', 'submission_festhome_url',
            'submission_festgate_url', 'submission_other_url'];

        foreach ($urlFields as $field) {
            if (!empty($data[$field]) && !preg_match('#^https?://#i', $data[$field])) {
                $errors[] = 'La URL ' . str_replace('_', ' ', $field) . ' debe empezar con http:// o https://';
            }
        }

        if (!empty($data['start_date']) && !empty($data['end_date'])) {
            if ($data['end_date'] < $data['start_date']) {
                $errors[] = 'La fecha de fin no puede ser anterior a la de inicio.';
            }
        }

        return $errors;
    }

    /**
     * Validate claim form data.
     */
    public static function validateClaim(array $data): array
    {
        $errors = [];

        if (empty(trim($data['user_name'] ?? ''))) {
            $errors[] = 'Tu nombre es obligatorio.';
        }

        if (empty(trim($data['user_email'] ?? ''))) {
            $errors[] = 'Tu email es obligatorio.';
        } elseif (!filter_var($data['user_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El email no es válido.';
        }

        if (empty(trim($data['user_role'] ?? ''))) {
            $errors[] = 'Tu rol/cargo es obligatorio.';
        }

        if (empty($data['is_authorized'])) {
            $errors[] = 'Debes confirmar que representas a este festival.';
        }

        return $errors;
    }
}
