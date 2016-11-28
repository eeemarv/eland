#Setup on Heroku

###Heroku buildpack
Set php buildpack.

```shell
heroku buildpacks:set heroku/php
```

###Cron

Trigger the cronscript every minute from a remote server.

crontab:
```
* * * * * wget -O /dev/null http://a.letsa.net/cron.php

```

Only one cronjob is needed for all installed domains (unlike eLAS). Just choose one domain.

###Domain

Every letsgroup is in its own subdomain of one overall domain.

The overall domain is set by environment variable OVERALL_DOMAIN

heroku config:set OVERALL_DOMAIN=letsa.net

Also add the overall domain with wildcard to Heroku:

```shell
heroku domains:add *.letsa.net
```

A CNAME record with wildcard should point to the Heroku app url.

set a config var for each subdomain to the name of the schema in the database
```shell
heroku config:set SCHEMA_MYGROUP=mygroup
```

Above example couples domain mygroup.letsa.net to database schema mygroup.

The environment variable SCHEMA_domain: couples a subdomain to a schema

* Dots in subdomain are replaced by double underscore __
* Hyphens in domain are replaced by triple underscore ___
* all characters should be uppercase in the environment variable.

###AWS S3
Create a IAM user on AWS with access only to S3. Then create 3 buckets in your region for images, documents and 3th party (javascript + css) libraries.
See (file inludes/defaults)[includes/inc_default.php] for which libraries are to be uploaded. 
The buckets should have the same name as the url.

```shell
heroku config:set S3_IMG=img.letsa.net S3_DOC=doc.letsa.net S3_RES=res.letsa.net
heroku config:set AWS_ACCESS_KEY_ID=aaa AWS_SECRET_ACCESS_KEY=bbb
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

###Redis cloud

```shell
heroku addons:add rediscloud:30
```

###Email

#### SMTP mailserver (e.i. Amazon Simple Email Service)
* SMTP_HOST
* SMTP_PORT
* SMTP_PASSWORD
* SMTP_USERNAME

#### From mail addresses

* MAIL_FROM_ADDRESS: a mail address when a reply-to address has been set.
* MAIL_NOREPLY_ADDRESS: a notification mail you can not reply to
* MAIL_HOSTER_ADDRESS: used for request-hosting form.

Mail is sent only from these addresses. 

### Request hosting form

The Domain of a request-hosting form can be set with:

* HOSTING_FORM_domain=1

### Redirects

* REDIRECT_fromdomain=to.domain.net

domain of the hosting form and redirects is formatted the same way as the schema subdomains.

* Dots are replaced by double underscore __
* Hyphens are replaced by triple underscore ___
* all characters should be uppercase in the environment variable.


###Other environment vars

* TIMEZONE: defaults to 'Europe/Brussels'
* MASTER_PASSWORD: sha512 encoded password for 'master' -> gives admin access to all letsgroups.

CDN urls of cdns see [includes/inc_default.php] for defaults

###Daily backups

```shell
heroku pg:backups schedule DATABASE_URL --at '02:00 Europe/Brussels'
```

