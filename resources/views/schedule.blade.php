@extends('layouts.app')
@section('title', 'Schema')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Laadschema</h1>
            <p class="text-sm text-gray-500 mt-0.5">Terugkerende momenten waarop de auto op een bepaald percentage moet staan.</p>
        </div>
        <button onclick="document.getElementById('addModal').classList.remove('hidden')"
                class="flex items-center gap-2 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Moment toevoegen
        </button>
    </div>

    {{-- Weekoverzicht --}}
    <div class="card">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Weekoverzicht</h3>
        <div class="grid grid-cols-7 gap-2">
            @foreach(\App\Models\ChargeSchedule::DAY_LABELS as $dayNum => $dayName)
            <div>
                <div class="text-xs font-semibold text-gray-500 text-center mb-2">
                    {{ \App\Models\ChargeSchedule::DAY_SHORT[$dayNum] }}
                </div>
                <div class="space-y-1 min-h-[60px]">
                    @foreach($schedules->where('day_of_week', $dayNum)->where('active', true) as $s)
                    <div class="text-xs rounded-lg px-2 py-1.5 text-center bg-green-100 text-green-800 font-medium leading-tight">
                        <div>{{ $s->deadline_short }}</div>
                        <div class="font-bold">{{ $s->target_soc }}%</div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
        {{-- Elke dag items --}}
        @if($schedules->whereNull('day_of_week')->where('active', true)->isNotEmpty())
        <div class="mt-3 pt-3 border-t border-gray-100">
            <div class="text-xs text-gray-500 mb-2">Elke dag</div>
            <div class="flex flex-wrap gap-2">
                @foreach($schedules->whereNull('day_of_week')->where('active', true) as $s)
                <div class="text-xs rounded-lg px-2 py-1.5 bg-blue-100 text-blue-800 font-medium">
                    {{ $s->deadline_short }} → {{ $s->target_soc }}%
                    @if($s->label) · {{ $s->label }} @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- Lijst --}}
    <div class="card">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Alle laadmomenten</h3>

        @if($schedules->isEmpty())
            <p class="text-sm text-gray-400">Nog geen laadmomenten. Voeg er een toe met de knop rechtsboven.</p>
        @else
        <div class="space-y-2">
            @foreach($schedules as $schedule)
            <div class="rounded-xl border border-gray-200 bg-gray-50 overflow-hidden" x-data="{ editing: false }">

                {{-- Rij --}}
                <div class="flex items-center gap-4 px-4 py-3">
                    {{-- Status dot --}}
                    <div class="w-2.5 h-2.5 rounded-full flex-shrink-0 {{ $schedule->active ? 'bg-green-500' : 'bg-gray-300' }}"></div>

                    {{-- Info --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-semibold text-gray-900 text-sm">
                                {{ $schedule->day_label }} om {{ $schedule->deadline_short }}
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-800">
                                {{ $schedule->target_soc }}%
                            </span>
                            @if($schedule->min_soc)
                                <span class="text-xs text-gray-400">min. {{ $schedule->min_soc }}%</span>
                            @endif
                            @if($schedule->label)
                                <span class="text-xs text-gray-500 italic">{{ $schedule->label }}</span>
                            @endif
                            @if(!$schedule->active)
                                <span class="text-xs bg-gray-200 text-gray-500 px-1.5 py-0.5 rounded">Inactief</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-400 mt-0.5">
                            Volgende: <strong>{{ $schedule->next->setTimezone('Europe/Amsterdam')->isoFormat('ddd D MMM HH:mm') }}</strong>
                            · Plan {{ $schedule->plan_ahead_hours }}u van tevoren
                        </div>
                    </div>

                    {{-- Acties --}}
                    <div class="flex items-center gap-2 flex-shrink-0">
                        {{-- Activeer als laaddoel --}}
                        @if($schedule->active)
                        <form method="POST" action="{{ route('schedule.activate', $schedule) }}">
                            @csrf
                            <button type="submit"
                                    class="text-xs px-2.5 py-1.5 rounded-lg border border-green-300 text-green-700 bg-white hover:bg-green-50 transition-colors font-medium">
                                → Activeer als doel
                            </button>
                        </form>
                        @endif

                        {{-- Toggle actief --}}
                        <form method="POST" action="{{ route('schedule.toggle', $schedule) }}">
                            @csrf @method('PATCH')
                            <button type="submit"
                                    class="text-xs px-2 py-1 rounded border transition-colors
                                    {{ $schedule->active ? 'border-gray-300 text-gray-500 bg-white hover:bg-gray-50' : 'border-green-300 text-green-600 bg-white hover:bg-green-50' }}">
                                {{ $schedule->active ? 'Uit' : 'Aan' }}
                            </button>
                        </form>

                        {{-- Bewerken --}}
                        <button @click="editing = !editing"
                                class="text-xs px-2 py-1 rounded border border-gray-300 text-gray-600 bg-white hover:bg-gray-50 transition-colors">
                            Bewerken
                        </button>

                        {{-- Verwijder --}}
                        <form method="POST" action="{{ route('schedule.destroy', $schedule) }}"
                              onsubmit="return confirm('Laadmoment verwijderen?')">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    class="text-xs px-2 py-1 rounded border border-red-200 text-red-600 bg-white hover:bg-red-50 transition-colors">
                                ✕
                            </button>
                        </form>
                    </div>
                </div>

                {{-- Inline bewerk-formulier --}}
                <div x-show="editing" x-cloak class="px-4 pb-4 pt-0 border-t border-gray-200 bg-white">
                    <form method="POST" action="{{ route('schedule.update', $schedule) }}"
                          class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-3">
                        @csrf @method('PUT')
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Label (optioneel)</label>
                            <input type="text" name="label" value="{{ $schedule->label }}"
                                   class="w-full text-sm border border-gray-300 rounded-lg px-2 py-1.5" placeholder="bijv. Weekendrit">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Dag</label>
                            <select name="day_of_week" class="w-full text-sm border border-gray-300 rounded-lg px-2 py-1.5">
                                <option value="">Elke dag</option>
                                @foreach(\App\Models\ChargeSchedule::DAY_LABELS as $num => $name)
                                    <option value="{{ $num }}" {{ $schedule->day_of_week == $num ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Deadline tijd</label>
                            <input type="time" name="deadline_time" value="{{ $schedule->deadline_short }}"
                                   class="w-full text-sm border border-gray-300 rounded-lg px-2 py-1.5" required>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Doel SoC (%)</label>
                            <input type="number" name="target_soc" value="{{ $schedule->target_soc }}" min="1" max="100"
                                   class="w-full text-sm border border-gray-300 rounded-lg px-2 py-1.5" required>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Minimum SoC (%) <span class="text-gray-400">optioneel</span></label>
                            <input type="number" name="min_soc" value="{{ $schedule->min_soc }}" min="1" max="100"
                                   class="w-full text-sm border border-gray-300 rounded-lg px-2 py-1.5" placeholder="—">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Plan X uur vooruit</label>
                            <input type="number" name="plan_ahead_hours" value="{{ $schedule->plan_ahead_hours }}" min="1" max="72"
                                   class="w-full text-sm border border-gray-300 rounded-lg px-2 py-1.5">
                        </div>
                        <div class="col-span-2 sm:col-span-3 flex gap-2 pt-1">
                            <button type="submit" class="px-4 py-1.5 bg-green-600 text-white text-xs rounded-lg hover:bg-green-700">Opslaan</button>
                            <button type="button" @click="editing = false" class="px-4 py-1.5 bg-gray-100 text-gray-700 text-xs rounded-lg hover:bg-gray-200">Annuleren</button>
                        </div>
                    </form>
                </div>

            </div>
            @endforeach
        </div>
        @endif
    </div>

</div>

{{-- Modal: toevoegen --}}
<div id="addModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
        <h2 class="text-lg font-bold text-gray-900 mb-4">Laadmoment toevoegen</h2>
        <form method="POST" action="{{ route('schedule.store') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="text-sm text-gray-600 block mb-1">Label <span class="text-gray-400">(optioneel)</span></label>
                    <input type="text" name="label" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                           placeholder="bijv. Wekelijkse rit, Werkdag">
                </div>
                <div>
                    <label class="text-sm text-gray-600 block mb-1">Dag</label>
                    <select name="day_of_week" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">Elke dag</option>
                        @foreach(\App\Models\ChargeSchedule::DAY_LABELS as $num => $name)
                            <option value="{{ $num }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-sm text-gray-600 block mb-1">Klaar om <span class="text-red-500">*</span></label>
                    <input type="time" name="deadline_time" value="09:00"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="text-sm text-gray-600 block mb-1">Doel SoC % <span class="text-red-500">*</span></label>
                    <input type="number" name="target_soc" value="80" min="1" max="100"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="text-sm text-gray-600 block mb-1">Minimum SoC % <span class="text-gray-400">optioneel</span></label>
                    <input type="number" name="min_soc" min="1" max="100"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="—">
                </div>
                <div class="col-span-2">
                    <label class="text-sm text-gray-600 block mb-1">Plan starten X uur voor deadline</label>
                    <div class="flex items-center gap-2">
                        <input type="number" name="plan_ahead_hours" value="24" min="1" max="72"
                               class="w-24 border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <span class="text-sm text-gray-500">uur van tevoren</span>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Bij 24u: als er meer dan 24u over is, wacht het systeem tot de planning start.</p>
                </div>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="flex-1 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700">
                    Toevoegen
                </button>
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')"
                        class="flex-1 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200">
                    Annuleren
                </button>
            </div>
        </form>
    </div>
</div>

@endsection
