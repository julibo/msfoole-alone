<?php
// +----------------------------------------------------------------------
// | msfoole [ 基于swoole4的高性能API服务框架 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2018 http://julibo.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: carson <yuzhanwei@aliyun.com>
// +----------------------------------------------------------------------

namespace Julibo\Msfoole\Mysqli;

use Julibo\Msfoole\Spl\SplString;

class TpDb
{
    /**
     * 先创建Mysqli的实例
     *
     * @var Mysqli
     */
    private $db;
    /**
     * 对象的表名。默认情况下将使用类名
     *
     * @var string
     */
    protected $dbTable;
    /**
     * 数据库前缀
     * @var string
     */
    protected $prefix = '';
    /**
     * 不带前缀的表名
     * @var string
     */
    protected $tableName = '';
    /**
     * 输出的字段
     * @var array
     */
    protected $fields = [];
    /**
     * 条数或者开始和结束
     * @var array | int
     */
    protected $limit;
    /**
     * 是否使用where调用
     * @var array
     */
    protected $isWhere = false;
    /**
     * 保存对象数据的数组
     *
     * @var array
     */
    public $data;
    /**
     * 对象的主键。'id'是默认值。
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * @param string $name
     * @return $this
     */
    protected function name( string $name )
    {
        $splString     = new SplString( $name );
        $tableName          = $splString->snake( '_' )->__toString();
        $this->tableName = $tableName;
        $this->dbTable = $this->prefix.$tableName;
        return $this;
    }
    /**
     * 带前缀
     * @return string
     */
    public function getDbTable():string {
        return $this->dbTable;
    }
    /**
     * 不带前缀
     * @return string
     */
    public function getTableName():string {
        return $this->tableName;
    }
    /**
     * @param $db
     * @return $this|null
     */
    public function setDb( $db )
    {
        if( $db instanceof Mysqli ){
            $this->db = $db;
            return $this;
        } else{
            return null;
        }
    }
    public function getDb() : Mysqli
    {
        return $this->db;
    }

    /**
     * @param $objectNames
     * @param string|null $joinStr
     * @param string $joinType
     * @return $this
     * @throws Exception\JoinFail
     */
    protected function join( $objectNames, string $joinStr = null, string $joinType = 'LEFT' )
    {
        if( is_array( $objectNames ) ){
            foreach( $objectNames as $join ){
                $this->_join( ...$join );
            }
        } else{
            $this->_join( $objectNames, $joinStr, $joinType );
        }
        return $this;
    }

    /**
     * @param string $objectName
     * @param string $joinStr
     * @param string $joinType
     * @return $this
     * @throws Exception\JoinFail
     */
    protected function _join( string $objectName, string $joinStr, string $joinType = 'LEFT' )
    {
        $splString = new SplString( $objectName );
        $name      = $splString->snake()->__toString();
        $tableName = $this->prefix.$name." AS `{$name}`";
        $this->db->join( $tableName, $joinStr, $joinType );
        return $this;
    }
    /**
     * @param array | string $field
     * @return $this
     */
    protected function field( $field )
    {
        $this->fields = $field;
        return $this;
    }
    /**
     * @param array | int eg : $limit [0,10] ， 1
     * @return $this
     */
    protected function limit( $limit )
    {
        $this->limit = $limit;
        return $this;
    }
    /**
     * @param array $pageInfo eg : [1,10]
     * @return $this
     */
    protected function page( array $pageInfo )
    {
        $page = $pageInfo[0] - 1;
        $rows = $pageInfo[1];
        $this->limit( [$page, $rows] );
        return $this;
    }

    /**
     * @param null $limit
     * @param null $fields
     * @return Mysqli|mixed|null
     * @throws Exception\ConnectFail
     * @throws Exception\PrepareQueryFail
     * @throws \Throwable
     */
    private function get( $limit = null, $fields = null )
    {
        $results = $this->db->get( $this->dbTable, $limit, $fields );
        if( count( $results ) === 0 ){
            return null;
        }
        return $results;
    }

    /**
     * @return array
     * @throws Exception\ConnectFail
     * @throws Exception\PrepareQueryFail
     * @throws \Throwable
     */
    protected function find()
    {
        $list = $this->get( 1, $this->fields );
        return isset( $list[0] ) ? $list[0] : [];
    }

    /**
     * @return Mysqli|mixed|null
     * @throws Exception\ConnectFail
     * @throws Exception\PrepareQueryFail
     * @throws \Throwable
     */
    protected function select()
    {
        return $this->get( $this->limit, $this->fields );
    }
    /**
     * @param   array | string $whereProps
     * @param string           $whereValue
     * @param string           $operator
     * @param string           $cond
     * @return $this
     */
    protected function where( $whereProps, $whereValue = 'DBNULL', $operator = '=', $cond = 'AND' )
    {
        $this->isWhere = true;
        if( is_array( $whereProps ) ){
            foreach( $whereProps as $field => $value ){
                if( is_array($value) && key( $value ) === 0 ){
                    // 用于支持['in',[123,232,32,3,4]]格式
                    $this->getDb()->where( $field, [$value[0] => $value[1]] );
                } else{
                    // 用于支持['in'=>[12,23,23]]格式
                    $this->getDb()->where( $field, $value );
                }
            }
        } else{
            $this->getDb()->where( $whereProps, $whereValue, $operator, $cond );
        }
        return $this;
    }

    /**
     * @return array|int|null
     * @throws Exception\ConnectFail
     * @throws Exception\PrepareQueryFail
     * @throws \Throwable
     */
    protected function count()
    {
        $res = $this->db->getValue( $this->dbTable, "count(*)" );
        return $res ?? 0;
    }

    /**
     * @param array $data
     * @return bool|int
     * @throws Exception\ConnectFail
     * @throws Exception\PrepareQueryFail
     * @throws \Throwable
     */
    protected function insert( $data = [] )
    {
        return $this->getDb()->insert( $this->dbTable, $data );
    }

    /**
     * @param array $data
     * @return bool|mixed
     * @throws Exception\ConnectFail
     * @throws Exception\PrepareQueryFail
     * @throws \Throwable
     */
    protected function update( $data = [] )
    {
        if( $this->isWhere === true ){
        } else if( empty ( $this->data[$this->primaryKey] ) ){
            return false;
        } else{
            $this->getDb()->where( $this->primaryKey, $this->data[$this->primaryKey] );
        }
        $this->isWhere = false;
        return $this->getDb()->update( $this->dbTable, $data );
    }

    /**
     * @return bool|null
     * @throws Exception\ConnectFail
     * @throws Exception\PrepareQueryFail
     * @throws \Throwable
     */
    protected function delete()
    {
        if( $this->isWhere === true ){
        } else if( empty ( $this->data[$this->primaryKey] ) ){
            return false;
        } else{
            $this->getDb()->where( $this->primaryKey, $this->data[$this->primaryKey] );
        }
        $this->isWhere = false;
        return $this->getDb()->delete( $this->dbTable );
    }

    /**
     * @param string $orderByField
     * @param string $orderByDirection
     * @param null $customFieldsOrRegExp
     * @return $this
     * @throws Exception\OrderByFail
     */
    protected function order( string $orderByField, string $orderByDirection = "DESC", $customFieldsOrRegExp = null )
    {
        // 替换多个空格为单个空格
        $orderByField = preg_replace( '#\s+#', ' ', trim($orderByField) );
        // 如果是 "create_time desc,time asc"
        if( strstr( $orderByField, ',' ) ){
            $orders = explode( ',', $orderByField );
            foreach( $orders as $order ){
                // 如果是存在空格，执行orderBy("create_time","DESC")
                if( strstr( $order, ' ' ) ){
                    $split = explode( ' ', $order );
                    $this->getDb()->orderBy( $split[0], $split[1] );
                } else{
                    // 可以执行，如：RAND()
                    $this->getDb()->orderBy( $order, $orderByDirection, $customFieldsOrRegExp );
                }
            }
        }else{
            // 如果是存在空格，执行orderBy("create_time","DESC")
            if( strstr( $orderByField, ' ' ) ){
                $split = explode( ' ', $orderByField );
                $this->getDb()->orderBy( $split[0], $split[1] );
            } else{
                // 可以执行，如：RAND()
                $this->getDb()->orderBy( $orderByField, $orderByDirection, $customFieldsOrRegExp );
            }
        }
        return $this;
    }

    /**
     * @param string $name
     * @return array
     * @throws Exception\ConnectFail
     * @throws Exception\PrepareQueryFail
     * @throws \Throwable
     */
    protected function column( string $name )
    {
        return $this->getDb()->getColumn( $this->dbTable, $name );
    }

    /**
     * @param string $name
     * @return array|null
     * @throws Exception\ConnectFail
     * @throws Exception\PrepareQueryFail
     * @throws \Throwable
     */
    protected function value( string $name )
    {
        return $this->getDb()->getValue( $this->dbTable, $name );
    }
    /**
     * @param string $groupByField
     * @return $this
     */
    protected function group( string $groupByField )
    {
        $this->getDb()->groupBy( $groupByField );
        return $this;
    }
    /**
     * 捕获对未定义方法的调用。
     * 提供对类的私有函数和本机公共mysqlidb函数的神奇访问
     *
     * @param string $method
     * @param mixed  $arg
     *
     * @return mixed
     */
    public function __call( $method, $arg )
    {
        if( method_exists( $this, $method ) ){
            return call_user_func_array( [$this, $method], $arg );
        }
        call_user_func_array( [$this->db, $method], $arg );
        return $this;
    }
    /**
     * 捕获对未定义静态方法的调用
     * 透明地创建DbObject类来提供平滑的API，如name::get() name::orderBy()->get()
     * @param $method
     * @param $arg
     * @return DbObject|mixed
     */
    public static function __callStatic( $method, $arg )
    {
        $obj    = new static;
        $result = call_user_func_array( [$obj, $method], $arg );
        if( method_exists( $obj, $method ) ){
            return $result;
        }
        return $obj;
    }
}
