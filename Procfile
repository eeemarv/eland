web: $(composer config bin-dir)/heroku-php-apache2 public/
cleanup_cache: php bin/console process:cleanup_cache
cleanup_images: php bin/console process:cleanup_images
cleanup_logs: php bin/console process:cleanup_logs
cleanup: php bin/console process:cleanup
geocode: php bin/console process:geocode
log: php bin/console process:log
mail: php bin/console process:mail
worker: php bin/console process:worker

# development
dev: php -S 0.0.0.0:$PORT -t public/ -c public/.user.ini
test_periodic_overview: php bin/console test:periodic_overview x
test_expired_messages: php bin/console test:expired_messages x
