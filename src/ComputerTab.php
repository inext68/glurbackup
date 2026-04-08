<?php

namespace PluginGlurbackup;

use CommonGLPI;
use Computer;
use Html;
use Session;

class ComputerTab extends CommonGLPI
{
    public const PLUGIN_RIGHT = 'plugin_glurbackup';

    public static function canRead(): bool
    {
        return Session::haveRight(self::PLUGIN_RIGHT, READ)
            || Session::haveRight('config', UPDATE);
    }

    public static function getTypeName($nb = 0): string
    {
        return __('URBackup', 'glurbackup');
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        if (!($item instanceof Computer)) {
            return '';
        }

        if (!self::canRead()) {
            return '';
        }

        return __('URBackup', 'glurbackup');
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if (!($item instanceof Computer)) {
            return false;
        }

        if (!self::canRead()) {
            Html::displayRightError();
        }

        $controller = new ServerController();
        echo $controller->renderComputerTab($item);

        return true;
    }
}

if (!class_exists('PluginGlurbackupComputerTab', false)) {
    \class_alias(__NAMESPACE__ . '\\ComputerTab', 'PluginGlurbackupComputerTab');
}
