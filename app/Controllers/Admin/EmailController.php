<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Support\DomainScope;

use App\Core\Session;
use App\Controllers\EmailController as BaseEmailController;
use App\Core\DB;
use App\Providers\ProviderException;
use App\Services\EmailService;

final class EmailController extends BaseEmailController
{
    protected function emailDomain(int $id): array
    {
        $d = $this->requireFound(DB::one(
            'SELECT e.*, c.name AS customer_name, p.label AS provider_label FROM email_domains e
             LEFT JOIN customers c ON c.id = e.customer_id LEFT JOIN providers p ON p.id = e.provider_id WHERE e.id = ?',
            [$id]
        ));
        DomainScope::assert($d['name']);
        if (!can('providers.view')) {
            $d['provider_label'] = null;
        }
        return $d;
    }

    protected function isAdmin(): bool
    {
        return true;
    }

    protected function base(): string
    {
        return '/admin/email';
    }

    protected function canEdit(array $domain): bool
    {
        return can('email.manage');
    }

    public function index(): string
    {
        $where = ['1 = 1'];
        $params = [];
        if (($q = query('q')) !== '') {
            $where[] = 'e.name LIKE ?';
            $params[] = $this->like($q);
        }
        if (($c = query('customer')) !== '') {
            $where[] = '(c.name LIKE ? OR c.code = ?)';
            array_push($params, $this->like($c), $c);
        }
        if (in_array($s = query('status'), ['pending', 'active', 'suspended'], true)) {
            $where[] = 'e.status = ?';
            $params[] = $s;
        }
        [$scopeSql, $scopeParams] = DomainScope::sql('e.name');
        $where[] = $scopeSql;
        array_push($params, ...$scopeParams);
        $w = implode(' AND ', $where);
        $from = 'FROM email_domains e LEFT JOIN customers c ON c.id = e.customer_id';
        return $this->view('admin/email/index', [
            'title' => 'Email',
            'page' => paginate(
                "SELECT e.*, c.name AS customer_name,
                    (SELECT COUNT(*) FROM mailboxes m WHERE m.email_domain_id = e.id) AS mailbox_count,
                    (SELECT COUNT(*) FROM email_aliases a WHERE a.email_domain_id = e.id) AS alias_count
                 $from WHERE $w ORDER BY e.name",
                "SELECT COUNT(*) $from WHERE $w",
                $params,
                30
            ),
            'unclaimed' => can('providers.view') ? (int) DB::value("SELECT COUNT(*) FROM provider_resources WHERE type = 'mail_order' AND local_id IS NULL AND is_missing = 0") : 0,
        ]);
    }

    public function create(): string
    {
        return $this->view('admin/email/create', ['title' => 'Add email domain', 'customers' => customer_options()]);
    }

    public function store(): string
    {
        if (!DomainScope::allows(strtolower(input_str('name')))) {
            $this->failed('/admin/email/create', ['You can only add email for domains that are assigned to you.']);
        }
        try {
            $id = EmailService::createDomain(input_str('name'), (int) input('customer_id', 0) ?: null);
        } catch (\InvalidArgumentException $e) {
            $this->failed('/admin/email/create', [$e->getMessage()]);
        }
        $this->success("/admin/email/$id", 'Email domain added. Verify its MX records to activate it.');
    }

    public function verify(int $id): string
    {
        $this->emailDomain($id);
        [$ok, $note] = EmailService::verify($id);
        flash($ok ? 'success' : 'warning', ($ok ? 'Verified. ' : 'Verification failed. ') . $note);
        redirect("/admin/email/$id");
    }

    public function status(int $id): string
    {
        $this->emailDomain($id);
        try {
            EmailService::setDomainStatus($id, input_str('status'), mb_substr(input_str('reason'), 0, 255));
        } catch (\InvalidArgumentException $e) {
            $this->failed("/admin/email/$id", [$e->getMessage()]);
        }
        $this->success("/admin/email/$id", 'Status updated.');
    }

    public function assign(int $id): string
    {
        $this->emailDomain($id);
        $customerId = (int) input('customer_id', 0) ?: null;
        if ($customerId && !DB::value("SELECT id FROM customers WHERE id = ? AND status <> 'closed'", [$customerId])) {
            $this->failed("/admin/email/$id", ['Choose a valid customer.']);
        }
        EmailService::assignDomain($id, $customerId);
        $this->success("/admin/email/$id", $customerId ? 'Email domain assigned. Its mailboxes and aliases moved with it.' : 'Unassigned.');
    }

    public function import(int $id): string
    {
        $this->emailDomain($id);
        try {
            $c = EmailService::import($id);
        } catch (ProviderException | \RuntimeException $e) {
            $this->failed("/admin/email/$id", [($e instanceof ProviderException ? provider_error($e) : $e->getMessage())]);
        }
        $this->success("/admin/email/$id", "Refreshed from the mail service: {$c['mailboxes']} new mailbox(es), {$c['aliases']} new alias(es).");
    }

    public function destroy(int $id): string
    {
        $d = $this->emailDomain($id);
        try {
            EmailService::deleteDomain($id);
        } catch (\RuntimeException $e) {
            $this->failed("/admin/email/$id", [$e->getMessage()]);
        }
        $this->success('/admin/email', "Email domain {$d['name']} removed.");
    }

    public function unsuspendMailbox(int $id, int $mid): string
    {
        $this->mailboxIn($id, $mid);
        EmailService::unsuspendMailbox($mid);
        $this->success("/admin/email/$id", 'Suspension lifted. The owner can enable the mailbox by setting a new password.');
    }

    /** Email routing trace for any address. */
    public function routing(): string
    {
        $address = query('address');
        if ($address !== '' && !DomainScope::allows(substr((string) strrchr($address, '@'), 1))) {
            flash('warning', 'You can only trace addresses on domains assigned to you.');
            $address = '';
        }
        return $this->view('admin/email/routing', [
            'title' => 'Email routing',
            'address' => $address,
            'trace' => $address !== '' ? EmailService::trace($address) : null,
        ]);
    }

    /** Mail server settings used by the webmail for this domain (empty = defaults). Save or test. */
    public function servers(int $id): string
    {
        // Mail server names reveal the provider: Super Admin only.
        if (!can('providers.view')) {
            abort(403);
        }
        $d = $this->emailDomain($id);
        $data = [];
        $errors = [];
        foreach (['imap' => 'IMAP', 'smtp' => 'SMTP'] as $k => $label) {
            $host = strtolower(trim(input_str($k . '_host')));
            $port = trim(input_str($k . '_port'));
            $sec = input_str($k . '_security');
            if ($host !== '' && !\App\Mail\Webmail::validHost($host)) {
                $errors[] = "Enter a valid $label server name, e.g. mail.example.com.";
            }
            if ($port !== '' && (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535)) {
                $errors[] = "Enter a valid $label port.";
            }
            $data[$k . '_host'] = $host !== '' ? $host : null;
            $data[$k . '_port'] = $port !== '' ? (int) $port : null;
            $data[$k . '_security'] = in_array($sec, ['ssl', 'tls', 'none'], true) ? $sec : null;
        }
        if ($errors) {
            $this->failed("/admin/email/$id", $errors);
        }
        if (input_str('do') === 'test') {
            $servers = \App\Mail\Webmail::serversFor([...$d, ...$data]);
            $email = strtolower(trim(input_str('test_email')));
            if ($email !== '' && !str_ends_with($email, '@' . $d['name'])) {
                $this->failed("/admin/email/$id", ["The test address must be on {$d['name']}."]);
            }
            [$ok, $msg] = \App\Mail\Webmail::test($servers, $email, (string) ($_POST['test_password'] ?? ''));
            Session::flashInput($_POST);
            flash($ok ? 'success' : 'danger', 'Mail server test: ' . $msg);
            redirect("/admin/email/$id");
        }
        DB::update('email_domains', $data + ['updated_at' => now()], 'id = ?', [$id]);
        \App\Core\Logger::activity('email', 'mail_servers', "Updated mail server settings for {$d['name']}", 'email_domain', $id, $d['customer_id'] ? (int) $d['customer_id'] : null);
        $this->success("/admin/email/$id", 'Mail server settings saved. Webmail sign-ins for this domain use them from now on.');
    }

    /** Super Admin: how many email accounts this domain may have (empty = customer/plan limit). */
    public function limit(int $id): string
    {
        if (!\App\Core\Auth::isSuper()) {
            abort(403, 'Only a Super Admin can change email limits.');
        }
        $d = $this->emailDomain($id);
        $v = trim(input_str('max_mailboxes'));
        if ($v !== '' && (!ctype_digit($v) || (int) $v > 100000)) {
            $this->failed("/admin/email/$id", ['Enter a whole number, or leave it empty to use the customer\'s plan limit.']);
        }
        $limit = $v === '' ? null : (int) $v;
        DB::update('email_domains', ['max_mailboxes' => $limit, 'updated_at' => now()], 'id = ?', [$id]);
        \App\Core\Logger::activity('email', 'limit', "Email account limit for {$d['name']}: " . ($limit === null ? 'plan limit' : $limit), 'email_domain', $id, $d['customer_id'] ? (int) $d['customer_id'] : null);
        $used = (int) DB::value('SELECT COUNT(*) FROM mailboxes WHERE email_domain_id = ?', [$id]);
        $this->success("/admin/email/$id", $limit === null
            ? "{$d['name']} now uses the customer's plan limit."
            : "{$d['name']} can have $limit email account" . ($limit === 1 ? '' : 's') . ": $used used, " . max(0, $limit - $used) . ' left.');
    }
}
