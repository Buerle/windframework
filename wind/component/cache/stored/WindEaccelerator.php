<?php
/**
 * the last known user to change this file in the repository  <$LastChangedBy$>
 * @author Qian Su <aoxue.1988.su.qian@163.com>
 * @version $Id$ 
 * @package 
 * tags
 */
L::import('WIND:component.cache.base.IWindCache');
/**
 * Eaccelerator是一款php加速器、优化器、编码器及动态内容缓存。
 * WindEaccelerator实现Eaccelerator动态内容缓存功能。
 * the last known user to change this file in the repository  <$LastChangedBy$>
 * @author Qian Su <aoxue.1988.su.qian@163.com>
 * @version $Id$ 
 * @package 
 */
class WindEaccelerator extends WindComponentModule implements IWindCache {
	/**
	 * @var string 安全code
	 */
	protected $securityCode = '';
	/* 
	 * @see wind/component/cache/base/IWindCache#add()
	 */
	public function add($key, $value, $expires = 0, IWindCacheDependency $denpendency = null) {
		$cacheData = $this->fetch($key);
		if (false === empty($cacheData)) {
			$this->error("The cache already exists");
		}
		return eaccelerator_put($this->buildSecurityKey($key), $this->storeData($value, $expires, $denpendency), $expires);
	}
	
	/* 
	 * @see wind/component/cache/base/IWindCache#set()
	 */
	public function set($key, $value, $expires = 0, IWindCacheDependency $denpendency = null) {
		return eaccelerator_put($this->buildSecurityKey($key), $this->storeData($value, $expires, $denpendency), $expires);
	}
	
	/* 
	 * @see wind/component/cache/base/IWindCache#replace()
	 */
	public function replace($key, $value, $expires = 0, IWindCacheDependency $denpendency = null) {
		$cacheData = $this->fetch($key);
		if (empty($cacheData)) {
			$this->error("The cache does not exist");
		}
		return eaccelerator_put($this->buildSecurityKey($key), $this->storeData($value, $expires, $denpendency), $expires);
	}
	
	/* 
	 * @see wind/component/cache/base/IWindCache#fetch()
	 */
	public function fetch($key) {
		$key = $this->buildSecurityKey($key);
		$data = unserialize(eaccelerator_get($key));
		if (empty($data) || !is_array($data)) {
			return $data;
		}
		if (isset($data[self::DEPENDENCY]) && isset($data[self::DEPENDENCYCLASS])) {
			L::import('Wind:component.cache.dependency.' . $data[self::DEPENDENCYCLASS]);
			$dependency = unserialize($data[self::DEPENDENCY]); /* @var $dependency IWindCacheDependency*/
			if (($dependency instanceof IWindCacheDependency) && $dependency->hasChanged()) {
				$this->delete($key);
				return null;
			}
		}
		return isset($data[self::DATA]) ? $data[self::DATA] : null;
	}
	
	/* 
	 * @see wind/component/cache/base/IWindCache#batchFetch()
	 */
	public function batchFetch(array $keys) {
		$data = array();
		foreach ($keys as $key) {
			if ('' != ($value = $this->fetch($key))) {
				$data[$key] = $value;
			}
		}
		return $data;
	}
	
	/* 
	 * @see wind/component/cache/base/IWindCache#delete()
	 */
	public function delete($key) {
		return eaccelerator_rm($this->buildSecurityKey($key));
	}
	
	/* 
	 * @see wind/component/cache/base/IWindCache#batchDelete()
	 */
	public function batchDelete(array $keys) {
		foreach ($keys as $key) {
			$this->delete($key);
		}
		return true;
	}
	
	/* 
	 * @see wind/component/cache/base/IWindCache#flush()
	 */
	public function flush() {
		eaccelerator_gc();
		$cacheKeys = eaccelerator_list_keys();
		foreach ($cacheKeys as $key) {
			$this->delete(substr($key['name'], 1));
		}
	}
	/**
	 * 错误处理
	 * @param string $message
	 * @param int $type
	 */
	public function error($message, $type = E_USER_ERROR) {
		trigger_error($message, $type);
	}
	/* 
	 * @see wind/core/WindComponentModule#setConfig()
	 */
	public function setConfig($config) {
		parent::setConfig($config);
		$config = $config->getConfig();
		if (isset($config[self::SECURITY])) {
			$this->securityCode = $config[self::SECURITY];
		}
	}
	
	/* 
	 * 获取存储的数据
	 * @see wind/component/cache/stored/IWindCache#set()
	 * @return string
	 */
	protected function storeData($value, $expires = 0, IWindCacheDependency $denpendency = null) {
		$data = array(self::DATA => $value, self::EXPIRES => $expires, self::STORETIME => time());
		if ($denpendency && (($denpendency instanceof IWindCacheDependency))) {
			$denpendency->injectDependent($this);
			$data[self::DEPENDENCY] = serialize($denpendency);
			$data[self::DEPENDENCYCLASS] = get_class($denpendency);
		}
		return serialize($data);
	}
	
	/**
	 * 生成安全的key
	 * @param string $key
	 * @return string
	 */
	private function buildSecurityKey($key) {
		return  $key . '_' . substr(sha1($key . $this->securityCode), 0, 5);
	}

}