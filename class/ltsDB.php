<?php

class ltsDB extends ltsBase
{
    private $db = false;
    private $last_insert_id = false;
    
    public function __construct ($pp = false)
    {
        // CALL PARENT CONSTRUCTOR
        parent::__construct();
        
        if ($pp && is_array ($pp))
        {
            $this->db = mysql_pconnect($pp['server'], $pp['user']);
            if (!mysql_select_db ($pp['db'], $this->db))    
            {
                $this->log ('ltsDB: Cannot connect to '.$pp['server'].':'.$pp['db']);
                $this->db = false;
            } // SELECT DATABASE 
        } // PASSED AN ARRAY
    } // contructor
    
        
    public function dbUpdate ($table, $values, $where)
    {
        // IN MYSQL, dbAffectedRows RETURNS FALSE WHEN UPDATING A ROW WITH THE SAME VALUES
        // UPDATE SOMETHING e.g. TIMESTAMP TO HAVE dbAffectedRows RETURN TRUE FOR UPDATE
//$this->log ('UPDATE '.$table.' SET '.$values.' WHERE '.$where);        
        if ($this->dbExec ('UPDATE '.$table.' SET '.$values.' WHERE '.$where) && (($ar = $this->dbAffectedRows ()) > 0))
            return ($ar);   
        return (false);
    } // dbUpdate
    
    
    public function dbExec ($qry, $store_id = false)
    {
        if ($qry && $this->db && ($r = mysql_query ($qry, $this->db)))
        {
            if ($store_id)
                $this->last_insert_id = mysql_insert_id ($this->db);
            return ($r);
        } // Query OK?          
        return (false);
    } // dbExec

    
    public function dbLastID ()
    {
        return ($this->last_insert_id);
    } // dbLastID
    
    
    public function dbNumRows ($r)
    {
        return (mysql_num_rows ($r));
    } // dbNumRows
    
    
    public function dbAffectedRows ()
    {
        if ($this->db)
            return (mysql_affected_rows ($this->db));
        return (false);
    } // dbAffectedRows
    
    
    public function dbGetRow ($r)
    {
        return (mysql_fetch_row ($r));
    } // dbGetRow
    
    
    public function dbInsert ($table, $value_set, $values)
    {
        $q = 'INSERT INTO '.$table;
        if ($value_set && strlen ($value_set))
            $q .= ' ('.$value_set.') ';
        $q .= 'VALUES ('.$values.')';
        if (($r = $this->dbExec ($q)) && ($ar = $this->dbAffectedRows ()))
            return ($ar);
        return (false);
    } // dbInsert
    
    
    public function dbDelete ($table, $where)
    {
        if (($r = $this->dbExec ('DELETE FROM '.$table.' WHERE '.$where)) && ($ar = $this->dbAffectedRows ()))
            return ($ar);
        return (false);
    } // dbDelete
    
    
    public function dbGetValue ($table, $values, $where)
    {
        if (($r = $this->dbExec ('SELECT '.$values.' FROM '.$table.' WHERE '.$where)) && $this->dbNumRows ($r)) 
        {
//$this->log ('SELECT '.$values.' FROM '.$table.' WHERE '.$where);        
            $rw = $this->dbGetRow ($r);
            if (count($rw) > 1) return ($rw);
            return ($rw[0]);
        } // HAS DB VALUE
        return (false);
    } // dbGetValue
    
} // ltsDB
?>