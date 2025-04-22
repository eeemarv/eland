# Dokku

eLAND installs on a VPS with Dokku.

See the [Dokku installation guide](http://dokku.viewdocs.io/dokku/getting-started/installation).

* Install Dokku on a VPS with a fresh Ubuntu 18.04

* Set a domain with a DNS A record to your VPS.

* Create app, install postgres and redis plugins and bind them to the app (see Dokku guide).

Tip: use the same name for your postgres and redis instances as your eland-app-name.

* Postgres

[Link a postgres database to the app.](https://github.com/dokku/dokku-postgres)

Schemas to be set up can be found in the [/setup directory](https://github.com/eeemarv/eland/tree/master/setup)
See also [Migrate from eLAS 3.x](migrate-from-elas-3.md)

* Redis

[Link a redis instance to the app.](https://github.com/dokku/dokku-redis)

* Attach the domain to your dokku eland app.

(all dokku commands are on the server)

```shell

dokku domains:set your-eland-app your-domain.com

```

Create a persistant storage for uploaded documents and images:
```
dokku storage:ensure-directory eland
dokku storage:mount your-eland-app /var/lib/dokku/data/storage/eland:/app/public/store
```








* Use the [Dokku Letsencrypt](https://github.com/dokku/dokku-letsencrypt) plugin to enable https

* Set the environment varables for links in emails. (emails get sent in a background process that does not have a request context.)

´´´shell
dokku config:set your-eland-app APP_SCHEME=https APP_HOST=your-domain.com
´´´

## Configure nginx

* Allow bigger uploads (for documents)

```shell

sudo su - dokku
mkdir /home/dokku/appname/nginx.conf.d/
echo 'client_max_body_size 10M;' > /home/dokku/appname/nginx.conf.d/upload.conf
exit
sudo service nginx reload

```

* Block unwanted IPs (optional)

```shell

sudo su - dokku
mkdir /home/dokku/appname/nginx.conf.d/
echo 'deny x.x.x.x;' > /home/dokku/appname/nginx.conf.d/upload.conf
exit
sudo service nginx reload

```

## AWS S3

Create a IAM user on AWS with access only to S3.
Then create a bucket in your region and set the environment variables.

```shell
dokku config:set AWS_S3_BUCKET=yourbucketname AWS_S3_REGION=eu-central-1
dokku config:set AWS_ACCESS_KEY_ID=youraccesskeyid AWS_SECRET_ACCESS_KEY=yoursecretaccesskey
```

## Email

### SMTP mailserver (e.i. Amazon Simple Email Service)

Set the environment variables with ´dokku config:set your-eland-app´

* `SMTP_HOST`
* `SMTP_PORT`
* `SMTP_PASSWORD`
* `SMTP_USERNAME`

### From mail addresses

* `MAIL_FROM_ADDRESS`: a mail address when a reply-to address has been set.
* `MAIL_NOREPLY_ADDRESS`: a notification mail you can not reply to
* `MAIL_HOSTER_ADDRESS`: used for the general contact form on the index page (to contact the hoster).

Mail is sent only from `MAIL_FROM_ADDRESS` and `MAIL_NOREPLY_ADDRESS`.
These addresses should be set up for DKIM in the mailserver.

## Google geocoding

Coordinates of addresses are looked up and cached from the Google geocoding service in order to show the location on maps. At maximum every 2 minutes a request is sent to the API in order not to hit the daily free limit. You need to get a key from [Google](https://developers.google.com/maps/documentation/geocoding/intro)
and put the key in the environment variable `GOOGLE_GEO_API_KEY`

The geocoding service can be blocked by setting `GEO_BLOCK` to 1.

## MAPBOX token

For displaying maps, get a token from [Mapbox](https://www.mapbox.com)

and set the environment variable

´´´shell
dokku config:set MAPBOX_TOKEN=yourmapboxtoken
´´´

## Other environment vars

* `TIMEZONE`: defaults to 'Europe/Brussels'
* `MASTER_PASSWORD`: sha512 encoded password for 'master' -> gives admin access to all Systems.

## Permanent redirects

Use the [dokku-redirect](https://github.com/dokku/dokku-redirect) plugin for redirects.

## Local Development Server

The several processes of eLAND (see the [ProcFile](https://github.com/eeemarv/eland/blob/master/Procfile)) can be run locally by [the Heroku CLI](https://devcenter.heroku.com/articles/heroku-cli)

```shell
heroku local -e .env dev
```

`.env` is the file that contains the environment parameters.

The `dev` process is the server for local only (at localhost:5000) and `web` is for production only.
