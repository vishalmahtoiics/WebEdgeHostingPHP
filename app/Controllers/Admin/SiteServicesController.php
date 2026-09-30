<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\DB;
use App\Core\Logger;
use App\Support\Money;
use App\Support\SiteServices;

/** Services shown on the public website: add, edit, reorder, hide and delete. */
final class SiteServicesController extends Controller
{
    public function index(): string
    {
        return $this->view('admin/site_services/index', [
            'title' => 'Website services',
            'services' => DB::all("SELECT * FROM site_services ORDER BY FIELD(category, 'digital', 'hosting'), sort_order, title"),
        ]);
    }

    public function create(): string
    {
        return $this->view('admin/site_services/form', ['title' => 'Add service', 'service' => null]);
    }

    public function store(): string
    {
        $data = $this->validated(null, '/admin/site-services/create');
        $id = DB::insert('site_services', $data + ['created_at' => now(), 'updated_at' => now()]);
        Logger::activity('settings', 'site_service', "Added website service {$data['title']}", 'site_service', $id);
        $this->success('/admin/site-services', "Service \"{$data['title']}\" added to the website.");
    }

    public function edit(int $id): string
    {
        $s = $this->find($id);
        return $this->view('admin/site_services/form', ['title' => 'Edit ' . $s['title'], 'service' => $s]);
    }

    public function update(int $id): string
    {
        $this->find($id);
        $data = $this->validated($id, "/admin/site-services/$id/edit");
        DB::update('site_services', $data + ['updated_at' => now()], 'id = ?', [$id]);
        Logger::activity('settings', 'site_service', "Updated website service {$data['title']}", 'site_service', $id);
        $this->success('/admin/site-services', 'Service saved.');
    }

    public function status(int $id): string
    {
        $s = $this->find($id);
        $status = $s['status'] === 'active' ? 'hidden' : 'active';
        DB::update('site_services', ['status' => $status, 'updated_at' => now()], 'id = ?', [$id]);
        $this->success('/admin/site-services', $status === 'active' ? "\"{$s['title']}\" is shown on the website." : "\"{$s['title']}\" is hidden from the website.");
    }

    public function move(int $id): string
    {
        $s = $this->find($id);
        $list = DB::all('SELECT id FROM site_services WHERE category = ? ORDER BY sort_order, title', [$s['category']]);
        $ids = array_map('intval', array_column($list, 'id'));
        $pos = array_search($id, $ids, true);
        $to = input_str('dir') === 'up' ? $pos - 1 : $pos + 1;
        if ($pos !== false && $to >= 0 && $to < count($ids)) {
            [$ids[$pos], $ids[$to]] = [$ids[$to], $ids[$pos]];
            foreach ($ids as $i => $sid) {
                DB::update('site_services', ['sort_order' => ($i + 1) * 10], 'id = ?', [$sid]);
            }
        }
        redirect('/admin/site-services');
    }

    public function destroy(int $id): string
    {
        $s = $this->find($id);
        DB::run('DELETE FROM site_services WHERE id = ?', [$id]);
        Logger::activity('settings', 'site_service', "Deleted website service {$s['title']}", 'site_service', $id);
        $this->success('/admin/site-services', "Service \"{$s['title']}\" deleted.");
    }

    private function find(int $id): array
    {
        return $this->requireFound(DB::one('SELECT * FROM site_services WHERE id = ?', [$id]));
    }

    private function validated(?int $id, string $back): array
    {
        $errors = [];
        $title = trim(input_str('title'));
        $slug = trim(strtolower(input_str('slug')));
        $slug = $slug === '' ? SiteServices::slugify($title) : $slug;
        $data = [
            'title' => mb_substr($title, 0, 120),
            'slug' => $slug,
            'category' => input_str('category'),
            'icon' => SiteServices::icon(input_str('icon')),
            'summary' => mb_substr(trim(input_str('summary')), 0, 300),
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
            'features' => trim((string) ($_POST['features'] ?? '')) ?: null,
            'faqs' => trim(str_replace("\r\n", "\n", (string) ($_POST['faqs'] ?? ''))) ?: null,
            'meta_title' => mb_substr(trim(input_str('meta_title')), 0, 120) ?: null,
            'meta_description' => mb_substr(trim(input_str('meta_description')), 0, 300) ?: null,
            'price_note' => mb_substr(trim(input_str('price_note')), 0, 80) ?: null,
            'status' => input('status') ? 'active' : 'hidden',
            'sort_order' => (int) input('sort_order', 0),
        ];
        if (mb_strlen($title) < 2) {
            $errors[] = 'Enter the service name.';
        }
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || strlen($slug) > 80) {
            $errors[] = 'The page address may only use lowercase letters, numbers and dashes (e.g. website-design).';
        } elseif (DB::value('SELECT id FROM site_services WHERE slug = ? AND id <> ?', [$slug, $id ?? 0])) {
            $errors[] = 'Another service already uses this page address.';
        }
        if (!isset(SiteServices::CATEGORIES[$data['category']])) {
            $errors[] = 'Choose a section.';
        }
        if ($data['faqs'] !== null && !SiteServices::faqs($data['faqs'])) {
            $errors[] = 'Write questions as "Q: question" and the answer on the next line as "A: answer", with a blank line between them.';
        }
        if (mb_strlen($data['summary']) < 10) {
            $errors[] = 'Write a short summary (shown on the service card).';
        }
        $price = trim(input_str('price_from'));
        if ($price === '') {
            $data['price_from'] = null;
        } elseif (!Money::isValid($price) || Money::parse($price) < 0) {
            $errors[] = 'Starting price must be an amount like 4999, or empty.';
        } else {
            $data['price_from'] = Money::parse($price);
        }
        if ($errors) {
            $this->failed($back, $errors);
        }
        return $data;
    }
}
