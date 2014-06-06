<?php

class ltsResource extends ltsPage
{
    public $rid = 0;

    protected $uid = NULL;
    protected $field_length = NULL;
    protected $data = NULL;
    protected $errors = NULL; 
    
    // This corresponds to the physical database fields.
    // Used when separating external data resource data from native resource data.
    // Asterisks (*) flags the field as read-only (during normal updates these fields are skipped).
    protected $db_field = NULL;
    protected $db_table = NULL;
    protected $error_key = NULL;
    

    public function __construct ($pp = false)
    {
        // CALL PARENT CONSTRUCTOR
        parent::__construct($pp);
        
        // Initialize Resource Object
        $this->resetResource();
        
        // Get User ID for this session
        $this->uid = $this->getSessionUID();
    } // contructor
    
    
    // $pt = PROCESS TYPE (ORing values processes the input value)
    // 0 - none
    // 1 - Trim value
    // 2 - Does not add/replace data if string is length 0.
    // 4 - Does a Word Upcase
    // 8 - Does an ALL uppercase
    // 16 - Does an ALL lowercase 
    public function setData ($k, $v, $pt = 0, $len = 0)
    {
        if ($pt && ($v !== false))
        {
            // Trim words
            if ($pt & 1) 
            {
                $rw = explode (' ', $v);  
                $v = '';
                foreach ($rw as $words) 
                {
                    $w = trim ($words);  
                    if (!strlen ($w))  
                        continue;
                    $v .= $w.' ';
                } // FOREACH
                $v = trim ($v);
            } // Trim value
            
            // Return if string value is empty
            if (($pt & 2) && (strlen ($v) < 1))
                return (false);
            
            // Upper case words    
            if ($pt & 4) {
                $v = ucwords ($v);
                // Check for all 'Mc' words      
                $w = explode (' ', $v); 
                $v = '';
                for ($i = 0; $i < count ($w); $i++)
                {
                    if (($w[$i][0] == 'M') && ($w[$i][1] == 'c'))
                        $w[$i][2] = strtoupper ($w[$i][2]);
                    $v .= $w[$i].' ';
                } // FOR
                $v = trim ($v);
            } // Upcase words?
            
            // Upper case data
            if ($pt & 8) $v = strtoupper ($v);
            
            // Lower case data
            if ($pt & 16) $v = strtolower ($v);
            
            // Do we have a set field length?
            if (!$len && $this->field_length)
               $len = $this->field_length;
               
            // Limit data to field length?
            if ($len) $v = substr ($v, 0, $len);
        } // HAS PROCESS TYPE
        // Save Data to object
        $this->data[$k] = $v;
        return ($v);
    } // setData
    
    
    public function resetResource()
    {
        // Initialize Data Containers
        $this->data = array();  
        $this->errors = array();  
        $this->field_length = 0;
    } // resetResource
    
    
    // $t = RETURN TYPE
    // $t = 1 : Returns a trimmed value
    // $t = 2 : Returns a trimmed strlen of the value
    // $t = 3 : Returns trimmed value, false if strlen is 0
    // $t = 4 : Returns intVal of value
    // else return the value
    public function getData ($k = false, $t = 0)
    {
        if ($this->data) 
        {
            // Return whole associative Array
            if ($k === false) return ($this->data);
            
            if ($k && array_key_exists ($k, $this->data)) 
            {
                $v = $this->data[$k];
                switch ($t)
                {
                    // Trim value
                    case 1 : 
                        return (trim ($v));
                    // Trim value then return strlen
                    case 2 :
                        $v = trim ($v);
                        return (strlen ($v));
                    // Returns value if strlen is not 0, else false
                    case 3 : 
                        $v = trim ($v);
                        return (strlen ($v) ? $v : false);
                    // Returns Integer value
                    case 4 : 
                        return (intVal ($v));
                    // Returns Float value
                    case 5 : 
                        return (floatVal ($v));
                    default:
                        return ($v);
                } // RETURN TYPE
            } // HAS KEY VALUE?
        } // VALID HEADER DATA?
        return (false);
    } // getData
    
    
    public function getSerializeData ($k = false)
    {
        $data = false;
        if ($data = $this->getData ($k))
            return (@unserialize (base64_decode ($data)));
        return ($data);
    } // getSerializedData
    
    
    public function setSerializeData ($k = false, $v = false, $pt = 0, $len = 0)
    {
        return ($this->setData ($k, base64_encode (serialize ($v)), $pt, $len));
    } // setSerializeData
    
    
    public function unsetData ($k)
    {
        if (array_key_exists ($k, $this->data))
        {
            unset ($this->data[$k]);
            return (true);
        } // Unset value
        return (false);
    } // unsetData
    
    
    public function error ($k = false, $m = false)
    {
        if ($k === false)
            return ($this->errors);
            
        if ($m !== false) 
            $this->errors[$k] = $m;
        else if (isset ($this->errors[$k]))
            return ($this->errors[$k]);
        return (false);
    } // error
    
    
    public function unsetError ($k = false)
    {
        if ($k)
        {
            unset ($this->errors[$k]);
            return (true);
        } // Has Key?
        return (false);
    } // unsetError
    
    
    public function hasErrors()
    {
        if (empty ($this->errors))
            return (false);
        return (count ($this->errors));
    } // hasErrors
    
    
    public function clearErrors()
    {
        $this->errors = array();
    } // clearErrors
    
    
    public function stagingSave ($isid = false, $sid = false)
    {
        // Usually a staging save is a one time deal
        // we only save/update objects WITH our data contents (data part only)
        if (($isid = intVal ($isid)) && $this->data && ($sid == false))
        {
            $data = base64_encode (serialize ($this->data));
            $q = 'INSERT INTO staging (isid,rid,data) VALUES ('.$isid.','.$this->rid.','.$this->dbStr ($data).')';
            if ($this->dbExec ($q, 'staging'))
            {
                $sid = intVal ($this->dbLastID());
                $this->setData ('staging_id', $sid);  // Assign Stagging ID
                $this->unsetData ('id');   // Force remove this key, since we're INSERTING
                return ($sid);
            } // Successfully saved?
        } // Has ImportSet ID?
        
        // If $sid has value lets update our data
        if (($sid = intVal ($sid)) && ($isid == false))
        {
            $data = base64_encode (serialize ($this->data));
            if ($this->dbUpdate ('staging', 'data='.$this->dbStr ($data), 'id='.$sid))
            {
                $this->setData ('staging_id', $sid);
                // Remember we may assume that ->getData ('id') exists
                return ($sid);
            } // Update successful?
        } // Has Stagging ID value?
        
        return (false);
    } // stagingSave
    
    
    public function stagingLoad ($sid = false)
    {
        // Replaces this objects data (DATA ONLY!!!) with the data on the staging area
        if ($sid = intVal ($sid))
        {
            // LOAD FROM STAGGING TABLE
            if ($data = $this->dbGetValue ('staging', 'data', 'id='.$sid))
            {
                $this->resetResource();
                $this->data = unserialize (base64_decode ($data));
                $this->setData ('staging_id', $sid);  // Assign staging ID
                return ($sid);
            } // Has data from staging table?
        } // Has ImportSet ID?
        return (false);
    } // stagingLoad
    
    
    public function save ($urid = false)
    {
        // Determine what kind of data are we saving
        // Usually all Resource objects are associated with a User
        $urid = intVal ($urid);

        // Determine RID, User type is a special case
        if ($this->rid == 1) 
        {
            if (!$urid)
                $urid = $this->uid;
        } else {
            if (!$urid && $this->getData ('id', 4))
                $urid = $this->getData ('id', 4);
        } // Which resource are we working on?
        
        // Do we have a table to process?
        // We ALWAYS NEED a User ID associated for every resource
        if ($this->db_table && isset ($this->db_field))
        {
            // Determine if we need to do an INSERT or UPDATE
            // Depending on the value of $urid. A zero means an INSERT.
            if ($urid)
            {
                // We'll be storing the field names here
                // for use to extract the External data
                $db_field = array();
                
                // Build query string
                $qry = 'UPDATE '.$this->db_table.' SET ';
                $indx = 0;
                foreach ($this->db_field as $fields)
                {
                    // Skip fields without an attribute divider
                    if (strpos ($fields, ':') === false)
                        continue;
                    // Extract field name and attributes
                    $fld_tuple = explode (':', $fields);
                    if (count ($fld_tuple) < 1)
                        continue;
                    // Store 'em
                    $fld_name = $fld_tuple[0];
                    $fld_attr = $fld_tuple[1];
                    $db_field[] = $fld_name;	// Append to array
                    
                    // Only update the fields that ARE EXISTING in the data array
                    if (array_key_exists ($fld_name, $this->data))
                    {
                        $v = 'NULL';
                        // Skip Read-only fields
                        if (strpos ($fld_attr, 'r') !== false)
                            continue;
                        // Skip crossed-out fields
                        else if (strpos ($fld_attr, 'x') !== false)
                            continue;
                        // Numerical fields
                        else if (strpos ($fld_attr, 'n') !== false)
                            $v = intVal ($this->getData ($fld_name));
                        // Float fields
                        else if (strpos ($fld_attr, 'f') !== false)
                            $v = floatVal ($this->getData ($fld_name));                            
                        // Boolean types
                        else if (strpos ($fld_attr, 'b') !== false)
                            $v = $this->dbStr ((intVal ($this->getData ($fld_name)) ? 't' : 'f'));
                        // Three-way boolean
                        else if (strpos ($fld_attr, 'y') !== false)
                        {
                            if ($this->getData ($fld_name) === false)
                                $v = 'NULL';
                            else {
                                switch (strtolower ($this->getData ($fld_name)))
                                {
                                    case 'f' :
                                    case '0' :
                                        $v = $this->dbStr ('f');
                                        break;
                                        
                                    case 't' :
                                    case '1' : 
                                        $v = $this->dbStr ('t');
                                        break;
                                    
                                    case 'p' :
                                    default :
                                        $v = 'NULL';
                                } // SWITCH
                            } // If not NULL
                        } // Three-way boolean
                        // String fields
                        else if (strpos ($fld_attr, 's') !== false)
                            $v = $this->dbStr ($this->getData ($fld_name, 3));
                    } else continue;
                    $qry .= $fld_name.'='.$v.',';
                } // FOREACH
                
                // Remove extra comma
                $qry = rtrim ($qry, ',').' WHERE id='.$urid;
                
                // Save any extended data
                foreach ($this->data as $key => $value)
                {
                    // Skip fields that are static data
                    if (in_array ($key, $db_field))
                        continue;
                        
                    // Skip fields that has names starting with 'tmp_'
                    // Good for storing temporary data for Object serialization
                    if (strpos ($key, 'tmp_') === 0)
                        continue;
                    
                    // Let the Base class handle saving for us
                    $this->dbExtData ($this->rid, $urid, $key, $value);
                } // FOREACH

                // Commit to Database
                if ($this->dbExec ($qry))
                    return ($urid);
                
            } else {
            
                // INSERT A NEW RECORD ENTRY
                if ($this->uid)
                    $this->setData ('uid', $this->uid);  // Assign UID -- usually a standard field name for all resources
                
                // IMPORTANT!!!! Resources should have a 'modified' field
                $this->setData ('modified', $this->getTimestamp());

                $qry = 'INSERT INTO '.$this->db_table.' (';
                // Only insert fields with values, else we'll just
                // use the DB defaults
                foreach ($this->db_field as $fields)
                {
                    // Skip fields without an attribute divider
                    if (strpos ($fields, ':') === false)
                        continue;
                        
                    // Extract field name and attributes
                    $fld_tuple = explode (':', $fields);
                    if (count ($fld_tuple) < 1)
                        continue;
                        
                    if (array_key_exists ($fld_tuple[0], $this->data))
                        $qry .= $fld_tuple[0].',';
                } // FOREACH
                $qry = rtrim ($qry, ',').') VALUES (';
                
                // Now the values
                foreach ($this->db_field as $fields)
                {
                    // Skip fields without an attribute divider
                    if (strpos ($fields, ':') === false)
                        continue;
                    // Extract field name and attributes
                    $fld_tuple = explode (':', $fields);
                    if (count ($fld_tuple) < 1)
                        continue;
                    $fld_name = $fld_tuple[0];
                    $fld_attr = $fld_tuple[1];
                    
                    // Only insert the fields that ARE EXISTING in the data array
                    // We don't skip fields because we're creating rows
                    if (array_key_exists ($fld_name, $this->data))
                    {
                        // Numberical fields
                        if (strpos ($fld_attr, 'n') !== false)
                            $v = intVal ($this->getData ($fld_name));
                        // Float fields
                        else if (strpos ($fld_attr, 'f') !== false)
                            $v = floatVal ($this->getData ($fld_name));
                        // Boolean types
                        else if (strpos ($fld_attr, 'b') !== false)
                            $v = $this->dbStr ((intVal ($this->getData ($fld_name)) ? 't' : 'f'));
                        // Three-way booleans
                        else if (strpos ($fld_attr, 'y') !== false)
                        {
                            if ($this->getData ($fld_name) === false)
                                $v = 'NULL';
                            else if ($this->getData ($fld_name) === 1)
                                $v = 't';
                            else if ($this->getData ($fld_name) === 0)
                                $v = 'f';
                        } // Three-way 
                        // String fields
                        else if (strpos ($fld_attr, 's') !== false)
                            $v = $this->dbStr ($this->getData ($fld_name));
                    } else continue;
                    $qry .= $v.',';
                } // FOREACH
                $qry = trim ($qry, ',').')';;

                // Commit to Database
                if ($this->dbExec ($qry, $this->db_table))
                {
                    $this->setData ('id', intVal ($this->dbLastID()));
                    return ($this->getData ('id', 4));
                } else $this->error ($this->error_key, 'There was a Database error');
                return (false);
            } // INSERT or UPDATE?
        } // Are we able to process?
        return (false);
    } // save
    
    
    public function load ($urid = false, $load_from_staging = false)
    {
        // Remember resources are always associated with a User
        $urid = intVal ($urid);
        
        // Special case resource, User resource
        if (($this->rid == 1) && (!$urid))
            $urid = $this->uid;
        
        // Do we have a valid Table?
        if ($this->db_table && $urid)
        {
            // Build query string
            $qry = 'SELECT ';
            foreach ($this->db_field as $fields)
            {
                // Skip fields without an attribute divider
                if (strpos ($fields, ':') === false)
                    continue;
                    
                // Extract field name and attributes
                $fld_tuple = explode (':', $fields);
                if (count ($fld_tuple) < 1)
                    continue;
                
                // Process field attributes
                if (strpos ($fld_tuple[1], 'x') !== false)
                    continue;
                
                $qry .= $fld_tuple[0].',';
            } // FOREACH
            
            // Remove extra comma
            $qry = rtrim ($qry, ',').' FROM '.$this->db_table.' WHERE id='.$urid;
            
            // Retrieve data from Database
            if (($r = $this->dbExec ($qry)) && $this->dbNumRows ($r))
            {
                // Query went through... now load it to the data array
                $rw = $this->dbGetRow ($r);
                $indx = 0;
                foreach ($this->db_field as $fields)
                {
                    // Skip fields without an attribute divider
                    if (strpos ($fields, ':') === false)
                        continue;
                        
                    // Extract field name and attributes
                    $fld_tuple = explode (':', $fields);
                    if (count ($fld_tuple) < 1)
                        continue;
                        
                    // Skip crossed-out fields
                    if (strpos ($fld_tuple[1], 'x') !== false)
                        continue;
                        
                    // Set the value
                    $value = $rw[$indx];
                    
                    // Unique to PostreSQL, convert boolean types to Integer
                    if (strpos ($fld_tuple[1], 'b') !== false)
                        $value = ($value == 't' ? 1 : 0);
                    // Get the field name
                    $this->setData ($fld_tuple[0], $value);
                    $indx++;
                } // FOREACH
            } else {
                // IF WE CAN'T GET DATA THEN GIVE UP
                return (false);
            } // Do we have Data?
            
            // Now merge External data for this resource
            if ($ext_data = $this->dbGetExtData ($this->rid, $urid))
                $this->data = array_merge ((array) $this->data, (array) $ext_data);
            return ($urid);
        } // Are we able to process?
        return (false);
    } // load
    
    
    public function serialize ($name = NULL)
    {
        if (isset ($name) && strlen ($name))
        {
            $class_name = get_class ($this);
            
            // Build data container
            $data = array ();
            $data['m'] = $this->data;
            $data['e'] = $this->errors;
            $data_str = $class_name.'#'.base64_encode (serialize ($data));
            $this->sessionReplace ($name, $data_str);
            return (true);
        } // Has name?
        return (false);
    } // serialize
    
    
    public function unserialize ($name = NULL)
    {
        // Take note that we only unserialize the 'data' portion
        // of the resource. So ltsPage, ltsBase instance values take the 
        // values of the current instance that will unserialize this
        if (isset ($name) && strlen ($name) && $this->rid)
        {
            $data_str = $this->sessionGet ($name);
            if (($dv = strpos ($data_str, '#')) !== false)
            {
                $s_name = substr ($data_str, 0, $dv);
                if ($s_name == get_class ($this))
                {
                    $s_data = substr ($data_str, ($dv + 1));
                    $data = unserialize (base64_decode ($s_data));
                    $this->data = $data['m'];
                    $this->errors = $data['e'];
                    return (true);
                } // Valid class Object
            } // Has valid data/name division
        } // Have valid Resource ID?
        return (false);
    } // unserialize
    
    
    public function harvest ($ar)
    {
        if ($ar && is_array ($ar))
        {
            if (isset ($this->db_field))
            {
                foreach ($this->db_field as $fields)
                {
                    // Skip fields without an attribute divider
                    if (strpos ($fields, ':') === false)
                        continue;
                    
                    // Extract field name and attributes
                    $fld_tuple = explode (':', $fields);
                    if (count ($fld_tuple) < 1)
                        continue;
                    
                    // Skip non-read values
                    if (strpos ($fld_tuple[1], 'x') !== false)
                        continue;
                    // Don't harvest read-only variables
                    if (strpos ($fld_tuple[1], 'r') !== false)
                        continue;                        
                    
                    // Harvest data from Array, does a trim
                    $value = @$ar[$fld_tuple[0]];
                    if (isset ($value))
                        $this->setData ($fld_tuple[0], $value, 1);
                } // FOREACH
                return (true);
            } // We should have db_field populated
        } // Do we have an array?
        return (false);
    } // harvest
    
    
    public function setObjectData ($data)
    {
        if ($data && is_array ($data))
            $this->data = $data;
    } // setObjectData
    
    
    public function setObjectError ($error)
    {
        if ($error && is_array ($error))
            $this->errors = $error;
    } // setObjectError
    
    
    public function getUID()
    {
        return ($this->uid);
    } // getUID
    
    
    public function getTableName()
    {
        return ($this->db_table);
    } // getTableName
    
    
    // This function should be overloaded by the child object
    // That implements EGRender. IMPORTANT!!!
    public function getDBFieldsCache()
    {
        return (false);
    } // getDBFieldsCache
    
    
    // O(n) slow but sure
    public function getFieldType ($f = false)
    {
        if ($f && strlen ($f) && $this->db_field)
        {
            foreach ($this->db_field as $field)
            {
                if (($fp = strpos ($field, ':')) === false)
                    continue;
                $dbf = substr ($field, 0, $fp);
                $type = substr ($field, ($fp + 1));
                if ($f == $dbf)
                    return ($type);
            } // FOREACH
        } // Has Field name and DB Table?
        return (false);
    } // getFieldType 
    
    
    public function dbGetNextID ()
    {
        // Get's next ID from our sequence
        return (parent::dbGetNextID ($this->db_table));
    } // dbGetNextID
                    
    
} // ltsResource
?>