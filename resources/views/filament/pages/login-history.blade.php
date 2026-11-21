<x-filament-panels::page>
    {{ $this->form }}

    @php
        $entries = $this->getEntries();
    @endphp

    <div class="lh-card">
        <div class="lh-card-header">
            <h2 class="lh-card-title">Mes connexions et déconnexions</h2>
            <p class="lh-card-desc">
                Chaque connexion et déconnexion est enregistrée, que vous utilisiez le site web ou l’application mobile.
            </p>
        </div>

        @if ($entries->isEmpty())
            <p class="lh-empty">Aucune connexion enregistrée pour le moment.</p>
        @else
            <div class="lh-scroll">
                <table class="lh-table">
                    <thead>
                        <tr>
                            <th>Événement</th>
                            <th>Source</th>
                            <th>Date et heure</th>
                            <th class="lh-col-ip">Adresse IP</th>
                            <th class="lh-col-device">Appareil</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $entry)
                            <tr>
                                <td class="lh-nowrap">
                                    <span class="lh-badge {{ $entry->event === \App\Models\LoginHistory::EVENT_LOGOUT ? 'lh-badge--logout' : 'lh-badge--login' }}">
                                        {{ $entry->eventLabel() }}
                                    </span>
                                </td>
                                <td class="lh-nowrap">
                                    <span class="lh-badge {{ $entry->source === \App\Models\LoginHistory::SOURCE_MOBILE ? 'lh-badge--mobile' : 'lh-badge--web' }}">
                                        {{ $entry->sourceLabel() }}
                                    </span>
                                </td>
                                <td class="lh-nowrap">{{ $entry->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="lh-col-ip lh-nowrap lh-muted">{{ $entry->ip_address ?? '—' }}</td>
                                <td class="lh-col-device lh-nowrap lh-muted" title="{{ $entry->user_agent ?? '' }}">{{ $entry->deviceLabel() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="lh-footer">{{ $entries->links() }}</div>
        @endif
    </div>
</x-filament-panels::page>
