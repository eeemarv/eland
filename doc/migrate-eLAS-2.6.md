
Migrating from eLAS 2.x (MySQL to PostgreSQL)

mysqldump -u username -p --default-character-set=utf8 --result-file=newgroup.sql database

---------------------

create a local mysql database (newgroup)

shell> mysql -u root -p
(enter password)

mysql> create database newgroup;
mysql> use newgroup;
mysql> \. newgroup.sql
mysql> truncate table city_distance;
mysql> truncate table tokens;
mysql> truncate table eventlog;
mysql>

alter table config change `default` `default` varchar(1);
update config set `default` = 'f' where `default` = '0';
update config set `default` = 't' where `default` = '1';

alter table messages change exp_user_warn exp_user_warn varchar(1);
update messages set exp_user_warn = 'f' where exp_user_warn = '0';
update messages set exp_user_warn = 't' where exp_user_warn = '1';

alter table messages change exp_admin_warn exp_admin_warn varchar(1);
update messages set exp_admin_warn = 'f' where exp_admin_warn = '0';
update messages set exp_admin_warn = 't' where exp_admin_warn = '1';

alter table messages change local local varchar(1);
update messages set local = 'f' where local = '0';
update messages set local = 't' where local = '1';

alter table news change approved approved varchar(1);
update news set approved = 'f' where approved = '0';
update news set approved = 't' where approved = '1';

alter table news change published published varchar(1);
update news set published = 'f' where published = '0';
update news set published = 't' where published = '1';

alter table news change sticky sticky varchar(1);
update news set sticky = 'f' where sticky = '0';
update news set sticky = 't' where sticky = '1';

alter table ostatus_queue change protect protect varchar(1);
update ostatus_queue set protect = 'f' where protect = '0';
update ostatus_queue set protect = 't' where protect = '1';

alter table users change cron_saldo cron_saldo varchar(1);
update users set cron_saldo = 'f' where cron_saldo = '0';
update users set cron_saldo = 't' where cron_saldo = '1';

alter table users change pwchange pwchange varchar(1);
update users set pwchange = 'f' where pwchange = '0';
update users set pwchange = 't' where pwchange = '1';

alter table users change locked locked varchar(1);
update users set locked = 'f' where locked = '0';
update users set locked = 't' where locked = '1';

shell> mysqldump -u root -p --no-create-info --skip-triggers --no-create-db --compact --compatible=postgresql --default-character-set=utf8 --result-file=mysql.sql newgroup

--------------
download perl script: http://pgfoundry.org/frs/download.php/1535/mysql2pgsql.perl

shell> perl mysql2pgsql.perl mysql.sql data.sql


make a structure-only (tables and colummns) of an existing eLAS 3.1.17, use a test-group for safety (schema name is different than a schema name of a live group)

shell> pg_dump -d _database_ -U _user_ -h _hostname_ -p 5432 -W --no-owner --no-acl --schema=_schemaname_ --schema-only > structure.sql
(enter password)

edit the schema name to 'public' in the dump you just made (structure.sql), so from line ~14 it will be:

CREATE SCHEMA public;
SET search_path = public, pg_catalog;


log in the postgres database

shell> psql -d _database_ -U _user_ -h _hostname_ -p 5432
(enter password)

The public schema should be empty but doesn't have to be present as it will be created by importing the structure.

psql> \i structure.sql

import data

psql> \i data.sql

rename the public schema

psql> alter schema public rename to newgroup;

(import images / bind schema to url as before.)

