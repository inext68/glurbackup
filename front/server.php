<?php

include('../../../inc/includes.php');

// Carica esplicitamente la classe server legacy wrapper
require_once dirname(__DIR__) . '/src/Server.php';

Session::checkCentralAccess();
PluginGlurbackupServer::denyIfNoAdminRead();

Html::header(
    PluginGlurbackupServer::getTypeName(Session::getPluralNumber()),
    $_SERVER['PHP_SELF'],
    'admin',
    'PluginGlurbackupServer'
);

if (PluginGlurbackupServer::canAdminUpdate()) {
    global $CFG_GLPI;
    echo "<div class='mb-3'>";
    echo "<a class='btn btn-primary' href='" . $CFG_GLPI['root_doc'] . "/plugins/glurbackup/front/server.form.php?id=0'>";
    echo PluginGlurbackupServer::h(__('Add'));
    echo "</a>";
    echo "</div>";
}

Search::show('PluginGlurbackupServer');

Html::footer();
