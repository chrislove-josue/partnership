@php
    $amountFormatted = number_format($amount, 0, ',', ' ');
    $donationDate = $date ? \Illuminate\Support\Carbon::parse($date)->translatedFormat('d F Y') : '';
    $partnerName = $partner?->full_name ?? 'Cher partenaire';
    $partnerEmail = $partner?->email;
    $churchName = $church?->name ?? '';
    $categoryName = $category?->name ?? '';
    $churchPastorName = $church?->manager?->name;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation de don</title>
</head>
<body style="margin:0;padding:0;background-color:#f0f4ff;font-family:'Georgia',serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f0f4ff;padding:40px 0;">
    <tr><td align="center">
        <table width="600" cellpadding="0" cellspacing="0"
               style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 32px rgba(79,70,229,0.12);">

            <tr>
                <td style="background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 50%,#a855f7 100%);padding:48px 40px 36px;text-align:center;">
                    <div style="font-size:48px;margin-bottom:12px;">🙏</div>
                    <h1 style="margin:0;color:#ffffff;font-size:26px;font-weight:bold;letter-spacing:0.5px;font-family:Georgia,serif;">Merci pour votre don</h1>
                    <p style="margin:10px 0 0;color:#c4b5fd;font-size:14px;font-style:italic;">« Dieu aime celui qui donne avec joie » — 2 Corinthiens 9:7</p>
                </td>
            </tr>

            <tr><td style="background:linear-gradient(90deg,#4f46e5,#a855f7);height:4px;"></td></tr>

            <tr>
                <td style="padding:40px 40px 24px;">
                    <p style="color:#1e1b4b;font-size:17px;margin:0 0 16px;line-height:1.7;">
                        Bien-aimé(e) <strong style="color:#4f46e5;">{{ $partnerName }}</strong>,
                    </p>
                    <p style="color:#374151;font-size:15px;line-height:1.8;margin:0 0 28px;">
                        C'est avec un cœur rempli de gratitude et de joie que nous accusons réception
                        de votre précieux partenariat en faveur de l'église <strong>{{ $churchName }}</strong>.
                        Votre générosité est un témoignage vivant de votre amour pour Dieu et pour Son œuvre.
                    </p>

                    <table width="100%" cellpadding="0" cellspacing="0"
                           style="background:#f8f7ff;border-radius:12px;overflow:hidden;margin-bottom:20px;border:1px solid #e9d5ff;">
                        <tr><td style="padding:16px 24px 10px;">
                            <p style="margin:0 0 10px;color:#7c3aed;font-size:13px;font-weight:bold;text-transform:uppercase;letter-spacing:1px;">👤 Informations du partenaire</p>
                        </td></tr>
                        <tr><td style="padding:0 24px 4px;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding:8px 0;border-bottom:1px solid #ddd6fe;color:#6b7280;font-size:14px;">Nom complet</td>
                                    <td style="padding:8px 0;border-bottom:1px solid #ddd6fe;color:#1e1b4b;font-size:14px;font-weight:bold;text-align:right;">{{ $partnerName }}</td>
                                </tr>
                                @if($partnerEmail)
                                    <tr>
                                        <td style="padding:8px 0;border-bottom:1px solid #ddd6fe;color:#6b7280;font-size:14px;">Email</td>
                                        <td style="padding:8px 0;border-bottom:1px solid #ddd6fe;color:#4f46e5;font-size:14px;text-align:right;">{{ $partnerEmail }}</td>
                                    </tr>
                                @endif
                                @if($partner?->phone)
                                    <tr>
                                        <td style="padding:8px 0;border-bottom:1px solid #ddd6fe;color:#6b7280;font-size:14px;">Téléphone</td>
                                        <td style="padding:8px 0;border-bottom:1px solid #ddd6fe;color:#1e1b4b;font-size:14px;text-align:right;">{{ $partner->phone }}</td>
                                    </tr>
                                @endif
                                @if($partner?->cellule?->name)
                                    <tr>
                                        <td style="padding:8px 0;border-bottom:1px solid #ddd6fe;color:#6b7280;font-size:14px;">Cellule</td>
                                        <td style="padding:8px 0;border-bottom:1px solid #ddd6fe;color:#1e1b4b;font-size:14px;text-align:right;">{{ $partner->cellule->name }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding:8px 0;color:#6b7280;font-size:14px;">Église</td>
                                    <td style="padding:8px 0;color:#1e1b4b;font-size:14px;text-align:right;">{{ $churchName }}</td>
                                </tr>
                            </table>
                        </td></tr>
                        <tr><td style="padding:10px;"></td></tr>
                    </table>

                    <table width="100%" cellpadding="0" cellspacing="0"
                           style="background:linear-gradient(135deg,#f5f3ff,#ede9fe);border-radius:12px;overflow:hidden;margin-bottom:28px;">
                        <tr><td style="padding:16px 24px 10px;">
                            <p style="margin:0 0 10px;color:#6d28d9;font-size:13px;font-weight:bold;text-transform:uppercase;letter-spacing:1px;">📋 Récapitulatif du don</p>
                        </td></tr>
                        <tr><td style="padding:0 24px;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding:8px 0;border-bottom:1px solid #ddd6fe;color:#6b7280;font-size:14px;">Catégorie</td>
                                    <td style="padding:8px 0;border-bottom:1px solid #ddd6fe;color:#1e1b4b;font-size:14px;text-align:right;">{{ $categoryName }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0;border-bottom:1px solid #ddd6fe;color:#6b7280;font-size:14px;">Date</td>
                                    <td style="padding:8px 0;border-bottom:1px solid #ddd6fe;color:#1e1b4b;font-size:14px;text-align:right;">{{ $donationDate }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:8px 0;color:#6b7280;font-size:14px;">Montant</td>
                                    <td style="padding:8px 0;color:#4f46e5;font-size:16px;font-weight:bold;text-align:right;">{{ $amountFormatted }} FCFA</td>
                                </tr>
                            </table>
                        </td></tr>
                        <tr><td style="padding:10px;"></td></tr>
                    </table>

                    @if($churchPastorName)
                        <table width="100%" cellpadding="0" cellspacing="0"
                               style="background:#f0fdf4;border-left:4px solid #22c55e;border-radius:0 10px 10px 0;margin-bottom:28px;">
                            <tr><td style="padding:16px 20px;">
                                <p style="margin:0 0 6px;color:#15803d;font-size:13px;font-weight:bold;text-transform:uppercase;letter-spacing:1px;">⛪ Pasteur de l'église</p>
                                <p style="margin:0;color:#166534;font-size:15px;font-weight:bold;">{{ $churchPastorName }}</p>
                                <p style="margin:4px 0 0;color:#4ade80;font-size:12px;font-style:italic;">{{ $churchName }}</p>
                            </td></tr>
                        </table>
                    @endif

                    <p style="color:#374151;font-size:15px;line-height:1.8;margin:0 0 28px;">
                        Votre générosité est un témoignage vivant de votre amour pour Dieu et pour Son œuvre.
                        Que le Seigneur vous bénisse abondamment.
                    </p>
                </td></tr>

            <tr>
                <td style="background:#f8f7ff;padding:20px 40px;border-top:1px solid #ede9fe;">
                    <p style="margin:0 0 8px;color:#4f46e5;font-size:14px;font-weight:bold;">Pasteur de groupe — {{ $groupPastor }}</p>
                    <p style="margin:0;color:#9ca3af;font-size:12px;">
                        Cet email vous a été envoyé automatiquement, merci de ne pas y répondre.<br>
                        © {{ date('Y') }} {{ $churchName }} — Tous droits réservés.
                    </p>
                </td>
            </tr>

        </table>
    </td></tr>
</table>
</body>
</html>
