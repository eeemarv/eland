dev: php -S 0.0.0.0:$PORT -t web/
web: $(composer config bin-dir)/heroku-php-apache2 web/
cleanup_cache: php bin/console process:cleanup_cache
cleanup_images: php bin/console process:cleanup_images
cleanup_logs: php bin/console process:cleanup_logs
fetch_elas_intersystem: php bin/console process:fetch_elas_intersystem
geocode: php bin/console process:geocode
log: php bin/console process:log
mail: php bin/console process:mail
worker: php bin/console process:worker
