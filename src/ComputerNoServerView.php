<?php

namespace PluginGlurbackup;

use Computer;
use Session;

final class ComputerNoServerView
{
    public function render(Computer $computer, array $messages = []): string
    {
        $computerId     = (int) ($computer->fields['id'] ?? 0);
        $locationId     = (int) ($computer->fields['locations_id'] ?? 0);
        $rootLocationId = LocationResolver::getRootLocationId($locationId);
        $servers        = $this->getServersForRootLocation($rootLocationId);
        $hasServers     = count($servers) > 0;
        $canUpdate      = Server::canAdminUpdate();
        $actionUrl      = $this->getComputerActionUrl();

        ob_start();

        echo "<div class='card mb-3'>";
        echo "<div class='card-body'>";

        foreach ($messages as $message) {
            $type = $this->normalizeAlertType((string) ($message['type'] ?? 'info'));
            $text = (string) ($message['text'] ?? '');
            if ($text !== '') {
                echo "<div class='alert alert-{$type} mb-2'>" . Server::h($text) . "</div>";
            }
        }

        echo "<div class='alert alert-warning mb-3'>";
        echo Server::h(__('nessun server collegato', 'glurbackup'));
        echo "</div>";

        if (!$canUpdate) {
            echo "<div class='alert alert-info mb-0'>";
            echo Server::h(__('Il tuo profilo ha diritti di sola lettura. Non puoi collegare questo computer a un server URBackup.', 'glurbackup'));
            echo "</div>";
            echo "</div>";
            echo "</div>";

            return (string) ob_get_clean();
        }

        if (!$hasServers) {
            echo "<div class='alert alert-info mb-0'>";
            echo Server::h(__('No URBackup servers are available for the root location of this computer.', 'glurbackup'));
            echo "</div>";
            echo "</div>";
            echo "</div>";

            return (string) ob_get_clean();
        }

        echo '<form method="post" action="' . Server::h($actionUrl) . '">';
        echo "<input type='hidden' name='_glpi_csrf_token' value='" . Server::h(Session::getNewCSRFToken()) . "'>";
        echo "<input type='hidden' name='glurbackup_computer_id' value='" . $computerId . "'>";
        echo "<input type='hidden' name='_glurbackup_action' value='link_server'>";

        echo "<table class='tab_cadre_fixe' style='width:100%;'>";
        echo "<tr>";
        echo "<th colspan='2'>" . Server::h(__('Collega server URBackup', 'glurbackup')) . "</th>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td style='width:30%;'>" . Server::h(__('Server URBackup', 'glurbackup')) . "</td>";
        echo "<td>";
        echo "<select name='plugin_glurbackup_servers_id' class='form-select' required>";
        echo "<option value=''>" . Server::h(__('Select a server', 'glurbackup')) . "</option>";

        foreach ($servers as $server) {
            $serverId   = (int) ($server['id'] ?? 0);
            $serverName = (string) ($server['name'] ?? '');
            $serverIp   = (string) ($server['ip_address'] ?? '');
            $isDefault  = ((int) ($server['is_default'] ?? 0) === 1);

            $label = $serverName;
            if ($serverIp !== '') {
                $label .= ' [' . $serverIp . ']';
            }
            if ($isDefault) {
                $label .= ' *';
            }

            echo "<option value='" . $serverId . "'>" . Server::h($label) . "</option>";
        }

        echo "</select>";
        echo "<div class='text-muted mt-2'>" . Server::h(__('* = server predefinito', 'glurbackup')) . "</div>";
        echo "</td>";
        echo "</tr>";

        echo "<tr>";
        echo "<td colspan='2' class='center'>";
        echo "<button type='submit' class='btn btn-primary' ";
        echo "onclick='return confirm(\"" . addslashes(__('Confermi il collegamento del server selezionato?', 'glurbackup')) . "\")'>";
        echo Server::h(__('Collega a server', 'glurbackup'));
        echo "</button>";
        echo "</td>";
        echo "</tr>";

        echo "</table>";
        echo "</form>";

        echo "</div>";
        echo "</div>";

        return (string) ob_get_clean();
    }

    private function getServersForRootLocation(int $rootLocationId): array
    {
        global $DB;

        $rows  = [];
        $table = Server::getTable();

        if (!$DB->tableExists($table)) {
            return [];
        }

        $iterator = $DB->request([
            'FROM'  => $table,
            'WHERE' => [
                'locations_id' => $rootLocationId,
            ],
            'ORDER' => [
                'is_default DESC',
                'name ASC',
            ],
        ]);

        foreach ($iterator as $row) {
            $rows[] = [
                'id'         => (int) ($row['id'] ?? 0),
                'name'       => (string) ($row['name'] ?? ''),
                'ip_address' => (string) ($row['ip_address'] ?? ''),
                'is_default' => (int) ($row['is_default'] ?? 0),
            ];
        }

        return $rows;
    }

    private function getComputerActionUrl(): string
    {
        global $CFG_GLPI;

        return $CFG_GLPI['root_doc'] . '/plugins/glurbackup/front/computer.action.php';
    }

    private function normalizeAlertType(string $type): string
    {
        return match ($type) {
            'success' => 'success',
            'danger'  => 'danger',
            'warning' => 'warning',
            default   => 'info',
        };
    }
}

