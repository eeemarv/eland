<?php

if (php_sapi_name() !== 'cli')
{
	echo '-- cli only --';
	exit;
}

echo 'worker start';
