<?php
 /**
  * Discrete Wavelet Transform (Using Haar Transform)
  * @link http://www.cs.ucf.edu/~mali/haar/
  * @link https://github.com/gitpan/Math-Wavelet-Haar/blob/master/lib/Math/Wavelet/Haar.pm
  *
  * @file DWT.php
  * @date 2014-05-13 12:57 PDT
  * @author Paul Reuter
  * @version 1.0.1
  *
  * @modifications <pre>
  * 1.0.0 - 2014-05-13 - Created from template: phpclass
  * 1.0.1 - 2014-05-13 - Add: header block links.
  * </pre>
  */



/**
 * Discrete Wavelet Transform (Using Haar Transform)
 * @package Math
 */
class DWT {
  public static $isq = 0.70710678118; //  2^(-0.5) = 1/sqrt(2.0);

  /**
   * Discrete Wavelet Transform (Using Haar Transform)
   *
   * @public
   * @return new DWT object
   */
  function DWT() { 
  } // END: constructor DWT


  function forward($in) { 
    $in = self::pad_2($in);
    $out = array_fill(0,count($in),0);
    $w = count($in);
    $isq = self::$isq;
    while( $w > 1 ) { 
      $w >>= 1;
      for($i=0; $i<$w; $i++) { 
        $h = $i<<1;
        $out[$i] = $isq*($in[$h] + $in[$h+1]);
        $out[$w+$i] = $isq*($in[$h] - $in[$h+1]);
      }
      for($i=0,$ww=$w<<1; $i<$ww; $i++) { 
        $in[$i] = $out[$i];
      }
    } 
    return $out;
  } // END: function forward($in)


  function reverse($in) { 
    $in = self::pad_2($in);
    $out = array_fill(0,count($in),0);
    $w = count($in);

    $isq = self::$isq;
    for($len=1; $len<=$w>>1; $len<<=1) { 
      for($i=0; $i<$len; $i++) { 
        $out[$i<<1] = $isq*($in[$i] + $in[$i+$len]);
        $out[($i<<1)+1] = $isq*($in[$i] - $in[$i+$len]);
      }
      for($i=0,$ww=$len<<1; $i<$ww; $i++) { 
        $in[$i] = round($out[$i],9);
      }
    }
    return $in;
  } // END: function reverse($in)


  function pad_2($in,$val=0) { 
    $leni = count($in);
    if( $leni<1 ) { 
      return $in;
    }
    $leno = pow(2,ceil(log($leni)/log(2)));
    if( $leni<$leno ) { 
      // Zero-pad.
      array_splice($in,$leni,$leno-$leni,array_fill(0,$leno-$leni,$val));
    }
    return $in;
  } // END: function pad_2($in,$val=0)


} // END: class DWT


// EOF -- DWT.php
?>
