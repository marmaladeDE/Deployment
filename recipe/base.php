<?php
/**
 * Define basic tasks for deployment
 */

require 'recipe/common.php';

$baseDir = dirname(__DIR__);
$projectDir = dirname($baseDir);
$configDir = "$projectDir/config/deploy";
$archiveDir = "$projectDir/archives";

serverList("$configDir/servers.yaml");
