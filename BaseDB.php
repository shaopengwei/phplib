<?php
/**
 * Base_DB实现 (单例模式 singleton pattern)
 * @subpackage  controllers
 * @author      邵鹏伟 <shaopengwei@baidu.com>
 * @version     2021/2/3 5:19 下午
 * @copyright   Copyright (c) 2019 Baidu.com, Inc. All Rights Reserved
 */

/**
 * SQL Engine
 *
 * 单例模式实现数据库连接，支持数据库CURD操作，满足SQL语句扩展
 *
 * 使用方法：
 *
 * 1.实例化对象
 * $objTest = Base_Db::getInstance();
 *
 * 2.数据库查询操作
 * $objTest->field(array('id', 'username'))
 *      ->where(array('id' => array('>0', 'and'), 'username' => ' = "zhangsan"'))
 *      ->append(array('order by id asc', 'group by username'))
 *      ->select('tblUser');
 *
 * 3.数据库插入操作
 * $objTest->insert('tblUser', array('username'=>'"zhangsan"', 'age'=>13));
 *
 * 4.数据库更新操作
 * $objTest->where('username = "wangwu"')
 *         ->update('tblUser', ['age'=>11]);
 *
 * 5.数据库删除操作
 * $objTest->where(array('username' => '="zhangsan"'))
 *         ->delete('tblUser');
 *
 * Class Base_Db
 */
class Base_Db{
    /**
     * 数据库连接参数
     */
    const HOSTNAME  = "10.138.26.22";
    const USERNAME  = "root";
    const PASSWORD  = "123456";
    const DBNAME    = "test";
    const PORT      = 8360;
    /**
     * 保存全局实例
     * @var string
     */
    private static $instance;
    /**
     * 查询信息
     */
    public $_where     = '';
    protected $_field  = '*';
    protected $_append = '';
    /**
     * 数据库连接句柄
     */
    private $db;
    /**
     * 私有化构造函数，防止外界实例化对象
     * @codeCoverageIgnore
     * @param      null
     */
    private function __construct() {
        $this->db = new mysqli(self::HOSTNAME, self::USERNAME, self::PASSWORD, self::DBNAME, self::PORT);
        if ($this->db->connect_error) {
            throw new Exception('[Base_DB] Connect ErrorNo:' . $this->db->connect_errno . '. ErrorInfo:' . $this->db->connect_error);
        }
    }
    /**
     * 私有化克隆函数，防止外界克隆对象
     * @param      null
     */
    private function __clone() {}
    /**
     * 单例访问统一入口
     * @param      null
     * @return     $this
     */
    public static function getInstance() {
        if (!(self::$instance instanceof self)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    /**
     * 更换连接数据库
     * @param      string
     * @return     boolean
     */
    public function selectDB($strNewDB) {
        if (empty($strNewDB)) {
            throw new Exception("[Base_DB] selectDB: new db empty!");
        }
        return $this->db->select_db($strNewDB);
    }
    /**
     * 执行数据库SQL语句
     * @codeCoverageIgnore
     * @param      string
     * @return     object
     */
    public function sendQuery($strSql) {
        if (empty($strSql)) {
            throw new Exception("[Base_DB] sendQuery: sql empty!");
        }
        //返回mysqli_result对象
        return $this->db->query($strSql);
    }
    /**
     * 查询数据库中所有表
     * @param      null
     * @return     object
     */
    public function showTables() {
        return $this->sendQuery("show tables");
    }
    /**
     * 查询函数
     * @param      string $tbName 操作的数据表名
     * @return     object 结果集
     */
    public function select($tblName = '') {
        $sql = "select ".trim($this->_field)." from ".$tblName." ".trim($this->_where)." ".trim($this->_append);
        return $this->sendQuery(trim($sql));
    }
    /**
     * 设置查询字段
     * @param      mixed $field 字段数组
     * @return     $this
     */
    public function field($field) {
        if (empty($field)) {
            throw new Exception("[Base_DB] field: empty field!");
        }
        if (is_string($field)) {
            $field = explode(',', $field);
        }
        $nField = array_map(array($this,'_addChar'), $field);
        $this->_field = implode(',', $nField);
        return $this;
    }
    /**
     * 拼接where条件
     * @param      mixed $option 组合条件的二维数组，例：
     * $option = 'field1 = 1 and field2 > 2' 或者
     * $option = array(
     *  'field' => '> 1 and',
     *  'field' => 'IS NOT NULL and',
     *  'field' => array('= 1', 'or'),
     *)
     * @return     $this
     */
    public function where($option) {
        if (empty($option)) {
            throw new Exception("[Base_DB] where: empty option!");
        }
        $this->_where = ' where ';
        if (is_string($option)) {
            $this->injectCheck($option);
            $this->_where .= $option;
        } elseif (is_array($option)) {
            foreach($option as $k=>$v) {
                if (is_array($v)) {
                    $this->injectCheck($v[0]);
                    $condition = ' '.$this->_addChar($k).' '.$v[0].' '.$v[1].' ';
                } else {
                    $this->injectCheck($v);
                    $condition = ' '.$this->_addChar($k).' '.$v.' ';
                }
                $this->_where .= $condition;
            }
        } else {
            throw new Exception("[Base_DB] where: input param error!");
        }
        return $this;
    }
    /**
     * select 附加语句
     * @param      array $option 例: array('group by id', 'order by id', 'limit 5')
     * @return     $this
     */
    public function append($option) {
        if (empty($option)) {
            throw new Exception("[Base_DB] append: empty input");
        }
        foreach ($option as $key => $value) {
            $this->injectCheck($value);
            $this->_append .= $value . " ";
        }
        return $this;
    }
    /**
     * 插入方法
     * @param      string $tblName 操作的数据表名
     * @param      array $data 字段-值的一维数组
     * @return     object
     */
    public function insert($tblName, $data) {
        if (empty($tblName) || empty($data)) {
            throw new Exception("[Base_DB] insert: input params empty!");
        }
        foreach ($data as $key => $value) {
            $this->injectCheck($value);
        }
        $sql = "insert into " . $tblName . "(" . implode(',', array_map(array($this,'_addChar'), array_keys($data)))
            . ") values(" . implode(',', array_values($data)). ")";
        return $this->sendQuery($sql);
    }
    /**
     * 返回最后一条插入语句产生的自增ID
     * @param      null
     * @return     int 自增ID
     */
    public function getInsertId() {
        return $this->db->insert_id;
    }
    /**
     * 更新函数
     * @param      string $tblName 操作的数据表名
     * @param      array $data 参数数组
     * @return     object 受影响的行数
     */
    public function update($tblName, $data) {
        if (empty($tblName) || empty($data)) {
            throw new Exception("[Base_DB] update: input param empty!");
        }
        $arrValue = '';
        foreach ($data as $k=>$v) {
            $this->injectCheck($v);
            $arrValue[] = $this->_addChar($k).'='.$v;
        }
        $valStr = implode(",", $arrValue);
        $sql = "update " . trim($tblName) . " set " . trim($valStr) . " " . trim($this->_where);
        return $this->sendQuery($sql);
    }
    /**
     * 删除函数
     * @param      string $tblName 操作的数据表名
     * @return     mixed
     */
    public function delete($tblName) {
        //安全考虑,阻止全表删除
        if (!trim($this->_where)) {
            throw new Exception("[Base_DB] delete: where is empty!");
        }
        $sql = "delete from " . $tblName . " " . $this->_where;
        return $this->sendQuery($sql);
    }
    /**
     * 关闭数据库连接
     * @param      null
     * @return     boolean
     */
    public function close() {
        return $this->db->close();
    }
    /**
     * 字段和表名添加 `符号
     * 保证指令中使用关键字不出错 针对mysql
     * @param      string $value
     * @return     string
     * @codeCoverageIgnore
     */
    protected function _addChar($value) {
        if ('*'==$value || false!==strpos($value,'(') || false!==strpos($value,'.') || false!==strpos($value,'`')) {
            //如果包含* 或者 使用了sql方法 则不作处理
            return $value;
        } elseif (false === strpos($value,'`') ) {
            $value = '`'.trim($value).'`';
        }
        return $value;
    }
    /**
     * 检测用户输入数据是否SQL注入
     * @param      string
     * @return     null
     * @codeCoverageIgnore
     */
    public function injectCheck(&$strParam) {
        $mixRet = mb_eregi('select|insert|update|delete|\'|\/\*|\*|\.\.\/|\.\/|\#|union|into|load_file|outfile|where', $strParam);
        if($mixRet > 0){
            $strParam = $this->injectConvert($strParam);
        }
    }
    /**
     * SQL注入处理
     * @param      mixed
     * @return     string
     * @codeCoverageIgnore
     */
    public function injectConvert($mixParam) {
        if (get_magic_quotes_gpc()) {
            $mixParam = stripslashes($mixParam);
        }
        if(!is_numeric($mixParam)) {
            $mixParam = "'" . mysqli_real_escape_string($this->db, $mixParam) . "'";
        }
        return $mixParam;
    }
}