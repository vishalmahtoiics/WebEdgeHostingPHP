<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Settings;
use App\Services\AdminAlerts;
use App\Services\Mailer;
use App\Support\SiteServices;

/** The public company website shown at the site root, and its contact form. */
final class HomeController extends Controller
{
    public const INTERESTS = [
        'migration' => 'Moving an existing website',
        'other' => 'Something else',
    ];

    public function index(): string
    {
        if (!Settings::bool('site.public_home')) {
            if (Auth::isAdmin()) {
                redirect('/admin');
            }
            redirect(Auth::isCustomer() ? '/customer' : '/login');
        }
        $plans = self::plans();
        $interest = '';
        if (ctype_digit($pid = query('plan')) && in_array((int) $pid, array_map('intval', array_column($plans, 'id')), true)) {
            $interest = 'plan:' . $pid;
        } elseif (($svc = query('service')) !== '' && SiteServices::findActive($svc)) {
            $interest = 'service:' . $svc;
        }
        return $this->view('site/home', [
            'title' => (string) (Settings::get('site.name') ?: brand_name()),
            'plans' => $plans,
            'services' => SiteServices::grouped(),
            'interest' => $interest,
            'interests' => self::INTERESTS,
        ], 'layouts/site');
    }

    /** A service's own page, e.g. /services/website-design. */
    public function service(string $slug): string
    {
        if (!Settings::bool('site.public_home')) {
            abort(404);
        }
        $service = SiteServices::findActive($slug) ?? abort(404);
        $all = SiteServices::active();
        return $this->view('site/service', [
            'title' => $service['title'],
            'service' => $service,
            'related' => array_slice(array_values(array_filter($all, static fn ($s) => $s['id'] !== $service['id'] && $s['category'] === $service['category'])), 0, 3),
            'plans' => $service['category'] === 'hosting' ? self::plans() : [],
            'services' => SiteServices::grouped(),
            'interest' => 'service:' . $service['slug'],
            'interests' => self::INTERESTS,
        ], 'layouts/site');
    }

    private static function plans(): array
    {
        return DB::all("SELECT * FROM plans WHERE status = 'active' AND is_public = 1 ORDER BY sort_order, price, id");
    }

    public function contact(): string
    {
        if (!Settings::bool('site.contact_form')) {
            abort(404);
        }
        // Honeypot: people never see this field, simple bots fill it in.
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            $this->success('/#contact', 'Thank you! We have received your message and will get back to you soon.');
        }
        $name = mb_substr(trim(input_str('name')), 0, 120);
        $email = strtolower(trim(input_str('email')));
        $phone = mb_substr(preg_replace('/[^0-9+\-() ]/', '', input_str('phone')), 0, 40);
        $interest = input_str('interest');
        $message = mb_substr(trim((string) ($_POST['message'] ?? '')), 0, 5000);

        $label = self::INTERESTS[$interest] ?? null;
        if (str_starts_with($interest, 'plan:') && ctype_digit($pid = substr($interest, 5))) {
            $plan = DB::value("SELECT name FROM plans WHERE id = ? AND status = 'active' AND is_public = 1", [(int) $pid]);
            $label = $plan ? 'Plan: ' . $plan : null;
        } elseif (str_starts_with($interest, 'service:')) {
            $svc = SiteServices::findActive(substr($interest, 8));
            $label = $svc ? $svc['title'] : null;
        }
        $back = input_str('back') !== '' && preg_match('#^/services/[a-z0-9-]+$#', input_str('back')) ? input_str('back') : '/';

        $errors = [];
        if (mb_strlen($name) < 2) {
            $errors[] = 'Please enter your name.';
        }
        if (!valid_email($email)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (mb_strlen($message) < 10) {
            $errors[] = 'Please write a short message (at least 10 characters).';
        }
        $ip = client_ip();
        $recent = (int) DB::value('SELECT COUNT(*) FROM enquiries WHERE ip = ? AND created_at > ?', [$ip, date('Y-m-d H:i:s', time() - 3600)]);
        if ($recent >= 5) {
            $errors[] = 'You have sent several messages already. Please wait a while, or contact us by email or phone.';
        }
        if ($errors) {
            $this->failed($back . '#contact', $errors);
        }

        $id = DB::insert('enquiries', [
            'name' => $name, 'email' => $email, 'phone' => $phone ?: null, 'interest' => $label,
            'message' => $message, 'ip' => $ip, 'status' => 'new', 'created_at' => now(),
        ]);

        $recipients = AdminAlerts::recipients();
        if ($recipients) {
            $link = rtrim((string) config('app.url'), '/') . '/admin/enquiries/' . $id;
            $html = '<p>New enquiry from the website.</p><table cellpadding="4">'
                . '<tr><td style="color:#6b7280">Name</td><td><strong>' . e($name) . '</strong></td></tr>'
                . '<tr><td style="color:#6b7280">Email</td><td>' . e($email) . '</td></tr>'
                . ($phone ? '<tr><td style="color:#6b7280">Phone</td><td>' . e($phone) . '</td></tr>' : '')
                . ($label ? '<tr><td style="color:#6b7280">About</td><td>' . e($label) . '</td></tr>' : '')
                . '</table><p style="white-space:pre-wrap;border-left:3px solid #e5e7eb;padding-left:12px">' . e($message) . '</p>'
                . '<p><a href="' . e($link) . '">Open in the admin panel</a></p>';
            $subject = 'Website enquiry from ' . $name . ($label ? " — $label" : '');
            register_shutdown_function(static function () use ($recipients, $subject, $html): void {
                if (function_exists('fastcgi_finish_request')) {
                    @fastcgi_finish_request();
                } elseif (function_exists('litespeed_finish_request')) {
                    @litespeed_finish_request();
                }
                foreach ($recipients as $to) {
                    Mailer::send($to, $subject, $html);
                }
            });
        }
        $this->success($back . '#contact', 'Thank you! We have received your message and will get back to you soon.');
    }
}
