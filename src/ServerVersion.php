<?php

namespace PluginGlurbackup;

use CommonDropdown;
use Session;

class ServerVersion extends CommonDropdown
{
    public const PLUGIN_RIGHT = 'plugin_glurbackup';

    public static $rightname = self::PLUGIN_RIGHT;

    public static function getTypeName($nb = 0): string
    {
        return _n('URBackup server version', 'URBackup server versions', $nb, 'glurbackup');
    }

    public static function getTable($classname = null): string
    {
        return 'glpi_pluginglurbackups_serverversions';
    }

    public static function getSearchURL($full = true): string
    {
        global $CFG_GLPI;

        $path = '/plugins/glurbackup/front/serverversion.php';

        if ($full) {
            return $CFG_GLPI['root_doc'] . $path;
        }

        return $path;
    }

    public static function getFormURL($full = true): string
    {
        global $CFG_GLPI;

        $path = '/plugins/glurbackup/front/serverversion.form.php';

        if ($full) {
            return $CFG_GLPI['root_doc'] . $path;
        }

        return $path;
    }

    public static function canView(): bool
    {
        return Session::haveRight(self::PLUGIN_RIGHT, READ) || Session::haveRight('config', UPDATE);
    }

    public static function canCreate(): bool
    {
        return Session::haveRight(self::PLUGIN_RIGHT, UPDATE) || Session::haveRight('config', UPDATE);
    }

    public function canUpdateItem(): bool
    {
        return self::canCreate();
    }

    public function canDeleteItem(): bool
    {
        return self::canCreate();
    }

    public static function getMenuName(): string
    {
        return __('URBackup server versions', 'glurbackup');
    }

    public function getAdditionalFields(): array
    {
        return [
            [
                'name'  => 'comment',
                'label' => __('Comments'),
                'type'  => 'text',
            ],
        ];
    }
}

if (!class_exists('PluginGlurbackupServerVersion', false)) {
    \class_alias(__NAMESPACE__ . '\\ServerVersion', 'PluginGlurbackupServerVersion');
}
