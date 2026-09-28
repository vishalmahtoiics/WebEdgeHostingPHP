<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;

abstract class Controller
{
    protected function view(string $template, array $data = [], ?string $layout = 'layouts/app'): string
    {
        return view($template, $data, $layout);
    }

    /** Redirect back to a form with errors and the submitted input. */
    protected function failed(string $to, array $errors): never
    {
        Session::flashInput($_POST);
        foreach ($errors as $err) {
            flash('danger', $err);
        }
        redirect($to);
    }

    protected function success(string $to, string $message): never
    {
        flash('success', $message);
        redirect($to);
    }

    /** Build a LIKE pattern that treats user input literally. */
    protected function like(string $term): string
    {
        return '%' . addcslashes($term, '%_\\') . '%';
    }

    protected function requireFound(?array $row): array
    {
        if (!$row) {
            abort(404);
        }
        return $row;
    }
}
