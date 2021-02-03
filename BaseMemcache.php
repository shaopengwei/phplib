<?php
/**
 * Base_Memcache实现
 * @subpackage  controllers
 * @author      邵鹏伟 <shaopengwei@baidu.com>
 * @version     2020/9/30 4:57 下午
 * @copyright   Copyright (c) 2019 Baidu.com, Inc. All Rights Reserved
 */

class Base_Memcache{
    //声明静态成员变量
    private static $instance = null;

    //Memcache对象
    private $m;

    //Memcache配置信息
    const HOST = 'www.cat.com';
    const PORT = '11211';

    private function __construct() {
        $this->m = new Memcache();
        $this->m->connect(self::HOST,self::PORT);
    }

    //为当前类创建对象
    public static function getInstance(){
        if(!(self::$instance instanceof self)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    /*
     * 添加缓存数据
     * @param string $key 获取数据唯一key
     * @param String||Array $value 缓存数据
     * @param $time memcache生存周期(秒)
     */
    public function setMem($key, $value, $time){
        $this->m->set($key, $value, 0, $time);
    }

    /*
     * 获取缓存数据
     * @param string $key
     * @return
     */
    public function getMem($key){
        return $this->m->get($key);
    }

    /*
     * 删除对应缓存数据
     * @param string $key
     * @return
     */
    public function delMem($key){
        $this->m->delete($key);
    }

    /*
     * 删除所有缓存数据
     */
    public function delAllMem(){
        $this->m->flush();
    }

    /*
     * 获取服务器统计信息
     */
    public function getMemStatus(){
        return $this->m->getStats();
    }

    /*
     * 关闭服务器连接
     */
    public function closeMem(){
        return $this->m->close();
    }
}