<?php

namespace PluginGlurbackup;

use CommonDBTM;
use MassiveAction;
use Html;
use Dropdown;
use Computer;

/**
 * Massive actions for Computer ↔ URBackup linkage
 * GLPI 11 – strict mode
 */
class MassiveActionComputer extends CommonDBTM
{
    public const ACTION_LINK_SERVER   = 'link_server';
    public const ACTION_UNLINK_SERVER = 'unlink_server';

    /**
     * Display sub-form for massive actions
     */
    public static function showMassiveActionsSubForm(MassiveAction $ma)
    {
        switch ($ma->getAction()) {

            case self::ACTION_LINK_SERVER:
                echo "<span class='small_space'>";
                echo Server::h(__('Select the URBackup server to associate with the selected computers.', 'glurbackup'));
                echo "<br>";

                Dropdown::show('PluginGlurbackupServer', [
                    'name'                => 'plugin_glurbackup_servers_id',
                    'display_emptychoice' => false,
                ]);

                echo "<br>";
                echo Html::submit(__('Apply'), ['name' => 'massiveaction']);
                echo "</span>";
                return true;

            case self::ACTION_UNLINK_SERVER:
                echo "<span class='small_space'>";
                echo Server::h(__('This action will unlink the selected computers from any URBackup server (GLPI database only).', 'glurbackup'));
                echo "<br>";
                echo Html::submit(__('Apply'), ['name' => 'massiveaction']);
                echo "</span>";
                return true;
        }

        return parent::showMassiveActionsSubForm($ma);
    }

    /**
     * Execute massive actions
     */
    public static function processMassiveActionsForOneItemtype(
        MassiveAction $ma,
        CommonDBTM $item,
        array $ids
    ): void {

        switch ($ma->getAction()) {

            case self::ACTION_LINK_SERVER:
                $input    = $ma->getInput();
                $serverId = (int) ($input['plugin_glurbackup_servers_id'] ?? 0);

                foreach ($ids as $id) {
                    if (
                        $serverId > 0 &&
                        $item->getFromDB((int) $id) &&
                        ComputerServer::linkComputerToServer((int) $id, $serverId)
                    ) {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                    }
                }
                return;

            case self::ACTION_UNLINK_SERVER:
                foreach ($ids as $id) {
                    if (
                        $item->getFromDB((int) $id) &&
                        ComputerServer::unlinkComputer((int) $id)
                    ) {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                    }
                }
                return;
        }

        parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
    }
}

if (!class_exists('PluginGlurbackupMassiveActionComputer', false)) {
    class_alias(
        __NAMESPACE__ . '\\MassiveActionComputer',
        'PluginGlurbackupMassiveActionComputer'
    );
}

