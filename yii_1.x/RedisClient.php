<?php
/**
 * Usage:
 * config.php:
 *
 * 'rds' => array(
 *            'class'=>'RedisClient',
 *            'masterConfig'=>array(
 *                'host'=>'127.0.0.1',
 *                'port'=>6000,
 *                'password'=>'passwd',
 *                'timeout'=>10,
 *            ),
 *            'slaveConfig'=>array(
 *                'host'=>'127.0.0.1',
 *                'port'=>6001,
 *                'password'=>'passwd',
 *                'timeout'=>10,
 *            ),
 *        ),
 *
 *
 * $key='abc';
 * Yii::app()->rds->master->set($key, 'b'); //or Yii::app()->rds->set($key, 'b');
 * Yii::app()->rds->expireAt($key, time()+86400);
 * echo Yii::app()->rds->slave->ttl($key);
 * echo Yii::app()->rds->slave->get($key);
 * echo Yii::app()->rds->get($key); //default from master
 *
 * Created by PhpStorm.
 * User: mengfanbin
 * Date: 15-3-23
 * Time: 下午3:33
 */

class RedisClient extends CApplicationComponent {

    private $_masterClient;

    private $_slaveClient;

    public $masterConfig;
    public $slaveConfig;

    public $prefix = 'yii:';
    public $timeout = 0;


    public function setMaster($config) {
        $this->_master = $config;
    }

    public function setSlave($config) {
        $this->_slave = $config;
    }

    public function getMaster($reconnect = false) {
        if($this->_masterClient === null || $reconnect) {
            $this->_masterClient = new Redis();
            $this->_masterClient->connect($this->masterConfig['host'],$this->masterConfig['port'],($this->masterConfig['timeout'] ?: 0));
            $this->_masterClient->auth($this->masterConfig['password']);
            $this->_masterClient->setOption(Redis::OPT_PREFIX, $this->prefix);
        }
        return $this->_masterClient;
    }

    public function getSlave($reconnect = false) {
        if($this->_slaveClient === null || $reconnect) {
            $this->_slaveClient = new Redis();
            $this->_slaveClient->connect($this->slaveConfig['host'],$this->slaveConfig['port'],($this->slaveConfig['timeout'] ?: 0));
            $this->_slaveClient->auth($this->slaveConfig['password']);
            $this->_slaveClient->setOption(Redis::OPT_PREFIX, $this->prefix);
        }
        return $this->_slaveClient;
    }




    public function __get($name) {
        $getter='get'.$name;
        if (property_exists($this->getMaster(),$name)) {
            return $this->getMaster()->{$name};
        }
        elseif(method_exists($this->getMaster(),$getter)) {
            return $this->$getter();
        }
        return parent::__get($name);
    }


    public function __set($name,$value)
    {
        $setter='set'.$name;
        if (property_exists($this->getMaster(),$name)) {
            return $this->getMaster()->{$name} = $value;
        }
        elseif(method_exists($this->getMaster(),$setter)) {
            return $this->getMaster()->{$setter}($value);
        }
        return parent::__set($name,$value);
    }


    public function __isset($name)
    {
        $getter='get'.$name;
        if (property_exists($this->getMaster(),$name)) {
            return true;
        }
        elseif (method_exists($this->getMaster(),$getter)) {
            return true;
        }
        return parent::__isset($name);
    }


    public function __unset($name)
    {
        $setter='set'.$name;
        if (property_exists($this->getMaster(),$name)) {
            $this->getMaster()->{$name} = null;
        }
        elseif(method_exists($this,$setter)) {
            $this->$setter(null);
        }
        else {
            parent::__unset($name);
        }
    }

    public function __call($name, $parameters) {
        return call_user_func_array(array($this->getMaster(),$name),$parameters);
    }
}