<?php

use PluginGlurbackup\Profile;

include('../../../inc/includes.php');
Session::checkCentralAccess();

if (!Session::haveRight('profile', UPDATE)) {
    Html::displayRightError();
}

$profileId  = (int) ($_POST['profiles_id'] ?? 0);
$rightValue = (int) ($_POST['plugin_glurbackup'] ?? 0);

if (isset($_POST['update']) && $profileId > 0) {
    Profile::saveRights($profileId, $rightValue);
}

Html::redirect(
    $CFG_GLPI['root_doc']
    . '/front/profile.form.php?id='
    . $profileId
    . '&forcetab='
    . rawurlencode('PluginGlurbackup\\Profile$1')
);
