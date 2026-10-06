<?php
declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Controllers\EmailController as BaseEmailController;
use App\Core\Auth;
use App\Core\DB;
use App\Services\EmailService;
use App\Services\PlanLimits;

final class EmailController extends BaseEmailController
{
    protected function emailDomain(int $id): array
    {
        return $this->requireFound(DB::one('SELECT * FROM email_domains WHERE id = ? AND customer_id = ?', [$id, (int) Auth::customerId()]));
    }

    protected function isAdmin(): bool
    {
        return false;
    }

    protected function base(): string
    {
        return '/customer/email';
    }

    /** Customers manage mailboxes on active and not-yet-verified domains; only suspended ones are locked. */
    protected function canEdit(array $domain): bool
    {
        return $domain['status'] !== 'suspended';
    }

    public function index(): string
    {
        $cid = (int) Auth::customerId();
        return $this->view('customer/email', [
            'title' => 'Email',
            'domains' => DB::all(
                "SELECT e.id, e.name, e.status, e.max_mailboxes,
                    (SELECT COUNT(*) FROM mailboxes m WHERE m.email_domain_id = e.id) AS mailbox_count,
                    (SELECT COUNT(*) FROM email_aliases a WHERE a.email_domain_id = e.id) AS alias_count
                 FROM email_domains e WHERE e.customer_id = ? ORDER BY e.name",
                [$cid]
            ),
            'usage' => PlanLimits::summary($cid),
        ]);
    }

    /** "Upgrade email plan": tell the admin team this customer needs more email storage. */
    public function upgrade(int $id): string
    {
        $d = $this->emailDomain($id);
        $recent = DB::value("SELECT 1 FROM email_upgrade_requests WHERE email_domain_id = ? AND status = 'open' AND created_at > NOW() - INTERVAL 1 DAY", [$id]);
        if ($recent) {
            flash('info', 'We already have your request for more email storage on ' . $d['name'] . '. Our team will contact you soon.');
            redirect("/customer/email/$id");
        }
        $message = mb_substr(trim(input_str('message')), 0, 1000) ?: null;
        $u = Auth::user();
        DB::insert('email_upgrade_requests', ['email_domain_id' => $id, 'customer_id' => (int) Auth::customerId(), 'user_id' => $u['id'] ?? null,
            'message' => $message, 'status' => 'open', 'created_at' => now()]);
        \App\Core\Logger::activity('email', 'upgrade_request', "Asked to upgrade the email plan for {$d['name']}", 'email_domain', $id);

        $s = EmailService::storage($d);
        $c = DB::one('SELECT name, code FROM customers WHERE id = ?', [(int) Auth::customerId()]);
        $link = rtrim((string) config('app.url'), '/') . '/admin/email/' . $id;
        $html = '<p><strong>' . e($c['name'] ?? 'A customer') . '</strong>' . (!empty($c['code']) ? ' (' . e($c['code']) . ')' : '') . ' wants to upgrade the email plan for <strong>' . e($d['name']) . '</strong>.</p>'
            . '<ul><li>Requested by: ' . e(($u['name'] ?? '') . ' <' . ($u['email'] ?? '') . '>') . '</li>'
            . '<li>Total storage: ' . e(EmailService::sizeLabel($s['total'])) . '</li>'
            . '<li>Size per email account: ' . e(EmailService::sizeLabel($s['per'])) . '</li>'
            . '<li>Email accounts: ' . (int) $s['mailboxes'] . ' · storage used: ' . e(EmailService::sizeLabel($s['used'])) . '</li></ul>'
            . ($message ? '<p><strong>Message:</strong><br>' . nl2br(e($message)) . '</p>' : '')
            . '<p><a href="' . e($link) . '">Open ' . e($d['name']) . ' in the admin panel</a> to change the storage.</p>';
        \App\Services\AdminAlerts::important('Email upgrade request: ' . ($c['name'] ?? 'Customer') . ' — ' . $d['name'], $html);
        $this->success("/customer/email/$id", 'Request sent. Our team will contact you soon about more email storage.');
    }
}
