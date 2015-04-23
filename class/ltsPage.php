<?php

class ltsPage extends ltsBase
{
    protected static $template_tag = false;
    protected static $global_vars = array ();
    private $template_data = false;
    
    protected static $template_path = false;
    
    // Cache Status
    // 0 - Do nothing
    // 1 - This page is cacheable (physically exists)
    // 2 - Cache current loaded page
    // 3 - Load data from cache
    protected static $socket = false;
    protected static $socket_ref = 0;
    
    private $from_cache = false;
    private $ltsURL = false;	// copy of ltsURL object


    public function __construct ($url = false, $target_url = false)
    {
        // HACK FOR CLI SCRIPTS
        $for_web = true;
        if ($url == 'no_web') $for_web = false;
            
        // CALL PARENT CONSTRUCTOR
        parent::__construct($for_web);
        
        // It is important to have an ltsURL object. Some public functions
        // offerred by the ltsPage object depends on this!
        if ($url && is_a ($url, 'ltsURL'))
            $this->ltsURL = $url;
        else if ($url && is_a ($url, 'ltsPage'))
        {
           $this->ltsURL = new ltsURL ($url->getGlobalVars(), $target_url);
        } // Do we have a URL string?
            
        // Merge arrays, we get this from the ltsURL object!
        if ($this->ltsURL)
        {
            self::$global_vars = array_merge ($this->ltsURL->getGlobalVars(), self::$global_vars);
            
            // Do class initialization (One-time evaluation)
            if (self::$template_tag == false)
            {
                // Set paths depending on the page loader
                self::$template_path = $this->ltsURL->getLtsPath().'data/'.$this->getValue ('ln').'/template/';
                
                // Get Template tag string
                if ($ts = $this->getValue ('template_tag'))
                    self::$template_tag = '<'.$ts.':';
            } // Template tag?
        } // has ltsURL
        
        // Attach to Shared Memory
        // If none exists, create ltsBackend process
        // We only start one... The backend takes care of the rest
        // if it needs to create clones of itself
        if ($this->getValue ('use_backend') && $this->ltsURL && ($this->shmem_get() == false))
            parent::start_backend ($this->ltsURL->getLtsSystemPath(), $this->getGlobalVars());
            
        // Check for Mail header array
        if (!$this->mail_headers() && isset (self::$global_vars['lts_mail_headers']))
        {
            $this->mail_headers (self::$global_vars['lts_mail_headers']);
            // Unset this
            unset (self::$global_vars['lts_mail_headers']);
        } // Set mail header
        
        // Check for Mail params array
        if (!$this->mail_params() && isset (self::$global_vars['lts_mail_params']))
        {
            $this->mail_params (@self::$global_vars ['lts_mail_params']);
            // We need to unset this
            unset (self::$global_vars['lts_mail_params']);
        } // Set mail params            
            
        // Update socket reference count, if any
        if (self::$socket)
            self::$socket_ref++;
    } // contructor
    
    
    public function __destruct()
    {
        if (self::$socket && self::$socket_ref)
        {
            if ((--self::$socket_ref) <= 0)
            {
                self::$socket_ref = 0;
                socket_close (self::$socket);
                self::$socket = false;
            } // Auto close sockets if any
        } // Has Socket?
    } // destructor
    
    
    public function lastURLItem ($strip = false)
    {
        if ($this->ltsURL)
            return ($this->ltsURL->lastURLItem ($strip));
        return (false);
    } // lastURLItem
    
    
    public function getScript()
    {
        if ($this->ltsURL)
            return ($this->ltsURL->getScript());
        return (false);
    } // getScript
    
    
    public function getTemplateTag()
    {
        return (self::$template_tag);
    } // getTemplateTag
    
    
    public function setTemplateTag ($tt)
    {
        self::$template_tag = $tt; 
    } // setTemplateTag
    
    
    public function getServerName()
    {
        if ($this->ltsURL)
            return ($this->ltsURL->getServerName());
        return (false);
    } // getServerName
    
    
    public function loadedFromCache()
    {
        if ($this->ltsURL)
        {
            if ($this->from_cache)
                return ('Cached');
            if ($this->ltsURL->cacheStat() == 3)
                return ('Backend');
            return ('Disk');
        } else return ('Unknown');
    } // isCached
    
    
    public function getBaseURL()
    {
        if ($this->ltsURL)
            return ($this->ltsURL->getBaseURL());
        return (false);
    } // getBaseURL
    
    
    public function getRawURL()
    {
        if ($this->ltsURL)
            return ($this->ltsURL->getRawURL());
        return (false);
    } // getRawURL
    
    
    public function getURL ($dv = '/')
    {
        if ($this->ltsURL)
            return ($this->ltsURL->getURL ($dv));
        return (false);
    } // getURL
    
    
    public function getURLObject ()
    {
        return ($this->ltsURL);
    } // getURLObject
    
    
    public function getURLPath ($prefix = false)
    {
        if ($this->ltsURL)
            return($this->ltsURL->getURLPath ($prefix));
        return (false);
    } // getURLPath

    
    public function out ($pv)
    {
        echo $this->page_out ($pv);
    } // out
    
    
    public function render ($pt = false, $str_out = false)
    {
        // Get Page path and type. Type is stored on page_type variable.
        if ($this->ltsURL && ($page_path = $this->ltsURL->getPagePath ($str_out)))
        {
            $out = true;           
            switch ($this->ltsURL->getPageType())
            {
                // Normal PHP script file. We just do an include for execution
                case 1 :
                    // Force disable caching
                    header ('Cache-control: no-store, no-cache, must-revalidate');
                    header ('Pragma: no-cache');
                                  
                    // Load Common  
                    $this->loadCommon();
                    $po = $this;                
                    include_once ($page_path);
                    break;
                    
                // Defaults to a page that needs a template
                default:

                    // Initial page template value. For use in Backend caching
                    $page_template_value = false;
                    
                    // Load page if it exists in cache and user is not logged in
                    // CACHE LEVEL 1 - Static pages
                    if ($this->ltsURL->isCacheable() && !$this->getSessionUID() 
                        && ($cache_path = $this->ltsURL->getCachePath().$this->ltsURL->getURL('_'))
                        && ($cfp = @fopen ($cache_path, 'r')))
                    {
                        // Get last modification time
                        $mtime = false;
                        if ($cstat = fstat ($cfp))
                            $mtime = $cstat['mtime'];
                        fclose ($cfp);

                        // Check if cache is > 60 minutes old
                        // Or source is newer than cached data
                        if ($mtime && ((intVal ((time() - $mtime) / 60) > 60) || ($this->ltsURL->getModtime() > $mtime)))
                        {
                            // Remove from Cache if stale
                            $this->log ('ltsPage: Disk cache stale ['.$this->ltsURL->getURL().']');
                            unlink ($cache_path);
                        } else {
                            $this->xSendFile ($cache_path);
                            $this->from_cache = true;
                            return (true);
                        } // Load from cache?
                    } // In Cache?
                    
                    // Load cached data from backend process, if any
                    // This primes the global_var and template_data arrays
                    // Usually only loads type ltsPagedata and ltsTemplatedata from a recently
                    // loaded page
                    // CACHE LEVEL 2 - Dynamic pages
                    if ($this->ltsURL->useBackend())
                        $this->loadFromBackend ();   // Sets $this->ltsURL->cacheStat() == 3, if no error loading
                    else {
                        $this->log ('ltsPage: skipping backend cache...');
                    } // Load from backend?

                    if ($this->ltsURL->cacheStat () != 3)
                    {
                        // Load Common
                        $this->loadCommon();
                        
                        // We only save "page_init"... this is special since
                        // when we load common.includes, this gets executed before being
                        // (possibly) over written by the page data we're loading.

                        // common.includes are loaded as we traverse the directory tree
                        // and builds up the global_var array but ALWAYS over writes the last
                        // 'page_init' with the most recent one.
                        //
                        // Let's store this on a special pre-fixed global array name
                        // and use it only if 'page_init' exists and is of type 'ltsPagedata'
                        
                        // Execute common 'page_init' if exists. Remember, this can 
                        // over-write global variables and render it dirty
                        if (($v = $this->getValue ('lts_common_include_page_init')) && is_array ($v))
                        {
                            // Iterate all page inits and execute them in order
                            foreach ($v as $pv)
                            {
                                if (is_a ($pv, 'ltsPagedata'))
                                    $pv->render ($this);
                            } // FOREACH
                        } else if (is_a ($v, 'ltsPagedata')) {
                            $v->render ($this); 
                        } // Do we have common.include page inits?
                    } else {
                        // Copy current the page_template value, if any
                        $page_template_value = $this->getValue ('page_template');
                        
                        // Loaded from backend cache?
                        if (($v = $this->getValue ('lts_common_include_page_init')) && is_array ($v))
                        {
                            // Iterate all page inits and execute them in order
                            foreach ($v as $pv)
                            {
                                if (is_a ($pv, 'ltsPagedata'))
                                    $pv->render ($this);
                            } // FOREACH
                        } else if (is_a ($v, 'ltsPagedata')) {
                            $v->render($this);
                        } // Do we have common.include page inits?
                    } // Cache data?
                
                    // Load Page Data, only if we don't have data from cache
                    // 'loadPageData' reads from disk (and or db)
                    if ($this->ltsURL->cacheStat () != 3)
                    {
                        $this->loadPageData ($page_path);

                        // Let's cache this, if we need to, so we can
                        // minimize dirty variables resulting from parsed embedded PHP code
                        if (self::$socket && ($this->ltsURL->cacheStat () == 2))
                        {
                            // We have an opened socket and valid connection
                            // DATA <size>
                            $ga = base64_encode (serialize (self::$global_vars));
                            $this->backendSend ('DATA '.strlen ($ga));
                            // Now send the data, pass 'true' so we don't add the '\n' in the end
                            $this->backendSend ($ga, true);
                        } // Send to server?
                    } // Do we need to cache data?
                    
                    // Execute 'page_init' if exists.
                    // This gets executed even before the 
                    // template page gets parsed
                    if (($v = $this->getValue ('page_init')) && is_a ($v, 'ltsPagedata'))
                        $v->render ($this);
                    
                    // We're ready to load the template
                    // If we have it on backend cache, then let's skip 
                    // local reading // from disk (or db)
                    if ($this->ltsURL->cacheStat () != 3)
                    {
                        // If we don't have a template string, 
                        // lets see the global_vars
                        if (!$pt || (strlen ($pt) < 1))
                        {
                            if (($pt = $this->getValue ('page_template')) == false)
                                return (0);
                        } // Load the default template, if any
                        // Trim path character?
                        $pt = self::$template_path.trim ($pt, '/');
                        
                        // Parse template file, if there's none, then stop!
                        if ($this->parseTemplate ($pt) === false)
                            return (false);
                        
                        // Let's cache this, if we have template data
                        if ($this->template_data && self::$socket && ($this->ltsURL->cacheStat () == 2))
                        {
                            // We have an opened socket and valid connection
                            // TEMPLATE <size>
                            $ta = base64_encode (serialize ($this->template_data));
                            $this->backendSend ('TEMPLATE '.strlen ($ta));
                            $this->backendSend ($ta, true);
                        } // Cache template
                    } else {
                        // Check 'page_template' value for changes. A page might modify the value
                        // programmatically and still load the cached template data from the backend.
                        // If this value changes, then let's load it from disk
                        $current_ptv = $this->getValue ('page_template');
                        if ($current_ptv && ($page_template_value != $current_ptv))
                        {
                            $current_ptv = self::$template_path.trim ($current_ptv, '/');
                            // Skip rendering if we don't have a template 
                            if ($this->parseTemplate ($current_ptv) === false)
                                return (false);
                        } // Load template from disk?
                    }  // Load from backend cache?
                       
                    // PAGE CACHING, caching to disk usually is meant for static pages
                    // We ALWAYS cache page and template data
                    if (!$str_out && ($this->getValue ('no_cache') || !$this->ltsURL->isCacheable()))
                    {
                        header ('Cache-control: no-store, no-cache, must-revalidate');
                        header ('Pragma: no-cache');
                        $this->ltsURL->isCacheable (false);
                    } // CACHE PAGE?
                    
                    // Render page back to browser
                    // Also caches the page if $this->cacheable
                    $out = $this->renderPage ($str_out);
                    
                    // Execute 'page_done' if exists
                    if (($v = $this->getValue ('page_done')) && is_a ($v, 'ltsPagedata'))
                        $v->render ($this);
                        
                     // Close Network Connection, if any
                     if (self::$socket && ($this->ltsURL->cacheStat () > 1))
                         $this->backendSend ('CLOSE');
            } // SWITCH
            return ($out);
        } // Has a valid Page Path
        return (false);
    } // render
    
    
    private function loadCommon ()
    {
        if ($this->ltsURL)
        {
            // Load directory includes, if any. 
            // We already have checks done at 'getPagePath' 
            $dir_path = $this->ltsURL->getPagesPath();
            $this->loadPageData ($dir_path.'common.include', 0, true);
            for ($i = 0; $i < $this->ltsURL->getPageURLCount(); $i++)
            {
                $dir_path .= $this->ltsURL->pageURL($i).'/';

                if (!@is_dir ($dir_path))
                    continue;

                $this->loadPageData ($dir_path.'common.include', 0, true);
            } // FOR
        } // has ltsURL
        return (false);
    } // loadCommon
    
    
    public function renderPage ($str_out = false)
    {
        $out = '';
        for ($i = 0; $i < count ($this->template_data); $i++)
        {
            if (method_exists ($this->template_data[$i], 'out'))
                $out .= $this->template_data[$i]->out ($this);
        } // FOR
            
        if (!$str_out) 
        {
            echo $out;
            
            if ($this->ltsURL)
            {
                // Cache pages output data, if not logged in
                if (!$this->getSessionUID() && $this->ltsURL->isCacheable() && $this->ltsURL->cacheStat ())
                    file_put_contents ($this->ltsURL->getCachePath().$this->ltsURL->getURL('_'), $out);
                return (true);
            } // Has ltsURL?
        } else return ($out);
    } // renderPage
    
    
    public function parseTemplate ($pt)
    {
        if (!$pt || (strlen ($pt) < 1))
            return (false);
            
        if (is_file ($pt) && ($fp = fopen ($pt, 'r')))
        {
            $lp = 0;
            $this->template_data = array();  // Create an empty array
            $data = '';
            
            // looking for template_tag '<lts:', if not then we skip
            while (($fline = fgets ($fp)) !== false)
            {
                $lp = $sp = 0;
                $len = strlen ($fline);
                do 
                {
                    if (($sp = strpos ($fline, self::$template_tag, $sp)) !== false)
                    {
                        $i = $sp;
                        $vs = false;
                        // Now find the ending tag
                        while (($fline[$i] !== '>') && ($i < $len))
                        {
                            if (!$vs && ($fline[$i] == ':') && (($i + 1) < $len))
                                $vs = ($i + 1);
                            $i++;
                        } // while
                        
                        // Did we found it?
                        if ($fline[$i] == '>')
                        {
                            $data .= substr ($fline, $lp, ($sp - $lp));
                        
                            // Save template chunk
                            if (strlen ($data)) {
                                $this->template_data[] = new ltsTemplatedata (0, $data);
                                $data = '';
                            } // save chunck
                            
                            // Do we have a variable name?
                            if ($vs) 
                            {
                                $vn = trim (substr ($fline, $vs, ($i - $vs)), '/ ');
                                $this->template_data[] = new ltsTemplatedata (1, $vn);
                            }  // store variable name    
                            $lp = $sp = ($i + 1);
                        } // we found it
                    } // found hot tag?
                } while ($sp);
                // Was there a tag found within the line?
                if ($lp) $fline = substr ($fline, $lp);
                $data .= $fline;
            } // while
            // Just append the remaining data as type static
            if (strlen ($data))
                $this->template_data[] = new ltsTemplatedata (0, $data);
            return (true);
        } // has template file
    } // parseTemplate
    

    public function loadPageData ($page_path = false, $level = 0, $common_include = false)
    {
        // Do go into 5 levels deep of includes
        if ($level > 5) return (false);

        if ($this->ltsURL && file_exists ($page_path) && is_file ($page_path) && ($fp = fopen ($page_path, 'r')))
        {
            unset ($var);
            $i = 0;        // Line counter
            $opcode = 0;   // Addtional Operation codes. See ltsPagedata for details
            $data = '';
            $mline = $php_eval = false;
            while (($fline = fgets ($fp)) !== false)
            {
                $i++;  // Increment Line counter
                // Do we need to include a file?
                if ($fline[0] == '@')
                {
                    // Extract include path
                    $ip = trim (substr ($fline, 1), " \n");
                    
                    // When the path doesn't start with a '/' we always
                    // include from the directory where this file is
                    // being read.
                    $lp = rtrim ($this->ltsURL->getPagesPath (), '/');
                    if (($ip[0] != '/') && ($cp = $this->getValue ('last_path')))
                        $lp = $cp;
                    $path = $lp.'/'.$ip;
                    $this->loadPageData ($path, ($level + 1), $common_include);
                    continue;
                } // include file
                
                // Is this a start of a page variable?
                if ($fline[0] == ':')
                {
                    // Insert into Global Vars
                    if (isset ($var) && strlen ($data)) 
                    {
                        // Is this a var pointer?
                        if ($var[strlen ($var) - 1] == '*') 
                            $data = $this->getFileContents ($data);
                            
                        // Process Opcodes, if any
                        switch ($opcode)
                        {
                            case 1 : // Pass to Minifier?
                                $data = JSMin::minify ($data);
                                break;
                        } // SWITCH
                        
                        // Assign to Global Variable
                        // But first check if this comes from 'common.include'
                        // We need to serialize ALL the 'page_init' vars into an array
                        if ($common_include && ($var == 'page_init'))
                        {
                            // Check if we have an existing array of 'page_init's
                            if (!isset (self::$global_vars['lts_common_include_page_init']))
                                self::$global_vars['lts_common_include_page_init'] = array();
                            self::$global_vars['lts_common_include_page_init'][] = new ltsPagedata ($var, trim ($data), $opcode, $this);
                        } else self::$global_vars [$var] = new ltsPagedata ($var, trim ($data), $opcode, $this);
                        $data = '';
                        $opcode = 0;
                    } // Insert Data
                    
                    // Extract Header line
                    if (($sp = strpos ($fline, " ", 1)) !== false) 
                    {
                        $data = trim (substr ($fline, $sp));
                        if (strlen ($data))
                        {
                            // Check for additional commands
                            switch ($oc = $data[0])
                            {
                                case '~' :
                                case '!' :
                                case '#' :
                                    if ($oc == '~') $opcode |= 1;
                                    // Adjust data to exclude the char code
                                    $data = substr ($data, 1);
                            } // SWITCH
                        } // Has Data?
                    } else {
                        $sp = strlen ($fline);
                        $data = '';
                    }
                    $var = trim (substr ($fline, 1, $sp));
                    continue;
                } // if start of var
                $data .= $fline;
            } // while
            
            // Add trailing data...
            if (strlen ($data)) 
            {
                if (isset ($var))
                {
                    if ($var[strlen ($var) - 1] == '*')
                        $data = $this->getFileContents ($data);
                        
                    // Process Opcodes, if any
                    switch ($opcode)
                    {
                        case 1 : // Pass to Minifier?
                            $data = JSMin::minify ($data);
                            break;
                    } // SWITCH
                    
                    // Assign to Global Variable
                    // But first check if this comes from 'common.include'
                    // We need to serialize ALL the 'page_init' vars into an array
                    if ($common_include && ($var == 'page_init'))
                    {
                        // Check if we have an existing array of 'page_init's
                        if (!isset (self::$global_vars['lts_common_include_page_init'])) 
                            self::$global_vars['lts_common_include_page_init'] = array();
                        self::$global_vars['lts_common_include_page_init'][] = new ltsPagedata ($var, trim ($data), $opcode, $this);
                    } else self::$global_vars [$var] = new ltsPagedata ($var, trim ($data), $opcode, $this);
                } // Is $var set?
            } // Insert data
            fclose ($fp);
        } // File exists?
        return (true);
    } // loadPageData
    
    
    private function getFileContents ($path)
    {
        if ($this->ltsURL)
        {
            $data = '';
            $path = trim (trim($path), '/');
            $file_path = $this->ltsURL->getPagesPath ().$path;
            if (file_exists ($file_path) && is_file ($file_path) && ($fp = fopen ($file_path, 'r'))) 
            {
                while (($lines = fgets ($fp)) !== false)
                    $data .= $lines;
                fclose ($fp);
            } // Load file content
            return ($data);
        } // Has ltsURL
        return (false);
    } // getFileContents
    
    
    public function page_out ($pv)
    {
        $v = $this->getValue ($pv);
        return ($v ? $v : '');
    } // page_out
    
    
    public function valueExists ($key)
    {
        return (array_key_exists ($key, self::$global_vars));
    } // valueExists
    
    
    public function getValue ($key)
    {
        if (array_key_exists ($key, self::$global_vars))
            return (self::$global_vars[$key]);
        return (false);
    } // getValue
    
    
    public function unsetValue ($key)
    {
        if (array_key_exists ($key, self::$global_vars))
        {
            unset (self::$global_vars[$key]);
            return (true);
        } // Unset value
        return (false);
    } // unsetValue
    
    
    public function getGlobalVars ()
    {
        return (self::$global_vars);
    } // getGlobalVars
    
    
    public function setTemplateValue ($key, $value)
    {
        if ($key)
            self::$global_vars [$key] = new ltsPagedata ($key, $value);
        return ($value);
    } // setTemplateValue
    
    
    public function setValue ($key, $value = true)
    {
        return (self::$global_vars[$key] = $value);
    } // setValue
    
    
    public function emptyURL()
    {
        if ($this->ltsURL)
            return ($this->ltsURL->emptyURL());
        return (false);
    } // emptyURL
    
    
    public function phoneFormat ($s, $ac = false)
    {
        if (!$s || (strlen ($s) < 7))
            return (false);
            
        $phone_tokens = array ('-', ' ', '(', ')','+');
        
        $s = strtolower(trim ($s));
        $hp = ($s[0] == '+' ? true : false);
        $s = str_replace ($phone_tokens, '', trim ($s));
        $pl = $sl = strlen ($s);
        
        // AREA CODE?
        if ($sl == 7) {
            if ($ac === false) $ac = '510'; // HERCULES DEFAULT AREA CODE
            $s = $ac.$s;
            $pl = $sl = strlen ($s);
        } // APPEND AREA CODE?
        
        if (ctype_digit ($s) && ($sl > 6))
        {
            // START FROM THE RIGHT MOST (BASE FIRST)
            $ps = '';
            $pl -= 4;
            $ps = substr ($s, $pl);
            
            // 3 DIGIT CODE
            if ($pl)
            {
                $r = 3;
                if ($pl < $r) $r = $pl;
                $pl -= $r;
                $ps = substr ($s, $pl, $r).'-'.$ps;
            } // FINISH 7 DIGITS
            
            if ($pl) 
            {
                $r = 3;
                if ($pl < $r) $r = $pl;
                $pl -= $r;
                $ps = '('.substr ($s, $pl, $r).') '.$ps;
            } // AREA CODE
            
            // PREPEND REMAINING DIGITS
            if ($pl) $ps = substr ($s, 0, $pl).' '.$ps;
            
            if ($hp) $ps = '+'.$ps;
            return ($ps);
        } // IS ALL DIGITS
        return (false);
    } // phoneFormat
    
    
    /**
    Validate an email address.
    Provide email address (raw input)
    Returns true if the email address has the email 
    address format and the domain exists.
    Taken from http://www.linuxjournal.com/article/9585?page=0,3
    by Douglas Lovell
    */
    public function checkEmail ($email)
    {
        $notValid = 0;
        $atIndex = strrpos($email, "@");
        if (is_bool($atIndex) && !$atIndex)
        {
            $notValid = 1;
        } else {
            $domain = substr ($email, $atIndex + 1);
            $local = substr ($email, 0, $atIndex);
            $localLen = strlen ($local);
            $domainLen = strlen ($domain);
            if (($localLen < 1) || ($localLen > 64))
            {
                $notValid = 2;
            } else if (($domainLen < 1) || ($domainLen > 255)) {
                $notValid = 3;
            } else if (($local[0] == '.') || ($local[$localLen-1] == '.')) {
                $notValid = 4;
            } else if (preg_match('/\\.\\./', $local)) {
                $notValid = 5;
            } else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain)) {
                $notValid = 6;
            } else if (preg_match('/\\.\\./', $domain)) {
                $notValid = 7;
            } else if (!preg_match('/^(\\\\.|[A-Za-z0-9!#%&=\\/$\'*+?^{}|~.-])+$/', str_replace("\\\\","",$local))) {
                if (!preg_match('/^"(\\\\"|[^"])+"$/', str_replace("\\\\","",$local))) {
                    $notValid = 8;
                }
            }
            if (($notValid == 0) && !(checkdnsrr($domain.'.',"MX") || checkdnsrr($domain.'.',"A"))) {
                $notValid = 9;
            }
        }
        return $notValid;
    } // checkEmail
    
    
    public function generateCode ($c = 0)
    {
        if ($c < 1) $c = 4;
        $ct = "ABDEFGHJLMNQRTYabdefghijkmnqrtuy23456789";
        $cd = '';
        for ($i = 0; $i < $c; $i++)
            $cd .= $ct{ mt_rand (0, (strlen ($ct) - 1)) };
            
        // FORCE NO-CACHING
        $this->setValue ('no_cache', true);
        if ($this->ltsURL)
            $this->ltsURL->isCacheable (false);
            
        return (parent::sessionReplace ('vcode', $cd));
    } // generateCode
    
    
    public function urlPrefix ($pf, $s, $cs = false)
    {
        if ($this->ltsURL && $this->ltsURL->getPageURLCount ())
        {
            $e = $this->ltsURL->pageURL (0);
            if (($p = strpos ($e, '.')) !== false)
                $e = substr ($e, 0, $p);
            // Override the first URL element and
            // check the script name instead
            if ($cs)
                $e = trim ($this->ltsURL->getScript(), '/');
            if ($e == $pf) echo $s;
        } // Has ltsURL object?
    } // urlPrefix
    
    
    public function getCountry ($cid = false, $col = 0)
    {
        $n = 'name';
        switch ($col)
        {
            case 1 : $n = 'abv'; break;
            case 2 : $n = 'id'; break;
        } // SWITCH
        $q = 'SELECT '.$n.' FROM country WHERE id='.intVal ($cid).' LIMIT 1';
        if ($r = parent::dbExec ($q))
        {
            $rw = parent::dbGetRow ($r);
            return ($rw[0]);
        } // HAS DATA?
        return (false);
    } // getCountry
    
    
    public function generateCountry ($n, $id = false, $e = false, $attr = false)
    {
        $c = 0;
        $q = 'SELECT id,name FROM country';
        $s = '<select'.($attr ? ' '.$attr : '').' name="'.$n.'">';
        if ($r = parent::dbExec ($q))
        {
            while ($rw = parent::dbGetRow ($r))
                $s .= '<option'.($id == $rw[0] ? ' selected="true"' : '').' value="'.$rw[0].'">'.$rw[1].'</option>';
        } // HAS COUNTRY?
        $s .= '</select>';
        if ($e) {
            echo ($s);
            return ($c);
        } else return ($s);
    } // generateCountry
    
    
    public function pageReferrer()
    {
        if ($this->ltsURL)
            return ($this->ltsURL->pageReferrer());
        return (false);
    } // pageReferrer
    
    
    public function trimWords ($str, $opt = false)
    {
        if ($str)
        {
            $rw = explode (' ', $str);
            $str = '';
            foreach ($rw as $words) 
            {
                $w = trim ($words);  
                if (!strlen ($w))  
                    continue;
                    
                // Transformation options
                switch ($opt)
                {
                    case 1 : // Upcase words
                        $w = ucwords ($w);
                        if ((strlen ($w) > 2) && ($w[0] == 'M') && ($w[1] == 'c'))
                            $w[2] = strtoupper ($w[2]);
                        else if ((strlen ($w) > 2) && ctype_alpha ($w[0]) && ($w[1] == "'") && ctype_alpha ($w[2]))
                            $w[2] = strtoupper ($w[2]);
                        break;
                        
                } // OPTIONS
                $str .= $w.' ';
            } // FOREACH
            $str = trim ($str);
        } // Has String?
        return ($str);
    } // trimWords
    
    
    public function loadPage ($path = false)
    {
        if ($path)
        {
            // Determine file path
            $load_path = rtrim ($this->getValue ('base_path'), '/');
            if ($path[0] == '/')
                $load_path = $load_path.$path;
            else $load_path = $this->getValue ('last_path').'/'.$path;
            // Load Page data
            return ($this->loadPageData ($load_path));
        } // Has page path to load
        return (false);
    } // loadPage
    
    
    public function loadVariable ($name = false, $eval = false, $force = false)
    {
        if ($name)
        {
            $obj = $this->getValue ($name);
            if ($obj && is_a ($obj, 'ltsPagedata'))
            {
                if (!$this->getValue ('load_once_'.$name) || $force)
                {
                    $this->setValue ('load_once_'.$name, 1);
                    $data = $obj->render($this);
                    if (($obj->has_code() && $eval) || $force)
                    {
                        $po = $this;
                        eval ('?><?php '.$data.' ?>');
                        return (true);
                    } // Data has no PHP script tag and $eval flag is true
                } // Already loaded?
            } // If Object not an ltsPagedata, then just disregard
        } // Has name
        return (false);
    } // loadVariable
    
    
    public function urlMatch ($url, $str = ' class="s"', $echo = true)
    {
        if ($this->ltsURL)
            return ($this->ltsURL->urlMatch ($url, $str, $echo));
        return ('');
    } // urlMatch
    
    
    public function getTuple ($s, $w = 0, $c = ':')
    {
        if ($s && (($dv = strpos ($s, $c)) !== false)) 
        {
            switch ($w)
            {
                case 0 :
                    return (substr ($s, 0, $dv));
                    
                case 1 : 
                    return (substr ($s, ($dv + 1)));
                    
                default:
                    $a = array();
                    $a[0] = substr ($s, 0, $dv);
                    $a[1] = substr ($s, ($dv + 1));
                    return ($a);
            } // SWITCH
        } // Valid tuple?
        return (false);  
    } // getTuple
    
    
    public function tupleSearch ($sv, $a, $w = 0, $c = ':')
    {
        if ($a && is_array ($a))
        {
            $i = 0;
            foreach ($a as $av)
            {
                if (($v = $this->getTuple ($av, $w, $c)) && ($v == $sv))
                    return ($i);
                $i++;
            } // FOREACH
        } // We have an array
        return (false);
    } // tupleSearch
    
    
    private function pagingURL ($u, $p)
    {
        $js = false;
        $url = $u.$p;
        if (strpos ($u, 'js:', 0) !== false)
        {
            $u = substr ($u, 3);
            $js = true;
        } // Is a Javascript onclick url?
        if (strpos ($u, '$') !== false)
            $url = str_replace ('$', $p, $u);
        if ($js)
           $href='href="javascript:void(0)" onclick="'.$url.'"';
        else $href = 'href="'.$url.'"';
        return ($href);
    } // pagingURL
    
    
    public function generatePaging ($u, $l, $c, $np, $cp, $echo = true, $pbody = true)
    {
        /*
         *  $u - URL (when an $ exists, this is replaced by the page number)
         *  $l - Label (e.g. Photo, Album)
         *  $cp - Current position
         *  $c - total count
         *  $np - number of entries per page
         */
        $class = ($pbody ? 'lts_paging' : 'lts_tpaging');
        $out = '';
        $tpages = intVal($c / $np);
        if ($c % $np) $tpages++;
        $tbx = 9;
        $j = 0;
        $out = '<div id="lts_paging" class="'.$class.'">
                <a class="dir lts_paging_button '.($cp > 1 ? '' : 'disabled').'"'.($cp > 1 ? ' '.$this->pagingURL ($u, ($cp - 1)) : '').'>Previous</a>';
                
        if ($pbody)
        {                
            $pos_first = $pos_last = false;
            for ($i = 0; $i < $tbx; $i++)
            {
                $j++;
                if ($j > $tpages) break;
                if (!$pos_first && ($tpages > $tbx) && (($i >= 2) && ($cp > ($tbx - 3)))) 
                {
                    $out .= '<span class="dots">...</span>';
                    if ($cp >= ($tpages - ($tbx / 2)))
                        $j = ($tpages - ($tbx - 3));
                    else $j = ($cp - 2);
                    $pos_first = true;
                } // FIRST
                
                if (!$pos_last && ($i >= ($tbx - 2)) && (($j + 1) < $tpages)) 
                {
                    $out .= '<span class="dots">...</span>';
                    $j = ($tpages - 1);
                    $pos_last = true;
                } // LAST
                
                if ($cp == $j) 
                {
                    $out .= '<span class="lts_paging_button cur">'.$j.'</span>';
                    continue;
                } // Current Position?
                
                $out .= '<a class="lts_paging_button" '.$this->pagingURL ($u, $j).'>'.$j.'</a>';
            } // FOR
        } // Do we need to output page buttons?
        
        // PRINT LABEL
        $es = ($c > 1 ? ($l{strlen ($l) - 1} == 's' ? 'es' : 's') : '');
        $out .= '  <a class="dir lts_paging_button '.(($cp + 1) <= $tpages ? '' : 'disabled').'"'.(($cp + 1) <= $tpages ? ' '.$this->pagingURL ($u, ($cp + 1)) : '').'>Next</a>';
        if ($pbody)
            $out .= '<div class="label">'.$c.' '.$l.$es.'</div>';
        $out .= '   </div>';
              
        // OUTPUT?
        if ($echo) 
        {
            echo $out;
            return (true);
        } // ECHO Data
        return ($out);
    } // generatePaging
    
    
    private function readFromBackend($size)
    {
        // Get read data size
        $rsize = intVal ($size);
        if ($rsize > 0)
        {
            $tread = 0;
            $data = '';
            while ($tread < $rsize)
            {
                if (($rdata = socket_read (self::$socket, ($rsize - $tread), PHP_BINARY_READ)) === false)
                {
                    $this->log ('ltsPage ('.$cmd.'): '.socket_strerror(socket_last_error()));
                    return (false);
                } // Read data? 
                $data .= $rdata;
                $tread += strlen ($rdata);
            } // WHILE 
            if (strlen ($data))
                return ($data);
        } // Has read size?
        return (false);
    } // readFromBackend
    
    
    public function loadFromBackend()
    {
        if ($this->ltsURL)
        {
            // Check first if the reference page
            // is cacheable, not a 404
            if (!$this->ltsURL->cacheStat ())
                return (false);
                
            // Connect to a backend process?
            // Create a socket resource
            if (!self::$socket)
            {
                if (($server_port = $this->getBackendPort ()) && (self::$socket = socket_create (AF_INET, SOCK_STREAM, SOL_TCP)))
                {
                    // Client connect?
                    if (socket_connect (self::$socket, '127.0.0.1',  $server_port) == false)
                        $this->log ('ltsPage: Failed to connect to server port '.$server_port);
                    else self::$socket_ref++;
                } // Has client socket?
            } // Get Socket
            
            if (self::$socket)
            {
                // Bit flag to determine if all data from server
                // was loaded 
                // 00000001 - Data part loaded
                // 00000010 - Template part loaded
                $data_parts = 0;
                
                // REQUEST URL
                // GET <prefix>:<url> <modtime>
                //$this->backendSend ('GET '.$this->getValue ('loadedFrom').':'.$this->getURL().' '.$this->ltsURL->getModtime ());
                $this->backendSend ('GET '.$this->getValue ('ln').':'.$this->getURL().' '.$this->ltsURL->getModtime ());
                do
                {
                    // GET SERVER RESPONSE
                    $sres = $this->backendReceive ();
                    // $this->log ('Server (Raw): '.$sres);
                    switch ($sres)
                    {
                        case 'NONE' : 
                            // Backend says is not in Cache, so lets send a copy of our data
                            $this->ltsURL->cacheStat (2);
                            
                        case 'DONE' :
                            // server done with communication?
                            // See if we have all data sets loaded
                            // 00000011
                            if ($data_parts == 3)
                                $this->ltsURL->cacheStat (3);
                            break 2; // Exit the while loop too
                            
                        default:
                            // We expect a command
                            if (strlen ($sres) && (($sp = strpos ($sres, ' ')) !== false))
                            {
                                $cmd = substr ($sres, 0, $sp);
                                $params = substr ($sres, ($sp + 1));
                                switch ($cmd)
                                {
                                    case 'DATA' : // PAGE DATA
                                        // Mark this as loaded
                                        $data_parts = ($data_parts | 1);
                                        if ($data = $this->readFromBackend ($params))
                                            self::$global_vars = unserialize (base64_decode ($data));
                                        break;
                                        
                                    case 'TEMPLATE' : // PAGE TEMPLATE
                                        // Mark this as loaded too
                                        $data_parts = ($data_parts | 2);
                                        if ($data = $this->readFromBackend ($params))
                                            $this->template_data = unserialize (base64_decode ($data));
                                        break;
                                } // SWITCH
                            } // Has command?
                            break;
                    } // SWITCH
                } while ($sres);
                
                // CLOSE THE CONNECTION, we don't need to send or receive to backend
                if ($this->ltsURL->cacheStat () != 2)
                    $this->backendSend ('CLOSE');
                return (true);
            } // We have a socket connection?
        } // Has ltsURL Object?
        return (false);
    } // loadFromBackend
    
    
    public function backendSend ($str = false, $bin = false)
    {
        if (self::$socket && $str && strlen ($str))
        {
            if (!$bin) $str = trim ($str)."\n";
            if (socket_write (self::$socket, $str, strlen ($str)) === false)
            {
                $this->log ('Backend send failed: ['.trim ($str).']');
                return (false);
            } // Has write errors?
            return (true);
        } // Has String?
        return (false);
    } // backendSend
    

    public function backendReceive ()
    {
        if (self::$socket)
        {
            do
            {
                $data = false;
                if (($data = socket_read (self::$socket, 512, PHP_NORMAL_READ)) === false)
                {
                    $this->log ('ltsPage: '.socket_strerror(socket_last_error()));
                    return (false);
                } // Has write errors?
                // Pre-process read data
                if (strlen ($data = trim ($data)) < 1)
                    continue;
            } while ($data === false);
            return ($data);
        } // Has String?
        return (false);
    } // backendReceive
    
    
    private function getFileExt ($path)
    {
        if ($path && strlen ($path))
        {
            if (($ep = strrpos ($path, '.')) !== false)
                return (strtolower (substr ($path, ($ep + 1))));
        } // Has path?
        return (false);
    } // getFileExt
    
    
    private function xSendFile ($path = false)
    {
        if ($path)
        {
            header('X-Sendfile: '.$path);
            // Extract file extension
            if ($ext = $this->getFileExt ($path))
                header ('Content-type: '.$GLOBALS['lts_mime_types'][$ext]);
            else header ('Content-type: application/octet-stream');
        } // Has Path?
    } // xSendFile
    
    
    public function parseCSSfile ($path = false)
    {
        if ($path && $this->parseTemplate ($path))
        {
            $out = '';
            for ($i = 0; $i < count ($this->template_data); $i++)
                $out .= $this->template_data[$i]->out($this);
            return ($out);
        } // Has CSS file path?
        return (false);
    } // parseCSSFile
    
    
    public function issetPOST (&$v, $pv, $av = false, $t = false)
    {
        if ($t === false)
        {
            // In this option, if $t === false and $v is not false
            // Skip assignment of values
            if ($v) return (false);
            if (isset ($_POST[$pv]))
            {
                if ($av)
                    return ($v = $av);
                else return ($v = $pv);
            } // POST is set?
        } else {
            if (isset ($_POST[$pv]))
            {
                return ($v |= $av);
            } // POST is set?
        } // Type of assignment
        return (false);
    } // issetPOST
    
    
    // CHECKS HTTP VARIABLES FOR REGISTERED ACTIONS
    public function checkAction (&$action)
    {
        if ($action && is_array ($action))
        {
            foreach ($action as $key)
            {
                if (isset ($_POST[$key]))
                    return ($key);
                if (isset ($_GET[$key]))
                    return ($key);
            } // FOREACH
        } // Type of assignment
        return (false);
    } // checkAction
    
    
    public function registerTimeout ($fn)
    {
        $script = $this->ltsURL->getPagesPath ().$this->getValue ('callback_path').'/'.$fn;
        // Check if the script has been registered
        $db_script = $this->dbGetValue ('session', 'value', '(sid='.$this->dbStr (self::$backend_sid).') AND 
                                                             (name='.$this->dbStr ('lts_callback_script').') AND 
                                                             (value='.$this->dbStr ($script).')');
        // Only allow if 'callbacks_enable' is set                                                        
        if (($db_script != $script) && $this->valueExists ('callbacks_enable'))
        {
            $this->log ('ltsPage: Registering callback script ['.$fn.']');
            $this->dbInsert ('session', 'name,value,sid', $this->dbStr ('lts_callback_script').','.$this->dbStr ($script).','.$this->dbStr (self::$backend_sid));
            return (true);
        } // Registered?
        return (false);
    } // registerTimeout
    
    
    public function unregisterTimeout ($fn)
    {
        $script = $this->ltsURL->getPagesPath ().$this->getValue ('callback_path').$fn;
        $this->dbDelete ('session', '(sid='.$this->dbStr (self::$backend_sid).') AND 
                                     (name='.$this->dbStr ('lts_callback_script').') AND
                                     (value='.$this->dbStr ($script).')');
        return (true);
    } // unregisterTimeout
    
    
    public function mangle ($str, $key = false)
    {
        if ($key === false)
        {
            // ALWAYS GET THIS FROM COOKIES
            if (isset ($_COOKIE['lts_sid']))
                $key = $_COOKIE['lts_sid'];
            else $key = $this->sid;
        } else {
            // CONVERT STRING TO BYTES -- WE ASSUME VANILLA STRINGS
            $pkey = '';
            foreach (str_split ($key) as $c)
                $pkey .= dechex (ord ($c));
            $key = sprintf ("%-32s", $pkey);
        } // Do we have a key?
        $bkey = pack ('H32', $key);
        return (base64_encode (@mcrypt_encrypt (MCRYPT_RIJNDAEL_128, $bkey, trim ($str), MCRYPT_MODE_CBC)));
    } // mangle
    
    
    public function unmangle ($str, $key = false)
    {
        if ($key === false)
        {
            // ALWAYS GET THIS FROM COOKIES
            if (isset ($_COOKIE['lts_sid']))
                $key = $_COOKIE['lts_sid'];
            else $key = $this->sid;
        } else {
            // CONVERT STRING TO BYTES -- WE ASSUME VANILLA STRINGS
            $pkey = '';
            foreach (str_split ($key) as $c)
                $pkey .= dechex (ord ($c));
            $key = sprintf ("%-32s", $pkey);
        } // Do we have a key?
        $bkey = pack ('H32', $key);
        $data = base64_decode ($str);
        return (trim (@mcrypt_decrypt (MCRYPT_RIJNDAEL_128, $bkey, $data, MCRYPT_MODE_CBC)));
    } // unmangle
    
} // ltsPage

?>
