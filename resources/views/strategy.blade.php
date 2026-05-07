@extends('layouts.app')

@section('title', 'Laadstrategie')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-bold text-white">Laadstrategie</h1>
        <p class="text-gray-400 text-sm mt-1">Plan wanneer en hoe de auto geladen wordt op basis van prijs en zonne-overschot.</p>
    </div>

    {{-- Laaddoel instellen --}}
    <div class="bg-gray-800 rounded-xl p-6">
        <h2 class="text-lg font-semibold text-white mb-4">
            {{ $summary['has_goal'] ? 'Actief laaddoel' : 'Nieuw laaddoel instellen' }}
        </h2>

        @if($summary['has_goal'])
            @php $goal = $summary['goal']; @endphp

            {{-- Progress --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-gray-700 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-green-400">{{ $summary['progress_pct'] }}%</div>
                    <div class="text-xs text-gray-400 mt-1">Voortgang</div>
                </div>
                <div class="bg-gray-700 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-white">{{ round($summary['remaining_kwh'], 1) }} kWh</div>
                    <div class="text-xs text-gray-400 mt-1">Nog te laden</div>
                </div>
                <div class="bg-gray-700 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-blue-400">{{ $summary['hours_until_dep'] }}u</div>
                    <div class="text-xs text-gray-400 mt-1">Tot vertrek</div>
                </div>
                <div class="bg-gray-700 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-yellow-400">€{{ number_format($summary['estimated_cost'], 2) }}</div>
                    <div class="text-xs text-gray-400 mt-1">Geschatte kosten</div>
                </div>
            </div>

            {{-- Progress bar --}}
            <div class="mb-6">
                <div class="flex justify-between text-xs text-gray-400 mb-1">
                    <span>
                        <span class="text-white font-semibold">{{ $autoSoc ?? $goal->current_soc }}%</span>
                        <span class="text-gray-500"> (start {{ $goal->current_soc }}%)</span>
                    </span>
                    <span>{{ round($goal->energy_added_kwh, 1) }} / {{ $goal->energy_needed_kwh }} kWh geladen</span>
                    <span>{{ $goal->target_soc }}% doel</span>
                </div>
                <div class="w-full bg-gray-600 rounded-full h-4">
                    <div class="bg-green-500 h-4 rounded-full transition-all"
                         style="width: {{ $summary['progress_pct'] }}%"></div>
                </div>
            </div>

            <div class="flex items-center justify-between text-sm text-gray-300 mb-4">
                <span>Vertrek: <strong class="text-white">{{ $goal->depart_at->setTimezone('Europe/Amsterdam')->format('D d M H:i') }}</strong></span>
                <form method="POST" action="{{ route('goal.destroy', $goal) }}">
                    @csrf @method('DELETE')
                    <button class="text-red-400 hover:text-red-300 text-xs">Laaddoel annuleren</button>
                </form>
            </div>
        @else
            <p class="text-gray-400 text-sm mb-4">Geen actief laaddoel. Stel in wanneer je wilt vertrekken en hoe vol de auto moet zijn.</p>
        @endif

        {{-- Formulier (altijd zichtbaar om aan te passen) --}}
        @if(!$summary['has_goal'])
        <form method="POST" action="{{ route('goal.store') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @csrf
            <div>
                <label class="block text-xs text-gray-400 mb-1">Vertrektijd</label>
                <input type="datetime-local" name="depart_at" required
                       min="{{ now('Europe/Amsterdam')->addMinutes(30)->format('Y-m-d\TH:i') }}"
                       value="{{ now('Europe/Amsterdam')->addDays(1)->setTime(8,0)->format('Y-m-d\TH:i') }}"
                       class="w-full bg-gray-700 text-white rounded-lg px-3 py-2 text-sm border border-gray-600 focus:border-green-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">
                    Huidige SoC (%)
                    @if($autoSoc !== null)
                        <span class="text-green-400 ml-1">
                            @if($vehicleData && !empty($vehicleData['vehicle_name']))
                                — {{ $vehicleData['vehicle_name'] }}
                            @endif
                            ← automatisch
                        </span>
                    @endif
                </label>
                <input type="number" name="current_soc" min="0" max="100"
                       {{ $vehicleData ? '' : 'required' }}
                       value="{{ $autoSoc ?? 20 }}"
                       placeholder="{{ $vehicleData ? 'Auto-ingevuld via BlueLink' : '' }}"
                       class="w-full bg-gray-700 text-white rounded-lg px-3 py-2 text-sm border border-gray-600 focus:border-green-500 focus:outline-none
                              {{ $autoSoc !== null ? 'border-green-600' : '' }}">
                @if($vehicleData)
                    <p class="text-xs text-gray-500 mt-1">
                        @if($vehicleData['range_km']) Bereik: {{ $vehicleData['range_km'] }}km · @endif
                        @if($vehicleData['is_charging']) ⚡ Aan het laden
                        @elseif($vehicleData['is_plugged_in']) 🔌 Ingeplugd
                        @else 🚗 Niet aangesloten @endif
                        · <span title="{{ $vehicleData['recorded_at'] }}">{{ \Carbon\Carbon::parse($vehicleData['recorded_at'])->setTimezone('Europe/Amsterdam')->isoFormat('D MMM HH:mm') }}</span>
                    </p>
                @endif
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">Doel SoC (%) — batterij {{ $batteryCapacity }} kWh</label>
                <input type="number" name="target_soc" min="1" max="100" required
                       value="{{ $defaultTarget }}"
                       class="w-full bg-gray-700 text-white rounded-lg px-3 py-2 text-sm border border-gray-600 focus:border-green-500 focus:outline-none">
            </div>
            <div class="md:col-span-3">
                <button type="submit"
                        class="bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-2 rounded-lg text-sm">
                    Plan berekenen
                </button>
            </div>
        </form>
        @endif
    </div>

    {{-- Hoe werkt de strategie — altijd zichtbaar --}}
    <div class="bg-gray-800 rounded-xl p-6" x-data="{ open: false }">
        <button @click="open = !open" class="flex items-center justify-between w-full text-left group">
            <h2 class="text-lg font-semibold text-white group-hover:text-green-400 transition-colors">
                Hoe werkt de laadstrategie?
            </h2>
            <svg x-show="!open" class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
            <svg x-show="open" x-cloak class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
            </svg>
        </button>

        <div x-show="open" x-cloak class="mt-5 space-y-6 text-sm text-gray-300">

            {{-- Stap 1: Energiebehoefte --}}
            <div class="flex gap-4">
                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-green-600 text-white flex items-center justify-center font-bold text-sm">1</div>
                <div>
                    <div class="font-semibold text-white mb-1">Energiebehoefte berekenen</div>
                    <p class="text-gray-400 leading-relaxed">
                        Op basis van de huidige SoC en het gewenste doel berekent het systeem hoeveel kWh er nog in moet.
                        De batterij is {{ $batteryCapacity }} kWh groot. Bij max laadvermogen van 9 kW (13A × 3 fases) wordt
                        bepaald hoeveel uren er minimaal nodig zijn.
                    </p>
                    <div class="mt-2 bg-gray-700 rounded-lg px-4 py-3 text-xs text-gray-300 font-mono space-y-0.5">
                        <div>Voorbeeld: 40% → 80% in {{ $batteryCapacity }} kWh batterij</div>
                        <div>= 40% × {{ $batteryCapacity }} kWh = <span class="text-green-400">{{ round(0.4 * $batteryCapacity, 0) }} kWh</span> te laden</div>
                        <div>= ceil({{ round(0.4 * $batteryCapacity, 0) }} / 9) = <span class="text-green-400">{{ ceil(0.4 * $batteryCapacity / 9) }} laaduren</span> minimaal nodig</div>
                    </div>
                </div>
            </div>

            {{-- Stap 2: Uren selecteren --}}
            <div class="flex gap-4">
                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-yellow-500 text-gray-900 flex items-center justify-center font-bold text-sm">2</div>
                <div>
                    <div class="font-semibold text-white mb-1">Goedkoopste uren selecteren</div>
                    <p class="text-gray-400 leading-relaxed">
                        Alle uren tussen nu en het vertrektijdstip worden gerangschikt op stroomprijs (day-ahead tarieven via Zonneplan).
                        Alleen de goedkoopste N uren worden als laadmomenten aangemerkt. Uren onder de <strong class="text-white">{{ $priceThresholdCt }} ct/kWh</strong>
                        worden altijd meegenomen, ook als dat technisch meer uren zijn dan strikt nodig.
                    </p>
                    <div class="mt-2 bg-gray-700 rounded-lg px-4 py-3 text-xs text-gray-300 space-y-1">
                        <div class="grid grid-cols-5 gap-1 text-center font-mono">
                            @foreach([['02:00','8ct','✅ laden'], ['06:00','12ct','✅ laden'], ['14:00','18ct','⏭ skip'], ['19:00','32ct','⏭ skip'], ['23:00','9ct','✅ laden']] as [$t,$p,$a])
                            <div class="bg-gray-600 rounded p-1.5">
                                <div class="text-gray-400">{{ $t }}</div>
                                <div class="{{ str_starts_with($a,'✅') ? 'text-green-400' : 'text-gray-500' }} font-bold">{{ $p }}</div>
                                <div class="text-gray-400 text-[10px]">{{ $a }}</div>
                            </div>
                            @endforeach
                        </div>
                        <div class="text-gray-500 text-[11px] pt-1">→ 3 goedkoopste uren geselecteerd voor dit voorbeeld</div>
                    </div>
                </div>
            </div>

            {{-- Stap 3: Dynamische vermogensregeling --}}
            <div class="flex gap-4">
                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-blue-500 text-white flex items-center justify-center font-bold text-sm">3</div>
                <div>
                    <div class="font-semibold text-white mb-1">Dynamische vermogensregeling — elke minuut</div>
                    <p class="text-gray-400 leading-relaxed">
                        Binnen een gepland laaduur past het systeem <strong class="text-white">elke minuut</strong> het laadvermogen aan
                        op basis van live data van de P1-meter en omvormer. Zo wordt altijd het maximale benut zonder de hoofdzekering te belasten.
                    </p>
                    <div class="mt-2 grid grid-cols-1 md:grid-cols-3 gap-2 text-xs">
                        <div class="bg-gray-700 rounded-lg p-3">
                            <div class="text-green-400 font-semibold mb-1">☀️ Zon komt op</div>
                            <p class="text-gray-400">Zonne-overschot groeit → laadvermogen wordt automatisch <strong class="text-white">opgeschaald</strong> tot het surplus.</p>
                        </div>
                        <div class="bg-gray-700 rounded-lg p-3">
                            <div class="text-orange-400 font-semibold mb-1">🍽️ Vaatwasser aan</div>
                            <p class="text-gray-400">Huishoudverbruik stijgt → beschikbaar netcapaciteit daalt → laadvermogen wordt <strong class="text-white">teruggeschaald</strong>.</p>
                        </div>
                        <div class="bg-gray-700 rounded-lg p-3">
                            <div class="text-blue-400 font-semibold mb-1">🌙 Gepland uur, geen zon</div>
                            <p class="text-gray-400">Goedkoop nacht-uur → laad op <strong class="text-white">vol netcapaciteit</strong>, ongeacht zonne-overschot.</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Stap 4: Spoedfunctie --}}
            <div class="flex gap-4">
                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-red-500 text-white flex items-center justify-center font-bold text-sm">4</div>
                <div>
                    <div class="font-semibold text-white mb-1">Spoedfunctie — nooit met een lege auto</div>
                    <p class="text-gray-400 leading-relaxed">
                        Als er te weinig tijd overblijft om het doel nog te halen (minder dan 1,2× de minimale laadtijd),
                        negeert het systeem de prijsstrategie en laadt het <strong class="text-white">altijd op volledig beschikbaar vermogen</strong>
                        — ongeacht prijs of zonne-overschot.
                    </p>
                    <div class="mt-2 bg-gray-700 rounded-lg px-4 py-3 text-xs text-gray-300 font-mono">
                        Voorbeeld: nog 20 kWh nodig, max 9 kW → minimaal 3u nodig<br>
                        Als er &lt; 3,6u over is → <span class="text-red-400 font-bold">spoed: altijd laden</span>
                    </div>
                </div>
            </div>

            {{-- Zonne-overschot buiten plan --}}
            <div class="flex gap-4">
                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-yellow-400 text-gray-900 flex items-center justify-center font-bold text-sm">+</div>
                <div>
                    <div class="font-semibold text-white mb-1">Gratis laden buiten het plan</div>
                    <p class="text-gray-400 leading-relaxed">
                        Ook buiten de geselecteerde laaduren wordt geladen als er voldoende zonne-overschot is (standaard &gt;1500W).
                        Zo profiteer je altijd van gratis zonne-energie, ook als het geen gepland laaduur is.
                    </p>
                </div>
            </div>

            {{-- Groepenkast bescherming --}}
            <div class="flex gap-4">
                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-gray-500 text-white flex items-center justify-center font-bold text-sm">🔒</div>
                <div>
                    <div class="font-semibold text-white mb-1">Groepenkast bescherming — twee lagen</div>
                    <p class="text-gray-400 leading-relaxed">
                        Het systeem bewaakt de groepenkast op twee niveaus, beide instelbaar via de
                        <a href="{{ route('settings.index') }}" class="text-green-400 hover:underline">instellingen</a>:
                    </p>
                    <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
                        <div class="bg-gray-700 rounded-lg p-3">
                            <div class="text-orange-400 font-semibold mb-1">Laad-automaat (per fase)</div>
                            <p class="text-gray-400">
                                De instelling <strong class="text-white">Max laadstroom (A)</strong> begrenst het laadvermogen hard.
                                Staat nu op <strong class="text-white">{{ \App\Models\Setting::get('max_charge_current_a', 13) }}A</strong> — pas dit aan als je een zwaardere automaat plaatst (bijv. 16A).
                            </p>
                            <div class="mt-1 font-mono text-gray-500">
                                {{ \App\Models\Setting::get('max_charge_current_a', 13) }}A × {{ \App\Models\Setting::get('phase_count', 3) }} fases × 230V
                                = {{ \App\Models\Setting::get('max_charge_current_a', 13) * \App\Models\Setting::get('phase_count', 3) * 230 }}W max
                            </div>
                        </div>
                        <div class="bg-gray-700 rounded-lg p-3">
                            <div class="text-red-400 font-semibold mb-1">Hoofdzekering (totaal)</div>
                            <p class="text-gray-400">
                                De instelling <strong class="text-white">Netcapaciteit (W)</strong> bewaakt het totale huisverbruik.
                                Zodra het huis zijn limiet nadert, schaalt de auto automatisch terug.
                            </p>
                            <div class="mt-1 font-mono text-gray-500">
                                Limiet: {{ number_format(\App\Models\Setting::get('grid_capacity_w', 17250)) }}W
                                ({{ round(\App\Models\Setting::get('grid_capacity_w', 17250) / 230 / \App\Models\Setting::get('phase_count', 3)) }}A per fase)
                                − {{ \App\Models\Setting::get('grid_buffer_w', 500) }}W buffer
                            </div>
                        </div>
                    </div>
                    <div class="mt-2 bg-gray-700 rounded-lg px-4 py-3 text-xs text-gray-300">
                        <strong class="text-white">Voorbeeld:</strong> Hoofdzekering 25A, huis verbruikt 3.000W → nog 17.250 − 3.000 − 500 = 13.750W beschikbaar voor de auto.<br>
                        Zet je de wasmachine aan (+2.000W) → nog 11.750W → balancer schaalt automatisch terug van bijv. 13A naar 9A.
                    </div>
                </div>
            </div>

        </div>
    </div>

    @if($summary['has_goal'] && count($summary['plan']) > 0)

    {{-- Visueel tijdlijnplan --}}
    <div class="bg-gray-800 rounded-xl p-6">
        <h2 class="text-lg font-semibold text-white mb-1">Laadplan — uur per uur</h2>
        <p class="text-sm text-gray-400 mb-4">
            {{ $summary['planned_hours'] }} laaduren geselecteerd van de {{ count($summary['plan']) }} beschikbare uren ·
            gem. {{ $summary['avg_price_ct'] }}ct/kWh · geschatte kosten €{{ number_format($summary['estimated_cost'], 2) }}
        </p>

        {{-- Tijdlijn balk --}}
        <div class="flex gap-0.5 mb-3 rounded overflow-hidden" style="height: 32px;">
            @foreach($summary['plan'] as $slot)
                @php
                    $action = $slot['action'];
                    $color = match($action) {
                        'cheap'   => 'bg-green-500',
                        'planned' => 'bg-blue-500',
                        default   => 'bg-gray-600',
                    };
                    $hour = \Carbon\Carbon::parse($slot['hour_iso'])->setTimezone('Europe/Amsterdam')->format('H');
                @endphp
                <div class="flex-1 {{ $color }} relative group cursor-default" title="{{ $hour }}u — {{ $slot['reason'] ?: 'Niet laden' }}">
                    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block
                                bg-gray-900 text-white text-xs rounded px-2 py-1 whitespace-nowrap z-10 shadow">
                        {{ $hour }}:00 — {{ round($slot['price_ct'], 1) }}ct
                        @if($slot['action'] !== 'skip') · {{ round($slot['kwh'], 1) }}kWh @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Legenda --}}
        <div class="flex gap-4 text-xs text-gray-400 mb-5">
            <span><span class="inline-block w-3 h-3 bg-green-500 rounded mr-1"></span>Goedkoop (≤{{ $priceThresholdCt }}ct)</span>
            <span><span class="inline-block w-3 h-3 bg-blue-500 rounded mr-1"></span>Gepland (goedkoopste uren)</span>
            <span><span class="inline-block w-3 h-3 bg-gray-600 rounded mr-1"></span>Overgeslagen</span>
        </div>

        {{-- Uur-tabel --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-gray-400 text-xs border-b border-gray-700">
                        <th class="text-left py-2 pr-4">Tijdstip</th>
                        <th class="text-right pr-4">Prijs</th>
                        <th class="text-center pr-4">Actie</th>
                        <th class="text-right pr-4">Te laden</th>
                        <th class="text-left">Reden</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    @foreach($summary['plan'] as $slot)
                        @php
                            $isNow = \Carbon\Carbon::parse($slot['hour_iso'])->eq(now()->startOfHour());
                            $amsHour = \Carbon\Carbon::parse($slot['hour_iso'])->setTimezone('Europe/Amsterdam');
                        @endphp
                        <tr class="{{ $slot['action'] !== 'skip' ? 'bg-gray-750' : '' }} {{ $isNow ? 'ring-1 ring-green-500' : '' }}">
                            <td class="py-2 pr-4 font-mono text-gray-300">
                                {{ $amsHour->format('D d M H:i') }}
                                @if($isNow) <span class="text-green-400 text-xs ml-1">← nu</span> @endif
                            </td>
                            <td class="text-right pr-4 font-mono
                                {{ $slot['price_ct'] < 10 ? 'text-green-400' : ($slot['price_ct'] < 20 ? 'text-yellow-400' : 'text-red-400') }}">
                                {{ number_format($slot['price_ct'], 1) }}ct
                            </td>
                            <td class="text-center pr-4">
                                @if($slot['action'] === 'cheap')
                                    <span class="bg-green-900 text-green-300 text-xs px-2 py-0.5 rounded">Goedkoop</span>
                                @elseif($slot['action'] === 'planned')
                                    <span class="bg-blue-900 text-blue-300 text-xs px-2 py-0.5 rounded">Gepland</span>
                                @else
                                    <span class="bg-gray-700 text-gray-400 text-xs px-2 py-0.5 rounded">Skip</span>
                                @endif
                            </td>
                            <td class="text-right pr-4 font-mono text-gray-300">
                                {{ $slot['kwh'] > 0 ? number_format($slot['kwh'], 1) . ' kWh' : '—' }}
                            </td>
                            <td class="text-gray-400 text-xs">{{ $slot['reason'] ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @elseif($summary['has_goal'])
    <div class="bg-gray-800 rounded-xl p-6 text-center text-gray-400">
        Geen prijsdata beschikbaar voor de laadperiode. Prijzen worden elk uur automatisch opgehaald — even wachten of ververs de pagina.
    </div>
    @endif

</div>
@endsection
