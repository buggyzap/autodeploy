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

function getDeletePath()
{
  return (isWordpress() ? "../wp-content/plugins/" . MODULE : isPrestashop()) ? "../modules/" . MODULE : false;
}

function getExtractPath()
{
  return (isWordpress() ? "../wp-content/plugins/" : isPrestashop()) ? "../modules/" : false;
}

function isWordpress()
{
  return file_exists("../wp-content/") && file_exists("../wp-config.php");
}

function isPrestashop()
{
  return file_exists("../modules/") && file_exists("../config/defines.inc.php") && file_exists("../src/PrestaShopBundle/");
}

$config = file_get_contents("autodeploy.config.json");

if (!$config) {
  file_put_contents("error.log", "File di configurazione mancante. Crea il file autodeploy.config.json \n\n", FILE_APPEND);
  die;
}

$config = json_decode($config);

define("TOKEN", $config->token);
define("USERNAME", $config->username);
define("REPO", $config->repo);
define("FILENAME", $config->filename);
define("MODULE", $config->module);

$payload = $_POST["payload"] ?? NULL;
if ($payload === NULL) die;
$received_post = json_decode($payload);
$action = $received_post->action;
if ($action !== "released") die;

sleep(2);

$security_token = $_GET["security_token"] ?? NULL;
if ($security_token !== $config->security_token) die("not valid");

$client = new \Github\Client();
$client->authenticate(TOKEN, null, Github\AuthMethod::ACCESS_TOKEN);

$last = $client->api('repo')->releases()->latest(USERNAME, REPO);
$id = $last["id"];

$assets = $client->api('repo')->releases()->assets()->all(USERNAME, REPO, $id);
$asset_id = $assets[0]["id"];

$zip = $client->api('repo')->releases()->assets()->show(USERNAME, REPO, $asset_id, true);

file_put_contents(FILENAME, $zip);

$delete_path = getDeletePath();
$extract_path = getExtractPath();

if ($delete_path === false || $extract_path === false) {
  file_put_contents("error.log", "Unrecognized CMS. Supported: Prestashop and Wordpress \n\n", FILE_APPEND);
  die;
}

rrmdir($delete_path);
$zip = new ZipArchive;
$res = $zip->open(FILENAME);
$zip->extractTo($extract_path);
$zip->close();
unlink(FILENAME);


$time = date("Y-m-d H:i:s");

file_put_contents("autodeploy.log", "Received and updated at $time \n\n", FILE_APPEND);

echo "done $time";
