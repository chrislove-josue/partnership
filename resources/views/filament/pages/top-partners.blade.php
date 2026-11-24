<x-filament-panels::page>
    {{ $this->form }}

    @php
        $ranking = $this->getRanking();
    @endphp

    @if (isset($ranking['error']))
        <div class="rp-alert">{{ $ranking['error'] }}</div>
    @else
        <div class="rp-stats">
            <div class="rp-stat">
                <p class="rp-label">Période</p>
                <p class="rp-value rp-value-primary">{{ $ranking['label'] }}</p>
            </div>
            <div class="rp-stat">
                <p class="rp-label">Du au</p>
                <p class="rp-value">{{ $ranking['range'] }}</p>
            </div>
        </div>

        <div class="rp-card">
            <div class="rp-card-head">
                <h2 class="rp-card-title">Top partenaires — {{ $ranking['range'] }}</h2>
            </div>
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
                        <td class="rp-strong">{{ $item['name'] }}@if ($item['is_user']) (compte utilisateur)@endif</td>
                        <td>{{ $item['church'] ?? '—' }}</td>
                        <td>{{ $item['cellule'] ?? '—' }}</td>
                        <td class="rp-center">{{ $item['count'] }}</td>
                        <td class="rp-right rp-strong">{{ number_format((float) $item['amount'], 0, ',', ' ') }} FCFA</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="rp-empty">Aucun don sur cette période pour vos églises.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
