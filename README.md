#eLAS-Heroku

* Is a fork of [eLAS](http://www.elasproject.org/) (version 3.1.17) to run on Heroku.
* is hosted for free for all letsgroups.
* is only available in Dutch.
* see the [documentation](http://letsa.net) in Dutch.

Here some info following on how to set up the hosting.

##Checklist

###Cron
```shell
heroku addons:add scheduler
```
Set every 10 min.  
```shell
$ php -r "echo file_get_contents('http://yourdomain.com/cron.php');"
```
Only one cronjob is needed for all installed domains (unlike eLAS). Just choose one domain.

###Domain
Configure your domain with a CNAME to the Heroku app URL.
set a config var for each domain to the name of the schema in the database
```shell
heroku config:set SCHEMA_EXAMPLE__COM=examplecom
```
The environment variable SCHEMA_domain: couples a domain to a schema

    `Dots in domain are replaced by double underscore __`
    `Hyphens in domain are replaced by triple underscore ___`
    `Colons in domain are replaced by quadruple underscore ____`

    i.e couple e-example.com with schema `eexample`
    ```shell
        heroku config:set SCHEMA_E___EXAMPLE__COM=eexample
    ```
    Also add the domain to Heroku:
    ```shell
    heroku domains:add e.example.com
    ```

    i.e localhost:40000 on php development server
    ```shell
        SCHEMA_LOCALHOST____40000=abc (define here other environment variables like DATABASE_URL) php -d variables_order=EGPCS -S localhost:40000
    ```

The schema name is also:
  * the name of the session
  * prefix of the files in S3 cloud storage
  * prefix of the keys in Redis.

By convention the schema is named after the so called system tag or letscode of the letsgroup.


###AWS S3
Create a IAM user on AWS with access only to S3. Then create 3 buckets in your region for images, documents and 3th party (javascript + css) libraries.
See [./includes/inc_default.php] for which libraries are to be uploaded. 
The buckets should have the same name as the url.

```shell
heroku config:set S3_IMG=img.letsa.net S3_DOC=doc.letsa.net S3_RES=res.letsa.net
heroku config:set AWS_ACCESS_KEY_ID=aaa AWS_SECRET_ACCESS_KEY=bbb
```

Create CNAME records to these buckets

img.letsa.net CNAME record for img.letsa.net.s3-eu-central-1.amazonaws.com

See (http://docs.aws.amazon.com/AmazonS3/latest/dev/VirtualHosting.html)

###Redis cloud
```shell
heroku addons:add rediscloud:30
```

###Mandrill
```shell
heroku addons:add mandrill
```

###Mongolab (logs, forum topics and document references are stored in mongodb)
```shell
heroku addons:add mongolab
```

###Other environment vars

* TIMEZONE: defaults to 'Europe/Brussels'
* DEBUG: [0, 1]
* MASTER_PASSWORD: sha512 encoded password for 'master' -> gives admin access to all letsgroups.

CDN urls of cdns see [includes/inc_default.php] for defaults

##Migrating a group from eLAS 3.1 to eLAS-Heroku

For eLAS 2.6 see [here](./setup/migrate-eLAS-2.6.md)

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
In eLAS-Heroku all letsgroups are stored as schemas in one database.
You can import a dump file you made previously with pg_dump with options --no-acl --no-owner (no custom format).
```sql
\i myletsgroupdumpfile.sql
```
The tables of the imported letsgroup are now in the default schema named public.
You can truncate the city_distance table which is not used anymore and which is very big. (More than a 1M rows.)
```sql
TRUNCATE TABLE city_distance;
```
In eLAS there are only 2 levels of access for contacts. Public and private. In eLAS-Heroku public is further divided in 'members' and 'interlets'. To keep consistent the 'public' access level of eLAS should be transformed into the 'interlets' access level of eLAS-Heroku.
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
Meta command to list all schemas:
```
\dn
```
Meta command list all tables from all schemas:
```
\dt *.*
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
