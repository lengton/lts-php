<?php

class ltsPagedata
{
    private $cacheable = true;
    private $has_code = false;
    private $opcodes = 0;
    private $data_path = false;
    private $name = NULL;
    private $data = NULL;
    private $render_data = false;
    private static $template_tag = '<lts:';
    
    
    public function __construct ($k = false, $d = false, $opcode = 0, &$page_object = false, $c = false)
    {
        if (!$k || !$d || (strlen ($d) < 1))
            return (false);
        
        // Has template tag
        if ($page_object && is_a ($page_object, 'ltsPage'))
            self::$template_tag = $page_object->getTemplateTag();
            
        $this->data = $d;  // Raw data from file
        $this->name = $k;  // Name of this page data, usually variable name
        
        // OPERATION CODES VALUES
        // 0x01 - JSMinify the output 
        $this->opcodes = $opcode; // Other operation switches
        
        // Auto classify this data
        if ((strpos ($this->data, '<?') !== false) || (strpos ($this->data, self::$template_tag) !== false)) 
        {
            // Pass this string to the LTS parser--one pass to include
            // page variables. This looks for <*[tag]: -- note the asterisk before the 
            // template tag name. And only works if data is inside PHP tags
            $this->data = $this->parseData ($this->data, 0, true, $page_object);
            
            $this->has_code = true;
            $this->cacheable = $c;  // was this forced to be cacheable?
        } else $this->render_data = true;
    } // contructor
    
    
    public function render (&$page_object = false, $level = 0)
    {
        if (!isset ($this->data) || ($level > 10))
           return ('');
        // Return data ASAP
        if ($this->cacheable)
        {
            // Usually if 'render_data' == true, 
            // means there's no PHP code within
            if ($this->render_data === true)
                return ($this->data);
            // Evaluated code by is cacheable
            if ($this->render_data)
                return ($this->render_data);
        } // Is this data cacheable?
           
        if ($this->has_code && ($page_object && is_a ($page_object, 'ltsPage')))
        {
            // ALWAYS GET THE TEMPLATE TAG FROM THE PAGE OBJECT
            self::$template_tag = $page_object->getTemplateTag();
            $this->render_data = $this->evalData ($level, $page_object);
            return ($this->render_data);
        } // eval code
        
        return ('');
    } // render
    
    
    private function parseData ($data = false, $level = 0, $first_pass = false, &$page_object = false)
    {
        if ($data && $page_object)
        {
            // We now build a new string with appended data
            $ndata = '';
            $lp = $sp = 0;
            $len = strlen ($data);
            $tt = self::$template_tag;

            // Is this a first pass?
            if ($first_pass)
                $tt = '<*'.substr ($tt, 1);
            
            do {
                if (($sp = strpos ($data, $tt, $sp)) !== false)
                {
                    $i = $sp;
                    $vs = false;
                    
                    // Now find the ending tag
                    while (($data[$i] !== '>') && ($i < $len))
                    {
                        if (!$vs && ($data[$i] == ':') && (($i + 1) < $len))
                            $vs = ($i + 1);
                        $i++; 
                    } // while
                    
                    // Did we found it?
                    if ($data[$i] == '>')
                    {
                        $ndata .= substr ($data, $lp, ($sp - $lp));
                        // Do we have a variable name?
                        if ($vs) 
                        {
                            $vn = trim (substr ($data, $vs, ($i - $vs)), '/ ');

                            // Do we have TAG parameters?
                            if ((($opi = strpos ($vn, '(')) !== false) && 
                                (($cpi = strpos ($vn, ')', $opi)) !== false))
                            {
                                // Extract Parameters
                                $tag_params = trim (substr ($vn, ($opi + 1), (($cpi - $opi) - 1)));
                                
                                // Remove Tag parameters from $vn
                                $vn = substr ($vn, 0, $opi).substr ($vn, ($cpi + 1));
                                
                                // Has Tag Parameters?
                                if (strlen ($tag_params))
                                {
                                    $params = explode (',', $tag_params);
                                    if (count ($params) > 1)
                                        $params = array_map ('trim', $params);
                                    else $params = $tag_params;
                                    $page_object->setValue ($vn.'_PARAMS', $params);
                                } // We have tag parameters?
                            } // Has Parameters?

                            $pv = $page_object->getValue ($vn);
                            //$this->log ('>>> ('.$vn.') '.$pv->getData());
                            if (is_a ($pv, 'ltsPagedata'))
                            {
                                if (!$page_object->getValue ('load_once_'.$vn))
                                {
                                    if ($page_object && is_a ($page_object, 'ltsPage'))
                                        $pv = $pv->render ($page_object, $level + 1);
                                    else $this->log ('ltsPagedata: ltsPage Object not found');
                                } else $pv = '';
                            } // Has Value and an ltsPagedata
                            $ndata .= $pv;
                        }  // store variable name    
                        $lp = $sp = ($i + 1);
                    } // we found it
                } // did we find an EG tag?
            } while ($sp);
            
            // Just append the remaining data as type static
            $ndata .= substr ($data, $lp);
            $data = $ndata;
            return ($data);
        } // Has data
        return (false);
    } // parseData
    
    
    private function evalData ($level = 0, &$page_object = false)
    {
        // Evaluate all PHP code first
        $po = $page_object;
        unset ($ltsChunk);
        ob_start();
//        $this->log ($this->data);
        eval ('$ltsChunk=true;?>'.$this->data);
        $data = ob_get_contents();
        ob_end_clean();
        if (!isset ($ltsChunk))
            $this->log ('EvalErr: '.$this->name);
        return ($this->parseData ($data, $level + 1, false, $page_object));
    } // evalData
    
    
    public function out ()
    {
        if ($this->render_data)
            echo $this->render_data;
        else echo '';
    } // out
    
    
    public function has_code()
    {
        return ($this->has_code);
    } // has_code
    
    
    public function getName()
    {
        return ($this->name);
    } // getName
    
    
    public function getData()
    {
        return ($this->data);
    } // getData
    
    
    public function getOpcodes()
    {
        return ($this->opcodes);
    } // getOpcodes 
    
} // ltsPagedata

?>