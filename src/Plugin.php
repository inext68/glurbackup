<?php

namespace PluginGlurbackup;

use Computer;
use Session;

final class Plugin
{
    public const RIGHTNAME = 'plugin_glurbackup';

    public const TAB_OVERVIEW = 'glurbackup_overview';

    public static function canRead(): bool
    {
        return Session::haveRight(self::RIGHTNAME, READ)
            || Session::haveRight('config', UPDATE);
    }

    public static function canUpdate(): bool
    {
        return Session::haveRight(self::RIGHTNAME, UPDATE)
            || Session::haveRight('config', UPDATE);
    }

    public static function getComputerTabs(Computer $computer): array
    {
        if (!self::canRead()) {
            return [];
        }

        return [
            self::TAB_OVERVIEW => __('URBackup', 'glurbackup'),
        ];
    }

    public static function displayComputerTabContent(Computer $computer, string $tab): string
    {
        if (!self::canRead()) {
            return '';
        }

        if ($tab !== self::TAB_OVERVIEW) {
            return '';
        }

        $controller = new ServerController();

        return $controller->renderComputerTab($computer);
    }
}
