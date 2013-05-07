<?php
//用于做全局路由控制器
//执行后不允许修改，保证路由一致性，该功能应由具体项目执行
/**
	$array = array(
		'rulename'=>'rulename',//保证路由唯一，做反向路由
		'rule'=>'/:c1/:c2.html',//路由规则，如果不支持URL Rewrite会将所有参数当成get方式链接
		'class'=>'',//项目的类文件字符串
		'priority'=>1,//路由的优先级,默认为1
		'extend'=>array(
			'c1'=>array('rule','function')
		)
		'default'=>array(),//默认值
	);
 */
class FN_tools_route implements FN__single{
	static private $_Instance = null;
	static private $_URLPrefix = '';
	static private $_DEBUG = false;
	static private $_HOST = false;
	static private $_RouteArray = array();
	static private $_RouteList = array();
	static public function getInstance($array = array()){
		if(!self::$_Instance){
			self::$_Instance = new self();
		}
		return self::$_Instance;
	}
	private function __construct(){}
	//修改路由规则，执行后不允许修改
	public function route($routename,$route){
		$route['routename'] = $routename;
		if(empty($route['priority'])) $route['priority'] = 1;
		if(empty(self::$_RouteArray)){
			self::$_RouteArray[] = $route;
			return true;
		}
		$d_key = $sort_key = null;
		foreach(self::$_RouteArray as $key=>$value){
			if($value['routename']==$route['routename']){
				$d_key=$key+1;
			}
			if(!$sort_key && $value['priority']>=$route['priority']){
				$sort_key = $key+1;
			}
		}
		if($sort_key){
			$sort_key--;
			if($d_key > $sort_key) $d_key++;
			array_splice(self::$_RouteArray,$sort_key,0,array($route));
		}else{
			self::$_RouteArray[] = $route;
		}
		if($d_key){
			$d_key--;
			array_splice(self::$_RouteArray,$d_key,1);
		}
		return true;
	}
	public function debug(){
		self::$_DEBUG = true;
		return $this;
	}
	public function setHost($host=false){
		self::$_HOST = $host ? $host : FNbase::getHead('host');
		return $this;
	}
	//根据选择的路由，返回对应的链接，直接返回当前项目的url
	public function url($routename,$array){
		if(empty(self::$_RouteList[$routename])) return false;
		$route = self::$_RouteArray[self::$_RouteList[$routename]];
		if(!empty($array)){
			$search_arr = array();
			$replace_arr = array();
			foreach($array as $key=>$value){
				//:为变量说明符
				$search_arr[] = '/:'.$key.'/';
				$replace_arr[] = $value;
			}
			$route['rule'] = str_replace($search_arr,$replace_arr,$route['rule']);
		}
		return (empty($route['prefix']) ? self::$_URLPrefix : $route['prefix']).$route['rule'];
	}
	//设置链接返回前缀
	public function setURLPrefix($prefix){
		return self::$_URLPrefix = $prefix;
	}
	static public function parseSlice($string,$arg1,$arg2=false){
		return $arg2 ? substr($string, $arg1, $arg2) : substr($string,$arg1); 
	}
	static public function parseParam($param,$split = '/'){
		if(empty($param)) return array();
		$paramarr = array();
		$sarr = explode($split,$param);
		if(empty($sarr[0])) array_shift($sarr);
		$len = count($sarr);
		if(empty($sarr)) return $paramarr;
		if($len == 1){
			return $sarr;
		}elseif($len%2 != 0){
			$sarr = array_slice($sarr, 0, -1);
		}
		for($i=0; $i<=$len; $i=$i+2) {
			if(isset($sarr[$i+1])) $paramarr[$sarr[$i]] = addslashes(str_replace(array('/', '\\'), '', rawurldecode(stripslashes($sarr[$i+1]))));
		}
		return $paramarr;
	}
	//执行路由
	public function run($url=''){
		if(empty(self::$_RouteArray)) return false;
		$r_key = false;
		$result = array();
		$url = empty($url) ? (self::$_HOST ? self::$_HOST : '' ).FNbase::getRequestUri() : $url;
		if(self::$_DEBUG) var_dump($url);
		foreach(self::$_RouteArray as $key=>$value){
			self::$_RouteList[$value['routename']]=$key;
			if($r_key) continue;
			//:为变量说明符
			preg_match_all('/:(\w+)/',$value['rule'],$param_arr_tmp);
			//正则替换
			$search_arr = array('/','.','-','?','*');
			$replace_arr = array('\/','\.','\-','\?','\*');
			if(self::$_DEBUG) var_dump($value['rule']);
			if(!empty($param_arr_tmp)){
				foreach($param_arr_tmp[1] as $k=>$v){
					//扩展正则替换
					$rule = empty($value['extend'][$v]) ? '\w+':(is_array($value['extend'][$v]) ? $value['extend'][$v][0] : $value['extend'][$v]);
					$search_arr[]=$param_arr_tmp[0][$k];
					$replace_arr[]='('.$rule.')';
				}
				$value['rule'] = str_replace($search_arr,$replace_arr,$value['rule']);
			}
			if(self::$_DEBUG) var_dump($value['rule']);
			preg_match('/'.$value['rule'].'/m',$url,$value_arr_tmp,PREG_OFFSET_CAPTURE);
			if(self::$_DEBUG) var_dump($value_arr_tmp);
			if(empty($value_arr_tmp)) continue;
			$r_key = $key+1;//匹对成功
			if(empty($param_arr_tmp)) continue;
			$index = 1;//正则匹对索引1开始
			$length = 0;
			$count = count($value_arr_tmp);
			foreach($param_arr_tmp[1] as $k=>$v){
				$length = $value_arr_tmp[$index][1] + strlen($value_arr_tmp[$index][0]);
				$result[$v] = $value_arr_tmp[$index][0];
				if(!empty($value['extend'][$v]) && is_array($value['extend'][$v])){
					$function = $value['extend'][$v][1];
					if(empty($value['extend'][$v][2])){
						$result[$v] = call_user_func($value['extend'][$v][1],$result[$v]);
					}else{
						$result[$v] = call_user_func_array($value['extend'][$v][1],array_merge(array($result[$v]),$value['extend'][$v][2]));
					}
				}
				//去除为空数据导致的false判断，以至于default数据无法生效
				if($result[$v] === false) unset($result[$v]);
				do{
					$index++;
					if($index >= $count) break;
				}while($value_arr_tmp[$index][1] < $length);
				
			}
		}
		if(!$r_key) return false;
		$r_key--;
		$rule = self::$_RouteArray[$r_key];
		if(!empty($rule['default'])) $result = array_merge($rule['default'],$result);
		if(!empty($result)){
			//$为变量说明符
			$pos = strpos($rule['class'],'$');
			if($pos !== false){//参数需转换
				$search_arr = $replace_arr = array();
				foreach($result as $key=>$value){
					if(is_array($value)) continue;
					$search_arr[]='$'.$key;
					$replace_arr[]=$value;
				}	
				$rule['class'] = str_replace($search_arr,$replace_arr,$rule['class']);
			}
		}
		if(self::$_DEBUG) var_dump($result,$rule['class']);
		self::$_DEBUG = false;
		//返回执行类
		return FN::i($rule['class'],$result);
    }
}
?>
