<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Logger;
use App\Support\Blog;

/** Write, edit and publish blog posts. */
final class BlogController extends Controller
{
    private const IMAGE_TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    public function index(): string
    {
        $where = ['1 = 1'];
        $params = [];
        if (in_array($s = query('status'), ['draft', 'published'], true)) {
            $where[] = 'p.status = ?';
            $params[] = $s;
        }
        if (($q = query('q')) !== '') {
            $where[] = '(p.title LIKE ? OR p.focus_keyword LIKE ?)';
            array_push($params, $this->like($q), $this->like($q));
        }
        $w = implode(' AND ', $where);
        return $this->view('admin/blog/index', [
            'title' => 'Blog posts',
            'page' => paginate("SELECT p.*, u.name AS author_name FROM blog_posts p LEFT JOIN users u ON u.id = p.author_id WHERE $w ORDER BY p.status = 'draft' DESC, p.published_at DESC, p.id DESC", "SELECT COUNT(*) FROM blog_posts p WHERE $w", $params, 25),
        ]);
    }

    public function create(): string
    {
        return $this->form(null);
    }

    public function edit(int $id): string
    {
        return $this->form($this->find($id));
    }

    private function form(?array $post): string
    {
        return $this->view('admin/blog/form', [
            'title' => $post ? 'Edit post' : 'New blog post',
            'post' => $post,
            'checks' => $post ? Blog::seoCheck($post) : [],
            'categories' => DB::column("SELECT DISTINCT category FROM blog_posts WHERE category IS NOT NULL AND category <> '' ORDER BY category"),
            'services' => DB::all('SELECT id, title FROM site_services ORDER BY title'),
        ]);
    }

    public function store(): string
    {
        $data = $this->validated(null, '/admin/blog/create');
        $data['author_id'] = Auth::id();
        $id = DB::insert('blog_posts', $data + ['created_at' => now(), 'updated_at' => now()]);
        Logger::activity('settings', 'blog', ($data['status'] === 'published' ? 'Published' : 'Drafted') . " blog post \"{$data['title']}\"", 'blog_post', $id);
        $this->after($id, $data, 'Post saved.');
    }

    public function update(int $id): string
    {
        $old = $this->find($id);
        $data = $this->validated($old, "/admin/blog/$id/edit");
        DB::update('blog_posts', $data + ['updated_at' => now()], 'id = ?', [$id]);
        Logger::activity('settings', 'blog', "Updated blog post \"{$data['title']}\"", 'blog_post', $id);
        $this->after($id, $data, $data['status'] === 'published' && $old['status'] !== 'published' ? 'Post published.' : 'Post saved.');
    }

    private function after(int $id, array $data, string $msg): never
    {
        if (input_str('then') === 'preview') {
            redirect('/blog/' . $data['slug']);
        }
        $this->success("/admin/blog/$id/edit", $msg . ($data['status'] === 'published' ? ' It is live at /blog/' . $data['slug'] . '.' : ''));
    }

    public function status(int $id): string
    {
        $p = $this->find($id);
        $publish = $p['status'] !== 'published';
        DB::update('blog_posts', ['status' => $publish ? 'published' : 'draft', 'published_at' => $publish ? ($p['published_at'] ?: now()) : $p['published_at'], 'updated_at' => now()], 'id = ?', [$id]);
        Logger::activity('settings', 'blog', ($publish ? 'Published' : 'Unpublished') . " blog post \"{$p['title']}\"", 'blog_post', $id);
        $this->success('/admin/blog', $publish ? "\"{$p['title']}\" is live." : "\"{$p['title']}\" is now a draft.");
    }

    public function destroy(int $id): string
    {
        $p = $this->find($id);
        DB::run('DELETE FROM blog_posts WHERE id = ?', [$id]);
        $this->removeImage($p['cover_image']);
        Logger::activity('settings', 'blog', "Deleted blog post \"{$p['title']}\"", 'blog_post', $id);
        $this->success('/admin/blog', 'Post deleted.');
    }

    private function find(int $id): array
    {
        return $this->requireFound(DB::one('SELECT * FROM blog_posts WHERE id = ?', [$id]));
    }

    private function validated(?array $old, string $back): array
    {
        $errors = [];
        $title = mb_substr(trim(input_str('title')), 0, 200);
        $slug = Blog::slugify(input_str('slug') !== '' ? input_str('slug') : $title);
        $body = str_replace("\r\n", "\n", (string) ($_POST['body'] ?? ''));
        $publishAt = trim(input_str('published_at'));
        $data = [
            'title' => $title,
            'slug' => $slug,
            'excerpt' => mb_substr(trim(input_str('excerpt')), 0, 400) ?: null,
            'body' => $body,
            'category' => mb_substr(trim(input_str('category')), 0, 80) ?: null,
            'service_id' => (int) input('service_id', 0) ?: null,
            'meta_title' => mb_substr(trim(input_str('meta_title')), 0, 120) ?: null,
            'meta_description' => mb_substr(trim(input_str('meta_description')), 0, 300) ?: null,
            'focus_keyword' => mb_substr(trim(input_str('focus_keyword')), 0, 120) ?: null,
            'cover_alt' => mb_substr(trim(input_str('cover_alt')), 0, 200) ?: null,
            'status' => input_str('status') === 'published' ? 'published' : 'draft',
        ];
        if (mb_strlen($title) < 5) {
            $errors[] = 'Enter a title (at least 5 characters).';
        }
        if (DB::value('SELECT id FROM blog_posts WHERE slug = ? AND id <> ?', [$slug, $old['id'] ?? 0])) {
            $errors[] = 'Another post already uses the address /blog/' . $slug . '.';
        }
        if (trim($body) === '') {
            $errors[] = 'Write the article text.';
        }
        if ($data['service_id'] && !DB::value('SELECT id FROM site_services WHERE id = ?', [$data['service_id']])) {
            $data['service_id'] = null;
        }
        if ($publishAt !== '') {
            $ts = strtotime($publishAt);
            if ($ts === false) {
                $errors[] = 'Enter a valid publish date.';
            } else {
                $data['published_at'] = date('Y-m-d H:i:s', $ts);
            }
        } elseif ($data['status'] === 'published') {
            $data['published_at'] = $old['published_at'] ?? now();
        } else {
            $data['published_at'] = $old['published_at'] ?? null;
        }
        $image = $this->upload($errors);
        if ($image !== null) {
            $data['cover_image'] = $image;
            $this->removeImage($old['cover_image'] ?? null);
        } elseif (input('remove_cover') && $old) {
            $data['cover_image'] = null;
            $this->removeImage($old['cover_image']);
        }
        if ($errors) {
            if ($image !== null) {
                $this->removeImage($image);
            }
            $this->failed($back, $errors);
        }
        return $data;
    }

    private function upload(array &$errors): ?string
    {
        $f = $_FILES['cover'] ?? null;
        if (!$f || (int) $f['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ((int) $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f['tmp_name'])) {
            $errors[] = 'The cover image upload failed. Please try again.';
            return null;
        }
        if ((int) $f['size'] > 3 * 1048576) {
            $errors[] = 'Cover images must be 3 MB or smaller.';
            return null;
        }
        $ext = self::IMAGE_TYPES[(new \finfo(FILEINFO_MIME_TYPE))->file((string) $f['tmp_name'])] ?? null;
        if ($ext === null || @getimagesize((string) $f['tmp_name']) === false) {
            $errors[] = 'Upload the cover as a JPG, PNG or WebP image.';
            return null;
        }
        $dir = BASE_PATH . '/public/uploads/blog';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            $errors[] = 'The upload folder is not writable.';
            return null;
        }
        $name = Blog::slugify(input_str('title') ?: 'cover', 60) . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!move_uploaded_file((string) $f['tmp_name'], "$dir/$name")) {
            $errors[] = 'Could not save the cover image.';
            return null;
        }
        @chmod("$dir/$name", 0644);
        return 'uploads/blog/' . $name;
    }

    private function removeImage(?string $path): void
    {
        if ($path && str_starts_with($path, 'uploads/blog/') && !str_contains($path, '..') && is_file(BASE_PATH . '/public/' . $path)) {
            @unlink(BASE_PATH . '/public/' . $path);
        }
    }
}
