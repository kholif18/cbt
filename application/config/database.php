<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$active_group = 'default';
$query_builder = TRUE;

$db['default'] = array(
    'dsn'      => '',
    'hostname' => getenv('CBT_DB_HOST') ?: 'cbt-db',
    'username' => getenv('CBT_DB_USER') ?: 'garudacbt',
    'password' => getenv('CBT_DB_PASS') ?: '',
    'database' => getenv('CBT_DB_NAME') ?: 'garudacbt',
    'dbdriver' => 'mysqli',
    'dbprefix' => '',
    'pconnect' => FALSE,
    'db_debug' => (getenv('CBT_ENV') === 'production') ? FALSE : TRUE,
    'cache_on' => FALSE,
    'cachedir' => '',
    'char_set' => 'utf8mb4',
    'dbcollat' => 'utf8mb4_general_ci',
    'swap_pre' => '',
    'encrypt'  => FALSE,
    'compress' => FALSE,
    'stricton' => FALSE,
    'failover' => array(),
    'save_queries' => FALSE
);
