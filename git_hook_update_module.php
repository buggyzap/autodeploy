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

$delete_path = getDeletePath();
$extract_path = getExtractPath();

if ($delete_path === false || $extract_path === false) die("Unrecognized CMS. Supported: Prestashop and Wordpress");

rrmdir($delete_path);
$zip = new ZipArchive;
$res = $zip->open(FILENAME);
$zip->extractTo($extract_path);
$zip->close();
unlink(FILENAME);


$time = date("Y-m-d H:i:s");

file_put_contents("call.log", "Chiamata ricevuta alle $time e cartella aggiornata \n\n", FILE_APPEND);

echo "done $time";
