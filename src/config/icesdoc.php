<?php
/**
 * User: Yirius
 * Date: 2018/1/9
 * Time: 22:12
 */
use think\facade\Env;

return [
    'controller' => [],
    'filter_method' => ['_empty'],
    'path' => Env::get("root_path") . DIRECTORY_SEPARATOR . "public" . DIRECTORY_SEPARATOR . "apidoc" . DIRECTORY_SEPARATOR
];
