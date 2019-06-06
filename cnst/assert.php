<?php

namespace cnst;

class assert
{
    const ADMIN = '[a]';
    const GUEST = '[gua]';
    const NUMBER = '\d+';
    const LOCALE = 'nl';
    const SYSTEM = '[a-z][a-z0-9]*';
    const SCHEMA = '[a-z][a-z0-9]*';
    const TOKEN = '[a-z0-9-]{12}';
    const USER = '[ua]';
    const VIEW = 'extended|list|map|tiles';
    const PRIMARY_STATUS = 'active|inactive|im|ip|extern';
}
