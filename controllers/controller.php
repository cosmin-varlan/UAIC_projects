<?php
abstract class Controller
{
    protected $model;
    protected $view;

    public function __construct()
    {

        $modelName = str_replace("Controller", "Model", get_class($this));
        if (!class_exists($modelName, true)) {
            echo "$modelName nu a fost definita -> halt !";
            exit();
        };

        $this->model = new $modelName;

        $viewName = str_replace("Controller", "View", get_class($this));
        if (!class_exists($viewName, true)) {
            echo "$viewName nu a fost definita -> halt !";
            exit();
        };
        $this->view = new $viewName;
    }

}