<x-filament-panels::page>
    {{ $this->form }}

    @php
        $report = $this->getReport();
        $format = fn (float|int $amount): string => app(\App\Services\PartnershipReportService::class)->formatAmount($amount);
        $formatDate = fn (?string $date): string => $date
            ? \Illuminate\Support\Carbon::parse($date)->format('d/m/Y')
            : '—';
    @endphp

    @if (isset($report['error']))
        <div class="rp-alert">{{ $report['error'] }}</div>
    @else
        @php
            $section = (string) ($report['report_section'] ?? '');
            $showSection = fn (string $key): bool => $section === '' || $section === $key;
        @endphp

        <div class="rp-stats">
            <div class="rp-stat">
                <p class="rp-label">Total des dons</p>
                <p class="rp-value rp-value-primary">{{ $format($report['totals']['amount']) }}</p>
            </div>
            <div class="rp-stat">
                <p class="rp-label">Nombre de dons</p>
                <p class="rp-value">{{ $report['totals']['donationCount'] }}</p>
            </div>
            <div class="rp-stat">
                <p class="rp-label">Partenaires ayant donné</p>
                <p class="rp-value">{{ $report['totals']['partnersWithDonation'] }} / {{ $report['totals']['registeredPartners'] }}</p>
            </div>
            <div class="rp-stat">
                <p class="rp-label">Sans don sur la période</p>
                <p class="rp-value">{{ $report['totals']['partnersWithoutDonation'] }}</p>
            </div>
        </div>

        @if ($showSection('categories'))
            <div class="rp-card">
                <div class="rp-card-head">
                    <h2 class="rp-card-title">Dons par catégorie — {{ $report['period']['label'] }}</h2>
                </div>
                <table class="rp-table">
                    <thead>
                    <tr>
                        <th>Catégorie</th>
                        <th class="rp-center">Nombre de dons</th>
                        <th class="rp-right">Montant total</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($report['categories'] as $category)
                        <tr>
                            <td class="rp-strong">{{ $category['name'] }}</td>
                            <td class="rp-center">{{ $category['count'] }}</td>
                            <td class="rp-right rp-strong">{{ $format($category['amount']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="rp-empty">Aucune catégorie pour ces filtres.</td>
                        </tr>
                    @endforelse
                    </tbody>
                    <tfoot>
                    <tr>
                        <td>TOTAL</td>
                        <td class="rp-center">{{ $report['totals']['donationCount'] }}</td>
                        <td class="rp-right">{{ $format($report['totals']['amount']) }}</td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        @endif

        @if ($showSection('partners'))
            <div class="rp-card">
                <div class="rp-card-head">
                    <h2 class="rp-card-title">Partenaires ayant donné</h2>
                </div>
                <table class="rp-table">
                    <thead>
                    <tr>
                        <th>Partenaire</th>
                        <th>Cellule</th>
                        <th class="rp-center">Nb dons</th>
                        <th class="rp-right">Total doné</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($report['partners'] as $partner)
                        <tr>
                            <td class="rp-strong">{{ $partner['name'] }}</td>
                            <td>{{ $partner['cellule'] ?? '—' }}</td>
                            <td class="rp-center">{{ $partner['count'] }}</td>
                            <td class="rp-right rp-strong">{{ $format($partner['amount']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="rp-empty">Aucun partenaire pour ces filtres.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        @if ($showSection('without'))
            <div class="rp-card">
                <div class="rp-card-head">
                    <h2 class="rp-card-title">Partenaires sans don sur la période</h2>
                </div>
                <table class="rp-table">
                    <thead>
                    <tr>
                        <th>Partenaire</th>
                        <th>Cellule</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($report['partnersWithoutDonation'] as $partner)
                        <tr>
                            <td class="rp-strong">{{ $partner['name'] }}</td>
                            <td>{{ $partner['cellule'] ?? '—' }}</td>
                            <td>{{ $partner['email'] ?? '—' }}</td>
                            <td>{{ $partner['phone'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="rp-empty">Aucun partenaire sans don pour ces filtres.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        @if ($showSection('donations'))
            <div class="rp-card">
                <div class="rp-card-head">
                    <h2 class="rp-card-title">Détail des dons</h2>
                </div>
                <table class="rp-table">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Partenaire</th>
                        <th>Catégorie</th>
                        <th class="rp-right">Montant</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($report['donations'] as $donation)
                        <tr>
                            <td class="rp-nowrap">{{ $formatDate($donation['date'] ?? null) }}</td>
                            <td class="rp-strong">{{ $donation['partner'] ?? '—' }}</td>
                            <td>{{ $donation['category'] ?? '—' }}</td>
                            <td class="rp-right rp-strong">{{ $format($donation['amount']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="rp-empty">Aucun don pour ces filtres.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</x-filament-panels::page>
