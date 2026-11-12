<?php

namespace App\Modules\Ledger\Actions;

use App\Models\User;
use App\Modules\Distribution\Models\Distributor;
use App\Modules\Ledger\Models\Payment;
use App\Support\Actions\Action;

class RecordPayment extends Action
{
    public function handle(Distributor $distributor, array $data, User $user): Payment
    {
        $payment = $distributor->payments()->create($data + ['recorded_by' => $user->id]);
        $this->log('Recorded payment', "{$distributor->name}: ".fmt_rs($payment->amount)." on {$payment->paid_on->format('j M Y')} by ".$payment->method->label()
            .($payment->reference ? ", ref {$payment->reference}" : ''));

        return $payment;
    }
}
