<?php
/*
 * get session name from environment variable ELAS_DOMAIN_SESSION_<domain>
 * dots in <domain> are replaced by double underscore __
 * hyphens in <domain> are replaced by triple underscore ___
 *
 * example:
 *
 * to link e-example.com to a session set environment variable
 * ELAS_DOMAIN_SESSION_E___EXAMPLE__COM = <session_name>
 *
 * + the session name has to be set to the color name of the database!
 * + session name is prefix of the image files.
 * + session name is prefix of keys in Redis.
 *
 */
 
$session_name = str_replace(':', '', $baseurl);
$session_name = str_replace('.', '__', $session_name);
$session_name = str_replace('-', '___', $session_name);
$session_name = strtoupper($session_name);
$session_name = getenv('ELAS_DOMAIN_SESSION_' . $session_name);
$session_name = ($session_name) ? $session_name : 'ELASDEFAULT';

session_name($session_name);
session_start();
