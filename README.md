eLAS-Heroku
=====

Fork of [eLAS](http://www.elasproject.org/) (version 3.1.17) to run on Heroku.

Checklist
---------

#####Cron
    heroku addons:add scheduler
    set every 10 min.  `$ php -r "echo file_get_contents('http://yourdomain.com/cron/cron.php');"`
    Only one cronjob is needed for all installed domains (as opposed to eLAS). Just choose one domain or the Heroku app URL.

#####Domain
    Configure your domain with a CNAME to the Heroku app URL.

Environment Vars
------

* REDISTOGO_URL: addon redistogo (redis server)

* MANDRILL_USERNAME: addon mandrill (smtp server)
* MANDRILL_PASSWORD

* DATABASE_URL: default database (postgres) when no domain session name / database color is set.

* ELAS_DOMAIN_SESSION_<domain>: session name by domain (must be the color name of the database!)

    `Dots in <domain> are replaced by double underscore __`
    `Hyphens in <domain> are replaced by triple underscore ___`

    example:
    e-example.com
    ELAS_DOMAIN_SESSION_E___EXAMPLE__COM=<session_name>

    set environment variable:
        `heroku config:set ELAS_DOMAIN_SESSION_E___EXAMPLE__COM=PURPLE`

The session name is also:
  * the color name of the database.
  * prefix of the image files: <session name>_U_<ID>.JPG for profile images, <session_name>_M_<ID>.JPG for message images.
  * prefix of the keys in Redis.

* ELAS_TIMEZONE: defaults to 'Europe/Brussels'
* ELAS_DEBUG
* ELAS_DB_DEBUG
* ELAS_MASTER_PASSWORD: sha512 encoded password for master (role admin)
