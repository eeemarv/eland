--
-- PostgreSQL database dump
--

-- Dumped from database version 10.12 (Ubuntu 10.12-0ubuntu0.18.04.1)
-- Dumped by pg_dump version 10.12 (Ubuntu 10.12-0ubuntu0.18.04.1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: x; Type: SCHEMA; Schema: -; Owner: -
--

CREATE SCHEMA x;


--
-- Name: trigger_set_canceled_at(); Type: FUNCTION; Schema: x; Owner: -
--

CREATE FUNCTION x.trigger_set_canceled_at() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
     BEGIN
     NEW.canceled_at := timezone('utc'::text, NOW());
     NEW.is_canceled := 't'::bool;
     RETURN NEW;
     END;
     $$;


--
-- Name: trigger_set_last_edit_at(); Type: FUNCTION; Schema: x; Owner: -
--

CREATE FUNCTION x.trigger_set_last_edit_at() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
     BEGIN
     NEW.last_edit_at := timezone('utc'::text, NOW());
     NEW.edit_count := OLD.edit_count + 1;
     RETURN NEW;
     END;
     $$;


--
-- Name: trigger_set_paid_at(); Type: FUNCTION; Schema: x; Owner: -
--

CREATE FUNCTION x.trigger_set_paid_at() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
     BEGIN
     NEW.paid_at := timezone('utc'::text, NOW());
     RETURN NEW;
     END;
     $$;


--
-- Name: apikeys_id_seq; Type: SEQUENCE; Schema: x; Owner: -
--

CREATE SEQUENCE x.apikeys_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: categories_id_seq; Type: SEQUENCE; Schema: x; Owner: -
--

CREATE SEQUENCE x.categories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: categories; Type: TABLE; Schema: x; Owner: -
--

CREATE TABLE x.categories (
    id integer DEFAULT nextval('x.categories_id_seq'::regclass) NOT NULL,
    name character varying(40) DEFAULT ''::character varying NOT NULL,
    id_parent integer DEFAULT 0 NOT NULL,
    description character varying(60),
    cdate timestamp without time zone,
    id_creator integer DEFAULT 0 NOT NULL,
    fullname character varying(100),
    leafnote integer DEFAULT 0 NOT NULL,
    stat_msgs_wanted integer DEFAULT 0,
    stat_msgs_offers integer DEFAULT 0
);


--
-- Name: config; Type: TABLE; Schema: x; Owner: -
--

CREATE TABLE x.config (
    created_at timestamp without time zone DEFAULT timezone('utc'::text, now()) NOT NULL,
    last_edit_at timestamp without time zone DEFAULT timezone('utc'::text, now()) NOT NULL,
    id text NOT NULL,
    data jsonb DEFAULT '{}'::jsonb NOT NULL,
    edit_count integer DEFAULT 0 NOT NULL,
    user_id integer
);


--
-- Name: contact_id_seq; Type: SEQUENCE; Schema: x; Owner: -
--

CREATE SEQUENCE x.contact_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: contact; Type: TABLE; Schema: x; Owner: -
--

CREATE TABLE x.contact (
    id integer DEFAULT nextval('x.contact_id_seq'::regclass) NOT NULL,
    id_type_contact integer DEFAULT 0 NOT NULL,
    comments text,
    value text NOT NULL,
    id_user integer DEFAULT 0 NOT NULL,
    access text DEFAULT 'admin'::text NOT NULL
);


--
-- Name: doc_maps; Type: TABLE; Schema: x; Owner: -
--

CREATE TABLE x.doc_maps (
    id integer NOT NULL,
    created_at timestamp without time zone DEFAULT timezone('utc'::text, now()) NOT NULL,
    last_edit_at timestamp without time zone DEFAULT timezone('utc'::text, now()) NOT NULL,
    name text NOT NULL,
    user_id integer NOT NULL,
    edit_count integer DEFAULT 0 NOT NULL
);


--
-- Name: doc_maps_id_seq; Type: SEQUENCE; Schema: x; Owner: -
--

CREATE SEQUENCE x.doc_maps_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: doc_maps_id_seq; Type: SEQUENCE OWNED BY; Schema: x; Owner: -
--

ALTER SEQUENCE x.doc_maps_id_seq OWNED BY x.doc_maps.id;


--
-- Name: docs; Type: TABLE; Schema: x; Owner: -
--

CREATE TABLE x.docs (
    id integer NOT NULL,
    created_at timestamp without time zone DEFAULT timezone('utc'::text, now()) NOT NULL,
    last_edit_at timestamp without time zone DEFAULT timezone('utc'::text, now()) NOT NULL,
    map_id integer,
    filename text NOT NULL,
    original_filename text,
    name text,
    user_id integer NOT NULL,
    access text DEFAULT 'admin'::text NOT NULL,
    edit_count integer DEFAULT 0 NOT NULL
);


--
-- Name: docs_id_seq; Type: SEQUENCE; Schema: x; Owner: -
--

CREATE SEQUENCE x.docs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: docs_id_seq; Type: SEQUENCE OWNED BY; Schema: x; Owner: -
--

ALTER SEQUENCE x.docs_id_seq OWNED BY x.docs.id;


--
-- Name: emails; Type: TABLE; Schema: x; Owner: -
--

CREATE TABLE x.emails (
    id integer NOT NULL,
    subject text NOT NULL,
    content text NOT NULL,
    sent_to jsonb NOT NULL,
    route text NOT NULL,
    created_by integer NOT NULL,
    created_at timestamp without time zone DEFAULT timezone('utc'::text, now()) NOT NULL
);


--
-- Name: emails_id_seq; Type: SEQUENCE; Schema: x; Owner: -
--

CREATE SEQUENCE x.emails_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: emails_id_seq; Type: SEQUENCE OWNED BY; Schema: x; Owner: -
--

ALTER SEQUENCE x.emails_id_seq OWNED BY x.emails.id;


--
-- Name: forum_posts; Type: TABLE; Schema: x; Owner: -
--

CREATE TABLE x.forum_posts (
    created_at timestamp without time zone DEFAULT timezone('utc'::text, now()) NOT NULL,
    last_edit_at timestamp without time zone DEFAULT timezone('utc'::text, now()) NOT NULL,
    id integer NOT NULL,
    topic_id integer,
    content text NOT NULL,
    edit_count integer DEFAULT 0 NOT NULL,
    user_id integer NOT NULL
);


--
-- Name: forum_posts_id_seq; Type: SEQUENCE; Schema: x; Owner: -
--

CREATE SEQUENCE x.forum_posts_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: forum_posts_id_seq; Type: SEQUENCE OWNED BY; Schema: x; Owner: -
--

ALTER SEQUENCE x.forum_posts_id_seq OWNED BY x.forum_posts.id;


--
-- Name: forum_topics; Type: TABLE; Schema: x; Owner: -
--

CREATE TABLE x.forum_topics (
    created_at timestamp without time zone DEFAULT timezone('utc'::text, now()) NOT NULL,
    last_edit_at timestamp without time zone DEFAULT timezone('utc'::text, now()) NOT NULL,
    id integer NOT NULL,
    access text NOT NULL,
    subject text NOT NULL,
    user_id integer NOT NULL,
    edit_count integer DEFAULT 0 NOT NULL
);


--
-- Name: forum_topics_id_seq; Type: SEQUENCE; Schema: x; Owner: -
--

CREATE SEQUENCE x.forum_topics_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: forum_topics_id_seq; Type: SEQUENCE OWNED BY; Schema: x; Owner: -
--

ALTER SEQUENCE x.forum_topics_id_seq OWNED BY x.forum_topics.id;


--
-- Name: letsgroups_id_seq; Type: SEQUENCE; Schema: x; Owner: -
--

CREATE SEQUENCE x.letsgroups_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: letsgroups; Type: TABLE; Schema: x; Owner: -
--

CREATE TABLE x.letsgroups (
    id integer DEFAULT nextval('x.letsgroups_id_seq'::regclass) NOT NULL,
    groupname character varying(128) NOT NULL,
    shortname character varying(50) NOT NULL,
    prefix character varying(5),
    apimethod character varying(20) NOT NULL,
    remoteapikey character varying(80),
    localletscode character varying(20) NOT NULL,
    myremoteletscode character varying(20) NOT NULL,
    url character varying(256),
    elassoapurl character varying(256),
    presharedkey character varying(80),
    pubkey text
);


--
-- Name: login; Type: TABLE; Schema: x; Owner: -
--

CREATE TABLE x.login (
    id integer NOT NULL,
    user_id integer NOT NULL,
    ip text,
    agent text,
    created_at timestamp without time zone DEFAULT timezone('utc'::text, now()) NOT NULL
);


--
-- Name: login_id_seq; Type: SEQUENCE; Schema: x; Owner: -
--

CREATE SEQUENCE x.login_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: login_id_seq; Type: SEQUENCE OWNED BY; Schema: x; Owner: -
--

ALTER SEQUENCE x.login_id_seq OWNED BY x.login.id;


--
-- Name: logout; Type: TABLE; Schema: x; Owner: -
--

CREATE TABLE x.logout (
    id integer NOT NULL,
    user_id integer NOT NULL,
    ip text,
    agent text,
    created_at timestamp without time zone DEFAULT timezone('utc'::text, now()) NOT NULL
);


--
-- Name: logout_id_seq; Type: SEQUENCE; Schema: x; Owner: -
--

CREATE SEQUENCE x.logout_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: logout_id_seq; Type: SEQUENCE OWNED BY; Schema: x; Owner: -
--

ALTER SEQUENCE x.logout_id_seq OWNED BY x.logout.id;


--
-- Name: messages_id_seq; Type: SEQUENCE; Schema: x; Owner: -
--

CREATE SEQUENCE x.messages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: messages; Type: TABLE; Schema: x; Owner: -
--

CREATE TABLE x.messages (
    id integer DEFAULT nextval('x.messages_id_seq'::regclass) NOT NULL,
    cdate timestamp without time zone,
    mdate timestamp without time zone,
    validity timestamp without time zone,
    id_category integer DEFAULT 0 NOT NULL,
    id_user integer DEFAULT 0 NOT NULL,
    content text NOT NULL,
    "Description" text,
    amount integer,
    units character varying(15),
    msg_type integer DEFAULT 0 NOT NULL,
    exp_user_warn boolean DEFAULT false NOT NULL,
    image_files jsonb,
    access text,
    created_at timestamp without time zone DEFAULT timezone('utc'::text, now()) NOT NULL,
    last_edit_at timestamp without time zone DEFAULT timezone('utc'::text, now()) NOT NULL,
    edit_count integer DEFAULT 0 NOT NULL
);


--
-- Name: mollie_payment_requests; Type: TABLE; Schema: x; Owner: -
--

CREATE TABLE x.mollie_payment_requests (
    id integer NOT NULL,
    description text NOT NULL,
    created_by integer NOT NULL,
    created_at timestamp without time zone DEFAULT timezone('utc'::text, now()) NOT NULL
);


--
-- Name: mollie_payment_requests_id_seq; Type: SEQUENCE; Schema: x; Owner: -
--

CREATE SEQUENCE x.mollie_payment_requests_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: mollie_payment_requests_id_seq; Type: SEQUENCE OWNED BY; Schema: x; Owner: -
--

ALTER SEQUENCE x.mollie_payment_requests_id_seq OWNED BY x.mollie_payment_requests.id;


--
-- Name: mollie_payments; Type: TABLE; Schema: x; Owner: -
--

CREATE TABLE x.mollie_payments (
    id integer NOT NULL,
    user_id integer NOT NULL,
    mollie_payment_id text,
    request_id integer NOT NULL,
    emails_sent jsonb DEFAULT '[]'::jsonb NOT NULL,
    amount numeric(5,2) NOT NULL,
    currency text NOT NULL,
    mollie_status text,
    is_canceled boolean DEFAULT false NOT NULL,
    canceled_at timestamp without time zone,
    canceled_by integer,
    is_paid boolean DEFAULT false NOT NULL,
    paid_at timestamp without time zone,
    created_by integer NOT NULL,
    created_at timestamp without time zone DEFAULT timezone('utc'::text, now()) NOT NULL,
    last_edit_at timestamp without time zone DEFAULT timezone('utc'::text, now()) NOT NULL,
    token text,
    edit_count integer DEFAULT 0 NOT NULL
);


--
-- Name: mollie_payments_id_seq; Type: SEQUENCE; Schema: x; Owner: -
--

CREATE SEQUENCE x.mollie_payments_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: mollie_payments_id_seq; Type: SEQUENCE OWNED BY; Schema: x; Owner: -
--

ALTER SEQUENCE x.mollie_payments_id_seq OWNED BY x.mollie_payments.id;


--
-- Name: msgpictures_id_seq; Type: SEQUENCE; Schema: x; Owner: -
--

CREATE SEQUENCE x.msgpictures_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: news_id_seq; Type: SEQUENCE; Schema: x; Owner: -
--

CREATE SEQUENCE x.news_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: news; Type: TABLE; Schema: x; Owner: -
--

CREATE TABLE x.news (
    id integer DEFAULT nextval('x.news_id_seq'::regclass) NOT NULL,
    id_user integer DEFAULT 0 NOT NULL,
    headline character varying(200) DEFAULT ''::character varying NOT NULL,
    newsitem text NOT NULL,
    cdate timestamp without time zone,
    itemdate timestamp without time zone NOT NULL,
    approved boolean NOT NULL,
    sticky boolean,
    location character varying(128),
    access text,
    created_at timestamp without time zone DEFAULT timezone('utc'::text, now()) NOT NULL,
    last_edit_at timestamp without time zone DEFAULT timezone('utc'::text, now()) NOT NULL,
    edit_count integer DEFAULT 0 NOT NULL
);


--
-- Name: openid_id_seq; Type: SEQUENCE; Schema: x; Owner: -
--

CREATE SEQUENCE x.openid_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ostatus_queue_id_seq; Type: SEQUENCE; Schema: x; Owner: -
--

CREATE SEQUENCE x.ostatus_queue_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: regions_id_seq; Type: SEQUENCE; Schema: x; Owner: -
--

CREATE SEQUENCE x.regions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: static_content; Type: TABLE; Schema: x; Owner: -
--

CREATE TABLE x.static_content (
    id text NOT NULL,
    lang text NOT NULL,
    data jsonb DEFAULT '{}'::jsonb NOT NULL,
    created_at timestamp without time zone DEFAULT timezone('utc'::text, now()),
    last_edit_at timestamp without time zone DEFAULT timezone('utc'::text, now()),
    edit_count integer DEFAULT 0 NOT NULL,
    created_by integer NOT NULL,
    last_edit_by integer NOT NULL
);


--
-- Name: static_content_images; Type: TABLE; Schema: x; Owner: -
--

CREATE TABLE x.static_content_images (
    file text NOT NULL,
    created_at timestamp without time zone DEFAULT timezone('utc'::text, now()),
    created_by integer NOT NULL
);


--
-- Name: transactions_id_seq; Type: SEQUENCE; Schema: x; Owner: -
--

CREATE SEQUENCE x.transactions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: transactions; Type: TABLE; Schema: x; Owner: -
--

CREATE TABLE x.transactions (
    id integer DEFAULT nextval('x.transactions_id_seq'::regclass) NOT NULL,
    amount integer DEFAULT 0 NOT NULL,
    description text DEFAULT '0'::character varying NOT NULL,
    id_from integer DEFAULT 0 NOT NULL,
    id_to integer DEFAULT 0 NOT NULL,
    real_from text,
    real_to text,
    transid text,
    creator integer DEFAULT 0 NOT NULL,
    cdate timestamp without time zone,
    created_at timestamp without time zone DEFAULT timezone('utc'::text, now()) NOT NULL,
    last_edit_at timestamp without time zone DEFAULT timezone('utc'::text, now()) NOT NULL,
    edit_count integer DEFAULT 0 NOT NULL
);


--
-- Name: type_contact_id_seq; Type: SEQUENCE; Schema: x; Owner: -
--

CREATE SEQUENCE x.type_contact_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: type_contact; Type: TABLE; Schema: x; Owner: -
--

CREATE TABLE x.type_contact (
    id integer DEFAULT nextval('x.type_contact_id_seq'::regclass) NOT NULL,
    name text DEFAULT ''::character varying NOT NULL,
    abbrev text DEFAULT ''::character varying NOT NULL
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: x; Owner: -
--

CREATE SEQUENCE x.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users; Type: TABLE; Schema: x; Owner: -
--

CREATE TABLE x.users (
    id integer DEFAULT nextval('x.users_id_seq'::regclass) NOT NULL,
    cdate timestamp without time zone,
    mdate timestamp without time zone,
    creator integer,
    comments text,
    hobbies text,
    name text DEFAULT ''::character varying NOT NULL,
    birthday date,
    letscode text,
    postcode text,
    cron_saldo boolean,
    password text,
    accountrole text DEFAULT ''::character varying NOT NULL,
    status integer DEFAULT 0 NOT NULL,
    saldo integer DEFAULT 0 NOT NULL,
    minlimit integer,
    maxlimit integer,
    fullname text,
    admincomment text,
    adate timestamp without time zone,
    image_file text,
    remote_schema text,
    is_intersystem boolean DEFAULT false NOT NULL,
    fullname_access text DEFAULT 'admin'::text NOT NULL,
    created_at timestamp without time zone DEFAULT timezone('utc'::text, now()) NOT NULL,
    last_edit_at timestamp without time zone DEFAULT timezone('utc'::text, now()) NOT NULL,
    edit_count integer DEFAULT 0 NOT NULL,
    last_login_at timestamp without time zone,
    login_count integer DEFAULT 0 NOT NULL,
    has_open_mollie_payment boolean DEFAULT false NOT NULL
);


--
-- Name: doc_maps id; Type: DEFAULT; Schema: x; Owner: -
--

ALTER TABLE ONLY x.doc_maps ALTER COLUMN id SET DEFAULT nextval('x.doc_maps_id_seq'::regclass);


--
-- Name: docs id; Type: DEFAULT; Schema: x; Owner: -
--

ALTER TABLE ONLY x.docs ALTER COLUMN id SET DEFAULT nextval('x.docs_id_seq'::regclass);


--
-- Name: emails id; Type: DEFAULT; Schema: x; Owner: -
--

ALTER TABLE ONLY x.emails ALTER COLUMN id SET DEFAULT nextval('x.emails_id_seq'::regclass);


--
-- Name: forum_posts id; Type: DEFAULT; Schema: x; Owner: -
--

ALTER TABLE ONLY x.forum_posts ALTER COLUMN id SET DEFAULT nextval('x.forum_posts_id_seq'::regclass);


--
-- Name: forum_topics id; Type: DEFAULT; Schema: x; Owner: -
--

ALTER TABLE ONLY x.forum_topics ALTER COLUMN id SET DEFAULT nextval('x.forum_topics_id_seq'::regclass);


--
-- Name: login id; Type: DEFAULT; Schema: x; Owner: -
--

ALTER TABLE ONLY x.login ALTER COLUMN id SET DEFAULT nextval('x.login_id_seq'::regclass);


--
-- Name: logout id; Type: DEFAULT; Schema: x; Owner: -
--

ALTER TABLE ONLY x.logout ALTER COLUMN id SET DEFAULT nextval('x.logout_id_seq'::regclass);


--
-- Name: mollie_payment_requests id; Type: DEFAULT; Schema: x; Owner: -
--

ALTER TABLE ONLY x.mollie_payment_requests ALTER COLUMN id SET DEFAULT nextval('x.mollie_payment_requests_id_seq'::regclass);


--
-- Name: mollie_payments id; Type: DEFAULT; Schema: x; Owner: -
--

ALTER TABLE ONLY x.mollie_payments ALTER COLUMN id SET DEFAULT nextval('x.mollie_payments_id_seq'::regclass);


--
-- Name: categories categories_id_pkey; Type: CONSTRAINT; Schema: x; Owner: -
--

ALTER TABLE ONLY x.categories
    ADD CONSTRAINT categories_id_pkey PRIMARY KEY (id);


--
-- Name: config config_pkey; Type: CONSTRAINT; Schema: x; Owner: -
--

ALTER TABLE ONLY x.config
    ADD CONSTRAINT config_pkey PRIMARY KEY (id);


--
-- Name: contact contact_id_pkey; Type: CONSTRAINT; Schema: x; Owner: -
--

ALTER TABLE ONLY x.contact
    ADD CONSTRAINT contact_id_pkey PRIMARY KEY (id);


--
-- Name: static_content_images content_images_pkey; Type: CONSTRAINT; Schema: x; Owner: -
--

ALTER TABLE ONLY x.static_content_images
    ADD CONSTRAINT content_images_pkey PRIMARY KEY (file);


--
-- Name: doc_maps doc_maps_pkey; Type: CONSTRAINT; Schema: x; Owner: -
--

ALTER TABLE ONLY x.doc_maps
    ADD CONSTRAINT doc_maps_pkey PRIMARY KEY (id);


--
-- Name: docs docs_pkey; Type: CONSTRAINT; Schema: x; Owner: -
--

ALTER TABLE ONLY x.docs
    ADD CONSTRAINT docs_pkey PRIMARY KEY (id);


--
-- Name: emails emails_pkey; Type: CONSTRAINT; Schema: x; Owner: -
--

ALTER TABLE ONLY x.emails
    ADD CONSTRAINT emails_pkey PRIMARY KEY (id);


--
-- Name: forum_posts forum_posts_pkey; Type: CONSTRAINT; Schema: x; Owner: -
--

ALTER TABLE ONLY x.forum_posts
    ADD CONSTRAINT forum_posts_pkey PRIMARY KEY (id);


--
-- Name: forum_topics forum_topics_pkey; Type: CONSTRAINT; Schema: x; Owner: -
--

ALTER TABLE ONLY x.forum_topics
    ADD CONSTRAINT forum_topics_pkey PRIMARY KEY (id);


--
-- Name: letsgroups letsgroups_id_pkey; Type: CONSTRAINT; Schema: x; Owner: -
--

ALTER TABLE ONLY x.letsgroups
    ADD CONSTRAINT letsgroups_id_pkey PRIMARY KEY (id);


--
-- Name: login login_pkey; Type: CONSTRAINT; Schema: x; Owner: -
--

ALTER TABLE ONLY x.login
    ADD CONSTRAINT login_pkey PRIMARY KEY (id);


--
-- Name: logout logout_pkey; Type: CONSTRAINT; Schema: x; Owner: -
--

ALTER TABLE ONLY x.logout
    ADD CONSTRAINT logout_pkey PRIMARY KEY (id);


--
-- Name: messages messages_id_pkey; Type: CONSTRAINT; Schema: x; Owner: -
--

ALTER TABLE ONLY x.messages
    ADD CONSTRAINT messages_id_pkey PRIMARY KEY (id);


--
-- Name: mollie_payment_requests mollie_payment_requests_pkey; Type: CONSTRAINT; Schema: x; Owner: -
--

ALTER TABLE ONLY x.mollie_payment_requests
    ADD CONSTRAINT mollie_payment_requests_pkey PRIMARY KEY (id);


--
-- Name: mollie_payments mollie_payments_pkey; Type: CONSTRAINT; Schema: x; Owner: -
--

ALTER TABLE ONLY x.mollie_payments
    ADD CONSTRAINT mollie_payments_pkey PRIMARY KEY (id);


--
-- Name: news news_id_pkey; Type: CONSTRAINT; Schema: x; Owner: -
--

ALTER TABLE ONLY x.news
    ADD CONSTRAINT news_id_pkey PRIMARY KEY (id);


--
-- Name: static_content static_content_pkey; Type: CONSTRAINT; Schema: x; Owner: -
--

ALTER TABLE ONLY x.static_content
    ADD CONSTRAINT static_content_pkey PRIMARY KEY (id, lang);


--
-- Name: transactions transactions_id_pkey; Type: CONSTRAINT; Schema: x; Owner: -
--

ALTER TABLE ONLY x.transactions
    ADD CONSTRAINT transactions_id_pkey PRIMARY KEY (id);


--
-- Name: type_contact type_contact_id_pkey; Type: CONSTRAINT; Schema: x; Owner: -
--

ALTER TABLE ONLY x.type_contact
    ADD CONSTRAINT type_contact_id_pkey PRIMARY KEY (id);


--
-- Name: users users_id_pkey; Type: CONSTRAINT; Schema: x; Owner: -
--

ALTER TABLE ONLY x.users
    ADD CONSTRAINT users_id_pkey PRIMARY KEY (id);


--
-- Name: letsgroups_groupname; Type: INDEX; Schema: x; Owner: -
--

CREATE INDEX letsgroups_groupname ON x.letsgroups USING btree (groupname);


--
-- Name: letsgroups_localletscode; Type: INDEX; Schema: x; Owner: -
--

CREATE INDEX letsgroups_localletscode ON x.letsgroups USING btree (localletscode);


--
-- Name: letsgroups_myremoteletscode; Type: INDEX; Schema: x; Owner: -
--

CREATE INDEX letsgroups_myremoteletscode ON x.letsgroups USING btree (myremoteletscode);


--
-- Name: letsgroups_shortname; Type: INDEX; Schema: x; Owner: -
--

CREATE INDEX letsgroups_shortname ON x.letsgroups USING btree (shortname);


--
-- Name: messages_id_user; Type: INDEX; Schema: x; Owner: -
--

CREATE INDEX messages_id_user ON x.messages USING btree (id_user);


--
-- Name: messages_validity; Type: INDEX; Schema: x; Owner: -
--

CREATE INDEX messages_validity ON x.messages USING btree (validity);


--
-- Name: news_approved; Type: INDEX; Schema: x; Owner: -
--

CREATE INDEX news_approved ON x.news USING btree (approved);


--
-- Name: transactions_transid; Type: INDEX; Schema: x; Owner: -
--

CREATE UNIQUE INDEX transactions_transid ON x.transactions USING btree (transid);


--
-- Name: users_cron_saldo; Type: INDEX; Schema: x; Owner: -
--

CREATE INDEX users_cron_saldo ON x.users USING btree (cron_saldo);


--
-- Name: mollie_payments set_canceled_at; Type: TRIGGER; Schema: x; Owner: -
--

CREATE TRIGGER set_canceled_at BEFORE UPDATE OF canceled_by ON x.mollie_payments FOR EACH ROW EXECUTE PROCEDURE x.trigger_set_canceled_at();


--
-- Name: config set_last_edit_at; Type: TRIGGER; Schema: x; Owner: -
--

CREATE TRIGGER set_last_edit_at BEFORE UPDATE ON x.config FOR EACH ROW EXECUTE PROCEDURE x.trigger_set_last_edit_at();


--
-- Name: doc_maps set_last_edit_at; Type: TRIGGER; Schema: x; Owner: -
--

CREATE TRIGGER set_last_edit_at BEFORE UPDATE ON x.doc_maps FOR EACH ROW EXECUTE PROCEDURE x.trigger_set_last_edit_at();


--
-- Name: docs set_last_edit_at; Type: TRIGGER; Schema: x; Owner: -
--

CREATE TRIGGER set_last_edit_at BEFORE UPDATE ON x.docs FOR EACH ROW EXECUTE PROCEDURE x.trigger_set_last_edit_at();


--
-- Name: forum_posts set_last_edit_at; Type: TRIGGER; Schema: x; Owner: -
--

CREATE TRIGGER set_last_edit_at BEFORE UPDATE ON x.forum_posts FOR EACH ROW EXECUTE PROCEDURE x.trigger_set_last_edit_at();


--
-- Name: forum_topics set_last_edit_at; Type: TRIGGER; Schema: x; Owner: -
--

CREATE TRIGGER set_last_edit_at BEFORE UPDATE ON x.forum_topics FOR EACH ROW EXECUTE PROCEDURE x.trigger_set_last_edit_at();


--
-- Name: messages set_last_edit_at; Type: TRIGGER; Schema: x; Owner: -
--

CREATE TRIGGER set_last_edit_at BEFORE UPDATE ON x.messages FOR EACH ROW EXECUTE PROCEDURE x.trigger_set_last_edit_at();


--
-- Name: mollie_payments set_last_edit_at; Type: TRIGGER; Schema: x; Owner: -
--

CREATE TRIGGER set_last_edit_at BEFORE UPDATE ON x.mollie_payments FOR EACH ROW EXECUTE PROCEDURE x.trigger_set_last_edit_at();


--
-- Name: news set_last_edit_at; Type: TRIGGER; Schema: x; Owner: -
--

CREATE TRIGGER set_last_edit_at BEFORE UPDATE ON x.news FOR EACH ROW EXECUTE PROCEDURE x.trigger_set_last_edit_at();


--
-- Name: static_content set_last_edit_at; Type: TRIGGER; Schema: x; Owner: -
--

CREATE TRIGGER set_last_edit_at BEFORE UPDATE ON x.static_content FOR EACH ROW EXECUTE PROCEDURE x.trigger_set_last_edit_at();


--
-- Name: transactions set_last_edit_at; Type: TRIGGER; Schema: x; Owner: -
--

CREATE TRIGGER set_last_edit_at BEFORE UPDATE ON x.transactions FOR EACH ROW EXECUTE PROCEDURE x.trigger_set_last_edit_at();


--
-- Name: users set_last_edit_at; Type: TRIGGER; Schema: x; Owner: -
--

CREATE TRIGGER set_last_edit_at BEFORE UPDATE ON x.users FOR EACH ROW EXECUTE PROCEDURE x.trigger_set_last_edit_at();


--
-- Name: mollie_payments set_paid_at; Type: TRIGGER; Schema: x; Owner: -
--

CREATE TRIGGER set_paid_at BEFORE UPDATE OF is_paid ON x.mollie_payments FOR EACH ROW EXECUTE PROCEDURE x.trigger_set_paid_at();


--
-- PostgreSQL database dump complete
--
