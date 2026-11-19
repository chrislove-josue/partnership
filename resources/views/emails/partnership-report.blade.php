<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapport de partenariat — {{ $churchName }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f0f4ff;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f0f4ff;padding:32px 0;">
    <tr><td align="center">
        <table width="640" cellpadding="0" cellspacing="0"
               style="max-width:640px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;">
            <tr>
                <td style="background:#1F4E78;padding:28px 32px;">
                    <h1 style="margin:0;color:#ffffff;font-size:22px;">Rapport de partenariat — {{ $churchName }}</h1>
                    <p style="margin:8px 0 0;color:#d6e4f0;font-size:14px;">Période : <strong>{{ $periodLabel }}</strong></p>
                </td>
            </tr>
            <tr>
                <td style="padding:28px 32px;">
                    <p style="font-size:15px;color:#374151;margin:0 0 18px;">
                        Total des dons :
                        <strong style="color:#1F4E78;font-size:18px;">{{ $format($totals['amount']) }}</strong>
                        sur <strong>{{ $totals['donationCount'] }}</strong> don(s).
                    </p>

                    <table style="border-collapse:collapse;width:100%;margin:12px 0;" cellpadding="0" cellspacing="0">
                        <thead>
                        <tr style="background:#1F4E78;color:#ffffff;">
                            <th style="padding:8px 10px;border:1px solid #ddd;text-align:left;">Catégorie</th>
                            <th style="padding:8px 10px;border:1px solid #ddd;text-align:center;">Dons</th>
                            <th style="padding:8px 10px;border:1px solid #ddd;text-align:right;">Montant</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($categories as $category)
                            <tr>
                                <td style="padding:6px 10px;border:1px solid #ddd;">{{ $category['name'] }}</td>
                                <td style="padding:6px 10px;border:1px solid #ddd;text-align:center;">{{ $category['count'] }}</td>
                                <td style="padding:6px 10px;border:1px solid #ddd;text-align:right;">{{ $format($category['amount']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" style="padding:6px 10px;border:1px solid #ddd;text-align:center;">Aucun don sur la période</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>

                    <p style="font-size:14px;color:#555;">
                        Partenaires actifs : <strong>{{ $totals['partnersWithDonation'] }}</strong>
                        / {{ $totals['registeredPartners'] }} inscrits
                        (sans don : <strong>{{ $totals['partnersWithoutDonation'] }}</strong>).
                    </p>
                    <p style="font-size:12px;color:#888;">Le rapport Excel détaillé est joint à cet email.</p>
                </td>
            </tr>
            <tr>
                <td style="background:#f8fafc;padding:16px 32px;color:#888;font-size:12px;">
                    Envoyé par {{ $senderName }} — Suivi des Partenariats
                </td>
            </tr>
        </table>
    </td></tr>
</table>
</body>
</html>
