<?php
/**
 * Created by solaa.
 * User: solaa
 * Date: 15/3/25 上午11:14
 */

class Multi_DB_Model extends CI_Model{

    public $other_db = FALSE;
    protected $table = '';

    protected $primary = '';

    //对象数据
    protected $data = array();

    //链操作
    private $methods = array('from', 'select', 'select_max', 'select_min', 'select_avg', 'select_sum', 'join', 'where', 'or_where', 'where_in', 'or_where_in', 'where_not_in', 'or_where_not_in', 'like', 'or_like', 'not_like', 'or_not_like', 'group_by', 'distinct', 'having', 'or_having', 'order_by', 'limit');

    public function __construct()
    {
        parent::__construct();
        if( !$this->table ){
            $this->setTable('');
        }

        if( $this->other_db===FALSE ){
            $this->other_db = $this->db;
        }else{
            $this->primary = $this->other_db->primary($this->table);
            $this->fields = $this->get_fields();
        }
        
    }

    public function setTable($table_name='')
    {
        if( !$table_name ){
            if (get_class($this) != 'Multi_DB_Model') {
                if (empty($this->table)) {
                    $this->table = strtolower(substr(get_class($this), 0, -6));
                }
            }
        }else{
            $this->table = $table_name;
        }

        return $this;
    }

    public function config_db($config_name)
    {
        $this->other_db = $this->load->database($config_name, TRUE);
        return $this;
    }

    /**
     * 魔术方法实现链式方法
     */
    public function __call($method, $args)
    {
        if (in_array($method, $this->methods, true)) {
            call_user_func_array(array($this->other_db, $method), $args);
            return $this;
        }
    }

    protected function _parse_options($options)
    {
        if (!empty($options)) {
            foreach ($options as $key => $val) {
                call_user_func_array(array($this->other_db, $key), array($val));
            }
        }
    }

    /**
     * 数据过滤
     */
    protected function _facade($data) {
        // 检查非数据字段
        if(!empty($this->fields)) {
            foreach ($data as $key=>$val){
                if(!in_array($key,$this->fields,true)){
                    unset($data[$key]);
                }
            }
        }
        return $data;
    }

    /**
     * 统计行数
     */
    public function count($options = array())
    {
        $this->_parse_options($options);
        return $this->other_db->from($this->table)->count_all_results();
    }

    /**
     * 查询第一行记录
     */
    public function find($options = array())
    {
        if (is_string($options) || is_numeric($options)) {
            if (strpos($options, ',')) {
                $this->other_db->where_in($this->primary, explode(',', $options));
            } else {
                $this->other_db->where(array($this->primary => $options));
            }
        } else {
            $this->_parse_options($options);
        }
        return $this->other_db->get($this->table)->row_array();
    }

    /**
     * 查询记录
     */
    public function find_all($options = array())
    {
        $this->_parse_options($options);
        return $this->other_db->get($this->table)->result_array();
    }

    /**
     * 查询单个字段值
     */
    public function get_field($field, $options = array())
    {
        $row = $this->find($options);
        if ($row) {
            return element($field, $row);
        } else {
            return NULL;
        }


    }

    /**
     *  添加数据
     */
    public function add($data = array())
    {
        if (empty($data)) {
            // 没有传递数据，获取当前数据对象的值
            if (!empty($this->data)) {
                $data = $this->data;
                $this->data = array();
            } else {
                return false;
            }
        }

        if (isset($data[0]) && is_array($data[0])) {
            //批量添加
            $batch_data = array();
            foreach ($data as $key=>$_data){
                $this->_before_insert($_data);
                $batch_data[$key] = $this->_facade($_data);
            }
            $result = $this->other_db->insert_batch($this->table, $batch_data);
        } else {
            $this->_before_insert($data);
            $facade_data = $this->_facade($data);

            $result = $this->other_db->insert($this->table, $facade_data);
        }

        if (false !== $result) {
            $insert_id = $this->other_db->insert_id();
            if ($insert_id) {
                $data[$this->primary] = $insert_id;
                $this->_after_insert($data);
                return $insert_id;
            }
            $this->_after_insert($data);
        }
        return $result;
    }

    protected function _before_insert(&$data) {}

    protected function _after_insert($data) {}

    /**
     * 修改数据
     */
    public function edit($data = array(), $options = array(), $primary = '')
    {
        if (empty($data)) {
            // 没有传递数据，获取当前数据对象的值
            if (!empty($this->data)) {
                $data = $this->data;
                $this->data = array();
            } else {
                return false;
            }
        }
        $this->_parse_options($options);
        if (isset($data[0]) && is_array($data[0]) && $primary) {
            $batch_data = array();
            foreach ($data as $key=>$_data){
                $this->_before_update($_data, $options);
                $batch_data[$key] = $this->_facade($_data);
            }
            $result = $this->other_db->update_batch($this->table, $batch_data, $primary);
        } else {
            $this->_before_update($data, $options);
            $facade_data = $this->_facade($data);
            $result = $this->other_db->update($this->table, $facade_data);
        }
        if (false !== $result) {
            $this->_after_update($data, $options);
        }
        return $result;
    }

    protected function _before_update(&$data, $options) {}

    protected function _after_update($data, $options) {}

    public function set_field($data, $options = array())
    {
        $this->_parse_options($options);
        $result = $this->other_db->update($this->table, $data);
        if (false !== $result) {
            $this->_after_update($data, $options);
        }
        return $result;
    }

    /**
     * 删除数据
     */
    public function delete($options = array())
    {
        if(is_numeric($options)  || is_string($options)) {
            $primary = $this->primary;
            if(strpos($options, ',')) {
                $this->other_db->where_in($primary, explode(',', $options));
            }else{
                $this->other_db->where(array($primary => $options));
            }
        } else {
            $this->_parse_options($options);
        }
        $result = $this->other_db->delete($this->table);
        if(false !== $result) {
            $this->_after_delete($options);
        }
        return $result;
    }

    protected function _after_delete($options) {}

    public function set_inc($field, $options = array(), $step = 1) {
        if(is_numeric($options)  || is_string($options)) {
            $primary = $this->primary;
            if(strpos($options, ',')) {
                $this->other_db->where_in($primary, explode(',', $options));
            }else{
                $this->other_db->where(array($primary => $options));
            }
        } else {
            $this->_parse_options($options);
        }
        $this->other_db->set($field, $field.'+'.$step, FALSE)->update($this->table);
    }

    public function set_dec($field, $options = array(), $step = 1) {
        if(is_numeric($options)  || is_string($options)) {
            $primary = $this->primary;
            if(strpos($options, ',')) {
                $this->other_db->where_in($primary, explode(',', $options));
            }else{
                $this->other_db->where(array($primary => $options));
            }
        } else {
            $this->_parse_options($options);
        }
        $this->other_db->set($field, $field.'-'.$step, FALSE)->update($this->table);
    }

    //使用前请 写好条件语句
    public function set_dec_r($field, $step=1)
    {
        return $this->other_db->set($field, $field.'-'.$step, FALSE)->update($this->table);
    }


    /**
     * 字段之和
     * @param $field
     * @return mixed
     */

    public function sum($field)
    {
        $row = $this->other_db->select_sum($field)->get($this->table)->row_array();
        return $row[$field];
    }

    public function avg($field)
    {
        $row = $this->other_db->select_avg($field)->get($this->table);
        return $row[$field];
    }

    public function max($field)
    {
        $row = $this->other_db->select_max($field)->get($this->table)->row_array();
        return $row[$field];
    }

    public function min($field)
    {
        $row = $this->other_db->select_min($field)->get($this->table)->row_array();
        return $row[$field];
    }

    /**
     * 获取主键
     */
    public function get_primary()
    {
        return $this->primary;
    }

    /**
     * 获取表字段
     */
    public function get_fields($table = '')
    {
        return $this->other_db->list_fields($table ? $table : $this->table);
    }

    /**
     * 获取表名
     */
    public function get_table() {
        return $this->table;
    }

    public function get_last_query()
    {
        return $this->other_db->last_query();
    }

}
