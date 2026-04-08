<?php

namespace PluginGlurbackup;

use Computer;
use Session;

final class ComputerLinkedTabsView
{
    public function render(Computer $computer, array $server, array $apiResult, array $messages = []): string
    {
        $computerId    = (int) ($computer->fields['id'] ?? 0);
        $tabPrefix     = 'glurbackup_tabs_' . $computerId;
        $recentLogs    = $apiResult['recent_logs'] ?? [];
        $recentBackup  = $apiResult['recent_backups'] ?? [];
        $defaultDirs   = (string) ($apiResult['default_dirs'] ?? '');
        $canUpdate     = Server::canAdminUpdate();
        $actionUrl     = $this->getComputerActionUrl();
        $internetKey   = (string) ($apiResult['internet_auth_key'] ?? '');
        $internetMode  = (bool) ($apiResult['internet_mode_enabled'] ?? false);
        $internetLabel = $internetMode ? __('Enabled', 'glurbackup') : __('Disabled', 'glurbackup');

        ob_start();

        foreach ($messages as $message) {
            $type = $this->normalizeAlertType((string) ($message['type'] ?? 'info'));
            $text = (string) ($message['text'] ?? '');
            if ($text !== '') {
                echo "<div class='alert alert-{$type} mb-3'>" . Server::h($text) . '</div>';
            }
        }

        if ((string) ($apiResult['message'] ?? '') !== '') {
            $message = (string) ($apiResult['message'] ?? '');
            $type    = 'info';

            if ($message === 'Errore comunicazione col server') {
                $type = 'danger';
            } elseif ($message === 'non presente sul server urbackup') {
                $type = 'warning';
            }

            echo "<div class='alert alert-{$type} mb-3'>" . Server::h($message) . '</div>';
        }

        echo "<div class='card mb-3'><div class='card-body'>";
        echo "<ul class='nav nav-tabs mb-3' id='" . Server::h($tabPrefix) . "_nav' role='tablist'>";
        echo "<li class='nav-item' role='presentation'><button class='nav-link active' type='button' data-glurbackup-tab='" . Server::h($tabPrefix) . "_stato'>" . Server::h(__('Stato', 'glurbackup')) . '</button></li>';
        if ($canUpdate) {
            echo "<li class='nav-item' role='presentation'><button class='nav-link' type='button' data-glurbackup-tab='" . Server::h($tabPrefix) . "_azioni'>" . Server::h(__('Azioni', 'glurbackup')) . '</button></li>';
        }
        echo "<li class='nav-item' role='presentation'><button class='nav-link' type='button' data-glurbackup-tab='" . Server::h($tabPrefix) . "_info'>" . Server::h(__('Info/Result', 'glurbackup')) . '</button></li>';
        echo '</ul><div class="tab-content">';

        echo "<div class='glurbackup-tab-pane' id='" . Server::h($tabPrefix) . "_stato' style='display:block;'>";
        echo "<div class='card mb-3'><div class='card-header'><strong>" . Server::h(__('URBackup linkage', 'glurbackup')) . "</strong></div><div class='card-body'>";
        echo '<p><strong>' . Server::h(__('Linked server', 'glurbackup')) . ':</strong> ' . Server::h((string) ($server['name'] ?? '—')) . '</p>';
        echo '<p><strong>' . Server::h(__('Server IP', 'glurbackup')) . ':</strong> ' . Server::h((string) ($server['ip_address'] ?? '—')) . '</p>';
        echo '<p><strong>' . Server::h(__('Server version', 'glurbackup')) . ':</strong> ' . Server::h((string) ($server['version'] ?? '—')) . '</p>';
        echo '<p><strong>' . Server::h(__('Matching strategy', 'glurbackup')) . ':</strong> ' . Server::h(__('Name first, then IP', 'glurbackup')) . '</p>';
        echo '<p><strong>' . Server::h(__('Computer name used for match', 'glurbackup')) . ':</strong> ' . Server::h((string) ($computer->fields['name'] ?? '—')) . '</p>';
        echo '<p><strong>' . Server::h(__('Matched by', 'glurbackup')) . ':</strong> ' . Server::h((string) ($apiResult['matched_by'] ?? '—')) . '</p>';
        echo '</div></div>';

        echo "<div class='card mb-3'><div class='card-header'><strong>" . Server::h(__('URBackup client status', 'glurbackup')) . "</strong></div><div class='card-body'>";
        echo "<table class='tab_cadre_fixe' style='width:100%; table-layout:fixed;'><colgroup><col style='width:35%;'><col style='width:65%;'></colgroup>";
        echo '<tr><th>' . Server::h(__('Field')) . '</th><th>' . Server::h(__('Value')) . '</th></tr>';
        $rows = [
            __('Client version', 'glurbackup')           => (string) ($apiResult['client_version'] ?? '—'),
            __('Online / Offline', 'glurbackup')         => (string) ($apiResult['online_offline'] ?? '—'),
            __('Last file backup', 'glurbackup')         => (string) ($apiResult['last_file_backup'] ?? '—'),
            __('Last image backup', 'glurbackup')        => (string) ($apiResult['last_image_backup'] ?? '—'),
            __('Last file backup result', 'glurbackup')  => (string) ($apiResult['last_file_backup_result'] ?? '—'),
            __('Last image backup result', 'glurbackup') => (string) ($apiResult['last_image_backup_result'] ?? '—'),
            __('Current activities', 'glurbackup')       => (string) ($apiResult['current_activities'] ?? '—'),
            __('Internet mode', 'glurbackup')            => $internetLabel,
        ];
        foreach ($rows as $label => $value) {
            echo "<tr class='tab_bg_1'><td style='vertical-align:top; white-space:normal; word-break:break-word;'>" . Server::h($label) . "</td><td style='vertical-align:top; white-space:normal; word-break:break-word;'>" . Server::h($value) . '</td></tr>';
        }
        echo "<tr class='tab_bg_1'><td style='vertical-align:top; white-space:normal; word-break:break-word;'>" . Server::h(__('Internet auth key', 'glurbackup')) . "</td><td style='vertical-align:top; white-space:normal; word-break:break-word;'>" . $this->renderSecretField($tabPrefix . '_internet_auth_key', $internetKey) . '</td></tr>';
        echo '</table></div></div></div>';

        if ($canUpdate) {
            echo "<div class='glurbackup-tab-pane' id='" . Server::h($tabPrefix) . "_azioni' style='display:none;'>";
            echo "<div class='card mb-3'><div class='card-header'><strong>" . Server::h(__('Internet mode', 'glurbackup')) . "</strong></div><div class='card-body'>";
            echo '<p class="mb-2"><strong>' . Server::h(__('Current status', 'glurbackup')) . ':</strong> ' . Server::h($internetLabel) . '</p>';
            echo '<form method="post" action="' . Server::h($actionUrl) . '">';
            echo "<input type='hidden' name='_glpi_csrf_token' value='" . Server::h(Session::getNewCSRFToken()) . "'><input type='hidden' name='glurbackup_computer_id' value='" . $computerId . "'><input type='hidden' name='_glurbackup_action' value='save_internet_mode'>";
            echo "<div class='mb-3'><label class='form-label' for='glurbackup_internet_mode_enabled'>" . Server::h(__('Enable internet mode', 'glurbackup')) . "</label><select id='glurbackup_internet_mode_enabled' name='glurbackup_internet_mode_enabled' class='form-select'>";
            echo "<option value='1'" . ($internetMode ? ' selected' : '') . '>' . Server::h(__('Enabled', 'glurbackup')) . '</option>';
            echo "<option value='0'" . (!$internetMode ? ' selected' : '') . '>' . Server::h(__('Disabled', 'glurbackup')) . '</option>';
            echo "</select><div class='text-muted mt-2'>" . Server::h(__('Valore letto dal server URBackup (chiave client setting: internet_mode_enabled).', 'glurbackup')) . '</div></div>';
            echo "<button type='submit' class='btn btn-primary'>" . Server::h(__('Salva internet mode', 'glurbackup')) . '</button></form></div></div>';

            echo "<div class='card mb-3'><div class='card-header'><strong>" . Server::h(__('Directory di default per cui eseguire il backup', 'glurbackup')) . "</strong></div><div class='card-body'>";
            echo '<form method="post" action="' . Server::h($actionUrl) . '">';
            echo "<input type='hidden' name='_glpi_csrf_token' value='" . Server::h(Session::getNewCSRFToken()) . "'><input type='hidden' name='glurbackup_computer_id' value='" . $computerId . "'><input type='hidden' name='_glurbackup_action' value='save_default_dirs'>";
            echo "<div class='mb-3'><textarea name='glurbackup_default_dirs' class='form-control' rows='4'>" . Server::h($defaultDirs) . "</textarea><div class='text-muted mt-2'>" . Server::h(__('Valore letto dal server URBackup (chiave client setting: default_dirs).', 'glurbackup')) . '</div></div>';
            echo "<button type='submit' class='btn btn-primary'>" . Server::h(__('Salva directory di default', 'glurbackup')) . '</button></form></div></div>';

            echo "<div class='card mb-3'><div class='card-header'><strong>" . Server::h(__('URBackup actions', 'glurbackup')) . "</strong></div><div class='card-body'><div class='d-flex flex-wrap gap-2'>";
            $actions = [
                'backup_incr_file'  => __('Incremental file backup', 'glurbackup'),
                'backup_full_file'  => __('Full file backup', 'glurbackup'),
                'backup_incr_image' => __('Incremental image backup', 'glurbackup'),
                'backup_full_image' => __('Full image backup', 'glurbackup'),
            ];
            foreach ($actions as $actionKey => $actionLabel) {
                echo '<form method="post" action="' . Server::h($actionUrl) . '" style="display:inline-block; margin-right:8px; margin-bottom:8px;">';
                echo "<input type='hidden' name='_glpi_csrf_token' value='" . Server::h(Session::getNewCSRFToken()) . "'><input type='hidden' name='glurbackup_computer_id' value='" . $computerId . "'><input type='hidden' name='_glurbackup_action' value='" . Server::h($actionKey) . "'>";
                echo '<button type="submit" class="btn btn-primary" onclick="return confirm(\'' . addslashes(__("Confermi l\'esecuzione di questa azione URBackup?", 'glurbackup')) . '\')">' . Server::h($actionLabel) . '</button></form>';
            }
            echo '</div></div></div>';

            echo "<div class='card mb-3'><div class='card-header'><strong>" . Server::h(__('Gestione client URBackup', 'glurbackup')) . "</strong></div><div class='card-body'><div class='d-flex flex-wrap gap-2'>";
            echo '<form method="post" action="' . Server::h($actionUrl) . '" style="display:inline-block; margin-right:8px; margin-bottom:8px;">';
            echo "<input type='hidden' name='_glpi_csrf_token' value='" . Server::h(Session::getNewCSRFToken()) . "'><input type='hidden' name='glurbackup_computer_id' value='" . $computerId . "'><input type='hidden' name='_glurbackup_action' value='create_client_explicit'>";
            echo '<button type="submit" class="btn btn-secondary" onclick="return confirm(\'' . addslashes(__('Confermi la creazione/verifica del client su URBackup?', 'glurbackup')) . '\')">' . Server::h(__('Crea client in URBackup', 'glurbackup')) . '</button></form>';
            echo '<form method="post" action="' . Server::h($actionUrl) . '" style="display:inline-block; margin-right:8px; margin-bottom:8px;">';
            echo "<input type='hidden' name='_glpi_csrf_token' value='" . Server::h(Session::getNewCSRFToken()) . "'><input type='hidden' name='glurbackup_computer_id' value='" . $computerId . "'><input type='hidden' name='_glurbackup_action' value='unlink_server'>";
            echo '<button type="submit" class="btn btn-warning" onclick="return confirm(\'' . addslashes(__('Confermi la disconnessione del client dal server URBackup? (solo collegamento GLPI)', 'glurbackup')) . '\')">' . Server::h(__('Disconnetti client', 'glurbackup')) . '</button></form>';
            echo '</div></div></div></div>';
        }

        echo "<div class='glurbackup-tab-pane' id='" . Server::h($tabPrefix) . "_info' style='display:none;'>";
        echo "<div class='card mb-3'><div class='card-header'><strong>" . Server::h(__('Recent backups (max 7)', 'glurbackup')) . "</strong></div><div class='card-body'>";
        echo "<table class='tab_cadre_fixe' style='width:100%; table-layout:fixed;'><colgroup><col style='width:24%;'><col style='width:12%;'><col style='width:14%;'><col style='width:18%;'><col style='width:14%;'></colgroup>";
        echo '<tr><th>' . Server::h(__('Tempo di backup', 'glurbackup')) . '</th><th>' . Server::h(__('Backup ID', 'glurbackup')) . '</th><th>' . Server::h(__('Incrementale', 'glurbackup')) . '</th><th>' . Server::h(__('Dimensione', 'glurbackup')) . '</th><th>' . Server::h(__('Archiviato', 'glurbackup')) . '</th></tr>';
        if (!is_array($recentBackup) || count($recentBackup) === 0) {
            for ($i = 0; $i < 7; $i++) {
                echo "<tr class='tab_bg_1'><td>—</td><td>—</td><td>—</td><td>—</td><td>—</td></tr>";
            }
        } else {
            $displayed = 0;
            foreach ($recentBackup as $row) {
                echo "<tr class='tab_bg_1'><td>" . Server::h((string) ($row['date'] ?? '—')) . '</td><td>' . Server::h((string) ($row['backup_id'] ?? '—')) . '</td><td>' . Server::h((string) ($row['incremental'] ?? '—')) . '</td><td>' . Server::h((string) ($row['size'] ?? '—')) . '</td><td>' . Server::h((string) ($row['archived'] ?? '—')) . '</td></tr>';
                $displayed++;
                if ($displayed >= 7) {
                    break;
                }
            }
            while ($displayed < 7) {
                echo "<tr class='tab_bg_1'><td>—</td><td>—</td><td>—</td><td>—</td><td>—</td></tr>";
                $displayed++;
            }
        }
        echo '</table></div></div>';

        echo "<div class='card mb-3'><div class='card-header'><strong>" . Server::h(__('Ultimi log URBackup del PC', 'glurbackup')) . "</strong></div><div class='card-body'>";
        echo "<table class='tab_cadre_fixe' style='width:100%; table-layout:fixed;'><colgroup><col style='width:18%;'><col style='width:14%;'><col style='width:68%;'></colgroup>";
        echo '<tr><th>' . Server::h(__('Time')) . '</th><th>' . Server::h(__('Level')) . '</th><th>' . Server::h(__('Message')) . '</th></tr>';
        if (!is_array($recentLogs) || count($recentLogs) === 0) {
            for ($i = 0; $i < 10; $i++) {
                echo "<tr class='tab_bg_1'><td>—</td><td>—</td><td>—</td></tr>";
            }
        } else {
            $displayed = 0;
            foreach ($recentLogs as $row) {
                echo "<tr class='tab_bg_1'><td style='vertical-align:top; white-space:normal; word-break:break-word;'>" . Server::h((string) ($row['time'] ?? '—')) . "</td><td style='vertical-align:top; white-space:normal; word-break:break-word;'>" . Server::h((string) ($row['level'] ?? '—')) . "</td><td style='vertical-align:top; white-space:normal; word-break:break-word;'>" . Server::h((string) ($row['message'] ?? '—')) . '</td></tr>';
                $displayed++;
                if ($displayed >= 10) {
                    break;
                }
            }
            while ($displayed < 10) {
                echo "<tr class='tab_bg_1'><td>—</td><td>—</td><td>—</td></tr>";
                $displayed++;
            }
        }
        echo '</table></div></div></div></div></div>';

        echo '<script>(function(){const nav=document.getElementById("' . addslashes($tabPrefix) . '_nav");if(!nav){return;}const buttons=nav.querySelectorAll("[data-glurbackup-tab]");buttons.forEach(function(btn){btn.addEventListener("click",function(){const targetId=btn.getAttribute("data-glurbackup-tab");buttons.forEach(function(b){b.classList.remove("active");});document.querySelectorAll("[id^=\\"' . addslashes($tabPrefix) . '_\\"]").forEach(function(pane){if(pane.classList.contains("glurbackup-tab-pane")){pane.style.display="none";}});btn.classList.add("active");const target=document.getElementById(targetId);if(target){target.style.display="block";}});});})();(function(){document.querySelectorAll(".glurbackup-toggle-secret").forEach(function(button){button.addEventListener("click",function(){const targetId=button.getAttribute("data-target");const input=document.getElementById(targetId);if(!input){return;}const visible=input.getAttribute("type")==="text";input.setAttribute("type",visible?"password":"text");button.textContent=visible?"' . addslashes(__('Mostra', 'glurbackup')) . '":"' . addslashes(__('Nascondi', 'glurbackup')) . '";});});})();</script>';

        return (string) ob_get_clean();
    }

    private function renderSecretField(string $fieldId, string $value): string
    {
        $displayValue = $value !== '' ? $value : '—';

        return '<div class="input-group">'
            . '<input id="' . Server::h($fieldId) . '" type="password" class="form-control" value="' . Server::h($displayValue) . '" readonly>'
            . '<button type="button" class="btn btn-outline-secondary glurbackup-toggle-secret" data-target="' . Server::h($fieldId) . '">' . Server::h(__('Mostra', 'glurbackup')) . '</button>'
            . '</div>';
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
