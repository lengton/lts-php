<?php

ini_set ('include_path', '.:/usr/share/pear/:'.ini_get ('include_path'));
require_once ('Mail.php');

class ltsBase
{
    private static $db = false;
    private static $backend_lock = '/tmp/lts_backend';
    
    protected $my_pid = false;
    protected static $lts_path = false;
    protected static $shmem_key = 101712;
    protected static $shmem_header_size = 0;
    protected static $sem_key = 61012;
    protected static $backend_count = 1;
    
    protected $shmem_id = false;
    protected $sem_id = false;
    
    private $uid = 'NULL';
    private $type = 'NULL';
    private $last_insert_id = false;
    private $time_start = false;
    public $sid = false;
    public $host_ip = false;
    public static $backend_sid = '860f5605a4833c2a9447baddb58815b9';  // Assigned SID for the Backend process - Gigatt.AttGig!
    private static $log_fd = false;
    static private $log_path = false;
    static private $log_size = false;
    
    // Mail connection settings
    static private $mail_param = false;
    static private $mail_header = false;
    static private $mail_smtp = false;
    
    
    public function __construct($for_web = true)
    {
        // Set timezone
        date_default_timezone_set ('US/Central');
        
        // Compute header size
        $this->shmem_compute_header_size();
        
        @include_once ('conf/log.php');
        if (isset ($lts_log_path))
        {
            // Create if it doesn't exists
            if (!file_exists ($lts_log_path))
                mkdir ($lts_log_path);
                
            // Our current Log file
            self::$log_path = rtrim ($lts_log_path, '/').'/lts.log';
            if (isset ($lts_log_size))
                self::$log_size = $lts_log_size;
            else self::$log_size = 500;
                
            // Open log file
            if (!self::$log_fd)
                self::$log_fd = fopen (self::$log_path, 'a');
        } // LOG PATH EXISTS?
        
        // We just need one copy of the Database connection
        // Also do the one-time code evaluation here
        if (self::$db == false)
        {
            // Load Database string (for connection)
            include_once ('conf/db.php');
            if ((self::$db = @pg_pconnect($dbstr)) === false) 
            {
                $this->log ('ltsBase: Cannot connect to system database');
                self::$db = false;
            } // SELECT DATABASE
        } // Open Database connection

        if ($for_web)
        {
            // Get Remote Address
            $this->host_ip = @$_SERVER['REMOTE_ADDR'];
            
            // SET SESSION COOKIE
            if (isset ($_COOKIE['lts_sid']))
            {
                $this->sid = $_COOKIE['lts_sid'];

                // Check if we're logged in using this Session ID
                $this->uid = $this->sessionUID();
            } else {
                $this->sid = md5 (uniqid (mt_rand (), true)).md5 (uniqid (mt_rand (), true));
                setcookie ('lts_sid', $this->sid, 0, '/');
                
                // COLLECT INFO
                if ($this->host_ip)
                {
                    // Check if Hosts exists in our database
                    if (!$this->dbUpdate ('access', 'date=NOW()', 'host_ip='.$this->dbStr ($this->host_ip)))
                        $this->dbInsert ('access', 'host_ip,date,browser', $this->dbStr($this->host_ip).',NOW(),'.$this->dbStr(@$_SERVER['HTTP_USER_AGENT']));
                } // SAVE BROWSER INFO
            } // SET SESSION KEY
                    
            // CHECK FOR STALE TEMPORARY FILES
            $q = 'SELECT value FROM session WHERE (AGE(ts) > INTERVAL \'24 hours\') AND (name LIKE \'lts_tempFile\')';
            if (($r = $this->dbExec ($q)) && $this->dbNumRows ($r))
            {
                while ($rw = $this->dbGetRow ($r))
                {
                    $this->log ('ltsBase: Deleting stale lts_tempFile ['.$rw[0].']');
                    @unlink ($rw[0]);
                } // WHILE
            } // Has stale lts_tempFile?
            
            // CLEAN SESSION GARBAGE -- 24 hours
            $this->dbExec ('DELETE FROM session WHERE AGE(ts) > INTERVAL \'24 hours\'');
        } // Used in web?
    } // CONSTRUCTOR
    
    
    public function mail_headers ($k = false, $v = false)
    {
        if ($k && is_array ($k))
        {
            self::$mail_header = $k;
            $v = true;
        } else if ($k) {
            if ($v)
                self::$mail_header[$k] = $v;
            else $v = @self::$mail_header[$k];
        } else if ($k === false)
            $v = self::$mail_header;
        return ($v);
    } // mail_headers
    
    
    public function mail_params ($k = false, $v = false)
    {
        if ($k && is_array ($k)) 
        {
            self::$mail_param = $k;
            $v = true;
        } else if ($k) {
            if ($v)
                self::$mail_param[$k] = $v;
            else $v = @self::$mail_param[$k];
        } else if ($k === false)
            $v = self::$mail_param;
        return ($v);
    } // mail_params
    
    
    public function sendMail($mail_body = NULL, $mail_to = false)
    {
        if (isset ($mail_body))
        {
            // Connect to SMTP server
            if (!@$this->mail_smtp)
                $this->mail_smtp =& Mail::factory ('smtp', self::$mail_param);
            
            // Did we supply a Mail to?
            if ($mail_to == false)
                $mail_to = self::$mail_header ['To'];
                
            // Did we have an SMTP connection?
            if ($this->mail_smtp)
            {
                $status = $this->mail_smtp->send ($mail_to, self::$mail_header, $mail_body);
                if (PEAR::isError ($status))
                    $this->log ('Email Error: '.$status->getMessage());
                else if ($status === true)
                    return (true);
            } // Has SMTP connection
        } // Has message
        return (false);
    } // sendMail
    
    
    public function getSessionUID ()
    {
        $uid = false;
        if ($this->uid != 'NULL')
            $uid = intVal ($this->uid);
        return ($uid);
    } // getSessionUID
    
    
    public function getSessionUserType ()
    {
        if ($uid = $this->getSessionUID())
            return ($this->dbGetValue ('users', 'type', 'id='.$uid));
        return (false);
    } // getSessionUID    
    
    
    public function dbStr ($str)
    {
        if (self::$db && strlen ($str))
            return ('\''.pg_escape_string (self::$db, $str).'\'');
        return ('NULL');
    } // dbStr
    
    
    public function setSessionType ($type = false)
    {
        if ($type = intVal ($type))
            $this->type = $type;
    } // setSessionType
    
    
    public function dbUpdate ($table, $values, $where)
    {
        //$this->log ('UPDATE '.$table.' SET '.$values.' WHERE '.$where);
        if (($r = $this->dbExec ('UPDATE '.$table.' SET '.$values.' WHERE '.$where)) && (($ar = $this->dbAffectedRows ($r)) > 0))
            return ($ar);   
        return (false);
    } // dbUpdate
    
    
    public function dbExec ($qry, $current_table = false)
    {
        if ($qry && self::$db && ($r = pg_query (self::$db, $qry)))
        {
//if (strpos ($qry, 'session') !== false)
//    $this->log ('** '.$qry);        
            if ($current_table && ($sq = pg_query (self::$db, 'SELECT currval(\''.$current_table.'_id_seq\'::regclass)')))
            {
                $rw = $this->dbGetRow ($sq);
                $this->last_insert_id  = intVal ($rw[0]);
                //$this->last_insert_id = mysql_insert_id (self::$db);
            } // RETRIEVE LAST INSERTED ID
            return ($r);
        } // Query OK?          
        return (false);
    } // dbExec

    
    public function dbLastID ()
    {
        // Only works if a recent insert was done for the 
        // DB session
        return ($this->last_insert_id);
    } // dbLastID
    
    
    public function dbGetNextID ($t = false)
    {
        // This is PostgreSQL specific
        if ($t && strlen (trim ($t))  && ($r = $this->dbExec ('SELECT nextval (\''.$t.'_id_seq\'::regclass)')))
        {
            $rw = $this->dbGetRow ($r);
            // Update last inserted ID
            $this->last_insert_id = intVal ($rw[0]);
            return ($rw[0]);
        } // Has table?
        return (false);
    } // dbGetNextID
    
    
    public function dbNumRows ($r)
    {
        return (pg_num_rows ($r));
    } // dbNumRows
    
    
    public function dbAffectedRows ($r)
    {
        return (pg_affected_rows ($r));
    } // dbAffectedRows
    
    
    public function dbGetRow ($r)
    {
        return (pg_fetch_row ($r));
    } // dbGetRow
    
    
    public function dbGetRowAssoc ($r)
    {
        return (pg_fetch_assoc ($r));
    } // dbGetRowAssoc
    
    
    public function dbCount ($table, $where = false, $count = '*')
    {
        // Supply Where?
        if ($where)
            $where = ' WHERE '.$where;
        else $where = '';
        
//$this->log ('** '.'SELECT COUNT('.$count.') FROM '.$table.$where);        
        // Returns a wildcard count query
        if (($r = $this->dbExec ('SELECT COUNT('.$count.') FROM '.$table.$where)) && $this->dbNumRows ($r))
        {
            $rw = $this->dbGetRow ($r);
            return (intVal ($rw[0]));
        } // HAS DB COUNT
        return (false);        
    } // dbCount
    
    
    public function dbConn()
    {
        return (self::$db);
    } // dbConn
    
    
    public function dbInsert ($table, $value_set, $values)
    {
        $q = 'INSERT INTO '.$table;
        if ($value_set && strlen ($value_set))
            $q .= ' ('.$value_set.') ';
        $q .= 'VALUES ('.$values.')';
        if (($r = $this->dbExec ($q)) && ($ar = $this->dbAffectedRows ($r)))
            return ($ar);
        return (false);
    } // dbInsert
    
    
    public function dbDelete ($table, $where)
    {
        if (($r = $this->dbExec ('DELETE FROM '.$table.' WHERE '.$where)) && ($ar = $this->dbAffectedRows ($r)))
            return ($ar);
        return (false);
    } // dbDelete
    
    
    public function sessionLogin ($uid = false)
    {
        if ($uid = intVal ($uid))
        {
            $this->uid = $uid;
            $this->dbExec ('UPDATE session SET uid='.$uid.' WHERE sid='.$this->dbStr ($this->sid));
            $this->dbInsert ('session', 'sid,name,value,type,uid,host_ip', 
                             $this->dbStr($this->sid).',
                             '.$this->dbStr('login').',
                             '.$this->dbStr (strval(time())).',
                             '.$this->type.',
                             '.$this->uid.',
                             '.$this->dbStr ($this->host_ip));
        } // Has UID
        return ($uid);
    } // sessionLogin
    
    
    public function sessionBackendGet ($key)
    {
        if ($key && strlen ($key))
        {
            if (($v = $this->dbGetValue ('session', 'value', '(sid='.$this->dbStr (self::$backend_sid).') AND (name='.$this->dbStr ($key).')')) !== false)
                return ($v);
        } // Has Key
        return (false);
    } // sessionBackendGet
    
    
    public function sessionGet ($key, $where = false)
    {
        if ($key && strlen ($key))
        {
            $whr = '(sid='.$this->dbStr($this->sid).') AND (name='.$this->dbStr ($key).')';
            if ($where && strlen ($where)) $whr .= ' AND ('.$where.')';
            if (($v = $this->dbGetValue ('session', 'value', $whr)) !== false)
                return ($v);
        } // Has Key
        return (false);
    } // sesssionGet
    
    
    public function sessionUID ()
    {
        $uid = intVal ($this->dbGetValue ('session', 'uid', '(name='.$this->dbStr ('login').') AND (sid='.$this->dbStr ($this->sid).')'));
        return (intVal ($uid) ? $uid : 'NULL');
    } // sessionUID
    
    
    public function sessionInsert ($key, $value, $sid = false)
    {
        $type = $uid = 0;
        if (!$sid) $sid = $this->sid;
        return ($this->dbInsert ('session', 'sid,name,value,type,uid,host_ip', 
                $this->dbStr ($sid).','.$this->dbStr($key).','.$this->dbStr($value).','.$this->type.','.$this->uid.','.$this->dbStr ($this->host_ip)));
    } // sessionInsert
    
    
    public function sessionReplace ($key, $value, $where = false)
    {
        $whr = '(sid='.$this->dbStr($this->sid).') AND (name='.$this->dbStr ($key).')';
        if ($where) $whr .= ' AND ('.$where.')';
        if (!$this->dbUpdate ('session', 'ts=NOW(),host_ip='.$this->dbStr ($this->host_ip).',value='.$this->dbStr($value), $whr))
            return ($this->sessionInsert ($key, $value));
        return (false);
    } // sessionReplace
    
    
    public function sessionBackendReplace ($key, $value)
    {
        $whr = '(sid='.$this->dbStr(self::$backend_sid).') AND (name='.$this->dbStr ($key).')';
        if (!$this->dbUpdate ('session', 'ts=NOW(), value='.$this->dbStr($value), $whr))
            return ($this->sessionInsert ($key, $value, self::$backend_sid));
        return (false);
    } // sessionReplace    
    
    
    public function sessionErase ($key, $where = false, $opr = '=')
    {
        $whr = '(name '.$opr.' '.$this->dbStr ($key).') AND (sid='.$this->dbStr($this->sid).')';
        if ($where) $whr .= ' AND ('.$where.')';
        return ($this->dbDelete ('session', $whr));
    } // sessionErase
    
    
    public function sessionKeyErase ($key, $where = false, $opr = '=')
    {
        $whr = '(name '.$opr.' '.$this->dbStr ($key).')';
        if ($where) $whr .= ' AND ('.$where.')';
        return ($this->dbDelete ('session', $whr));
    } // sessionKeyErase
    
    
    public function sessionClear ()
    {
        // Is there a temporary file?
        // Blindly delete it
        if ($fp = $this->sessionGet ('lts_tempFile'))
            @unlink ($fp);
            
        return ($this->dbDelete ('session', 'sid='.$this->dbStr($this->sid)));
    } // sessionClear
    
    
    public function sessionSerialize ($key, $variable, $where = false)
    {
        return ($this->sessionReplace ($key, base64_encode (serialize ($variable)), $where));
    } // sessionSerialize
    
    
    public function sessionUnserialize ($key, $where = false)
    {
        return (@unserialize (base64_decode ($this->sessionGet ($key, $where))));
    } // sessionUnserialize
    
    
    public function dbGetValue ($table, $values, $where)
    {
//$this->log ('SELECT '.$values.' FROM '.$table.' WHERE '.$where);    
        if (($r = $this->dbExec ('SELECT '.$values.' FROM '.$table.' WHERE '.$where)) && $this->dbNumRows ($r)) 
        {
            $rw = $this->dbGetRow ($r);
            if (count($rw) > 1) return ($rw);
            return ($rw[0]);
        } // HAS DB VALUE
        return (false);
    } // dbGetValue
    
    
    public function dbGetExtKeyID ($key = false)
    {
        $id = false;
        $key = strtolower (trim ($key));
        if (strlen ($key))
        {
            if ($id = intVal($this->dbGetValue ('ext_keys', 'id', 'name='.$this->dbStr ($key))))
                return ($id);
            else if ($this->dbExec ('INSERT INTO ext_keys (name) VALUES ('.$this->dbStr ($key).')', 'ext_keys') && ($id = $this->dbLastID()))
                    return ($id);
        } // Key has length
        return (false);
    } // dbGetExtKeyID
    
    
    public function dbExtData ($rid = 0, $uid = 0, $key = false, $value = false)
    {
        if (($rid = intVal ($rid)) && ($uid = intVal ($uid)))
        {
            if ($id = $this->dbGetExtKeyID ($key))
            {
                if (!$this->dbUpdate ('ext_data', 'data='.$this->dbStr ($value).',modified=NOW()', '(rid='.$rid.') AND (uid='.$uid.') AND (key_id='.$id.')'))
                    $this->dbInsert ('ext_data', 'key_id,uid,rid,modified,data', $id.','.$uid.','.$rid.',NOW(),'.$this->dbStr ($value));
                return ($id);
            } // Has Key ID?
        } // Has RID and UID
        return (false);
    } // dbExtData
    
    
    public function dbGetExtData ($rid = 0, $uid = 0, $key = false)
    {
        if (($rid = intVal ($rid)) && ($uid = intVal ($uid)))
        {
            if (($key !== false) && ($id = $this->dbGetExtKeyID ($key)))
                return ($this->dbGetValue ('ext_data', '(rid='.$rid.') AND (uid='.$uid.') AND (key_id='.$id.')'));
            else {
                if ($r = $this->dbExec ('SELECT k.name, d.data FROM ext_data d, ext_keys k 
                                         WHERE (k.id = d.key_id) AND (d.uid='.$uid.') AND (d.rid='.$rid.')')) {
                    $arr = array();
                    while ($rw = $this->dbGetRow ($r))
                        $arr[$rw[0]] = $rw[1];
                    return ($arr);
                } // Has Rows?
            } // Return as an associative array
        } // Has RID and UID
        return (false);
    } // dbGetExtData
    
    
    public function getTimestamp()
    {
        return (date ('Y-m-d H:i:s'));
    } // getTimestamp
    
    
    public function unserialize ($name = false)
    {
        // Take note that we only unserialize the 'data' portion
        // of a resource. So ltsPage, ltsBase instance values take the 
        // values of the current instance that will unserialize this
        if ($name)
        {
            $data_str = $this->sessionGet ($name);
            if ($data_str && (($dv = strpos ($data_str, '#')) !== false))
            {
                $s_name = substr ($data_str, 0, $dv);
                $obj = new $s_name;
                if ($obj)
                {
                    $s_data = substr ($data_str, ($dv + 1));
                    if ($data = @unserialize (base64_decode ($s_data)))
                    {
                        $obj->setObjectData ($data['m']);
                        $obj->setObjectError ($data['e']);
                        return ($obj);
                    } // Has valid unserialized object?
                } // Valid class Object
            } // Has valid data/name division
        } // Have valid Resource ID?
        return (false);
    } // unserialize
    
    
    public function shmem_get ()
    {
        // Get a semaphone if we don't have one
        $this->shmem_id = @shmop_open (self::$shmem_key, 'w', 0, 0);
        return ($this->shmem_id);
    } // shmem_get
    
    
    public function start_backend ($lts_path, $global_vars)
    {
        if ($lts_path)
        {
            self::$lts_path = $lts_path;
            $cmd = self::$lts_path.'backend/master.php';
            
            // Open and create exclusively (lock file)
            // Only one backend Master can be started at anytime
            if ($fp = @fopen (self::$backend_lock, 'x'))
            {
                // CHECK FOR A LOADER SCRIPT
                if (isset ($global_vars['ln']) && isset ($global_vars['lts_data_path']))
                {
                    $ldp = $global_vars['lts_data_path'];
                    if (($pi = strpos ($ldp, 'lts/')) !== false)
                    {
                        $ldp = substr ($ldp, 0, $pi);
                        $cmd .= ' '.$ldp.$global_vars['ln'];
                    } // Coming from the web?
                } // Has loader script vars?
                
                $this->log ('Spawning Master Backend process...');
                exec ($cmd.' > /dev/null &');
                fclose ($fp);
                return (true);
            } // FIRST ONE TO CREATE LOCK FILE?
        } // Has lts path?
        return (false);
    } // start_backend
    
    
    private function shmem_compute_header_size ()
    {
        // Compute Memory Block size, depending on the number
        // of backend processes
        $memsize  = 4;	// Shared Memory Signature ID size
        $memsize += 4; // Timestamp anchor (start of Master backend process)
        $memsize += 4; // Busy/Idle Flags
        $memsize += 4; // Alive flag
        $memsize += 2; // Number of Backend process (Maximum of 32)
        $memsize += 2; // Backend control Flags
        
        // Get a copy of the end header size (offset)
        self::$shmem_header_size = $memsize;
        return ($memsize);
    } // shmem_computer_header_size
    
    
    public function shmem_create ()
    {
        // Get computed header size
        if (!($memsize = self::$shmem_header_size))
            return (false);
            
        // Check if we already had our PID stored
        if ($this->my_pid == false)
            $this->my_pid = getmypid();
        
        // Starting on byte offset self::$shmem_header_size, lists the backend PID/Socket ports
        // Backend count CANNOT BE greater than 31
        // We add ourselves to the mix +1
        if (self::$backend_count > 31)
            self::$backend_count = 31;
        $memsize += ((self::$backend_count + 1) * 10);
        
        // Use 'n' just in case. Let's create the Shared memory block
        if ($this->shmem_id = @shmop_open (self::$shmem_key, 'n', 0666, $memsize))
        {
            // Now let's initialize the Shared block
            // Initialize first backend slot for the master
            $data = pack('LLLLSS', 0xbeef, $this->time_start = time(), 0, 0, (self::$backend_count + 1), 0);
            
            // Now initialize backend containers
            $baks = pack ('LLS', 0, 0, 0);
            for ($i = 0; $i < (self::$backend_count + 1); $i++)
                $data .= $baks;
            
            // Write to Memory
            if (shmop_write ($this->shmem_id, $data, 0) == false)
            {
                $this->log ('ltsBase: Memory initialization write failed');
                return (false);
            } // Write to Memory
        } // Created shared memory successfull
        
        // Returns FALSE anyway, if there are errors
        return ($this->shmem_id);
    } // shmem_create
    
    
    public function shmem_read ()
    {
        // Unblocking read
        // Read the header first
        if ($this->shmem_id && ($data = shmop_read ($this->shmem_id, 0, self::$shmem_header_size)))
        {
            // Unpack data
            $adata = unpack ('Lid/Ltime/Lbusy/Lalive/Scount/Sflags', $data);
            if ($adata['id'] == 0xbeef)
            {
                for ($i = 0; $i < $adata['count']; $i++)
                {
                    $dpid = unpack ('Lpid', shmop_read ($this->shmem_id, (self::$shmem_header_size + ($i * 10)), 4));
                    $dport = unpack ('Lport', shmop_read ($this->shmem_id, (self::$shmem_header_size + ($i * 10)) + 4, 4));
                    $dflag = unpack ('Sflag', shmop_read ($this->shmem_id, (self::$shmem_header_size + ($i * 10)) + 8, 2));
                    $adata[$i] = array ($dpid['pid'], $dport['port'], $dflag['flag']);
                } // FOR
                return ($adata);
            } // Check Shared memory ID
        } // Read successful?
        return (false);
    } // shmem_read
    
    
    public function shmem_getTimestamp()
    {
        if ($this->shmem_id && ($data = shmop_read ($this->shmem_id, 4, 4)))
        {
            $ts = unpack ('Lts', $data);
            return ($ts['ts']);
        } // Read timestamp directly!
        return (false);        
    } // shmem_getTimestamp
    
    
    public function shmem_destroy()
    {
        // Release Shared memory
        if ($this->shmem_id && $shdata = $this->shmem_read())
        {
            $acount = 0;
            $abits = $shdata ['alive'];
            $bitp = 1;
            for ($i = 0; $i < $shdata['count']; $i++)
            {
                if ($abits & $bitp) $acount++;
                $bitp = $bitp << 1;
            } // FOR
            
            // Check if we're the only ONE
            if (!$acount)
            {
                shmop_delete ($this->shmem_id);
                $this->shmem_id = false;
                
                // Remove semaphore
                if ($this->sem_id) 
                {
                    sem_release ($this->sem_id);
                    sem_remove ($this->sem_id);
                    $this->sem_id = false;
                } // Remove Semaphone ID
                
                // Remove Locks too
                @unlink (self::$backend_lock);
            }  // Remove Shared Memory
            
            // Close
            if ($this->shmem_id)
            {
                shmop_close ($this->shmem_id);
                return ($this->shmem_id = false);
            } // Close shared memory
            
            // Return true if there was a deletion
            // of the shared memory block
            return (true);
        } // Has Shared Memory ID?
        return (false);
    } // shmem_destroy
    
    
    public function getBackendPort ($sm = false)
    {
        if (!$sm) $sm = $this->shmem_read (false);
        // Has Shared Memory read?
        if ($sm)
        {
            // Usually we just need the first port
            $busy = $sm['busy'];
            $i = 0;
            $bit_pos = 1;
            if ($sm['count'] > 1)
            {
                $bit_pos = 2;
                $i = 1;
            } // Skip the Master when there's a sibling around
            
            for (; $i < $sm['count']; $i++)
            {
                // Check for a busy server
                if ($busy & $bit_pos)
                {
                    $bit_pos = ($bit_pos << 1);
                    $this->log ('ltsPage: Backend slot #'.$i.' busy');
                    continue;
                } // Is the server busy?
                
                $bdata = $sm[$i];
                // If we have a PID and PORT then return PORT
                if ($bdata[0] && $bdata[1])
                    return ($bdata[1]);
            } // FOR
            $this->log ('ltsPage: No available ports');
        } // Do we have a shared memory read?
        return (false);
    } // getBackendPort
    
    
    public function log ($log)
    {
        // Do we have a log path?
        if (self::$log_path)
        {
            if (filesize (self::$log_path) > (self::$log_size * 1024))
            {
                fclose (self::$log_fd);
                rename (self::$log_path, self::$log_path.'-'.date ('mdY_Gi'));
                self::$log_fd = fopen (self::$log_path, 'a');
            } // Rotate log?
        } // Check file size
        
        if (self::$log_fd)
        {
            fwrite (self::$log_fd, trim ($log)."\n");
            fflush (self::$log_fd);
        } // Write to log
    } // log
    
} // ltsBase

?>
