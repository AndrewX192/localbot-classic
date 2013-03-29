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

$config = array();
require_once "config.php";

if (!file_exists('lib/localbot.php')) {
  die("The file 'localbot.php' in lib/ is missing, please check your installation.");
}

require_once "lib/LocalBot.php";

$start_time = microtime(true);
$localbot = new LocalBot($config);

echo "[LocalBot: Loaded all plugins in " . round(
        microtime(true) - $start_time, 3) . " seconds.]" . PHP_EOL;


while($localbot->shouldReconnect())
{
  if (!$localbot->connect()) {
    die("LocalBot was unable to connect, exiting.\n");
  }
  sleep(3);
  $localbot->listen(); // run this as long as were connected
  if (!$localbot->shouldReconnect())
  {
    die("Exiting.\n");
  }
  echo "Please stand by while LocalBot is restarts.\n";
  sleep(10);
}