<?php
/**
 * @file MultipleRegression.php
 * @date 2012-11-06 16:54 PDT
 * @author Paul Reuter
 * @version 1.1.1
 *
 * @modifications <pre>
 * 1.0.0 - 2011-06-10 - Created
 * 1.1.0 - 2011-08-25 - API Change, FromColumns more similar to FromXY
 * 1.1.1 - 2012-11-06 - BugFix: transpose of vector is matrix column
 * </pre>
 */


/**
 * Compute parameters for multiple linear regression.
 * @package Math
 */
class MultipleRegression {

  /**
   * @protected
   */
  var $X;  // Array of equation tuples. M rows of N column parameters.
  var $Y;  // Array of M equation result rows.

  /**
   * @private
   */
  var $a = 0;       // Y-intercept
  var $b = array(); // beta vector


  function MultipleRegression() {
    return $this;
  }


  /**
   * Create object from matrix.
   * Each row represents an equation tuple and the Y-value is on the right.
   * Rows of: [ x1, x2, ..., xn, y ]
   *
   * @return new object
   * @type MultipleRegression
   */
  function FromMatrix($X) { 
    $that = new MultipleRegression();
    $X = $that->cols($X);
    $that->Y = array_pop($X);
    $that->X = $that->transpose($X);
    return $that;
  } // END: function FromMatrix($X)


  /**
   * Create object from X matrix, Y vector
   * Each row represents an equation tuple and the Y-value is on the right.
   * Rows of: [ x1, x2, ..., xn, y ]
   *
   * @return new object
   * @type MultipleRegression
   */
  function FromXY($X,$Y) {
    $that = new MultipleRegression();
    if( !is_array($X) || !is_array(current($X)) ) { 
      error_log("Expecting X to be a matrix.");
      return false;
    }
    $that->X = $X;   // Input matrix X; M equation sets of N column parameters
    $that->Y = $Y;   // Input array of M equation results
    return $that;
  } // END: function FromXY($X,$Y)


  function FromColumns() {
    $that = new MultipleRegression();
    $args = func_get_args();
    $that->Y = array_pop($args);
    return $that;
  } // END: function FromColumns(X0,X1,X2...Y)


  function evaluate() { 
    if( !empty($this->b) || isset($this->error) ) { 
      return true;
    }

    // Augment the X parameters matrix with 1's in front.
    $Xt = $this->cols($this->X);
    array_unshift($Xt,array_fill(0,count($this->Y),1));
    $X = $this->transpose($Xt);

    $Y = $this->transpose(array($this->Y));
    $b = $this->col(
      $this->mul(
        $this->mul(
          $this->inv($this->mul($Xt,$X)),
          $Xt
        ),
        $Y
      )
    );

    $this->a = array_shift($b);
    $this->b = $b;
    return (empty($b)) ? false : true;
  } // END: function evaluate()


  function getParameters() { 
    if( $this->evaluate() ) { 
      return array_merge(array($this->a),$this->b);
    }
    return false;
  } // END: function getParameters()


  function getEquation($fmt="%.5f") { 
    $p = $this->getParameters();
    if( empty($p) ) { 
      return "Invalid Parameters";
    }
    $txt = "y = ".sprintf($fmt,$p[0]);
    for($i=1,$n=count($p); $i<$n; $i++) {
      $sig = ($p[$i]<0) ? ' - ' : ' + ';
      $txt = $txt.$sig.sprintf($fmt."*X%d",abs($p[$i]),$i);
    }
    return $txt;
  } // END: function getEquationString($precision=5)


  function estimate() { 
    $args = func_get_args();
    $narg = count($args);
    if( $narg < 1 ) { 
      return false;
    }
    // Treat input arguments as sequence of Xi parameters
    // OR use treat first argument as array of Xi parameters
    $X = ($narg==1 && is_array(current($args))) ? current($args) : $args;

    if( !$this->evaluate() ) { 
      return false;
    }

    $y = $this->a;
    foreach($X as $i=>$v) { 
      $y += $this->b[$i] * $X[$i];
    }
    return $y;
  } // END: function estimate($X)


  /**
   * Compute the correlation coefficient between two arrays.
   * Originally named pmcc (pm? calibration coefficient)
   *
   * <pre>
   * r: the calibration coefficient, defined as:
   *      (sum of products about the mean of X and Y)
   *                  divided by
   *   sqrt( (sum of squares about X) * (sum of squares about Y) )
   * </pre>
   *
   * @access public
   * @static
   * @param array $yvals Array of Y values.
   * @param array $xvals Array of X values.
   * @return float Calibration coefficient, or false if error.
   */
  function corr($yvals,$xvals) { 
    if( !is_array($yvals) ) { 
      trigger_error("Parameter 2 must be an array.",E_USER_ERROR);
      return false;
    }
    if( !is_array($xvals) ) { 
      trigger_error("Parameter 1 must be an array.",E_USER_ERROR);
      return false;
    }
  
    $nx = count($xvals);
    $ny = count($yvals);
    if( $nx != $ny ) { 
      trigger_error("Number of elements must match.",E_USER_ERROR);
      return false;
    }
    if( $nx < 1 ) { 
      trigger_error("There must be at least some elements.",E_USER_ERROR);
      return false;
    }
  
    for($i=0; $i<$nx; $i++) { 
      if( !is_numeric($xvals[$i]) ) {
        trigger_error(
          "All values must be numeric, x[$i] = ".$xvals[$i], E_USER_ERROR
        );
        return false;
      }
      if( !is_numeric($yvals[$i]) ) { 
        trigger_error(
          "All values must be numeric, y[$i] = ".$yvals[$i], E_USER_ERROR
        );
        return false;
      }
    }
  
    $xsum = array_sum($xvals);
    $xmean = $xsum/$nx;
    $ysum = array_sum($yvals);
    $ymean = $ysum/$ny;
  
    // sum of products about the mean of X and Y
    $sopmxy = 0;
    // sum of squares about mean of X
    $sosmx = 0;
    // sum of squares about mean of Y
    $sosmy = 0;
  
    for($i=0; $i<$nx; $i++) { 
      $dx = $xvals[$i] - $xmean;
      $dy = $yvals[$i] - $ymean;
      $sopmxy += $dx * $dy;
      $sosmx  += $dx * $dx;
      $sosmy  += $dy * $dy;
    }
  
    // r: the calibration coefficient, defined as:
    //   (sum of products about the mean of X and Y)
    //               divided by
    //   sqrt( (sum of squares about X) * (sum of squares about Y) )
  
    $den = sqrt($sosmx * $sosmy);
    return ($den>0.000002||$den<-0.000002) ? $sopmxy / $den : null;
  } // END: function corr($y,$x)


  /**
   * Assumes $A is a square matrix.
   * Extremely inefficient use of recursion.  
   * Most quadrants are repeated calculated.
   */
  function det($A) { 
    $n = count($A);
    if( $n == 1 ) { 
      return $A[0][0];
    }
    if( $n == 2 ) { 
      return $A[0][0]*$A[1][1] - $A[0][1]*$A[1][0];
    }
    $det = 0;
    $sig = 1;
    for($a=0; $a<$n; $a++) { 
      $c = $A[0][$a];

      // Build minor matrix
      $B = array();
      for($i=1; $i<$n; $i++) { 
        $row = array();
        for($j=0; $j<$n; $j++) { 
          if( $j !== $a ) { 
            $row[] = $A[$i][$j];
          }
        }
        $B[] = $row;
      }
      $det += $sig * $c * $this->det($B);
      $sig *= -1;
    }
    return $det;
  } // END: function det($A)


  function inv($A) { 
    $nr = count($A);
    $nc = count(current($A));

    // Only square matricies have inverses.
    // Only matricies with determinints != 0 have inverses.
    // My alg to compute the det is N! recurisve (very bad!!)
    if( $nr != $nc ) { // || $this->det($A) == 0) { 
      $this->error = "Matrix must be square.";
      return false;
    }

    $B = array();
    $tmp = array_fill(0,$nc,0);
    foreach(array_values($A) as $r=>$rv) { 
      $B[$r] = array_merge($rv,$tmp);
      $B[$r][$r+$nc] = 1;
    }
    
    // Gaussian elimination to get reduced row-echelon form
    $B = $this->reduce($B);
    if( $B == 0 ) { 
      $this->error = "Matrix does not have an inverse.";
      return false;
    }

    // Extract right half of the resultant matrix.
    foreach(array_values($B) as $r=>$rv) { 
      $B[$r] = array_slice($rv,$nc,$nc);
    }
    return $B;
  } // END: function inv($A)


  /**
   * Perform the cross-product on two matricies. 
   * In a cross product, A x B, where A is (m by n) and B is (n by p), 
   *   the result is (m by p).
   *
   * @param matrix $A A matrix to multiply of dims (m rows by n cols).
   * @param matrix $B A matrix to multiply of dims (n rows by p cols).
   * @return matrix The result of the cross product of A x B of 
   *  dims (m rows by p cols).
   */
  function mul($A,$B) { 
    if( !is_array($A) || !is_array($B) ) { 
      error_log("mul: Parameters must be matricies");
      return false;
    }
    $n = count($A);
    if( $n < 1 ) {
      error_log("Empty input matrix A");
      return false;
    }
    $m = count($B);
    if( $m !== count(current($A)) ) { 
      error_log("Rows in B != cols in A");
      return false;
    }
    $p = count(current($B));
    $C = array_fill(0,$n,array_fill(0,$p,0));
    for($i=0;$i<$n;$i++) { 
      for($j=0;$j<$p;$j++) { 
        for($k=0;$k<$m;$k++) { 
          $C[$i][$j] += $A[$i][$k] * $B[$k][$j];
        }
      }
    }
    return $C;
  } // END: function mul($A,$B)


  function reduce($A) { 
    $n = count($A);
    $m = count( current($A) );
    for($i=0;$i<$n;$i++) {
      // Find first non-zero element.
      $r = $i;

      // if for all r >= i, a[r][i]==0, then conclude inverse does not exist.
      $isZero = true;
      for(; $r<$n; $r++) { 
        if( !$this->isZero($A[$r][$i]) ) { 
          $isZero = false;
          break;
        }
      }
      if( $isZero ) {
        // Matrix inverse does not exist.
        return 0;
      }
      
      // Swap empty row with non-empty row
      if( $r > $i ) { 
        list($A[$r],$A[$i]) = array($A[$i],$A[$r]);
      }
      
      // Now we agree that a[i][i] is non-zero.
      $coeff = $A[$i][$i];
      for($j=$i;$j<$m;$j++) { 
        $A[$i][$j] /= $coeff;
      }
      
      // Subtract leading coefficient from row to set coefficient to zero.
      for($r=$i+1; $r<$n; $r++) { 
        $factor = $A[$r][$i];
        for($j=0; $j<$m; $j++) {
          $A[$r][$j] -= $factor*$A[$i][$j];
        }
      }
    }

    // Solve
    for($elim=$n-1; $elim>=1; $elim--) { 
      for($row=$elim-1; $row>=0; $row--) { 
        $coeff = $A[$row][$elim];
        for($col=0;$col<$m;$col++) { 
          $A[$row][$col] -= $coeff*$A[$elim][$col];
        }
      }
    }
    return $A;
  } // END: function reduce($A)


  function row($A,$i=0) { 
    return $A[$i];
  } // END: function row($A,$i)

  function rows($A) { 
    return $A;
  }

  function col($A,$i=0) { 
    $v = array();
    foreach($A as $r) { 
      $v[] = $r[$i];
    }
    return $v;
  } // END: function col($A,$i)


  function cols($A) { 
    return $this->transpose($A);
  } // END: function cols($A)


  function transpose($A) {
    $B = array();
    if( !is_array(current($A)) ) { 
      $A = $this->v2m($A);
    }
    foreach($A as $r=>$vr) { 
      foreach($vr as $c=>$vc) { 
        $B[$c] = array();
      }
      break;
    }
    foreach($A as $r=>$vr) { 
      foreach($vr as $c=>$vc) { 
        $B[$c][$r] = $vc;
      }
    }
    return $B;
  } // END: function transpose($A)


  /**
   * Convert a vector to matrix representation.
   */
  function v2m($v) { 
    $m = array();
    foreach( array_keys($v) as $i ) { 
      $m[$i] = array($v[$i]);
    }
    return $m;
  } // END: function v2m($v)


  /**
   * Convert a matrix to vector representation.
   */
  function m2v($m) { 
    $v = array();
    foreach( $m as $row ) { 
      if( is_array($row) ) { 
        $v = array_merge($v,$this->m2v($row));
      } else { 
        $v[] = $row;
      }
    }
    return $v;
  } // END: function m2v($m)


  /**
   * Floating point computation of "zero" to avoid round-off errors.
   *
   * @param float $v A value to test against.
   * @return bool true if -0.000005 < $v < 0.000005; false otherwise.
   */
  function isZero($v) { 
    return (-0.000005 < $v && $v < 0.000005);
  } // END: function isZero($v)


  /**
   * Greatest common divisor
   */
  function gcd($a,$b) { 
    if( $a==0 || $b==0 ) { 
      return 1;
    }
    $a = abs($a);
    $b = abs($b);
    if( $a<$b ) { 
      list($a,$b) = array($b,$a);
    }
    $r = 1;
    while( ($r=$a%$b) != 0 ) { 
      $a = $b;
      $b = $r;
    }
    return $b;
  } // END: function gcd($a,$b)


  /**
   * Least common multiple
   */
  function lcm($a,$b) { 
    return $a*$b / $this->gcd($a,$b);
  } // END: function lcm($a,$b)


} // END: class MultipleRegression



/*
$X1 = array(6,7,8,9,7);
$X2 = array(5,6,6,7,8);
$Y = array(1,2,3,3,4);

$mlr = MultipleRegression::FromColumns($Y,$X1,$X2);
echo( $mlr->getEquation("%.3f") . "\n" );
*/


// EOF -- MultipleRegression.php
?>
