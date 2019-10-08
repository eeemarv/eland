
create schema xdb;

create table if not exists xdb.logs (
schema varchar(60) not null,
user_id int not null default 0,
user_schema varchar(60),
letscode varchar(20),
username varchar(255),
ip varchar(60),
ts timestamp without time zone default (now() at time zone 'utc'),
type varchar(60),
event varchar(255),
data jsonb);

create index on xdb.logs(schema);
create index on xdb.logs(schema, letscode);
create index on xdb.logs(letscode);
create index on xdb.logs(type);

create table if not exists xdb.events (
ts timestamp without time zone default timezone('utc'::text, now()),
user_id int default 0,
user_schema varchar(60),
agg_id varchar(255),
agg_type varchar(60),
agg_version int,
data jsonb,
event_time timestamp without time zone default timezone('utc'::text, now()),
ip varchar(60),
event varchar(128),
agg_schema varchar(60),
eland_id varchar(40)
);

alter table xdb.events add primary key (agg_id, agg_version);

create table if not exists xdb.aggs (
ts timestamp without time zone default timezone('utc'::text, now()),
user_id int default 0,
user_schema varchar(60),
agg_id varchar(255) primary key not null,
agg_type varchar(60),
agg_version int,
data jsonb,
ip varchar(60),
event varchar(128),
agg_schema varchar(60),
eland_id varchar(40),
event_time timestamp without time zone default timezone('utc'::text, now())
);

create index on xdb.aggs(agg_type, agg_schema);
create index on xdb.aggs(agg_schema);
create index on xdb.logs(agg_type);

create table if not exists xdb.queue (
ts timestamp without time zone default timezone('utc'::text, now()),
id bigserial primary key,
topic varchar(60) not null,
data jsonb,
priority int default 0);

create index on xdb.queue(id, priority);

create table if not exists xdb.cache (
id varchar(255) primary key not null,
data jsonb,
ts timestamp without time zone default timezone('utc'::text, now()),
expires timestamp without time zone);

