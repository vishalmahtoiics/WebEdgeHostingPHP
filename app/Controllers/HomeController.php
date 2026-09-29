<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;

final class HomeController extends Controller
{
    public function index(): string
    {
        // A webmail host name (e.g. mails.example.com) pointing at the panel opens the webmail.
        $host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
        $webmailHosts = array_filter(array_map(static fn ($h) => strtolower(trim($h)), explode(',', (string) \App\Core\Settings::get('webmail.hostnames', ''))));
        if ($host !== '' && in_array($host, $webmailHosts, true) && \App\Mail\Webmail::enabled()) {
            redirect('/mails');
        }
        if (Auth::isAdmin()) {
            redirect('/admin');
        }
        redirect(Auth::isCustomer() ? '/customer' : '/login');
    }
}
