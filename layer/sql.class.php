<?php
//数据库操作类
//通过设置表字段，主键及主键是否自增长实现sql的自动编写等功能
class FN_layer_sql implements FN__factory{
	// 常量:SQL执行返回结果：自动，执行，取第一单元格，取整行，取
	const RETURN_AUTO = 'auto';
	const RETURN_EXEC = 'exec';
	const RETURN_CELL = 'cell';
	const RETURN_ROW = 'row';
	const RETURN_COLUMN = 'column';
	const RETURN_ALL = 'all';
	const RETURN_KEY = 'key';
	const RETURN_GROUP = 'group';
	const RETURN_INSERTID = 'insertid';

	//支持对象级别操作，所以所有内置变量都添加_前缀用于避免冲突
	private $_sql=null;
	private $_join=null;
	private $_where=null;
	private $_order=null;
	private $_group=null;
	private $_having=null;
	private $_limit=null;
	private $_count=null;
	private $_field_string=null;
	private $_exec=null;
	private $_row=null;
	private $_db=null;
	private $_error=null;
	//驱动跟随映射走的
	//protected $_drive=null;//数据库驱动，mysql，mssql，默认是mysql
	protected $__link='default';//数据库映射名。默认是default
	protected $__dbname=null;//数据库名
	protected $__table=null;//数据表名
	protected $__field = array();//字段数组
	protected $__pkey = false;//主键字段
	protected $__aint = true;//主键是否自增长
	protected $__alias = null;//别名，用来自动连表等操作

	static public function getFactory(){
		$class = get_called_class();
		return new $class();
	}
	private function __construct(){
		$config = FN::serverConfig('database',$this->__link);
		if(empty($config) || !in_array($config['drive'],array('mysql'))){
			$this->_error = 5;//配置错误
			return false;
		}
        $this->_db = FN::server('database',$this->__link);
		if(!empty($config['prefix'])) $this->__table = $config['prefix'].$this->__table;//实现表前缀添加
		if(!in_array($this->__pkey,$this->__field)) $this->__pkey = null;
		if(empty($this->__pkey)) $this->__aint = false;
        return $this;
	}
	public function add($array=array(),$replace=false){
		if(empty($array) && empty($this->_exec)) throw new FN_layer_sqlException(FN_layer_sqlException::DATA_EMPTY);

		if(!empty($array)){
			foreach($array as $field=>$value){
				$this->$field = $value;
			}
		}
		$string1 = $string2 = '';
		foreach($this->__field as $field){
			if($field == $this->__pkey && $this->__aint){
				if(empty($array[$field])) continue;
			}
			if(!isset($array[$field])) $array[$field] = '';//默认字段为空
			$string1 .= '`'.$field.'`,';
			$string2 .= '"'.$array[$field].'",';
		}
		$sql = ($replace ? 'replace' : 'insert').' into '.$this->getTable().'('.substr($string1,0,-1).')values('.substr($string2,0,-1).')';
		$this->execute($sql);
		if($this->__aint && $this->__pkey){
			$pkey = $this->__pkey;
			$this->$pkey = $this->_db->insert_id();
		}
		return $this->_map();
	}
	public function addMore($array){
		if(empty($array)) throw new FN_layer_sqlException(FN_layer_sqlException::DATA_EMPTY);
		$field_array = array();
		$string1 = $string2 = '';
		foreach($this->__field as $field){
			if($field == $this->__pkey && $this->__aint){
				continue;
			}
			$field_array[] = $field;
			$string1 .= '`'.$field.'`,';
		}
		foreach($array as $key=>$value){
			foreach($field_array as $field){
				$string2 .= '"'.(empty($value[$field]) ? '' : $value[$field]).'",';
			}
			$string_array[] = substr($string2,-1);
		}
		$sql = 'insert into '.$this->getTable().'('.substr($string1,0,-1).')values('.implode('),(',$string_array).')';
		$this->execute($sql);
		return true;
	}
	public function edit($array=array()){
		if(empty($array) && empty($this->_exec)) throw new FN_layer_sqlException(FN_layer_sqlException::DATA_EMPTY);
		if(!empty($array)){
			foreach($array as $field=>$value){
				$this->$field = $value;
			}
		}
		$string = '';
		foreach($this->_exec as $key=>$value){
			$string .= '`'.$key.'`'.self::judgeSQL($value,true).',';
		}
		if($this->__pkey){
			$pkey = $this->__pkey;
			if($this->$pkey >0) $this->where(' `'.$this->__pkey.'` = "'.$this->$pkey.'"');
		}
		if(empty($this->_where)) throw new FN_layer_sqlException(FN_layer_sqlException::WHERE_ERROR);
		$sql = 'update '.$this->getTable().' set '.substr($string,0,-1)
			.$this->_buildSQL('where',$this->_where);
		$this->execute($sql);
		return $this->_map();
	}
	public function delete($string=''){
		if($this->__pkey && $string){
			if(is_array($string)){
				$string = implode('","',$string);
			}
			$this->where(' `'.$this->__pkey.'` in ("'.$string.'")');
		}elseif($this->__pkey){
			$pkey = $this->__pkey;
			if($this->$pkey > 0) $this->where(' `'.$this->__pkey.'` = "'.$this->$pkey.'"');
		}
		if(empty($this->_where)) throw new FN_layer_sqlException(FN_layer_sqlException::WHERE_ERROR);
		$sql = 'delete from '.$this->getTable()
			.$this->_buildSQL('where',$this->_where);
		$this->_map(false);
		return $this->execute($sql);
	}
	public function select($array = array(), $type=self::RETURN_EXEC){
		if(!empty($array['limit'])){
			$this->limit($array['limit']);
		}
		if(!empty($array['page'])){
			$this->page($array['page']);
		}
		if(!empty($array['order'])){
			$this->order($array['order']);
		}
		if(!empty($array['where'])){
			$this->where($array['where']);
		}
		if(!empty($array['group'])){
			$this->group($array['group']);
		}
		if(!empty($array['having'])){
			$this->group($array['having']);
		}
		if(!empty($array['field'])){
			$this->field($array['field']);
		}
		return $this->query($this->buildSQL(), $type);
	}
	public function find($pkey=''){
		if(!$this->__pkey) throw new FN_layer_sqlException(FN_layer_sqlException::PKEY_ERROR);
		if($pkey){
			if(is_array($pkey)) $pkey = implode('","',$pkey);
			$this->where(' `'.$this->__pkey.'` in ("'.$pkey.'")');
			$type = self::RETURN_ALL;
		}else{
			$pkey = $this->__pkey;
			$this->where(' `'.$this->__pkey.'` = "'.$this->$pkey.'"');
			$type = self::RETURN_ROW;
		}
		if(empty($this->_where)) throw new FN_layer_sqlException(FN_layer_sqlException::WHERE_ERROR);
		$row = $this->query($this->buildSQL(), $type);
		$this->_map($row);
		return $row;
	}
	public function join($table,$relation,$fields,$dire='left'){
		$this->_join[] = array($table,$relation,$dire);
		$this->field($fields,$table);
		return $this;
	}
	public function where($string,$table=false){
		$this->_where[] = array($string,$table);
		return $this;
	}
	public function page($page){
		if(empty($this->_limit[1])) throw new FN_layer_sqlException(FN_layer_sqlException::PAGE_ERROR);
		$this->_limit[0] = ($page-1)*$this->_limit[1];
		return $this;
	}
	public function limit($l1,$l2=''){
		if(is_array($l1)){
			list($l1, $l2) = $l1;
		}elseif(empty($l2)){
			$l1 = 0;
            $l2 = $l1;
		}
		$this->_limit = array($l1,$l2);
		return $this;
	}
	public function order($string,$table=false){
		$this->_order[] = array($string,$table);
		return $this;
	}
	public function group($string,$table=false){
		$this->_group[] = array($string,$table);
		return $this;
	}
	public function having($string,$table=false){
		$this->_having[] = array($string,$table);
		return $this;
	}
	public function field($string,$table=false){
		$this->_field_string[] = array($string,$table);
		return $this;
	}
	public function query($sql, $type=self::RETURN_AUTO){
		try{
			$sth = $this->_db->prepare($sql);
			$sth->execute();
			switch($type){
				case self::RETURN_CELL://返回第一行第一列
					return $sth->fetchColumn();
				case self::RETURN_ROW://返回第一行
					return $sth->fetch();
				case self::RETURN_COLUMN://返回所有行第一列的数组
					return $sth->fetchAll(PDO::FETCH_COLUMN);
				case self::RETURN_ALL:
					return $sth->fetchAll();
				case self::RETURN_INSERTID:
					return $this->_db->lastInsertId();
				case self::RETURN_KEY:
					return $sth->fetchAll(PDO::FETCH_COLUMN|PDO::FETCH_KEY_PAIR);
				case self::RETURN_AUTO:
				case self::RETURN_EXEC://返回资源符
				default:
					return $sth;
			}
		}catch(Exception $e){
			throw new FN_layer_sqlException(FN_layer_sqlException::SQL_ERROR, $sql, $e->getCode(), $e->getMessage());
		}
	}
	public function execute($sql){
		$this->_sql = $sql;
		$this->_clear();
		return $this->_db->query($sql);
	}
	public function getSQL(){
		return $this->_sql;
	}
	public function getTable(){
		return (empty($this->__dbname) ? '' : '`'.$this->__dbname.'`').'`'.$this->__table.'`';
	}
	public function getAlias(){
		return $this->__alias;
	}
	public function getAliasTable(){
		return $this->__alias ? $this->__alias : $this->getTable();
	}
	public function getField(){
		return $this->__field;
	}
	public function clear(){
		$this->_clear();
		return $this;
	}
	public function buildSQL(){
		if(!empty($this->_join)){
			$this->_sql = 'select '.$this->_buildJoinSQL('field',$this->_field_string).' from '.$this->getTable().(!empty($this->_join) ? ' '.$this->getAlias():'').' '
				.$this->_buildJoinSQL('join',$this->_join).$this->_buildJoinSQL('where',$this->_where)
				.$this->_buildJoinSQL('group',$this->_group).$this->_buildJoinSQL('having',$this->_having)
				.$this->_buildJoinSQL('order',$this->_order).$this->_buildJoinSQL('limit',$this->_limit);
		}else{
			$this->_sql = 'select '.$this->_buildSQL('field',$this->_field_string).' from '.$this->getTable().' '.$this->_buildSQL('where',$this->_where)
				.$this->_buildSQL('group',$this->_group).$this->_buildSQL('having',$this->_having)
				.$this->_buildSQL('order',$this->_order).$this->_buildSQL('limit',$this->_limit);
		}
		$this->_clear();
		return $this->_sql;
	}
	public function count($new_field=''){
		$field = $this->_field_string;
		$this->_field_string = array(array('count('.($new_field?'`'.$new_field.'`':'*').')',$this));
		$num = $this->query($this->buildSql(),self::RETURN_CELL);
		$this->_field_string = $field;
		return $num ? $num : 0;
	}
	/**
	 * 属性魔术方法
	 */
	public function __get($property){
		if(!in_array($property ,$this->__field)) throw new FN_layer_sqlException(FN_layer_sqlException::FIELD_ERROR);
		if(!$this->_row || !isset($this->_row[$property])) return '';
		return $this->_row[$property];
	}
	public function __set($property,$value){
		if(!in_array($property,$this->__field)) throw new FN_layer_sqlException(FN_layer_sqlException::FIELD_ERROR);
		$this->_row[$property] = $value;
		if(!($this->_pkey && $property == $this->_pkey)) $this->_exec[$property] = $value;
		return true;
	}
	private function _buildSQL($type,$array){
		$alias = '';
		switch($type){
			case 'where':
				if(empty($array)) return ' ';
				foreach($array as $key=>$value){
					$string = $value[0];
					if(is_array($string)){
						$str = array();
						foreach($string as $k=>$v){
							$str[] = '`'.$k.'`'.self::judgeSQL($v);
						}
						$string = implode(' and ',$str);
					}
					if(empty($string)) {
						unset($array[$key]);
						continue;
					}
					$array[$key] = $string;
				}
				if(empty($array)) return ' ';
				return ' where ('.implode(') and (',$array).') ';
			case 'order':
				if(empty($array)) return ' ';
				foreach($array as $key=>$value){
					if(empty($value[0])) {
						unset($array[$key]);
						continue;
					}
					$array[$key] = $value[0];
				}
				if(empty($array)) return ' ';
				return ' order by '.implode(' , ',$array);
			case 'limit':
				if(empty($array)) return ' ';
				return ' limit '.implode(',',$array);
			case 'group':
				if(empty($array)) return ' ';
				foreach($array as $key=>$value){
					if(empty($value[0])) {
						unset($array[$key]);
						continue;
					}
					$array[$key] = $value[0];
				}
				if(empty($array)) return ' ';
				return ' group by '.implode(',',$array);
			case 'having':
				if(empty($array)) return ' ';
				foreach($array as $key=>$value){
					$string = $value[0];
					if(is_array($string)){
						$str = array();
						foreach($string as $k=>$v){
							$str[] = '`'.$k.'`'.self::judgeSQL($v);
						}
						$string = implode(' and ',$str);
					}
					if(empty($string)) {
						unset($array[$key]);
						continue;
					}
					$array[$key] = $string;
				}
				if(empty($array)) return ' ';
				return ' having '.implode(',',$array);
			case 'field':
				if(empty($array)) return ' * ';
				foreach($array as $key=>$value){
					$string = $value[0];
					if(is_array($string)){
						$str = '';
						foreach($string as $value){
							$str .= ',`'.$value.'`';
						}
						$string = substr($str,1);
					}
					if(empty($string)) {
						unset($array[$key]);
						continue;
					}
					$array[$key] = $string;
				}
				if(empty($array)) return ' ';
				return ' '.implode(' , ',$array);
		}
	}
	private function _buildJoinSQL($type,$array){
		switch($type){
			case 'join':
				if(empty($array)) return '';
				foreach($array as $key=>$a){
					$table_name = $a[0]->getTable();
					$alias_2 = $a[0]->getAlias();
					$string = ' '.$a[2].' join '.$table_name.(!empty($alias_2) ? ' as '.$alias_2: '').' on ';
					$alias = !empty($this->__alias) ? $this->__alias:$this->getTable();
					$alias_2 = $a[0]->getAliasTable();
					foreach($a[1] as $f=>$ff){
						$string .= $alias.'.`'.$f.'` = '.$alias_2.'.`'.$ff.'` and ';
					}
					$string = substr($string,0,-4);
					$array[$key] = $string;
				}
				if(empty($array)) return ' ';
				return ' '.implode(' ',$array);
			case 'where':
				if(empty($array)) return ' ';
				foreach($array as $key=>$value){
					$string = $value[0];
					if(!$value[1]) $value[1] = $this;
					$alias = $value[1]->getAliasTable().'.';
					$fields = $value[1]->getField();
					if(is_array($string)){
						$str = array();
						foreach($string as $k=>$v){
							$str[] = $alias.'`'.$k.'`'.self::judgeSQL($v);
						}
						$string = implode(' and ',$str);
					}
					if(empty($string)) {
						unset($array[$key]);
						continue;
					}
					$array[$key] = $string;
				}
				if(empty($array)) return ' ';
				return ' where ('.implode(') and (',$array).') ';
			case 'order':
				if(empty($array)) return ' ';
				foreach($array as $key=>$value){
					$string = $value[0];
					if(!$value[1]) $value[1] = $this;
					$alias = $value[1]->getAliasTable().'.';
					$fields = $value[1]->getField();
					$string = trim($string);
					$replate_array = array();
					foreach($fields as $field){
						$replace_array[] = $alias.'`'.$field.'`';
					}
					$string = str_replace($fields,$replace_array,$string);
					if(empty($string)) {
						unset($array[$key]);
						continue;
					}
					$array[$key] = $string;
				}
				if(empty($array)) return ' ';
				return ' order by '.implode(' , ',$array);
			case 'limit':
				if(empty($array)) return ' ';
				return ' limit '.implode(',',$array);
			case 'group':
				if(empty($array)) return ' ';
				foreach($array as $key=>$value){
					$string = $value[0];
					if(!$value[1]) $value[1] = $this;
					$alias = $value[1]->getAliasTable().'.';
					$fields = $value[1]->getField();
					$string = trim($string);
					$replate_array = array();
					foreach($fields as $field){
						$replace_array[] = $alias.'`'.$field.'`';
					}
					$string = str_replace($fields,$replace_array,$string);
					if(empty($string)) {
						unset($array[$key]);
						continue;
					}
					$array[$key] = $string;
				}
				if(empty($array)) return ' ';
				return ' group by '.implode(',',$array);
			case 'having':
				if(empty($array)) return ' ';
				foreach($array as $key=>$value){
					$string = $value[0];
					if(!$value[1]) $value[1] = $this;
					$alias = $value[1]->getAliasTable().'.';
					if(is_array($string)){
						$str = array();
						foreach($string as $k=>$v){
							$str[] = $alias.'`'.$k.'`'.self::judgeSQL($v);
						}
						$string = implode(' and ',$str);
					}
					if(empty($string)) {
						unset($array[$key]);
						continue;
					}
					$array[$key] = $string;
				}
				if(empty($array)) return ' ';
				return ' having '.implode(',',$array);
			case 'field':
				if(empty($array)) return ' * ';
				foreach($array as $key=>$value){
					$string = $value[0];
					if(!$value[1]) $value[1] = $this;
					$alias = $value[1]->getAliasTable().'.';
					if(is_array($string)){
						$str = '';
						foreach($string as $value){
							$str .= ','.$alias.'`'.$value.'`';
						}
						$string = substr($str,1);
					}elseif($alias){
						$fields = $value[1]->getField();
						$string = trim($string);
						if($string == '*'){
							$string = $alias.$string;
						}else{
							$replate_array = array();
							foreach($fields as $field){
								$replace_array[] = $alias.'`'.$field.'`';
							}
							$string = str_replace($fields,$replace_array,$string);
						}
					}
					if(empty($string)) {
						unset($array[$key]);
						continue;
					}
					$array[$key] = $string;
				}
				if(empty($array)) return ' ';
				return ' '.implode(' , ',$array);
		}
	}
	private function _clear(){
		$this->_join = $this->_where = $this->_group = $this->_having = $this->_order = $this->_limit = $this->_count = $this->_field_string = $this->_exec = null;
	}
	private function _map($row=true){
		if($row===false) $row = array();
		if(is_array($row)) $this->_row = $row;
		return $this->_row;
	}
	static public function judgeSQL($value,$set=false){
		if(is_array($value) && !$set) return ' in ("'.implode('","',$value).'")';
		$Symbol = substr($value,0,1);
		//匹配基本符号
		switch($Symbol){
			case '!':
				if(!$set){
					return '<>"'.substr($value,1).'"';
				}else{
					break;
				}
			case '>':
			case '<':
				if(!$set){
					return $value;
				}else{
					break;
				}
			case '=':return $value;
		}
		//匹配特殊的where查询关键字
		if(!$set && preg_match('/^(like)\s[\'"].*?[\'"]$/',$value)) return ' '.$value;
		//匹配数字
		if(preg_match('/^\d+(\.\d+)?$/',$value)) return '='.$value;
		return '="'.$value.'"';
	}
}
class FN_layer_sqlException extends  FN_Exception{
	const DATA_EMPTY = 101;
	const WHERE_ERROR = 102;
	const PKEY_ERROR = 103;
	const FIELD_ERROR = 104;
	const PAGE_ERROR = 105;
	const SQL_ERROR = 106;
	static protected  $__error_list = array(
		self::DATA_EMPTY=>"数据为空",
		self::SQL_ERROR=>"SQL:%s ERROR(%s)：%s",
	);
}
