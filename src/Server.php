<?php

namespace PluginGlurbackup;

use CommonDBTM;
use Html;
use Session;

require_once __DIR__ . '/ComputerServer.php';
require_once __DIR__ . '/ServerFormRenderer.php';
require_once __DIR__ . '/ServerUrbackupClientProvider.php';

class Server extends CommonDBTM
{
    public const PLUGIN_RIGHT = 'plugin_glurbackup';

    public static $rightname = self::PLUGIN_RIGHT;

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_glurbackup_servers';
    }

    public static function getSearchURL($full = true): string
    {
        global $CFG_GLPI;

        $path = '/plugins/glurbackup/front/server.php';

        if ($full) {
            return $CFG_GLPI['root_doc'] . $path;
        }

        return $path;
    }

    public static function getFormURL($full = true): string
    {
        global $CFG_GLPI;

        $path = '/plugins/glurbackup/front/server.form.php';

        if ($full) {
            return $CFG_GLPI['root_doc'] . $path;
        }

        return $path;
    }

    public static function getTypeName($nb = 0): string
    {
        return _n('URBackup server', 'URBackup servers', $nb, 'glurbackup');
    }

    public static function canAdminRead(): bool
    {
        return Session::haveRight(self::PLUGIN_RIGHT, READ) || Session::haveRight('config', UPDATE);
    }

    public static function canAdminUpdate(): bool
    {
        return Session::haveRight(self::PLUGIN_RIGHT, UPDATE) || Session::haveRight('config', UPDATE);
    }

    public static function canView(): bool
    {
        return self::canAdminRead();
    }

    public static function canCreate(): bool
    {
        return self::canAdminUpdate();
    }

    public function canUpdateItem(): bool
    {
        return self::canAdminUpdate();
    }

    public function canDeleteItem(): bool
    {
        return self::canAdminUpdate();
    }

    public static function denyIfNoAdminRead(): void
    {
        if (!self::canAdminRead()) {
            Html::displayRightError();
        }
    }

    public static function denyIfNoAdminUpdate(): void
    {
        if (!self::canAdminUpdate()) {
            Html::displayRightError();
        }
    }

    public static function h(?string $value): string
    {
        $value = $value ?? '';
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function getMenuName(): string
    {
        return __('URBackup servers', 'glurbackup');
    }

    public static function getMenuContent(): array
    {
        if (!self::canAdminRead()) {
            return [];
        }

        return [
            'title' => self::getMenuName(),
            'page'  => '/plugins/glurbackup/front/server.php',
            'icon'  => 'fas fa-server',
        ];
    }

    public function getRights($interface = 'central'): array
    {
        return parent::getRights($interface);
    }

    public function prepareInputForAdd($input)
    {
        return $this->prepareInput((array) $input, true);
    }

    public function prepareInputForUpdate($input)
    {
        return $this->prepareInput((array) $input, false);
    }

    private function prepareInput(array $input, bool $isAdd): array
    {
        $input['name']         = trim((string) ($input['name'] ?? ''));
        $input['ip_address']   = trim((string) ($input['ip_address'] ?? ''));
        $input['port']         = max(1, (int) ($input['port'] ?? 55414));
        $input['version']      = UrbackupApiFactory::normalize((string) ($input['version'] ?? '2.5'));
        $input['protocol']     = (($input['protocol'] ?? 'http') === 'https') ? 'https' : 'http';
        $input['ignore_ssl']   = isset($input['ignore_ssl']) ? 1 : 0;
        $input['is_default']   = isset($input['is_default']) ? 1 : 0;
        $input['is_recursive'] = isset($input['is_recursive']) ? 1 : 0;
        $input['entities_id']  = (int) ($input['entities_id'] ?? 0);
        $input['locations_id'] = (int) ($input['locations_id'] ?? 0);
        $input['date_mod']     = date('Y-m-d H:i:s');

        if ($isAdd && !isset($input['date_creation'])) {
            $input['date_creation'] = date('Y-m-d H:i:s');
        }

        if (isset($input['password']) && trim((string) $input['password']) !== '') {
            $input['password'] = self::encryptSecret((string) $input['password']);
        } else {
            if (!$isAdd && array_key_exists('password', $input)) {
                unset($input['password']);
            }
        }

        return $input;
    }

    public function post_getFromDB(): void
    {
        if (!empty($this->fields['password'])) {
            $this->fields['password'] = self::decryptSecret((string) $this->fields['password']);
        }
    }

    public function post_addItem(): void
    {
        $this->normalizeDefaultServer();

        if (class_exists(Log::class)) {
            Log::record(
                0,
                (int) $this->fields['id'],
                'server.add',
                'success',
                __('URBackup server added', 'glurbackup'),
                [
                    'name'       => $this->fields['name'] ?? '',
                    'ip_address' => $this->fields['ip_address'] ?? '',
                    'port'       => $this->fields['port'] ?? 55414,
                    'version'    => $this->fields['version'] ?? '2.5',
                ]
            );
        }
    }

    public function post_updateItem($history = 1): void
    {
        $this->normalizeDefaultServer();

        if (class_exists(Log::class)) {
            Log::record(
                0,
                (int) $this->fields['id'],
                'server.update',
                'success',
                __('URBackup server updated', 'glurbackup')
            );
        }
    }

    public function post_deleteItem(): void
    {
        if (class_exists(Log::class)) {
            Log::record(
                0,
                (int) $this->fields['id'],
                'server.delete',
                'success',
                __('URBackup server deleted', 'glurbackup')
            );
        }
    }

    private function normalizeDefaultServer(): void
    {
        global $DB;

        if ((int) ($this->fields['is_default'] ?? 0) !== 1) {
            return;
        }

        $DB->update(self::getTable(), ['is_default' => 0], [
            'locations_id' => (int) ($this->fields['locations_id'] ?? 0),
            'entities_id'  => (int) ($this->fields['entities_id'] ?? 0),
            'id'           => ['<>', (int) ($this->fields['id'] ?? 0)],
        ]);
    }

    public function rawSearchOptions(): array
    {
        $table    = self::getTable();
        $itemtype = 'PluginGlurbackupServer';

        return [
            [
                'id'   => 'common',
                'name' => __('URBackup server', 'glurbackup'),
            ],
            [
                'id'       => '1',
                'table'    => $table,
                'field'    => 'id',
                'name'     => __('ID'),
                'datatype' => 'number',
                'itemtype' => $itemtype,
            ],
            [
                'id'       => '2',
                'table'    => $table,
                'field'    => 'name',
                'name'     => __('Name'),
                'datatype' => 'itemlink',
                'itemtype' => $itemtype,
            ],
            [
                'id'       => '3',
                'table'    => $table,
                'field'    => 'ip_address',
                'name'     => __('IP address', 'glurbackup'),
                'datatype' => 'string',
                'itemtype' => $itemtype,
            ],
            [
                'id'       => '4',
                'table'    => $table,
                'field'    => 'port',
                'name'     => __('Port'),
                'datatype' => 'number',
                'itemtype' => $itemtype,
            ],
            [
                'id'        => '5',
                'table'     => 'glpi_locations',
                'field'     => 'completename',
                'linkfield' => 'locations_id',
                'name'      => __('Location'),
                'datatype'  => 'dropdown',
                'itemtype'  => $itemtype,
            ],
            [
                'id'        => '6',
                'table'     => 'glpi_entities',
                'field'     => 'completename',
                'linkfield' => 'entities_id',
                'name'      => __('Entity'),
                'datatype'  => 'dropdown',
                'itemtype'  => $itemtype,
            ],
            [
                'id'       => '7',
                'table'    => $table,
                'field'    => 'protocol',
                'name'     => __('Protocol', 'glurbackup'),
                'datatype' => 'string',
                'itemtype' => $itemtype,
            ],
            [
                'id'       => '8',
                'table'    => $table,
                'field'    => 'ignore_ssl',
                'name'     => __('Ignore SSL', 'glurbackup'),
                'datatype' => 'bool',
                'itemtype' => $itemtype,
            ],
            [
                'id'       => '9',
                'table'    => $table,
                'field'    => 'is_default',
                'name'     => __('Default', 'glurbackup'),
                'datatype' => 'bool',
                'itemtype' => $itemtype,
            ],
            [
                'id'       => '10',
                'table'    => $table,
                'field'    => 'comment',
                'name'     => __('Comments'),
                'datatype' => 'text',
                'itemtype' => $itemtype,
            ],
            [
                'id'       => '11',
                'table'    => $table,
                'field'    => 'date_mod',
                'name'     => __('Last update'),
                'datatype' => 'datetime',
                'itemtype' => $itemtype,
            ],
            [
                'id'       => '12',
                'table'    => $table,
                'field'    => 'date_creation',
                'name'     => __('Creation date'),
                'datatype' => 'datetime',
                'itemtype' => $itemtype,
            ],
            [
                'id'       => '13',
                'table'    => $table,
                'field'    => 'username',
                'name'     => __('Username'),
                'datatype' => 'string',
                'itemtype' => $itemtype,
            ],
            [
                'id'       => '14',
                'table'    => $table,
                'field'    => 'version',
                'name'     => __('URBackup server version', 'glurbackup'),
                'datatype' => 'string',
                'itemtype' => $itemtype,
            ],
        ];
    }

    public function showForm($ID, $options = []): bool
    {
        self::denyIfNoAdminRead();

        $this->initForm($ID, $options);

        if ($ID > 0) {
            $this->getFromDB($ID);
        } else {
            $this->fields['port']     = 55414;
            $this->fields['protocol'] = 'http';
            $this->fields['version']  = '2.5';
        }

        $canedit = self::canAdminUpdate();

        $options['target']  = self::getFormURL();
        $options['canedit'] = $canedit;
        $options['colspan'] = 4;

        $renderer = new ServerFormRenderer();
        return $renderer->render($this, $options);
    }

    private static function encryptSecret(string $plain): string
    {
        if ($plain === '') {
            return '';
        }

        if (!function_exists('openssl_encrypt')) {
            return base64_encode($plain);
        }

        $key = hash('sha256', self::getCryptoKey(), true);
        $iv  = random_bytes(16);

        $cipher = openssl_encrypt(
            $plain,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($cipher === false) {
            return base64_encode($plain);
        }

        return base64_encode($iv . $cipher);
    }

    private static function decryptSecret(string $encoded): string
    {
        if ($encoded === '') {
            return '';
        }

        $raw = base64_decode($encoded, true);
        if ($raw === false) {
            return '';
        }

        if (!function_exists('openssl_decrypt')) {
            return $raw;
        }

        if (strlen($raw) < 17) {
            return $raw;
        }

        $iv     = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $key    = hash('sha256', self::getCryptoKey(), true);

        $plain = openssl_decrypt(
            $cipher,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return $plain === false ? '' : $plain;
    }

    private static function getCryptoKey(): string
    {
        if (defined('GLPIKEY')) {
            return (string) GLPIKEY;
        }

        if (defined('GLPI_CONFIG_DIR')) {
            return 'glurbackup|' . GLPI_CONFIG_DIR;
        }

        return 'glurbackup|fallback-key';
    }
}

if (!class_exists('PluginGlurbackupServer', false)) {
    class_alias(__NAMESPACE__ . '\\Server', 'PluginGlurbackupServer');
}

