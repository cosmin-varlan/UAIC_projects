<?php
    class Template{
        var $assignedValues = array();
        var $tpl;

        function __construct($_path = ''){
            if(!empty($_path)){
                if (file_exists($_path)){
                    $this -> tpl = file_get_contents($_path);                    
                }
                else
                {
                    echo "<b>Template Error:</b> template file not found: ".$_path;
                }
            }
        }

        function assign($searchString, $replaceValue){
            if(!empty($searchString)){
                $this->assignedValues[strtoupper($searchString)] = $replaceValue;
            }
        }

        function show(){            
            if(count($this->assignedValues) > 0)
            {
                foreach($this->assignedValues as $key => $value)
                {
                    $this->tpl = str_replace('{'.$key.'}', $value, $this->tpl);
                }
            }
            return $this->tpl;
        }


    }