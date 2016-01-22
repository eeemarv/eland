##Migrating a group from eLAS 3.x to eLAND

* Set your domain in DNS with CNAME to the domain of the Heroku app.
* Add the domain in Heroku with command
```shell
heroku domains:add my-domain.com
```
note that wildcards can be set on heroku.  
```shell
heroku domains:add *.example.com
```
will add all subdomains of example.com
* To import the database of the letsgroup use postgres command psql to log in with your local computer on the postgres server directly. Get host, port, username and password from the dsn of DATABASE_URL which you can find with `heroku config`. (or on the Heroku website)
In eLAND all letsgroups are stored as schemas in one database.
You can import a dump file you made previously with pg_dump with options --no-acl --no-owner (no custom format).
```sql
\i myletsgroupdumpfile.sql
```
The tables of the imported letsgroup are now in the default schema named public.
You can truncate the city_distance table which is not used anymore and which is very big. (More than a 1M rows.)
```sql
TRUNCATE TABLE city_distance;
```
In eLAS there are only 2 levels of access for contacts. Public and private. In eLAND public is further divided in 'members' and 'interlets'. To keep consistent the 'public' access level of eLAS should be transformed into the 'interlets' access level of eLAND.
```sql
UPDATE contact SET flag_public = 2 where flag_public = 1;
```
Rename then the public schema to the letsgroup code
```sql
ALTER SCHEMA public RENAME TO abc;
```
This way of importing letsgroups leaves the already present letsgroups data untouched. This can not be done with the Heroku tools.
Now there is no public schema anymore. this is no problem, but you need schema public to be present when you import the next letsgroup.
```sql
CREATE SCHEMA public;
```

* Match a domain to a schema with config variable `SCHEMA_domain=schema`
In domain all characters must be converted to uppercase. A dot must be converted to a double underscore. A h
yphen must be converted to a triple underscore and a colon (for defining port number) with quadruple underscore.

* Resize all image files from folders msgpictures and userpictures (image files in eLAS were up to 2MB) at least down to 200kB, but keep the same filename (the extension may be renamed to one of jpg, JPG, jpeg, JPEG).
Upload the image files to your S3 bucket (no directory path. The image files are prefixed automatically in the next step).
Make the image files public.
* Log in with admin rights to your website (you can use the master login and password) and go to path `/init.php` The image files get renamed with a new hash and orphaned files will be cleaned up.
The files get prefixed with the schema name and the user or message id. All extensions become jpg.
ie.
    abc_u_41_c533e0ef9491c7c0b22fdf4a385ab47e1bb49eec.jpg
    abc_m_71_a84d14fb1bfbd1f9426a2a9ca5f5525d1e46f15e.jpg
