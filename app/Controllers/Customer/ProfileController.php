<?php
declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Controllers\Admin\ProfileController as SharedProfile;
use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Logger;
use App\Core\Settings;
use App\Services\CustomerService;

final class ProfileController extends Controller
{
    public function edit(): string
    {
        $user = Auth::user();
        $customer = DB::one('SELECT * FROM customers WHERE id = ?', [Auth::customerId()]);
        $canEditBilling = $user['is_owner'] && Settings::bool('customer.allow_profile_edit');
        return $this->view('shared/profile', [
            'title' => 'My profile',
            'user' => $user,
            'role' => $user['is_owner'] ? 'Account owner' : 'Team member',
            'action' => '/customer/profile',
            'recentLogins' => DB::all("SELECT * FROM security_logs WHERE user_id = ? AND event IN ('login','failed_login') ORDER BY id DESC LIMIT 5", [$user['id']]),
            'extra' => view('customer/billing_profile', ['customer' => $customer, 'canEdit' => $canEditBilling], null),
        ]);
    }

    public function update(): string
    {
        // The billing profile form posts with section=billing; everything else is the personal form.
        if (input_str('section') !== 'billing') {
            return SharedProfile::updateProfile('/customer/profile');
        }
        if (!Auth::user()['is_owner'] || !Settings::bool('customer.allow_profile_edit')) {
            abort(403);
        }
        $cid = (int) Auth::customerId();
        [$data, $errors] = CustomerService::validateProfile($_POST);
        if ($errors) {
            $this->failed('/customer/profile', $errors);
        }
        DB::update('customers', [...$data, 'updated_at' => now()], 'id = ?', [$cid]);
        Logger::activity('profile', 'billing_update', 'Updated billing profile', 'customer', $cid);
        $this->success('/customer/profile', 'Billing details updated. New invoices will use these details.');
    }

    public function password(): string
    {
        return SharedProfile::changePassword('/customer/profile');
    }
}
