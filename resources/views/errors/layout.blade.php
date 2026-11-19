<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">

    <title>@yield('title', 'Erreur') — ChurchManager</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script>
        // Même logique que le sélecteur de thème Filament : clair par défaut.
        (() => {
            const theme = localStorage.getItem('theme') ?? 'light'
            if (theme === 'dark' || (theme === 'system' && matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark')
            }
        })()
    </script>

    <style>
        :root { color-scheme: light; }
        html.dark { color-scheme: dark; }

        * { box-sizing: border-box; }

        html, body { min-height: 100%; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1.5rem;
            padding: 2rem 1.25rem;
            background-color: #f9fafb;
            color: #111827;
            font-family: Geist, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        html.dark body { background-color: #030712; color: #f9fafb; }

        .brand {
            font-size: 1.125rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            background: linear-gradient(90deg, #9333ea, #4f46e5);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            text-decoration: none;
        }

        .card {
            width: 100%;
            max-width: 34rem;
            padding: 2.75rem 2rem 2.25rem;
            text-align: center;
            background-color: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            box-shadow: 0 18px 40px -24px rgba(17, 24, 39, 0.25);
        }

        html.dark .card {
            background-color: #111827;
            border-color: #374151;
            box-shadow: 0 18px 40px -24px rgba(0, 0, 0, 0.8);
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            color: #7e22ce;
            background-color: #faf5ff;
            border: 1px solid #e9d5ff;
        }

        html.dark .badge { color: #d8b4fe; background-color: #3b0764; border-color: #581c87; }

        .code {
            margin: 1rem 0 0.25rem;
            font-size: 5rem;
            line-height: 1;
            font-weight: 700;
            letter-spacing: -0.06em;
            background: linear-gradient(90deg, #9333ea, #4f46e5);
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
        }

        .title {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        .message {
            margin: 0.75rem auto 0;
            max-width: 27rem;
            font-size: 0.9375rem;
            line-height: 1.6;
            color: #4b5563;
        }

        html.dark .message { color: #d1d5db; }

        .hint {
            margin: 0.5rem auto 0;
            max-width: 27rem;
            font-size: 0.8125rem;
            line-height: 1.6;
            color: #6b7280;
        }

        html.dark .hint { color: #9ca3af; }

        .actions {
            margin-top: 2rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.75rem;
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            transition: opacity 0.15s ease;
        }

        .button:hover { opacity: 0.88; }

        .button-primary {
            color: #fff;
            background: linear-gradient(90deg, #9333ea, #4f46e5);
            box-shadow: 0 8px 20px -10px rgba(79, 70, 229, 0.7);
        }

        .button-secondary {
            color: #374151;
            background-color: #fff;
            border: 1px solid #e5e7eb;
        }

        html.dark .button-secondary { color: #e5e7eb; background-color: #1f2937; border-color: #4b5563; }

        .footer { font-size: 0.75rem; color: #9ca3af; }

        @media (max-width: 640px) {
            .card { padding: 2rem 1.25rem 1.75rem; }
            .code { font-size: 4rem; }
            .title { font-size: 1.25rem; }
        }
    </style>
</head>
<body>
    <a class="brand" href="/">ChurchManager</a>

    <main class="card" role="alert">
        @php
            $status = $status ?? (($exception ?? null) instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                ? $exception->getStatusCode()
                : 500);
            $status = (int) $status;
            $family = substr((string) $status, 0, 1).'xx';
            $isAuthError = in_array($status, [401, 403], true);
            $actionUrl = $actionUrl ?? ($isAuthError ? '/login' : '/dashboard');
            $actionLabel = $actionLabel ?? ($isAuthError ? 'Se connecter' : 'Retour au tableau de bord');
            $secondaryUrl = $isAuthError ? '/dashboard' : '/login';
            $secondaryLabel = $isAuthError ? 'Tableau de bord' : 'Connexion';
        @endphp

        <span class="badge">Erreur {{ $family }}</span>
        <p class="code">{{ $status }}</p>
        <h1 class="title">@yield('title', 'Une erreur est survenue')</h1>
        <p class="message">@yield('message', 'Une erreur inattendue est survenue. Réessayez dans quelques instants.')</p>
        <p class="hint">@yield('hint', "Si le problème persiste, contactez l'administrateur.")</p>

        <div class="actions">
            <a class="button button-primary" href="{{ $actionUrl }}">{{ $actionLabel }}</a>
            <a class="button button-secondary" href="{{ $secondaryUrl }}">{{ $secondaryLabel }}</a>
            <a class="button button-secondary" href="/">Accueil</a>
        </div>
    </main>

    <p class="footer">ChurchManager — Partenariat et suivi des dons</p>
</body>
</html>
