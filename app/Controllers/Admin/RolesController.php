<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\DB;
use App\Core\Logger;
use App\Support\Permissions;

final class RolesController extends Controller
{
    public function index(): string
    {
        return $this->view('admin/roles/index', [
            'title' => 'Roles & permissions',
            'roles' => DB::all(
                "SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id AND u.type = 'admin') AS user_count,
                        (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS perm_count
                 FROM roles r ORDER BY r.is_super DESC, r.name"
            ),
        ]);
    }

    public function create(): string
    {
        return $this->view('admin/roles/form', ['title' => 'New role', 'role' => null, 'granted' => (array) old('permissions', [])]);
    }

    public function store(): string
    {
        [$name, $description, $perms, $errors] = $this->validate(null);
        if ($errors) {
            $this->failed('/admin/roles/create', $errors);
        }
        $id = DB::transaction(static function () use ($name, $description, $perms): int {
            $id = DB::insert('roles', ['name' => $name, 'description' => $description, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($perms as $p) {
                DB::insert('role_permissions', ['role_id' => $id, 'permission' => $p]);
            }
            return $id;
        });
        Logger::activity('security', 'role_create', "Created role $name", 'role', $id);
        Logger::security('permission_change', 'info', "Role $name created with " . count($perms) . ' permissions: ' . implode(', ', $perms));
        $this->success('/admin/roles', 'Role created.');
    }

    public function edit(int $id): string
    {
        $role = $this->requireFound(DB::one('SELECT * FROM roles WHERE id = ?', [$id]));
        return $this->view('admin/roles/form', [
            'title' => 'Edit role · ' . $role['name'],
            'role' => $role,
            'granted' => DB::column('SELECT permission FROM role_permissions WHERE role_id = ?', [$id]),
        ]);
    }

    public function update(int $id): string
    {
        $role = $this->requireFound(DB::one('SELECT * FROM roles WHERE id = ?', [$id]));
        [$name, $description, $perms, $errors] = $this->validate($id);
        if ($errors) {
            $this->failed("/admin/roles/$id/edit", $errors);
        }
        $before = DB::column('SELECT permission FROM role_permissions WHERE role_id = ?', [$id]);
        DB::transaction(static function () use ($id, $role, $name, $description, $perms): void {
            DB::update('roles', ['name' => $role['is_system'] ? $role['name'] : $name, 'description' => $description, 'updated_at' => now()], 'id = ?', [$id]);
            if (!$role['is_super']) {
                DB::run('DELETE FROM role_permissions WHERE role_id = ?', [$id]);
                foreach ($perms as $p) {
                    DB::insert('role_permissions', ['role_id' => $id, 'permission' => $p]);
                }
            }
        });
        Logger::activity('security', 'role_update', "Updated role {$role['name']}", 'role', $id);
        $added = array_diff($perms, $before);
        $removed = array_diff($before, $perms);
        if (!$role['is_super'] && ($added || $removed)) {
            Logger::security('permission_change', 'info', "Role {$role['name']} permissions changed. Added: " . (implode(', ', $added) ?: 'none') . '. Removed: ' . (implode(', ', $removed) ?: 'none'));
        }
        $this->success('/admin/roles', 'Role updated.');
    }

    public function destroy(int $id): string
    {
        $role = $this->requireFound(DB::one('SELECT * FROM roles WHERE id = ?', [$id]));
        if ($role['is_system'] || $role['is_super']) {
            $this->failed('/admin/roles', ['Built-in roles cannot be deleted.']);
        }
        if (DB::value('SELECT COUNT(*) FROM users WHERE role_id = ?', [$id]) > 0) {
            $this->failed('/admin/roles', ['Move admin users to another role before deleting this one.']);
        }
        DB::run('DELETE FROM roles WHERE id = ?', [$id]);
        Logger::activity('security', 'role_delete', "Deleted role {$role['name']}", 'role', $id);
        Logger::security('permission_change', 'info', "Role {$role['name']} deleted");
        $this->success('/admin/roles', 'Role deleted.');
    }

    private function validate(?int $id): array
    {
        $errors = [];
        $name = input_str('name');
        $description = mb_substr(input_str('description'), 0, 255) ?: null;
        $perms = array_values(array_intersect(Permissions::adminKeys(), (array) ($_POST['permissions'] ?? [])));
        if ($name === '' || mb_strlen($name) > 100) {
            $errors[] = 'Role name is required (max 100 characters).';
        } elseif (DB::value('SELECT id FROM roles WHERE name = ? AND id <> ?', [$name, $id ?? 0])) {
            $errors[] = 'A role with that name already exists.';
        }
        return [$name, $description, $perms, $errors];
    }
}
