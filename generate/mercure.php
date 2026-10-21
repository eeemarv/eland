<?php declare(strict_types=1);

echo 'MERCURE_JWT_SECRET="';
echo base64_encode(random_bytes(32));
echo '"';
echo PHP_EOL;
