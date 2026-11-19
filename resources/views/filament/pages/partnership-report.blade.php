<x-filament-panels::page>
    {{ $this->form }}

    @php
        $report = $this->getReport();
        $format = fn (float|int $amount): string => app(\App\Services\PartnershipReportService::class)->formatAmount($amount);
    @endphp

    @if (isset($report['error']))
        <div class="fi-alert fi-alert-danger mt-4 rounded-lg bg-danger-50 p-4 text-sm text-danger-700 dark:bg-danger-950 dark:text-danger-200">
            {{ $report['error'] }}
        </div>
    @else
        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                <p class="text-sm text-gray-500 dark:text-gray-400">Total des dons</p>
                <p class="mt-1 text-2xl font-bold text-primary-600 dark:text-primary-400">{{ $format($report['totals']['amount']) }}</p>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                <p class="text-sm text-gray-500 dark:text-gray-400">Nombre de dons</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $report['totals']['donationCount'] }}</p>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                <p class="text-sm text-gray-500 dark:text-gray-400">Partenaires ayant donné</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $report['totals']['partnersWithDonation'] }} / {{ $report['totals']['registeredPartners'] }}</p>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                <p class="text-sm text-gray-500 dark:text-gray-400">Sans don sur la période</p>
                <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ $report['totals']['partnersWithoutDonation'] }}</p>
            </div>
        </div>

        <div class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Dons par catégorie — {{ $report['period']['label'] }}</h2>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                <tr>
                    <th class="px-5 py-3">Catégorie</th>
                    <th class="px-5 py-3 text-center">Nombre de dons</th>
                    <th class="px-5 py-3 text-right">Montant total</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse ($report['categories'] as $category)
                    <tr>
                        <td class="px-5 py-3 font-medium text-gray-900 dark:text-white">{{ $category['name'] }}</td>
                        <td class="px-5 py-3 text-center text-gray-600 dark:text-gray-300">{{ $category['count'] }}</td>
                        <td class="px-5 py-3 text-right text-gray-900 dark:text-white">{{ $format($category['amount']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-5 py-6 text-center text-gray-500">Aucun don sur la période</td>
                    </tr>
                @endforelse
                </tbody>
                <tfoot class="bg-gray-50 font-semibold dark:bg-gray-800">
                <tr>
                    <td class="px-5 py-3">TOTAL</td>
                    <td class="px-5 py-3 text-center">{{ $report['totals']['donationCount'] }}</td>
                    <td class="px-5 py-3 text-right">{{ $format($report['totals']['amount']) }}</td>
                </tr>
                </tfoot>
            </table>
        </div>

        <div class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Partenaires ayant donné</h2>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                <tr>
                    <th class="px-5 py-3">Partenaire</th>
                    <th class="px-5 py-3">Cellule</th>
                    <th class="px-5 py-3 text-center">Nb dons</th>
                    <th class="px-5 py-3 text-right">Total doné</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse ($report['partners'] as $partner)
                    <tr>
                        <td class="px-5 py-3 font-medium text-gray-900 dark:text-white">{{ $partner['name'] }}</td>
                        <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $partner['cellule'] ?? '—' }}</td>
                        <td class="px-5 py-3 text-center text-gray-600 dark:text-gray-300">{{ $partner['count'] }}</td>
                        <td class="px-5 py-3 text-right text-gray-900 dark:text-white">{{ $format($partner['amount']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-6 text-center text-gray-500">Aucun partenaire avec don sur la période</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($report['partnersWithoutDonation'] !== [])
            <div class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Partenaires sans don sur la période</h2>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="px-5 py-3">Partenaire</th>
                        <th class="px-5 py-3">Cellule</th>
                        <th class="px-5 py-3">Email</th>
                        <th class="px-5 py-3">Téléphone</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($report['partnersWithoutDonation'] as $partner)
                        <tr>
                            <td class="px-5 py-3 font-medium text-gray-900 dark:text-white">{{ $partner['name'] }}</td>
                            <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $partner['cellule'] ?? '—' }}</td>
                            <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $partner['email'] ?? '—' }}</td>
                            <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $partner['phone'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</x-filament-panels::page>
