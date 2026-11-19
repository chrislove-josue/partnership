@php
    $cookieName = config('session.consent_cookie');
@endphp

<div
    wire:ignore
    class="cookie-consent"
    x-cloak
    x-data="{
        visible: ! document.cookie.split('; ').some((entry) => entry.startsWith('{{ $cookieName }}=')),
        acknowledge() {
            const secure = location.protocol === 'https:' ? '; Secure' : '';
            document.cookie = '{{ $cookieName }}=acknowledged; Max-Age=15552000; Path=/; SameSite=Lax' + secure;
            this.visible = false;
        },
    }"
    x-show="visible"
    role="region"
    aria-label="Cookies et confidentialité"
>
    <div class="cookie-consent__card">
        <div class="cookie-consent__body">
            <p class="cookie-consent__title">Cookies et confidentialité</p>
            <p class="cookie-consent__text">
                Ce site utilise uniquement des cookies nécessaires à la connexion, à la sécurité et à la mémorisation de vos préférences. Aucun cookie publicitaire ou de suivi n’est déposé.
            </p>
            <details class="cookie-consent__details">
                <summary>Voir les cookies utilisés</summary>
                <ul>
                    <li><strong>{{ config('session.cookie') }}</strong> : session de connexion.</li>
                    <li><strong>XSRF-TOKEN</strong> : protection des formulaires contre les attaques CSRF.</li>
                    <li><strong>{{ $cookieName }}</strong> : mémorisation de votre prise de connaissance (6 mois).</li>
                </ul>
            </details>
        </div>
        <button type="button" class="cookie-consent__button" x-on:click="acknowledge()">
            J’ai compris
        </button>
    </div>
</div>
