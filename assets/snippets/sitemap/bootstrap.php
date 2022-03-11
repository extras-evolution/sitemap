<?php

if(!defined('MODX_BASE_PATH'))exit('-');
ini_set('max_execution_time', 0);
ini_set('memory_limit', '-1');

include_once __DIR__ . '/functions.inc.php';
return include __DIR__ . '/sitemap.php';
