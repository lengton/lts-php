<?php

class ltsDB extends ltsBase
{
    private $db = false;
    private $last_insert_id = false;
    private $db_type = false;  // 1 - MySQL, 0 - PostreSQL
    
    public function __construct ($pp = false)
    {
        // CALL PARENT CONSTRUCTOR
        parent::__construct();
        
        if ($pp && is_array ($pp))
        {
            // WHICH DATABASE?
            if (isset ($pp['pgsql']))
            {
                if (($this->db = @pg_pconnect($pp['dbstr'])) === false)
                {
                    $this->log ('ltsDB(PgSQL): Cannot connect to system database ['.$pp['dbstr'].']');
                    $this->db = false;
                } // SELECT DATABASE
            } else if (isset ($pp['mysql']))
            {
                $this->db_type = 1;
                $this->db = mysql_connect($pp['server'], $pp['user']);
                if (!mysql_select_db ($pp['db'], $this->db))    
                {
                    $this->log ('ltsDB(MySQL): Cannot connect to '.$pp['server'].':'.$pp['db']);
                    $this->db = false;
                } // SELECT DATABASE 
            } // MYSQL DATABASE?
        } // PASSED AN ARRAY
    } // contructor
    
        
    public function dbUpdate ($table, $values, $where)
    {
        // IN MYSQL, dbAffectedRows RETURNS FALSE WHEN UPDATING A ROW WITH THE SAME VALUES
        // UPDATE SOMETHING e.g. TIMESTAMP TO HAVE dbAffectedRows RETURN TRUE FOR UPDATE
        if (($r = $this->dbExec ('UPDATE '.$table.' SET '.$values.' WHERE '.$where)) && (($ar = $this->dbAffectedRows ($r)) > 0))
            return ($ar);   
        return (false);
    } // dbUpdate
    
    
    public function dbExec ($qry, $store_id = false)
    {
        if ($qry && $this->db)
        {
            if ($this->db_type)
            {
                // MYSQL
                if ($r = mysql_query ($qry, $this->db))
                {
                    if ($store_id)
                        $this->last_insert_id = mysql_insert_id ($this->db);
                    return ($r);
                } // Query OK?
            } else {
                // PGSQL
                if ($r = pg_query ($this->db, $qry))
                {
                    if ($store_id && ($sq = pg_query ($this->db, 'SELECT currval(\''.$store_id.'_id_seq\'::regclass)')))
                    {
                        $rw = $this->dbGetRow ($sq);
                        $this->last_insert_id  = intVal ($rw[0]);
                    } // RETRIEVE LAST INSERTED ID
                    return ($r);
                } // Has Return query?
            } // Type of DB
        } // Has Query and DB connection?
        return (false);
    } // dbExec

    
    public function dbLastID ()
    {
        return ($this->last_insert_id);
    } // dbLastID
    
    
    public function dbNumRows ($r)
    {
        if ($this->db_type)
        {
            // MYSQL
            return (mysql_num_rows ($r));
        } return (pg_num_rows ($r));
    } // dbNumRows
    
    
    public function dbAffectedRows ($r = false)
    {
        if ($this->db_type)
        {
            // MYSQL
            if ($this->db)
                return (mysql_affected_rows ($this->db));
        } else {
            // PGSQL
            if ($this->db && $r)
                return (pg_affected_rows ($r));
        } // Type of DB
        return (false);
    } // dbAffectedRows
    
    
    public function dbGetRow ($r)
    {
        if ($this->db_type)
        {
            // MYSQL
            return (mysql_fetch_row ($r));
        } else return (pg_fetch_row ($r));
    } // dbGetRow
    
    
    public function dbGetRowAssoc ($r)
    {
        return (pg_fetch_assoc ($r));
    } // dbGetRowAssoc
    
    
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
    
    
    public function dbStr ($str)
    {
        if ($this->db_type)
        {
            // MYSQL
            if (strlen ($str))
                return ('\''.mysql_escape_string($str).'\'');
            return ('NULL');
        } else return (parent::dbStr ($str));
    } // dbStr
    
    
    public function dbGetValue ($table, $values, $where)
    {
        if (($r = $this->dbExec ('SELECT '.$values.' FROM '.$table.' WHERE '.$where)) && $this->dbNumRows ($r)) 
        {
            $rw = $this->dbGetRow ($r);
            if (count($rw) > 1) return ($rw);
            return ($rw[0]);
        } // HAS DB VALUE
        return (false);
    } // dbGetValue
    
} // ltsDB
?>