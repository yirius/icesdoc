<?php
/**
 * User: Yirius
 * Date: 2018/1/9
 * Time: 21:13
 */

namespace icesdoc;


use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Env;
use think\facade\Log;

class Doc extends Command
{
    protected  $config = [
        'title'=>'APi接口文档',
        'version'=>'1.0.0',
        'copyright'=>'Powered By Yirius',
        'controller' => [],
        'filter_method'=>['_empty'],
        'return_format' => [
            'status' => "200/300/301/302",
            'message' => "提示信息",
        ],
        'path' => ''
    ];

    protected function configure()
    {
        $config = config("icesdoc.");
        if(empty($config)){
            $config = [];
        }
        $this->config = array_merge($this->config, $config);
        $this->setName('ices:doc')
            ->addOption("path", "p", Option::VALUE_OPTIONAL, "path to doc", null)
            ->setDescription('make the doc which controller is in the config');
    }

    protected function execute(Input $input, Output $output)
    {
        $listMenu = $this->getList();
        foreach($listMenu as $i => $v){
            $content = view(__DIR__ . DS . "apidoc.html", ['_render' => $v])->getContent();
            $name = str_replace("\\", '-', $v['class']);
            if(file_put_contents($this->config['path'] . DS . $name . ".html", $content)){
                $output->comment($name . ".html ouput success");
            }else{
                $output->comment($name . ".html have some error");
            }
        }
        $output->comment("ouput done");
    }

    /**
     * 获取接口列表
     * @return array
     */
    public function getList()
    {
        $controller = $this->config['controller'];
        $list = [];
        foreach ($controller as $class)
        {
            if(class_exists($class))
            {
                $module = [];
                $reflection = new \ReflectionClass($class);
                $doc_str = $reflection->getDocComment();
                $doc = new DocParser();
                $class_doc = $doc->parse($doc_str);
                $module =  $class_doc;
                $module['class'] = $class;
                $method = array_merge($reflection->getMethods(\ReflectionMethod::IS_PROTECTED), $reflection->getMethods(\ReflectionMethod::IS_PUBLIC));
                $filter_method = array_merge(['__construct'], $this->config['filter_method']);
                $module['actions'] = [];
                if(!empty($reflection->getParentClass())){
                    $module['parent'] = $reflection->getParentClass()->getName();
                }
                foreach ($method as $action){
                    if(!in_array($action->name, $filter_method) && $action->class == $class)
                    {
                        $doc = new DocParser();
                        $doc_str = $action->getDocComment();
                        if($doc_str)
                        {
                            $action_doc = $doc->parse($doc_str);
                            $action_doc['name'] = $class."::".$action->name;
                            if(array_key_exists('title', $action_doc)){
                                if(array_key_exists('module', $action_doc)){
                                    $key = array_search($action_doc['module'], array_column($module['actions'], 'title'));
                                    if($key === false){
                                        $action = $module;
                                        $action['title'] = $action_doc['module'];
                                        $action['module'] = $action_doc['module'];
                                        $action['actions'] = [];
                                        array_push($action['actions'], $action_doc);
                                        array_push($module['actions'], $action);
                                    }else{
                                        array_push($module['actions'][$key]['actions'], $action_doc);
                                    }
                                }else{
                                    array_push($module['actions'], $action_doc);
                                }
                            }
                        }
                    }
                }
                if(array_key_exists('group', $module)){
                    $key = array_search($module['group'], array_column($list, 'title'));
                    if($key === false){ //创建分组
                        $floder = [
                            'title' => $module['group'],
                            'description' => '',
                            'package' => '',
                            'class' => '',
                            'actions' => []
                        ];
                        array_push($floder['actions'], $module);
                        array_push($list, $floder);
                    }else{
                        array_push($list[$key]['actions'], $module);
                    }
                }else{
                    array_push($list, $module);
                }
            }
        }
        return $list;
    }

    /**
     * 文档目录列表
     * @return array
     */
    public function getModuleList()
    {
        $controller = $this->config['controller'];
        $list = [];
        foreach ($controller as $class) {
            if (class_exists($class)) {
                $reflection = new \ReflectionClass($class);
                $doc_str = $reflection->getDocComment();
                $doc = new DocParser();
                $class_doc = $doc->parse($doc_str);
                if(array_key_exists('group', $class_doc)){
                    $key = array_search($class_doc['group'], array_column($list, 'title'));
                    if($key === false){ //创建分组
                        $floder = [
                            'title' => $class_doc['group'],
                            'children' => []
                        ];
                        array_push($floder['children'], $class_doc);
                        array_push($list, $floder);
                    }
                    else
                    {
                        array_push($list[$key]['children'], $class_doc);
                    }
                }else{
                    array_push($list, $class_doc);
                }
            }
        }
        return $list;
    }

    /**
     * 获取类中指导方法注释详情
     * @param $class
     * @param $action
     * @return array
     */
    public function getInfo($class, $action)
    {
        $action_doc = [];
        if($class && class_exists($class)){
            $reflection = new \ReflectionClass($class);
            $doc_str = $reflection->getDocComment();
            $doc = new DocParser();
            $class_doc = $doc->parse($doc_str);
            $class_doc['header'] = isset($class_doc['header'])? $class_doc['header'] : [];
            $class_doc['param'] = isset($class_doc['param']) ? $class_doc['param'] : [];
            if($reflection->hasMethod($action)) {
                $method = $reflection->getMethod($action);
                $doc = new DocParser();
                $action_doc = $doc->parse($method->getDocComment());
                $action_doc['name'] = $class."::".$method->name;
                $action_doc['header'] = isset($action_doc['header']) ? array_merge($class_doc['header'], $action_doc['header']) : $class_doc['header'];
                $action_doc['param'] = isset($action_doc['param']) ? array_merge($class_doc['param'], $action_doc['param']) : $class_doc['param'];
            }
        }
        return $action_doc;
    }
}
