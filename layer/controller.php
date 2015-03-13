<?php
/**
 * Created by PhpStorm.
 * User: gaojie
 * Date: 15/2/20
 * Time: 上午9:52
 */
abstract class FN_layer_controller implements FN__auto{
    public function __construct(){

    }
    abstract protected function beforeAction();
    abstract protected function afterAction();

}