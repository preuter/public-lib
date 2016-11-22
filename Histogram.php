<?php
 /**
  * Helper class for creating histograms from data.
  *
  * @file Histogram.php
  * @date 2012-05-06 04:27 HST
  * @author Paul Reuter
  * @version 1.1.0
  *
  * @modifications <pre>
  * 1.0.0 - 2010-10-18 - Created
  * 1.1.0 - 2012-05-06 - Modify: bin accepts frqs, ret: [tick,cnt,val]
  * </pre>
  */


/**
 * Helper class for creating histograms from data.
 * @package Math
 */
class Histogram {
  var $_opts;


  function Histogram() {
    $this->m_initOpts();
    return true;
  } // END: function Histogram()


  /**
   * Perform the binning operation on $ys.
   *
   * @param array $ys An array of values to bin, according to options.
   * @return array An array of (<bin-coords>,<bin-values>)
   *   <bin-coords> is an array of numbers.
   *   <bin-values> is an array of arrays of input $ys values, binned.
   */
  function bin($ys,$frq=null) {
    if( !is_array($ys) || count($ys) < 1 ) { 
      return array();
    }
    if( !is_array($frq) ) { 
      $frq = array_fill(0,count($ys),1);
    }
    if( count($frq)!==count($ys) ) { 
      return array();
    }

    // Get target ticks from data (or user input)
    sort($ys);
    $ny = count($ys);
    $ticks = $this->m_getTicks($ys[0],$ys[$ny-1]);

    // Initialize bins
    $bins = array();
    $vals = array();
    for($i=0,$n=count($ticks); $i<$n; $i++) { 
      $bins[$i] = 0;
      $vals[$i] = array();
    }

    if( $this->_opts['BIN_LEFT'] ) { 
      $yi = 0;
      $ti = 0;
      $nt = count($ticks);
      while( $ti < $nt-1 && $yi < $ny ) { 
        if( $ys[$yi] < $ticks[$ti] ) { 
          $yi += 1;
        } else if( $ys[$yi] >= $ticks[$ti+1] ) { 
          $ti += 1;
        } else { 
          $bins[$ti] += $frq[$yi];
          $vals[$ti][] = $ys[$yi];
          $yi += 1;
        }
      }
      // Above loop stopped at 1 tick before last, 
      // append all remaining values to this last tick.
      while( $yi < $ny ) { 
        $bins[$ti] += $frq[$yi];
        $vals[$ti][] = $ys[$yi];
        $yi++;
      }

    } else if( $this->_opts['BIN_RIGHT'] ) { 
      $yi = 0;
      $ti = 0;
      $nt = count($ticks);
      while( $ti < $nt && $yi < $ny ) { 
        if( $ys[$yi] <= $ticks[$ti] ) { 
          $bins[$ti] += $frq[$yi];
          $vals[$ti][] = $ys[$yi];
          $yi += 1;
        } else { 
          $ti += 1;
        }
      }

    } else { // if( $this->_opts['BIN_CENTER']) {
      $yi = 0;
      $ti = 0;
      $nt = count($ticks);
      while( $ti < $nt-1 && $yi < $ny ) { 
        // add to current tick if ys[yi] <= ticks[ti]
        // -- always ensure current ys[yi] > ticks[ti]
        if( $ys[$yi] <= $ticks[$ti] ) { 
          $bins[$ti] += $frq[$yi];
          $vals[$ti][] = $ys[$yi];
          $yi += 1;
        } else {
        // compare diff between ticks[ti] < ys[yi] < ? ticks[ti+1]
          if( $ys[$yi]-$ticks[$ti] < $ticks[$ti+1]-$ys[$yi] ) { 
          // append to current tick if ys[yi] is closer
            $bins[$ti] += $frq[$yi];
            $vals[$ti][] = $ys[$yi];
            $yi += 1;
          } else {
          // increment tick
            $ti += 1;
          }
        }
      }
      // append remaining ys to last tick
      while( $yi < $ny ) { 
        $bins[$ti] += $frq[$yi];
        $vals[$ti][] = $ys[$yi];
        $yi++;
      }
    }

    return array($ticks,$bins,$vals);
  } // END: function bin($ys)
  

  /**
   * Compute the tick boundaries based on options.
   * Ticks are computed either by:
   *  1) User-specified
   *  2) BIN_SIZE was set
   *  3) BIN_COUNT was set (default)
   *
   * @param float $lo Min value of data to get ticks for.
   * @param float $hi Max value of data to get ticks for.
   * @return array of ticks, according to BIN_LEFT, BIN_CENTER, BIN_RIGHT
   */
  function m_getTicks($lo,$hi) {
    if( is_array($this->_opts['BIN_TICKS']) ) { 
      $ticks = $this->_opts['BIN_TICKS'];

    } else if( $this->_opts['BIN_SIZE'] !== null ) {
      if( $lo < 0 ) {
        $rem = fmod($lo,$this->_opts['BIN_SIZE']);
        if( $rem < -0.000002 || $rem > 0.000002 ) { 
          $lo = $lo - $this->_opts['BIN_SIZE'] - $rem;
        }
      } else { 
        $lo -= fmod($lo,$this->_opts['BIN_SIZE']);
      }
      $dy = $this->_opts['BIN_SIZE'];
      $ticks = array();
      for($y=$lo; $y<$hi; $y+=$dy) { 
        if( $this->_opts['BIN_LEFT'] ) { 
          $ticks[] = $y;
        } else if( $this->_opts['BIN_RIGHT'] ) { 
          $ticks[] = $y+$dy;
        } else { // if( $this->_opts['BIN_CENTER']) {
          $ticks[] = $y+$dy/2;
        }
      }

    } else {
      $ticks = array_fill(0,$this->_opts['BIN_COUNT'],null);
      $dy = ($hi-$lo) / $this->_opts['BIN_COUNT'];
      for($i=0,$y=$lo,$n=$this->_opts['BIN_COUNT']; $i<$n; $i++,$y+=$dy) {
        if( $this->_opts['BIN_LEFT'] ) { 
          $ticks[$i] = $y;
        } else if( $this->_opts['BIN_RIGHT'] ) { 
          $ticks[$i] = $y+$dy;
        } else { // if( $this->_opts['BIN_CENTER']) {
          $ticks[$i] = $y+$dy/2;
        }
      } // end for in in BIN_COUNT
    }

    return $ticks;
  } // END: function m_getTicks($ys)


  function m_initOpts() { 
    $this->_opts = array(
      'BIN_COUNT' => 10,
      'BIN_SIZE' => null,
      'BIN_TICKS' => null,
      'BIN_LEFT' => false,
      'BIN_CENTER' => true,
      'BIN_RIGHT' => false
    );
    return true;
  } // END: function m_initOpts()


  function getOpt($k) { 
    $k = strtoupper(trim($k));
    if( !array_key_exists($k,$this->_opts) ) { 
    // option not supported
      error_log("option not supported: $k");
      return false;
    }
    return $this->_opts[$k];
  } // END: function getOpt($k)


  function setOpt($k,$v=null) { 
    $k = strtoupper(trim($k));
    if( !array_key_exists($k,$this->_opts) ) { 
    // option not supported
      error_log("option not supported: $k");
      return false;
    }
    switch($k) { 
      case 'BIN_COUNT':
        if( (string)intVal($v)!==(string)$v || $v <= 0) { 
          error_log("BIN_COUNT: value must be an int > 0.");
          return false;
        }
        break;
      case 'BIN_SIZE':
        if( !is_numeric($v) || $v <= 0 ) { 
          error_log("BIN_SIZE: value must be a number > 0.");
          return false;
        }
        break;
      case 'BIN_TICKS':
        if( !is_array($v) || empty($v) ) { 
          error_log("BIN_TICKS: value must be a non-empty array.");
        }
        foreach($v as $i) { 
          if( !is_numeric($i) ) { 
            error_log("BIN_TICKS: all values must be numeric.");
            return false;
          }
        }
        sort($v);
        break;
      case 'BIN_LEFT':
      case 'BIN_CENTER':
      case 'BIN_RIGHT':
        $this->_opts['BIN_LEFT'] = ($k=='BIN_LEFT') ? true : false;
        $this->_opts['BIN_CENTER'] = ($k=='BIN_CENTER') ? true : false;
        $this->_opts['BIN_RIGHT'] = ($k=='BIN_RIGHT') ? true : false;
        return true;
      default:
        error_log("Unrecognized option: $k");
        return false;
    }
    $this->_opts[$k] = $v;
    return true;
  } // END: function setOpt($k,$v=null)


} // END: class Histogram


// EOF -- Histogram.php
?>
