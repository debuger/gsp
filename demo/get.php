<?php
/**
 * Created by PhpStorm.
 * User: rym
 * Date: 07.09.14
 * Time: 15:05
 */
require_once '../vendor/autoload.php';
$gsp = new Rym\GetSinglePage('../cache');
$url = $_POST['url'];
$prefix = $_POST['pref'];
$result = $gsp->get($url, $prefix);
echo(json_encode($result));