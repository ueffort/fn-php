fn-php
======

PHP Frame of Freedom Nature

# 框架特点
1. 命名规范，实现类自动加载
2. 路径管理，通过符合直接调用到实际环境中的真实路径
3. 类加载接口：通过接口设计，实现类加载的自动单例处理，工厂类扩展，及静态类的调用
4. 配置文件全局管理，并自动更新路径，实现单个配置节点的替换及获取
5. server及platform设计：通过配置文件实现server实例的自动管理，对于platform（各个云平台）实现服务的自定义扩展，整体解耦服务与业务的关系

# 框架思想
* 简化常规开发中的调用
* 实现不同运行环境的配置变更
* 以低耦合的方式嵌入已存在的系统
* 主要是提供常规功能的简化调用

# 调用框架
1. 在入口文件引入fn.php的框架文件
2. 获取配置文件并存入框架管理`FN::setConfig($config)`
3. 进行项目的初始化`FN::initProject();`

# 命名说明：
* 按项目路径规划类名：文件夹+文件名  ->  controller/main.class.php  ->  `class` controller_main
* 调用方式：`FN::i('controller.main')`
* 子类调用：`class` controller_mainChild -> `FN::i('controller.main|Child')` 命名有洁癖者可以忽略该功能
* 系统类说明：
    * 按框架目录开始：文件夹+文件名  ->  tools/route.class.php  ->  `class` FN_tools_route
    * 调用方式：`FN::F('tools.route')`

# 类加载接口：
* 一共3个类加载接口：
    * FN__factory:需实现getFactory接口，根据调用参数，自动传入参数
    * FN__single:需实现getInstance接口，如果无调用参数，则框架自动实现单例模式，如果有参数则需类自己维护单例（不推荐添加调用参数）
    * FN__auto:每次调用都返回一个新的类实例
    * 如果没有符合以上接口，则直接返回类名
* 在接口中没有实现具体接口定义，为了满足任意参数的需求

# 基本扩展类
* tools.route:提供基本的链接路由
* tools.logs:提供日志的接口
* tools.session:实现redis的session接口，方便在系统扩展web服务的session共享
* layer.sql:实现简易的orm，通过PDO的接口实现
* FN_exception:全局框架异常处理