@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div x-data="dashboard()" x-init="init()" class="space-y-6">

    <!-- Header row -->
    <div class="flex items-center justify-between gap-2">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                Laatste update: <span x-text="lastUpdate" class="font-mono"></span>
            </p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <!-- Balancer toggle -->
            <button @click="toggleBalancer()"
                    :class="balancerEnabled ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-400 hover:bg-gray-500'"
                    class="flex items-center gap-1.5 px-3 py-2 text-white text-sm font-medium rounded-lg transition-colors whitespace-nowrap">
                <span x-text="balancerEnabled ? 'Balancer AAN' : 'Balancer UIT'"></span>
            </button>
            <!-- Manual balance trigger -->
            <button @click="runBalance()" title="Nu berekenen"
                    class="flex items-center gap-1.5 px-3 py-2 bg-green-100 text-green-800 text-sm font-medium rounded-lg hover:bg-green-200 transition-colors">
                <svg class="w-4 h-4 shrink-0" :class="running ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <span class="hidden sm:inline">Nu berekenen</span>
            </button>
        </div>
    </div>

    <!-- Status cards row -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

        <!-- Laadpaal -->
        <div class="card">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Laadpaal</h3>
                <span :class="peblarOnline ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                      class="text-xs px-2 py-0.5 rounded-full font-medium"
                      x-text="peblarOnline ? 'Online' : 'Offline'"></span>
            </div>
            <div class="space-y-2">
                <div>
                    <div class="text-3xl font-bold text-gray-900">
                        <span x-text="peblarCurrentA"></span><span class="text-lg text-gray-400 ml-1">A</span>
                    </div>
                    <div class="text-sm text-gray-500">Werkelijke laadstroom</div>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Vermogen</span>
                    <span class="font-medium" x-text="peblarPowerW + ' W'"></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Status</span>
                    <span class="font-medium text-xs" x-text="cpState"></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Limiet</span>
                    <span class="font-medium text-blue-600" x-text="peblarLimitA + 'A'"></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Sessie</span>
                    <span class="font-medium" x-text="sessionKwh + ' kWh'"></span>
                </div>
            </div>
            <!-- Current bars: actual vs limit -->
            <div class="mt-3 space-y-1">
                <div class="flex justify-between text-xs text-gray-400">
                    <span>Huidig</span><span x-text="peblarCurrentA + 'A / 16A'"></span>
                </div>
                <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full bg-green-500 rounded-full transition-all duration-500"
                         :style="'width: ' + (peblarCurrentRaw / 16000 * 100) + '%'"></div>
                </div>
                <div class="flex justify-between text-xs text-gray-400">
                    <span>Limiet</span><span x-text="peblarLimitA + 'A'"></span>
                </div>
                <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full bg-blue-400 rounded-full transition-all duration-500"
                         :style="'width: ' + (peblarCurrentLimit / 16000 * 100) + '%'"></div>
                </div>
            </div>
        </div>

        <!-- P1 Meter -->
        <div class="card">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Thuisverbruik</h3>
                <span :class="p1Online ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                      class="text-xs px-2 py-0.5 rounded-full font-medium"
                      x-text="p1Online ? 'Online' : 'Offline'"></span>
            </div>
            <div class="space-y-2">
                <div>
                    <div class="text-3xl font-bold text-gray-900">
                        <span x-text="householdW"></span><span class="text-lg text-gray-400 ml-1">W</span>
                    </div>
                    <div class="text-sm text-gray-500">Huis excl. auto (net + zon)</div>
                </div>
                <div class="border-t border-gray-100 pt-2 space-y-1.5">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Net</span>
                        <span class="font-medium" :class="p1Net > 0 ? 'text-red-500' : 'text-green-600'"
                              x-text="(p1Net > 0 ? '+' : '') + p1Net + ' W'"></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Zonne-opbrengst</span>
                        <span class="font-medium text-yellow-600" x-text="pvPower + ' W'"></span>
                    </div>
                    <div class="flex justify-between text-sm" x-show="peblarPowerW > 0">
                        <span class="text-gray-500">Auto</span>
                        <span class="font-medium text-blue-600" x-text="peblarPowerW + ' W'"></span>
                    </div>
                    <div class="flex justify-between text-sm" x-show="solarSurplusW > 0">
                        <span class="text-gray-500">☀️ Overschot</span>
                        <span class="font-medium text-green-600" x-text="solarSurplusW + ' W'"></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Spanning L1</span>
                        <span class="font-medium text-gray-400" x-text="voltageL1 + ' V'"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Solax -->
        <div class="card">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Solax Omvormer</h3>
                <span :class="solaxOnline ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                      class="text-xs px-2 py-0.5 rounded-full font-medium"
                      x-text="solaxOnline ? 'Online' : 'Offline'"></span>
            </div>
            <div class="space-y-2">
                <div>
                    <div class="text-3xl font-bold text-yellow-500">
                        <span x-text="pvPower"></span><span class="text-lg text-gray-400 ml-1">W</span>
                    </div>
                    <div class="text-sm text-gray-500">Zonne-opbrengst</div>
                </div>
            </div>
        </div>

        <!-- Stroomprijs -->
        <div class="card">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Stroomprijs</h3>
                <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                      :class="priceClass"
                      x-text="priceLabel"></span>
            </div>
            <div>
                <div class="text-3xl font-bold text-gray-900">
                    <span x-text="currentPrice ? '€' + currentPrice.toFixed(3) : '—'"></span>
                </div>
                <div class="text-sm text-gray-500">per kWh incl. BTW</div>
            </div>
            <div class="mt-4 h-16">
                <canvas id="miniPriceChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Voertuig status -->
    @if(app(\App\Services\HyundaiService::class)->isConfigured())
    <div class="card" x-data>
        <div class="flex flex-wrap items-center justify-between gap-y-2 mb-4">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v9a2 2 0 01-2 2h-1m-9 0a2 2 0 11-4 0 2 2 0 014 0zm10 0a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <h3 class="text-sm font-semibold text-gray-700">Voertuig — IONIQ 5</h3>
                <!-- Polling-status badge -->
                <span x-show="vehicle && vehicle.is_plugged_in && vehicle.is_charging"
                      class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-600 font-medium whitespace-nowrap">
                    ↻ elke 5 min
                </span>
                <span x-show="vehicle && vehicle.is_plugged_in && !vehicle.is_charging && vehicle.polling_suspended"
                      class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-medium whitespace-nowrap">
                    ✓ Laden klaar
                </span>
            </div>
            <!-- Force refresh knop -->
            <button @click="vehicleRefresh()"
                    :disabled="vehicleRefreshing"
                    class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg transition-colors
                           bg-gray-100 hover:bg-gray-200 text-gray-600 disabled:opacity-50 shrink-0">
                <svg class="w-3.5 h-3.5" :class="vehicleRefreshing ? 'animate-spin' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <span x-text="vehicleRefreshing ? 'Ophalen…' : 'Handmatig vernieuwen'"></span>
            </button>
        </div>
        <div x-show="vehicleRefreshError" x-cloak
             class="mt-2 text-xs text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2"
             x-text="vehicleRefreshError"></div>

        <template x-if="vehicle">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <!-- SoC -->
                <div class="sm:col-span-1">
                    <div class="flex items-end gap-1 mb-1">
                        <span class="text-3xl font-bold text-gray-900" x-text="vehicle.soc"></span>
                        <span class="text-lg text-gray-400 mb-0.5">%</span>
                    </div>
                    <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-700"
                             :class="vehicle.soc > 60 ? 'bg-green-500' : vehicle.soc > 30 ? 'bg-yellow-500' : 'bg-red-500'"
                             :style="'width: ' + vehicle.soc + '%'"></div>
                    </div>
                    <div class="text-xs text-gray-400 mt-1">Laadniveau auto</div>
                </div>

                <!-- Status -->
                <div class="flex flex-col justify-center">
                    <span class="inline-flex items-center gap-1.5 text-sm font-medium"
                          :class="{
                              'text-green-600': vehicle.is_charging,
                              'text-blue-600': vehicle.is_plugged_in && !vehicle.is_charging,
                              'text-gray-400': !vehicle.is_plugged_in
                          }">
                        <span class="w-2 h-2 rounded-full"
                              :class="{
                                  'bg-green-500 animate-pulse': vehicle.is_charging,
                                  'bg-blue-400': vehicle.is_plugged_in && !vehicle.is_charging,
                                  'bg-gray-300': !vehicle.is_plugged_in
                              }"></span>
                        <span x-text="vehicle.is_charging ? 'Aan het laden' : vehicle.is_plugged_in ? 'Ingeplugd' : 'Niet aangesloten'"></span>
                    </span>
                    <template x-if="vehicle.is_charging && vehicle.minutes_to_full > 0">
                        <div class="text-xs text-gray-400 mt-1"
                             x-text="Math.floor(vehicle.minutes_to_full / 60) + 'u ' + (vehicle.minutes_to_full % 60) + 'min tot vol'">
                        </div>
                    </template>
                </div>

                <!-- Bereik -->
                <div class="flex flex-col justify-center">
                    <div class="text-2xl font-bold text-gray-900">
                        <span x-text="vehicle.range_km ?? '—'"></span>
                        <span class="text-base text-gray-400 ml-0.5">km</span>
                    </div>
                    <div class="text-xs text-gray-400">Geschat bereik</div>
                </div>

                <!-- Tijdstippen: auto-sync + fetch -->
                <div class="flex flex-col justify-center gap-1.5">
                    <!-- Wanneer de auto zelf heeft gesynchroniseerd met Hyundai cloud -->
                    <div>
                        <div class="text-xs text-gray-500">Auto gesynchroniseerd</div>
                        <div class="text-sm font-medium mt-0.5"
                             :class="vehicle.last_updated_at ? 'text-gray-700' : 'text-amber-500'"
                             x-text="vehicle.last_updated_at ? new Date(vehicle.last_updated_at).toLocaleTimeString('nl-NL', {hour:'2-digit',minute:'2-digit'}) : 'Onbekend'">
                        </div>
                        <div class="text-xs text-gray-400"
                             x-text="vehicle.last_updated_at ? new Date(vehicle.last_updated_at).toLocaleDateString('nl-NL', {day:'numeric',month:'short'}) : ''">
                        </div>
                    </div>
                    <!-- Wanneer wij de data voor het laatst hebben opgehaald -->
                    <div>
                        <div class="text-xs text-gray-400">Opgehaald om</div>
                        <div class="text-xs text-gray-400"
                             x-text="vehicle.last_fetched_at ? new Date(vehicle.last_fetched_at).toLocaleTimeString('nl-NL', {hour:'2-digit',minute:'2-digit'}) : '—'">
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <template x-if="!vehicle">
            <div class="flex items-center gap-3 text-sm text-gray-400 py-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Nog geen voertuigdata — druk op Vernieuwen om de status op te halen.
            </div>
        </template>
    </div>
    @endif

    <!-- Decision + Manual override -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        <!-- Last decision -->
        <div class="card lg:col-span-2">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Laatste beslissing</h3>
            <template x-if="decision">
                <div class="space-y-3">
                    <div class="flex items-center gap-3">
                        <span :class="priorityBadgeClass" x-text="decision.priority_label"></span>
                        <span class="text-sm text-gray-600" x-text="decision.reason"></span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="bg-gray-50 rounded-lg p-3">
                            <div class="text-xs text-gray-500">Gewenst vermogen</div>
                            <div class="font-bold text-gray-800" x-text="decision.desired_power_w + ' W'"></div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <div class="text-xs text-gray-500">Ingesteld</div>
                            <div class="font-bold text-gray-800" x-text="(decision.charge_current_ma/1000).toFixed(1) + ' A'"></div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <div class="text-xs text-gray-500">Thuisverbruik</div>
                            <div class="font-bold text-gray-800" x-text="(decision.household_consumption_w ?? '—') + (decision.household_consumption_w ? ' W' : '')"></div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <div class="text-xs text-gray-500">Zonne-overschot</div>
                            <div class="font-bold text-yellow-600" x-text="(decision.solar_surplus_w ?? '—') + (decision.solar_surplus_w != null ? ' W' : '')"></div>
                        </div>
                    </div>
                    <div class="text-xs text-gray-400" x-text="'Berekend om ' + formatTime(decision.decided_at)"></div>
                </div>
            </template>
            <template x-if="!decision">
                <p class="text-sm text-gray-400">Nog geen beslissingen. Start de load balancer.</p>
            </template>
        </div>

        <!-- Manual override -->
        <div class="card space-y-4">

            {{-- Force-charge: actief --}}
            <template x-if="forceChargeActive">
                <div class="bg-green-50 border border-green-200 rounded-xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-green-800">⚡ Nu laden actief</span>
                        <button @click="stopForceCharge()"
                                class="text-xs text-red-600 hover:text-red-800 font-medium">
                            Stoppen
                        </button>
                    </div>
                    <p class="text-xs text-green-700">
                        Laden op vol vermogen tot
                        <strong x-text="forceChargeTarget + '%'"></strong>.
                        Balancer hervat daarna automatisch.
                    </p>
                </div>
            </template>

            {{-- Force-charge: inactief --}}
            <template x-if="!forceChargeActive">
                <div class="space-y-2">
                    <h3 class="text-sm font-semibold text-gray-700">Nu laden tot</h3>
                    <div class="flex items-center gap-2">
                        <input type="number" x-model="forceChargeTarget" min="1" max="100" step="1"
                               class="w-20 border border-gray-200 rounded-lg px-2 py-1.5 text-sm text-center font-bold focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        <span class="text-sm text-gray-500">%</span>
                        <button @click="startForceCharge()"
                                class="flex-1 py-1.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
                            ⚡ Start
                        </button>
                    </div>
                    <p class="text-xs text-gray-400">Laadt op vol netcapaciteit, negeert prijs en schema.</p>
                </div>
            </template>

            <hr class="border-gray-100">

            {{-- Fijn handmatig instellen --}}
            <div class="space-y-3">
                <h3 class="text-sm font-semibold text-gray-700">Handmatig instellen <span class="font-normal text-gray-400">(30 min)</span></h3>
                {{-- Fase selector --}}
                @if((int)\App\Models\Setting::get('phase_count', 3) >= 3)
                <div class="flex rounded-lg overflow-hidden border border-gray-200 text-sm font-medium">
                    <button @click="overridePhases = 1"
                            :class="overridePhases === 1 ? 'bg-green-600 text-white' : 'bg-gray-50 text-gray-600 hover:bg-gray-100'"
                            class="flex-1 py-1.5 transition-colors">1-fase</button>
                    <button @click="overridePhases = 3"
                            :class="overridePhases === 3 ? 'bg-green-600 text-white' : 'bg-gray-50 text-gray-600 hover:bg-gray-100'"
                            class="flex-1 py-1.5 transition-colors">3-fase</button>
                </div>
                @endif
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs text-gray-500">Laadstroom</label>
                        <span class="text-sm font-bold text-green-700"
                              x-text="overrideCurrent + ' A · ' + Math.round(overrideCurrent * overridePhases * 230) + ' W'"></span>
                    </div>
                    <input type="range" x-model="overrideCurrent" min="6" :max="maxCurrentA" step="1"
                           class="w-full accent-green-600">
                    <div class="flex justify-between text-xs text-gray-400 mt-1">
                        <span>6 A</span>
                        <span x-text="maxCurrentA + ' A'"></span>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <button @click="sendOverrideStop()"
                            class="py-2 bg-red-100 hover:bg-red-200 text-red-700 text-sm font-medium rounded-lg transition-colors">
                        ✕ Stop laden
                    </button>
                    <button @click="sendOverride()"
                            class="py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
                        Instellen
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- Laaddoel widget -->
    <div class="card">
        @if($activeGoal)
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-700">Laaddoel actief</h3>
                <a href="{{ route('strategy') }}" class="text-xs text-green-600 hover:underline">Details →</a>
            </div>
            <div class="flex items-center gap-4 mb-3">
                <div class="flex-1">
                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                        <span class="font-semibold text-gray-700">{{ $autoSoc ?? $activeGoal->current_soc }}%
                            <span class="font-normal text-gray-400">(start {{ $activeGoal->current_soc }}%)</span>
                        </span>
                        <span>{{ round($activeGoal->energy_added_kwh, 1) }} / {{ $activeGoal->energy_needed_kwh }} kWh</span>
                        <span>{{ $activeGoal->target_soc }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        <div class="bg-green-500 h-3 rounded-full" style="width: {{ $activeGoal->progressPercent() }}%"></div>
                    </div>
                </div>
            </div>
            <div class="flex justify-between text-xs text-gray-500">
                <span>Vertrek: <strong>{{ $activeGoal->depart_at->setTimezone('Europe/Amsterdam')->format('D d M H:i') }}</strong></span>
                <span>Nog {{ round($activeGoal->hoursUntilDepart(), 1) }}u</span>
            </div>
        @else
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-700">Laaddoel instellen</h3>
                <a href="{{ route('strategy') }}" class="text-xs text-green-600 hover:underline">Uitgebreid →</a>
            </div>
            <form method="POST" action="{{ route('goal.store') }}" class="grid grid-cols-2 sm:grid-cols-3 gap-3 items-end">
                @csrf
                <div class="col-span-2 sm:col-span-1">
                    <label class="text-xs text-gray-500 block mb-1">Vertrek</label>
                    <input type="datetime-local" name="depart_at" required
                           min="{{ now('Europe/Amsterdam')->addMinutes(30)->format('Y-m-d\TH:i') }}"
                           value="{{ now('Europe/Amsterdam')->addDays(1)->setTime(8,0)->format('Y-m-d\TH:i') }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="text-xs text-gray-500 block mb-1">
                        Huidig SoC %
                        @if($autoSoc !== null)
                            <span class="text-green-600 font-medium">← auto</span>
                        @endif
                    </label>
                    <input type="number" name="current_soc" min="0" max="100"
                           value="{{ $autoSoc ?? 20 }}"
                           {{ $autoSoc !== null ? '' : 'required' }}
                           class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500
                                  {{ $autoSoc !== null ? 'border-green-400' : 'border-gray-300' }}">
                </div>
                <div>
                    <label class="text-xs text-gray-500 block mb-1">Doel SoC %</label>
                    <input type="number" name="target_soc" min="1" max="100" value="{{ $defaultTarget }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div class="col-span-2 sm:col-span-3">
                    <button type="submit"
                            class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2 rounded-lg text-sm">
                        Plan berekenen
                    </button>
                </div>
            </form>
        @endif
    </div>

    <!-- Price forecast chart -->
    @if(count($forecast) > 0)
    <div class="card">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Stroomprijs komende 24 uur</h3>
        <div class="h-48">
            <canvas id="priceChart"></canvas>
        </div>
    </div>
    @endif

</div>
@endsection

@push('scripts')
<script>
const forecastData = @json($forecast);
const lastDecisionData = @json($lastDecision);
const latestReading = @json($latest);

function dashboard() {
    return {
        lastUpdate: '—',
        running: false,
        balancerEnabled: {{ \App\Models\Setting::get('balancer_enabled', true) ? 'true' : 'false' }},

        // Peblar
        peblarOnline: false,
        peblarCurrentA: 0,      // actual current flowing (from meter)
        peblarCurrentRaw: 0,    // actual current in mA
        peblarCurrentLimit: 0,  // configured limit in mA
        peblarLimitA: 0,        // configured limit in A
        peblarPowerW: 0,
        cpState: '—',
        sessionKwh: 0,

        // P1
        p1Online: false,
        p1Consumed: 0,
        p1Produced: 0,
        p1Net: 0,
        voltageL1: 0,

        // Solax
        solaxOnline: false,
        pvPower: 0,
        batterySoc: 0,
        batteryPower: 0,

        // Price
        currentPrice: null,

        // Decision
        decision: lastDecisionData,

        // Manual
        maxCurrentA: {{ \App\Models\Setting::get('max_charge_current_a', 13) }},
        overrideCurrent: 6,
        overridePhases: {{ (int)\App\Models\Setting::get('phase_count', 3) }},
        forceChargeActive: {{ \App\Models\Setting::get('force_charge_active', false) ? 'true' : 'false' }},
        forceChargeTarget: {{ (int)\App\Models\Setting::get('force_charge_target_soc', 100) }},

        // Voertuig
        vehicle: null,
        vehicleRefreshing: false,
        vehicleRefreshError: null,

        // Totaal huishoudverbruik = netto netopname + zonne-opbrengst - batterijlading
        // (zelfde formule als LoadBalancerService, exclusief EV-lader)
        get householdW() {
            const gridNet = this.p1Consumed - this.p1Produced;
            const total   = gridNet + this.pvPower - Math.max(0, this.batteryPower);
            return Math.max(0, Math.round(total - this.peblarPowerW));
        },

        // Zonne-overschot uit de laatste balancer-beslissing (P1 + Peblar + Solax zelfde moment).
        // Live herberekening mengt stale P1-data met live Peblar en geeft verkeerde waarden.
        get solarSurplusW() {
            return this.decision?.solar_surplus_w ?? 0;
        },

        get priceClass() {
            if (!this.currentPrice) return 'bg-gray-100 text-gray-500';
            if (this.currentPrice <= 0.10) return 'bg-green-100 text-green-700';
            if (this.currentPrice <= 0.20) return 'bg-yellow-100 text-yellow-700';
            return 'bg-red-100 text-red-700';
        },
        get priceLabel() {
            if (!this.currentPrice) return 'Onbekend';
            if (this.currentPrice <= 0.10) return 'Laag';
            if (this.currentPrice <= 0.20) return 'Normaal';
            return 'Hoog';
        },
        get priorityBadgeClass() {
            if (!this.decision) return '';
            const map = { 4: 'badge-urgent', 3: 'badge-high', 2: 'badge-normal', 1: 'badge-low', 0: 'badge-stop' };
            return map[this.decision.priority] ?? 'badge-stop';
        },

        init() {
            this.initCharts();
            this.poll();
            setInterval(() => this.poll(), 10000);
        },

        poll() {
            fetch('{{ route('api.status') }}')
                .then(r => r.json())
                .then(data => this.applyStatus(data))
                .catch(() => {});
        },

        applyStatus(data) {
            this.lastUpdate = new Date().toLocaleTimeString('nl-NL');

            // Peblar — actual current from meter, limit from evinterface
            const meter = data.peblar?.meter;
            const evi   = data.peblar?.evinterface;
            this.peblarOnline = data.peblar?.online ?? false;
            // Actual current: highest of L1/L2/L3 from meter (in mA)
            const actualMa = Math.max(
                meter?.CurrentPhase1 ?? 0,
                meter?.CurrentPhase2 ?? 0,
                meter?.CurrentPhase3 ?? 0,
            );
            this.peblarCurrentRaw = actualMa;
            this.peblarCurrentA = (actualMa / 1000).toFixed(1);
            this.peblarCurrentLimit = evi?.ChargeCurrentLimitActual ?? 0;
            this.peblarLimitA = (this.peblarCurrentLimit / 1000).toFixed(0);
            this.peblarPowerW = meter?.PowerTotal ?? 0;
            this.cpState = evi?.CpState ?? '—';
            this.sessionKwh = meter ? (meter.EnergySession / 1000).toFixed(2) : 0;

            // P1
            this.p1Online = data.p1?.online ?? false;
            this.p1Consumed = data.p1?.power_consumed_w ?? 0;
            this.p1Produced = data.p1?.power_produced_w ?? 0;
            this.p1Net = this.p1Consumed - this.p1Produced;
            this.voltageL1 = data.p1?.voltage_l1?.toFixed(1) ?? 0;

            // Solax
            this.solaxOnline = data.solax?.online ?? false;
            this.pvPower = data.solax?.pv_power_w ?? 0;
            this.batterySoc = data.solax?.battery_soc ?? 0;
            this.batteryPower = data.solax?.battery_power_w ?? 0;

            // Price
            this.currentPrice = data.price;

            // Decision
            if (data.decision) this.decision = data.decision;

            // Balancer aan/uit
            if (data.balancer_enabled !== undefined) {
                this.balancerEnabled = data.balancer_enabled;
            }

            // Force-charge status syncen vanuit balancer (bijv. auto-stop na SoC bereikt)
            if (data.force_charge) {
                this.forceChargeActive = data.force_charge.active;
                this.forceChargeTarget = data.force_charge.target_soc;
            }

            // Voertuig — update alleen als de poll nieuwe data bevat (is_plugged_in stelt auto-poll in)
            if (data.vehicle) {
                this.vehicle = data.vehicle;
            }
        },

        async vehicleRefresh() {
            this.vehicleRefreshing = true;
            this.vehicleRefreshError = null;
            try {
                const r = await fetch('{{ route('api.vehicle.refresh') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                });
                const d = await r.json();
                if (d.error) {
                    this.vehicleRefreshError = d.error;
                    setTimeout(() => { this.vehicleRefreshError = null; }, 6000);
                } else {
                    this.vehicle = {
                        soc:             d.soc,
                        is_charging:     d.is_charging,
                        is_plugged_in:   d.is_plugged_in,
                        range_km:        d.range_km,
                        minutes_to_full: d.minutes_to_full,
                        last_fetched_at: d.last_fetched_at,
                    };
                }
            } catch (e) {
                this.vehicleRefreshError = 'Verbindingsfout — probeer opnieuw';
                setTimeout(() => { this.vehicleRefreshError = null; }, 6000);
            } finally {
                this.vehicleRefreshing = false;
            }
        },

        async runBalance() {
            this.running = true;
            try {
                const r = await fetch('{{ route('api.balance') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                });
                const d = await r.json();
                this.decision = d;
                this.poll();
            } finally {
                this.running = false;
            }
        },

        async toggleBalancer() {
            const r = await fetch('{{ route('api.toggle') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
            });
            const d = await r.json();
            this.balancerEnabled = d.enabled;
        },

        async sendOverride() {
            await fetch('{{ route('api.override') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ current_a: this.overrideCurrent, phases: this.overridePhases }),
            });
            this.poll();
        },

        async startForceCharge() {
            const r = await fetch('{{ route('api.force-charge') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ target_soc: parseInt(this.forceChargeTarget) }),
            });
            const d = await r.json();
            this.forceChargeActive = d.active;
            this.poll();
        },

        async stopForceCharge() {
            const r = await fetch('{{ route('api.force-charge.stop') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });
            const d = await r.json();
            this.forceChargeActive = d.active;
            this.poll();
        },

        async sendOverrideStop() {
            await fetch('{{ route('api.override') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ current_a: 0, phases: this.overridePhases }),
            });
            this.poll();
        },

        formatTime(ts) {
            if (!ts) return '—';
            return new Date(ts).toLocaleTimeString('nl-NL');
        },

        initCharts() {
            // Destroy existing instances first (voorkomt "Canvas already in use" error)
            Chart.getChart('miniPriceChart')?.destroy();
            Chart.getChart('priceChart')?.destroy();

            // Mini price chart in card
            const miniCtx = document.getElementById('miniPriceChart')?.getContext('2d');
            if (miniCtx && forecastData.length) {
                new Chart(miniCtx, {
                    type: 'line',
                    data: {
                        labels: forecastData.map(d => new Date(d.hour).toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' })),
                        datasets: [{
                            data: forecastData.map(d => d.price_eur_incl_tax),
                            borderColor: '#16a34a',
                            borderWidth: 1.5,
                            pointRadius: 0,
                            fill: true,
                            backgroundColor: 'rgba(22,163,74,0.1)',
                            tension: 0.4,
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { display: false }, y: { display: false } } }
                });
            }

            // Full price chart
            const priceCtx = document.getElementById('priceChart')?.getContext('2d');
            if (priceCtx && forecastData.length) {
                const colors = forecastData.map(d => {
                    const p = d.price_eur_incl_tax;
                    if (p <= 0.10) return 'rgba(22,163,74,0.8)';
                    if (p <= 0.20) return 'rgba(234,179,8,0.8)';
                    return 'rgba(239,68,68,0.8)';
                });

                new Chart(priceCtx, {
                    type: 'bar',
                    data: {
                        labels: forecastData.map(d => {
                            const h = new Date(d.hour);
                            return h.toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' });
                        }),
                        datasets: [{
                            label: '€/kWh',
                            data: forecastData.map(d => d.price_eur_incl_tax),
                            backgroundColor: colors,
                            borderRadius: 4,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: ctx => '€' + ctx.raw.toFixed(4) + '/kWh'
                                }
                            }
                        },
                        scales: {
                            y: {
                                ticks: { callback: v => '€' + v.toFixed(2) },
                                grid: { color: 'rgba(0,0,0,0.05)' }
                            },
                            x: { grid: { display: false } }
                        }
                    }
                });
            }
        },
    }
}
</script>
@endpush
