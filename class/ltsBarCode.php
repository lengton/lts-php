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
    private $im_height = false;
    private $im_width = false;
    private $has_text = 1;
    private $border_width = 0;


    
    public function __construct ($pp = false)
    {
        // CALL PARENT CONSTRUCTOR
        parent::__construct($pp);
        
        self::$font_name = $this->getValue ('raw_path').'/resource/Noxchi_Arial.ttf';
    } // contructor
    
    
    public function __destruct ()
    {
        // Destroy GD image data
        if ($this->image)
            imagedestroy($this->image);
        $this->image = false;
    } // destructor
    
    
    public function setBorderWidth ($bw)
    {
        if ($bw = intVal ($bw))
        {
            if ($bw > 3) $bw = 3;
            if ($bw < 1) $bw = 0;
            if ($bw)
                $this->border_width = $bw;
        } // has Border width?
        return ($bw);
    } // setBorderWidth
    
    
    public function setHeight ($h)
    {
        if ($h = intVal ($h))
        {
            if ($this->has_text)
                if ($h < 30) $h = 30;
            else if ($h < 5) $h = 5;
            if ($h > 100) $h = 80;
            self::$bar_height = $h;
        }
        return ($h);
    } // setHeight
    
    
    public function hasText ($v)
    {
        $this->has_text = intVal ($v);
    } // hasText
    
    
    public function &intcode ($v, $image_out = true)
    {
        $s = sprintf ("%011d", $v);
        $bh = $this->barcode ($s, $image_out);
        return ($bh);
    } // intcode
    
    
    
    public function &barcode ($str = false, $image_out = true)
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
            
            $this->im_width = ((($len + 2) * 7) * self::$bar_width) + (5 * self::$bar_width) + (2 * self::$bar_margins) +
                        (5 * self::$bar_width) + (self::$bar_smargins * 2);
            $this->im_height = self::$bar_height + (2 * self::$bar_margins);
            
            // Create Barcode Canvas
            if (!($this->image = imagecreate ($this->im_width, $this->im_height)))
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
            
            // Do we need to add borders?
            if ($this->border_width)
                $this->drawBorder();
            
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
                    if ($i == 11) $txt_x = ($this->im_width - self::$bar_smargins) - (self::$font_height - 3);
                    $long = true;
                }
                $bcode = self::$ccode[intVal ($code[$i])];
                if (!$long)
                    $txt_x = ($this->x + 1);
                $this->drawCode ($bcode, $long);
                
                // Do we need to output text?
                if ($this->has_text)
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
            
            
            // Do we need to output the image?
            if ($image_out)
            {
                // Output image 
                header ("Content-type: image/gif");
                imagegif ($this->image);
                imagedestroy($this->image);
                $this->image = false;
                $true = true;
                return ($true);
            } else {
                $img = $this->image;
                return ($img);
            } // Output the Image
        } // HAS STRING
        return (false);
    } // barcode
    
    
    public function getGDImage()
    {
        return ($this->image);
    } // getGDImage
    
    
    public function getHeight()
    {
        return ($this->im_height);
    } // getHeight
    
    
    public function getWidth()
    {
        return ($this->im_width);
    } // getWidth
    
    
    private function drawCode ($code, $long = false)
    {
        if ($code)
        {
            for ($i = 0; $i < strlen ($code); $i++)
                $this->drawLine ($code[$i], $long);
        } // has Code string
    } // drawCode
    
    
    private function drawBorder ()
    {
        if ($bw = $this->border_width)
        {
            // Fill the whole image with black
            imagefilledrectangle ($this->image, 0, 0, $this->im_width, $this->im_height, $this->black);
            
            // Fill the whole image with white
            imagefilledrectangle ($this->image, $bw, $bw, ($this->im_width - $bw) -1, ($this->im_height - $bw) -1, $this->white);
            
            return (true);
        } // has Border width?
        return (false);
    } // drawBorder
    
    
    private function drawLine ($w, $long = false)
    {
        if ($c = intVal ($w))
        {
            $color = ($this->solid ? $this->black : $this->white);
            
            // COMPUTE LINE VALUE
            $x1 = $this->x;
            $y1 = $this->y;
            $x2 = ($x1 + (self::$bar_width * $c)) - 1;
            
            // Do we need to make space for text?
            if ($this->has_text)
                $y2 = ($y1 + ($long ? self::$bar_height : (self::$bar_height - self::$font_height)));
            else $y2 = ($y1 + self::$bar_height);
            
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