<?php

class ltsBackend extends ltsBase
{
    static private $backend_port = 7500;
    static private $backend_cache = false;
    static private $alarm_interval = 10;   // 10 SECONDS, called 6 times a minute
    static private $terminate = false;
    static private $do_callbacks = false;
    static private $do_check_flags = false;
    static private $disk_cache_path = false;
    static public $ip_addr = '127.0.0.1';     // By default, we bind locally
    static public $loader_path = false;
    
    protected $shmem_id = false;
    private $process_slot = false;
    private $bit_pos = false;
    private $time_start = false;
    private $socket = false;
    private $server_port = false;
    static private $cycles = 0;
    private $callback_functions = false;
    
    private $currentURL = false;
    private $currentModtime = false;
    private $i_am_master = false;
    
    
    public function __construct ($backend_count = 0, $loader_path = false)
    {
        // CALL PARENT CONSTRUCTOR
        parent::__construct();
        
        // INITIALIZE VARIABLES
        if ($backend_count)
        {
            // Usually if we are passed a 'backend_count' then we are the MASTER
            $this->i_am_master = true;
            self::$backend_count = $backend_count;
            
            // Create callback function container
            $this->callback_functions = array();
        } // Set Backend count
            
        // Memory Cache is an associative array
        self::$backend_cache = array ();
        
        // Attach to signal handler
        declare (ticks = 1);
        pcntl_signal (SIGALRM, array (&$this, 'signal_handler'));
        pcntl_signal (SIGTERM, array (&$this, 'signal_handler'));
        pcntl_signal (SIGHUP, array (&$this, 'signal_handler'));
        pcntl_signal (SIGUSR1, array (&$this, 'signal_handler'));
        pcntl_signal (SIGABRT, array (&$this, 'signal_handler'));
        pcntl_signal (SIGCHLD, array (&$this, 'signal_handler'));
        
        if ($loader_path)
        {
            // Unset this variable so it wouldn't 
            // continue loading the LTS loader
            unset ($_SERVER['SCRIPT_NAME']);
            
            @include_once ($loader_path);
            if (isset ($page_vars))
            {
                // Do we need to bind to a specific IP address?
                if (isset ($page_vars['backend_ip']))
                    self::$ip_addr = $page_vars['backend_ip'];
                    
                self::$loader_path = $loader_path;
                if ($this->i_am_master)
                {
                    self::$disk_cache_path = $page_vars['lts_data_path'].'cache/';
                    $this->mail_headers (@$page_vars['lts_mail_headers']);
                    $this->mail_params (@$page_vars['lts_mail_params']);
                } // Am I a master?
            } // A normal LTS loader config file?
        } // Has Loader path?
        
        // Set the first alarm signal
        if ($this->i_am_master)
            pcntl_alarm (self::$alarm_interval);
    } // contructor
    
    
    public function __destruct()
    {
        $this->detach();
    } // destructor
    
    
    public static function signal_handler ($sig)
    {
        switch ($sig)
        {
            case SIGALRM :
                //syslog (LOG_NOTICE, 'ltsBackend: PID '.getmypid().' got alarm signal ('.self::$cycles++.')');
                self::$do_callbacks = true;
                break;
                
            case SIGTERM :
                self::$terminate = true;
                //syslog (LOG_NOTICE, 'ltsBackend: PID '.getmypid().' setting terminate flag');
                break;
            
            case SIGHUP :
                self::$do_check_flags = true;
                //syslog (LOG_NOTICE, 'ltsBackend: PID '.getmypid().' caught HUP signal (BROADCAST)');
                break;
                
            case SIGCHLD :
                //syslog (LOG_NOTICE, 'ltsBackend: PID '.getmypid().' caught child signal');
                break;
                
            default:
                //syslog (LOG_NOTICE, 'ltsBackend: PID '.getmypid().' caught signal '.$sig);
        } // SWITCH
    } // signal_handler
    
    
    private function init()
    {
        // Acquire semaphore ID, if none
        if (!$this->sem_id)
            $this->sem_id = sem_get (self::$sem_key);
            
        // Determine my process slot;
        // Lock down reading and writing
        if ($this->sem_id && sem_acquire ($this->sem_id))
        {
            // Check for existence of Shared memory
            if ($this->shmem_get() == false)
                $this->shmem_create();
            
            // Check if I have my PID
            if ($this->my_pid == false)
                $this->my_pid = getmypid();
            
            // Get shared memory data
            $memdata = $this->shmem_read();
            
            // Iterate through the proces slot list
            // O(N) and expensive but once we have our index value
            // operations take O(1).
            $first_zero = false;
            for ($i = 0; $i < $memdata['count']; $i++)
            {
                $sd = $memdata[$i];
                
                // Get the first zero
                // if we found our PID from the list, then 
                // we don't care about this anyway
                if (($first_zero === false) && ($sd[0] == 0))
                    $first_zero = $i;
                    
                if ($sd[0] == $this->my_pid)
                {
                    $this->log ('ltsBackend: PID '.$this->my_pid.' found at slot #'.$i);
                    $this->process_slot = $i;
                    
                    // Wait... if I could find myself here, then lets exit
                    // Release semaphone
                    sem_release ($this->sem_id);
                    return ($this->process_slot);
                } // FOUND MY PID?
            } // FOR
            
            // Check if we don't have a process slot
            if (($first_zero !== false) && ($this->process_slot === false))
            {
                // Lets get this slot
                $this->process_slot = $first_zero;
                
                $dpid = pack ('L', $this->my_pid);
                if (shmop_write ($this->shmem_id, $dpid, (parent::$shmem_header_size + ($this->process_slot * 10))))
                    $this->log ('Acquired slot #'.intVal ($this->process_slot).' for PID '.$this->my_pid);
                else $this->process_slot = false;
            } // WE DON'T HAVE ONE
            
            // Create Local Socket
            if ($this->process_slot !== false)
            {
                $port_bind = false;
                if ($this->socket = socket_create (AF_INET, SOCK_STREAM,  SOL_TCP))
                {
                    // Iterate ports to Bind
                    for ($port = self::$backend_port; $port < (self::$backend_port + 20); $port++)
                    {
                        if (@socket_bind ($this->socket, self::$ip_addr,  $port))
                        {
                            $port_bind = $port;
                            break;
                        } // Bind?
                    } // FOR - socket
                
                    // Do we have a port?
                    if ($port_bind !== false)
                    {
                        $this->log ('ltsBackend: PID '.$this->my_pid.' bind to Port '.$port_bind);
                        // Lets begin Listening
                        if (socket_listen ($this->socket) == false) {
                            $this->log ('Error listening on port #'.$port_bind);
                            $port_bind = false;
                        } // Listen 
                    } // Has Port binded?
                } // Has socket?
            
                // Listening to port?
                if ($port_bind)
                {
                    // Copy port
                    $this->server_port = $port_bind;
                
                    // Write port # to Shared memory block
                    $dport = pack ('L', $this->server_port);
                    if (shmop_write ($this->shmem_id, $dport, (parent::$shmem_header_size + ($this->process_slot * 10)) + 4))
                    {
                        $this->log ('ltsBackend: PID '.$this->my_pid.' (#'.$this->process_slot.') is listening on Port '.$this->server_port);
                        
                        // Flag that I'm alive!
                        $abits = $memdata['alive'];
                        $abits = $abits | (1 << $this->process_slot);
                        $adata = pack ('L', $abits);
                        shmop_write ($this->shmem_id, $adata, 12);
                    } else {
                        $this->log ('ltsBackend: PID '.$this->my_pid.' failed to write Port '.$this->server_port);
                        socket_close ($this->socket);
                        $this->socket = $this->server_port = false;
                    } // Did we write successfully?
                } // Has Listened port
            } else $this->log ('ltsBackend: PID '.$this->my_pid.' failed to get backend slot'); 
                
            // Release semaphone
            sem_release ($this->sem_id);
        } // Acquire semaphore?
        return ($this->process_slot);
    } // init
    
    
    private function doCallback()
    {
        if (self::$do_callbacks && $this->i_am_master)
        {
            // Disable Callbacks
            self::$do_callbacks = false;
            
            // Retrieve registered callbacks from session
            $q = 'SELECT value FROM session WHERE (sid='.$this->dbStr (parent::$backend_sid).') AND
                                                  (name='.$this->dbStr ('lts_callback_script').')';
            if (($r = $this->dbExec ($q)) && $this->dbNumRows ($r))
            {
                while ($rw = $this->dbGetRow ($r))
                {
                    $path = $rw[0];
                    // Check if we already have it local
                    if (!isset ($this->callback_functions[$path]))
                    {
                        // Does the callback file exists?
                        if (file_exists ($path))
                        {
                            // Create an entry
                            $this->callback_functions[$path]['created'] = time();		// Time created/added
                            $this->callback_functions[$path]['path'] = $path;			// Path of script
                            $this->callback_functions[$path]['execute'] = false;
                            $this->callback_functions[$path]['function'] = false;
                            
                            // Get function's content
                            if ($func_body = file_get_contents ($path))
                            {
                                $this->callback_functions[$path]['call_count'] = 0;
                                $this->callback_functions[$path]['function'] = create_function ('$po, $script_path', $func_body);
                                $this->callback_functions[$path]['execute'] = true;		// Can we execute the function?
                            } // Has function body content
                        } // Does file exists?
                    } // Exists in Memory Cache?
                } // WHILE
            } // CHECK SESSION FOR REGISTERED CALLBACKS?
            
            // Do the callbacks for already registered function
            foreach ($this->callback_functions as &$cbf)
            {
                // EXECUTE ONE AFTER THE OTHER
                // Callback functions should be designed to
                // execute and end fast. If we need longer execution time, 
                // Use a forked backend process for it. And NEVER re-enable
                // the 'execute' flag
                if ($cbf['execute'])
                {
                    // Disable the execute flag... The function should re-enable it
                    // if it wants to be called (executed) again.
                    $cbf['execute'] = false;
                    $cbf['call_count']++;
                    $func = $cbf['function'];
                    
                    // Execute the function
                    if ($func($this, $cbf['path']))
                    {
                        $cbf['execute'] = true;
                    } // Do we need to re-enable callback?
                } // Has Execute flag on?                
            } // FOREACH
            
            // Re-enable the alarm signal
            if ($this->i_am_master)
                pcntl_alarm (self::$alarm_interval);
            return (true);
        } // Do we need to perform callbacks?
        
        return (false);
    } // doCallback
    
    
    private function doEventLoop()
    {
        while (!self::$terminate) // *** BREAK LEVEL 1 ***
        {
            // Do we need to do a callback?
            $this->doCallback();
            
            // Temporary storage for incoming cache data
            $GET_PARAMS = false;
            
            // Flag that we're not busy
            $this->setBusy (false);
            
            // Accept connection from client...
            if (!$this->socket || (($conn = @socket_accept ($this->socket)) == false))
            {
                // Do we need to do a callback?
                $this->doCallback();
                //$this->log ('ltsBackend: PID '.$this->my_pid.' socket accept error on port '.$this->server_port);
                continue;
            } // Socket error?
            
            // Did we have the terminate flag?
            if (self::$terminate)
            {
                socket_close ($conn);
                break;
            } // Terminate?
            
            // Flag that we're busy
            $this->setBusy (true);
            
            // Do we need to check our flags?
            if (self::$do_check_flags)
            {
                socket_close ($conn);
                $this->checkFlags ();
                continue;
            } // Check flags
            
            // Do read from the client
            do {  // *** BREAK LEVEL 2 ***
                if (($cdata = @socket_read ($conn, 2048, PHP_NORMAL_READ)) === false)
                {
                    $this->log ('ltsBackend: PID '.$this->my_pid.' '.socket_strerror(socket_last_error()));
                    socket_close ($conn);
                    break 1;
                } // READ FROM CLIENT
                
                // Pre-process read data
                if (strlen ($cdata = trim ($cdata)) < 1)
                    continue;
                    
                //$this->log ('Client (Raw): '.$cdata);                    
                    
                // PROCESS IN COMING COMMANDS
                switch ($cmd = strtoupper ($cdata))  // *** BREAK LEVEL 3 ***
                {
                    // CLEAR ALL CACHE -- INCLUDING DISK CACHE
                    case 'CLEARALL' :
                        socket_close ($conn);
                        $this->broadcastCommand ($cmd);
                        break 2;
                        
                    // CLEAR BACKEND CACHE
                    case 'CLEAR' :
                        socket_close ($conn);
                        $this->broadcastCommand ($cmd);
                        break 2;
                        
                    // RELOAD CALLBACK FUNCTIONS
                    case 'RELOAD' :
                        socket_close ($conn);
                        $this->broadcastCommand ($cmd);
                        break 2;

                    case 'KILL' :
                        socket_close ($conn);
                        $this->sendTermSignal();
                        break 3;
                        
                    // RETURN MEMORY ALLOCATION
                    case 'MEM' :
                        $mem = memory_get_usage();
                        $this->log ('ltsBackend: PID '.$this->my_pid.' (#'.$this->process_slot.') memory used '.number_format($mem).' bytes');
                        $this->clientSend ($conn, $mem);
                        socket_close ($conn);
                        break 2;
                        
                    // EXIT SERVER PROCESS
                    case 'END' :
                        $this->log ('ltsBackend: PID '.$this->my_pid.' (#'.$this->process_slot.') end run loop');
                        socket_close ($conn);
                        break 3;
                    
                    // CLOSE CLIENT CONNECTION
                    case 'CLOSE' :
                        // Check to see if we have pending Cache data 
                        // to be commited into storage
                        if ($GET_PARAMS)
                            $this->cacheStore ($GET_PARAMS);
                            
                        // Close our socket
                        socket_close($conn);
                        break 2;
                        
                    // CLIENT REQUEST COMMANDS -- Multiple parameters
                    default :
                        if (($spos = strpos ($cdata, ' ')) !== false)
                        {
                            $cmd = substr ($cdata, 0, $spos);
                            $params = substr ($cdata, ($spos + 1));
                            
                            // CLIENT COMMANDS
                            switch ($cmd) // *** BREAK LEVEL 4 ***
                            {
                                // DUMP CACHE DATA
                                case 'DUMP' :
                                    if (!isset (self::$backend_cache[$params]))
                                    {
                                        // CHECK THE DATABASE
                                        if ($rw = $this->dbBackend ($params))
                                            self::$backend_cache[$params] = unserialize (base64_decode($rw[0]));
                                    } // Get Data
                                    
                                    // Now let's dump data
                                    if (isset (self::$backend_cache[$params]))
                                    {
                                        $fpath = '/tmp/LTS:'.str_replace ('/', '_', $params);
                                        if ($fp = fopen ($fpath, 'w'))
                                        {
                                            fwrite ($fp, self::$backend_cache[$params]['DATA']);
                                            fwrite ($fp, "\n");
                                            fwrite ($fp,  self::$backend_cache[$params]['TEMPLATE']);
                                            fclose ($fp);
                                            $this->log ('ltsBackend: PID '.$this->my_pid.' (#'.$this->process_slot.') data dumped at ['.$fpath.']');
                                            socket_close ($conn);
                                            break 3;     
                                        } // Open file
                                    } // DUMP CACHE DATA TO DISK
                                    break;
                                    
                                // GET CACHE DATA
                                case 'GET' :
                                    // Do we have it in cache?
                                    $GET_PARAMS = $this->extractGetParams ($params);
                                    if ($this->inCache ($GET_PARAMS))
                                    {
                                        $this->sendCacheData ($conn, $GET_PARAMS);
//                                        $this->log ('ltsBackend: PID '.$this->my_pid.' (#'.$this->process_slot.') in cache ['.$GET_PARAMS['url'].']');
                                    } else $this->clientSend ($conn, 'NONE');
                                    break;
                                    
                                // RECEIVE CACHE GLOBAL DATA
                                case 'DATA' :
                                    $rsize = intVal ($params);
                                    if ($GET_PARAMS && $rsize)
                                    {
                                        if ($data = $this->clientReceive ($conn, $rsize))
                                        {
                                            $GET_PARAMS['data'] = $data;
                                            $GET_PARAMS['data_size'] = $rsize;
                                        } // Cache data
                                    } // Is from a previous GET?
                                    break;
                                    
                                // RECEIVE CACHE TEMPLATE DATA
                                case 'TEMPLATE' :
                                    $rsize = intVal ($params);
                                    if ($GET_PARAMS && $rsize)
                                    {
                                        if ($data = $this->clientReceive ($conn, $rsize))
                                        {
                                            $GET_PARAMS['template'] = $data;
                                            $GET_PARAMS['template_size'] = $rsize;
                                        } // Cache template
                                    } // From a previous GET?
                                    break;
                            } // SWITCH
                        } // Do we have a command?
                } // SWITCH
            } while (!self::$terminate);
            
            // Just in case, flag that we're not busy
            $this->setBusy (false);
        } // WHILE
        
        // Close our main socket
        if ($this->socket) {
            socket_close ($this->socket);
            $this->socket = false;
        } // Close main socket
        
        $this->log ('ltsBackend: PID '.$this->my_pid.' (#'.$this->process_slot.') event loop terminated.');
    } // doEventLoop

    
    private function cacheStore (&$gp)
    {
        if ($gp && is_array ($gp))
        {
            // Check if we received both data from client
            if (isset ($gp['data']) && isset ($gp['template']))
            {
                // Check if we have an existing entry in our cache
                if (!isset (self::$backend_cache[$gp['url']]))
                {
                    self::$backend_cache[$gp['url']] = array();
                    self::$backend_cache[$gp['url']]['access'] = 0;
                    self::$backend_cache[$gp['url']]['mtime'] = $gp['modtime'];
                    $this->log ('ltsBackend: PID '.$this->my_pid.' (#'.$this->process_slot.') caching ['.$gp['url'].']');
                } // Allocate cache slot
                
                // Store serialized version in memory
                self::$backend_cache[$gp['url']]['DATA'] = $gp['data'];
                self::$backend_cache[$gp['url']]['DATA_SIZE'] = $gp['data_size'];
                self::$backend_cache[$gp['url']]['TEMPLATE'] = $gp['template'];
                self::$backend_cache[$gp['url']]['TEMPLATE_SIZE'] = $gp['template_size'];
                
                // Store this data to the database backend
                $bdata = base64_encode (serialize (self::$backend_cache[$gp['url']]));
                if ($dbmt = $this->dbGetValue ('backend', 'modtime', 'name='.$this->dbStr ($gp['url'])))
                {
                    $l = 'retained';
                    if ($dbmt != $gp['modtime']) {
                        $this->dbUpdate ('backend', 'modtime='.$gp['modtime'].',data='.$this->dbStr ($bdata).',access=0', 'name='.$this->dbStr ($gp['url']));
                        $l = 'update';
                    } // DB Update?
                    $this->log ('ltsBackend: PID '.$this->my_pid.' (#'.$this->process_slot.') DB '.$l.' ['.$gp['url'].']');
                } else {
                    $this->dbInsert ('backend', 'name,modtime,data', $this->dbStr ($gp['url']).','.$gp['modtime'].','.$this->dbStr ($bdata));
                    $this->log ('ltsBackend: PID '.$this->my_pid.' (#'.$this->process_slot.') DB insert ['.$gp['url'].']');
                } // Store to Database?
                return (true);
            } // Do we have cache and template data?
        } // Did we get a GET_PARAMS array?
        return (false);
    } // cacheStore
    
    
    private function sendCacheData (&$conn, &$gp)
    {
        if ($conn && $gp)
        {
            // Increment hit count
            self::$backend_cache[$gp['url']]['access']++;
            
            // Send Global array data
            if (@self::$backend_cache[$gp['url']]['DATA_SIZE'])
            {
                // Inform client that we're sending
                $this->clientSend ($conn, 'DATA '.self::$backend_cache[$gp['url']]['DATA_SIZE']);
                
                // Send page data, binary send
                $this->clientSend ($conn, self::$backend_cache[$gp['url']]['DATA'], true);
            } // SEND PAGE DATA
            
            // Send Template data
            if (@self::$backend_cache[$gp['url']]['TEMPLATE_SIZE'])
            {
                // Inform client that we're sending template data
                $this->clientSend ($conn, 'TEMPLATE '.self::$backend_cache[$gp['url']]['TEMPLATE_SIZE']);
                
                // Send template data, binary send
                $this->clientSend ($conn, self::$backend_cache[$gp['url']]['TEMPLATE'], true);
            } // SEND PAGE TEMPDATE DATA
                                        
            // Tell client that we're done
            $this->clientSend ($conn, 'DONE');
            return (true);
        } // Has Get parameters and socket connection?
        return (false);
    } // sendCacheData
    
    
    private function inCache (&$gp)
    {
        // Check if we have it in memory cache
        if ($gp && array_key_exists ($gp['url'], self::$backend_cache))
        {
            // Change our mind if what is stored is older than the request
            if (self::$backend_cache[$gp['url']]['mtime'] < $gp['modtime'])
            {
                // Remove from Cache
                unset (self::$backend_cache[$gp['url']]);
                $this->log ('ltsBackend: PID '.$this->my_pid.' (#'.$this->process_slot.') cache stale ['.$gp['url'].']');
                return (false);
            } // Still valid?
            return (true);
        } else {
            // Not in memory? Check it if we have the data on the DB backend
            if ($rw = $this->dbBackend ($gp['url']))
            {
                if ($rw[1] < $gp['modtime'])
                {
                    $this->log ('ltsBackend: PID '.$this->my_pid.' (#'.$this->process_slot.') DB stale ['.$gp['url'].']');
                    return (false);   // Data from DB is older
                } // Check DB modtime
                // Store to memory
                self::$backend_cache[$gp['url']] = unserialize (base64_decode ($rw[0]));
                // Update Access count
                $this->dbUpdate ('backend', 'access=access+1', 'name='.$this->dbStr ($gp['url']));
                $this->log ('ltsBackend: PID '.$this->my_pid.' (#'.$this->process_slot.') DB cached ['.$gp['url'].']');                
                return (true);
            } // Has data?
        }// Do we have an entry in cache?
        return (false);
    } // inCache
    
    
    private function dbBackend ($k = false)
    {
        if ($k)
        {
            $q = 'SELECT data,modtime FROM backend WHERE name='.$this->dbStr ($k);
            if (($r = $this->dbExec ($q)) && $this->dbNumRows ($r))
                return ($this->dbGetRow ($r));
        } // Has key?
        return (false);
    } // dbBackend
    
    
    private function extractGetParams ($params)
    {
        if ($params && strlen ($params))
        {
            $gp = array();
            // Parse parameter string
            if (($rpos = strrpos ($params, ' ')) !== false)
            {
                $gp['url'] = substr ($params, 0, $rpos);
                $gp['modtime'] = intVal (substr ($params, $rpos + 1));
            } else {
                $gp['url'] = $params; // Has modtime included?
                $gp['modtime'] = time(); // Use the current time
            } // Parse get parameters
            return ($gp);
        } // Has Params string?
        return (false);
    } // extractGetParams
    
    
    public function setBusy ($busy = false)
    {
        // Lock down reading and writing
        if ($this->sem_id && @sem_acquire ($this->sem_id))
        {
            if ($this->shmem_id && ($data = shmop_read ($this->shmem_id, 8, 4))) 
            {       
                $bdata = unpack ('Lbusy', $data);
                $bbyte = $bdata['busy'];
                if ($this->bit_pos === false)
                    $this->bit_pos = (1 << $this->process_slot);
                    
                // Change values
                if ($busy)
                    $bbyte = ($bbyte | $this->bit_pos);
                else $bbyte = ($bbyte & ~$this->bit_pos);
                
                // Write to Memory
                $bdata = pack ('L', $bbyte);
                shmop_write ($this->shmem_id, $bdata, 8);
            } // Read timestamp directly!  
            // Release semaphore
            sem_release ($this->sem_id);
        } // Lock for Reading/Writing
        return (false);
    } // setBusy
    
    
    private function clientReceive (&$conn, $rsize = false)
    {
        if ($rsize > 0)
        {
            $tread = 0;
            $data = '';
            while ($tread < $rsize)
            {
                if (($rdata = socket_read ($conn, ($rsize - $tread), PHP_BINARY_READ)) === false)
                {
                    $this->log ('ltsBackend ('.$cmd.'): PID '.$this->my_pid.' (#'.$this->process_slot.') '.socket_strerror(socket_last_error()));
                    return (false);
                } // Read data? 
                $data .= $rdata;
                $tread += strlen ($rdata);
            } // WHILE
            if (strlen ($data))
                return ($data);
        } // Has read size?
        return (false);
    } // clientReceive
    
    
    private function clientSend ($conn, $str, $bin = false)
    {
        if ($conn && $str && strlen ($str))
        {
            if (!$bin) $str = trim ($str)."\n";
            if (socket_write ($conn, $str, strlen ($str)) === false)
            {
                $this->log ('Client write failed: ['.$str.']');
                return (false);
            } // Error sending data? 
            return (true);
        } // Has string?
        return (false);
    } // clientSend
    
    
    private function sendTermSignal()
    {
        if ($sm = $this->shmem_read())
        {
            // Send each siblings the TERM signal
            // For sure if I'm the master, I'll always be on
            // slot #0
            $this->log ('ltsBackend:  (#'.$this->process_slot.') Sending siblings the TERM signal...');
            for ($i = 0; $i < $sm['count']; $i++)
            {
                 $bdata = $sm[$i];
                 
                 // Skip me from receiving the TERM signal
                 if ($bdata[0] == $this->my_pid)
                     continue;
                     
                 // If we have a PID and PORT then
                 // Send the terminate signal
                 if ($bdata[0] && $bdata[1])
                     posix_kill ($bdata[0], SIGTERM);
            } // FOR
            
            // Connect to Siblings
            for ($j = 0; $j < 1; $j++)
            {
                $sm = $this->shmem_read();
                $this->log ('ltsBackend('.($j + 1).'): PID '.$this->my_pid.' (#'.$this->process_slot.') poking siblings...');
                for ($i = 0; $i < $sm['count']; $i++)
                {
                    $bdata = $sm[$i];
                    
                    // Skip me again
                    if ($bdata[0] == $this->my_pid)
                        continue;
                    
                    // If we have a PID and PORT then
                    if ($bdata[0] && $bdata[1])
                    {
                        if ($socket = socket_create (AF_INET, SOCK_STREAM, SOL_TCP))
                        {
                            // Sibling connect?
                            if (socket_connect ($socket, '127.0.0.1',  $bdata[1]) == false)
                                $this->log ('ltsBackend: Sibling at port '.$bdata[1].' did not respond');
                        } // Has socket?
                        socket_close ($socket);
                    } // Has opened port and PID
                } // FOR
            } // FOR
            return (true);
        } // Read Shared memory
        return (false);
    } // sendTermSignal
    
    
    private function broadcastCommand ($cmd = false)
    {
        if ($cmd && ($sm = $this->shmem_read()))
        {
            // Send each siblings send a signal
            $this->log ('ltsBackend: PID '.$this->my_pid.' (#'.$this->process_slot.') broadcasting to siblings ('.$cmd.')');
            
            // WHAT COMMAND WILL WE BROADCAST?
            $flag = 0;
            switch ($cmd)
            {
                case 'CLEAR' :   // CLEAR CACHE EXCEPT DISK
                    $flag |= 1;
                    break;
                    
                case 'CLEARALL' : // CLEAR CACHE INCLUDING DISK
                    $flag |= 3;
                    break;
                    
                case 'RELOAD' : // RELOAD CALLBACKS
                    $flag |= 4;
                    break;
                    
                default :
                    $flag = 0;
            } // SWITCH
            
            for ($i = 0; $i < $sm['count']; $i++)
            {
                $bdata = $sm[$i];
                
                // Write to siblings flags
                $dflag = pack ('S', $fd = ($flag | $bdata[2]));
                shmop_write ($this->shmem_id, $dflag, (parent::$shmem_header_size + ($i * 10)) + 8);
                
                // Skip me from receiving the HUP signal
                if ($bdata[0] == $this->my_pid)
                {
                    self::$do_check_flags = true;
                    continue;
                } // Is this me?
                                
                // If we have a PID and PORT then
                // Send the HUP signal
                if ($bdata[0] && $bdata[1])
                    posix_kill ($bdata[0], SIGHUP);
            } // FOR
            
            // Connect to Siblings
            for ($j = 0; $j < 1; $j++)
            {
                $sm = $this->shmem_read();
                $this->log ('ltsBackend('.($j + 1).'): PID '.$this->my_pid.' (#'.$this->process_slot.') poking siblings...');
                for ($i = 0; $i < $sm['count']; $i++)
                {
                    $bdata = $sm[$i];
                    
                    // Skip me again
                    if ($bdata[0] == $this->my_pid)
                        continue;
                    
                    // If we have a PID and PORT then
                    if ($bdata[0] && $bdata[1])
                    {
                        if ($socket = socket_create (AF_INET, SOCK_STREAM, SOL_TCP))
                        {
                            // Sibling connect?
                            if (socket_connect ($socket, '127.0.0.1',  $bdata[1]) == false)
                                $this->log ('ltsBackend: Sibling at port '.$bdata[1].' did not respond');
                        } // Has socket?
                        socket_close ($socket);
                    } // Has opened port and PID
                } // FOR
            } // FOR
            
            // Check my flags
            $this->checkFlags();
            
            return (true);
        } // Read Shared memory
        return (false);
    } // broadcastCommand 
    
    
    private function detach()
    {
        // Lock down shared memory...
        // We're detaching from it
        if ($this->sem_id && @sem_acquire ($this->sem_id))
        {
            // Clear backend record in shared memory block
            $sm = $this->shmem_read();
            $abits = $sm['alive'];
            $abits = $abits & ~(1 << $this->process_slot);
            $adata = pack ('L', $abits);
            if ($this->shmem_id && shmop_write ($this->shmem_id, $adata, 12) 
                && shmop_write ($this->shmem_id, pack ('LL', 0, 0), (parent::$shmem_header_size + ($this->process_slot * 8))))
                $this->log ('ltsBackend: PID '.$this->my_pid.' (#'.$this->process_slot.') is now detached from port '.$this->server_port);
            $sm = $this->shmem_read();
            
            // Remove shared memory
            if ($this->shmem_destroy())
                $this->log ('ltsBackend: PID '.$this->my_pid.' (#'.$this->process_slot.') deleted shared memory');
            
            // Release semaphore
            if ($this->sem_id)
                @sem_release ($this->sem_id);
        } // Remove my slot
        
        // Close server (socket) ports
        if ($this->socket)
            @socket_close ($this->socket);
    } // detach
    
    
    public function clean()
    {
        $this->shmem_get();
        if ($this->shmem_id)
            shmop_delete ($this->shmem_id);
    } // clean
    
    
    public function run()
    {
        set_time_limit(0);
        
        // Initialize backend
        // Create server socket too
        $this->init();
        
        // Spawn Siblings
        if (self::$backend_count > 1)
        {
            $this->log ('ltsBackend: PID '.$this->my_pid.' spawning '.self::$backend_count.' sibling'.(self::$backend_count > 1 ? 's' : ''));
            for ($i = 0; $i < self::$backend_count; $i++)
            {
                $pid = pcntl_fork();
                switch ($pid)
                {
                    case -1 :
                        $this->log ('** '.getmypid().': Error forking');
                        break;
                        
                    case 0 :
                        if (self::$lts_path)
                            $cmd = self::$lts_path.'lts/backend/siblings.php';
                        else {
                            $cmd = getcwd();
                            if (($spos = strpos ($cmd, 'html')) !== false)
                            {
                                $cmd = substr ($cmd, 0, $spos);
                                $cmd = $cmd.'lts/backend/siblings.php';
                            } else if (strpos ($cmd, 'lts/backend') !== false)
                            {
                                $cmd = $cmd.'/siblings.php';
                            } // Ran from CLI
                        } // Generate command line
                        
                        $this->log ('Spawning sibling backend process ['.getmypid().']...');
                        $args = array ();
                        $args[] = $cmd;
                        if (self::$loader_path)
                            $args[] = self::$loader_path;
                        pcntl_exec ($cmd, $args);
                        exit;
                        
                    default :
                        pcntl_wait ($status, WNOHANG);
                        continue;                        
                } // SWITCH
            } // FOR
        } // Spawning Sibings
        
        // Start the Server loop
        if ($this->server_port)
            $this->doEventLoop();
            
        // We're ending our run
        $this->detach();
        return (true);
    } // run
    
    
    private function checkFlags ()
    {
        if (self::$do_check_flags && $this->sem_id)
        {
            // Disable checking of flags
            self::$do_check_flags = false;
            
            // Read my flags
            if ($data = shmop_read ($this->shmem_id, (parent::$shmem_header_size + ($this->process_slot * 10)) + 8, 2))
            {
                $bdata = unpack ('Sflag', $data);
                $flag = $bdata['flag'];
                
                // Execute commands
                $this->do_flag_command ($flag);
                
                // Reset and write to flag
                $dflag = pack ('S', 0);
                shmop_write ($this->shmem_id, $dflag, (parent::$shmem_header_size + ($this->process_slot * 10)) + 8);
            } // Has successfully read flag?
            return (true);
        } // Do we need to check our flags?
        return (false);
    } // checkFlags
    
    
    private function do_flag_command ($flag = false)
    {
        //$this->log ('ltsBackend: PID '.$this->my_pid.' (#'.$this->process_slot.') flag command ['.$flag.']');
        if ($flag)
        {
            // CLEAR MEM AND DATABASE CACHE
            if ($flag & 1) 
            {
                self::$backend_cache = array();
                
                if ($this->i_am_master)
                {
                    // Delete database backend data
                    $q = 'DELETE FROM backend';
                    $this->dbExec ($q);
                } // Am I the master?
                $this->log ('ltsBackend: PID '.$this->my_pid.' (#'.$this->process_slot.') cleared cache');
            } // CLEAR MEM AND DATABASE CACHE
            
            // CLEAR DISK CACHE
            if ($flag & 2)
            {
                if ($this->i_am_master)
                {
                    if ($files = scandir (self::$disk_cache_path))
                    {
                        foreach ($files as $file)
                        {
                            if (($file == '.') || ($file == '..'))
                                continue;
                            @unlink (self::$disk_cache_path.$file);
                        } // FOREACH
                    } // Has files?
                    $this->log ('ltsBackend: PID '.$this->my_pid.' (#'.$this->process_slot.') cleared disk cache');
                } // Am I the master?
            } // CLEAR DISK CACHE
            
            // RELOAD CALLBACKS
            if ($flag & 4)
            {
                if ($this->i_am_master)
                {
                    $this->callback_functions = array();
                    $this->log ('ltsBackend: PID '.$this->my_pid.' (#'.$this->process_slot.') callbacks reloaded');
                } // Am I the master?
            } // RELOAD CALLBACKS
            return ($flag);
        } // Has Flag?
        return (false);
    } // do_flag_command
    
} // ltsBackend

?>