<?php declare(strict_types=1);

require 'vendor/autoload.php';

use Minishlink\WebPush\VAPID;

$keys = VAPID::createVapidKeys();

echo 'VAPID_PUBLIC_KEY=' . $keys['publicKey'];
echo PHP_EOL;
echo 'VAPID_PRIVATE_KEY=' . $keys['privateKey'];
echo PHP_EOL;
