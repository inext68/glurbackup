<?php

include('../../../inc/includes.php');

require_once dirname(__DIR__) . '/src/Server.php';
require_once dirname(__DIR__) . '/src/ComputerServer.php';
require_once dirname(__DIR__) . '/src/LocationResolver.php';
require_once dirname(__DIR__) . '/src/Config.php';

Session::checkCentralAccess();

$computerId = (int) ($_POST['glurbackup_computer_id'] ?? 0);
$action     = trim((string) ($_POST['_glurbackup_action'] ?? ''));

if ($computerId <= 0 || $action === '') {
    throw new Glpi\Exception\Http\BadRequestHttpException('Missing computer id or action');
}

$redirect = static function () use ($CFG_GLPI, $computerId): void {
    Html::redirect(
        $CFG_GLPI['root_doc']
        . '/front/computer.form.php?id=' . $computerId
        . '&forcetab=PluginGlurbackupComputerTab$1'
    );
};

if (!\PluginGlurbackup\Server::canAdminUpdate()) {
    Session::addMessageAfterRedirect(__('Non hai i permessi per eseguire questa azione.', 'glurbackup'), false, ERROR);
    $redirect();
}

$allowedActions = ['link_server','backup_incr_file','backup_full_file','backup_incr_image','backup_full_image','save_default_dirs','save_internet_mode','create_client_explicit','delete_client_explicit','unlink_server'];
if (!in_array($action, $allowedActions, true)) {
    throw new Glpi\Exception\Http\BadRequestHttpException('Unsupported action');
}
if ($action === 'delete_client_explicit' && !\PluginGlurbackup\Config::isDeleteClientEnabled()) {
    Session::addMessageAfterRedirect(__('Il pulsante di eliminazione client è disabilitato nella configurazione del plugin.', 'glurbackup'), false, ERROR);
    $redirect();
}

$computer = new Computer();
if (!$computer->getFromDB($computerId)) {
    Session::addMessageAfterRedirect(__('Computer non trovato.', 'glurbackup'), false, ERROR);
    $redirect();
}
$computerName = trim((string) ($computer->fields['name'] ?? ''));
$computerIp   = null;

$getLinkedServer = static function (int $cid): ?\PluginGlurbackup\Server {
    $link = \PluginGlurbackup\ComputerServer::getForComputer($cid);
    if (!$link) {
        return null;
    }
    $server = new \PluginGlurbackup\Server();
    if (!$server->getFromDB((int) $link['plugin_glurbackup_servers_id'])) {
        return null;
    }
    return $server;
};

try {
    switch ($action) {
        case 'link_server':
            $serverId       = (int) ($_POST['plugin_glurbackup_servers_id'] ?? 0);
            $locationId     = (int) ($computer->fields['locations_id'] ?? 0);
            $rootLocationId = \PluginGlurbackup\LocationResolver::getRootLocationId($locationId);
            if ($serverId <= 0) {
                Session::addMessageAfterRedirect(__('Seleziona un server URBackup.', 'glurbackup'), false, WARNING);
                break;
            }
            $server = new \PluginGlurbackup\Server();
            if (!$server->getFromDB($serverId)) {
                Session::addMessageAfterRedirect(__('Server URBackup non trovato.', 'glurbackup'), false, ERROR);
                break;
            }
            if ((int) ($server->fields['locations_id'] ?? 0) !== $rootLocationId) {
                Session::addMessageAfterRedirect(__('Il server selezionato non appartiene alla root location del computer.', 'glurbackup'), false, ERROR);
                break;
            }
            $linked = \PluginGlurbackup\ComputerServer::linkComputerToServer($computerId, $serverId);
            if (!$linked) {
                Session::addMessageAfterRedirect(__('Collegamento del server al computer nel plugin non riuscito.', 'glurbackup'), false, ERROR);
                break;
            }
            Session::addMessageAfterRedirect(__('Server collegato correttamente nel database del plugin.', 'glurbackup'), false, INFO);
            break;

        case 'save_default_dirs':
        case 'save_internet_mode':
        case 'create_client_explicit':
        case 'delete_client_explicit':
        case 'backup_incr_file':
        case 'backup_full_file':
        case 'backup_incr_image':
        case 'backup_full_image':
            $server = $getLinkedServer($computerId);
            if (!$server) {
                Session::addMessageAfterRedirect(__('nessun server collegato', 'glurbackup'), false, WARNING);
                break;
            }
            $api = \PluginGlurbackup\UrbackupApiFactory::createFromServer($server->fields);
            if ($action === 'save_default_dirs') {
                $result = $api->updateDefaultDirs($server->fields, $computerName, trim((string) ($_POST['glurbackup_default_dirs'] ?? '')), $computerIp);
                Session::addMessageAfterRedirect((string) ($result['message'] ?? __('Salvataggio delle directory di default non riuscito.', 'glurbackup')), false, ($result['ok'] ?? false) ? INFO : ERROR);
                break;
            }
            if ($action === 'save_internet_mode') {
                $enabled = ((string) ($_POST['glurbackup_internet_mode_enabled'] ?? '0') === '1');
                $result = $api->saveInternetMode($server->fields, $computerName, $computerIp, $enabled);
                Session::addMessageAfterRedirect((string) ($result['message'] ?? __('Salvataggio internet mode non riuscito.', 'glurbackup')), false, ($result['ok'] ?? false) ? INFO : ERROR);
                break;
            }
            if ($action === 'create_client_explicit') {
                $result = $api->createClientExplicit($server->fields, $computerName);
                Session::addMessageAfterRedirect((string) ($result['message'] ?? __('Creazione client non riuscita.', 'glurbackup')), false, ($result['ok'] ?? false) ? INFO : ERROR);
                break;
            }
            if ($action === 'delete_client_explicit') {
                $result = $api->deleteClientExplicit($server->fields, $computerName);
                Session::addMessageAfterRedirect((string) ($result['message'] ?? __('Eliminazione client non riuscita.', 'glurbackup')), false, ($result['ok'] ?? false) ? INFO : ERROR);
                break;
            }
            $result = $api->runClientAction($server->fields, $computerName, $action);
            Session::addMessageAfterRedirect((string) ($result['message'] ?? __('Azione URBackup non riuscita.', 'glurbackup')), false, ($result['ok'] ?? false) ? INFO : ERROR);
            break;

        case 'unlink_server':
            if (\PluginGlurbackup\ComputerServer::unlinkComputer($computerId)) {
                Session::addMessageAfterRedirect(__('Client disconnesso dal server URBackup nel database GLPI.', 'glurbackup'), false, INFO);
            } else {
                Session::addMessageAfterRedirect(__('Disconnessione del client dal server URBackup non riuscita.', 'glurbackup'), false, ERROR);
            }
            break;
    }
} catch (Throwable $e) {
    Session::addMessageAfterRedirect(__('Errore durante l\'esecuzione dell\'azione URBackup.', 'glurbackup') . ' ' . $e->getMessage(), false, ERROR);
}

$redirect();
