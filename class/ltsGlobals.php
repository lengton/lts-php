<?php

// GLOBAL FUNCTIONS available to all scripts

function _p($s, $k)
{
    if ($s)
    {
        if (is_array($s) && array_key_exists ($k, $s))
            echo htmlspecialchars ($s[$k]);
        else if (is_a ($s, 'ltsResource'))
            echo htmlspecialchars ($s->getData ($k, 1));
    } // has valid $s
} // _p

function _g($s, $k, $conv = false)
{
    if ($s)
    {
        if (is_array($s) && array_key_exists ($k, $s)) {
            $v = $s[$k];
            return ($conv ? htmlspecialchars ($v) : $v);
        } else if (is_a ($s, 'ltsResource')) {
            $v = $s->getData ($k, 1);
            return ($conv ? htmlspecialchars ($v) : $v);
        } // An eg_object?
    } // has valid $s
    return (false);
} // _g

function _cs ($v, $t, $e = false, $r = ' class="s" ', $inv = false)
{
    $rs = $r;
    switch ($inv)
    {
        case false :
            if ($v != $t) $rs = ''; break;
        default :
            if ($v == $t) $rs = ''; break;
    } // SWITCH
    
    if ($e)
    {
        echo $rs;
        return (true);
    } // ECHO?
    return ($rs);
} // _cs -- Class Select

function _s($s, $k, $m, $t = 0, $d = false)
{
    $str = ($t ? ' checked' : ' selected').'="true"';
    if ($s && is_array($s) && array_key_exists ($k, $s))
    {
        if (strval($s[$k]) == $m)
            echo $str;
    } else if ($d) echo $str;
} // _s

function _m ($s, $k, $v)
{
    if ($s && is_array ($s) && array_key_exists ($k, $s))
    {
        $si = $s[$k];
        if (in_array ($v, $si))
            return (' selected="true"');
    }
    return ('');
} // _m

function _ers ($e, $k = false)
{
    if ($e && is_array ($e) && $k) {
        $e = @$e[$k];
        if (!$e || (strlen ($e) < 1))
            $e = false;
    } // Is $e an array?
        
    if ($e)
        echo '<div class="err">'.$e.'</div>';
} // _ers

function _er ($f, $k)
{
    if ($f)
    {
        _ers (@$f->error ($k));
    } // IS AN OBJECT?
} // _er

?>