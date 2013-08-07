<?php

class ltsTemplatedata
{
    private $data = false;
    private $type = 0;  // 0 - static, 1 - lts page vars
    
    public function __construct ($t = 0, $d = false)
    {
        if (!$d)
            return (false);
        $this->type = $t;  // type of this data
        $this->data = $d;  // Raw data from file
    } // contructor
    
    
    public function out (&$page_object)
    {
        if ($this->type == 0)
            return ($this->data);
        else {
            $v = $page_object->getValue ($this->data);
            if (!is_a ($v, 'ltsPagedata'))
                return ($v);
            else return ($v->render ($page_object));
        } // template object type
        return (false);
    } // out
    
} // ltsTemplatedata

?>