eLAS-Heroku
=====

Fork of [eLAS](http://www.elasproject.org/) (version 3.1.17) to run on Heroku.


Checklist
---------

#####Cron
    heroku addons:add scheduler
    set every 10 min.  `$ php -r "echo file_get_contents('http://yourdomain.com/cron/cron.php');"`
    Only one cronjob is needed for all installed domains (unlike eLAS). Just choose one domain or the Heroku app URL.

#####Domain
    Configure your domain with a CNAME to the Heroku app URL.
    set a config var for each domain to a database.
    heroku config:set ELAS_DOMAIN_SESSION_EXAMPLE__COM=RED

#####AWS S3
    Create a file bucket (in your region) on Amazon S3 and put the config in environment vars.
    heroku config:set AWS_ACCESS_KEY_ID=aaa AWS_SECRET_ACCESS_KEY=bbb S3_BUCKET=ccc

#####Redistogo
    heroku addons:add redistogo

#####Mandrill
    heroku addons:add mandrill

Environment Vars
------
* AWS_ACCESS_KEY
* AWS_SECRET_ACCESS_KEY
* S3_BUCKET

* REDISTOGO_URL: addon redistogo (redis server)

* MANDRILL_USERNAME: addon mandrill (smtp server)
* MANDRILL_PASSWORD

* DATABASE_URL: default database (postgres) when no domain session name / database color is set.

* ELAS_DOMAIN_SESSION_domain: session name by domain (must be the color name of the database!)

    `Dots in <domain> are replaced by double underscore __`
    `Hyphens in <domain> are replaced by triple underscore ___`

    example:
    set environment variable:
        `heroku config:set ELAS_DOMAIN_SESSION_E___EXAMPLE__COM=PURPLE`

The session name is also:
  * the color name of the database.
  * prefix of the files in S3 cloud storage
  * prefix of the keys in Redis.

* ELAS_TIMEZONE: defaults to 'Europe/Brussels'
* ELAS_DEBUG
* ELAS_DB_DEBUG
* ELAS_MASTER_PASSWORD: sha512 encoded password for 'master' (role admin) -> access to all lets groups.


Steps moving a group from eLAS to eLAS-Heroku
----------

* Set your domain in DNS with CNAME to the domain of the Heroku app.
* Accept the domain in Heroku. 
* Copy the image files from folders msgpictures and userpictures to your bucket in S3 without the directory path.
* Create a postgres database.
* Import the data in the database from a pg_dump
* Set the domain variable ELAS_DOMAIN_SESSION_domain=colorname-of-the-database

The images files will automatically be renamed the first time the cronjob is running.
