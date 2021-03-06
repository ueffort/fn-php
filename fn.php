<?php
//框架路径
define('FN_FRAME_PATH',dirname(__FILE__).'/');
//框架类前缀
define('FN_FRAME_PREFIX','FN');
//框架所支持的类文件后缀
define('FN_FRAME_SUFFIX','.class.php');
if(empty($_SERVER['argc'])){
	$default_port = array('http'=>80,'https'=>443);
	if(empty($_SERVER['REQUEST_SCHEME'])){
		$_SERVER['REQUEST_SCHEME'] = array_search($_SERVER['HTTP_HOST'],$default_port);
		if(empty($_SERVER['REQUEST_SCHEME'])) $_SERVER['REQUEST_SCHEME'] = 'http';
	}
	//当前访问的web路径
	define('FN_WEB_PATH',$_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].($default_port[$_SERVER['REQUEST_SCHEME']] == $_SERVER['SERVER_PORT'] ? '':':'.$_SERVER['SERVER_PORT']).FNbase::getBaseUri());
}else{
	//当前为控制器操作
	define('FN_CONSOLE',true);
}
//入口文件所在的系统路径
define('FN_SYSTEM_PATH',dirname($_SERVER['SCRIPT_FILENAME']).'/');
//用于满足框架自身工具类的正常使用
FN::setConfig(
	array(
		'autoCode'=>'freedomnature'
		,'charset'=>'UTF-8'
	)
	,'global'
);
//自动加载类
if(false === spl_autoload_functions() && function_exists('__autoload')) spl_autoload_registe('__autoload',false);
spl_autoload_register(array('FN', 'loadClass'));
class FN{
	private $_config = array();//全局配置设定，按每个主项目进行划分
	private static $_FileSpace = array();
	private static $_InitProject = false;
    private static $_ProjectPath = null;
	private static $_Instance = null;
	private static $_NowCloud = false;
	private static $_Frame = null;
	//存储自身映射关系，用作全局对象管理
	private static $_Map = array();
	private static $_Server = array();
	private static $_Platform = array();
	private function __clone() {}
	private function __construct() {}
	/**
	 * 初始化项目
	 * 传递项目路径或默认为入口文件所在文件路径
	 * 项目全局只能初始化一次
	 * @param string $path 项目所在路径，如果不设置，默认为项目入口文件所在路径
	 * @return void
	 */
	static public function initProject($path = ''){
		if(self::$_InitProject) return ;
		define('FN_PROJECT_PATH',$path ? $path : FN_SYSTEM_PATH);//项目路径
		$cloud = self::getConfig('cloud');
		self::$_NowCloud = $cloud ? $cloud : false;//保存云设置
		self::$_Instance = self::getFrame();//初始化框架单例
		self::$_InitProject = true;
        self::$_ProjectPath = FN_PROJECT_PATH;
	}
	static public function getNowCloud(){
		return self::$_NowCloud;
	}
	/**
	 * 获取服务对应平台(云平台是平台的一种，项目初始化可以预先设置当前的云平台)，实现全局单例模式，无需内部单例化
	 * @param string $platform  对应平台名称
	 */
	static public function getPlatform($platform){
		if($platform == 'cloud') $platform = self::$_NowCloud;
		if(!isset(self::$_Platform[$platform])){
			self::$_Platform[$platform] = self::F('platform.'.$platform,self::getConfig('platform/'.$platform));
		}
		return self::$_Platform[$platform];
	}

    /**
     * 获取基础服务:任何可由其他计算机单独完成的任务，实现全局单例模式，无需内部单例化
     * @param string $server_name 调用的服务名称
     * @param string $link 映射名称，默认为default
     * @throws FN_exception
     * @return class 服务实例
     */
	static public function server($server_name,$link='default'){
		if(!isset(self::$_Server[$server_name][$link])){
			$config = self::serverConfig($server_name,$link);
			//将服务与实际驱动分割，实现控制管理
			$config['drive'] = empty($config['drive'])?$server_name : $config['drive'];
			if(empty(self::$_Server[$server_name])) self::$_Server[$server_name] = array();
			self::$_Server[$server_name][$link] = self::_server($server_name,$config);
		}
        if(empty(self::$_Server[$server_name][$link])) throw new FN_exception("Server: $server_name($link) init is error");
		return self::$_Server[$server_name][$link];
	}
	/**
	 * 获取基础服务,云服务需自行判断是否支持外部云调用方式（API接口）
	 * @param string $server_name  调用的服务名称
	 * @param string $config  映射名称，默认为default
	 * @return class 服务实例
	 */
	static private function _server($server_name,$config){
		if(isset($config['platform'])){
			$return = self::getPlatform($config['platform'])->server($server_name,$config);
			if($return) return $return;
		}
		$class_name = self::F('server.'.$server_name.($config['drive'] ?  '.'.$config['drive'] : '' ));
		return call_user_func_array(array($class_name,'initServer'),array($config));
	}
	/**
	 * 获取基础服务的配置信息，方便内部高阶服务调用基础配置
	 * @param string $server_name  调用的服务名称
	 * @param string $link  映射名称，默认为default
	 * @return array
	 */
	static public function serverConfig($server_name,$link='default'){
		return self::getConfig($server_name.'/'.$link);
	}
	/**
	 * 用静态方式获取框架
	 */
	static private function getFrame() {
		if(empty(self::$_Frame)) self::$_Frame = new self();
		return self::$_Frame;
	}
	/**
	 * 该方法实现扩展类的返回
	 */
	static public function getInstance(){
		if(!self::$_InitProject) return self::getFrame();
		return self::$_Instance;
	}
	/**
	 * 设置全局配置信息
	 * @param array $config
	 * @param string $string
	 * @return array or string
	 */
	static public function setConfig($config,$string=''){
		return self::getFrame()->_setConfig($config,$string);
	}
	/**
	 * 获取全局配置信息
	 * @param string $string
	 * @return array or string
	 */
	static public function getConfig($string=''){
		return self::getFrame()->_getConfig($string);
	}
	/**
	 * 全局存储映射关系，一层关系，可以存储对象，单一调用接口
	 */
	static public function map($key,$value=null){
		if($value===null) return !isset(self::$_Map[$key]) ? null : self::$_Map[$key];
		return self::$_Map[$key] = $value;
	}
	/**
	 * 项目扩展类调用
	 * 以项目目录为调用目录结构tools.controller.view  =>  FN_PROJECT_PATH/tools/controller/view  =>  tools_controller_view
	 */
	static public function i(){
		if(!self::$_InitProject) return true;
		//return self::C($class,$array);
		$arg = func_get_args();
		return call_user_func_array(array(self::getFrame(),'C'),$arg);
	}
	/**
	 * 框架类调用
	 * 以工具目录为调用目录结构controller.view  =>  FN_FRAME_PATH/controller/view  =>  FN_tools_view
	 */
	static public function F(){
		//return self::C(FN_FRAME_PREFIX.'.'.$class,$array);
		$arg = func_get_args();
		$arg[0] = FN_FRAME_PREFIX.'.'.$arg[0];
		return call_user_func_array(array(self::getFrame(),'C'),$arg);
	}
	/**
	 * 统一类文件调用
	 */
	static private function C(){
		$array = func_get_args();
		$name = array_shift($array);
		if(count($array)==0) $array = array(array());
		list($class_name,$shortname,$path) = self::parseName($name);
		//开启自动加载类，减少调用
		if(!class_exists($class_name)){
			throw new FN_exception("Class : $name is error!");
		}
		$Reflection = new ReflectionClass($class_name);
		$interface = $Reflection->getInterfaceNames();
		if(empty($interface)){
			//直接跳过下列判断
		}elseif(in_array('FN__factor',$interface)){//定义factor接口
			//return $class_name::factor($array);
			return call_user_func_array(array($class_name,'factor'),$array);
		}elseif(in_array('FN__auto',$interface)){//定义auto接口
			//return new $class_name($array);
			$arrayString = $return = '';
			foreach($array as $key=>$value){
				$arrayString .= '$array['.$key.'],';
			}
			eval('$return = new $class_name('.substr ( $arrayString, 0, strlen ( $arrayString ) - 1 ).');');
			return $return;
		}elseif(in_array('FN__single',$interface)){//定义single接口,object instanceof class,parents class,interface
			//return $class_name::getInstance($array);
            if(empty($array)){
                $class = self::map($name);
                if($class) return $class;
            }
			$class = call_user_func_array(array($class_name,'getInstance'),$array);
            if(!$array){
                self::map($name, $class);
            }
            return $class;
		}
		return $class_name;
	}
	/**
	 * 加载类文件，方便延迟加载的调用
	 * 框架内不写成子类互相调用，子类只给内部调用
	 */
	static public function loadClass($class_name){
		if(substr($class_name,0,strlen(FN_FRAME_PREFIX)+1) == FN_FRAME_PREFIX.'_'){
			$path = FN_FRAME_PATH;
			$file_name = substr($class_name,strlen(FN_FRAME_PREFIX)+1);
		}else{
			$path = FN_PROJECT_PATH;
			$file_name = $class_name;
		}
		$file_name = $path.str_replace('_','/',$file_name).FN_FRAME_SUFFIX;
		if(!self::loadFile($file_name) && $path == FN_PROJECT_PATH){
			//子类判断,框架内不判断
			$path = dirname($file_name);
			if(!is_dir($path)) return false;
			$dir = dir($path);
			$success = false;
			$last_name = self::lastName($class_name);
			$len = strlen(FN_FRAME_SUFFIX);
			//加载所有前部名称相同的类文件
			//注：单目录中的文件数量，含义相同的文件数量都是性能问题
			while (($file = $dir->read()) !== false){
				if($file == '..' || $file== '.') continue;
				$name = substr($file,0,-$len);
				if(substr($last_name,0,strlen($name)) != $name) continue;
				self::loadFile($file,$path);
				if(class_exists($class_name,false)){
					$success = true;
					break;
				}
			}
			$dir->close();
			return $success;
		}else{
			return class_exists($class_name,false);
		}
	}
	/**
	 * 用于类完整的调用名返回
	 * child设置同类文件的子类名，用于区分该类是否是子类
	 * showchild开关，默认打开，用于处理返回值内是否带有子类的调用
	 * 完整的调用名，传递类名即可实现自动调用类
	 */
	static public function callName($className,$child='',$showchild=true){
		$lastName = self::lastName($className,$child,$showchild);
		$fatherName = str_replace('_','.',substr($className,0,strrpos($className,'_')));
		return $fatherName.'.'.$lastName;
	}
	/**
	 * 用于类最后的调用名返回
	 * child设置同类文件的子类名，用于区分该类是否是子类
	 * showchild开关，默认关闭，用于处理返回值内是否带有子类的调用
	 *
	 * 如果类名按类型划分，通过该函数可以获取该类的文件名，通过传递类名即可实现类型划分
	 */
	static public function lastName($className,$child='',$showchild=false){
		$pos = strrpos($className,'_');
		if($pos) $className = substr($className,$pos+1);
		if($child){
			$className = substr($className,0,-1 * strlen($child));
			if($showchild) $className .=':'.$child;
		}
		return $className;
	}
	/**
	 * 类文件的子类文件夹的返回
	 */
	static public function ChildDir($file){
		return substr($file,0,-1 * strlen(FN_FRAME_SUFFIX)).'/';
	}
	static public function loadFile($file,$path = '') {
		if(!empty($path)) substr($path, -1) != '/' && $path .= "/";
		$file = $path.$file;
		if(isset(self::$_FileSpace[$file])) return true;
		if(file_exists($file)&&!is_dir($file)) {
			include_once ($file);
			return self::$_FileSpace[$file] = true;
		}else {
			return false;
		}
	}
	static public function __callstatic($method,$args){
		$frame = self::getInstance();
		return call_user_func_array(array(&$frame,'_'.$method),$args);
	}
	static public function setKey($source,&$target,$path = false) {
		if(is_array($source)) {
			foreach($source as $key=>$s) {
				self::setKey($s,$target[$key],$path || $key == 'path');//substr($key,-4)=='path'
			}
		}else{
			if($path) $target = self::parsePath($source);
			else $target = $source;
		}
		return true;
	}
	static public function parsePath($dir){
		$Symbol = substr($dir,0,1);
		switch($Symbol){
			case '~':return FN_FRAME_PATH.substr($dir,1);//框架路径
			case '!':return '';//暂留，无用
			case '@':return FN_WEB_PATH.substr($dir,1);//当前访问的web路径
			case '#':return FN_SYSTEM_PATH.substr($dir,1);//当前执行脚本所在的路径（可以当项目的访问路径）
			case '$':return self::$_ProjectPath.substr($dir,1);//项目的路径
			default:return self::$_NowCloud ? self::getPlatform(self::$_NowCloud)->parsePath($dir,$Symbol) : $dir;//扩展当前云平台的路径解析
		}
	}
	/**
	 * 根据字符串，返回类名，类文件名，类所在相对路径
	 * @param string $name
	 * @param string $Symbol
	 * @return array [classname,shortname,path] [完整类名,简短类名（目录实际名）,路径]
	 * 格式：prefix:class|child，prefix和child不参与路径和文件名操作
	 * class及prefix均用 . 进行命名分割
	 * class的分割还涉及到文件名及文件路径的判断
	 * child用于实现一个文件多个类的命名规则
	 * prefix用于实现设置类前缀，避免命名冲突
	 */
	static public function parseName($name,$Symbol='_'){
		$child = '';
		//一个类文件中多个类
		$pos = strrpos($name,'|');
		if($pos !== FALSE){
			$child = substr($name,$pos+1);
			$name = substr($name,0,$pos);
		}
		$name = str_replace('.',$Symbol,$name);
		$pos = strrpos($name,$Symbol);
		$shortname = substr($name,$pos+1);
		$path = str_replace($Symbol,'/',substr($name,0,$pos+1));
		return array($name.$child,$shortname,$path);
	}
	private function _getConfig($string=''){
		$config = $this->_config;
		if(empty($string)) return $config;
		$stringArray = explode('/',$string);
		foreach($stringArray as $str) {
			if(empty($config[$str])) return false;
			$config = $config[$str];
		}
		return $config;
	}
	private function _setConfig($config,$string=''){
		if(!is_array($config)) return false;
		if($string){
			$config_tmp = $this->_getConfig($string);
			$this->setKey($config,$config_tmp);
			$string = '$this->_config["'.str_replace('/','"]["',$string).'"] = $config_tmp;';
			return eval($string);
		}
		return $this->setKey($config,$this->_config);
	}
}

/**
 * 单例接口
 * Interface FN__single
 */
interface FN__single{
    /**
     * 获取单例 getInstance
     * @param $config
     * @return mixed
     */
}

/**
 * 工厂接口
 * Interface FN__factory
 */
interface FN__factory{
    /**
     * 执行工厂 geFactory
     * @param $config
     * @return mixed
     */
}

/**
 * 简单对象接口
 * Interface FN__auto
 */
interface FN__auto{
	/**
	 * 自动实例化new
	 *
	 * @access  public
	 * @return Object
	 */
}
define('SET_MAGIC_QUOTES_GPC',get_magic_quotes_gpc());
define('TIME_BASE',time());
class FNbase{
	static private $_Ip;
	static private $_RequestUri;
	static private $_Baseuri;
	static public function isAJAX(){
		return strtolower(self::getHead('X_REQUESTED_WITH')) == 'xmlhttprequest';
	}
	static public function isPJAX(){
		return strtolower(self::getHead('X-PJAX')) ? true : false;
	}
	static public function setPath($path){
		if(empty($path)) return false;
		substr($path, -1) != '/' && $path .= "/";
		if(!is_dir($path)) mkdir($path,0777,ture);
		return $path;
	}
	static public function getTime(){
		return TIME_BASE;
	}
	static public function setHtmlChars($string) {
		if(is_array($string)) {
			foreach($string as $key => $val) {
				$string[$key] = self::setHtmlChars($val);
			}
		} else {
			$string = htmlspecialchars($string);
		}
		return $string;
	}
	//force是一个开关，如果不设置则根据配置参数确定是否添加转义，如果设置为true，则强制添加转义
	static public function setEscape($string,$force = 0) {
		if(SET_MAGIC_QUOTES_GPC && !$force) return $string;
		if(is_array($string)) {
			foreach($string as $key => $val) {
				$string[$key] = self::setEscape($val, $force);
			}
		} else {
			$string = addslashes($string);
		}
		return $string;
	}
	//force是一个开关，如果不设置则根据配置参数确定是否清除转义，如果设置为true，则强制清除转义
	static public function clearEscape($string,$force = 0){
		if(!SET_MAGIC_QUOTES_GPC && !$force) return $string;
		if(is_array($string)) {
			foreach($string as $key => $val) {
				$string[$key] = self::clearEscape($val, $force);
			}
		} else {
			$string = stripslashes($string);
		}
		return $string;
	}
	static public function isAbsolutePath($path){
		if(substr($path, 0,1) == '/') return true;
		if(strpos($path,":\\") > 0) return true;
		return false;
	}
	static public function getHead($header){
		$temp = 'HTTP_'.strtoupper(str_replace('-', '_', $header));
		if (!empty($_SERVER[$temp])) return $_SERVER[$temp];
		if (function_exists('apache_request_headers')){
			$headers = apache_request_headers();
			if (!empty($headers[$header])) return $headers[$header];
		}
		return false;
	}
	static public function getIp(){
		if(!self::$_Ip){
			if(!empty($_SERVER['HTTP_CLIENT_IP']) && strcasecmp($_SERVER['HTTP_CLIENT_IP'], 'unknown')) {
				$ip = $_SERVER['HTTP_CLIENT_IP'];
			} elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR']) && strcasecmp($_SERVER['HTTP_X_FORWARDED_FOR'], 'unknown')) {
				$ip = substr($_SERVER['HTTP_X_FORWARDED_FOR'],0,strpos($_SERVER['HTTP_X_FORWARDED_FOR'],','));
				if (preg_match("/^(10|172.16|192.168)./", $ip)) $ip = false;
			}
			if(!$ip && !empty($_SERVER['REMOTE_ADDR']) && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
				$ip = $_SERVER['REMOTE_ADDR'];
			}
			preg_match("/[\d\.]{7,15}/",$ip, $onlineipmatches);
			self::$_Ip = $onlineipmatches[0] ? $onlineipmatches[0] : 'unknown';
			unset($onlineipmatches);unset($ip);
		}
		return self::$_Ip;
	}
	function getIpLocation($ip){
		$result = self::getUrlContent('http://ip.qq.com/cgi-bin/searchip?searchip1='.$ip);
		$result = mb_convert_encoding($result, "utf-8", "gb2312");//编码转换，否则乱码
		preg_match("@<span>(.*)</span></p>@iU",$result,$ipArray);
		return $ipArray[1];
	}
	//返回当前的完整请求
	static public function getRequestUri(){
		if (!self::$_RequestUri){
			if (isset($_SERVER['HTTP_X_REWRITE_URL'])){
				self::$_RequestUri = $_SERVER['HTTP_X_REWRITE_URL'];
			}elseif (isset($_SERVER['REQUEST_URI'])){
				self::$_RequestUri = $_SERVER['REQUEST_URI'];
			}elseif (isset($_SERVER['ORIG_PATH_INFO'])){
				self::$_RequestUri = $_SERVER['ORIG_PATH_INFO'];
				if (! empty($_SERVER['QUERY_STRING'])) self::$_RequestUri .= '?' . $_SERVER['QUERY_STRING'];
			}else{
				self::$_RequestUri = '';
			}
		}
		return self::$_RequestUri;
	}
	static public function setRequestUri($requestUri){
		self::$_RequestUri = $requestUri;
		self::$_Baseuri = null;
	}
	//返回当前请求的基本路径（去除请求文件名及参数）
	static public function getBaseUri(){
		if (self::$_Baseuri) return self::$_Baseuri;
		$filename = basename($_SERVER['SCRIPT_FILENAME']);
		if (basename($_SERVER['SCRIPT_NAME']) === $filename){
			$url = $_SERVER['SCRIPT_NAME'];
		}elseif (basename($_SERVER['PHP_SELF']) === $filename){
			$url = $_SERVER['PHP_SELF'];
		}elseif (isset($_SERVER['ORIG_SCRIPT_NAME']) && basename($_SERVER['ORIG_SCRIPT_NAME']) === $filename){
			$url = $_SERVER['ORIG_SCRIPT_NAME'];
		}else{
			$path = $_SERVER['PHP_SELF'];
			$segs = explode('/', trim($_SERVER['SCRIPT_FILENAME'], '/'));
			$segs = array_reverse($segs);
			$index = 0;
			$last = count($segs);
			$url = '';
			do{
				$seg = $segs[$index];
				$url = '/' . $seg . $url;
				++ $index;
			} while (($last > $index) && (false !== ($pos = strpos($path, $url))) && (0 != $pos));
		}
		$request = self::getRequestUri();
		if (0 === strpos($request, dirname($url))){
			self::$_Baseuri = rtrim(dirname($url), '/').'/';
		}elseif (!strpos($request, basename($url))){
			return '';
		}else{
			if ((strlen($request) >= strlen($url)) && ((false !== ($pos = strpos($request, $url))) && ($pos !== 0))){
				$url = substr($request, 0, $pos + strlen($url));
			}
			self::$_Baseuri = self::setHtmlChars(rtrim($url, '/') . '/');
		}
		return self::$_Baseuri;
	}
	//PHP代理访问函数
	static public function getUrlContent($url,$fields=array()){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		if(!empty($fields)){
			curl_setopt($ch, CURLOPT_POST, 1 );
			curl_setopt($ch, CURLOPT_POSTFIELDS,$fields);
		}
		$data = curl_exec($ch);
		curl_close($ch);
		return $data;
	}
	static function random($length, $numeric = 0) {
		PHP_VERSION < '4.2.0' && mt_srand((double)microtime() * 1000000);
		if($numeric) {
			$hash = sprintf('%0'.$length.'d', mt_rand(0, pow(10, $length) - 1));
		} else {
			$hash = '';
			$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz';
			$max = strlen($chars) - 1;
			for($i = 0; $i < $length; $i++) {
				$hash .= $chars[mt_rand(0, $max)];
			}
		}
		return $hash;
	}
	static public function guid($namespace='',$op=false){
		$uid = uniqid($namespace, true);
		$data = $namespace;
		$data .= $_SERVER['REQUEST_TIME'];
		$data .= $_SERVER['HTTP_USER_AGENT'];
		$data .= $_SERVER['SERVER_ADDR'];
		$data .= $_SERVER['SERVER_PORT'];
		$data .= $_SERVER['REMOTE_ADDR'];
		$data .= $_SERVER['REMOTE_PORT'];
		$hash = strtoupper(hash('ripemd128', $uid . md5($data)));
		if($op){
			return substr($hash,0,32);
		}else{
			return substr($hash,  0,  8).'-'.substr($hash,  8,  4) .'-'.substr($hash, 12,  4) .'-'.substr($hash, 16,  4).'-'.substr($hash, 20, 12);
		}
	}
	static public function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0) {
		$ckey_length = 4;
		$key = md5(empty($key) ? FN::getConfig('global/autoCode') : $key);
		$keya = md5(substr($key, 0, 16));
		$keyb = md5(substr($key, 16, 16));
		$keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length): substr(md5(microtime()), -$ckey_length)) : '';

		$cryptkey = $keya.md5($keya.$keyc);
		$key_length = strlen($cryptkey);

		$string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
		$string_length = strlen($string);

		$result = '';
		$box = range(0, 255);

		$rndkey = array();
		for($i = 0; $i <= 255; $i++) {
			$rndkey[$i] = ord($cryptkey[$i % $key_length]);
		}

		for($j = $i = 0; $i < 256; $i++) {
			$j = ($j + $box[$i] + $rndkey[$i]) % 256;
			$tmp = $box[$i];
			$box[$i] = $box[$j];
			$box[$j] = $tmp;
		}

		for($a = $j = $i = 0; $i < $string_length; $i++) {
			$a = ($a + 1) % 256;
			$j = ($j + $box[$a]) % 256;
			$tmp = $box[$a];
			$box[$a] = $box[$j];
			$box[$j] = $tmp;
			$result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
		}

		if($operation == 'DECODE') {
			if((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)) {
				return substr($result, 26);
			} else {
				return '';
			}
		} else {
			return $keyc.str_replace('=', '', base64_encode($result));
		}
	}
	static public function cutstr($string, $length, $havedot=0) {
		if(strlen($string) <= $length) return $string;
		$wordscut = '';
		if(strtolower(FN::getConfig('global/charset')) == 'utf-8') {
			$n = 0;
			$tn = 0;
			$noc = 0;
			while ($n < strlen($string)) {
				$t = ord($string[$n]);
				if($t == 9 || $t == 10 || (32 <= $t && $t <= 126)) {
					$tn = 1;
					$n++;
					$noc++;
				} elseif(194 <= $t && $t <= 223) {
					$tn = 2;
					$n += 2;
					$noc += 2;
				} elseif(224 <= $t && $t <= 239) {
					$tn = 3;
					$n += 3;
					$noc += 2;
				} elseif(240 <= $t && $t <= 247) {
					$tn = 4;
					$n += 4;
					$noc += 2;
				} elseif(248 <= $t && $t <= 251) {
					$tn = 5;
					$n += 5;
					$noc += 2;
				} elseif($t == 252 || $t == 253) {
					$tn = 6;
					$n += 6;
					$noc += 2;
				} else {
					$n++;
				}
				if ($noc >= $length) {
					break;
				}
			}
			if ($noc > $length) {
				$n -= $tn;
			}
			$wordscut = substr($string, 0, $n);
		} else {
			for($i = 0; $i < $length - 3; $i++) {
				if(ord($string[$i]) > 127) {
					$wordscut .= $string[$i].$string[$i + 1];
					$i++;
				} else {
					$wordscut .= $string[$i];
				}
			}
		}
		if($string != $wordscut && $havedot){
			return $wordscut.'...';
		}else{
			return $wordscut;
		}
	}
}

//基本错误异常类
class FN_exception extends Exception{
	protected $__error_list = null;

	public function __construct($error_const){
		$param = func_get_args();
        $code = 0;
		if(isset($this->__error_list[$error_const])){
			$code = $error_const;
			$param[0] = $this->__error_list[$error_const];
			$message = call_user_func_array('sprintf', $param);
		}else{
			$message = implode(' ', $param);
		}
        parent::__construct($message, $code);
	}

	public function getLog(){
		return sprintf("[%s] [%s:%i] (%i) %s", date('Y-m-d H:i:s'), $this->getFile(), $this->getLine(), $this->getCode(), $this->getMessage());
	}

	public static function printException(Exception $exception){
		$info = $exception->getTraceAsString();
        echo sprintf("[%s] [%s:%i] (%i) %s<br />", date('Y-m-d H:i:s'), $exception->getFile(), $exception->getLine(), $exception->getCode(), $exception->getMessage());
        print $info;
	}

}
/**
 * 服务平台抽象类
 * Class FN_platform
 */
class FN_platform implements FN__auto{
	protected $PlatformSelf = null;
    private $config = null;
    public function __construct($config){
        $this->config = $config;
    }
	/**
	 * 判断当前云环境是否是当前云服务
	 * 用于云服务的参数设置和获取，需实例化子类自身访问
	 * @return string
	 */
	protected function isCloudSelf(){
		return FN::getNowCloud() == $this->PlatformSelf;
	}
	/*
	 * 代理服务接口，转为全局类接口
	 */
	public function server($server_name,&$config){
        switch($server_name){
            case 'database':
                switch($config['drive']){
                    case 'mysql':
                        try {
	                        $pdo = new PDO($config['drive'].':dbname='.$config['dbname'].';host='.$config['host'].';port='.$config['port'], $config['user'], $config['pass']);
                            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
                            if (!empty($config['charset'])){
                                $sth = $pdo->prepare('SET NAMES ' . $config['charset']);
                                $sth->execute();
                                if ($sth->rowCount() === false) throw new FN_exception('Server: failed to set charset');
                            }
                        } catch (PDOException $e) {
                            throw new FN_exception('Server：connection failed: ' . $e->getMessage());
                        }
                        return $pdo;
                    case 'mongodb':
                        if (class_exists("MongoClient")) {
                            $class = 'MongoClient';
                        } else {
                            $class = 'Mongo';
                        }
                        $options = array();
                        if(isset($config['user']) && isset($config['pass'])){
                            $options['username'] = $config['user'];
                            $options['password'] = $config['pass'];
                        }
                        $class = new $class("mongodb://".$config['host'].":".$config['port'],$options);
                        if(isset($config['dbname'])){
                            $class->selectDB($config['dbname']);
                        }
                        return $class;
                    case 'redis':
                        $class = new Redis();
                        if($class->connect($config['host'], $config['port']))
                            throw new FN_exception('Server: redis connection failed');
                        if(isset($config['pass'])) $class->auth($config['pass']);
                        return $class;
                }
                break;
            case 'cache':
                switch($config['drive']){
                    case 'memcached'://cache
                        $class = new Memcached();
                        if(!$class->addServer($config['host'], $config['port']))
                            throw new FN_exception('Server: memcached connection failed');
                        return $class;
                }

        }
        return ;
	}
	public function parsePath($dir,$Symbol){
		return $dir;
	}
}

if(!function_exists('get_called_class')) {
class class_tools{
	private static $i = 0;
	private static $fl = null;
	public static function get_called_class(){
		$bt = debug_backtrace();
		//使用call_user_func或call_user_func_array函数调用类方法，处理如下
		if (array_key_exists(3, $bt)
			&& array_key_exists('function', $bt[3])
			&& in_array($bt[3]['function'], array('call_user_func', 'call_user_func_array'))
		){
			//如果参数是数组
			if (is_array($bt[3]['args'][0])) {
				$toret = $bt[3]['args'][0][0];
				return $toret;
			}else if(is_string($bt[3]['args'][0])) {//如果参数是字符串
			//如果是字符串且字符串中包含::符号，则认为是正确的参数类型，计算并返回类名
				if(false !== strpos($bt[3]['args'][0], '::')) {
					$toret = explode('::', $bt[3]['args'][0]);
					return $toret[0];
				}
			}
		}
		//BUG修正，复杂环境直接多一层判断
		if(array_key_exists("object",$bt[2]) && array_key_exists("class",$bt[2])){
			if ( $bt[2]['object'] instanceof $bt[2]['class'] )
				return get_class( $bt[2]['object'] );
		}
		//使用正常途径调用类方法，如:A::make()
		if(self::$fl == $bt[2]['file'].$bt[2]['line']) {
			self::$i++;
		} else {
			self::$i = 0;
			self::$fl = $bt[2]['file'].$bt[2]['line'];
		}
		$lines = file($bt[2]['file']);
		preg_match_all('/([a-zA-Z0-9\_]+)::'.$bt[2]['function'].'/', $lines[$bt[2]['line']-1],$matches);
		return $matches[1][self::$i];
	}
}
function get_called_class(){
	return class_tools::get_called_class();
}
}
