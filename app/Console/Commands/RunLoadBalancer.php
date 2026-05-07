<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('peblar:balance')]
#[Description('Run one load balancer cycle: collect data, decide charge current, send to Peblar')]
class RunLoadBalancer extends Command
{
    public function handle(
        \App\Services\LoadBalancerService $balancer
    ): int {
        $decision = $balancer->run();

        $this->info(sprintf(
            '[%s] Priority=%s | Current=%dA | Power=%dW | Sent=%s | %s',
            now()->format('H:i:s'),
            $decision->priority_label,
            round($decision->charge_current_ma / 1000, 1),
            $decision->desired_power_w,
            $decision->command_sent ? 'ja' : 'nee',
            $decision->reason,
        ));

        return Command::SUCCESS;
    }
}
