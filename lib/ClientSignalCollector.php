<?php

namespace WHMCS\Module\Addon\ClientHealthScore;

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

class ClientSignalCollector
{
    /**
     * Collect raw billing/payment and activity/engagement signals for a client.
     */
    public function collect(int $clientId, array $enabledSignals = []): array
    {
        // 1. Fetch signup datecreated & lastlogin from tblclients
        $client = Capsule::table('tblclients')
            ->where('id', $clientId)
            ->select('id', 'datecreated', 'lastlogin')
            ->first();

        $dateCreated = $client ? $client->datecreated : '';
        $lastLogin = $client ? $client->lastlogin : '';

        // 2. Fetch Average Days Late (trailing 90 days)
        $date90 = date('Y-m-d', strtotime('-90 days'));
        $paidInvoices = Capsule::table('tblinvoices')
            ->where('userid', $clientId)
            ->where('status', 'Paid')
            ->where('datepaid', '>=', $date90)
            ->select('duedate', 'datepaid')
            ->get();

        $delays = [];
        foreach ($paidInvoices as $inv) {
            $due = strtotime($inv->duedate);
            $paid = strtotime($inv->datepaid);
            $delay = 0.0;
            if ($paid > $due) {
                $delay = (float)(($paid - $due) / 86400.0);
            }
            $delays[] = $delay;
        }
        $avgLate = 0.0;
        if (!empty($delays)) {
            $avgLate = (float)(array_sum($delays) / count($delays));
        }

        // 3. Fetch Failed Gateway Payments (trailing 90 days)
        $failedCount = 0;
        $invs = Capsule::table('tblinvoices')
            ->where('userid', $clientId)
            ->pluck('id')
            ->toArray();

        $date90Time = date('Y-m-d H:i:s', strtotime('-90 days'));
        if (!empty($invs)) {
            foreach ($invs as $invId) {
                $failedCount += Capsule::table('tblgatewaylog')
                    ->where('date', '>=', $date90Time)
                    ->where('data', 'like', "%Invoice ID => {$invId}%")
                    ->whereIn('result', ['Error', 'Declined', 'Failed'])
                    ->count();
            }
        }

        // 4. Fetch Current Overdue Invoice Count
        $overdueCount = Capsule::table('tblinvoices')
            ->where('userid', $clientId)
            ->where('status', 'Unpaid')
            ->where('duedate', '<', date('Y-m-d'))
            ->count();

        // 5. Fetch Refund/Chargeback Flag (trailing 12 months)
        $date12Months = date('Y-m-d', strtotime('-365 days'));
        $refundedCount = Capsule::table('tblinvoices')
            ->where('userid', $clientId)
            ->where('status', 'Refunded')
            ->where('datepaid', '>=', $date12Months)
            ->count();

        // 6. Fetch Login Frequency (trailing 90 days)
        $loginCount = Capsule::table('tblactivitylog')
            ->where('userid', $clientId)
            ->where('date', '>=', $date90Time)
            ->where(function($q) {
                $q->where('description', 'like', '%Login%')
                  ->orWhere('description', 'like', '%Logged In%')
                  ->orWhere('description', 'like', '%authenticated%');
              })
              ->count();

        // 7. Fetch Downgrades / Partial Cancellations (trailing 12 months)
        $date12MonthsTime = date('Y-m-d H:i:s', strtotime('-365 days'));
        $downgradeCount = Capsule::table('tblcancelrequests')
            ->join('tblhosting', 'tblcancelrequests.relid', '=', 'tblhosting.id')
            ->where('tblhosting.userid', $clientId)
            ->where('tblcancelrequests.date', '>=', $date12MonthsTime)
            ->count();

        // Compute days since last login
        $loginRecencyDays = 999;
        if ($lastLogin) {
            $loginRecencyDays = (int)floor((time() - strtotime($lastLogin)) / 86400);
            if ($loginRecencyDays < 0) {
                $loginRecencyDays = 0;
            }
        }

        // Default telemetry values (Usage Trend)
        $usageTrendAvailable = false;
        $usageTrendValue = 'stable';

        return [
            'payment' => [
                'avg_days_late' => [
                    'available' => !isset($enabledSignals['avg_days_late']) || $enabledSignals['avg_days_late'] === true,
                    'value' => $avgLate,
                ],
                'failed_payment_attempts' => [
                    'available' => !isset($enabledSignals['failed_payment_attempts']) || $enabledSignals['failed_payment_attempts'] === true,
                    'value' => $failedCount,
                ],
                'overdue_invoice_count' => [
                    'available' => !isset($enabledSignals['overdue_invoice_count']) || $enabledSignals['overdue_invoice_count'] === true,
                    'value' => $overdueCount,
                ],
                'refund_or_chargeback' => [
                    'available' => true,
                    'value' => ($refundedCount > 0),
                ],
            ],
            'engagement' => [
                'login_recency_days' => [
                    'available' => !isset($enabledSignals['login_recency_days']) || $enabledSignals['login_recency_days'] === true,
                    'value' => $loginRecencyDays,
                ],
                'login_count_90_days' => [
                    'available' => !isset($enabledSignals['login_count_90_days']) || $enabledSignals['login_count_90_days'] === true,
                    'value' => $loginCount,
                ],
                'downgrade_count_12_months' => [
                    'available' => !isset($enabledSignals['downgrade_count_12_months']) || $enabledSignals['downgrade_count_12_months'] === true,
                    'value' => $downgradeCount,
                ],
                'usage_trend' => [
                    'available' => !isset($enabledSignals['usage_trend']) || $enabledSignals['usage_trend'] === true,
                    'value' => $usageTrendValue,
                ],
            ],
            'metadata' => [
                'datecreated' => $dateCreated,
                'lastlogin'   => $lastLogin,
            ],
        ];
    }
}
