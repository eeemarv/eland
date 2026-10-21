<?php declare(strict_types=1);

echo 'APP_SECRETS_ENCRYPTION_KEY="';
echo base64_encode(random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES));
echo '"';
echo PHP_EOL;
