<?php

return [
    /*
     * Domaine utilisé pour les comptes de personnel générés par
     * `php artisan church:ensure-staff`. Le domaine par défaut est réservé
     * aux tests/démonstrations : modifiez-le si les personnels doivent
     * recevoir des emails (réinitialisation de mot de passe, par exemple).
     */
    'email_domain' => env('STAFF_EMAIL_DOMAIN', 'churchmanager.test'),

    /*
     * Mot de passe initial des comptes de personnel créés par la commande.
     * À changer après la première connexion.
     */
    'default_password' => env('STAFF_DEFAULT_PASSWORD', 'azerty123'),
];
