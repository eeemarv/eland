# Import CSV

This is only an emergency method to import .csv files that were exported from eLAS.
To import directly in the database is far superior (also more easy) and should always be considered
first. With cvs import a lot of data is lost.

Beforehand, load an empty template in a schema the database. (See [setup](https://github.com/eeemarv/eland/setup/full_schema_30000.sql))

In psql:

```sql
create schema public;
\i template.sql
alter schema public rename to yourschemaname;
```

## Import users

Set a default and remove the not null constraint on several columns.

Do this for columns code, hobbies, lang, postcode, name, login, maxlimit

In psql:

```sql
alter table yourschemaname.users alter column code set default '';
alter table yourschemaname.users alter column code drop not null;
```

Delete the "mailinglist" column from the csv file with a spreadsheet program (like Open Office)
Then import the users csv file.
Check the order of the columns.

```sql
\copy yourschemaname.users(code, cdate, comments, hobbies, name, postcode, login, password, role, status, lastlogin, minlimit, full_name, admin_comments, adate) from 'users.csv' delimiter ',' csv header;
```

(replace users.cvs with your actual filename and location)

## Import contacts

The cvs export from eLAS does not contain user ids.
We have to use the code (Account Code) to link the contacts to the users.

Add a column to the contact table to store the code (Account Code):

```sql
alter table yourschemaname.contact add column code character varying(20) default '';
```

Also add a column to the contact table to store the contact type abbreviation.
The contact cvs export from eLAS does not contain contact type ids.
The contact type ids need to be linked later.

```sql
alter table yourschemaname.contact add column abbrev character varying(20) default '';
```

Set default and allow null values in the "value" column:

```sql
alter table yourschemaname.contact alter column value set default '';
alter table yourschemaname.contact alter column value drop not null;
```

Delete the "username" column from the csv file with your spreadsheet program.

Then import the contacts csv file.
Check the order of the columns.

```sql
\copy yourschemaname.contact(code, abbrev, comments, value, flag_public) from 'contacts.csv' delimiter ',' csv header;
```

## Link users to the contacts

```sql
update yourschemaname.contact c set id_user = u.id from yourschemaname.users u where u.code = c.code;
```

## Link contacts to the contact_types table

Before linking, check if all contact types are present:

```sql
select abbrev from yourschemaname.type_contact;
```

```sql
select distinct abbrev from yourschemaname.contact;
```

Make sure to add the required contact types with the right abbreviations in the type_contact table.
This can be done in the eLAND UI.

When all contact types are present, you can link the contact types:

```sql
update yourschemaname.contact c set id_type_contact = tc.id from yourschemaname.type_contact tc where c.abbrev = tc.abbrev;
```

Afterwards, the code (Account Code) and abbrev columns can be removed from the contact table:

```sql
alter table yourschemaname.contact drop column abbrev;
alter table yourschemaname.contact drop column code;
```

Records that were not linked to users or types can be removed:

```sql
delete from yourschemaname.contact where id_user = 0 or id_type_contact = 0;
```