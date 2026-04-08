<?php

include('../../../inc/includes.php');

// Carica esplicitamente la classe server legacy wrapper
require_once dirname(__DIR__) . '/src/Server.php';

Session::checkCentralAccess();

$server = new PluginGlurbackupServer();

if (isset($_POST['add'])) {
    PluginGlurbackupServer::denyIfNoAdminUpdate();

    $newID = $server->add($_POST);
    if ($newID) {
        Html::redirect($server->getFormURLWithID((int) $newID));
        return;
    }

    Html::back();
    return;
}

if (isset($_POST['update'])) {
    PluginGlurbackupServer::denyIfNoAdminUpdate();

    $server->update($_POST);
    Html::redirect($server->getFormURLWithID((int) ($_POST['id'] ?? 0)));
    return;
}

if (isset($_POST['delete'])) {
    PluginGlurbackupServer::denyIfNoAdminUpdate();

    $server->delete($_POST, true);
    $server->redirectToList();
    return;
}

PluginGlurbackupServer::denyIfNoAdminRead();

$id = (int) ($_GET['id'] ?? 0);

Html::header(
    PluginGlurbackupServer::getTypeName(1),
    $_SERVER['PHP_SELF'],
    'admin',
    'PluginGlurbackupServer'
);

// Non usare ->display() qui per evitare i problemi già visti con i tab AJAX
$server->showForm($id);

Html::footer();
