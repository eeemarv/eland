<?php
// default timezone to Europe/Brussels (read from config file removed, use env var instead)
$elas_timezone = getenv('ELAS_TIMEZONE');
$elas_timezone = ($elas_timezone) ? $elas_timezone : 'Europe/Brussels';
date_default_timezone_set($elas_timezone);
