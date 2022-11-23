<?php

require_once __DIR__ . '/vendor/autoload.php';

function rrmdir($dir)
{
  if (is_dir($dir)) {
    $objects = scandir($dir);
    foreach ($objects as $object) {
      if ($object != "." && $object != "..") {
        if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && !is_link($dir . "/" . $object))
          rrmdir($dir . DIRECTORY_SEPARATOR . $object);
        else
          unlink($dir . DIRECTORY_SEPARATOR . $object);
      }
    }
    rmdir($dir);
  }
}

define("TOKEN", "");
define("USERNAME", "");
define("REPO", "");
define("FILENAME", "");
define("MODULE", "");

$payload = $_POST["payload"] ?? NULL;
if ($payload === NULL) die;
$received_post = json_decode($payload);
$action = $received_post->action;
if ($action !== "released") die;

sleep(2);

$security_token = $_GET["security_token"] ?? NULL;
if ($security_token !== "") die("not valid");

$client = new \Github\Client();
$client->authenticate(TOKEN, null, Github\AuthMethod::ACCESS_TOKEN);

$last = $client->api('repo')->releases()->latest(USERNAME, REPO);
$id = $last["id"];

$assets = $client->api('repo')->releases()->assets()->all(USERNAME, REPO, $id);
$asset_id = $assets[0]["id"];

$zip = $client->api('repo')->releases()->assets()->show(USERNAME, REPO, $asset_id, true);

file_put_contents(FILENAME, $zip);
rrmdir("../wp-content/plugins/" . MODULE);
$zip = new ZipArchive;
$res = $zip->open(FILENAME);
$zip->extractTo("../wp-content/plugins/");
$zip->close();
unlink(FILENAME);

$time = date("Y-m-d H:i:s");

file_put_contents("call.log", "Chiamata ricevuta alle $time e cartella aggiornata \n\n", FILE_APPEND);

echo "done $time";
