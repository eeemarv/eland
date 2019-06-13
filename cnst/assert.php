<?php

namespace cnst;

class assert
{
    const ADMIN = 'a';
    const CONFIG_TAB = 'system-name|currency|mail-addr|balance|periodic-mail|contact|register|messages|users|news|forum|system';
    const ELAS_TOKEN = 'elasv2[0-9a-f]+';
    const FORUM_ID = '[0-9a-f]{24}';
    const GUEST = '[gua]';
    const NUMBER = '\d+';
    const DOC_ID = '[0-9a-f]{24}';
    const DOC_MAP_ID = '[0-9a-f]{24}';
    const LOCALE = 'nl';
    const SYSTEM = '[a-z][a-z0-9]*';
    const SCHEMA = '[a-z][a-z0-9]*';
    const TOKEN = '[a-z0-9-]{12}';
    const USER = '[ua]';
    const VIEW = 'extended|list|map|tiles';
    const PRIMARY_STATUS = 'active|inactive|im|ip|extern';
}
