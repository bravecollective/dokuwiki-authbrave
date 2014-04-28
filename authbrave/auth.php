<?php
/**
 * DokuWiki Plugin authbrave (Auth Component)
 *
 * @license MIT http://opensource.org/licenses/MIT
 * @author  kiu kiu@gmx.net
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class auth_plugin_authbrave extends DokuWiki_Auth_Plugin {

    private $db = 0;

    private function getCookie() {
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

    private function getUser() {
	$stm = $this->db->prepare('DELETE FROM session WHERE created < :time;');
	$stm->bindValue(':time', time() - (1000 * 60 * 60 * 24 * 1));
	$result = $stm->execute();

	$stm = $this->db->prepare('SELECT charid FROM session WHERE sessionid = :sessionid;');
	$stm->bindValue(':sessionid', $this->getCookie());
	$result = $stm->execute();
	if (!$result) die("Cannot execute query.");

	$row = $result->fetchArray();
	if (!$row) {
	    return false;
	}

	$stm = $this->db->prepare('SELECT * FROM user WHERE charid = :charid;');
	$stm->bindValue(':charid', $row['charid']);
	$result = $stm->execute();
	if (!$result) die("Cannot execute query.");

	$row = $result->fetchArray();
	if (!$row) {
	    return false;
	}

	return $row;
    }

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct(); // for compatibility

	require('config.php');
	$this->db = new SQLite3($cfg_auth_db_path . '/auth.db', SQLITE3_OPEN_READWRITE);
	if (!$this->db) die ('auth database init failed');

        $this->cando['modMail']     = true; // can emails be changed?
        $this->cando['external']    = true; // does the module do external auth checking?
        $this->cando['logout']      = true; // can the user logout again? (eg. not possible with HTTP auth)

        $this->cando['addUser']     = false; // can Users be created?
        $this->cando['delUser']     = false; // can Users be deleted?
        $this->cando['modLogin']    = false; // can login names be changed?
        $this->cando['modPass']     = false; // can passwords be changed?
        $this->cando['modName']     = false; // can real names be changed?
        $this->cando['modGroups']   = false; // can groups be changed?
        $this->cando['getUsers']    = false; // can a (filtered) list of users be retrieved?
        $this->cando['getUserCount']= false; // can the number of users be retrieved?
        $this->cando['getGroups']   = false; // can a list of available groups be retrieved?

        $this->success = true;
    }


    public function logOff() {
	$stm = $this->db->prepare('DELETE FROM session WHERE sessionid = :sessionid;');
	$stm->bindValue(':sessionid', $this->getCookie());
	$result = $stm->execute();
    }

    /**
     * Do all authentication [ OPTIONAL ]
     *
     * @param   string  $user    Username
     * @param   string  $pass    Cleartext Password
     * @param   bool    $sticky  Cookie should not expire
     * @return  bool             true on successful auth
     */
    public function trustExternal($user, $pass, $sticky = false) {
        global $USERINFO;
        global $conf;
        $sticky ? $sticky = true : $sticky = false; //sanity check

	$row = $this->getUser();
	if (!$row) {
	    return false;
	}

        // set the globals if authed
        $USERINFO['name'] = $row['char'];
        $USERINFO['mail'] = $row['mail'];
        $USERINFO['grps'] = explode(',', $row['groups']);
        $_SERVER['REMOTE_USER'] = $row['user'];
        $_SESSION[DOKU_COOKIE]['auth']['user'] = $row['user'];
//        $_SESSION[DOKU_COOKIE]['auth']['pass'] = $pass;
        $_SESSION[DOKU_COOKIE]['auth']['info'] = $USERINFO;
        return true;
    }

    /**
     * Return user info
     *
     * Returns info about the given user needs to contain
     * at least these fields:
     *
     * name string  full name of the user
     * mail string  email addres of the user
     * grps array   list of groups the user is in
     *
     * @param   string $user the user name
     * @return  array containing user data or false
     */
    public function getUserData($user) {
	$stm = $this->db->prepare('SELECT * FROM user WHERE user = :user;');
	$stm->bindValue(':user', $user);
	$result = $stm->execute();
	if (!$result) die("Cannot execute query.");

	$row = $result->fetchArray();
	if (!$row) {
	    return false;
	}

	return array('name' => $row['char'], 'email' => $row['mail'], 'grps' => explode(',', $row['grp']));
    }

    /**
     * Modify user data [implement only where required/possible]
     *
     * Set the mod* capabilities according to the implemented features
     *
     * @param   string $user    nick of the user to be changed
     * @param   array  $changes array of field/value pairs to be changed (password will be clear text)
     * @return  bool
     */
    public function modifyUser($user, $changes) {
	$stm = $this->db->prepare('UPDATE user SET mail = :mail WHERE user = :user;');
	$stm->bindValue(':user', $user);
	$stm->bindValue(':mail', $changes['mail']);
	$result = $stm->execute();
	if (!$result) die("Cannot execute query.");
	return true;
    }

    /**
     * Return case sensitivity of the backend
     *
     * When your backend is caseinsensitive (eg. you can login with USER and
     * user) then you need to overwrite this method and return false
     *
     * @return bool
     */
    public function isCaseSensitive() {
        return true;
    }

    /**
     * Sanitize a given username
     *
     * This function is applied to any user name that is given to
     * the backend and should also be applied to any user name within
     * the backend before returning it somewhere.
     *
     * This should be used to enforce username restrictions.
     *
     * @param string $user username
     * @return string the cleaned username
     */
    public function cleanUser($user) {
	return preg_replace("/[^A-Za-z0-9]/", '_', $user);
    }

    /**
     * Sanitize a given groupname
     *
     * This function is applied to any groupname that is given to
     * the backend and should also be applied to any groupname within
     * the backend before returning it somewhere.
     *
     * This should be used to enforce groupname restrictions.
     *
     * Groupnames are to be passed without a leading '@' here.
     *
     * @param  string $group groupname
     * @return string the cleaned groupname
     */
    public function cleanGroup($group) {
	return preg_replace("/[^A-Za-z0-9]/", '_', $group);
    }

    /**
     * Check Session Cache validity [implement only where required/possible]
     *
     * DokuWiki caches user info in the user's session for the timespan defined
     * in $conf['auth_security_timeout'].
     *
     * This makes sure slow authentication backends do not slow down DokuWiki.
     * This also means that changes to the user database will not be reflected
     * on currently logged in users.
     *
     * To accommodate for this, the user manager plugin will touch a reference
     * file whenever a change is submitted. This function compares the filetime
     * of this reference file with the time stored in the session.
     *
     * This reference file mechanism does not reflect changes done directly in
     * the backend's database through other means than the user manager plugin.
     *
     * Fast backends might want to return always false, to force rechecks on
     * each page load. Others might want to use their own checking here. If
     * unsure, do not override.
     *
     * @param  string $user - The username
     * @return bool
     */
    public function useSessionCache($user) {
	return false;
    }
}

// vim:ts=4:sw=4:et:
