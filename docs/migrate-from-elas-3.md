# Migrating a group from eLAS 3.x to eLAND

## Database

* Upload the SQL dump file to the server:

```shell
scp myletsgroupdumpfile.sql myusername@app.mydomain.com:myletsgroupdumpfile.sql
```

In eLAND all letsgroups are stored as schemas in one database.
First create an empty public schema where the dump will be imported into.

Log into postgres:

```shell
dokku postgres:connect databasename
```

```sql
create schema public;
```

You can import a dump file you made previously with pg_dump with options --no-acl --no-owner (no custom format).

```sql
\i ./myusername/home/myletsgroupdumpfile.sql
```

Or, use on of the [dokku-postgres](https://github.com/dokku/dokku-postgres) commands according to the format of the file.

The tables of the imported letsgroup are now in the default schema named public.
You can truncate the city_distance table which is not used anymore and which is more than a 1M rows big.

```sql
TRUNCATE TABLE city_distance;
```

In eLAS there are only 2 levels of access for contacts. Public and private. In eLAND public is further divided in 'members' and 'interSystem'. To keep consistent the 'public' access level of eLAS should be transformed into the 'interlets' access level of eLAND.

```sql
UPDATE contact SET flag_public = 2 where flag_public = 1;
```

Coming from eLAS +3.6 run also the following to undo some database changes:

```sql
alter table users add column "PictureFile" character varying(128);
alter table msgpictures add column "PictureFile" character varying(128);

update users u set "PictureFile" = trim(leading 'userpictures/' from f.path) from files f where f.fileid = u.pictureid;
update msgpictures m set "PictureFile" = trim(leading 'msgpictures/' from f.path) from files f where f.fileid = m.pictureid;

update parameters set value = '31000' where parameter = 'schemaversion';
```

Rename then the public schema to the letsgroup code

```sql
ALTER SCHEMA public RENAME TO abc;
```

This way of importing letsgroups leaves the already present letsgroups data untouched. This can not be done with the Heroku tools.

* Match a subdomain to a schema with config variable `SCHEMA_subdomain=schema`

In domain all characters must be converted to uppercase. A dot must be converted to a double underscore. A h
yphen must be converted to a triple underscore.

Example:

```shell
dokku config:set appname SCHEMA_FLUPKE___AND___SABRINA=flupkesabrina
```

matches flupke-and-sabrina.my-domain.com to database schema flupkesabrina.

The overall domain my-domain.com was set with

```shell
dokku config:set appname OVERALL_DOMAIN=my___domain__com
```

## Images

* Resize all image files from folders msgpictures and userpictures (image files in eLAS were up to 2MB) at least down to 200kB, but keep the same filename (the extension may be renamed to one of jpg, JPG, jpeg, JPEG).

You can use imagemagick for this:

```shell
cd userpictures
mogrify -resize 400x400 -quality 100 -path ../imgs *.jpg
cd ../msgpictures
mogrify -resize 400x400 -quality 100 -path ../imgs *.jpg
```

Upload the image files to your S3 bucket (no directory path. The image files are prefixed automatically in the next step).
Make the image files public. Use the [awscli](https://aws.amazon.com/cli/)

```shell
cd ../imgs
aws s3 sync . s3://img.letsa.net
```

The aws s3 sync command can also be used to take a backup on your local machine:

```shell
cd destination-directory
aws s3 sync s3://img.letsa.net .
```

* Log in with admin rights to your website (you can use the master login and password) go to path `/init.php` The image files get renamed with a new hash and orphaned files will be cleaned up.

The files get prefixed with the schema name and the user or message id. All extensions become jpg.
ie.
    abc_u_41_c533e0ef9491c7c0b22fdf4a385ab47e1bb49eec.jpg
    abc_m_71_a84d14fb1bfbd1f9426a2a9ca5f5525d1e46f15e.jpg

* In case the init procedure times out, it needs to be resumed with the same location where it stopped (Just put your cursor in the address bar of your browser and hit enter.)
* The init procudure copies the images and gives them a new name. The original images, the filename not starting with a schema name but with a number, can be removed manually from the S3 bucket, with the AWS webinterface.
