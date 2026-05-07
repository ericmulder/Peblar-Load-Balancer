@extends('layouts.app')
@section('title', 'Instellingen')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    <div>
        <h1 class="text-2xl font-bold text-white">Instellingen</h1>
        <p class="text-sm text-gray-400 mt-0.5">Configureer apparaatverbindingen en load balancer parameters.</p>
    </div>

    <form method="POST" action="{{ route('settings.update') }}" class="space-y-6">
        @csrf

        {{-- Voertuig --}}
        <div class="bg-gray-800 rounded-xl p-6">
            <div class="flex items-center gap-2 mb-5">
                <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v9a2 2 0 01-2 2h-1m-9 0a2 2 0 11-4 0 2 2 0 014 0zm10 0a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <h2 class="text-base font-semibold text-white">Voertuig</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-400 block mb-1" for="battery_capacity_kwh">
                        Batterijcapaciteit (kWh)
                        <span class="text-xs text-gray-500 ml-1">— Ioniq 5 LR = 77.4, SR = 58</span>
                    </label>
                    <input type="number" step="0.1" name="battery_capacity_kwh" id="battery_capacity_kwh"
                           value="{{ $settings['vehicle']->firstWhere('key','battery_capacity_kwh')?->value ?? 60 }}"
                           class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                </div>
                <div>
                    <label class="text-sm text-gray-400 block mb-1" for="default_target_soc">
                        Standaard doel SoC (%)
                        <span class="text-xs text-gray-500 ml-1">— bijv. 80 of 90</span>
                    </label>
                    <input type="number" step="1" min="10" max="100" name="default_target_soc" id="default_target_soc"
                           value="{{ $settings['vehicle']->firstWhere('key','default_target_soc')?->value ?? 90 }}"
                           class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                </div>
            </div>
            <p class="text-xs text-gray-500 mt-3">
                Huidige SoC stel je in per laadsessie op de
                <a href="{{ route('strategy') }}" class="text-green-400 hover:underline">Strategie-pagina</a>.
                SoC wordt automatisch uitgelezen via Hyundai BlueLink als dat geconfigureerd is.
            </p>
        </div>

        {{-- Hyundai BlueLink --}}
        <div class="bg-gray-800 rounded-xl p-6">
            <div class="flex items-center gap-2 mb-5">
                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v9a2 2 0 01-2 2h-1m-9 0a2 2 0 11-4 0 2 2 0 014 0zm10 0a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <h2 class="text-base font-semibold text-white">Hyundai BlueLink</h2>
                <span class="text-xs text-gray-500 ml-1">— automatische SoC uitlezen</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Ingeschakeld toggle --}}
                <div class="sm:col-span-2">
                    <label class="text-sm text-gray-400 block mb-1" for="hyundai_enabled">Voertuigdata ophalen</label>
                    <select name="hyundai_enabled" id="hyundai_enabled"
                            class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        <option value="1" @selected($settings['hyundai']->firstWhere('key','hyundai_enabled')?->value)>Aan</option>
                        <option value="0" @selected(!$settings['hyundai']->firstWhere('key','hyundai_enabled')?->value)>Uit</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm text-gray-400 block mb-1" for="hyundai_username">BlueLink e-mailadres</label>
                    <input type="email" name="hyundai_username" id="hyundai_username"
                           value="{{ $settings['hyundai']->firstWhere('key','hyundai_username')?->value }}"
                           placeholder="jouw@email.nl"
                           class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                </div>
                <div>
                    <label class="text-sm text-gray-400 block mb-1" for="hyundai_pin">BlueLink PIN <span class="text-gray-500">(optioneel)</span></label>
                    <input type="password" name="hyundai_pin" id="hyundai_pin"
                           value="{{ $settings['hyundai']->firstWhere('key','hyundai_pin')?->value }}"
                           placeholder="Laat leeg als geen PIN"
                           autocomplete="off"
                           class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                </div>
                <div class="sm:col-span-2">
                    <label class="text-sm text-gray-400 block mb-1" for="hyundai_refresh_token">
                        Refresh token
                    </label>
                    <input type="password" name="hyundai_refresh_token" id="hyundai_refresh_token"
                           value="{{ $settings['hyundai']->firstWhere('key','hyundai_refresh_token')?->value }}"
                           placeholder="Plak hier je refresh token na het uitvoeren van het script"
                           autocomplete="off"
                           class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-green-500 focus:border-transparent">
                </div>
            </div>
            {{-- Token ophalen instructie --}}
            <div class="mt-4 bg-gray-900 rounded-lg p-4 border border-gray-700">
                <p class="text-xs text-gray-400 font-semibold mb-2">🔑 Refresh token ophalen (eenmalig)</p>
                <ol class="text-xs text-gray-500 space-y-1 list-decimal list-inside">
                    <li>Maak de venv aan: <code class="bg-gray-700 px-1 rounded text-green-400">python3 -m venv scripts/.venv</code></li>
                    <li>Installeer: <code class="bg-gray-700 px-1 rounded text-green-400">scripts/.venv/bin/pip install selenium chromedriver-autoinstaller requests hyundai_kia_connect_api</code></li>
                    <li>Voer uit: <code class="bg-gray-700 px-1 rounded text-green-400">scripts/.venv/bin/python3 scripts/get_hyundai_token.py</code></li>
                    <li>Log in via het geopende Chrome-venster (incl. reCAPTCHA)</li>
                    <li>Druk op ENTER → kopieer het refresh token hierboven</li>
                </ol>
            </div>
        </div>

        {{-- Peblar --}}
        <div class="bg-gray-800 rounded-xl p-6">
            <div class="flex items-center gap-2 mb-5">
                <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                <h2 class="text-base font-semibold text-white">Peblar Laadpaal</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach($settings['peblar'] ?? [] as $s)
                <div>
                    <label class="text-sm text-gray-400 block mb-1" for="{{ $s->key }}">{{ $s->label }}</label>
                    <input type="{{ str_contains($s->key,'token') ? 'password' : 'text' }}"
                           name="{{ $s->key }}" id="{{ $s->key }}" value="{{ $s->value }}"
                           autocomplete="off"
                           class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-green-500 focus:border-transparent">
                </div>
                @endforeach
            </div>
        </div>

        {{-- Solax --}}
        <div class="bg-gray-800 rounded-xl p-6">
            <div class="flex items-center gap-2 mb-5">
                <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 3v1m0 16v1M3 12h1m16 0h1M5.636 5.636l.707.707M17.657 17.657l.707.707M5.636 18.364l.707-.707M17.657 6.343l.707-.707"/>
                </svg>
                <h2 class="text-base font-semibold text-white">Solax Omvormer</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach($settings['solax'] ?? [] as $s)
                <div>
                    <label class="text-sm text-gray-400 block mb-1" for="{{ $s->key }}">{{ $s->label }}</label>
                    <input type="{{ str_contains($s->key,'password') ? 'password' : 'text' }}"
                           name="{{ $s->key }}" id="{{ $s->key }}" value="{{ $s->value }}"
                           autocomplete="off"
                           class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-green-500 focus:border-transparent">
                </div>
                @endforeach
            </div>
        </div>

        {{-- Slimmelezer P1 --}}
        <div class="bg-gray-800 rounded-xl p-6">
            <div class="flex items-center gap-2 mb-5">
                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                <h2 class="text-base font-semibold text-white">Slimmelezer P1</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach($settings['p1'] ?? [] as $s)
                <div>
                    <label class="text-sm text-gray-400 block mb-1" for="{{ $s->key }}">{{ $s->label }}</label>
                    <input type="text" name="{{ $s->key }}" id="{{ $s->key }}" value="{{ $s->value }}"
                           class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-green-500 focus:border-transparent">
                </div>
                @endforeach
            </div>
        </div>

        {{-- Load Balancer --}}
        <div class="bg-gray-800 rounded-xl p-6">
            <div class="flex items-center gap-2 mb-5">
                <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                </svg>
                <h2 class="text-base font-semibold text-white">Load Balancer</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach($settings['balancer'] ?? [] as $s)
                <div>
                    <label class="text-sm text-gray-400 block mb-1" for="{{ $s->key }}">{{ $s->label }}</label>
                    @if($s->type === 'boolean')
                        <select name="{{ $s->key }}" id="{{ $s->key }}"
                                class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <option value="1" @selected($s->value)>Aan</option>
                            <option value="0" @selected(!$s->value)>Uit</option>
                        </select>
                    @elseif($s->key === 'solar_min_surplus_w')
                        @php $solarMinVal = (int) $s->value; @endphp
                        <input type="number" step="1" min="1"
                               name="{{ $s->key }}" id="{{ $s->key }}" value="{{ $s->value }}"
                               class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        @if($solarMinVal < 1380)
                        <p class="text-xs text-yellow-400 mt-1">
                            ⚠️ Drempel ligt onder 1.380 W (6 A × 230 V, 1-fase minimum).
                            De balancer vult het verschil aan vanuit het net — zo laadt hij altijd minimaal 6 A zodra de drempel is bereikt.
                        </p>
                        @else
                        <p class="text-xs text-gray-500 mt-1">
                            Min. 1.380 W voor 1-fase (6 A × 230 V). Surplus onder deze waarde start het laden niet.
                        </p>
                        @endif
                    @else
                        <input type="number"
                               step="{{ $s->type === 'float' ? '0.001' : '1' }}"
                               name="{{ $s->key }}" id="{{ $s->key }}" value="{{ $s->value }}"
                               class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-transparent">
                    @endif
                </div>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                    class="px-6 py-2.5 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg transition-colors">
                Opslaan
            </button>
        </div>
    </form>

    {{-- Scheduler info --}}
    @php($inDocker = file_exists('/.dockerenv'))
    <div class="bg-gray-800 rounded-xl p-5 border border-gray-700 space-y-4">
        <div>
            <h3 class="text-sm font-semibold text-gray-300 mb-2">Automatisch uitvoeren — scheduler</h3>
            <p class="text-xs text-gray-500">
                De load balancer draait elke minuut via Laravel's scheduler. Dit moet door een
                achtergrond-process aangestuurd worden, anders gebeurt er niets ook al staan
                de instellingen goed.
            </p>
        </div>

        @if($inDocker)
            <div class="border-l-4 border-green-500 bg-green-900/20 px-4 py-3 rounded">
                <p class="text-sm text-green-300 font-semibold mb-1">Docker — al actief</p>
                <p class="text-xs text-gray-400">
                    Deze instance draait in een Docker container. Supervisord houdt
                    <code class="bg-gray-700 px-1 rounded">php artisan schedule:work</code>
                    permanent draaiend. Je hoeft niets te doen.
                </p>
            </div>
        @else
            <div class="border-l-4 border-yellow-500 bg-yellow-900/20 px-4 py-3 rounded">
                <p class="text-sm text-yellow-300 font-semibold mb-1">Self-host zonder Docker</p>
                <p class="text-xs text-gray-400 mb-2">
                    Voeg deze regel toe aan je crontab (<code class="bg-gray-700 px-1 rounded">crontab -e</code>).
                    Cron triggert de scheduler elke minuut.
                </p>
                <pre class="bg-gray-900 rounded-lg p-3 text-xs text-green-400 overflow-x-auto">* * * * * cd {{ base_path() }} && php artisan schedule:run >> /dev/null 2>&1</pre>
                <p class="text-xs text-gray-500 mt-2">
                    Alternatief: laat <code class="bg-gray-700 px-1 rounded">php artisan schedule:work</code>
                    permanent draaien onder systemd of supervisord.
                </p>
            </div>
        @endif
    </div>

</div>
@endsection
