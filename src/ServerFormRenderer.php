<?php

namespace PluginGlurbackup;

use Dropdown;
use Html;
use Session;

require_once __DIR__ . '/ComputerServer.php';
require_once __DIR__ . '/ServerUrbackupClientProvider.php';

final class ServerFormRenderer
{
    public function render(Server $server, array $options = []): bool
    {
        global $_SESSION;

        $server->showFormHeader($options);

        echo Html::hidden('id', [
            'value' => (int) ($server->fields['id'] ?? 0),
        ]);

        echo Html::hidden('_glpi_csrf_token', [
            'value' => Session::getNewCSRFToken(),
        ]);

        $entity      = (int) ($server->fields['entities_id'] ?? ($_SESSION['glpiactive_entity'] ?? 0));
        $location    = (int) ($server->fields['locations_id'] ?? 0);
        $protocol    = (string) ($server->fields['protocol'] ?? 'http');
        $version     = UrbackupApiFactory::normalize((string) ($server->fields['version'] ?? '2.5'));
        $serverUiUrl = (new ServerUrbackupClientProvider())->buildServerUiUrl($server->fields);

        echo "<tr class='tab_bg_1'>";
        echo '<td>' . Server::h(__('Name')) . '</td>';
        echo "<td><input type='text' class='form-control' name='name' value='" . Server::h((string) ($server->fields['name'] ?? '')) . "' required></td>";
        echo '<td>' . Server::h(__('IP address', 'glurbackup')) . '</td>';
        echo "<td><input type='text' class='form-control' name='ip_address' value='" . Server::h((string) ($server->fields['ip_address'] ?? '')) . "' required></td>";
        echo '</tr>';

        echo "<tr class='tab_bg_1'>";
        echo '<td>' . Server::h(__('Port')) . '</td>';
        echo "<td><input type='number' min='1' max='65535' class='form-control' name='port' value='" . (int) ($server->fields['port'] ?? 55414) . "'></td>";
        echo '<td>' . Server::h(__('Protocol', 'glurbackup')) . '</td>';
        echo "<td><select name='protocol' class='form-select'>";
        foreach (['http', 'https'] as $p) {
            echo "<option value='" . Server::h($p) . "'" . ($protocol === $p ? ' selected' : '') . '>' . strtoupper($p) . '</option>';
        }
        echo '</select></td>';
        echo '</tr>';

        echo "<tr class='tab_bg_1'>";
        echo '<td>' . Server::h(__('URBackup server version', 'glurbackup')) . '</td>';
        echo '<td>';
        echo "<select name='version' class='form-select'>";
        foreach (UrbackupApiFactory::getChoices() as $key => $label) {
            $selected = ($version === $key) ? ' selected' : '';
            echo "<option value='" . Server::h($key) . "'" . $selected . '>' . Server::h($label) . '</option>';
        }
        echo '</select>';
        echo '</td>';
        echo '<td>' . Server::h(__('Username')) . '</td>';
        echo "<td><input type='text' class='form-control' name='username' value='" . Server::h((string) ($server->fields['username'] ?? '')) . "'></td>";
        echo '</tr>';

        echo "<tr class='tab_bg_1'>";
        echo '<td>' . Server::h(__('Password')) . '</td>';
        echo "<td><input type='password' class='form-control' name='password' value='" . Server::h((string) ($server->fields['password'] ?? '')) . "'></td>";
        echo '<td>' . Server::h(__('Entity')) . '</td>';
        echo '<td>';
        Dropdown::show('Entity', [
            'name'  => 'entities_id',
            'value' => $entity,
        ]);
        echo '</td>';
        echo '</tr>';

        echo "<tr class='tab_bg_1'>";
        echo '<td>' . Server::h(__('Location')) . '</td>';
        echo '<td>';
        Dropdown::show('Location', [
            'name'  => 'locations_id',
            'value' => $location,
        ]);
        echo '</td>';
        echo '<td>' . Server::h(__('Options')) . '</td>';
        echo '<td>';
        echo "<label class='me-4'><input type='checkbox' name='ignore_ssl' value='1'" . ((int) ($server->fields['ignore_ssl'] ?? 0) === 1 ? ' checked' : '') . '> ' . Server::h(__('Ignore SSL', 'glurbackup')) . '</label>';
        echo "<label class='me-4'><input type='checkbox' name='is_default' value='1'" . ((int) ($server->fields['is_default'] ?? 0) === 1 ? ' checked' : '') . '> ' . Server::h(__('Default', 'glurbackup')) . '</label>';
        echo "<label><input type='checkbox' name='is_recursive' value='1'" . ((int) ($server->fields['is_recursive'] ?? 0) === 1 ? ' checked' : '') . '> ' . Server::h(__('Recursive on child entities', 'glurbackup')) . '</label>';
        echo '</td>';
        echo '</tr>';

        echo "<tr class='tab_bg_1'>";
        echo '<td>' . Server::h(__('Comments')) . '</td>';
        echo "<td colspan='3'><textarea name='comment' class='form-control' rows='4'>" . Server::h((string) ($server->fields['comment'] ?? '')) . '</textarea></td>';
        echo '</tr>';

        if ((int) ($server->fields['id'] ?? 0) > 0) {
            echo "<tr class='tab_bg_1'>";
            echo '<td>' . Server::h(__('URBackup web interface', 'glurbackup')) . '</td>';
            echo "<td colspan='3'>";

            if ($serverUiUrl !== '') {
                echo "<a class='btn btn-outline-primary' href='" . Server::h($serverUiUrl) . "' target='_blank' rel='noopener noreferrer'>";
                echo Server::h(__('Open URBackup interface', 'glurbackup'));
                echo '</a>';
                echo "<span class='ms-3 text-muted'>" . Server::h($serverUiUrl) . '</span>';
            } else {
                echo "<span class='text-muted'>" . Server::h(__('Unable to compose the server interface URL. Check protocol, IP address and port.', 'glurbackup')) . '</span>';
            }

            echo '</td>';
            echo '</tr>';
        }

        echo '<tr>';
        echo "<td colspan='4' class='center'>";

        if ((bool) ($options['canedit'] ?? false)) {
            if ((int) ($server->fields['id'] ?? 0) > 0) {
                echo "<button type='submit' name='update' value='1' class='btn btn-primary me-2'>";
                echo Server::h(__('Save'));
                echo '</button>';

                echo "<button type='submit' name='delete' value='1' class='btn btn-danger' ";
                echo "onclick='return confirm(\"" . addslashes(__('Are you sure?')) . "\")'>";
                echo Server::h(__('Delete'));
                echo '</button>';
            } else {
                echo "<button type='submit' name='add' value='1' class='btn btn-primary'>";
                echo Server::h(__('Add'));
                echo '</button>';
            }
        }

        echo '</td>';
        echo '</tr>';

        echo '</table>';

        if ((int) ($server->fields['id'] ?? 0) > 0) {
            $serverId = (int) $server->fields['id'];
            $this->showLinkedComputersSection($serverId);
            $this->showUnlinkedUrbackupClientsSection($server->fields, $serverId);
        }

        echo '</form>';

        return true;
    }

    private function showLinkedComputersSection(int $serverId): void
    {
        global $CFG_GLPI;

        $rows = ComputerServer::getLinkedComputersForServer($serverId);

        echo "<div class='spaced'>";
        echo "<table class='tab_cadre_fixe'>";
        echo '<tr><th colspan="5">' . Server::h(__('Linked computers', 'glurbackup')) . '</th></tr>';
        echo '<tr>';
        echo '<th>' . Server::h(__('Computer')) . '</th>';
        echo '<th>' . Server::h(__('IP address', 'glurbackup')) . '</th>';
        echo '<th>' . Server::h(__('URBackup client version', 'glurbackup')) . '</th>';
        echo '<th>' . Server::h(__('Last file backup', 'glurbackup')) . '</th>';
        echo '<th>' . Server::h(__('Last image backup', 'glurbackup')) . '</th>';
        echo '</tr>';

        if ($rows === []) {
            echo "<tr class='tab_bg_1'>";
            echo '<td colspan="5" class="center">' . Server::h(__('No computers linked to this server.', 'glurbackup')) . '</td>';
            echo '</tr>';
        } else {
            foreach ($rows as $row) {
                $computerId   = (int) ($row['computers_id'] ?? 0);
                $computerName = (string) ($row['name'] ?? '');

                if ($computerId > 0 && $computerName !== '') {
                    $computerUrl  = $CFG_GLPI['root_doc'] . '/front/computer.form.php?id=' . $computerId;
                    $computerLink = "<a href='" . Server::h($computerUrl) . "'>" . Server::h($computerName) . '</a>';
                } else {
                    $computerLink = Server::h($computerName);
                }

                echo "<tr class='tab_bg_1'>";
                echo '<td>' . $computerLink . '</td>';
                echo '<td>' . Server::h((string) ($row['ip_address'] ?? '—')) . '</td>';
                echo '<td>' . Server::h((string) ($row['client_version'] ?? '—')) . '</td>';
                echo '<td>' . Server::h((string) ($row['last_file_backup'] ?? '—')) . '</td>';
                echo '<td>' . Server::h((string) ($row['last_image_backup'] ?? '—')) . '</td>';
                echo '</tr>';
            }
        }

        echo '</table>';
        echo '</div>';
    }

    private function showUnlinkedUrbackupClientsSection(array $serverFields, int $serverId): void
    {
        $provider = new ServerUrbackupClientProvider();
        $result   = $provider->getUnlinkedClientsForServer($serverFields, $serverId);

        echo "<div class='spaced'>";
        echo "<table class='tab_cadre_fixe'>";
        echo '<tr><th colspan="6">' . Server::h(__('URBackup clients not linked to GLPI computers', 'glurbackup')) . '</th></tr>';
        echo '<tr>';
        echo '<th>' . Server::h(__('Client name', 'glurbackup')) . '</th>';
        echo '<th>' . Server::h(__('IP address', 'glurbackup')) . '</th>';
        echo '<th>' . Server::h(__('URBackup client version', 'glurbackup')) . '</th>';
        echo '<th>' . Server::h(__('Status', 'glurbackup')) . '</th>';
        echo '<th>' . Server::h(__('Last file backup', 'glurbackup')) . '</th>';
        echo '<th>' . Server::h(__('Last image backup', 'glurbackup')) . '</th>';
        echo '</tr>';

        if (!($result['ok'] ?? false)) {
            echo "<tr class='tab_bg_1'>";
            echo '<td colspan="6" class="center">' . Server::h((string) ($result['message'] ?? __('Unable to load URBackup clients.', 'glurbackup'))) . '</td>';
            echo '</tr>';
            echo '</table>';
            echo '</div>';
            return;
        }

        $rows = $result['clients'] ?? [];

        if (!is_array($rows) || $rows === []) {
            echo "<tr class='tab_bg_1'>";
            echo '<td colspan="6" class="center">' . Server::h(__('All clients present on this URBackup server are already linked in GLPI.', 'glurbackup')) . '</td>';
            echo '</tr>';
            echo '</table>';
            echo '</div>';
            return;
        }

        foreach ($rows as $row) {
            echo "<tr class='tab_bg_1'>";
            echo '<td>' . Server::h((string) ($row['name'] ?? '—')) . '</td>';
            echo '<td>' . Server::h((string) ($row['ip_address'] ?? '—')) . '</td>';
            echo '<td>' . Server::h((string) ($row['client_version'] ?? '—')) . '</td>';
            echo '<td>' . Server::h((string) ($row['online_offline'] ?? '—')) . '</td>';
            echo '<td>' . Server::h((string) ($row['last_file_backup'] ?? '—')) . '</td>';
            echo '<td>' . Server::h((string) ($row['last_image_backup'] ?? '—')) . '</td>';
            echo '</tr>';
        }

        echo '</table>';
        echo '</div>';
    }
}
