<?php

namespace PluginGlurbackup;

use Computer;

final class ServerController
{
    public function renderComputerTab(Computer $computer): string
    {
        $computerId   = (int) ($computer->fields['id'] ?? 0);
        $computerName = (string) ($computer->fields['name'] ?? '');
        $computerIp   = $this->getPrimaryComputerIp($computerId);

        $link   = ComputerServer::getForComputer($computerId);
        $server = null;

        if ($link) {
            $server = new Server();
            if (!$server->getFromDB((int) $link['plugin_glurbackup_servers_id'])) {
                $server = null;
            }
        }

        if (!$server instanceof Server) {
            return (new ComputerNoServerView())->render($computer, []);
        }

        $apiResult = [
            'ok'                       => false,
            'found'                    => false,
            'message'                  => '',
            'client_version'           => '—',
            'online_offline'           => '—',
            'last_file_backup'         => '—',
            'last_image_backup'        => '—',
            'last_file_backup_result'  => '—',
            'last_image_backup_result' => '—',
            'current_activities'       => '—',
            'default_dirs'             => '',
            'recent_backups'           => [],
            'recent_logs'              => [],
            'connection'               => [],
            'matched_by'               => '',
        ];

        $api = UrbackupApiFactory::createFromServer($server->fields);
        $apiResult = $api->getComputerOverview(
            $server->fields,
            $computerName,
            $computerIp
        );

        return (new ComputerLinkedTabsView())->render(
            $computer,
            $server->fields,
            $apiResult,
            []
        );
    }

    private function getPrimaryComputerIp(int $computerId): ?string
    {
        global $DB;

        if ($computerId <= 0) {
            return null;
        }

        if (!$DB->tableExists('glpi_ipaddresses')) {
            return null;
        }

        $iterator = $DB->request([
            'FROM'  => 'glpi_ipaddresses',
            'WHERE' => [
                'mainitems_id' => $computerId,
                'mainitemtype' => 'Computer',
            ],
            'ORDER' => ['id ASC'],
        ]);

        foreach ($iterator as $row) {
            $ip = trim((string) ($row['name'] ?? ''));
            if ($ip !== '') {
                return $ip;
            }
        }

        return null;
    }
}
