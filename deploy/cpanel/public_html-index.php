<?php

/*
| Option C (copy) front controller for cPanel accounts where the document root
| cannot be changed and cannot be a symlink.
|
| Layout:
|   /home/USER/beercheck/      <- Laravel app (NOT web accessible)
|   /home/USER/public_html/    <- contents of beercheck/public/ + this file as index.php
*/

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$app = dirname(__DIR__).'/beercheck';

if (file_exists($maintenance = $app.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $app.'/vendor/autoload.php';

/** @var Application $laravel */
$laravel = require_once $app.'/bootstrap/app.php';

// Tell Laravel that public_html is the public path (Vite manifest, asset() etc).
$laravel->usePublicPath(__DIR__);

$laravel->handleRequest(Request::capture());
