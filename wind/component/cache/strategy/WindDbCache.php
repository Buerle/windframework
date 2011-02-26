<?php
/**
 * @author Qian Su <aoxue.1988.su.qian@163.com> 2010-12-16
 * @link http://www.phpwind.com
 * @copyright Copyright &copy; 2003-2110 phpwind.com
 * @license 
 */
L::import('WIND:component.cache.strategy.AbstractWindCache');
/**
 * the last known user to change this file in the repository  <$LastChangedBy$>
 * @author Qian Su <aoxue.1988.su.qian@163.com>
 * @version $Id$ 
 * @package 
 */
class WindDbCache extends AbstractWindCache {
	/**
	 * @var WindConnectionManager 分布式管理
	 */
	protected $dbHandler;
	/**
	 * @var string 缓存表
	 */
	protected $table = 'pw_cache';
	
	/**
	 * @var string 缓存表的键字段
	 */
	protected $keyField = 'key';
	/**
	 * @var string 缓存表的值字段
	 */
	protected $valueField = 'value';
	/**
	 * @var string 缓存表过期时间字段
	 */
	protected $expireField = 'expire';
	
	/**
	 * @var boolean 数据过期策略
	 */
	protected $expirestrage = true;
	
	const CACHETABLE = 'cachetable';
	const NAME = 'name';
	const KEY = 'key';
	const VALUE = 'value';
	const EXPIRE = 'expire';
	const FIELD = 'field';
	const STRAGE = 'expirestrage';
	
	public function __construct(WindConnectionManager $dbHandler = null) {
		$dbHandler && $this->setDbHandler($dbHandler);
	}
	
	
	/* 
	 * @see wind/component/cache/base/IWindCache#set()
	 */
	public function set($key, $value, $expires = 0, IWindCacheDependency $denpendency = null) {
		$data = $this->getSlaveConnection()->getSqlBuilder()->from($this->table)->field($this->expireField)->where($this->keyField.' = :key ', array(':key' => $this->buildSecurityKey($key)))->select()->getRow();
		if ($data) {
			return !$this->expirestrage && '0' === $data[$this->expireField] ? null : $this->update($key, $value, $expires, $denpendency);
		} else {
			return $this->store($key, $value, $expires, $denpendency);
		}
		return true;
	}
	
	/* 
	 * @see wind/component/cache/base/IWindCache#fetch()
	 */
	public function get($key) {
		if($this->expirestrage){
			$data = $this->getSlaveConnection()->getSqlBuilder()->from($this->table)->field($this->valueField)->where($this->keyField.' = :key ', array(':key' => $this->buildSecurityKey($key)))->select()->getRow();
		}else{
			$data = $this->getSlaveConnection()->getSqlBuilder()->from($this->table)->field($this->valueField)->where($this->expireField.' != 0 AND '.$this->keyField.' = :key ', array(':key' => $this->buildSecurityKey($key)))->select()->getRow();
		}
		return $this->getDataFromMeta($key, unserialize($data[$this->valueField]));
	}
	
	/* 
	 * @see wind/component/cache/base/IWindCache#batchFetch()
	 */
	public function batchGet(array $keys) {
		foreach ($keys as $key => $value) {
			$keys[$key] = $this->buildSecurityKey($value);
		}
		if(true === $this->expirestrage){
			$data = $this->getSlaveConnection()->getSqlBuilder()->from($this->table)->field($this->valueField, $this->keyField)->where($this->keyField.' in ( :key ) ', array(':key' => $keys))->select()->getAllRow();
		}else{
			$data = $this->getSlaveConnection()->getSqlBuilder()->from($this->table)->field($this->valueField, $this->keyField)->where($this->expireField.' != 0 AND '.$this->keyField.' in ( :key ) ', array(':key' => $keys))->select()->getAllRow();
		}
		$result = $changed = array();
		foreach ($data as $_data) {
			$_data = unserialize($_data[$this->valueField]);
			if (isset($_data[self::DEPENDENCY]) && $_data[self::DEPENDENCY] instanceof IWindCacheDependency) {
				if ($_data[self::DEPENDENCY]->hasChanged()) {
					$changed[] = $_data[$this->keyField];
				} else {
					$result[$_data[$this->keyField]] = $_data[self::DATA];
				}
			}
		}
		$changed && $this->batchDelete($changed);
		return $result;
	}
	
	/* 
	 * @see wind/component/cache/base/IWindCache#delete()
	 */
	public function delete($key) {
		if($this->expirestrage){
			 $this->getMasterConnection()->getSqlBuilder()->from($this->table)->where($this->keyField.' = :key ', array(':key' => $this->buildSecurityKey($key)))->delete();
		}else{
			 $this->getMasterConnection()->getSqlBuilder()->from($this->table)->set($this->expireField.' = 0')->where($this->keyField.' = :key ', array(':key' => $this->buildSecurityKey($key)))->update();
		}
	}
	
	/* 
	 * @see wind/component/cache/base/IWindCache#batchDelete()
	 */
	public function batchDelete(array $keys) {
		foreach ($keys as $key => $value) {
			$keys[$key] = $this->buildSecurityKey($value);
		}
		if($this->expirestrage){
			 $this->getMasterConnection()->getSqlBuilder()->from($this->table)->where($this->keyField.' in (:key) ', array(':key' => $keys))->delete();
		}else{
			 $this->getMasterConnection()->getSqlBuilder()->from($this->table)->set($this->expireField.' = 0')->where($this->keyField.' in (:key) ', array(':key' => $keys))->update();
		}
	}
	
	/* 
	 * @see wind/component/cache/base/IWindCache#flush()
	 */
	public function flush() {
		if($this->expirestrage){
			 $this->getMasterConnection()->getSqlBuilder()->from($this->table)->delete();
		}else{
			 $this->getMasterConnection()->getSqlBuilder()->from($this->table)->set($this->expireField.' = 0')->update();
		}
	}
	
	/**
	 * 删除过期数据
	 */
	public function deleteExpiredCache() {
		if($this->expirestrage){
		 	$this->getMasterConnection()->getSqlBuilder()->from($this->table)->where($this->expireField.' !=0 AND '.$this->expireField.' < :expires', array(':expires' => time()))->delete();
		}else{
			$this->getMasterConnection()->getSqlBuilder()->from($this->table)->set($this->expireField.' = 0')->where($this->expireField.' < :expires', array(':expires' => time()))->update();
		}
	}
	
	public function setDbHandler(WindConnectionManager $dbHandler){
		$this->dbHandler = $dbHandler;
	}
	
	/* 
	 * @see wind/core/WindComponentModule#setConfig()
	 */
	public function setConfig($config) {
		parent::setConfig($config);
		$_config = is_object($config) ? $config->getConfig() : $config;
		$tableConfig = $_config[self::CACHETABLE];
		$this->expirestrage = 'true' === $tableConfig[self::STRAGE];
		$this->table = $tableConfig[self::NAME];
		$field = $tableConfig[self::FIELD];
		$this->keyField = $field[self::KEY];
		$this->valueField = $field[self::VALUE];
		$this->expireField = $field[self::EXPIRE];
	}
	
	/**
	 * 存储数据
	 * @param string $key
	 * @param string $value
	 * @param int $expires
	 * @param IWindCacheDependency $denpendency
	 * @return boolean
	 */
	protected function store($key, $value, $expires = 0, IWindCacheDependency $denpendency = null) {
		$data = addslashes($this->storeData($value, $expires, $denpendency));
		if($expires){
			$expires += time();
		}
		return $this->getMasterConnection()->getSqlBuilder()->from($this->table)->field($this->keyField, $this->valueField, $this->expireField)->data($this->buildSecurityKey($key), $data, $expires)->insert();
	}
	
	/**
	 * 更新数据
	 * @param string $key
	 * @param int $value
	 * @param int $expires
	 * @param IWindCacheDependency $denpendency
	 * @return boolean
	 */
	protected function update($key, $value, $expires = 0, IWindCacheDependency $denpendency = null) {
		$data = $this->storeData($value, $expires, $denpendency);
		if($expires){
			$expires += time();
		}
		return $this->getMasterConnection()->getSqlBuilder()->from($this->table)->set($this->valueField.' = :value,'.$this->expireField.' = :expires', array(':value' => $data, ':expires' => $expires))->where($this->keyField.'=:key', array(':key' => $this->buildSecurityKey($key)))->update();
	}
	/**
	 * 获取写缓存的数据库
	 * @return AbstractWindDbAdapter
	 */
	private function getMasterConnection() {
		return $this->dbHandler->getMasterConnection();
	}
	
	/**
	 *  获取读缓存的数据库
	 * @return AbstractWindDbAdapter
	 */
	private function getSlaveConnection() {
		return $this->dbHandler->getSlaveConnection();
	}
	
	public function __destruct() {
		if( null !== $this->dbHandler){
			$this->deleteExpiredCache();
		}
	}

}