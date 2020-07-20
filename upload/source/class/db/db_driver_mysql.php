<?php

/**
 *      [Discuz!] (C)2001-2099 Comsenz Inc.
 *      This is NOT a freeware, use is subject to license terms
 *
 *      $Id: db_driver_mysql.php 28855 2012-03-15 05:48:02Z zhengqingpeng $
 */

if(!defined('IN_DISCUZ')) {
	exit('Access Denied');
}

class db_driver_mysql
{
	var $tablepre;
	var $version = '';
	var $querynum = 0;
	var $slaveid = 0;
	var $curlink;
	var $link = array();
	var $config = array();
	var $sqldebug = array();
	var $map = array();

	function db_mysql($config = array()) {
		if(!empty($config)) {
			$this->set_config($config);
		}
	}

	function set_config($config) {
		$this->config = &$config;
                //$this->halt(' 2 '.json_encode($this->config['1']), 111111);
		//$this->tablepre = $this->config['1']['tablepre'];//$config['1']['tablepre'];
                //$this->halt(' '.$this->config['1']['tablepre'], 111111);
                $this->tablepre = $this->config['1']['tablepre'];
                //$this->halt(' '.$this->tablepre, 111111);
                //var_dump($this->config['1']['tablepre']);
                //var_dump($this->tablepre);
		if(!empty($this->config['map'])) {
			$this->map = $this->config['map'];
			for($i = 1; $i <= 100; $i++) {
				if(isset($this->map['forum_thread'])) {
					$this->map['forum_thread_'.$i] = $this->map['forum_thread'];
				}
				if(isset($this->map['forum_post'])) {
					$this->map['forum_post_'.$i] = $this->map['forum_post'];
				}
				if(isset($this->map['forum_attachment']) && $i <= 10) {
					$this->map['forum_attachment_'.($i-1)] = $this->map['forum_attachment'];
				}
			}
			if(isset($this->map['common_member'])) {
				$this->map['common_member_archive'] =
				$this->map['common_member_count'] = $this->map['common_member_count_archive'] =
				$this->map['common_member_status'] = $this->map['common_member_status_archive'] =
				$this->map['common_member_profile'] = $this->map['common_member_profile_archive'] =
				$this->map['common_member_field_forum'] = $this->map['common_member_field_forum_archive'] =
				$this->map['common_member_field_home'] = $this->map['common_member_field_home_archive'] =
				$this->map['common_member_validate'] = $this->map['common_member_verify'] =
				$this->map['common_member_verify_info'] = $this->map['common_member'];
			}
		}
	}

	function connect($serverid = 1) {

		if(empty($this->config) || empty($this->config[$serverid])) {
			$this->halt('config_db_not_found');
		}

		$this->link[$serverid] = $this->_dbconnect(
			$this->config[$serverid]['dbhost'],
			$this->config[$serverid]['dbuser'],
			$this->config[$serverid]['dbpw'],
			$this->config[$serverid]['dbcharset'],
			$this->config[$serverid]['dbname'],
			$this->config[$serverid]['pconnect']
			);
		$this->curlink = $this->link[$serverid];

	}

	function _dbconnect($dbhost, $dbuser, $dbpw, $dbcharset, $dbname, $pconnect, $halt = true) {
              //$this->halt(' '.$dbhost.' '.$dbuser.' '.$dbname, $pconnect);
		if($pconnect) {
			$link = @mysqli_pconnect($dbhost, $dbuser, $dbpw, MYSQL_CLIENT_COMPRESS);
		} else {
			//$link = @mysqli_connect($dbhost, $dbuser, $dbpw, 1, MYSQL_CLIENT_COMPRESS);
                        $link = @mysqli_connect($dbhost, $dbuser, $dbpw, $dbname);
		}
		if(!$link) {
			$halt && $this->halt('notconnect', $this->errno());
		} else {
			$this->curlink = $link;
			if($this->version() > '4.1') {
				$dbcharset = $dbcharset ? $dbcharset : $this->config[1]['dbcharset'];
				$serverset = $dbcharset ? 'character_set_connection='.$dbcharset.', character_set_results='.$dbcharset.', character_set_client=binary' : '';
				$serverset .= $this->version() > '5.0.1' ? ((empty($serverset) ? '' : ',').'sql_mode=\'\'') : '';
				$serverset && mysqli_query($link, "SET $serverset");
			}
			$dbname && @mysqli_select_db($link, $dbname);
		}
		return $link;
	}

	function table_name($tablename) {
                //$tabpre = 'firemail';
                //$this->halt(' '.$tabpre, 111111);
                //$this->halt('tablename:'.$tablename.' tabpre:'.$tabpre.' tablepre:'.$this->tablepre, 111111);
		if(!empty($this->map) && !empty($this->map[$tablename])) {
                        //$this->halt(' 1', 111111);
			$id = $this->map[$tablename];
                        //if(!empty($this->config[$id]['tablepre'])){
	                  // $this->tablepre = $this->config[$id]['tablepre'];
                        //}
			if(!$this->link[$id]) {
				$this->connect($id);
			}
			$this->curlink = $this->link[$id];
		} else {
                        //$this->halt(' 2 '.json_encode($this->config['1']), 111111);
                        //$this->tablepre = $this->config['1']['tablepre'];
			$this->curlink = $this->link[1];
		}
                //var_dump($this->config['1']['tablepre']);
                //var_dump($this->tablepre);
                //var_dump($this->config['1']['tablepre'].$tablename);
                //$this->halt(' '.$this->config['1']['tablepre'].$tablename, 111111);
		//return $this->tablepre.$tablename;
                return $this->config['1']['tablepre'].$tablename;
	}

	function select_db($dbname) {
		return mysqli_select_db($this->curlink, $dbname);
	}

	function fetch_array($query, $result_type = MYSQL_ASSOC) {
		return mysqli_fetch_array($query, $result_type);
	}

	function fetch_first($sql) {
		return $this->fetch_array($this->query($sql));
	}

	function result_first($sql) {
		return $this->result($this->query($sql), 0);
	}

	public function query($sql, $silent = false, $unbuffered = false) {
		if(defined('DISCUZ_DEBUG') && DISCUZ_DEBUG) {
			$starttime = microtime(true);
		}

		if('UNBUFFERED' === $silent) {
			$silent = false;
			$unbuffered = true;
		} elseif('SILENT' === $silent) {
			$silent = true;
			$unbuffered = false;
		}

		//$func = $unbuffered ? 'mysql_unbuffered_query' : 'mysqli_query';
                $func = 'mysqli_query';
                //var_dump($this->curlink);
                
		//if(!($query = $func($sql, $this->curlink))) {
                if(!($query = $func($this->curlink, $sql))) {
			if(in_array($this->errno(), array(2006, 2013)) && substr($silent, 0, 5) != 'RETRY') {
				$this->connect();
				return $this->query($sql, 'RETRY'.$silent);
			}
			if(!$silent) {
				$this->halt($this->error(), $this->errno(), $sql);
			}
		}

		if(defined('DISCUZ_DEBUG') && DISCUZ_DEBUG) {
			$this->sqldebug[] = array($sql, number_format((microtime(true) - $starttime), 6), debug_backtrace(), $this->curlink);
		}

		$this->querynum++;
		return $query;
	}

	function affected_rows() {
		return mysqli_affected_rows($this->curlink);
	}

	function error() {
		return (($this->curlink) ? mysqli_error($this->curlink) : mysqli_error());
	}

	function errno() {
		return intval(($this->curlink) ? mysqli_errno($this->curlink) : mysqli_errno());
	}

	function result($query, $row = 0) {
		$query = @mysqli_result($query, $row);
		return $query;
	}

	function num_rows($query) {
		$query = mysqli_num_rows($query);
		return $query;
	}

	function num_fields($query) {
		return mysqli_num_fields($query);
	}

	function free_result($query) {
		return mysqli_free_result($query);
	}

	function insert_id() {
		return ($id = mysqli_insert_id($this->curlink)) >= 0 ? $id : $this->result($this->query("SELECT last_insert_id()"), 0);
	}

	function fetch_row($query) {
		$query = mysqli_fetch_row($query);
		return $query;
	}

	function fetch_fields($query) {
		return mysqli_fetch_field($query);
	}

	function version() {
		if(empty($this->version)) {
			$this->version = mysqli_get_server_info($this->curlink);
		}
		return $this->version;
	}

	function close() {
		return mysqli_close($this->curlink);
	}

	function halt($message = '', $code = 0, $sql = '') {
		throw new DbException($message, $code, $sql);
	}

}

?>