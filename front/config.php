<?php

include('../../../inc/includes.php');

require_once dirname(__DIR__) . '/src/Server.php';
require_once dirname(__DIR__) . '/src/Config.php';

Session::checkCentralAccess();
\PluginGlurbackup\Config::denyIfNoAdminRead();

$config = new \PluginGlurbackup\Config();
$id     = \PluginGlurbackup\Config::getSingletonId();

Html::header(
    __('Glurbackup', 'glurbackup'),
    $_SERVER['PHP_SELF'],
    'config',
    'PluginGlurbackupServer'
);

echo "<div class='center'>";
echo "<h2>" . htmlspecialchars(__('Glurbackup', 'glurbackup'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>";
echo "</div>";

$config->showForm($id);

Html::footer();
