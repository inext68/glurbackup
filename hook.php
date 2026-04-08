<?php

use PluginGlurbackup\Install;
use PluginGlurbackup\Uninstall;
use PluginGlurbackup\ComputerServer;

/**
 * Install hook
 */
function plugin_glurbackup_install(): bool
{
    return (new Install())->doInstall();
}

/**
 * Uninstall hook
 */
function plugin_glurbackup_uninstall(): bool
{
    return (new Uninstall())->doUninstall();
}

/**
 * Massive actions for Computer objects
 * GLPI 11 strict mode
 */
function plugin_glurbackup_MassiveActions($type): array
{
    $actions = [];

    $canwrite = Session::haveRight('plugin_glurbackup', UPDATE)
        || Session::haveRight('config', UPDATE);

    if (!$canwrite) {
        return $actions;
    }

    if ($type === 'Computer') {
        $myclass = 'PluginGlurbackupMassiveActionComputer';

        // ✅ Link computer to URBackup server
        $actions[$myclass . MassiveAction::CLASS_ACTION_SEPARATOR . 'link_server']
            = __('URBACKUP: link to server', 'glurbackup');

        // ✅ Unlink computer from URBackup server (GLPI database only)
        $actions[$myclass . MassiveAction::CLASS_ACTION_SEPARATOR . 'unlink_server']
            = __('URBACKUP: Unlink from server', 'glurbackup');
    }

    return $actions;
}

/**
 * Search options for Computer list
 */
function plugin_glurbackup_build_computer_search_options(): array
{
    $relationTable = 'glpi_plugin_glurbackup_computers_servers';

    return [
        6500 => [
            'table'         => $relationTable,
            'field'         => 'plugin_glurbackup_servers_id',
            'name'          => __('Glurbackup server', 'glurbackup'),
            'datatype'      => 'specific',
            'itemtype'      => ComputerServer::class,
            'massiveaction' => false,
            'forcegroupby'  => true,
            'joinparams'    => [
                'jointype' => 'child',
            ],
        ],
        6501 => [
            'table'         => $relationTable,
            'field'         => 'computers_id',
            'name'          => __('Glurbackup server IP', 'glurbackup'),
            'datatype'      => 'specific',
            'itemtype'      => ComputerServer::class,
            'massiveaction' => false,
            'forcegroupby'  => true,
            'joinparams'    => [
                'jointype' => 'child',
            ],
        ],
    ];
}

/**
 * Legacy search options hook
 */
function plugin_glurbackup_getAddSearchOptions($itemtype): array
{
    if ($itemtype !== 'Computer') {
        return [];
    }

    return plugin_glurbackup_build_computer_search_options();
}

/**
 * New search options hook (GLPI 11)
 */
function plugin_glurbackup_getAddSearchOptionsNew(string $itemtype): array
{
    if ($itemtype !== 'Computer') {
        return [];
    }

    $options = [];
    foreach (plugin_glurbackup_build_computer_search_options() as $id => $opt) {
        $opt['id'] = (string) $id;
        $options[] = $opt;
    }

    return $options;
}
