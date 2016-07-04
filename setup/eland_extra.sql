
create schema eland_extra;

create table eland_extra.logs (
schema varchar(60) not null,
user_id int not null default 0,
user_schema varchar(60),
letscode varchar(20),
username varchar(255),
ip varchar(60),
ts timestamp without time zone default (now() at time zone 'utc'),
type varchar(60),
event varchar(255));

create index on eland_extra.logs(schema);
create index on eland_extra.logs(schema, letscode);
create index on eland_extra.logs(letscode);
create index on eland_extra.logs(type);

create table eland_extra.events (
ts timestamp without time zone default (now() at time zone 'utc'),
user_id int default 0,
user_schema varchar(60),
agg_id varchar(255),
agg_type varchar(60),
agg_version int,
data jsonb,
event_time timestamp without time zone default (now() at time zone 'utc'),
ip varchar(60),
event varchar(128),
agg_schema varchar(60),
eland_id varchar(40)
);

alter table eland_extra.events add primary key (agg_id, agg_version);

create table eland_extra.aggs (
ts timestamp without time zone default (now() at time zone 'utc'),
user_id int default 0,
user_schema varchar(60),
agg_id varchar(255) primary key not null,
agg_type varchar(60),
agg_version int,
data jsonb,
ip varchar(60),
event varchar(128),
agg_schema varchar(60),
eland_id varchar(40)
);

create index on eland_extra.aggs(agg_type, agg_schema);
create index on eland_extra.aggs(agg_schema);
create index on eland_extra.logs(agg_type);

