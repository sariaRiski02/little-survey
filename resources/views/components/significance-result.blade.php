@props(['analysis', 'label' => null])
@php
    $fmt = fn ($value) => number_format($value, 1, ',', '.');
    $pValue = fn ($value) => $value === null ? '-' : ($value < 0.001 ? '< 0,001' : number_format($value, 3, ',', '.'));
    $alpha = number_format($analysis['alpha'], 2, ',', '.');
    $tests = [
        'difference' => ['Uji z selisih dua proporsi', 'Kandidat teratas dibanding kandidat kedua'],
        'goodness' => ['Chi-square kecocokan', 'Dukungan antar kandidat dibanding pembagian merata'],
        'independence' => ['Chi-square independensi', 'Pilihan kandidat menurut '.($label ?: 'wilayah')],
    ];
@endphp

<div {{ $attributes->class('space-y-6') }}>
    <p class="text-sm text-slate-600">
        Taraf signifikansi α = {{ $alpha }} (tingkat kepercayaan {{ $analysis['confidence'] }}%). Uji memakai data mentah tanpa pembobotan dari {{ number_format($analysis['n'], 0, ',', '.') }} jawaban.
    </p>

    <p class="rounded-2xl p-4 text-sm font-bold {{ ($analysis['difference']['significant'] ?? false) ? 'bg-teal-50 text-teal-900' : 'bg-amber-50 text-amber-900' }}">
        {{ $analysis['conclusion'] }}
    </p>

    @if (count($analysis['candidates']))
        <div class="overflow-x-auto rounded-2xl border border-slate-200">
            <table class="w-full min-w-[36rem] text-left text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-bold">Kandidat</th>
                        <th scope="col" class="px-4 py-3 text-right font-bold">Mentah</th>
                        <th scope="col" class="px-4 py-3 text-right font-bold">Selang kepercayaan {{ $analysis['confidence'] }}%</th>
                        <th scope="col" class="px-4 py-3 text-right font-bold">p-value vs teratas</th>
                        <th scope="col" class="px-4 py-3 font-bold">Hasil uji</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($analysis['candidates'] as $candidate)
                        <tr wire:key="uji-{{ $candidate['nama'] }}">
                            <th scope="row" class="px-4 py-3 font-semibold">
                                <span class="flex items-center gap-2"><span class="h-3 w-3 rounded-full" style="background-color: {{ $candidate['warna'] }}" aria-hidden="true"></span>{{ $candidate['nama'] }}</span>
                            </th>
                            <td class="px-4 py-3 text-right">{{ $fmt($candidate['persen']) }}% <span class="text-slate-600">({{ number_format($candidate['jumlah'], 0, ',', '.') }})</span></td>
                            <td class="px-4 py-3 text-right">{{ $fmt($candidate['ciLow']) }}–{{ $fmt($candidate['ciHigh']) }}%</td>
                            @if ($candidate['vsLeader'])
                                <td class="px-4 py-3 text-right">{{ $pValue($candidate['vsLeader']['p']) }}</td>
                                <td class="px-4 py-3">
                                    <x-badge :tone="$candidate['vsLeader']['significant'] ? 'success' : 'warning'">{{ $candidate['vsLeader']['significant'] ? 'Berbeda nyata dari teratas' : 'Tidak berbeda nyata' }}</x-badge>
                                </td>
                            @else
                                <td class="px-4 py-3 text-right text-slate-600">-</td>
                                <td class="px-4 py-3"><x-badge tone="info">Teratas</x-badge></td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-3">
        @foreach ($tests as $key => [$title, $subtitle])
            @continue($key === 'independence' && ! $label)
            @php $test = $analysis[$key]; @endphp
            <div wire:key="tes-{{ $key }}" class="rounded-2xl border border-slate-200 bg-white p-4">
                <p class="font-bold">{{ $title }}</p>
                <p class="text-xs text-slate-600">{{ $subtitle }}</p>
                @if ($test)
                    <dl class="mt-3 grid grid-cols-3 gap-2 text-center text-sm">
                        @if ($key === 'difference')
                            <div class="rounded-xl bg-slate-50 p-2"><dt class="text-xs text-slate-600">z</dt><dd class="font-extrabold">{{ $test['z'] === null ? '-' : number_format($test['z'], 2, ',', '.') }}</dd></div>
                            <div class="rounded-xl bg-slate-50 p-2"><dt class="text-xs text-slate-600">Selisih</dt><dd class="font-extrabold">{{ $fmt($test['selisih']) }}</dd></div>
                        @else
                            <div class="rounded-xl bg-slate-50 p-2"><dt class="text-xs text-slate-600">χ²</dt><dd class="font-extrabold">{{ number_format($test['chi2'], 2, ',', '.') }}</dd></div>
                            <div class="rounded-xl bg-slate-50 p-2"><dt class="text-xs text-slate-600">db</dt><dd class="font-extrabold">{{ $test['df'] }}</dd></div>
                        @endif
                        <div class="rounded-xl bg-slate-50 p-2"><dt class="text-xs text-slate-600">p-value</dt><dd class="font-extrabold">{{ $pValue($test['p']) }}</dd></div>
                    </dl>
                    <p class="mt-3"><x-badge :tone="$test['significant'] ? 'success' : 'warning'">{{ $test['significant'] ? 'Signifikan' : 'Tidak signifikan' }}</x-badge></p>
                    <p class="mt-2 text-sm text-slate-700">{{ $test['conclusion'] }}</p>
                    @isset($test['cramersV'])<p class="mt-1 text-xs text-slate-600">Kekuatan hubungan (Cramér's V): {{ number_format($test['cramersV'], 2, ',', '.') }}</p>@endisset
                @else
                    <p class="mt-3 text-sm text-slate-600">{{ $analysis['notes'][$key] ?? 'Data belum cukup untuk uji ini.' }}</p>
                @endif
                @if (isset($analysis['notes'][$key]) && $test)
                    <p class="mt-2 text-xs text-amber-800">{{ $analysis['notes'][$key] }}</p>
                @endif
            </div>
        @endforeach
    </div>

    <p class="text-xs text-slate-600">p-value di bawah α berarti perbedaan dianggap bukan kebetulan. Perbandingan tiap kandidat dengan yang teratas tidak dikoreksi untuk banyak perbandingan.</p>
</div>
