<?php

namespace App\Modules\Ledger\Actions;

use App\Modules\Ledger\Models\Payment;
use App\Support\Actions\Action;

class RemovePayment extends Action
{
    public function handle(Payment $payment): void
    {
        $payment->loadMissing('distributor:id,name')->delete();
        $this->log('Removed payment', $payment->distributor->name.': '.fmt_rs($payment->amount).' of '.$payment->paid_on->format('j M Y'));
    }
}
