# Development

For development, use the Symfony server

```shell
symfony serve
```

The Symfony Asset Mapper normally compiles and serves files from the `/assets` map on the fly in development mode unless the files are already compiled into `/public/assets` . But there are many little files in eLAND that make that the development server can not follow (even if more workers are assigned with environment variable `PHP_CLI_SERVER_WORKERS=5` put before `symfony serve`)
Therefore, run in another terminal:

```shell
while inotifywait -r -e modify,create,delete assets/; do
    php bin/console asset-map:compile
done
```
