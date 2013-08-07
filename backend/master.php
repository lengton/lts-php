#!/usr/bin/php 
<?php
$loader_path = false;
if ($argv && isset ($argv[1]))
{
    $loader_path = trim ($argv[1]);
} // Has command line arguments?

// Setup include path
$abs_path = getcwd();

// Ran inside lts/backend?
if (($spos = strpos ($abs_path, '/lts/backend')) !== false)
{
    $abs_path = substr ($abs_path, 0, $spos);
} else if (($spos = strpos ($abs_path, '/html')) !== false)
{
    $abs_path = substr ($abs_path, 0, $spos);
} // In backend directory?

ini_set ('include_path', ini_get('include_path').':'.$abs_path.'/lts/');

function ltsClassLoader ($cn) { @include_once 'class/'.$cn.'.php';  } // LTS SYSTEM LOADER
//function customClassLoader ($cn) { @include_once 'data/'.$page_vars['ln'].'/class/'.$cn.'.php'; } // CUSTOM CLASSES LOADER

spl_autoload_register ('ltsClassLoader');
//spl_autoload_register ('customClassLoader');

$backend = new ltsBackend(2, $loader_path);
// Since we're the master, we need to 
// force clean the shared memory segment
$backend->clean();
$backend->run();

?>