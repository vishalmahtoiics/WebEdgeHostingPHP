<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;

final class HomeController extends Controller
{
    public function index(): string
    {
        if (Auth::isAdmin()) {
            redirect('/admin');
        }
        redirect(Auth::isCustomer() ? '/customer' : '/login');
    }
}
