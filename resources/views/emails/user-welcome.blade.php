<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f9fafb; padding:20px 0; font-family: Arial, sans-serif;">
    <tr>
        <td align="center">
            <table width="600" cellpadding="0" cellspacing="0" border="0" style="background:#ffffff; border:1px solid #e5e7eb;">

                <tr>
                    <td align="center" style="background:#4f46e5; padding:20px;">
                        <span style="font-size:22px; font-weight:bold; color:#ffffff; font-family: Arial, sans-serif;">
                            Suivi des Partenariats
                        </span>
                    </td>
                </tr>

                <tr>
                    <td style="padding:20px; color:#111827; font-size:16px; line-height:24px;">
                        <p>Bonjour {{ $userEmail }},</p>
                        <p>{{ $adminName }} vous a créé un compte sur Suivi des Partenariats.</p>

                        <table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:20px 0; background:#f3f4f6; padding:15px;">
                            <tr>
                                <td style="font-size:14px; color:#111827;">
                                    <p><strong>Email :</strong> {{ $userEmail }}</p>
                                    <p><strong>Mot de passe temporaire :</strong> {{ $tempPassword }}</p>
                                </td>
                            </tr>
                        </table>

                        <table cellpadding="0" cellspacing="0" border="0" align="center" style="margin:20px 0;">
                            <tr>
                                <td bgcolor="#4f46e5" style="border-radius:4px;">
                                    <a href="{{ $magicLink }}"
                                       style="display:inline-block; padding:12px 24px; color:#ffffff; text-decoration:none; font-size:16px; font-weight:bold; font-family: Arial, sans-serif;">
                                        Se connecter
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:20px 0; background:#fef3c7; padding:15px;">
                            <tr>
                                <td style="font-size:14px; color:#92400e;">
                                    <p style="margin:0 0 8px 0;">
                                        ⚠️ <strong>Action requise :</strong> Pour des raisons de sécurité, vous devez modifier votre mot de passe temporaire après votre première connexion pour jouir de votre espace d'administration.
                                    </p>
                                    <p style="margin:0 0 8px 0;">
                                        Cliquez sur le bouton ci-dessous pour accéder à votre profil et changer votre mot de passe.
                                    </p>
                                    <p style="margin:0;">
                                        🔄 <strong>Important :</strong> Après avoir modifié votre mot de passe, déconnectez-vous puis reconnectez-vous avec vos nouveaux identifiants pour que les changements soient pris en compte.
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <table cellpadding="0" cellspacing="0" border="0" align="center" style="margin:20px 0;">
                            <tr>
                                <td bgcolor="#f59e0b" style="border-radius:4px;">
                                    <a href="{{ rtrim(config('app.url'), '/') }}/dashboard/profile"
                                       style="display:inline-block; padding:12px 24px; color:#ffffff; text-decoration:none; font-size:16px; font-weight:bold; font-family: Arial, sans-serif;">
                                        🔐 Modifier mon mot de passe
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="font-size:13px; color:#6b7280; margin-top:20px;">
                            Cordialement,<br/>
                            L'équipe Suivi des Partenariats
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
