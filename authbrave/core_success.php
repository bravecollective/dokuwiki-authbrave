<?php

define('USE_EXT', 'GMP');
require('vendor/autoload.php');

require('config.php');

// -----------------------------------------------

$token = $_GET['token'];
if (!empty($token)) {
	$token = preg_replace("/[^A-Za-z0-9]/", '', $token);
}
if (empty($token)) {
    header("Location: " . $cfg_url_auth_fail);
    exit(1);
}

require('../../../inc/PassHash.class.php');
function getCookie() {
    $cookie = $_COOKIE['braveauth'];
    if (!empty($cookie)) {
	$cookie = preg_replace("/[^A-Za-z0-9]/", '', $cookie);
    }
    if (empty($cookie)) {
        $ph = new PassHash();
        $cookie = $ph->gen_salt(32);
        if (!headers_sent()) {
            setcookie('braveauth', $cookie, time() + (1000 * 60 * 60 * 24 * 7), '/', null, true, true);
        }
    }
    return $cookie;
}

$cookie = getCookie();

// -----------------------------------------------

$result = 0;

try {

    $api = new Brave\API($cfg_core_endpoint, $cfg_core_application_id, $cfg_core_private_key, $cfg_core_public_key);

    $result = $api->core->info(array('token' => $token));

} catch(\Exception $e) {
    require('core_error.php');
    exit(1);
}

$charid = $result->character->id;
$char = $result->character->name;
$user = strtolower($char);
$user = preg_replace("/[^A-Za-z0-9]/", '_', $user);

$corpid = $result->corporation->id;
$corp = $result->corporation->name;

$allianceid = $result->alliance->id;
$alliance = $result->alliance->name;

$tags = $result->tags;

// -----------------------------------------------

$db = new SQLite3($cfg_auth_db_path . '/auth.db', SQLITE3_OPEN_READWRITE);
if (!$db) die ('auth database init failed');

// -----------------------------------------------

function addGroup($groups, $criteria) {
    global $db;
    $stm = $db->prepare('SELECT grp FROM grp WHERE criteria = :criteria;');
    $stm->bindValue(':criteria', $criteria);
    $result = $stm->execute();
    if (!$result) die("Cannot execute query.");
    while($res = $result->fetchArray()){
	$groups[] = $res['grp'];
    }
    return $groups;
}

$groups = array('user');
$groups = addGroup($groups, 'charid_' . $charid);
$groups = addGroup($groups, 'corpid_' . $corpid);
$groups = addGroup($groups, 'allianceid_' . $allianceid);
//TODO add tags from core, e.g. wiki_admin, wiki_public_author

// -----------------------------------------------

$stm = $db->prepare('SELECT charid FROM user where charid = :charid;');
$stm->bindValue(':charid', $charid);
$result = $stm->execute();
if ($result->fetchArray()) {
    $stm = $db->prepare('UPDATE user SET user = :user, groups = :groups, char = :char, corpid = :corpid, corp = :corp, allianceid = :allianceid, alliance = :alliance  WHERE charid = :charid;');
} else {
    $stm = $db->prepare('INSERT INTO user (user, groups, charid, char, corpid, corp, allianceid, alliance) VALUES (:user, :groups, :charid, :char, :corpid, :corp, :allianceid, :alliance);');
}
$stm->bindValue(':user', $user);
$stm->bindValue(':groups', implode(',', $groups));
$stm->bindValue(':charid', $charid);
$stm->bindValue(':char', $char);
$stm->bindValue(':corpid', $corpid);
$stm->bindValue(':corp', $corp);
$stm->bindValue(':allianceid', $allianceid);
$stm->bindValue(':alliance', $alliance);
$result = $stm->execute();

$stm = $db->prepare('DELETE from session where sessionid = :sessionid;');
$stm->bindValue(':sessionid', $cookie);
$result = $stm->execute();

$stm = $db->prepare('INSERT INTO session (sessionid, charid, created) VALUES (:sessionid, :charid, :created)');
$stm->bindValue(':sessionid', $cookie);
$stm->bindValue(':charid', $charid);
$stm->bindValue(':created', time());
$result = $stm->execute();

// -----------------------------------------------

header("Location: " . $cfg_url_base);

?>
