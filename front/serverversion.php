<?php

use PluginGlurbackup\ServerVersion;

include(__DIR__ . '/../../../inc/includes.php');
Session::checkLoginUser();

Plugin::load('glurbackup', true);

$dropdown = new ServerVersion();
include(GLPI_ROOT . '/front/dropdown.common.php');
