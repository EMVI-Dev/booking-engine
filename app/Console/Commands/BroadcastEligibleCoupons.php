<?php

namespace App\Console\Commands;

use App\Models\Operator;
use App\Models\PlatformAnnouncement;
use App\Models\PlatformCoupon;
use Illuminate\Console\Command;

class BroadcastEligibleCoupons extends Command
{
    /**
     * Evaluate all broadcast-eligible coupons and create dashboard announcements
     * for operators who have just crossed the eligibility threshold.
     *
     * Safe to run daily — idempotent per billing cycle.
     */
    protected $signature = 'coupons:broadcast {--dry-run : List eligible operators without creating announcements}';

    protected $description = 'Broadcast platform subscription coupon codes to operators who meet the eligibility rules';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $cycle = now()->format('Y-m');

        $coupons = PlatformCoupon::forSubscription()
            ->active()
            ->withEligibilityRule()
            ->with('announcement')
            ->get();

        if ($coupons->isEmpty()) {
            $this->info('No broadcast-eligible coupons found.');

            return self::SUCCESS;
        }

        $operators = Operator::all();
        $notifiedCount = 0;

        foreach ($coupons as $coupon) {
            $this->line("\n<comment>Coupon: {$coupon->code} ({$coupon->redemption_scope})</comment>");

            foreach ($operators as $operator) {
                // Skip if operator is already specifically targeted by a different coupon operator_id
                if ($coupon->operator_id !== null && $coupon->operator_id !== $operator->id) {
                    continue;
                }

                // Skip if operator already received a broadcast notification this cycle
                $alreadyNotifiedThisCycle = $coupon->redemptions()
                    ->where('operator_id', $operator->id)
                    ->where('billing_cycle', $cycle)
                    ->exists();

                if ($alreadyNotifiedThisCycle) {
                    continue;
                }

                if (! $coupon->isEligibleFor($operator)) {
                    continue;
                }

                // Operator qualifies!
                $this->line("  ✓ Eligible: {$operator->name}");

                if ($isDryRun) {
                    $notifiedCount++;

                    continue;
                }

                // Create an operator-targeted announcement
                $discountLabel = $coupon->discount_type === 'percentage'
                    ? "{$coupon->discount_value}% OFF"
                    : 'Rp '.number_format((float) $coupon->discount_value, 0, ',', '.').' OFF';

                PlatformAnnouncement::create([
                    'title' => "🎉 Exclusive promo code for you: {$coupon->code}",
                    'message' => "You've unlocked a special subscription discount! Use code **{$coupon->code}** to get **{$discountLabel}** on your next plan checkout or renewal.".($coupon->expires_at ? " Valid until {$coupon->expires_at->format('d M Y')}." : ''),
                    'type' => 'success',
                    'is_active' => true,
                    'is_dismissible' => true,
                    'target_plan_id' => $operator->plan_id,
                    'starts_at' => now(),
                    'ends_at' => $coupon->expires_at,
                ]);

                $notifiedCount++;
            }
        }

        $verb = $isDryRun ? 'Would notify' : 'Notified';
        $this->info("\n{$verb} {$notifiedCount} operator(s) across ".$coupons->count().' coupon(s).');

        return self::SUCCESS;
    }
}
