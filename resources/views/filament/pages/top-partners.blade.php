<x-filament-panels::page>
    {{ $this->form }}

    @php
        $ranking = $this->getRanking();
        $topChurches = $this->getTopChurches();
    @endphp

    @if (isset($ranking['error']))
        <div class="rp-alert">{{ $ranking['error'] }}</div>
    @else
        @php
            $total = collect($ranking['items'])->sum('amount');
            $count = collect($ranking['items'])->sum('count');
        @endphp

        <div class="rp-card">
            <div class="rp-card-head">
                <h2 class="rp-card-title">
                    Top partenaires — {{ $ranking['label'] }} · {{ $ranking['church'] ?? '' }}
                </h2>
                <span class="rp-summary">{{ $ranking['range'] }} · {{ $count }} don(s) · {{ number_format((float) $total, 0, ',', ' ') }} FCFA</span>
            </div>
            <div class="rp-table-wrap">
                <table class="rp-table">
                    <thead>
                    <tr>
                        <th class="rp-center">#</th>
                        <th>Partenaire</th>
                        <th>Église créditée</th>
                        <th>Église d'origine</th>
                        <th>Cellule</th>
                        <th class="rp-center">Dons</th>
                        <th class="rp-right">Montant</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($ranking['items'] as $index => $item)
                        <tr>
                            <td class="rp-center rp-strong">
                                @if ($index === 0) 🥇 @elseif ($index === 1) 🥈 @elseif ($index === 2) 🥉 @else {{ $index + 1 }} @endif
                            </td>
                            <td class="rp-strong">{{ $item['name'] }}@if ($item['is_user']) <span class="rp-tag">compte</span>@endif</td>
                            <td>{{ $item['church'] ?? '—' }}</td>
                            <td>{{ $item['origin_church'] ?? '—' }}</td>
                            <td>{{ $item['cellule'] ?? '—' }}</td>
                            <td class="rp-center">{{ $item['count'] }}</td>
                            <td class="rp-right rp-strong">{{ number_format((float) $item['amount'], 0, ',', ' ') }} FCFA</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="rp-empty">Aucun don sur cette période pour cette église.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if (! empty($topChurches['items']))
        <div class="rp-card">
            <div class="rp-card-head">
                <h2 class="rp-card-title">Top églises — {{ $ranking['label'] ?? '' }} · {{ $ranking['range'] ?? '' }}</h2>
                <span class="rp-summary">Classement des églises par montant de dons sur la période</span>
            </div>
            <div class="rp-table-wrap">
                <table class="rp-table">
                    <thead>
                    <tr>
                        <th class="rp-center">#</th>
                        <th>Église</th>
                        <th>Type</th>
                        <th class="rp-center">Partenaires</th>
                        <th class="rp-center">Dons</th>
                        <th class="rp-right">Montant</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($topChurches['items'] as $index => $church)
                        <tr>
                            <td class="rp-center rp-strong">
                                @if ($index === 0) 🥇 @elseif ($index === 1) 🥈 @elseif ($index === 2) 🥉 @else {{ $index + 1 }} @endif
                            </td>
                            <td class="rp-strong">{{ $church['name'] }}</td>
                            <td>{{ $church['type'] }}</td>
                            <td class="rp-center">{{ $church['partners'] }}</td>
                            <td class="rp-center">{{ $church['count'] }}</td>
                            <td class="rp-right rp-strong">{{ number_format((float) $church['amount'], 0, ',', ' ') }} FCFA</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-filament-panels::page>
