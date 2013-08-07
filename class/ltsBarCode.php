<?php

class ltsBarCode extends ltsPage
{
    private static $font_name = false; 
    private static $ccode = array (
        0 => '3211',
        1 => '2221',
        2 => '2122',
        3 => '1411',
        4 => '1132',
        5 => '1231',
        6 => '1114',
        7 => '1312',
        8 => '1213',
        9 => '3112'
    );
    
    private static $bar_width = 1;  // 2 pixels wide
    private static $bar_height = 65; // pixels long
    private static $bar_margins = 5; // pixels margin
    private static $font_height = 12; // Font height
    private static $bar_smargins = 15; // left margin
    
    private $x = 0;
    private $y = 0;
    private $solid = 0;
    private $white = 0;
    private $black = 0;
    
    private $image = false;


    
    public function __construct ($pp = false)
    {
        // CALL PARENT CONSTRUCTOR
        parent::__construct($pp);
        
        self::$font_name = $this->getValue ('raw_path').'/resource/Noxchi_Arial.ttf';
    } // contructor
    
    
    public function intcode ($v)
    {
        $s = sprintf ("%011d", $v);
        $this->barcode ($s);
    } // intcode
    
    
    public function barcode ($str = false)
    {
        if ($str && ($len = strlen ($str)))
        {
            // WE SHOULD ALWAYS HAVE 10 DIGITS
            if ($len < 11)
            {
                $pad = (11 - $len);
                while ($pad) {
                    $str .= '0';
                    $pad--;
                } // WHILE
                $len = strlen ($str);
            } // Less than 10?
            
            // WE SHOULD HAVE ONLY 10 DIGITS
            if ($len > 11) {
                $str = substr ($str, 0, 11);
                $len = 11;
            } // TEN DIGITS ONLY
            
            $im_width = ((($len + 2) * 7) * self::$bar_width) + (6 * self::$bar_width) + (2 * self::$bar_margins) +
                        (5 * self::$bar_width) + (self::$bar_smargins * 2);
            $im_height = self::$bar_height + (2 * self::$bar_margins);
            
            // Create Barcode Canvas
            if (!($this->image = imagecreate ($im_width, $im_height)))
                return (false);
                
            // Compute Check digit -- Odd
            $odd = $even = false;
            for ($i = 0; $i < $len; $i++)
            {
                if ($i % 2) {
                    // EVEN
                    $even += intVal ($str[$i]);
                } else {
                    // ODD
                    $odd += intVal ($str[$i]);
                } // COMPUTE
            } // FOR
            $val = ($odd * 3) + $even;
            $check_digit = 0;
            while (($val + $check_digit) % 10)
                $check_digit++;
                
            // White background
            $this->white = imagecolorallocate ($this->image, 255, 255, 255);
            $this->black = imagecolorallocate ($this->image, 0, 0 ,0);
            
            // Initialize Vars
            $this->x = $this->y = self::$bar_margins;
            $this->x += self::$bar_smargins;
            $this->solid = true;
            
            // Header lines
            $this->drawCode ('111', true);
            
            // Final Barcode string
            $code = $str.$check_digit;
            $txt_size = (self::$font_height - 4);
            $txt_y = $this->y + (self::$bar_height);

            for ($i = 0; $i < strlen ($code); $i++)
            {
                $long = false;
                if (($i == 0) || ($i == 11)) 
                {
                    if ($i == 0) $txt_x = (self::$bar_smargins - $txt_size) + 5;
                    if ($i == 11) $txt_x = ($im_width - self::$bar_smargins) - (self::$font_height - 3);
                    $long = true;
                }
                $bcode = self::$ccode[intVal ($code[$i])];
                if (!$long)
                    $txt_x = ($this->x + 1);
                $this->drawCode ($bcode, $long);
                imagettftext ($this->image, $txt_size, 0, $txt_x, $txt_y, $this->black, self::$font_name, $code[$i]);

                
                // SECOND HALF?
                if ($i == 5) 
                {
                    $this->solid = false;
                    $this->drawCode ('11111', true);
                    $this->solid = true;
                } // OUT DIVIDER
            } // FOR
            
            // 	Trailing lines
            $this->solid = true;
            $this->drawCode ('111', true);
                
            // Output image 
            header ("Content-type: image/gif");
            imagegif ($this->image);
            imagedestroy($this->image);
        } // HAS STRING
        return (false);
    } // barcode
    
    
    private function drawCode ($code, $long = false)
    {
        if ($code)
        {
            for ($i = 0; $i < strlen ($code); $i++)
                $this->drawLine ($code[$i], $long);
        } // has Code string
    } // drawCode
    
    
    private function drawLine ($w, $long = false)
    {
        if ($c = intVal ($w))
        {
            $color = ($this->solid ? $this->black : $this->white);
            
            // COMPUTE LINE VALUE
            $x1 = $this->x;
            $y1 = $this->y;
            $x2 = ($x1 + (self::$bar_width * $c)) - 1;
            $y2 = ($y1 + ($long ? self::$bar_height : (self::$bar_height - self::$font_height)));
            
            imagefilledrectangle ($this->image, $x1, $y1, $x2, $y2, $color);
            
            // REPOSITION
            $this->x = ($x2 + 1);
            $this->solid = ($this->solid ? 0 : 1);
            
            return ($c);
        } // Has Valid line width
        return (false);
    } // drawLine
    
        
} // ltsBarCode
?>