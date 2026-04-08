<?php

namespace PluginGlurbackup;

use CommonDBTM;
use Computer;

final class ComputerServer extends CommonDBTM
{
    public static $rightname = 'plugin_glurbackup';

    public static function getTypeName($nb = 0): string
    {
        return __('Glurbackup computer link', 'glurbackup');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_glurbackup_computers_servers';
    }

    /**
     * Visualizzazione custom per le colonne "specific" della lista Computer.
     *
     * Colonna 6500:
     * - field = plugin_glurbackup_servers_id
     * - mostra il NOME del server come link cliccabile verso il form server
     *
     * Colonna 6501:
     * - field = computers_id
     * - mostra l'IP del server collegato al computer
     */
    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        switch ((string) $field) {
            case 'plugin_glurbackup_servers_id':
                $serverId = 0;

                if (is_array($values) && isset($values[$field]) && is_numeric($values[$field])) {
                    $serverId = (int) $values[$field];
                } elseif (is_numeric($values)) {
                    $serverId = (int) $values;
                }

                if (
                    $serverId <= 0
                    && isset($options['raw_data']['plugin_glurbackup_servers_id'])
                    && is_numeric($options['raw_data']['plugin_glurbackup_servers_id'])
                ) {
                    $serverId = (int) $options['raw_data']['plugin_glurbackup_servers_id'];
                }

                if ($serverId <= 0) {
                    return '';
                }

                $server = self::getServerById($serverId);
                if (!$server) {
                    return '';
                }

                $name = (string) ($server['name'] ?? '');
                if ($name === '') {
                    return '';
                }

                $url = self::getServerFormUrl($serverId);

                return "<a href='" . self::h($url) . "'>" . self::h($name) . "</a>";

            case 'computers_id':
                $computerId = 0;

                if (is_array($values) && isset($values[$field]) && is_numeric($values[$field])) {
                    $computerId = (int) $values[$field];
                } elseif (is_numeric($values)) {
                    $computerId = (int) $values;
                }

                if ($computerId <= 0 && isset($options['raw_data']['id']) && is_numeric($options['raw_data']['id'])) {
                    $computerId = (int) $options['raw_data']['id'];
                }

                if ($computerId <= 0) {
                    return '';
                }

                $server = self::getLinkedServerForComputer($computerId);
                return $server['ip_address'] ?? '';
        }

        return '';
    }

    public static function getForComputer(int $computerId): ?array
    {
        global $DB;

        $row = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['computers_id' => $computerId],
            'LIMIT' => 1,
        ])->current();

        return $row ?: null;
    }

    public static function getServerIdForComputer(int $computerId): int
    {
        $row = self::getForComputer($computerId);

        return (int) ($row['plugin_glurbackup_servers_id'] ?? 0);
    }

    public static function linkComputerToServer(int $computerId, int $serverId): bool
    {
        global $DB;

        if ($computerId <= 0 || $serverId <= 0) {
            return false;
        }

        $existing = self::getForComputer($computerId);
        $now      = date('Y-m-d H:i:s');

        if ($existing) {
            return (bool) $DB->update(self::getTable(), [
                'plugin_glurbackup_servers_id' => $serverId,
                'date_mod'                     => $now,
            ], [
                'id' => (int) $existing['id'],
            ]);
        }

        return (bool) $DB->insert(self::getTable(), [
            'computers_id'                  => $computerId,
            'plugin_glurbackup_servers_id' => $serverId,
            'date_mod'                     => $now,
            'date_creation'                => $now,
        ]);
    }

    public static function unlinkComputer(int $computerId): bool
    {
        global $DB;

        if ($computerId <= 0) {
            return false;
        }

        return (bool) $DB->delete(self::getTable(), [
            'computers_id' => $computerId,
        ]);
    }

    public static function getLinkedComputersForServer(int $serverId): array
    {
        global $DB;

        $rows = [];

        $iterator = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['plugin_glurbackup_servers_id' => $serverId],
            'ORDER' => ['computers_id ASC'],
        ]);

        foreach ($iterator as $link) {
            $computerId = (int) ($link['computers_id'] ?? 0);
            if ($computerId <= 0) {
                continue;
            }

            $computer = new Computer();
            if (!$computer->getFromDB($computerId)) {
                continue;
            }

            $rows[] = [
                'computers_id'      => $computerId,
                'name'              => (string) ($computer->fields['name'] ?? ''),
                'ip_address'        => '—',
                'client_version'    => '—',
                'last_file_backup'  => '—',
                'last_image_backup' => '—',
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            return strcmp((string) $a['name'], (string) $b['name']);
        });

        return $rows;
    }

    /**
     * Recupera i dati del server per ID.
     */
    private static function getServerById(int $serverId): ?array
    {
        global $DB;

        if ($serverId <= 0) {
            return null;
        }

        $serverTable = 'glpi_plugin_glurbackup_servers';

        if (!$DB->tableExists($serverTable)) {
            return null;
        }

        $server = $DB->request([
            'FROM'  => $serverTable,
            'WHERE' => ['id' => $serverId],
            'LIMIT' => 1,
        ])->current();

        if (!$server) {
            return null;
        }

        return [
            'id'         => (int) ($server['id'] ?? 0),
            'name'       => (string) ($server['name'] ?? ''),
            'ip_address' => (string) ($server['ip_address'] ?? ''),
        ];
    }

    /**
     * Recupera il server collegato a un computer.
     */
    private static function getLinkedServerForComputer(int $computerId): ?array
    {
        $serverId = self::getServerIdForComputer($computerId);
        if ($serverId <= 0) {
            return null;
        }

        return self::getServerById($serverId);
    }

    private static function getServerFormUrl(int $serverId): string
    {
        global $CFG_GLPI;

        return $CFG_GLPI['root_doc'] . '/plugins/glurbackup/front/server.form.php?id=' . $serverId;
    }

    private static function h(?string $value): string
    {
        $value = $value ?? '';
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
