# Import CSV

This is only an emergency method to import .csv files that were exported from eLAS.
To import directly in the database is far superior (also more easy) and should always be considered
first. With cvs import a lot of data is lost.

Beforehand, load an empty template in a schema the database. (See [setup](https://github.com/eeemarv/eland/setup/full_schema_30000.sql))

In psql:

```sql
create schema public;
\i template.sql
alter schema public rename to abc;
```

(abc = your letsgroup name; use the same name as the subdomain for convenience.)

## Import users

Set a default and remove the not null constraint on several columns.

Do this for columns letscode, hobbies, lang, postcode, name, login, maxlimit

In psql:

```sql
alter table abc.users alter column letscode set default '';
alter table abc.users alter column letscode drop not null;
```

Delete the "mailinglist" column from the csv file with a spreadsheet program (like Open Office)
Then import the users csv file.
Check the order of the columns.

```sql
\copy abc.users(letscode, cdate, comments, hobbies, name, postcode, login, password, accountrole, status, lastlogin, minlimit, fullname, admincomment, adate) from 'users.csv' delimiter ',' csv header;
```

(replace users.cvs with your actual filename and location)

## Import contacts

The cvs export from eLAS does not contain user ids.
We have to use the letscode to link the contacts to the users.

Add a column to the contact table to store the letscode:

```sql
alter table abc.contact add column letscode character varying(20) default '';
```

Also add a column to the contact table to store the contact type abbreviation.
The contact cvs export from eLAS does not contain contact type ids.
The contact type ids need to be linked later.

```sql
alter table abc.contact add column abbrev character varying(20) default '';
```

Set default and allow null values in the "value" column:

```sql
alter table abc.contact alter column value set default '';
alter table abc.contact alter column value drop not null;
```

Delete the "username" column from the csv file with your spreadsheet program.

Then import the contacts csv file.
Check the order of the columns.

```sql
\copy abc.contact(letscode, abbrev, comments, value, flag_public) from 'contacts.csv' delimiter ',' csv header;
```

## Link users to the contacts

```sql
update abc.contact c set id_user = u.id from abc.users u where u.letscode = c.letscode;
```

## Link contacts to the contact_types table

Before linking, check if all contact types are present:

```sql
select abbrev from type_contact;
```

```sql
select distinct abbrev from contact;
```

Make sure to add the required contact types with the right abbreviations in the type_contact table.
This can be done in the eLAND UI.

When all contact types are present, you can link the contact types:

```sql
update contact c set id_type_contact = tc.id from type_contact tc where c.abbrev = tc.abbrev;
```

Afterwards, the letscode and abbrev columns can be removed from the contact table:

```sql
alter table abc.contact drop column abbrev;
alter table abc.contact drop column letscode;
```

Records that were not linked to users or types can be removed:

```sql
delete from contact where id_user = 0 or id_type_contact = 0;
```