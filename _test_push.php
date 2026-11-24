<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$svc = app(\App\Services\PushNotificationService::class);
echo "configured: ".var_export($svc->isConfigured(), true)."\n";

$ref = new ReflectionClass($svc);
$saProp = $ref->getProperty('serviceAccount');
$saProp->setAccessible(true);
$sa = $saProp->getValue($svc);
echo "client_email: ".$sa['client_email']."\n";
echo "private_key starts: ".substr($sa['private_key'], 0, 40)."\n";
$key = openssl_pkey_get_private($sa['private_key']);
echo "openssl key: ".var_export($key !== false, true)."\n";
if ($key === false) {
    echo "openssl error: ".openssl_error_string()."\n";
}
