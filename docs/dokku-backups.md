#Dokku

###Backups

* Install Aws-cli
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


/home/dokku/eland-backup.sh
```shell
#!/bin/bash

PATH=/usr/local/bin:/usr/bin:/bin:/usr/local/games:/usr/games

export AWS_ACCESS_KEY_ID=XXXX
export AWS_SECRET_ACCESS_KEY=XXXX
export AWS_DEFAULT_REGION=eu-central-1

dokku postgres:export eland > /home/dokku/eland.sql
aws s3 cp /home/dokku/eland.sql s3://backup.letsa.net

```
Copy/past here credentials of the earlier created AWS IAM user.
The path should be copied from the normal environment variables of the dokku user. This can be found (as the dokku user) with:

```
env | grep PATH
```

* Create a crontab for the dokku user (for example twice a day) to run the bash script. (crontab -e)

```shell
0 16,4 * * * /bin/bash /home/dokku/eland-backup.sh
```

As a result the latest backup is available locally in /home/dokku/eland.sql and remotely in s3 all versions are stored for at least 30 days. The number of days is configurable in the versioning rules of the s3 bucket.




