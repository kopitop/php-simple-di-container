<?php

class FactoryView {
    public function show($str) {
        echo "<p>".$str."</p>";
    }
}

class UsersModel {
    public function get() {
        return [
            (object) ['ho' => 'Nguyen', 'ten' => 'Van A'],
            (object) ['ho' => 'Nguyen', 'ten' => 'Thi B'],
        ];
    }
}

/**
* @Inject view
*/
class Navigation {
    public function show() {
        $this->view->show('
            <a href="#" title="Home">Home</a> | 
            <a href="#" title="Home">Danh Muc</a> | 
            <a href="#" title="Home">Lien He</a>
        ');
    }
}
/**
* @Inject usersModel
* @Inject view
*/
class Content {
    private $title;
    
    public function __construct($title) {
        $this->title = $title;
    }

    public function show() {  
        $users = $this->usersModel->get();
        $this->view->show($this->title);
        foreach($users as $user) {
            $this->view->show($user->ho." ".$user->ten);
        }
    }
}
/**
* @Inject navigation
* @Inject content
*/
class PageController {
    public function show() {
        $this->navigation->show();
        $this->content->show();
    }
}

class DI {
    
    private static $map;
    
    private static function addToMap($key, $obj) {
        if(self::$map === null) {
            self::$map = (object) array();
        }
        self::$map->$key = $obj;
    }
    public static function bindValue($key, $value) {
        self::addToMap($key, (object) array(
            "value" => $value,
            "type" => "value"
        ));
    }
    public static function bindClass($key, $value, $arguments = null) {
        self::addToMap($key, (object) array(
            "value" => $value,
            "type" => "class",
            "arguments" => $arguments
        ));
    }
    public static function bindClassAsSingleton($key, $value, $arguments = null) {
        self::addToMap($key, (object) array(
            "value" => $value,
            "type" => "classSingleton",
            "instance" => null,
            "arguments" => $arguments
        ));
    }
    public static function resolve($className, $arguments = null) {
        // checking if the class exists
        if(!class_exists($className)) {
            throw new Exception("DI: missing class '".$className."'.");
        }
        
        // initialized the ReflectionClass
        $reflection = new ReflectionClass($className);
        
        // creating an instance of the class
        if($arguments === null || count($arguments) == 0) {
           $obj = new $className;
        } else {
            if(!is_array($arguments)) {
                $arguments = array($arguments);
            }
           $obj = $reflection->newInstanceArgs($arguments);
        }
        
        // injecting
        if($doc = $reflection->getDocComment()) {
            $lines = explode("\n", $doc);
            foreach($lines as $line) {
                if(count($parts = explode("@Inject", $line)) > 1) {
                    $parts = explode(" ", $parts[1]);
                    if(count($parts) > 1) {
                        $key = $parts[1];
                        $key = str_replace("\n", "", $key);
                        $key = str_replace("\r", "", $key);
                        if(isset(self::$map->$key)) {
                            switch(self::$map->$key->type) {
                                case "value":
                                    $obj->$key = self::$map->$key->value;
                                break;
                                case "class":
                                    $obj->$key = self::resolve(self::$map->$key->value, self::$map->$key->arguments);
                                break;
                                case "classSingleton":
                                    if(self::$map->$key->instance === null) {
                                        $obj->$key = self::$map->$key->instance = self::resolve(self::$map->$key->value, self::$map->$key->arguments);
                                    } else {
                                        $obj->$key = self::$map->$key->instance;
                                    }
                                break;
                            }
                        }
                    }
                }
            }
        }
        
        // return the created instance
        return $obj;
    }
}

DI::bindClass("navigation", "Navigation");
DI::bindClass("content", "Content", array("Content title!"));
DI::bindClass("view", "FactoryView");
DI::bindClassAsSingleton("usersModel", "UsersModel");


$page = DI::resolve("PageController");
$page->show();
