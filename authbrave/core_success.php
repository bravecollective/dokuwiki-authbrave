<?php

require('config.php');

define('USE_EXT', 'GMP');
require('vendor/autoload.php');

// -----------------------------------------------

function raiseError($log) {
    require('core_error.php');
    die($log);
}

// -----------------------------------------------

$token = preg_replace("/[^A-Za-z0-9]/", '', $_GET['token']);
if (empty($token)) {
    raiseError('no token');
}

// -----------------------------------------------

require('../../../inc/PassHash.class.php');
$ph = new PassHash();
$cookie = $ph->gen_salt(32);
if (!headers_sent()) {
    setcookie($cfg_cookie_name, $cookie, time() + $cfg_expire_session, '/', null, $cfg_cookie_https_only, true);
}

// -----------------------------------------------

try {
    $api = new Brave\API($cfg_core_endpoint, $cfg_core_application_id, $cfg_core_private_key, $cfg_core_public_key);
    $result = $api->core->info(array('token' => $token));
} catch(\Exception $e) {
    raiseError('core failed');
}

$charid = $result->character->id;
$charname = $result->character->name;
$username = preg_replace("/[^A-Za-z0-9]/", '_', strtolower($charname));
$corpid = $result->corporation->id;
$corpname = $result->corporation->name;
$allianceid = $result->alliance->id;
$alliancename = $result->alliance->name;
$tags = $result->tags;
$perms = $result->perms;

// -----------------------------------------------

try {
    $db = new PDO($cfg_sql_url, $cfg_sql_user, $cfg_sql_pass);
} catch (PDOException $e) {
    raiseError('auth database init failed');
}

// -----------------------------------------------

function addGroup($db, $groups, $criteria) {
    $stm = $db->prepare('SELECT grp FROM grp WHERE criteria = :criteria;');
    $stm->bindValue(':criteria', $criteria);
    if (!$stm->execute()) { raiseError('group query failed'); };
    while($res = $stm->fetch()){
	$groups[] = $res['grp'];
    }
    return $groups;
}

$groups = array('user');
$groups = addGroup($db, $groups, 'charid_' . $charid);
$groups = addGroup($db, $groups, 'corpid_' . $corpid);
$groups = addGroup($db, $groups, 'allianceid_' . $allianceid);
foreach ($tags as $tkey => $tvalue) {
    $groups = addGroup($db, $groups, 'tag_' . $tvalue);
}
foreach ($perms as $pkey => $pvalue) {
    $groups = addGroup($db, $groups, 'perm_' . $pvalue);
}
$groups = array_unique($groups);

// -----------------------------------------------

function addBan($db, $banned, $criteria) {
    $stm = $db->prepare('SELECT id FROM ban WHERE criteria = :criteria;');
    $stm->bindValue(':criteria', $criteria);
    if (!$stm->execute()) { raiseError('ban query failed'); };
    if ($stm->fetch()) {
	return true;
    }
    return $banned;
}

$banned = false;
$banned = addBan($db, $banned, 'charid_' . $charid);
$banned = addBan($db, $banned, 'corpid_' . $corpid);
$banned = addBan($db, $banned, 'allianceid_' . $allianceid);
foreach ($tags as $tkey => $tvalue) {
    $banned = addBan($db, $banned, 'tag_' . $tvalue);
}

if ($banned) {
    $groups = array('user');
}

// -----------------------------------------------

$stm = $db->prepare('SELECT charid FROM user where charid = :charid;');
$stm->bindValue(':charid', $charid);
if (!$stm->execute()) { raiseError('user query failed'); };

if ($stm->fetch()) {
    $stm = $db->prepare('UPDATE user SET username = :username, groups = :groups, charname = :charname, corpid = :corpid, corpname = :corpname, allianceid = :allianceid, alliancename = :alliancename, authtoken = :authtoken, authlast = :now WHERE charid = :charid;');
} else {
    $stm = $db->prepare('INSERT INTO user (username, groups, charid, charname, corpid, corpname, allianceid, alliancename, authtoken, authcreated, authlast) VALUES (:username, :groups, :charid, :charname, :corpid, :corpname, :allianceid, :alliancename, :authtoken, :now, :now);');
}
$stm->bindValue(':username', $username, PDO::PARAM_STR);
$stm->bindValue(':groups', implode(',', $groups), PDO::PARAM_STR);
$stm->bindValue(':charid', $charid, PDO::PARAM_INT);
$stm->bindValue(':charname', $charname, PDO::PARAM_STR);
$stm->bindValue(':corpid', $corpid, PDO::PARAM_INT);
$stm->bindValue(':corpname', $corpname, PDO::PARAM_STR);
$stm->bindValue(':allianceid', $allianceid, PDO::PARAM_INT);
$stm->bindValue(':alliancename', $alliancename, PDO::PARAM_STR);
$stm->bindValue(':authtoken', $token, PDO::PARAM_STR);
$stm->bindValue(':now', time(), PDO::PARAM_INT);
if (!$stm->execute()) { raiseError('user insert or update failed'); };

$stm = $db->prepare('DELETE from session where sessionid = :sessionid;');
$stm->bindValue(':sessionid', $cookie);
if (!$stm->execute()) { raiseError('session cleanup failed'); };

$stm = $db->prepare('INSERT INTO session (sessionid, charid, created) VALUES (:sessionid, :charid, :created)');
$stm->bindValue(':sessionid', $cookie);
$stm->bindValue(':charid', $charid);
$stm->bindValue(':created', time());
if (!$stm->execute()) { raiseError('session insert failed'); };

// -----------------------------------------------

header("Location: " . $cfg_url_base . '/' . $_GET['cb']);

?>
