@extends('layouts.app')
@section('title', 'Geschiedenis')

@section('content')
<div x-data="historyApp()" class="space-y-6">

    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Geschiedenis</h1>
            <p class="text-sm text-gray-500 mt-0.5">Laaddata en beslissingen over tijd.</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <span class="text-sm text-gray-500">Periode:</span>
            @foreach([6, 24, 48, 168, 720] as $h)
            <a href="?hours={{ $h }}"
               class="px-3 py-1.5 text-sm rounded-lg border transition-colors
                      {{ ($hours == $h && empty($customFrom)) ? 'bg-green-600 text-white border-green-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50' }}">
                {{ $h < 24 ? $h.'u' : round($h/24).'d' }}
            </a>
            @endforeach

            {{-- Custom datumbereik --}}
            <form method="GET" class="flex items-center gap-1.5 ml-1">
                <input type="date" name="from" value="{{ $customFrom }}" max="{{ now()->format('Y-m-d') }}"
                       class="text-sm border border-gray-300 rounded-lg px-2 py-1.5 bg-white text-gray-700 focus:outline-none focus:ring-1 focus:ring-green-500">
                <span class="text-gray-400 text-sm">—</span>
                <input type="date" name="to" value="{{ $customTo }}" max="{{ now()->format('Y-m-d') }}"
                       class="text-sm border border-gray-300 rounded-lg px-2 py-1.5 bg-white text-gray-700 focus:outline-none focus:ring-1 focus:ring-green-500">
                <button type="submit"
                        class="px-3 py-1.5 text-sm rounded-lg border transition-colors
                               {{ !empty($customFrom) ? 'bg-green-600 text-white border-green-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50' }}">
                    Toepassen
                </button>
            </form>
        </div>
    </div>

    <!-- Geschiedenis widgets -->
    <div x-data="historyWidgets({{ $hours }}, @js($customFrom), @js($customTo))" x-init="load()" class="space-y-4">

        {{-- Laadstatus / foutmelding --}}
        <div x-show="loading" class="text-sm text-gray-400 animate-pulse">Statistieken laden…</div>
        <div x-show="error && !loading" class="text-sm text-red-500" x-text="error"></div>

        {{-- Drie widgets --}}
        <div x-show="!loading && !error" x-cloak class="grid grid-cols-1 sm:grid-cols-3 gap-4">

            {{-- Widget 1: Herkomst (zon vs net balk) --}}
            <div class="card flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">Herkomst</h3>
                    <span class="text-xs text-gray-400" x-text="periodLabel"></span>
                </div>
                <div x-show="data.total_kwh > 0">
                    {{-- Gestapelde balk --}}
                    <div class="flex h-5 rounded-full overflow-hidden bg-gray-100">
                        <div class="bg-yellow-400 transition-all duration-500"
                             :style="'width:' + data.solar_pct + '%'"
                             :title="'Zon: ' + data.solar_pct + '%'">
                        </div>
                        <div class="bg-blue-400 flex-1 transition-all duration-500"
                             :title="'Net: ' + data.grid_pct + '%'">
                        </div>
                    </div>
                    <div class="flex justify-between mt-2 text-xs text-gray-600">
                        <span class="flex items-center gap-1">
                            <span class="inline-block w-2.5 h-2.5 rounded-sm bg-yellow-400"></span>
                            Zon <strong x-text="data.solar_pct + '%'"></strong>
                            <span class="text-gray-400" x-text="'(' + data.solar_kwh + ' kWh)'"></span>
                        </span>
                        <span class="flex items-center gap-1">
                            <span class="inline-block w-2.5 h-2.5 rounded-sm bg-blue-400"></span>
                            Net <strong x-text="data.grid_pct + '%'"></strong>
                            <span class="text-gray-400" x-text="'(' + data.grid_kwh + ' kWh)'"></span>
                        </span>
                    </div>
                </div>
                <p x-show="data.total_kwh === 0" class="text-sm text-gray-400">Geen laaddata in deze periode.</p>
            </div>

            {{-- Widget 2: Kosten --}}
            <div class="card flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">Netkosten</h3>
                    <span class="text-xs text-gray-400" x-text="periodLabel"></span>
                </div>
                <div>
                    <div class="text-3xl font-bold text-gray-900"
                         x-text="'€\u00a0' + data.total_cost_eur.toFixed(2)">
                    </div>
                    <div class="mt-2 space-y-1 text-xs text-gray-500">
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-1.5">
                                <span class="inline-block w-2.5 h-2.5 rounded-sm bg-blue-400"></span>
                                Net
                            </span>
                            <span x-text="data.grid_kwh + ' kWh'"></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-1.5">
                                <span class="inline-block w-2.5 h-2.5 rounded-sm bg-yellow-400"></span>
                                Zon (gratis)
                            </span>
                            <span x-text="data.solar_kwh + ' kWh'"></span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Widget 3: Totaal kWh --}}
            <div class="card flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">Totaal geladen</h3>
                    <span class="text-xs text-gray-400" x-text="periodLabel"></span>
                </div>
                <div>
                    <div class="text-3xl font-bold text-gray-900">
                        <span x-text="data.total_kwh"></span>
                        <span class="text-lg font-normal text-gray-500">kWh</span>
                    </div>
                    <p class="mt-2 text-xs text-gray-400">
                        Werkelijk geladen op basis van Peblar energiemeter
                    </p>
                </div>
            </div>

        </div>
    </div>

    <!-- Charts -->
    <div class="card">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Laadstroom & Vermogen</h3>
        <div class="h-56">
            <canvas id="powerChart"></canvas>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="card">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Stroomprijs</h3>
            <div class="h-40">
                <canvas id="historicPriceChart"></canvas>
            </div>
        </div>
        <div class="card">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Zonne-opbrengst vs Verbruik</h3>
            <div class="h-40">
                <canvas id="solarChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Decisions table -->
    <div class="card" x-data="decisionsTable()">

        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <h3 class="text-sm font-semibold text-gray-700">
                Beslissingen
                <span class="text-gray-400 font-normal ml-1">
                    (<span x-text="filtered.length"></span> van <span x-text="all.length"></span>)
                </span>
            </h3>

            <div class="flex flex-wrap items-center gap-2">
                {{-- Prioriteit-filters --}}
                <div class="flex items-center gap-1">
                    <button @click="setPriority(null)"
                            :class="activePriority === null ? 'bg-gray-700 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="px-2.5 py-1 text-xs rounded-md font-medium transition-colors">
                        Alles
                    </button>
                    <button @click="setPriority('charging')"
                            :class="activePriority === 'charging' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="px-2.5 py-1 text-xs rounded-md font-medium transition-colors">
                        ⚡ Aan het laden
                    </button>
                    <button @click="setPriority(0)"
                            :class="activePriority === 0 ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            class="px-2.5 py-1 text-xs rounded-md font-medium transition-colors">
                        Stop
                    </button>
                </div>

                {{-- Verberg-knop voor 'geen auto' --}}
                <label class="flex items-center gap-1.5 cursor-pointer select-none text-xs text-gray-600">
                    <input type="checkbox" x-model="hideNocar" class="rounded">
                    Verberg 'geen auto'
                </label>
            </div>
        </div>

        @if($decisions->isEmpty())
            <p class="text-sm text-gray-400">Geen beslissingen gevonden in deze periode.</p>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left text-xs font-semibold text-gray-500 pb-2 pr-4">Tijd</th>
                        <th class="text-left text-xs font-semibold text-gray-500 pb-2 pr-4">Prioriteit</th>
                        <th class="text-right text-xs font-semibold text-gray-500 pb-2 pr-4">Stroom</th>
                        <th class="text-right text-xs font-semibold text-gray-500 pb-2 pr-4">Vermogen</th>
                        <th class="text-right text-xs font-semibold text-gray-500 pb-2 pr-4">Prijs</th>
                        <th class="text-left text-xs font-semibold text-gray-500 pb-2">Reden</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <template x-for="d in filtered" :key="d.id">
                        <tr class="hover:bg-gray-50">
                            <td class="py-2 pr-4 text-gray-500 text-xs font-mono whitespace-nowrap" x-text="d.time"></td>
                            <td class="py-2 pr-4">
                                <span :class="d.badge" x-text="d.label"></span>
                            </td>
                            <td class="py-2 pr-4 text-right font-mono" x-text="d.current"></td>
                            <td class="py-2 pr-4 text-right font-mono text-gray-600" x-text="d.power"></td>
                            <td class="py-2 pr-4 text-right font-mono text-gray-600" x-text="d.price"></td>
                            <td class="py-2 text-xs text-gray-500 max-w-xs truncate" :title="d.reason" x-text="d.reason"></td>
                        </tr>
                    </template>
                    <tr x-show="filtered.length === 0">
                        <td colspan="6" class="py-6 text-center text-sm text-gray-400">Geen beslissingen voor dit filter.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        @endif
    </div>

</div>

<script>
const readingsRaw = @json($readings);

document.addEventListener('DOMContentLoaded', () => {
    const readings = readingsRaw;
    const labels = readings.map(r => {
        const d = new Date(r.recorded_at);
        return d.toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' });
    });

    // Power chart
    const powerCtx = document.getElementById('powerChart')?.getContext('2d');
    if (powerCtx) {
        new Chart(powerCtx, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Laadpaal (W)',
                        data: readings.map(r => r.peblar_power_total ?? 0),
                        borderColor: '#16a34a',
                        backgroundColor: 'rgba(22,163,74,0.08)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 0,
                    },
                    {
                        label: 'P1 Verbruik (W)',
                        data: readings.map(r => r.p1_power_consumed ?? 0),
                        borderColor: '#dc2626',
                        backgroundColor: 'transparent',
                        tension: 0.3,
                        pointRadius: 0,
                        borderDash: [4, 2],
                    },
                    {
                        label: 'Laadstroom (× 100 mA)',
                        data: readings.map(r => (r.peblar_charge_current_actual ?? 0) / 10),
                        borderColor: '#2563eb',
                        backgroundColor: 'transparent',
                        tension: 0.3,
                        pointRadius: 0,
                        yAxisID: 'y1',
                    },
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11 } } } },
                scales: {
                    y: { grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 11 } } },
                    y1: { position: 'right', grid: { display: false }, ticks: { font: { size: 10 }, callback: v => (v*10/1000).toFixed(0)+'A' } },
                    x: { grid: { display: false }, ticks: { maxTicksLimit: 12, font: { size: 10 } } },
                }
            }
        });
    }

    // Price chart
    const priceCtx = document.getElementById('historicPriceChart')?.getContext('2d');
    if (priceCtx) {
        new Chart(priceCtx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: '€/kWh',
                    data: readings.map(r => r.price_current),
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245,158,11,0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 0,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { ticks: { callback: v => '€'+v?.toFixed(2), font: { size: 10 } }, grid: { color: 'rgba(0,0,0,0.05)' } },
                    x: { grid: { display: false }, ticks: { maxTicksLimit: 8, font: { size: 10 } } },
                }
            }
        });
    }

    // Solar chart
    const solarCtx = document.getElementById('solarChart')?.getContext('2d');
    if (solarCtx) {
        new Chart(solarCtx, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'PV Opbrengst (W)',
                        data: readings.map(r => r.solax_pv_power ?? 0),
                        borderColor: '#eab308',
                        backgroundColor: 'rgba(234,179,8,0.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 0,
                    },
                    {
                        label: 'P1 Verbruik (W)',
                        data: readings.map(r => r.p1_power_consumed ?? 0),
                        borderColor: '#dc2626',
                        backgroundColor: 'transparent',
                        tension: 0.3,
                        pointRadius: 0,
                    },
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top', labels: { boxWidth: 12, font: { size: 10 } } } },
                scales: {
                    y: { grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 10 } } },
                    x: { grid: { display: false }, ticks: { maxTicksLimit: 8, font: { size: 10 } } },
                }
            }
        });
    }
});

function historyApp() {
    return {};
}

function historyWidgets(hours, customFrom, customTo) {
    const formatLabel = (h) => {
        if (h < 24) return h + ' uur';
        const d = Math.round(h / 24);
        return d === 1 ? '1 dag' : d + ' dagen';
    };

    return {
        hours:      hours,
        customFrom: customFrom || '',
        customTo:   customTo   || '',
        loading:    true,
        error:      null,
        data: {
            solar_kwh: 0, grid_kwh: 0, solar_pct: 0, grid_pct: 0,
            total_cost_eur: 0, total_kwh: 0,
        },

        get periodLabel() {
            if (this.customFrom && this.customTo) {
                return this.customFrom + ' — ' + this.customTo;
            }
            return 'Laatste ' + formatLabel(this.hours);
        },

        get apiUrl() {
            if (this.customFrom && this.customTo) {
                return '/api/history?from=' + this.customFrom + '&to=' + this.customTo;
            }
            return '/api/history?hours=' + this.hours;
        },

        load() {
            this.loading = true;
            this.error   = null;

            fetch(this.apiUrl, {
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
            })
            .then(r => {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(json => {
                this.data    = json;
                this.loading = false;
            })
            .catch(err => {
                this.error   = 'Fout bij laden: ' + err.message;
                this.loading = false;
            });
        },
    };
}

function decisionsTable() {
    const badgeMap = {
        4: 'badge-urgent',
        3: 'badge-high',
        2: 'badge-normal',
        1: 'badge-low',
        0: 'badge-stop',
    };
    const labelMap = {
        4: 'Urgent', 3: 'Hoog', 2: 'Normaal', 1: 'Laag', 0: 'Stop',
    };

    const raw = @json($decisionsJson);

    const all = raw.map(d => ({
        ...d,
        badge:   badgeMap[d.priority] ?? 'badge-stop',
        current: (d.current_ma / 1000).toFixed(1) + 'A',
        power:   d.power_w + 'W',
        price:   d.price ? '€' + parseFloat(d.price).toFixed(3) : '—',
    }));

    return {
        all,
        activePriority: null,   // null = alles, 'charging' = laden, 0 = stop
        hideNocar: true,        // standaard 'geen auto' verborgen

        get filtered() {
            return this.all.filter(d => {
                if (this.hideNocar && d.reason?.toLowerCase().includes('geen auto')) return false;
                if (this.activePriority === null) return true;
                if (this.activePriority === 'charging') return d.current_ma > 0;
                return d.priority === this.activePriority;
            });
        },

        setPriority(p) {
            this.activePriority = this.activePriority === p ? null : p;
        },
    };
}
</script>
@endsection
