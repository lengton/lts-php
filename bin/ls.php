<?php
$then = microtime(true);
$GLOBALS['lts_mime_types'] = array (
    'css'  => 'text/css',
    'js'   => 'application/x-javascript',
    'gif'  => 'image/gif',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'html' => 'text/html',
    'htm'  => 'text/html',
    'woff' => 'application/x-font-woff',
    'pdf'  => 'application/pdf',
    'ttf'  => 'application/x-font-ttf'
);

// Setup include paths
ini_set ('include_path', ini_get('include_path').':'.$page_vars ['lts_data_path']);

function customClassLoader ($cn) { @include_once 'data/'.$GLOBALS['page_vars']['ln'].'/class/'.$cn.'.php'; } // CUSTOM CLASSES LOADER
function ltsClassLoader ($cn) { @include_once 'class/'.$cn.'.php';  } // LTS SYSTEM LOADER

spl_autoload_register ('ltsClassLoader');
spl_autoload_register ('customClassLoader');

// GLOBAL FUNCTIONS AND VARIABLES
include_once ('class/ltsGlobals.php');

// CLEAR CACHE -- if we're coming in from development
//if ($pp == 'dev') clearstatcache();

// Render Page
if ($url = new ltsURL ($page_vars))
{
    // Is this a static file?
    if ($url->getPageType() == 2)
    {
        // Retrieve file extension
        $ext = false;
        if (($path = $url->getPagePath ()) && strlen ($path))
        {
            if (($ep = strrpos ($path, '.')) !== false)
                $ext = strtolower (substr ($path, ($ep + 1)));
        } else $path = false;

        
        // Check if we're serving CSS files?
        if ($ext == 'css')
        {
            $recreate = false;
            
            // Get cache path for this file
            $cache_path = $url->getCachePath().$url->getURL('_');

            if ($cfp = @fopen ($cache_path, 'r'))
            {
                // Get last modification time
                $mtime = false;
                if ($cstat = fstat ($cfp))
                    $mtime = $cstat['mtime'];
                fclose ($cfp);
                            
                // Is source is newer than cached data
                if ($url->getModtime() > $mtime)
                {
                    unlink ($cache_path);
                    $recreate = true;
                } else $path = $cache_path;    
            } else $recreate = true; // Has file in cache?

            // Recreate this file?
            if ($recreate && ($p = new ltsPage ($url)))
            {
                $p->log ('loader: CSS file regenerated ['.$url->getURL().']');
                if (($out = $p->parseCSSfile ($path)) && file_put_contents ($cache_path, $out))
                    $path = $cache_path;
            } // Can parse the file?
        } // Parse CSS file?

        // X-Send file
        if ($path)
        {
            header('X-Sendfile: '.$path);
            if ($ext && @$GLOBALS['lts_mime_types'][$ext])
                header ('Content-type: '.$GLOBALS['lts_mime_types'][$ext]);
            else header ('Content-type: application/octet-stream');
//            $now = microtime(true);
//            syslog (LOG_NOTICE, sprintf ("%s (%s)%s: %f", $_SERVER['REMOTE_ADDR'], $pp, $path, ($now - $then)));
        } // Has Path?
        
    } else {
        if ($p = new ltsPage ($url))
        {
            // Se we know where this page loaded from
            //$p->setValue ('loadedFrom', $pp);
            
            // Do we have an empty URL?
            if ($p->emptyURL () && ($df = $p->getValue ('default_url'))) 
            {
                header ('Location: '.$df);
                exit (0);
            } // Check for empty URLs
            // Render/Load Page
            $p->render();
            
            $now = microtime(true);
            $p->log (sprintf ("%s %s [%s]: %f", $_SERVER['REMOTE_ADDR'], $p->getBaseURL(), $p->loadedFromCache(), ($now - $then)));
        } // Has ltsPage Object?
        
    } // What Page type?
} // Has ltsURL Object?


?>