<?php

namespace PluginGlurbackup;

use CommonGLPI;
use Html;
use Profile as GlpiProfile;
use ProfileRight;
use Session;

class Profile extends GlpiProfile
{
    public const PLUGIN_RIGHT = 'plugin_glurbackup';

    public static $rightname = 'profile';

    public static function h(?string $value): string
    {
        $value = $value ?? '';
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function getTypeName($nb = 0): string
    {
        return __('Glurbackup', 'glurbackup');
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        if ($item->getType() === 'Profile') {
            return __('Glurbackup', 'glurbackup');
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if ($item->getType() !== 'Profile') {
            return false;
        }

        $profileId = (int) $item->getID();
        self::showRightsForm($profileId);

        return true;
    }

    public static function getCurrentRight(int $profileId): int
    {
        $rights = ProfileRight::getProfileRights($profileId);

        return (int) ($rights[self::PLUGIN_RIGHT] ?? 0);
    }

    public static function saveRights(int $profileId, int $rightValue): void
    {
        $rightValue = max(0, $rightValue);

        ProfileRight::updateProfileRights($profileId, [
            self::PLUGIN_RIGHT => $rightValue,
        ]);

        if (
            isset($_SESSION['glpiactiveprofile']['id'])
            && (int) $_SESSION['glpiactiveprofile']['id'] === $profileId
        ) {
            unset($_SESSION['glpiactiveprofile'][self::PLUGIN_RIGHT]);
        }
    }

    public static function showRightsForm(int $profileId): void
    {
        global $CFG_GLPI;

        // READ può visualizzare il tab
        if (!Session::haveRight('profile', READ)) {
            Html::displayRightError();
        }

        $canedit = Session::haveRight('profile', UPDATE);
        $current = self::getCurrentRight($profileId);

        $choices = [
            0                => __('No access', 'glurbackup'),
            READ             => __('Read', 'glurbackup'),
            ALLSTANDARDRIGHT => __('Write', 'glurbackup'),
        ];

        if ($canedit) {
            echo "<form method='post' action='" . $CFG_GLPI['root_doc'] . "/plugins/glurbackup/front/profile.form.php'>";
            echo Html::hidden('_glpi_csrf_token', [
                'value' => Session::getNewCSRFToken(),
            ]);
            echo Html::hidden('profiles_id', [
                'value' => $profileId,
            ]);
        }

        echo "<table class='tab_cadre_fixe'>";
        echo "<tr><th colspan='2'>" . self::h(__('Glurbackup rights', 'glurbackup')) . "</th></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td style='width:320px'>" . self::h(__('Access level', 'glurbackup')) . "</td>";
        echo "<td>";

        if ($canedit) {
            echo "<select class='form-select' name='plugin_glurbackup'>";
        } else {
            echo "<select class='form-select' disabled>";
        }

        foreach ($choices as $value => $label) {
            $selected = ((int) $current === (int) $value) ? ' selected' : '';
            echo "<option value='" . (int) $value . "'" . $selected . ">" . self::h($label) . "</option>";
        }

        echo "</select>";

        if (!$canedit) {
            // Campo hidden solo informativo se in futuro servirà nel DOM
            echo "<input type='hidden' name='plugin_glurbackup_current' value='" . (int) $current . "'>";
        }

        echo "</td>";
        echo "</tr>";

        if ($canedit) {
            echo "<tr>";
            echo "<td colspan='2' class='center'>";
            echo "<button type='submit' name='update' value='1' class='btn btn-primary'>";
            echo self::h(__('Save'));
            echo "</button>";
            echo "</td>";
            echo "</tr>";
        }

        echo "</table>";

        if ($canedit) {
            echo "</form>";
        }
    }
}

// Alias globale compatibile con setup.php e registerClass()
if (!class_exists('PluginGlurbackupProfile', false)) {
    class_alias(__NAMESPACE__ . '\\Profile', 'PluginGlurbackupProfile');
}
