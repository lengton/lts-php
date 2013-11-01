<?php

class ltsURL
{
    private $request_uri = false;
    private $page_referrer = false;
    private $page_baseurl = false;
    private $page_script = false;
    private $page_server_name = false;
    private $page_url = false;
    private $page_url_count = 0;
    private $page_indx = 0;
    private static $global_vars = false;
    private $page_type = 0;
    private $cacheable = true;	// By default all pages are Disk cacheable
    private $modtime = false;
    private $cache_stat = false;
    private $cache_path = false;
    private $successful_load_path = false;
    
    private $lts_data_path = false;
    private $lts_system_path = false;
    private $pages_path = false;
    private $use_backend = true;   // By default we ALWAYS use a backend


    public function __construct ($cv = false, $target_url = false)
    {
        if ($target_url === false)
            $this->request_uri = $_SERVER['REQUEST_URI'];
        else $this->request_uri = $target_url;
        
        // INITIALIZE THIS PAGE'S URL
        if (isset ($_SERVER['HTTP_REFERER']))
            $this->page_referrer = $_SERVER['HTTP_REFERER'];
        $this->page_baseurl = $this->request_uri;
        
        // EXTRACT GET PARAMETERS
        if (($qp = strpos ($this->request_uri, '?')) !== false) {
            $qstr = substr ($this->request_uri, $qp + 1);
            $this->page_baseurl = substr ($this->request_uri, 0, $qp);
            parse_str ($qstr, $_GET);
        } // HAS GET PARAMETER?
        
        $this->page_script = $_SERVER['SCRIPT_NAME'];
        $this->page_server_name = $_SERVER['SERVER_NAME'];
        
        // Check if we have the same script and baseurl and is a .php script
        if (($this->page_baseurl == $this->page_script) && strpos ($this->page_script, '.php'))
            $this->page_url = explode ('/', $this->page_baseurl);
        else $this->page_url = explode ('/', substr ($this->page_baseurl, strlen ($this->page_script) + 1));
        $this->page_url_count = count ($this->page_url);
        
        // Assign config values (usually called before ltsPage)
        if ($cv && is_array ($cv))
            self::$global_vars = $cv;
            
        // Set LTS data path
        if ($ltsp = $this->getValue ('lts_data_path'))
            $this->lts_data_path = $ltsp;
            
        // Set LTS system path
        if ($ltsp = $this->getValue ('lts_system_path'))
            $this->lts_system_path = $ltsp;            
            
        // Automatically set pages path based on the Loader name
        $default_path = 'data/'.$this->getValue ('ln').'/pages/';
        
        // Override auto generated path if 'pages_path' exists
        if ($ppath = $this->getValue ('pages_path'))
            $default_path = $ppath;
            
        // This is now the script's pages_path
        $this->pages_path = $this->lts_data_path.$default_path;
        
        // Set Cache path
        if ($this->cache_path == false)
            $this->cache_path = $this->lts_data_path.'cache/';
        
        // Check if 'source_prefix' exists
        if ($s = $this->getValue ('source_prefix'))
        {
            $s = trim ($s, '/');
            if (strlen ($s))
                $this->pages_path = $this->pages_path.$s.'/';
        } // affix suffix
        
        // Save Base page path
        if ($this->pages_path)
        {
            $this->setValue ('base_path', $this->pages_path);
            // If Config value is passed, then let's auto-preload
            if ($cv) $this->getPagePath ();
        } // Has Base page path?
    } // contructor
    
    
    public function lastURLItem ($strip = false)
    {
        $u = $this->page_url[$this->page_url_count - 1];
        if ($strip && ($p = strpos ($u, '.')))
            $u = substr ($u, 0, $p);
        return ($u);
    } // lastURLItem
    
    
    public function getCachePath()
    {
        $ln = '';
        if ($ln = $this->getValue ('ln'))
            $ln = $ln.':';
        return ($this->cache_path.$ln);
    } // getCachePath
    
    
    public function getPagesPath()
    {
        return ($this->pages_path);
    } // getPagesPath
    
    
    public function getPageURLCount()
    {
        return ($this->page_url_count);
    } // getPageURLCount
    
    
    public function pageURL ($indx = 0)
    {
        if ($indx === false)
            return ($this->page_url);
            
        if ($this->page_url_count)
            return ($this->page_url[$indx]);
        return (false);
    } // getPageURL
    
    
    public function getModtime()
    {
        return ($this->modtime);
    } // getModtime
    
    
    public function getLtsPath()
    {
        return ($this->lts_data_path);
    } // getLtsPath
    
    
    public function getLtsSystemPath()
    {
        return ($this->lts_system_path);
    } // getLtsSystemPath
    
    
    public function getScript()
    {
        return ($this->page_script);
    } // getScript
    
    
    public function getServerName()
    {
        return ($this->page_server_name);
    } // getServerName
    

    public function getBaseURL()
    {
        return ($this->page_baseurl);
    } // getBaseURL
    
    
    public function getRawURL()
    {
        return ($this->request_uri);
    } // getRawURL
    
    
    public function getURL($dv = '/')
    {
        $url = false;
        if ($this->page_url_count)
        {
            $url = '';
            for ($i = 0; $i < $this->page_url_count; $i++)
                $url .= $dv.$this->page_url[$i];
            $url = trim ($url, $dv);
        } // HAS URL COUNT
        return ($url);
    } // getURL

    
    public function getValue ($key)
    {
        if (array_key_exists ($key, self::$global_vars))
            return (self::$global_vars[$key]);
        return (false);
    } // getValue
    
    
    public function getPageType ()
    {
        return ($this->page_type);
    } // getPageType
    
    
    public function getGlobalVars ()
    {
        return (self::$global_vars);
    } // getGlobalVars
    
    
    public function setValue ($key, $value = true)
    {
        return (self::$global_vars[$key] = $value);
    } // setValue
    
    
    public function isCacheable($v = 0)
    {
        if ($v !== 0)
            $this->cacheable = $v;
        return ($this->cacheable);
    } // isCacheable
    
    
    public function cacheStat ($v = false)
    {
        if ($v !== false)
            $this->cache_stat = $v;
        return ($this->cache_stat);
    } // cacheStat
    
    
    public function useBackend()
    {
        return ($this->use_backend);
    } // useBackend
    
    
    public function getURLPath ($prefix = false)
    {
        // By default, we assume a template based file
        $this->page_type = 0;
        
        // Build path from URL
        $path = false;
        if ($prefix) $path = $prefix;
        
        for ($i = 0; $i < $this->page_url_count; $i++)
        {
            // Check base include restrictions. We can't
            // allow loading from the URL
            if ($prefix && (strpos ($path, 'common.include') !== false))
                return (false);
                
            // We need to check per directory flag files and 'common.includes'
            // Placed on this part so we can check the base /common.includes
            if ($path && is_dir ($path))
            {
                // Check for directory restriction file
                if ($prefix && is_file ($path.'/.deny'))
                    return (false);
                    
                // Check for 'no_cache' 
                if (is_file ($path.'/.no_cache'))
                    $this->cacheable = false;

                // Check for 'no_backend'
                if (is_file ($path.'/.no_backend'))
                    $this->use_backend = false;
                    
                // Check for static files (usually we just need to return the file)
                // and stop on the first files match
                if (($this->page_type == 0) && is_file ($path.'/.static'))
                {
                    $this->setValue ('static_path', $path);
                    $this->page_type = 2;
                } // Check for static files
                    
                // Check for directory Raw include file (usually a PHP script file)
                // and stop on the first files match 
                if (($this->page_type == 0) && is_file ($path.'/.raw'))
                {
                    $this->setValue ('raw_path', $path);
                    $this->page_type = 1;
                } // Check for Raw path
                
                // Store last dir
                $this->setValue ('last_path', $path);
            } // Is path a directory?        
        
            // Build URL
            if (strlen ($this->page_url[$i])) 
            {
                if ($i) $path .= '/';
                $path .= $this->page_url[$i];
            } // Add slashes
            
            // Break out the loop when we have the first match
            // after a .raw or .static switch
            if ($this->page_type && is_file ($path)) 
            {
                $this->cacheable = false;
                return ($path);
            } // RAW type
        } // for
        return ($path);
    } // getURLPath
    
    
    public function clearCachedPath()
    {
        $this->successful_load_path = false;
        return (true);
    } // clearCachedPath
    
    
    public function getPagePath ($loaded = false)
    {
        // Cache last successful load
        if ($this->successful_load_path)
            return ($this->successful_load_path);
            
        if ($path = $this->getURLPath ($this->pages_path))
        {
            if (file_exists ($path) && is_file ($path))
            {
                // Get modification time
                if ($this->modtime == false)
                    $this->modtime = filemtime ($path);
                
                // Path exists, so this page will be marked
                // as cacheable
                if ($this->cache_stat == 0)
                    $this->cache_stat = 1;
                    
                // Cache this and return
                $this->successful_load_path = $path;
                return ($path);
            } // Have we have a physical file?
        } // Has valid path?
        
        if (!$loaded)
        {
            // Force page_type to zero (template based)
            $this->page_type = 0;
            syslog (LOG_NOTICE, 'ltsURL: Path not found: '.$path);
            return ($this->pages_path.'error/404');
        } // Loaded the normal way?
        
        syslog (LOG_NOTICE, 'ltsURL: Was trying to load: '.$path);
        return (false);
    } // getPagePath
    
    
    public function emptyURL()
    {
        // See if we had cached last successful load
        if ($this->successful_load_path)
            return (false);
        if (!$this->getURLPath())
            return (true);
        return (false);
    } // emptyURL
    
    
    public function pageReferrer()
    {
        return ($this->page_referrer);
    } // pageReferrer
    
    
    public function urlMatch ($url, $str = ' class="s"', $echo = true)
    {
        if ($url && (strpos ($this->request_uri, $url) !== false))
        {
            if ($echo) echo $str;
            else return ($str);
        } // Find URL match?
        return ('');
    } // urlMatch
    
} // ltsURL

?>