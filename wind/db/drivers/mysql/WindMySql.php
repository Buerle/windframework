<?php
/**
 * @author Qian Su <aoxue.1988.su.qian@163.com> 2010-11-11
 * @link http://www.phpwind.com
 * @copyright Copyright &copy; 2003-2110 phpwind.com
 * @license 
 */
Wind::import('WIND:component.db.drivers.AbstractWindDbAdapter');
/**
 * the last known user to change this file in the repository  <$LastChangedBy$>
 * @author Qian Su <aoxue.1988.su.qian@163.com>
 * @version $Id$ 
 * @package 
 */
class WindMySql extends AbstractWindDbAdapter {
	/* (non-PHPdoc)
	 * @see wind/base/WDbAdapter#connect()
	 */
	protected function connect() {
		if (!is_resource($this->connection) || $this->config[IWindDbConfig::FORCE]) {
			$host = isset($this->config[IWindDbConfig::PORT]) ? $this->config[IWindDbConfig::HOST] . ':' . $this->config[IWindDbConfig::PORT] : $this->config[IWindDbConfig::HOST];
			$user = $this->config[IWindDbConfig::USER];
			$pass = $this->config[IWindDbConfig::PASS];
			$pconnect = ('true' === $this->config[IWindDbConfig::PCONNECT] );
			$this->connection = $pconnect ? mysql_pconnect($host, $user, $pass) : mysql_connect($host, $user, $pass, $this->config[IWindDbConfig::FORCE]);
			$this->changeDB($this->config[IWindDbConfig::NAME]);
			$this->setCharset($this->config[IWindDbConfig::CHARSET]);
		} else {
			$this->changeDB($this->config[IWindDbConfig::NAME]);
		}
		return $this->connection;
	}
	
	/* (non-PHPdoc)
	 * @see wind/base/WDbAdapter#query()
	 */
	public function query($sql) {
		$this->query = mysql_query($sql, $this->connection);
		$this->error($sql);
		return true;
	}
	
	/* (non-PHPdoc)
	 * @see wind/component/db/base/WindDbAdapter#getAffectedRows()
	 */
	public function getAffectedRows() {
		return mysql_affected_rows($this->connection);
	}
	
	/* (non-PHPdoc)
	 * @see wind/component/db/base/WindDbAdapter#getLastInsertId()
	 */
	public function getLastInsertId() {
		return mysql_insert_id($this->connection);
	}
	
	/* (non-PHPdoc)
	 * @see wind/component/db/base/WindDbAdapter#getMetaTables()
	 */
	public function getMetaTables($schema = '') {
		$schema = $schema ? $schema : $this->getSchema();
		if (empty($schema)) {
			throw new WindSqlException('', WindSqlException::DB_EMPTY);
		}
		$this->query('SHOW TABLES FROM ' . $schema);
		return $this->getAllRow(IWindDbConfig::ASSOC);
	}
	
	/* (non-PHPdoc)
	 * @see wind/component/db/base/WindDbAdapter#getMetaColumns()
	 */
	public function getMetaColumns($table) {
		if (empty($table)) {
			throw new WindSqlException('', WindSqlException::DB_TABLE_EMPTY);
		}
		$this->query('SHOW COLUMNS FROM ' . $table);
		return $this->getAllRow(IWindDbConfig::ASSOC);
	}
	
	/* (non-PHPdoc)
	 * @see wind/base/WDbAdapter#getAllRow()
	 */
	public function getAllRow($resultIndex = null,$fetch_type = IWindDbConfig::ASSOC) {
		if (!is_resource($this->query)) {
			throw new WindSqlException('', WindSqlException::DB_QUERY_LINK_EMPTY);
		}
		if (!in_array($fetch_type, array(1, 2, 3))) {
			throw new WindSqlException('', WindSqlException::DB_QUERY_FETCH_ERROR);
		}
		$result = array();
		while (false !== ($record = mysql_fetch_array($this->query, $fetch_type))) {
			if($resultIndex && isset($record[$resultIndex])){
				$result[$record[$resultIndex]] = $record;
			}else{
				$result[] = $record;
			}
		}
		return $result;
	}
	
	/* (non-PHPdoc)
	 * @see wind/component/db/base/WindDbAdapter#getRow()
	 */
	public function getRow($fetch_type = IWindDbConfig::ASSOC) {
		return mysql_fetch_array($this->query, $fetch_type);
	}
	
	/* (non-PHPdoc)
	 * @see wind/component/db/base/WindDbAdapter#beginTrans()
	 */
	public function beginTrans() {
		if ($this->transCounter == 0) {
			$this->query('START TRANSACTION');
		} elseif ($this->transCounter && $this->enableSavePoint) {
			$savepoint = 'savepoint_' . $this->transCounter;
			$this->query("SAVEPOINT `{$savepoint}`");
			array_push($this->savepoint, $savepoint);
		}
		++$this->transCounter;
		return true;
	}
	
	/**
	 *@see wind/component/db/base/WindDbAdapter#commitTrans() 
	 */
	public function commitTrans() {
		if ($this->transCounter <= 0) {
			throw new WindSqlException('', WindSqlException::DB_QUERY_TRAN_BEGIN);
		}
		--$this->transCounter;
		if ($this->transCounter == 0) {
			if ($this->last_errstr) {
				$this->query('ROLLBACK');
			} else {
				$this->query('COMMIT');
			}
		} elseif ($this->enableSavePoint) {
			$savepoint = array_pop($this->savepoint);
			if ($this->last_errstr) {
				$this->query("ROLLBACK TO SAVEPOINT `{$savepoint}`");
			}
		}
	}
	/* (non-PHPdoc)
	 * @see wind/base/WDbAdapter#close()
	 */
	public function close() {
		if (is_resource($this->connection)) {
			mysql_close($this->connection);
		}
	}
	/* (non-PHPdoc)
	 * @see wind/base/WDbAdapter#dispose()
	 */
	public function dispose() {
		$this->close($this->connection);
		$this->connection = null;
		$this->query = null;
	}
	/**
	 * 取得mysql版本号
	 * @param string|int|resource $key 数据库连接标识
	 * @return string
	 */
	public function getVersion() {
		return mysql_get_server_info($this->connection);
	}
	/**
	 * @param string $charset 字符集
	 * @param string | int $key 数据库连接标识
	 * @return boolean
	 */
	public function setCharset($charset) {
		$version = (int) substr($this->getVersion(), 0, 1);
		if ($version > 4) {
			$this->query("SET NAMES '" . $charset . "'");
		}
		return true;
	}
	
	/**
	 * 切换数据库
	 * @see wind/base/WDbAdapter#changeDB()
	 * @param string $databse 要切换的数据库
	 * @param string|int|resource $key 数据库连接标识
	 * @return boolean
	 */
	public function changeDB($database) {
		return mysql_select_db($database, $this->connection);
	}
	
	/* (non-PHPdoc)
	 * @see wind/base/WDbAdapter#error()
	 */
	protected function error($sql) {
		$this->last_errstr = mysql_error();
		$this->last_errcode = mysql_errno();
		$this->last_sql = $sql;
		if ($this->last_errstr || $this->last_errcode) {
			$errInfo = 'This sql statement error has occurred:'.$this->last_sql.'.<br/>';
			$errInfo .= 'Error Message:'.$this->last_errstr.'.<br/>';
			throw new WindSqlException($errInfo, $this->last_errcode);
		}
		return true;
	}

}