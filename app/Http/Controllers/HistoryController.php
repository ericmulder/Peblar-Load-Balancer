<?php

namespace App\Http\Controllers;

use App\Models\ChargeDecision;
use App\Models\MeterReading;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index(Request $request)
    {
        $customFrom = null;
        $customTo   = null;
        $hours      = (int) $request->input('hours', 24);

        if ($request->filled('from') && $request->filled('to')) {
            try {
                $from       = Carbon::parse($request->input('from'))->startOfDay();
                $to         = Carbon::parse($request->input('to'))->endOfDay();
                $customFrom = $from->format('Y-m-d');
                $customTo   = $to->format('Y-m-d');
                $hours      = max(1, (int) $from->diffInHours($to));
            } catch (\Throwable $e) {
                $from = now()->subHours($hours);
                $to   = now();
            }
        } else {
            $from = now()->subHours($hours);
            $to   = now();
        }

        $decisions = ChargeDecision::whereBetween('decided_at', [$from, $to])
            ->orderByDesc('decided_at')
            ->limit(500)
            ->get();

        $decisionsJson = $decisions->map(fn($d) => [
            'id'         => $d->id,
            'priority'   => $d->priority,
            'label'      => $d->priority_label,
            'current_ma' => $d->charge_current_ma,
            'power_w'    => $d->desired_power_w,
            'price'      => $d->price_eur,
            'reason'     => $d->reason,
            'time'       => $d->decided_at->setTimezone('Europe/Amsterdam')->format('d/m H:i:s'),
        ])->values();

        $readings = MeterReading::whereBetween('recorded_at', [$from, $to])
            ->orderBy('recorded_at')
            ->get(['recorded_at', 'peblar_power_total', 'p1_power_consumed', 'solax_pv_power', 'price_current', 'peblar_charge_current_actual']);

        return view('history', compact('decisions', 'decisionsJson', 'readings', 'hours', 'customFrom', 'customTo'));
    }
}
