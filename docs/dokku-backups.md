#Dokku

###Backups to S3: method 1

Use the `dokku postgres:backup-schedule command`

See the [Postgres addon](https://github.com/dokku/dokku-postgres)

Currently only the us-west-1 region is available with this method

###Backups to S3: method 2

* Install Awscli (with pip to ensure you have the latest version)
* Create a bucket on AWS S3 with versioning and rules.
* Create a Policy for this bucket:

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "s3:GetBucketLocation",
                "s3:ListAllMyBuckets"
            ],
            "Resource": "arn:aws:s3:::*"
        },
        {
            "Effect": "Allow",
            "Action": [
                "s3:ListBucket"
            ],
            "Resource": [
                "arn:aws:s3:::backup.letsa.net"
            ]
        },
        {
            "Effect": "Allow",
            "Action": [
                "s3:PutObject",
                "s3:GetObject"
            ],
            "Resource": [
                "arn:aws:s3:::backup.letsa.net/*"
            ]
        }
    ]
}
```
* Create a AWS IAM user, store the credentials and attach the created policy.
* On the VPS, create a bash script in the dokku home directory /home/dokku with the dokku user and group:


/home/dokku/backup.sh
```shell
#!/bin/bash

PATH=/usr/local/bin:/usr/bin:/bin:/usr/local/games:/usr/games

export AWS_ACCESS_KEY_ID=XXXX
export AWS_SECRET_ACCESS_KEY=XXXX
export AWS_DEFAULT_REGION=eu-central-1

dokku postgres:export database_name > /home/dokku/eland.sql
aws s3 cp /home/dokku/eland.sql s3://backup.letsa.net

```
Copy/past here credentials of the earlier created AWS IAM user.
The path should be copied from the normal environment variables of the dokku user. This can be found (as the dokku user) with:

```shell
env | grep PATH
```

* Create a crontab for the dokku user (for example twice a day) to run the bash script. (crontab -e)

```shell
0 16,4 * * * /bin/bash /home/dokku/backup.sh
```

As a result the latest backup is available locally in /home/dokku/eland.sql and remotely in s3 all versions are stored for at least 30 days. The number of days is configurable in the versioning rules of the s3 bucket.


###Download backups

Make sure you have the latest awscli (install with pip)

To download the latest backup to your local directory:

```shell
aws s3 sync s3://backup.letsa.net . --region=eu-central-1
```

###How to create a SQL plain text database dump 

```shell
dokku run app-name 'pg_dump $DATABASE_URL --no-owner --no-acl' > dev.sql
```




