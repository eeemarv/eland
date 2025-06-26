web: $(composer config bin-dir)/heroku-php-apache2 public/
cleanup_cache: php bin/console process:cleanup_cache
# cleanup_images: php bin/console process:cleanup_images
cleanup_logs: php bin/console process:cleanup_logs
cleanup: php bin/console process:cleanup
geocode: php bin/console process:geocode
log: php bin/console process:log
mail: php bin/console process:mail
worker: php bin/console process:worker

# new approach: manage background processes with messenger and scheduler
schedule: php bin/console messenger:consume schedule_default -vv --time-limit=3600 --memory-limit=128M
worker2: php bin/console messenger:consume mail_hi mail_mi mail_lo async -vv --time-limit=3611 --memory-limit=128M
images: php bin/console messenger:consume images -vv --time-limit=3622 --memory-limit=128M
# release is a special command that runs before the deploy
# it is used to clear caches and calculate asset hashes
# https://dokku.com/docs/advanced-usage/deployment-tasks/#procfile-release-command
release: php bin/console app:release -vv

# development
dev: php -S 0.0.0.0:$PORT -t public/ -c public/.user.ini
test_periodic_overview: php bin/console test:periodic_overview x
test_expired_messages: php bin/console test:expired_messages x
