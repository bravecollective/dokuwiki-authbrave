<?php

define('USE_EXT', 'GMP');
require 'vendor/autoload.php';

require('config.php');

try {

    $api = new Brave\API('https://core.braveineve.com/api', $cfg_core_application_id, $cfg_core_private_key, $cfg_core_public_key);

    $info_data = array(
	'success' => $cfg_url_auth_success,
	'failure' => $cfg_url_auth_fail
    );

    $result = $api->core->authorize($info_data);
    header("Location: " . $result->location);

} catch(\Exception $e) {
    require('core_error.php');
    exit(1);
}

?>
