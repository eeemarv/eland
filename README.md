eLAS-Heroku
=====

Fork of [eLAS](http://www.elasproject.org/) (version 3.1.17) to run on Heroku. 


Checklist
---------

Cron


Environment Vars
------

REDISTOGO_URL
MANDRILL_USERNAME
MANDRILL_PASSWORD

DATABASE_URL : default database


ELAS_TIMEZONE (defaults to 'Europe/Brussels')
ELAS_DEBUG
DB_DEBUG

Session name
-----
The session name is based on the domain stripped from non-alphanumeric chars.
Take care it's unique for each domain!

