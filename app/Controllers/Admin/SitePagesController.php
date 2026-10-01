<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\DB;
use App\Core\Logger;
use App\Support\AdsReadiness;
use App\Support\SitePages;

/** Edit the About, Contact, Privacy, Terms, Refund and Disclaimer pages. */
final class SitePagesController extends Controller
{
    public function index(): string
    {
        return $this->view('admin/site_pages/index', [
            'title' => 'Website pages',
            'pages' => DB::all('SELECT * FROM site_pages ORDER BY sort_order, title'),
            'checks' => AdsReadiness::checks(),
        ]);
    }

    public function edit(int $id): string
    {
        $p = $this->find($id);
        return $this->view('admin/site_pages/form', ['title' => 'Edit ' . $p['title'], 'page' => $p]);
    }

    public function update(int $id): string
    {
        $p = $this->find($id);
        $title = mb_substr(trim(input_str('title')), 0, 150);
        $body = str_replace("\r\n", "\n", (string) ($_POST['body'] ?? ''));
        $errors = [];
        if (mb_strlen($title) < 2) {
            $errors[] = 'Enter the page title.';
        }
        if (trim($body) === '') {
            $errors[] = 'The page text cannot be empty.';
        }
        if ($errors) {
            $this->failed("/admin/site-pages/$id/edit", $errors);
        }
        DB::update('site_pages', [
            'title' => $title,
            'body' => $body,
            'meta_description' => mb_substr(trim(input_str('meta_description')), 0, 300) ?: null,
            'status' => input('status') ? 'published' : 'hidden',
            'updated_at' => now(),
        ], 'id = ?', [$id]);
        Logger::activity('settings', 'site_page', "Updated the website page \"$title\"", 'site_page', $id);
        if (input_str('then') === 'preview') {
            redirect('/' . $p['slug']);
        }
        $this->success("/admin/site-pages/$id/edit", 'Page saved.');
    }

    private function find(int $id): array
    {
        return $this->requireFound(DB::one('SELECT * FROM site_pages WHERE id = ?', [$id]));
    }
}
