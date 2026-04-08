<?php

namespace PluginGlurbackup;

use CommonDBTM;
use Html;
use Session;

final class Config extends CommonDBTM
{
    public static $rightname = 'plugin_glurbackup';

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_glurbackup_configs';
    }

    public static function getTypeName($nb = 0): string
    {
        return __('Glurbackup configuration', 'glurbackup');
    }

    public static function canView(): bool
    {
        return Plugin::canRead();
    }

    public static function canCreate(): bool
    {
        return Plugin::canUpdate();
    }

    public function canUpdateItem(): bool
    {
        return Plugin::canUpdate();
    }

    public static function denyIfNoAdminRead(): void
    {
        if (!self::canView()) {
            Html::displayRightError();
        }
    }

    public static function denyIfNoAdminUpdate(): void
    {
        if (!self::canCreate()) {
            Html::displayRightError();
        }
    }

    public static function getSingletonId(): int
    {
        global $DB;

        $table = self::getTable();

        if (!$DB->tableExists($table)) {
            return 1;
        }

        $row = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => $table,
            'WHERE'  => ['id' => 1],
            'LIMIT'  => 1,
        ])->current();

        if ($row) {
            return 1;
        }

        $DB->insert($table, [
            'id'                          => 1,
            'api_base_path'               => '/x',
            'api_timeout'                 => 30,
            'log_retention_days'          => 90,
            'debug_mode'                  => 0,
            'enable_delete_client_button' => 0,
        ]);

        return 1;
    }

    public static function isDeleteClientEnabled(): bool
    {
        $config = new self();
        $id     = self::getSingletonId();

        if (!$config->getFromDB($id)) {
            return false;
        }

        return ((int) ($config->fields['enable_delete_client_button'] ?? 0)) === 1;
    }

    public function prepareInputForAdd($input)
    {
        return $this->normalizeInput((array) $input);
    }

    public function prepareInputForUpdate($input)
    {
        return $this->normalizeInput((array) $input);
    }

    private function normalizeInput(array $input): array
    {
        // Mantieni sempre singleton id=1
        $input['id'] = 1;

        // Se i campi non vengono passati, preserva i valori esistenti invece di forzare default
        $existing = is_array($this->fields ?? null) ? $this->fields : [];

        $input['api_base_path'] = array_key_exists('api_base_path', $input)
            ? (string) $input['api_base_path']
            : (string) ($existing['api_base_path'] ?? '/x');

        $input['api_timeout'] = array_key_exists('api_timeout', $input)
            ? (int) $input['api_timeout']
            : (int) ($existing['api_timeout'] ?? 30);

        $input['log_retention_days'] = array_key_exists('log_retention_days', $input)
            ? (int) $input['log_retention_days']
            : (int) ($existing['log_retention_days'] ?? 90);

        // QUI la correzione importante:
        // usare !empty / cast numerico, non isset(...)
        $input['debug_mode'] = array_key_exists('debug_mode', $input)
            ? (!empty($input['debug_mode']) ? 1 : 0)
            : (int) ($existing['debug_mode'] ?? 0);

        $input['enable_delete_client_button'] = array_key_exists('enable_delete_client_button', $input)
            ? (!empty($input['enable_delete_client_button']) ? 1 : 0)
            : (int) ($existing['enable_delete_client_button'] ?? 0);

        if ($input['api_timeout'] <= 0) {
            $input['api_timeout'] = 30;
        }

        if ($input['log_retention_days'] <= 0) {
            $input['log_retention_days'] = 90;
        }

        if ($input['api_base_path'] === '') {
            $input['api_base_path'] = '/x';
        }

        return $input;
    }

    public function showForm($ID, $options = []): bool
    {
        self::denyIfNoAdminRead();

        $ID = (int) $ID;
        if ($ID <= 0) {
            $ID = self::getSingletonId();
        }

        if (!$this->getFromDB($ID)) {
            $this->fields = [
                'id'                          => 1,
                'api_base_path'               => '/x',
                'api_timeout'                 => 30,
                'log_retention_days'          => 90,
                'debug_mode'                  => 0,
                'enable_delete_client_button' => 0,
            ];
        }

        $canedit = self::canCreate();
        $action  = htmlspecialchars(
            $GLOBALS['CFG_GLPI']['root_doc'] . '/plugins/glurbackup/front/config.form.php',
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        $csrf = htmlspecialchars(Session::getNewCSRFToken(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        echo '<form method="post" action="' . $action . '">';
        echo '<input type="hidden" name="_glpi_csrf_token" value="' . $csrf . '">';
        echo '<input type="hidden" name="id" value="' . (int) ($this->fields['id'] ?? 1) . '">';

        echo "<table class='tab_cadre_fixe' style='width:100%;'>";
        echo "<tr><th colspan='2'>" . htmlspecialchars(__('Impostazioni plugin', 'glurbackup'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</th></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td style='width:40%;'>" . htmlspecialchars(__('Abilita pulsante elimina client URBackup', 'glurbackup'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</td>";
        echo "<td><label>";
        echo '<input type="checkbox" name="enable_delete_client_button" value="1"'
            . (((int) ($this->fields['enable_delete_client_button'] ?? 0) === 1) ? ' checked' : '')
            . ($canedit ? '' : ' disabled') . '> ';
        echo htmlspecialchars(
            __('Consenti la visualizzazione e l’uso del pulsante di eliminazione client nel tab Azioni', 'glurbackup'),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        echo "</label></td>";
        echo "</tr>";

        echo "<tr><td colspan='2' class='center'>";
        if ($canedit) {
            echo '<button type="submit" name="save_glurbackup_config" value="1" class="btn btn-primary">';
            echo htmlspecialchars(__('Save'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            echo '</button>';
        }
        echo "</td></tr>";
        echo "</table>";
        echo "</form>";

        return true;
    }
}
