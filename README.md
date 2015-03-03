eLAS-Heroku
=====

Fork of [eLAS](http://www.elasproject.org/) (version 3.1.17) to run on Heroku. 


Checklist
---------

Cron 


Environment Vars
------

* REDISTOGO_URL: addon redistogo (redis server)

* MANDRILL_USERNAME: addon mandrill (smtp server)
* MANDRILL_PASSWORD

* DATABASE_URL: default database (postgres) when no domain session name / database color is set. 

* ELAS_DOMAIN_SESSION_<domain>: session name by domain (must be the color name of the database!)

Dots in <domain> are replaced by double underscore __
Hyphens in <domain> are replaced by triple underscore ___

    example:
    To link e-example.com to a session set environment variable
    ELAS_DOMAIN_SESSION_E___EXAMPLE__COM=<session_name>

The session name is also:
  * the color name of the database!
  * prefix of the image files: <session name>_U_<ID>.JPG for profile images, <session_name>_M_<ID>.JPG for message images.
  * prefix of the keys in Redis.



* ELAS_TIMEZONE: defaults to 'Europe/Brussels'
* ELAS_DEBUG
* ELAS_DB_DEBUG

Session name
-----
The session name is based on the domain stripped from non-alphanumeric chars.
Take care it's unique for each domain!

