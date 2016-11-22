<?php
 /**
  * Apply colors to a terminal application.
  * See: http://ascii-table.com/ansi-escape-sequences.php
  *
  * @file ColorTerm.php
  * @date 2014-10-22 14:18 PDT
  * @author Paul Reuter
  * @version 1.1.0
  *
  * @modifications <pre>
  * 1.0.0 - 2014-08-20 - Created from template: phpclass
  * 1.1.0 - 2014-10-22 - Add: bunch of controlling sequences.
  * </pre>
  */



/**#@+
 * Defined constants:
 */
// None
/**#@-*/


/**
 * Apply colors to a terminal application.
 * @package ColorTerm
 */
class ColorTerm {
  /**
   * Foreground color dictionary [name] => value.
   * @access private
   */
  private $foreground_colors = array();
  /**
   * Background color dictionary [name] => value.
   * @access private
   */
  private $background_colors = array();
  /**
   * Font faces / formats (dim, underline, blink, etc.)
   * @access private
   */
  private $font_face = array();

  /**
   * Apply colors to a terminal application.
   *
   * @access public
   * @return new ColorTerm object
   */
  public function __construct() {
    // Set up shell colors
    // http://www.tldp.org/HOWTO/Bash-Prompt-HOWTO/x329.html
    $this->foreground_colors['black'] = '0;30';
    $this->foreground_colors['red'] = '0;31';
    $this->foreground_colors['green'] = '0;32';
    $this->foreground_colors['yellow'] = '1;33';
    $this->foreground_colors['blue'] = '0;34';
    $this->foreground_colors['purple'] = '0;35';
    $this->foreground_colors['cyan'] = '0;36';
    $this->foreground_colors['light_gray'] = '0;37';
    $this->foreground_colors['dark_gray'] = '1;30';
    $this->foreground_colors['light_blue'] = '1;34';
    $this->foreground_colors['light_green'] = '1;32';
    $this->foreground_colors['light_cyan'] = '1;36';
    $this->foreground_colors['light_red'] = '1;31';
    $this->foreground_colors['light_purple'] = '1;35';
    $this->foreground_colors['brown'] = '0;33';
    $this->foreground_colors['white'] = '1;37';

    $this->background_colors['black'] = '40';
    $this->background_colors['red'] = '41';
    $this->background_colors['green'] = '42';
    $this->background_colors['yellow'] = '43';
    $this->background_colors['blue'] = '44';
    $this->background_colors['magenta'] = '45';
    $this->background_colors['cyan'] = '46';
    $this->background_colors['light_gray'] = '47';

    $this->font_face['bold'] = '1';
    $this->font_face['dim'] = '2';
    $this->font_face['underline'] = '4';
    $this->font_face['blink'] = '5';
    $this->font_face['reverse'] = '7';
    $this->font_face['invert'] = '7';
    $this->font_face['hidden'] = '8';
  } // END: constructor ColorTerm


  /**
   * Generate and return a formatted string with escape sequences.
   *
   * @param string $str String to colorize.
   * @param string $fgc Font color.
   * @param string $bgc Background color.
   * @param string $face Font face/formatting.
   * @return string Formatted string that can be printed with echo().
   */
  public function format($str, $fgc=null, $bgc=null, $face=null) {
    $colored_string = "";
    $resp = "";

    // Check if given foreground color found
    if( isset($this->foreground_colors[$fgc]) ) {
      $resp .= "\033[" . $this->foreground_colors[$fgc] . "m";
    }
    // Check if given background color found
    if( isset($this->background_colors[$bgc]) ) {
      $resp .= "\033[" . $this->background_colors[$bgc] . "m";
    }
    // Check if given font face is found
    if( isset($this->font_face[$face]) ) { 
      $resp .= "\033[" . $this->font_face[$face] . "m";
    }

    // Add string and end coloring
    $resp .=  $str . "\033[0m";

    return $resp;
  } // END: function format($str, $fgc=null, $bgc=null)


  /**
   * Get list of supported forground colors.
   * @return array of string.
   */
  public function getForegroundColors() {
    return array_keys($this->foreground_colors);
  } // END: function getForgroundColors()


  /**
   * Get list of supported backround colors.
   * @return array of string.
   */
  public function getBackgroundColors() {
    return array_keys($this->background_colors);
  } // END: function getBackgroundColors()


  public function clearLine() {
    echo("\033[K");
  }

  public function clearScreen() {
    echo("\033[2J");
  }

  public function cls() {
    return self::clearScreen();
  }

  public function moveTo($x=0,$y=0) {
    echo("\033[${x};${y}H");
  }

  public function moveBy($dx=0,$dy=0) {
    if( $dx>0 ) {
      self::moveRight($dx);
    } else if( $dx<0 ) {
      self::moveLeft(abs($dx));
    }
    if( $dy>0 ) {
      self::moveDown($dy);
    } else if( $dy<0 ) {
      self::moveUp(abs($dy));
    }
  }

  public function moveLeft($n=1) {
    echo("\033[${n}D");
  }

  public function moveRight($n=1) {
    echo("\033[${n}C");
  }

  public function moveUp($n=1) {
    echo("\033[${n}A");
  }
  public function moveDown($n=1) {
    echo("\033[${n}B");
  }

  public function savePos() {
    echo("\033[s");
  }
  public function restorePos() {
    echo("\033[u");
  }

  private function textMode($mode) {
    echo("\033[${mode}m");
  }

  public function textNormal() {
    return self::textMode(0);
  }
  public function textBold() {
    return self::textMode(1);
  }
  public function textUnderscore() {
    return self::textMode(4);
  }
  public function textBlink() {
    return self::textMode(5);
  }
  public function textReverse() {
    return self::textMode(7);
  }
  public function textHidden() {
    return self::textMode(8);
  }

  public function getColors() {
    return array(
      0 => 'black',
      1 => 'red',
      2 => 'green',
      3 => 'yellow',
      4 => 'blue',
      5 => 'magenta',
      6 => 'cyan',
      7 => 'white'
    );
  }

  public function setColor($fgc=null,$bgc=null) {
    $colors = array_flip(self::getColors());

    $set = array();
    if( $fgc!==null ) {
      $fgc = strtolower($fgc);
      $set[] = (isset($colors[$fgc])) ? 30+$colors[$fgc] : 30+$fgc;
    }
    if( $bgc!==null ) {
      $bgc = strtolower($bgc);
      $set[] = (isset($colors[$bgc])) ? 40+$colors[$bgc] : 40+$bgc;
    }

    if( empty($set) ) {
      echo("\033[0m");
    } else {
      echo("\033[".implode(";",$set)."m");
    }
  }


  function setBackgroundColor($bgc) {
    return self::setColor(null,$bgc);
  }

  function setForeground($fgc) {
    return self::setColor($fgc,null);
  }


} // END: class ColorTerm


// EOF -- ColorTerm.php
?>
