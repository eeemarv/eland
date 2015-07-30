<?php

$baseurl = $_SERVER['HTTP_HOST'];

if(!isset($rootpath))
{
	$rootpath = '';
}

$cdn_jqplot = (getenv('ELAS_CDN_JQPLOT')) ?: '//cdnjs.cloudflare.com/ajax/libs/jqPlot/1.0.8/';
$cdn_jquery = (getenv('ELAS_CDN_JQUERY')) ?: '//code.jquery.com/jquery-2.1.3.min.js';
$cdn_typeahead = (getenv('ELAS_CDN_TYPEAHEAD')) ?: '//cdnjs.cloudflare.com/ajax/libs/typeahead.js/0.10.4/typeahead.bundle.min.js';
$cdn_datepicker_css = (getenv('ELAS_CDN_DATEPICKER_CSS')) ?: '//cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.0/css/bootstrap-datepicker.standalone.min.css';
$cdn_datepicker = (getenv('ELAS_CDN_DATEPICKER')) ?: '//cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.0/js/bootstrap-datepicker.min.js';
$cdn_datepicker_nl = (getenv('ELAS_CDN_DATEPICKER_NL')) ?: '//cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.0/locales/bootstrap-datepicker.nl.min.js';

require_once $rootpath . 'vendor/autoload.php';
require_once $rootpath . 'includes/inc_session.php';
require_once $rootpath . 'includes/inc_eventlog.php';
require_once $rootpath . 'includes/inc_redis.php';
require_once $rootpath . 'includes/inc_setstatus.php';
require_once $rootpath . 'includes/inc_timezone.php';
require_once $rootpath . 'includes/inc_version.php';
require_once $rootpath . 'includes/inc_alert.php';

$alert = new alert();
