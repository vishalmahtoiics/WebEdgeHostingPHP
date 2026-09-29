<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Services\WebmailService;
use RuntimeException;

/** Settings → Webmail: install and configure the customer webmail subdomain. */
final class WebmailController extends Controller
{
    public function index(): string
    {
        $status = WebmailService::status();
        $host = (string) (parse_url($status['url'] ?: '', PHP_URL_HOST) ?: '');
        return $this->view('admin/webmail/index', [
            'title' => 'Webmail',
            'status' => $status,
            'suggestions' => WebmailService::suggestDocroots($host ?: 'mails.' . $this->mainDomain()),
            'defaultUrl' => $status['url'] ?: 'https://mails.' . $this->mainDomain(),
        ]);
    }

    public function install(): string
    {
        $url = rtrim(input_str('url'), '/');
        $docroot = rtrim(input_str('docroot'), '/');
        try {
            $msg = WebmailService::install(
                $url,
                $docroot,
                input_str('imap_host') ?: WebmailService::DEFAULT_IMAP,
                input_str('smtp_host') ?: WebmailService::DEFAULT_SMTP,
                (bool) input('filters', false)
            );
        } catch (RuntimeException | \PharException | \UnexpectedValueException | \PDOException $e) {
            $this->failed('/admin/webmail', ['Webmail: ' . $e->getMessage()]);
        }
        $this->success('/admin/webmail', "$msg. Customers can now sign in at $url with their email address and password.");
    }

    private function mainDomain(): string
    {
        $host = (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'example.com');
        $parts = explode('.', $host);
        // panel.example.com -> example.com; example.co.in stays example.co.in
        return count($parts) > 2 && strlen($parts[count($parts) - 2]) > 3 ? implode('.', array_slice($parts, -2)) : $host;
    }
}
