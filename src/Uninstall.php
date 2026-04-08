<?php

namespace PluginGlurbackup;

use ProfileRight;

final class Uninstall
{
    /**
     * Right name del plugin, definito qui localmente
     * per evitare dipendenze dalla classe Plugin durante la disinstallazione.
     */
    private const PLUGIN_RIGHT = 'plugin_glurbackup';

    public function doUninstall(): bool
    {
        global $DB;

        foreach ([
            'glpi_plugin_glurbackup_computers_servers',
            'glpi_plugin_glurbackup_servers',
            'glpi_pluginglurbackups_serverversions',
            'glpi_plugin_glurbackup_serverversions',
            'glpi_plugin_glurbackup_logs',
            'glpi_plugin_glurbackup_configs',
        ] as $table) {
            if ($DB->tableExists($table)) {
                $DB->doQuery('DROP TABLE IF EXISTS `' . $table . '`');
            }
        }

        if ($DB->tableExists('glpi_crontasks')) {
            $DB->delete('glpi_crontasks', [
                'itemtype' => Log::class,
                'name'     => 'purge',
            ]);
        }

        ProfileRight::deleteProfileRights([self::PLUGIN_RIGHT]);

        return true;
    }
}
