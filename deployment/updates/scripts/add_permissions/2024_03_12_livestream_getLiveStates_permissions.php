<?php
/**
 * @package deployment
 * @subpackage tucana.roles_and_permissions
 */

$script = realpath(dirname(__FILE__) . '/../../../../') . '/alpha/scripts/utils/permissions/addPermissionsAndItems.php';

$config = realpath(dirname(__FILE__) . '/../../../') . '/permissions/service.livestream.ini';
passthru("php $script $config");
