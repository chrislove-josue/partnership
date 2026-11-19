<x-filament-panels::page>
    @php
        $entries = $this->getEntries();
    @endphp

    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-section-header flex flex-col gap-3 px-6 py-4">
            <h2 class="fi-section-header-heading text-base font-semibold leading-6 text-gray-950 dark:text-white">
                Mes connexions et déconnexions
            </h2>
            <p class="fi-section-header-description text-sm text-gray-500 dark:text-gray-400">
                Chaque connexion et déconnexion est enregistrée, que vous utilisiez le site web ou l’application mobile.
            </p>
        </div>

        @if ($entries->isEmpty())
            <div class="px-6 pb-6">
                <p class="text-sm text-gray-500 dark:text-gray-400">Aucune connexion enregistrée pour le moment.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-y border-gray-200 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                            <th class="px-6 py-3">Événement</th>
                            <th class="px-6 py-3">Source</th>
                            <th class="px-6 py-3">Date et heure</th>
                            <th class="px-6 py-3">Adresse IP</th>
                            <th class="px-6 py-3">Appareil</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($entries as $entry)
                            <tr>
                                <td class="whitespace-nowrap px-6 py-3">
                                    <x-filament::badge :color="$entry->event === \App\Models\LoginHistory::EVENT_LOGOUT ? 'danger' : 'success'">
                                        {{ $entry->eventLabel() }}
                                    </x-filament::badge>
                                </td>
                                <td class="whitespace-nowrap px-6 py-3 text-gray-700 dark:text-gray-300">
                                    {{ $entry->sourceLabel() }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-3 text-gray-700 dark:text-gray-300">
                                    {{ $entry->created_at?->format('d/m/Y H:i') ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-3 text-gray-500 dark:text-gray-400">
                                    {{ $entry->ip_address ?? '—' }}
                                </td>
                                <td class="max-w-xs truncate px-6 py-3 text-gray-500 dark:text-gray-400" title="{{ $entry->user_agent ?? '' }}">
                                    {{ $entry->user_agent ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4">
                {{ $entries->links() }}
            </div>
        @endif
    </div>
</x-filament-panels::page>
