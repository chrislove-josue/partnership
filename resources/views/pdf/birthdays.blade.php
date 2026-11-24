<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Anniversaires du mois</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 16px; color: #0f2b46; margin-bottom: 4px; }
        .subtitle { font-size: 11px; color: #6b7280; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; }
        th { background: #0f2b46; color: #fff; text-align: left; font-weight: 600; }
        td.center { text-align: center; }
        tr:nth-child(even) { background: #f9fafb; }
        .empty { text-align: center; padding: 12px; color: #6b7280; }
        .footer { margin-top: 12px; font-size: 9px; color: #9ca3af; text-align: right; }
    </style>
</head>
<body>
    <h1>Anniversaires du mois — {{ $label }}</h1>
    <p class="subtitle">{{ $count }} anniversaire(s) pour les églises accessibles.</p>

    <table>
        <thead>
            <tr>
                <th style="width: 6%">Jour</th>
                <th style="width: 26%">Partenaire</th>
                <th style="width: 20%">Église</th>
                <th style="width: 16%">Cellule</th>
                <th style="width: 12%">Téléphone</th>
                <th style="width: 8%">Âge</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td class="center">{{ $item['day'] }}</td>
                    <td>{{ $item['name'] }}@if ($item['is_user']) (compte)@endif</td>
                    <td>{{ $item['church'] ?? '—' }}</td>
                    <td>{{ $item['cellule'] ?? '—' }}</td>
                    <td>{{ $item['phone'] ?? '—' }}</td>
                    <td class="center">{{ $item['age'] !== null ? $item['age'].' ans' : '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty">Aucun anniversaire dans ce mois pour vos églises.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="footer">Généré le {{ now()->format('d/m/Y H:i') }} — ChurchManager</p>
</body>
</html>
