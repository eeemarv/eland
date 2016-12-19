#Dokku

See the [Dokku installation guide](http://dokku.viewdocs.io/dokku/getting-started/installation).

* Install dokku on a VPS with a fresh Ubuntu 14.04,

* Domains: don't set a global domain, but set a domain for the app with a wildchart

(run command on server)

```
dokku domains:set app-name *.letsa.net
```

* Create app, install postgres and redis plugins and bind them the app (see Dokku guide).

* install the nl language pack if not present (for translating dates)

```shell
sudo apt-get install language-pack-nl
```

* Configure nginx to allow bigger uploads (for documents)
```shell
sudo su - dokku
mkdir /home/dokku/appname/nginx.conf.d/
echo 'client_max_body_size 10M;' > /home/dokku/appname/nginx.conf.d/upload.conf
exit
sudo service nginx reload
```
