<?php

class ltsUser extends ltsResource
{
    public function __construct ($pp = false)
    {
        // CALL PARENT CONSTRUCTOR
        parent::__construct($pp);
        
        // Initialize Variables
        $this->db_table = 'users';
        $this->field_length = 512;
        $this->rid = 1;              // Resource ID = 1 (user)
        
        // This corresponds to the physical database fields.
        // Used when separating external data resource data from native resource data.
        // Fields are encoded by this format <field name>:<attribute string>
        // Where attributes could be:
        //   's' = string
        //   'r' = read-only
        //   'n' = numerical
        //   'b' = boolean
        //   'x' = Don't read-in
        $this->db_field = array ('id:rn', 'email:rs', 'firstname:s', 'lastname:s', 'company:s', 'verified:rb', 'cid:n',
                                 'password:x', 'type:rn', 'created:rs', 'last_login:rs', 'blocked:rb', 'last_url:s');
    } // contructor
    
    
    public function changePassword ($password, $uid = false)
    {
        if (($password = trim ($password)) && strlen ($password))
        {
            $passwd_salt = md5 (uniqid (mt_rand (), true));
            $passwd_salt = substr ($passwd_salt, 0, 8);
            
            // Get User ID to change
            if ($uid == false)
                $uid = $this->uid;
                
            if ($uid && ($passwd = crypt ($password, '$1$'.$passwd_salt.'$')))
            {
                if ($this->dbUpdate ('users', 'password='.$this->dbStr ($passwd), 'id='.intVal ($uid)))
                    return (true);
            } // Has encrypted password
        } // has password
        return (false);
    } // changePassword
    
    
    public function login ($email = false, $password = false)
    {
        $email = substr (trim ($email), 0, 64);
        $uid = false;
        if ($email && strlen ($email))
        {
            // Retrieve Password
            if ($db_data = $this->dbGetValue ('users', 'id, password', '(email='.$this->dbStr ($email).') AND (blocked=false)'))
            {
                $uid = intVal ($db_data[0]);
                $db_pass = $db_data[1];
                
                // Is this an EG Staff?
                if ($db_pass == 'mail')
                    $db_pass = $this->getEGMailPassword ($email);
                    
                // Check password and login
                if ($uid && (crypt ($password, $db_pass) == $db_pass))
                    return ($this->loginUID ($uid));
            } // Has DB data
        } // Has semi-valid email address
        return (false);
    } // login
    
    
    public function isPassword ($password = false, $uid = false)
    {
        if ($this->uid && ($uid === false))
            $uid = $this->uid;
        if ($uid = intVal ($uid))
        {
            if ($pass = $this->dbGetValue ('users', 'password', 'id='.$uid))
            {
                if ($pass == 'mail')
                    return (1);

                if (crypt ($password, $pass) == $pass)
                    return (true);
            } // Has Password value
        } // Has uid
        return (false);
    } // isPassword
    
    
    public function changeEmail ($email = false, $uid = false)
    {
        if ($this->uid && ($uid === false))
            $uid = $this->uid;
        
        // Preprocess Email
        if ($email && ($email = trim ($email)) && strlen ($email))
            $email = strtolower ($email);
        else $email = false;
        
        if ($email && ($uid = intVal ($uid)))
        {
            if ($this->dbGetValue ('users', 'email', 'email='.$this->dbStr($email)) === false)
            {
                $es = $this->checkEmail ($email);
                if (($es == 0) && $this->dbUpdate ('users', 'email='.$this->dbStr ($email).',verified=\'f\'', 'id='.$uid))
                    return (true);
                return ($es);
            } // Has Password value
        } // Has uid
        return (false);
    } // changeEmail
    
    
    public function emailExists ($email = false)
    {
        // Preprocess Email
        if ($email && ($email = trim ($email)) && strlen ($email))
            $email = strtolower ($email);
        else $email = false;
        
        if ($email)
            return ($this->dbGetValue ('users', 'email', 'email='.$this->dbStr($email)) ? true : false);
        return (false);
    } // emailCheck
    
    
    public function getEGMailPassword ($email = false)
    {
        $db_pass = false;
        $email = trim ($email);
        if ($email && strlen ($email))
        {
            // Connect to EG Mailserver DB
            if ($db = mysql_connect('mail.eurekagenomics.com', 'web', 'webby.pass'))
            {
                if (mysql_select_db ('postfix', $db)) 
                {
                    if (($r = mysql_query ('SELECT password FROM mailbox WHERE (username='.$this->dbStr ($email).') AND (active=1) LIMIT 1', $db)) &&
                        ($rw = mysql_fetch_row ($r)))
                        $db_pass = $rw[0];
                } // Connected to Postfix
            } // Get DB connection
        } // has email string
        return ($db_pass);
    } // getEGMailPassword
    
    
    public function loginUID ($uid = false)
    {
        if ($uid = intVal ($uid))
        {
            // Double check that UID exists in the system
            if (intVal ($this->dbGetValue ('users', 'id', 'id='.$uid)) == $uid)
            {
                $this->sessionErase ('login');
                $this->sessionErase ('signup');
                // This enables login for the current Session ID
                $this->sessionLogin ($uid);
                // Update last_login time
                $this->dbUpdate ('users', 'last_login=NOW()', 'id='.$uid);
                
                // Load User object
                $this->load ($uid);
                return ($uid);
            } // ID exsists
        } // has UID
        return (false);
    } // loginUID
    
    
    public function getUID()
    {
        return ($this->uid);
    } // getUID
    
    
    public function getUIDFromEmail ($email = false)
    {
        $email = strtolower (trim ($email));
        if (strlen ($email))
        {
            $id = $this->dbGetValue ('users', 'id', 'email='.$this->dbStr ($email));
            return (intVal ($id));
        } // Has Page Object and Email
        return (false);
    } // getUIDFromEmail
    
} // ltsUser

?>