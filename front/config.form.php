<?php

include('../../../inc/includes.php');

require_once dirname(__DIR__) . '/src/Server.php';
require_once dirname(__DIR__) . '/src/Config.php';

Session::checkCentralAccess();
\PluginGlurbackup\Config::denyIfNoAdminUpdate();

$config = new \PluginGlurbackup\Config();
$id     = \PluginGlurbackup\Config::getSingletonId();

$input = [
    'id'                          => $id,
    'enable_delete_client_button' => isset($_POST['enable_delete_client_button']) ? 1 : 0,
];

$ok = false;

if ($config->getFromDB($id)) {
    $ok = (bool) $config->update($input);
} else {
    $input['api_base_path']      = '/x';
    $input['api_timeout']        = 30;
    $input['log_retention_days'] = 90;
    $input['debug_mode']         = 0;
    $ok = (bool) $config->add($input);
}

if ($ok) {
    Session::addMessageAfterRedirect(
        __('Configurazione salvata correttamente.', 'glurbackup'),
        false,
        INFO
    );
} else {
    Session::addMessageAfterRedirect(
        __('Errore durante il salvataggio della configurazione.', 'glurbackup'),
        false,
        ERROR
    );
}

Html::redirect($CFG_GLPI['root_doc'] . '/plugins/glurbackup/front/config.php');
