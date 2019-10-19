<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191018191623 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE c.agg (id UUID NOT NULL, agg_version INT NOT NULL, system_id UUID DEFAULT NULL, agg_type VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, data JSONB NOT NULL, meta JSONB NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX agg_type_system_idx ON c.agg (agg_type, system_id)');
        $this->addSql('COMMENT ON COLUMN c.agg.data IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN c.agg.meta IS \'(DC2Type:json_array)\'');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE c.cache (id VARCHAR(255) NOT NULL, data JSONB NOT NULL, ts TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN c.cache.data IS \'(DC2Type:json_array)\'');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE c.log (id BIGINT NOT NULL, ts TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, system_id UUID DEFAULT NULL, data JSONB NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX system_idx ON c.log (system_id)');
        $this->addSql('COMMENT ON COLUMN c.log.data IS \'(DC2Type:json_array)\'');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE c.queue (id BIGINT NOT NULL, topic VARCHAR(255) NOT NULL, ts TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, priority INT DEFAULT 0 NOT NULL, data JSONB NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX topic_idx ON c.queue (topic)');
        $this->addSql('COMMENT ON COLUMN c.queue.data IS \'(DC2Type:json_array)\'');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE e.event (sequence_id BIGINT NOT NULL, agg_id UUID NOT NULL, agg_version INT NOT NULL, data JSONB NOT NULL, PRIMARY KEY(sequence_id))');
        $this->addSql('CREATE INDEX agg_idx ON e.event (agg_id)');
        $this->addSql('CREATE UNIQUE INDEX agg_version_unique_idx ON e.event (agg_id, agg_version)');
        $this->addSql('COMMENT ON COLUMN e.event.data IS \'(DC2Type:json_array)\'');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE migration.versions (version VARCHAR(255) NOT NULL, PRIMARY KEY(version))');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE x.apikeys (id SERIAL NOT NULL, apikey VARCHAR(80) NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT \'now()\' NOT NULL, type VARCHAR(15) NOT NULL, comment VARCHAR(200) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX apikeys_type ON x.apikeys (type)');
        $this->addSql('CREATE INDEX apikeys_apikey ON x.apikeys (apikey)');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE x.categories (id SERIAL NOT NULL, name VARCHAR(40) DEFAULT \'\' NOT NULL, id_parent INT DEFAULT 0 NOT NULL, description VARCHAR(60) DEFAULT NULL, cdate TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id_creator INT DEFAULT 0 NOT NULL, fullname VARCHAR(100) DEFAULT NULL, leafnote INT DEFAULT 0 NOT NULL, stat_msgs_wanted INT DEFAULT 0, stat_msgs_offers INT DEFAULT 0, PRIMARY KEY(id))');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE x.city_distance (code_from VARCHAR(30) DEFAULT NULL, code_to VARCHAR(30) DEFAULT NULL, distance DOUBLE PRECISION DEFAULT NULL)');
        $this->addSql('CREATE INDEX city_distance_code_from_code_to ON x.city_distance (code_from, code_to)');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE x.config (setting VARCHAR(60) NOT NULL, category VARCHAR(50) NOT NULL, value VARCHAR(60) DEFAULT NULL, description VARCHAR(140) DEFAULT NULL, comment VARCHAR(140) DEFAULT NULL, "default" BOOLEAN DEFAULT \'true\' NOT NULL, PRIMARY KEY(setting))');
        $this->addSql('CREATE INDEX config_category ON x.config (category)');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE x.contact (id SERIAL NOT NULL, id_type_contact INT DEFAULT 0 NOT NULL, comments VARCHAR(50) DEFAULT NULL, value VARCHAR(130) NOT NULL, id_user INT DEFAULT 0 NOT NULL, flag_public INT DEFAULT 0 NOT NULL, PRIMARY KEY(id))');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE x.cron (cronjob VARCHAR(20) NOT NULL, lastrun TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT \'now()\' NOT NULL)');
        $this->addSql('CREATE INDEX cron_cronjob ON x.cron (cronjob)');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE x.eventlog ("timestamp" TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT \'now()\' NOT NULL, userid INT NOT NULL, type VARCHAR(15) NOT NULL, event TEXT NOT NULL, ip VARCHAR(30) NOT NULL)');
        $this->addSql('CREATE INDEX eventlog_ip ON x.eventlog (ip)');
        $this->addSql('CREATE INDEX eventlog_userid ON x.eventlog (userid)');
        $this->addSql('CREATE INDEX eventlog_type ON x.eventlog (type)');
        $this->addSql('CREATE INDEX eventlog_timestamp ON x.eventlog (timestamp)');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE x.interletsq (transid VARCHAR(80) NOT NULL, date_created TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT \'now()\' NOT NULL, id_from INT NOT NULL, letsgroup_id INT NOT NULL, letscode_to VARCHAR(20) NOT NULL, amount DOUBLE PRECISION NOT NULL, description VARCHAR(60) NOT NULL, signature VARCHAR(80) NOT NULL, retry_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT \'1970-01-01 00:00:00\' NOT NULL, retry_count INT NOT NULL, last_status VARCHAR(15) DEFAULT NULL, PRIMARY KEY(transid))');
        $this->addSql('CREATE INDEX interletsq_id_from ON x.interletsq (id_from)');
        $this->addSql('CREATE INDEX interletsq_letsgroup_id ON x.interletsq (letsgroup_id)');
        $this->addSql('CREATE INDEX interletsq_letscode_to ON x.interletsq (letscode_to)');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE x.letsgroups (id SERIAL NOT NULL, groupname VARCHAR(128) NOT NULL, shortname VARCHAR(50) NOT NULL, prefix VARCHAR(5) DEFAULT NULL, apimethod VARCHAR(20) NOT NULL, remoteapikey VARCHAR(80) DEFAULT NULL, localletscode VARCHAR(20) NOT NULL, myremoteletscode VARCHAR(20) NOT NULL, url VARCHAR(256) DEFAULT NULL, elassoapurl VARCHAR(256) DEFAULT NULL, presharedkey VARCHAR(80) DEFAULT NULL, pubkey TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX letsgroups_groupname ON x.letsgroups (groupname)');
        $this->addSql('CREATE INDEX letsgroups_shortname ON x.letsgroups (shortname)');
        $this->addSql('CREATE INDEX letsgroups_myremoteletscode ON x.letsgroups (myremoteletscode)');
        $this->addSql('CREATE INDEX letsgroups_localletscode ON x.letsgroups (localletscode)');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE x.lists (listname VARCHAR(25) NOT NULL, address VARCHAR(50) NOT NULL, type VARCHAR(25) NOT NULL, topic VARCHAR(25) NOT NULL, description VARCHAR(128) NOT NULL, auth VARCHAR(20) NOT NULL, subscribers VARCHAR(20) NOT NULL, PRIMARY KEY(listname))');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE x.listsubscriptions (listname VARCHAR(25) NOT NULL, user_id INT NOT NULL, PRIMARY KEY(listname, user_id))');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE x.messages (id SERIAL NOT NULL, cdate TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, mdate TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, validity TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id_category INT DEFAULT 0 NOT NULL, id_user INT DEFAULT 0 NOT NULL, content TEXT NOT NULL, "Description" TEXT DEFAULT NULL, amount INT DEFAULT NULL, units VARCHAR(15) DEFAULT NULL, msg_type INT DEFAULT 0 NOT NULL, exp_user_warn BOOLEAN DEFAULT \'false\' NOT NULL, exp_admin_warn BOOLEAN DEFAULT \'false\' NOT NULL, local BOOLEAN DEFAULT \'false\', PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX messages_exp_user_warn_exp_admin_warn ON x.messages (exp_user_warn, exp_admin_warn)');
        $this->addSql('CREATE INDEX messages_local ON x.messages (local)');
        $this->addSql('CREATE INDEX messages_id_user ON x.messages (id_user)');
        $this->addSql('CREATE INDEX messages_validity ON x.messages (validity)');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE x.msgpictures (id SERIAL NOT NULL, msgid INT NOT NULL, "PictureFile" VARCHAR(128) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX msgpictures_msgid ON x.msgpictures (msgid)');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE x.news (id SERIAL NOT NULL, id_user INT DEFAULT 0 NOT NULL, headline VARCHAR(200) DEFAULT \'\' NOT NULL, newsitem TEXT NOT NULL, cdate TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, itemdate TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, approved BOOLEAN NOT NULL, published BOOLEAN DEFAULT NULL, sticky BOOLEAN DEFAULT NULL, location VARCHAR(128) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX news_approved ON x.news (approved)');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE x.openid (id SERIAL NOT NULL, user_id INT NOT NULL, openid VARCHAR(128) NOT NULL, PRIMARY KEY(id))');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE x.ostatus_queue (id SERIAL NOT NULL, message VARCHAR(140) NOT NULL, url VARCHAR(100) DEFAULT NULL, pushed BOOLEAN DEFAULT NULL, PRIMARY KEY(id))');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE x.parameters (parameter VARCHAR(60) NOT NULL, value VARCHAR(60) DEFAULT NULL, PRIMARY KEY(parameter))');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE x.prefix (prefix VARCHAR(5) NOT NULL, subgroup VARCHAR(200) NOT NULL, PRIMARY KEY(prefix))');
        $this->addSql('CREATE INDEX prefix_subgroup ON x.prefix (subgroup)');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE x.regions (id SERIAL NOT NULL, name VARCHAR(100) DEFAULT \'\' NOT NULL, abbrev VARCHAR(11) DEFAULT \'\' NOT NULL, comments VARCHAR(100) DEFAULT \'\' NOT NULL, PRIMARY KEY(id))');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE x.stats (key VARCHAR(25) NOT NULL, description VARCHAR(250) NOT NULL, value DOUBLE PRECISION NOT NULL, PRIMARY KEY(key))');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE x.tokens (token VARCHAR(50) NOT NULL, validity TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, type VARCHAR(15) NOT NULL, PRIMARY KEY(token))');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE x.transactions (id SERIAL NOT NULL, amount INT DEFAULT 0 NOT NULL, description VARCHAR(60) DEFAULT \'0\' NOT NULL, id_from INT DEFAULT 0 NOT NULL, id_to INT DEFAULT 0 NOT NULL, real_from VARCHAR(80) DEFAULT NULL, real_to VARCHAR(80) DEFAULT NULL, transid VARCHAR(200) DEFAULT NULL, creator INT DEFAULT 0 NOT NULL, cdate TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX transactions_transid ON x.transactions (transid)');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE x.type_contact (id SERIAL NOT NULL, name VARCHAR(20) DEFAULT \'\' NOT NULL, abbrev VARCHAR(11) DEFAULT \'\' NOT NULL, protect BOOLEAN DEFAULT NULL, PRIMARY KEY(id))');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE x.users (id SERIAL NOT NULL, cdate TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, mdate TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id_region INT DEFAULT 0 NOT NULL, creator INT DEFAULT 0 NOT NULL, comments VARCHAR(100) DEFAULT NULL, hobbies TEXT NOT NULL, name VARCHAR(50) DEFAULT \'\' NOT NULL, birthday DATE DEFAULT NULL, letscode VARCHAR(20) DEFAULT \'\' NOT NULL, postcode VARCHAR(6) DEFAULT \'\' NOT NULL, login VARCHAR(50) DEFAULT \'\' NOT NULL, cron_saldo BOOLEAN DEFAULT NULL, password VARCHAR(150) DEFAULT NULL, accountrole VARCHAR(20) DEFAULT \'\' NOT NULL, status INT DEFAULT 0 NOT NULL, saldo INT DEFAULT 0 NOT NULL, lastlogin TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT \'now()\' NOT NULL, minlimit INT DEFAULT 0 NOT NULL, maxlimit INT DEFAULT NULL, fullname VARCHAR(100) DEFAULT NULL, admincomment VARCHAR(200) DEFAULT NULL, "PictureFile" VARCHAR(128) DEFAULT NULL, presharedkey VARCHAR(80) DEFAULT NULL, pwchange BOOLEAN DEFAULT NULL, locked BOOLEAN DEFAULT NULL, lang VARCHAR(5) NOT NULL, adate TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ostatus_id VARCHAR(50) DEFAULT NULL, pubkey TEXT DEFAULT NULL, privkey TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX users_cron_saldo ON x.users (cron_saldo)');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE xdb.ag (id UUID NOT NULL, ts TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT \'utc\', type VARCHAR(32) DEFAULT NULL, segment UUID DEFAULT NULL, version INT NOT NULL, data JSONB DEFAULT NULL, meta JSONB DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX ag_type_segment_idx ON xdb.ag (type, segment)');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE xdb.aggs (agg_id VARCHAR(255) NOT NULL, agg_version INT NOT NULL, data JSONB DEFAULT NULL, user_id INT DEFAULT 0, user_schema VARCHAR(60) DEFAULT \'\', ts TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT \'utc\', agg_type VARCHAR(60) DEFAULT NULL, agg_schema VARCHAR(60) DEFAULT NULL, eland_id VARCHAR(40) DEFAULT NULL, event VARCHAR(128) DEFAULT NULL, ip VARCHAR(60) DEFAULT NULL, event_time TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT \'utc\', eid VARCHAR(30) DEFAULT NULL, uid VARCHAR(8) DEFAULT NULL, PRIMARY KEY(agg_id))');
        $this->addSql('CREATE INDEX aggs_agg_type_agg_schema_idx ON xdb.aggs (agg_type, agg_schema)');
        $this->addSql('CREATE INDEX aggs_eid_agg_schema_idx ON xdb.aggs (eid, agg_schema)');
        $this->addSql('CREATE INDEX aggs_eid_idx ON xdb.aggs (eid)');
        $this->addSql('CREATE INDEX aggs_agg_type_idx ON xdb.aggs (agg_type)');
        $this->addSql('CREATE INDEX aggs_agg_schema_idx ON xdb.aggs (agg_schema)');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE xdb.cache (id VARCHAR(255) NOT NULL, data JSONB DEFAULT NULL, ts TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT \'utc\', expires TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE xdb.ev (id UUID NOT NULL, version INT NOT NULL, ts TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT \'utc\', data JSONB DEFAULT NULL, meta JSONB DEFAULT NULL, PRIMARY KEY(id, version))');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE xdb.events (agg_id VARCHAR(255) NOT NULL, agg_version INT NOT NULL, ts TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT \'utc\', user_id INT DEFAULT 0, user_schema VARCHAR(60) DEFAULT NULL, agg_type VARCHAR(60) DEFAULT NULL, data JSONB DEFAULT NULL, event_time TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT \'utc\', ip VARCHAR(60) DEFAULT NULL, event VARCHAR(128) DEFAULT NULL, agg_schema VARCHAR(60) DEFAULT NULL, eland_id VARCHAR(40) DEFAULT NULL, uid VARCHAR(8) DEFAULT NULL, eid VARCHAR(30) DEFAULT NULL, PRIMARY KEY(agg_id, agg_version))');
        $this->addSql('CREATE INDEX events_eid_agg_version_idx ON xdb.events (eid, agg_version)');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE xdb.logs (schema VARCHAR(60) NOT NULL, user_id INT DEFAULT 0 NOT NULL, user_schema VARCHAR(60) DEFAULT NULL, letscode VARCHAR(20) DEFAULT NULL, username VARCHAR(255) DEFAULT NULL, ip VARCHAR(60) DEFAULT NULL, ts TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT \'utc\', type VARCHAR(60) DEFAULT NULL, event TEXT DEFAULT NULL, data JSONB DEFAULT NULL)');
        $this->addSql('CREATE INDEX logs_type_idx ON xdb.logs (type)');
        $this->addSql('CREATE INDEX logs_schema_letscode_idx ON xdb.logs (schema, letscode)');
        $this->addSql('CREATE INDEX logs_schema_idx ON xdb.logs (schema)');
        $this->addSql('CREATE INDEX logs_letscode_idx ON xdb.logs (letscode)');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE xdb.queue (id BIGSERIAL NOT NULL, ts TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT \'utc\', data JSONB DEFAULT NULL, topic VARCHAR(60) NOT NULL, priority INT DEFAULT 0, event_time TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT \'utc\', PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX queue_id_priority_idx ON xdb.queue (id, priority)');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE y.apikeys (id SERIAL NOT NULL, apikey VARCHAR(80) NOT NULL, created TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT \'now()\' NOT NULL, type VARCHAR(15) NOT NULL, comment VARCHAR(200) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX apikeys_type ON y.apikeys (type)');
        $this->addSql('CREATE INDEX apikeys_apikey ON y.apikeys (apikey)');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE y.categories (id SERIAL NOT NULL, name VARCHAR(40) DEFAULT \'\' NOT NULL, id_parent INT DEFAULT 0 NOT NULL, description VARCHAR(60) DEFAULT NULL, cdate TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id_creator INT DEFAULT 0 NOT NULL, fullname VARCHAR(100) DEFAULT NULL, leafnote INT DEFAULT 0 NOT NULL, stat_msgs_wanted INT DEFAULT 0, stat_msgs_offers INT DEFAULT 0, PRIMARY KEY(id))');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE y.city_distance (code_from VARCHAR(30) DEFAULT NULL, code_to VARCHAR(30) DEFAULT NULL, distance DOUBLE PRECISION DEFAULT NULL)');
        $this->addSql('CREATE INDEX city_distance_code_from_code_to ON y.city_distance (code_from, code_to)');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE y.config (setting VARCHAR(60) NOT NULL, category VARCHAR(50) NOT NULL, value VARCHAR(60) DEFAULT NULL, description VARCHAR(140) DEFAULT NULL, comment VARCHAR(140) DEFAULT NULL, "default" BOOLEAN DEFAULT \'true\' NOT NULL, PRIMARY KEY(setting))');
        $this->addSql('CREATE INDEX config_category ON y.config (category)');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE y.contact (id SERIAL NOT NULL, id_type_contact INT DEFAULT 0 NOT NULL, comments VARCHAR(50) DEFAULT NULL, value VARCHAR(130) NOT NULL, id_user INT DEFAULT 0 NOT NULL, flag_public INT DEFAULT 0 NOT NULL, PRIMARY KEY(id))');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE y.cron (cronjob VARCHAR(20) NOT NULL, lastrun TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT \'now()\' NOT NULL)');
        $this->addSql('CREATE INDEX cron_cronjob ON y.cron (cronjob)');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE y.eventlog ("timestamp" TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT \'now()\' NOT NULL, userid INT NOT NULL, type VARCHAR(15) NOT NULL, event TEXT NOT NULL, ip VARCHAR(30) NOT NULL)');
        $this->addSql('CREATE INDEX eventlog_timestamp ON y.eventlog (timestamp)');
        $this->addSql('CREATE INDEX eventlog_type ON y.eventlog (type)');
        $this->addSql('CREATE INDEX eventlog_userid ON y.eventlog (userid)');
        $this->addSql('CREATE INDEX eventlog_ip ON y.eventlog (ip)');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE y.interletsq (transid VARCHAR(80) NOT NULL, date_created TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT \'now()\' NOT NULL, id_from INT NOT NULL, letsgroup_id INT NOT NULL, letscode_to VARCHAR(20) NOT NULL, amount DOUBLE PRECISION NOT NULL, description VARCHAR(60) NOT NULL, signature VARCHAR(80) NOT NULL, retry_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT \'1970-01-01 00:00:00\' NOT NULL, retry_count INT NOT NULL, last_status VARCHAR(15) DEFAULT NULL, PRIMARY KEY(transid))');
        $this->addSql('CREATE INDEX interletsq_letscode_to ON y.interletsq (letscode_to)');
        $this->addSql('CREATE INDEX interletsq_letsgroup_id ON y.interletsq (letsgroup_id)');
        $this->addSql('CREATE INDEX interletsq_id_from ON y.interletsq (id_from)');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE y.letsgroups (id SERIAL NOT NULL, groupname VARCHAR(128) NOT NULL, shortname VARCHAR(50) NOT NULL, prefix VARCHAR(5) DEFAULT NULL, apimethod VARCHAR(20) NOT NULL, remoteapikey VARCHAR(80) DEFAULT NULL, localletscode VARCHAR(20) NOT NULL, myremoteletscode VARCHAR(20) NOT NULL, url VARCHAR(256) DEFAULT NULL, elassoapurl VARCHAR(256) DEFAULT NULL, presharedkey VARCHAR(80) DEFAULT NULL, pubkey TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX letsgroups_myremoteletscode ON y.letsgroups (myremoteletscode)');
        $this->addSql('CREATE INDEX letsgroups_localletscode ON y.letsgroups (localletscode)');
        $this->addSql('CREATE INDEX letsgroups_shortname ON y.letsgroups (shortname)');
        $this->addSql('CREATE INDEX letsgroups_groupname ON y.letsgroups (groupname)');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE y.lists (listname VARCHAR(25) NOT NULL, address VARCHAR(50) NOT NULL, type VARCHAR(25) NOT NULL, topic VARCHAR(25) NOT NULL, description VARCHAR(128) NOT NULL, auth VARCHAR(20) NOT NULL, subscribers VARCHAR(20) NOT NULL, PRIMARY KEY(listname))');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE y.listsubscriptions (listname VARCHAR(25) NOT NULL, user_id INT NOT NULL, PRIMARY KEY(listname, user_id))');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE y.messages (id SERIAL NOT NULL, cdate TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, mdate TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, validity TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id_category INT DEFAULT 0 NOT NULL, id_user INT DEFAULT 0 NOT NULL, content TEXT NOT NULL, "Description" TEXT DEFAULT NULL, amount INT DEFAULT NULL, units VARCHAR(15) DEFAULT NULL, msg_type INT DEFAULT 0 NOT NULL, exp_user_warn BOOLEAN DEFAULT \'false\' NOT NULL, exp_admin_warn BOOLEAN DEFAULT \'false\' NOT NULL, local BOOLEAN DEFAULT \'false\', PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX messages_id_user ON y.messages (id_user)');
        $this->addSql('CREATE INDEX messages_local ON y.messages (local)');
        $this->addSql('CREATE INDEX messages_exp_user_warn_exp_admin_warn ON y.messages (exp_user_warn, exp_admin_warn)');
        $this->addSql('CREATE INDEX messages_validity ON y.messages (validity)');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE y.msgpictures (id SERIAL NOT NULL, msgid INT NOT NULL, "PictureFile" VARCHAR(128) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX msgpictures_msgid ON y.msgpictures (msgid)');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE y.news (id SERIAL NOT NULL, id_user INT DEFAULT 0 NOT NULL, headline VARCHAR(200) DEFAULT \'\' NOT NULL, newsitem TEXT NOT NULL, cdate TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, itemdate TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, approved BOOLEAN NOT NULL, published BOOLEAN DEFAULT NULL, sticky BOOLEAN DEFAULT NULL, location VARCHAR(128) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX news_approved ON y.news (approved)');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE y.openid (id SERIAL NOT NULL, user_id INT NOT NULL, openid VARCHAR(128) NOT NULL, PRIMARY KEY(id))');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE y.ostatus_queue (id SERIAL NOT NULL, message VARCHAR(140) NOT NULL, url VARCHAR(100) DEFAULT NULL, pushed BOOLEAN DEFAULT NULL, PRIMARY KEY(id))');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE y.parameters (parameter VARCHAR(60) NOT NULL, value VARCHAR(60) DEFAULT NULL, PRIMARY KEY(parameter))');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE y.prefix (prefix VARCHAR(5) NOT NULL, subgroup VARCHAR(200) NOT NULL, PRIMARY KEY(prefix))');
        $this->addSql('CREATE INDEX prefix_subgroup ON y.prefix (subgroup)');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE y.regions (id SERIAL NOT NULL, name VARCHAR(100) DEFAULT \'\' NOT NULL, abbrev VARCHAR(11) DEFAULT \'\' NOT NULL, comments VARCHAR(100) DEFAULT \'\' NOT NULL, PRIMARY KEY(id))');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE y.stats (key VARCHAR(25) NOT NULL, description VARCHAR(250) NOT NULL, value DOUBLE PRECISION NOT NULL, PRIMARY KEY(key))');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE y.tokens (token VARCHAR(50) NOT NULL, validity TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, type VARCHAR(15) NOT NULL, PRIMARY KEY(token))');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE y.transactions (id SERIAL NOT NULL, amount INT DEFAULT 0 NOT NULL, description VARCHAR(60) DEFAULT \'0\' NOT NULL, id_from INT DEFAULT 0 NOT NULL, id_to INT DEFAULT 0 NOT NULL, real_from VARCHAR(80) DEFAULT NULL, real_to VARCHAR(80) DEFAULT NULL, transid VARCHAR(200) DEFAULT NULL, creator INT DEFAULT 0 NOT NULL, cdate TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX transactions_transid ON y.transactions (transid)');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE y.type_contact (id SERIAL NOT NULL, name VARCHAR(20) DEFAULT \'\' NOT NULL, abbrev VARCHAR(11) DEFAULT \'\' NOT NULL, protect BOOLEAN DEFAULT NULL, PRIMARY KEY(id))');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE y.users (id SERIAL NOT NULL, cdate TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, mdate TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id_region INT DEFAULT 0 NOT NULL, creator INT DEFAULT 0 NOT NULL, comments VARCHAR(100) DEFAULT NULL, hobbies TEXT NOT NULL, name VARCHAR(50) DEFAULT \'\' NOT NULL, birthday DATE DEFAULT NULL, letscode VARCHAR(20) DEFAULT \'\' NOT NULL, postcode VARCHAR(6) DEFAULT \'\' NOT NULL, login VARCHAR(50) DEFAULT \'\' NOT NULL, cron_saldo BOOLEAN DEFAULT NULL, password VARCHAR(150) DEFAULT NULL, accountrole VARCHAR(20) DEFAULT \'\' NOT NULL, status INT DEFAULT 0 NOT NULL, saldo INT DEFAULT 0 NOT NULL, lastlogin TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT \'now()\' NOT NULL, minlimit INT DEFAULT 0 NOT NULL, maxlimit INT DEFAULT NULL, fullname VARCHAR(100) DEFAULT NULL, admincomment VARCHAR(200) DEFAULT NULL, "PictureFile" VARCHAR(128) DEFAULT NULL, presharedkey VARCHAR(80) DEFAULT NULL, pwchange BOOLEAN DEFAULT NULL, locked BOOLEAN DEFAULT NULL, lang VARCHAR(5) NOT NULL, adate TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ostatus_id VARCHAR(50) DEFAULT NULL, pubkey TEXT DEFAULT NULL, privkey TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX users_cron_saldo ON y.users (cron_saldo)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE c.agg');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE c.cache');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE c.log');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE c.queue');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE e.event');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE migration.versions');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE x.apikeys');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE x.categories');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE x.city_distance');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE x.config');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE x.contact');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE x.cron');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE x.eventlog');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE x.interletsq');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE x.letsgroups');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE x.lists');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE x.listsubscriptions');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE x.messages');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE x.msgpictures');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE x.news');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE x.openid');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE x.ostatus_queue');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE x.parameters');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE x.prefix');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE x.regions');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE x.stats');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE x.tokens');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE x.transactions');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE x.type_contact');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE x.users');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE xdb.ag');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE xdb.aggs');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE xdb.cache');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE xdb.ev');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE xdb.events');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE xdb.logs');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE xdb.queue');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE y.apikeys');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE y.categories');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE y.city_distance');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE y.config');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE y.contact');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE y.cron');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE y.eventlog');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE y.interletsq');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE y.letsgroups');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE y.lists');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE y.listsubscriptions');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE y.messages');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE y.msgpictures');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE y.news');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE y.openid');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE y.ostatus_queue');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE y.parameters');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE y.prefix');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE y.regions');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE y.stats');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE y.tokens');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE y.transactions');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE y.type_contact');
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE y.users');
    }
}
