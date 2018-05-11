# Dokku

eLAND installs on a VPS with Dokku.

See the [Dokku installation guide](http://dokku.viewdocs.io/dokku/getting-started/installation).

* Install Dokku on a VPS with a fresh Ubuntu 16.04,

* Domains: don't set a global domain, but set a domain for the app with a wildchart

(all dokku commands are on the server)

```shell

dokku domains:set app-name *.my-domain.com

```

* Create app, install postgres and redis plugins and bind them to the app (see Dokku guide).

* Configure nginx to allow bigger uploads (for documents)

```shell

sudo su - dokku
mkdir /home/dokku/appname/nginx.conf.d/
echo 'client_max_body_size 10M;' > /home/dokku/appname/nginx.conf.d/upload.conf
exit
sudo service nginx reload

```

## DNS

A CNAME record with wildcard should point to the Dokku app url.

## Match subdomains and database schemas (groups)

Set a config var for each subdomain to the name of the schema in the database

```shell

dokku config:set appname SCHEMA_FLUPKESUBDOMAIN=flupkeschema

```

Above example couples domain flupkesubdomain.my-domain.com to database schema flupkeschema.

Set the overall domain with:

```shell

dokku config:set appname OVERALL_DOMAIN=my-domain.com

```

## AWS S3

Create a IAM user on AWS with access only to S3. Then create 2 buckets in your region for images and documents
See [file include/default.php](includes/default.php) for which libraries are to be uploaded.
The buckets should have the same name as the url.

```shell

dokku config:set S3_IMG=img.letsa.net S3_DOC=doc.letsa.net
dokku config:set AWS_ACCESS_KEY_ID=aaa AWS_SECRET_ACCESS_KEY=bbb

```

Create CNAME records to these buckets

img.letsa.net CNAME record for img.letsa.net.s3-eu-central-1.amazonaws.com

See [the AWS S3 docs](http://docs.aws.amazon.com/AmazonS3/latest/dev/VirtualHosting.html)

You need to set up CORS configuration on bucket S3_RES for the fonts of footable 2.0.3 to load.

```xml

<?xml version="1.0" encoding="UTF-8"?>
<CORSConfiguration xmlns="http://s3.amazonaws.com/doc/2006-03-01/">
    <CORSRule>
        <AllowedOrigin>*</AllowedOrigin>
        <AllowedMethod>GET</AllowedMethod>
        <MaxAgeSeconds>3000</MaxAgeSeconds>
        <AllowedHeader>Authorization</AllowedHeader>
    </CORSRule>
</CORSConfiguration>

```

## Email

### SMTP mailserver (e.i. Amazon Simple Email Service)

* `SMTP_HOST`
* `SMTP_PORT`
* `SMTP_PASSWORD`
* `SMTP_USERNAME`

### From mail addresses

* `MAIL_FROM_ADDRESS`: a mail address when a reply-to address has been set.
* `MAIL_NOREPLY_ADDRESS`: a notification mail you can not reply to
* `MAIL_HOSTER_ADDRESS`: used for the request-hosting form.
* `MAIL_NOTIFY_ADDRESS`: mail system notifications.

Mail is sent only from `MAIL_FROM_ADDRESS` and `MAIL_NOREPLY_ADDRESS`.
These addresses should be set up for DKIM in the mailserver.

### Google geocoding

Coordinates of addresses are looked up and cached from the Google geocoding service in order to show the location on maps. At maximum every 2 minutes a request is sent to the API in order not to hit the daily free limit. You need to get a key from [Google](https://developers.google.com/maps/documentation/geocoding/intro)
and put the key in the environment variable `GOOGLE_GEO_API_KEY`

The geocoding service can be blocked by setting `GEO_BLOCK` to 1.

## Request hosting form

The Domain of a request-hosting form can be set with:

* `HOSTING_FORM_domain=1`

## Redirects

* `REDIRECT_fromdomain=to.domain.net`

domain of the hosting form and redirects is formatted the same way as the schema subdomains.

* Dots are replaced by double underscore `__`
* Hyphens are replaced by triple underscore `___`
* all characters should be uppercase in the environment variable.

## Other environment vars

* `TIMEZONE`: defaults to 'Europe/Brussels'
* `MASTER_PASSWORD`: sha512 encoded password for 'master' -> gives admin access to all letsgroups.

## Postgres

[Link a postgres database to the app.](https://github.com/dokku/dokku-postgres)

Schemas to be set up can be found in the [/setup directory](https://github.com/eeemarv/eland/tree/master/setup)
See also [Migrate from eLAS 3.x](migrate-from-elas-3.md)

## Redis

[Link a redis instance to the app.](https://github.com/dokku/dokku-redis)
