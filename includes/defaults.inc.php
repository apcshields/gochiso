<?php
  // error_reporting(E_STRICT | E_ALL); //!
  // ini_set('display_errors', true); //!
  ini_set('display_errors', false); //!

  // Our configuration file.
  require_once 'config.inc.php';

  // Composer
  require __DIR__ . '/../vendor/autoload.php';

  if (in_array($_SERVER['HTTP_ORIGIN'], ALLOWED_DOMAINS)) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN'], true);
    header('Vary: Origin', true);
  }
