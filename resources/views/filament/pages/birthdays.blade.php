<x-filament-panels::page>
    {{ $this->form }}

    @php
        $list = $this->getBirthdays();
        $today = \Illuminate\Support\Carbon::today();
    @endphp

    @if (isset($list['error']))
        <div class="rp-alert">{{ $list['error'] }}</div>
    @else
        <div class="rp-stats">
            <div class="rp-stat">
                <p class="rp-label">Mois</p>
                <p class="rp-value rp-value-primary">{{ $list['label'] }}</p>
            </div>
            <div class="rp-stat">
                <p class="rp-label">Anniversaires</p>
                <p class="rp-value">{{ $list['count'] }}</p>
            </div>
        </div>

        <div class="rp-card">
            <div class="rp-card-head">
                <h2 class="rp-card-title">Liste des anniversaires — {{ $list['label'] }}</h2>
            </div>
            <table class="rp-table">
                <thead>
                <tr>
                    <th class="rp-center">Jour</th>
                    <th>Partenaire</th>
                    <th>Église</th>
                    <th>Cellule</th>
                    <th class="rp-center">Âge</th>
                    <th>Téléphone</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($list['items'] as $item)
                    @php
                        $isBirthdayToday = $item['date'] === $today->toDateString();
                    @endphp
                    <tr>
                        <td class="rp-center rp-strong">{{ $item['day'] }}@if ($isBirthdayToday) 🎂@endif</td>
                        <td class="rp-strong">{{ $item['name'] }}@if ($item['is_user']) (compte utilisateur)@endif</td>
                        <td>{{ $item['church'] ?? '—' }}</td>
                        <td>{{ $item['cellule'] ?? '—' }}</td>
                        <td class="rp-center">{{ $item['age'] !== null ? $item['age'].' ans' : '—' }}</td>
                        <td>{{ $item['phone'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="rp-empty">Aucun anniversaire dans ce mois pour vos églises.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
