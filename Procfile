dev: php -S 0.0.0.0:$PORT -t web/
web: $(composer config bin-dir)/heroku-php-apache2 web/
worker: php process/worker.php
log: php process/log.php
mail: php process/mail.php
geocode: php process/geocode.php
cleanup_cache: php process/cleanup_cache.php
cleanup_logs: php process/cleanup_logs.php
cleanup_images: php process/cleanup_images.php
fetch_elas_intersystem: php process/fetch_elas_intersystem.php
process_mail: php bin/console process:mail
