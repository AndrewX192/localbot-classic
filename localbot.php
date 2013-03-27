<?php
/**
 * LocalBot Classic - PHP IRC bot.
 * @author Andrew Sorensen <andrew@localcoast.net>
 *
 * LocalBot startup script.
 */
 
if (!file_exists('config.php')) {
  die("The file 'config.php' is missing, please make sure it exists.");
}

require_once "config.php";

if (!file_exists('lib/localbot.php')) {
  die("The file 'localbot.php' in lib/ is missing, please check your installation.");
}

require_once "lib/localbot.php";

$start_time = microtime(true);
$localbot = new localbot($config);

echo "[LocalBot: Loaded all plugins in " . round($start_time - microtime(true), 3) . " seconds.]\n";


while($localbot->reconnect)
{
  if (!$localbot->connect()) {
    die("LocalBot was unable to connect, exiting.\n");
  }
  sleep(3);
  $localbot->listen(); // run this as long as were connected
  if(!$localbot->reconnect)
  {
    die("Exiting.\n");
  }
  echo "Please stand by while LocalBot is restarts.\n";
  sleep(10);
}