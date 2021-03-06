<?php
 /**
  * Another implementation of Array methods.
  *
  * @file A.php
  * @package Core
  * @date 2016-03-23 19:01 PDT
  * @author Paul Reuter
  * @version 1.3.7
  *
  * @modifications <pre>
  * 1.0.0 - 2012-08-30 - Created from template: phpclass
  * 1.0.1 - 2012-09-17 - Add: zip(a,b,...) BugFix: inner loop key collision.
  * 1.0.2 - 2012-10-22 - Modify: Whitespace for best practices
  * 1.0.3 - 2012-11-24 - Add: gt, gte, lt, lte, eq, neq methods.
  * 1.0.4 - 2012-11-30 - BugFix: parameter b check for isArray
  * 1.0.5 - 2012-12-05 - Add: ninterp, rename interp to linterp.
  * 1.0.6 - 2012-12-14 - BugFix: boundary condition movacc.
  * 1.0.7 - 2012-12-21 - Modify: scanmin/scanmax return (x,y) tuple
  * 1.0.8 - 2013-02-26 - BugFix: movavg division was incorrect.
  * 1.0.9 - 2013-04-16 - BugFix: movcor($y1,$y2,$n) updated.
  * 1.1.0 - 2013-05-21 - Add: map, scanmin/max fillvalue check
  * 1.1.1 - 2013-05-21 - BugFix: bsearch[LR] hi/lo vars undefined.
  * 1.1.2 - 2013-06-07 - BugFix: initialization of gt/lt/eq methods.
  * 1.1.3 - 2013-09-20 - BugFix: lingrid boundary sensitivity.
  * 1.1.4 - 2013-10-15 - Add: init(dimsz,val)
  * 1.1.5 - 2013-10-15 - Add: mkrank(a,b,c,d,...,wi?)
  * 1.1.6 - 2013-10-15 - Add: logspace(10^lo,10^hi,n=50)
  * 1.1.7 - 2014-04-18 - Add: size(&$a)
  * 1.1.8 - 2014-05-07 - Add: filter(a,b): return push a[i] if b[i] forall i
  * 1.1.9 - 2014-05-07 - BugFix: filter(a,b): wasn't reducing array size.
  * 1.2.0 - 2014-09-17 - BugFix: movmin/max boundary counditions.
  * 1.2.1 - 2014-09-19 - BugFix: movchg n=min(count(a), n)
  * 1.2.2 - 2014-10-02 - Tweak: Whitespace and formatting.
  * 1.2.3 - 2014-09-28 - BugFix: boundary condition: scanmin/max
  * 1.2.4 - 2014-10-14 - BugFix: ninterp bad values.
  * 1.3.0 - 2014-10-17 - Modify: (bug) movperf: negchg/negval is now neeg.
  * 1.3.1 - 2015-02-15 - Add: Transpose. Because it happens too frequently.
  * 1.3.2 - 2015-02-17 - BugFix: Transpose array w/ 1 row should return rows.
  * 1.3.3 - 2015-03-27 - BugFix: kz undefined.
  * 1.3.4 - 2015-07-22 - Add: fillna(v,nan=null)
  * 1.3.5 - 2016-02-22 - Add: replace(a, $find, $repl=null)
  * 1.3.6 - 2016-03-23 - Add: mslice(&$mat, $cixs)
  * 1.3.7 - 2016-03-23 - Add: col(&$mat, $ix), cols(&$mat, $ix1, $ix2...)
  * </pre>
  */



/**
 * @package Core
 */
class A {
  public static $fillvalue = null;
  public static $epsilon = 0.0000001;

  /**
   * @public
   * @return new  object
   */
  function A() { 
    return $this;
  } // END: constructor 


  /**
   * Compute the dimension of parameter $a
   *
   * @param mixed &$a An array or other.
   * @return array of dimensions. eg. array(3,2) or array().
   */
  function size(&$a) { 
    if( is_array($a) ) { 
      $d = self::size(current($a));
      array_unshift($d,count($a));
      return $d;
    }
    return array();
  } // END: function size(&$a)


  /**
   * Compute the min on an array subset. More optimal than subsetting.
   *
   * @see scanminx
   * @see scanminy
   * @param array &$a An input array.
   * @param int $i The starting index.
   * @param int $n The number of values to observe.
   * @param bool $lr If true, preserves first instance; else last instance.
   * @return array (index,value) The index and value of the min in scan-space.
   */
  function scanmin(&$a,$i=0,$n=null,$lr=true) { 
    while( $i<0 ) { $i+= count($a); }
    $n = ($n===null) ? count($a)-$i : min($n,count($a)-$i);
    $loi = $i;
    $lo = $a[$loi];
    for ($j=1; $j<$n; $j++) {
      $v = $a[$i+$j];
      if( $lo === self::$fillvalue || $v<$lo || (!$lr && $v==$lo) ) { 
        $loi = $i+$j;
        $lo = $v;
      }
    }
    return array($loi,$lo);
  } // END: function scanmin(&$a,$i=0,$n=null,$lr=true)


  /**
   * Compute the max on an array subset. More optimal than subsetting.
   *
   * @see scanmaxx
   * @see scanmaxy
   * @param array &$a An input array.
   * @param int $i The starting index.
   * @param int $n The number of values to observe.
   * @param bool $lr If true, preserves first instance; else last instance.
   * @return array (index,value) The index and value of the max in scan-space.
   */
  function scanmax(&$a,$i=0,$n=null,$lr=true) { 
    while( $i<0 ) { $i+= count($a); }
    $i = min(count($a)-1, $i);
    $n = ($n===null) ? count($a)-$i : min($n,count($a)-$i);
    $hii = $i;
    $hi = $a[$hii];
    for ($j=1; $j<$n; $j++) {
      $v = $a[$i+$j];
      if( $hi === self::$fillvalue || $v>$hi || (!$lr && $v==$hi) ) { 
        $hii = $i+$j;
        $hi = $v;
      }
    }
    return array($hii,$hi);
  } // END: function scanmax(&$a,$i=0,$n=null,$lr=true)


  /**
   * Compute change between two periods, separated by $n periods.
   *
   * @param array $a Array of numbers.
   * @param uint $n Number of periods between change comparisons.
   * @return array out[i] := a[i]-a[i-n]
   */
  function movchg($a,$n=1) { 
    $a = array_values($a);
    $n = max(1,$n);

    $b = array();
    for ($i=0,$m=min(count($a),$n); $i<$m; $i++) {
      $b[] = $a[$i]-$a[0];
    }
    for ($i=$n,$m=count($a); $i<$m; $i++) {
      $b[] = $a[$i]-$a[$i-$n];
    }
    return $b;
  } // END: function movchg($a,$n=1)


  /**
   * Compute performance of $a over $n periods.
   * If change is negative, sign is negative; else positive.
   *
   * @param array $a array of numbers.
   * @param uint $n Number of periods between performance review.
   * @return array b[i] = (a[i]/a[i-n] - 1) * 100
   */
  function movperf($a, $n=1) {
    return self::mul(
      self::div(
        self::movchg($a, $n),
        array_map('abs',self::rshift($a, current($a), $n))
      ),
      100
    );
  } // END: function movperf($a, $n=1)


  /**
   * Accumulate past $n values.
   * @param array $a array of numbers.
   * @param uint $n Number of periods to accumulate over.
   * @return array b[i] = sum(a[i-j+1] | j=1..n)
   */
  function movacc($a,$n=1) { 
    $a = array_values($a);
    $n = max(1,$n);

    $b = array();
    $sum = 0;
    for ($i=0,$m=min(count($a),$n); $i<$m; $i++) {
      $sum += $a[$i];
      $b[] = $sum;
    }
    for ($i=$n,$m=count($a); $i<$m; $i++) {
      $sum = $sum - $a[$i-$n] + $a[$i];
      $b[] = $sum;
    }
    return $b;
  } // END: function movacc($a,$n=1)


  /**
   * Simple moving average.
   *
   * @param array $a array of numbers.
   * @param uint $n Number of periods to calculate over.
   * @return array mean at a[i] = mean(slice(a,i-n,n))
   */
  function movavg($a,$n=1) { 
    $n = max(1,$n);

    $len = count($a);
    $den = array_fill(0,$len,$n);
    array_splice($den,0,$n,range(1,min($len,$n)));
    return self::div(self::movacc($a,$n),$den);
  } // END: function movavg($a,$n=1)


  /**
   * Moving standard deviation calculation
   *
   * @param array $a array of numbers.
   * @param uint $n Number of periods to calculate over.
   * @return array stddev at a[i] = stddev(slice(a,i-n,n))
   */
  function movstd($a,$n=1) { 
    return array_map('sqrt',self::movvar($a,$n));
  } // END: function movstd($a,$n=1)


  /**
   * Moving variance calculation
   *
   * @param array $a array of numbers.
   * @param uint $n Number of periods to calculate over.
   * @return array variance at a[i] = variance(slice(a,i-n,n))
   */
  function movvar($a,$n=1) { 
    $a = array_values($a);
    $b = self::movavg($a,$n);
    for ($i=0,$na=count($a); $i<$na; $i++) {
      $sos = 0; // sum of squares
      for ($j=max(0,$i-$n); $j<=$i; $j++) {
        $sos += ($a[$j]-$b[$i]) * ($a[$j]-$b[$i]);
      }
      $b[$i] = $sos/($n-1);
    }
    return $b;
  } // END: function movvar($a,$n=1)


  /**
   * Moving Covariance
   *
   * The Moving Covariance calculates the covariance of two data series over 
   * the last n periods. 
   *
   * @see movcor
   *
   * @param float[] $y1 Series A's prices
   * @param float[] $y2 Series B's prices
   * @param uint $n Number of periods to examine
   * @return float[] array of moving covariance
   */
  function movcov($y1,$y2,$n=1) { 
    // COV = SUM{ (X-X')*(Y-Y') } / N
    return self::movavg(
      self::mul(
        self::sub($y1,self::movavg($y1,$n)),
        self::sub($y2,self::movavg($y2,$n))
      ),$n);
  } // END: function movcov($y1,$y2,$n=1)


  /**
   * Moving Correlation Coefficient
   *
   * The Moving Correlation Coefficient calculates a correlation coefficient
   * oftwo data series over the last n periods. This statisical calculation
   * is used to determine if two series of numbers are related. The closer
   * the value is to 1, the closer the data is related.
   *
   * @see movcov
   *
   * @param float[] $y1 Series A's values
   * @param float[] $y2 Series B's prices
   * @param uint $n Number of periods to examine
   * @return float[] array of moving correlation coefficients
   */
  function movcor($y1,$y2,$n=1) { 
    // COV = SUM{ (X-X')*(Y-Y') } / N
    // COR = COV / (sigX * sigY)
    $result = array();

    $xsum = $xmean = 0;
    $ysum = $ymean = 0;

    $sopmxy = 0; // sum of products about mean of X & Y
    $sosmx = $sosmy = 0; // sum of squares about mean of X , Y

    $buf_xy = array();
    $buf_mx = array();
    $buf_my = array();
    for($i=0,$ni=count($y1); $i<$ni; $i++) { 
      if( $i>=$n ) { 
        $xsum -= $y1[$i-$n];
        $ysum -= $y2[$i-$n];
        $sopmxy -= $buf_xy[$i-$n];
        $sosmx -= $buf_mx[$i-$n];
        $sosmy -= $buf_my[$i-$n];
      }
      $xsum += $y1[$i];
      $xmean = $xsum/min($n,$i+1);
      $dx = $y1[$i] - $xmean;
      $ysum += $y2[$i];
      $ymean = $ysum/min($n,$i+1);
      $dy = $y2[$i] - $ymean;
      $buf_xy[$i] = $dx*$dy;
      $buf_mx[$i] = $dx*$dx;
      $buf_my[$i] = $dy*$dy;
      $sopmxy += $buf_xy[$i];
      $sosmx += $buf_mx[$i];
      $sosmy += $buf_my[$i];

      $den = sqrt($sosmx * $sosmy);
      $result[$i] = ($den>0.000002||$den<-0.000002) ? $sopmxy/$den : null;
    }
    $result[0] = 0;
    return $result;
  } // END: function movcor($a,$n=1)


  /**
   * Simple moving minimum value. out[i] = min(slice(a,i-n,n))
   * @param array $a array of numbers.
   * @param uint $n number of periods to examine.
   * @return array of moving min values.
   */
  function movmin($a,$n=1) { 
    $a = array_values($a);
    $n = max(1,$n);
    if( $n<2 ) {
      return $a;
    }
    $b = array();

    $min = current($a); // Min value
    $mix = 0;           // Min index
    for ($i=0,$m=count($a); $i<$m; $i++) {
      if ($a[$i] <= $min) {
      // Check if we encounter a new lower low
        $mix = $i;
        $min = $a[$i];
      } else if ($mix+$n <= $i) {
      // Check if the old low is expired, and if so: 
      // Find the new lowest low within allowed range.
        // Starting from current, looking back, find next lowest
        $mix = $i;
        $min = $a[$i];
        for ($j=$i-1,$k=max(0,$i-$n+1); $j>=$k; $j--) {
          if ($a[$j] < $min) {
            $mix = $j;
            $min = $a[$j];
          }
        }
      } // else { use previous min }
      $b[] = $min;
    }
    return $b;
  } // END: function movmin($a,$n=1)


  /**
   * Simple moving maximum value. out[i] = max(slice(a,i-n,n))
   * @param array $a array of numbers.
   * @param uint $n number of periods to examine.
   * @return array of moving max values.
   */
  function movmax($a,$n=1) { 
    $a = array_values($a);
    $n = max(1,$n);
    if( $n<2 ) {
      return $a;
    }
    $b = array();

    $max = current($a); // Max value
    $mix = 0;           // Max index
    for ($i=0,$m=count($a); $i<$m; $i++) {
      if ($a[$i] >= $max) {
      // Check if we encounter a new higher high
        $mix = $i;
        $max = $a[$i];
      } else if ($mix+$n <= $i) {
      // Check if the old high is expired, and if so: 
      // Find the new highest high within allowed range.
        // Starting from current, looking back, find next highest
        $mix = $i;
        $max = $a[$i];
        for ($j=$i-1,$k=max(0,$i-$n+1); $j>=$k; $j--) {
          if ($a[$j] > $max) {
            $mix = $j;
            $max = $a[$j];
          }
        }
      } // else { use previous max }
      $b[] = $max;
    }
    return $b;
  } // END: function movmax($a,$n=1)


  function movsma($a,$n=1) { 
    return self::movavg($a,$n);
  } // END: function movsma($a,$n=1)


  /**
   * Computes the exponential moving average for the array.  Initialization
   * is done with SMA.  Average is computing using the $n trailing values.
   * Initialization is done using the first input value as S_0.
   *
   * @public
   * @param array $a An array of numbers.
   * @param int $n The number of time periods to average over.
   * @return array An array containing the ema values corresponding to the
   *   input array and $n-1 prior values (totaling $n).
   */
  function movema($a,$n=1) { 
    $a = array_values($a);
    $n = max(1,$n);

    $alpha = 2/($n+1);
    $b = array();
    $sum = 0;
    $prev = 0;
    for ($i=0,$ni=min($n,count($a)); $i<$ni; $i++) { 
      $sum += $a[$i];
      $prev = $sum/($i+1);
      $b[$i] = $prev;
    }
    for ($i=$n,$ni=count($a); $i<$ni; $i++) { 
      $prev = ($a[$i]-$prev)*$alpha + $prev;
      $b[$i] = $prev;
    }
    return $b;
  } // END: function movema($a,$n=1)


  /**
   * Remove $len values from beginning of an array $a.
   *
   * @link http://ir2.php.net/manual/en/function.array-shift.php
   * @param array $a Array to remove from
   * @param uint $len Number of values to remove.
   * @return array An augmented array.
   */
  function shift($a,$len=1) { 
    array_splice($a,0,$len);
    return $a;
  } // END: function shift($a,$len)


  /**
   * prepend a value $val to an array $a, repeat $len times.
   *
   * @link http://ir2.php.net/manual/en/function.array-unshift.php
   * @param array $a Array to prepend to
   * @param mixed $val A value to prepend
   * @param uint $len Number of times to prepend value.
   * @return array An augmented array.
   */
  function unshift($a,$val=null,$len=1) { 
    array_splice($a,0,0,array_fill(0,$len,$val));
    return $a;
  } // END: function unshift($a,$val=null,$len=1)


  /**
   * Remove $len values from end of an array $a.
   *
   * @link http://ir2.php.net/manual/en/function.array-pop.php
   * @param array $a Array to remove from
   * @param uint $len Number of values to remove.
   * @return array An augmented array.
   */
  function pop($a,$len=1) { 
    array_splice($a,count($a)-$len,$len);
    return $a;
  } // END: function pop($a,$len=1)


  /**
   * Append a value $val to an array $a, repeat $len times.
   *
   * @link http://ir2.php.net/manual/en/function.array-push.php
   * @param array $a Array to append to
   * @param mixed $val A value to append
   * @param uint $len Number of times to append value.
   * @return array An augmented array.
   */
  function push($a,$val=null,$len=1) { 
    array_splice($a,count($a),0,array_fill(0,$len,$val));
    return $a;
  } // END: function push($a,$val=null,$len=1)


  /**
   * Move all elements left, empty space to the right is filled by $val.
   *
   * @param array $a An array of values
   * @param mixed $val A fill value (pad value).
   * @param uint $len Number of elements to move by.
   * @return array a shift/rotated array filled in by $val
   */
  function lshift($a,$val=null,$len=1) { 
    array_splice($a, 0, $len, array_fill(0,$len, $val));
    return self::rot($a, -$len);
  } // END: function lshift($a, $val=null, $len=1)


  /**
   * Move all elements right, empty space to the left is filled by $val.
   *
   * @param array $a An array of values
   * @param mixed $val A fill value (pad value).
   * @param uint $len Number of elements to move by.
   * @return array a shift/rotated array filled in by $val
   */
  function rshift($a,$val=null,$len=1) { 
    array_splice($a, count($a)-$len, $len, array_fill(0, $len, $val));
    return self::rot($a, $len);
  } // END: function rshift($a, $val=null, $len=1)



  /**
   * Rotate an array's elements.
   * if $lendir>0, move elements to the right by $lendir. 
   * if $lendir<0, move elements to the left by abs($lendir).
   *
   * @param array $a Input array
   * @param int $lendir Number of elements and direction to rotate.
   * @return array Rotated array of elements from $a.
   */
  function rot($a,$lendir=1) { 
    if (empty($a)) { 
      return $a;
    }
    // if move elements to the left
    if ($lendir < 0) { 
      $lendir = abs($lendir)%count($a);
      $tmp = array_splice($a, 0, $lendir);
      array_splice($a, count($a), 0, $tmp);
      return $a;
    } 
    // else move elements to the right
    $lendir = $lendir%count($a);
    $tmp = array_splice($a, count($a)-$lendir, $lendir);
    array_splice($a, 0, 0, $tmp);
    return $a;
  } // END: function rot($a, $lendir=+1)


  /**
   * Extract one column from a matrix of rows by columns.
   *
   * @param array $mat matrix. rows by cols.
   * @param uint $cixs Column index.
   * @return array Contents of one column.
   *
   * @see cols
   * @see mslice
   */
  function col(&$mat, $ix) {
    return current(A::cols($mat,$ix));
  } // END: function col(&$mat, $ix)


  /**
   * Extract one or more columns from a matrix of rows by columns.
   *
   * @param array $mat matrix. rows by cols.
   * @param mixed|uint $cixs Column index or array of column indexes.
   * @return array of column arrays.
   *
   * @see col
   * @see mslice
   */
  function cols(&$mat, $cixs /*, $cix2, ... */) {
    if( !is_array($cixs) ) {
      $cixs = array($cixs);
      for($i=2,$n=func_num_args(); $i<$n; $i++) {
        $cixs[] = func_get_arg($i);
      }
    }
    $sub = array();
    foreach($cixs as $ix) {
      $tmp = array();
      foreach($mat as $row) {
        $tmp[] = (isset($row[$ix])) ? $row[$ix] : null;
      }
      $sub[] = $tmp;
    }
    return $sub;
  } // END: function cols(&$mat, $cixs /*, $cix2, ... */)


  /**
   * Extract a matrix subset from a larger matrix.
   *
   * @param array $mat parent matrix.
   * @param mixed|uint $cixs Column index or array of column indexes.
   * @param uint $cix2 (optional) Another column index. (as needed).
   * @return array Matrix subset containing rows of $cix, $cix2... cols.
   *
   * @see col
   * @see cols
   */
  function mslice(&$mat, $cixs /*, $cix2... */) {
    if( !is_array($cixs) ) {
      $cixs = array($cixs);
      for($i=2,$n=func_num_args(); $i<$n; $i++) {
        $cixs[] = func_get_arg($i);
      }
    }
    $sub = array();
    foreach(array_keys($mat) as $r) {
      $row = array();
      foreach($cixs as $c) {
        $row[] = (isset($mat[$r][$c])) ? $mat[$r][$c] : null;
      }
      $sub[] = $row;
    }
    return $sub;
  } // END: function mslice(&$mat, $cix /*, $cix2... */)


  /**
   * Return index of cell that matches or preceeds $needle
   *
   * @param array &$haystack An array of items to compare to.
   * @param mixed $needle Something to look for (<> comparable)
   * @param int $lo Internal min index to comapre.
   * @param int $hi Internal max index to comapre.
   * @return int The index of the item <= $needle; -1 if item[0] > needle
   */
  function bsearch(&$haystack, $needle, $lo=0, $hi=null) {
    if ($hi===null) {
      if (empty($haystack) || $needle < $haystack[0]) {
      // initial execution: check if left edge too high.
        return -1;
      }
      $hi=count($haystack)-1;
    }
    if ($lo >= $hi) {
      return $lo;
    }
    $mid = ($lo+$hi+1)>>1;
    if ($needle < $haystack[$mid]) {
      return self::bsearch($haystack, $needle, $lo, $mid-1);
    }
    if ($needle > $haystack[$mid]) {
      return self::bsearch($haystack, $needle, $mid, $hi);
    }
    return $mid;
  } // END: function bsearch(&$haystack, $needle, $i=0, $n=null)


  /**
   * Return left-most index of a cell that matches or preceeds $needle.
   * Note that this turns bsearch into an O(n) worst-case search.
   *
   * @see bsearch
   * @return int The left-most index of $haystack matching $needle.
   */
  function bsearchL(&$haystack, $needle, $lo=0, $hi=null) {
    $ix = self::bsearch($haystack, $needle, $lo, $hi);
    while ($ix > 0 && $haystack[$ix-1]===$needle) {
      $ix -= 1;
    }
    return $ix;
  } // END: function bsearchL(&$haystack, $needle, $i=0, $n=null)


  /**
   * Return right-most index of a cell that matches or preceeds $needle.
   * Note that this turns bsearch into an O(n) worst-case search.
   *
   * @see bsearch
   * @return int The right-most index of $haystack matching $needle.
   */
  function bsearchR(&$haystack, $needle, $lo=0, $hi=null) {
    $ix = self::bsearch($haystack, $needle, $lo, $hi);
    $nk = count($haystack) - 1;
    while ($ix<$nk && $haystack[$ix+1]===$needle) {
      $ix += 1;
    }
    return $ix;
  } // END: function bsearchR(&$haystack, $needle, $i=0, $n=null)


  /**
   * Interpolate the values in $x0,$y0 to the x values of $xa.
   *
   * @param array $x0 An array of x coordinates
   * @param array $y0 An array of y values
   * @param array $xa The x coordinates to return interp values for.
   * @param bool $extrap Whether to extrapolate or copy edge cases.
   * @return array An array of $ya: the corresponding values to $xa.
   */
  function linterp($x0, $y0, $xa, $extrap=true) {
    $mx = 0;
    $nx = count($x0);

    if ($nx < 1) {
      // no input data? fill result with null
      return array_fill(0, count($xa), null);
    }

    if ($nx==1) {
      // 1 input point? return array of length($xa) all equal to $y0[0]
      return array_fill(0, count($xa), $y0[0]);
    }

    $ya = array();
    for ($i=0,$n=count($xa); $i<$n; $i++) {
      // Make mx point to first value > xa[i]
      while ($mx < $nx && $x0[$mx] <= $xa[$i]) {
        $mx += 1;
      }

      if ($mx == 0) {
      // if first value in x0 is > xa[i]

        if ($extrap) {
          // Strategy: extrapolate
          $dy = $y0[1]-$y0[0];
          $dx = $x0[1]-$x0[0];
          $da = $xa[$i]-$x0[0];
          if ($dx > 0.000005 || $dx < -0.000005) {
            $ya[] = $y0[0] + $da * $dy/$dx;
          } else {
            $ya[] = $y0[0];
          }
        } else {
          // Strategy: carry forward
          $ya[] = $y0[0];
        }

      } else if ($mx == $nx) {
      // if last value in x0 is > xa[i]

        if ($extrap) {
          // Strategy: extrapolate
          $dy = $y0[$nx-1]-$y0[$nx-2];
          $dx = $x0[$nx-1]-$x0[$nx-2];
          $da = $xa[$i]-$x0[$nx-1];
          if ($dx > 0.000005 || $dx < -0.000005) {
            $ya[] = $y0[$nx-1] + $da * $dy/$dx;
          } else {
            $ya[] = $y0[$nx-1];
          }
        } else {
          // Strategy: carry forward
          $ya[] = $y0[$nx-1];
        }

      } else {
      // common case: use x0[i]-x0[i-1] and y0[i]-y0[i-1]
        $dy = $y0[$mx]-$y0[$mx-1];
        $dx = $x0[$mx]-$x0[$mx-1];
        $da = $xa[$i]-$x0[$mx-1];
        if ($dx > 0.000005 || $dx < -0.000005) {
          $ya[] = $y0[$mx-1] + $da * $dy/$dx;
        } else {
          $ya[] = ($i>0) ? $ya[$i-1] : null;
        }
      }
    } // end: for($i=0,$n=count($xa); $i<$n; $i++)

    return $ya;
  } // END: function linterp($x0, $y0, $xa, $extrap=true)


  /**
   * Nearest neighbor interpolate the values in $x0,$y0 to x values of $xa.
   *
   * @param array $x0 An array of x coordinates
   * @param array $y0 An array of y values
   * @param array $xa The x coordinates to return interp values for.
   * @param array $edge Neg=carry forward, Pos=carry back, zero=nearest
   * @return array An array of $ya: the corresponding values to $xa.
   */
  function ninterp($x0,$y0,$xa,$edge=0) {
    $mx = 0;
    $nx = count($x0);

    if ($nx < 1) {
      // no input data? fill result with null
      return array_fill(0, count($xa), null);
    }

    if ($nx==1) {
      // 1 input point? return array of length($xa) all equal to $y0[0]
      return array_fill(0, count($xa), $y0[0]);
    }

    $ya = array();
    for ($i=0,$n=count($xa); $i<$n; $i++) {
      // Make mx point to first value > xa[i]
      while ($mx < $nx && $x0[$mx] <= $xa[$i]) {
        $mx += 1;
      }

      if ($mx == 0) {
      // if first value in x0 is > xa[i]

        // Strategy: carry forward
        $ya[] = $y0[0];

      } else if ($mx == $nx) {
      // if last value in x0 is > xa[i]

        // Strategy: carry forward
        $ya[] = $y0[$nx-1];

      } else {
        if( $edge == 0 ) {
        // common case: use y0 value from x0 closest to target.
          $ya[] = ($x0[$mx]-$xa[$i]<$xa[$i]-$x0[$mx-1]) ? $y0[$mx]:$y0[$mx-1];
        } else if( $edge < 0 ) {
        // use left edge (carry-forward)
          $ya[] = $y0[$mx-1];
        } else {
        // use right edge (carry-back)
          $ya[] = $y0[$mx];
        }
      }
    } // end: for($i=0,$n=count($xa); $i<$n; $i++)

    return $ya;
  } // END: function ninterp($x0, $y0, $xa, $edge=0)


  /**
   * Cubic spline interpolation
   *
   * @param array $xs Array of x data
   * @param array $ys Array of y data
   * @param array $xi Array of target xi values.
   * @return array $yi Array of interpolated values for $xi x-targets.
   */
  function spline($xs, $ys, $xi) {
    $n = count($xs);
    if (count($ys) !== $n) {
      return false;
    }
    $xs = array_values($xs);
    $ys = array_values($ys);
    $y2a = self::spline_d2($xs, $ys);

    $n = count($y2a) - 1;
    $klo = 1;

    $yi = array();
    for ($i=0,$ni=count($xi); $i<$ni; $i++) {
      $khi = $n;
      while ($khi-$klo > 1) {
        $k = ($khi+$klo) >> 1;
        if ($xs[$k] > $xis[$i]) {
          $khi = $k;
        } else {
          $klo = $k;
        }
      }

      $h = $xs[$khi] - $xs[$klo];
      if ($h == 0) {
        error_log("Bad xs input to routine splint");
        return false;
      }
      $a = ($xs[$khi]-$xi[$i]) / $h;
      $b = ($xis[$i]-$xs[$klo]) / $h;
      $yi[$i] = $a*$ys[$klo] + $b*$ys[$khi] +
           (($a*$a*$a-$a)*$y2a[$klo] + ($b*$b*$b-$b)*$y2a[$khi])*($h*$h)/6.0;
    }
    return $yi;
  } // END: function spline($xs, $ys, $xi)


  /**
   * Compute second derivitive for spline function.
   *
   * @private
   * @param array $xs Safe x array from spline.
   * @param array $ys Safe y values from spline
   * @return array of second derivitives
   */
  function spline_d2($xs, $ys) {
    $n = count($xs) - 1;

    // First derivitive at first point
    $yp1 = ($ys[1]-$ys[0]) / ($xs[1]-$xs[0]);
    // First derivitive at last point
    $ypn = ($ys[$n]-$ys[$n-1]) / ($xs[$n]-$xs[$n-1]);

    $y2 = array(0);
    $u = array(0);

    if ($yp1 > 0.99e30) {
      $y2[1] = 0.0;
      $u[1] = 0.0;
    } else {
      $y2[1] = -0.5;
      $u[1] = (3.0/($xs[2]-$xs[1]))*(($ys[2]-$ys[1])/($xs[2]-$xs[1])-$yp1);
    }

    for ($i=2; $i<=$n-1; $i++) {
      $sig = ($xs[$i]-$xs[$i-1])/($xs[$i+1]-$xs[$i-1]);
      $p = $sig*$y2[$i-1] + 2.0;
      $y2[$i] = ($sig-1.0)/$p;
      $u[$i] = ($ys[$i+1]-$ys[$i])/($xs[$i+1]-$xs[$i]) -
               ($ys[$i]-$ys[$i-1])/($xs[$i]-$xs[$i-1]);
      $u[$i] = (6.0*$u[$i]/($xs[$i+1]-$xs[$i-1]) - $sig*$u[$i-1])/$p;
    }

    if ($ypn > 0.99e30) {
      $qn = 0.0;
      $un = 0.0;
    } else {
      $qn = 0.5;
      $un = (3.0/($xs[$n]-$xs[$n-1])) *
            ($ypn-($ys[$n]-$ys[$n-1])/($xs[$n]-$xs[$n-1]));
    }

    $y2[$n] = ($un - $qn*$u[$n-1]) / ($qn*$y2[$n-1] + 1.0);
    for ($k=$n-1; $k>=1; $k--) {
      $y2[$k] = $y2[$k]*$y2[$k+1]+$u[$k];
    }
    return $y2;
  } // END: function spline_d2($xs, $ys)



  /**
   * Compute a regularly spaces grid of $n records, from $lo to $hi.
   *  step = ($hi-$lo)/($n-1)
   *
   * @param number $lo Starting value
   * @param number $hi Ending value
   * @param int $n Number of items to return.
   * @return float[] array of equally spaced values from $lo to $hi.
   */
  function linspace($lo, $hi, $n=100) {
    if ($n < 1) { 
      return array();
    } else if ($n < 2) { 
      return array($lo);
    } 
    $dx = ($hi-$lo) / ($n-1);
    return self::lingrid($lo, $hi, $dx);
  } // END: function linspace($lo, $hi, $n=100)


  /**
   * Generates n points between decades 10^$lo and 10^$hi.
   *  step = ($hi-$lo)/($n-1)
   *   >> x = linspace(1,5,5)
   *      x =  1, 2, 3, 4, 5
   *   >> y = logspace(1,4,4)
   *      y = 10, 100, 1000, 10000
   *
   * @link http://www.mathworks.com/help/matlab/ref/logspace.html
   * @see linspace
   * @param number $lo Starting value
   * @param number $hi Ending value
   * @param int $n Number of items to return.
   * @return float[] array of equally spaced values from $lo to $hi.
   */
  function logspace($lo, $hi, $n=50) {
    $a = self::linspace($lo, $hi, $n);
    foreach( array_keys($a) as $i ) { 
      $a[$i] = pow(10,$a[$i]);
    }
    return $a;
  } // END: function logspace($lo, $hi, $n=50)


  /**
   * Compute range of values, equally spaced.
   *
   * @link http://ir2.php.net/manual/en/function.range.php
   * @param float $start First number in grid.
   * @param float $stop Max number in grid.
   * @param float $step distance between grid ticks
   * @return array of $step-spaced vals, val[0]=$start, max(vals) <= $stop
   */
  function lingrid($start,$stop,$step=1) { 
    $b = array();
    for ($v=$start,$tmp=$stop-A::$epsilon; $v<$tmp; $v+=$step) { 
      $b[] = $v;
    }
    if( $stop>$start ) { 
      $b[] = $stop;
    }
    return $b;
  } // END: function lingrid($start, $stop, $step=1)



  /**
   * Create a multi-dimensional array. Initialize to value, $val.
   *
   * @param int|array $dimsz dimension sizes. N or (N,M,...O,P...)
   * @param mixed $val Fill value/initialization constant.
   * @return multi-dimensional array initialized to $val.
   */
  function init($dimsz, $val) {
    if( !is_array($dimsz) ) {
      return array_fill(0, $dimsz, $val);
    }
    $a = array();
    $n = array_shift($dimsz);
    if( empty($dimsz) ) {
      return self::init($n, $val);
    }
    for($i=0; $i<$n; $i++) {
      $a[$i] = self::init($dimsz, $val);
    }
    return $a;
  } // END: function init($dimsz, $val)



  /**
   * @link http://ir2.php.net/manual/en/function.range.php
   */
  function range($start, $stop, $step=1) {
    return self::lingrid($start, $stop, $step);
  } // END: function range($start, $stop, $step=1)


  /**
   * Create a 1-dimensional array of out an array of potentially nested arrays.
   *
   * @param array $a Source array.
   * @return array A one-dimensional array containing all elements of $a.
   */
  function flatten($a) { 
    $b = array();
    if (!is_array($a)) { 
      return array($a);
    }
    foreach ($a as $i) { 
      if (is_array($i)) { 
        array_splice($b, count($b), 0, self::flatten($i));
      } else { 
        $b[] = $i;
      }
    }
    return $b;
  } // END: function flatten($a)


  /**
   * Find the value with the most amount of decimal places,
   * return num decimal places
   *
   * Stops after max-precision is exceeded. Max precision defined by epsilon.
   * @param array $a An array of numbers.
   */
  function precision($a) { 
    $len = 0;
    $maxprec = max(6,0-floor(log(self::$epsilon)));
    foreach ($ys as $value) {
      $pos = strpos((string)$value,'.');
      if ($pos!==false) {
        $len = max($len,strlen((string)$value)-$pos-1);
        if ($len > $maxprec) {
          return $maxprec;
        }
      }
    }
    return $len;
  } // END: function precision($a)


  /**
   * Compare a[i] to b[i]. Return array of (1=a[i]>b[i] else 0).
   *
   * @param array $a Array of number to compare against $b.
   * @param array $b Array of number to compare against $a.
   * @return array of 0 or 1; 1 if a[i]>b[i], 0 otherwise.
   */
  function gt($a, $b) {
    $a = func_get_arg(0);
    $c = (is_array($a)) ? self::combine(
      array_keys($a), array_fill(0,count($a),1)) : 1;
    for ($i=1,$n=func_num_args(); $i<$n; $i++) {
      $b = func_get_arg($i);

      if (!is_array($a)) {
        if (!is_array($b)) {
          // compare single a value to single b value
          $c *= ($a>$b) ? 1 : 0;
          continue;
        }
        // Prepare single a value as an array, keyed like b
        $a = self::combine(array_keys($b), array_fill(0, count($b), $a));
        $c = self::combine(array_keys($b), array_fill(0, count($b), 1));
      }

      if( !is_array($b) ) { 
        // Prepare a single b value as an array, keyed like a
        $b = self::combine(array_keys($a), array_fill(0, count($a), $b));
      }

      foreach (array_keys($a) as $j) {
        $c[$j] *= ($a[$j]>$b[$j]) ? 1 : 0;
      }
    }
    return $c;
  } // END: function gt($a, $b)

  function gte($a, $b) {
    $a = func_get_arg(0);
    $c = (is_array($a)) ? self::combine(
      array_keys($a), array_fill(0, count($a), 1)) : 1;
    for ($i=1,$n=func_num_args(); $i<$n; $i++) {
      $b = func_get_arg($i);

      if (!is_array($a)) {
        if (!is_array($b)) {
          // compare single a value to single b value
          $c = ($a>=$b) ? 1 : 0;
          continue;
        }
        // Prepare single a value as an array, keyed like b
        $a = self::combine(array_keys($b), array_fill(0, count($b), $a));
        $c = self::combine(array_keys($b), array_fill(0, count($b), 1));
      }

      if( !is_array($b) ) { 
        // Prepare a single b value as an array, keyed like a
        $b = self::combine(array_keys($a), array_fill(0, count($a), $b));
      }

      foreach (array_keys($a) as $j) {
        $c[$j] *= ($a[$j]>=$b[$j]) ? 1 : 0;
      }
    }
    return $c;
  } // END: function gte($a,$b)

  function lt($a,$b) { 
    $a = func_get_arg(0);
    $c = (is_array($a)) ? self::combine(
      array_keys($a), array_fill(0, count($a), 1)) : 1;
    for ($i=1,$n=func_num_args(); $i<$n; $i++) {
      $b = func_get_arg($i);

      if (!is_array($a)) {
        if (!is_array($b)) {
          // compare single a value to single b value
          $c *= ($a<$b) ? 1 : 0;
          continue;
        }
        // Prepare single a value as an array, keyed like b
        $a = self::combine(array_keys($b), array_fill(0, count($b), $a));
        $c = self::combine(array_keys($b), array_fill(0, count($b), 1));
      }

      if( !is_array($b) ) { 
        // Prepare a single b value as an array, keyed like a
        $b = self::combine(array_keys($a), array_fill(0, count($a), $b));
      }

      foreach (array_keys($a) as $j) {
        $c[$j] *= ($a[$j]<$b[$j]) ? 1 : 0;
      }
    }
    return $c;
  } // END: function lt($a,$b)

  function lte($a,$b) { 
    $a = func_get_arg(0);
    $c = (is_array($a)) ? self::combine(
      array_keys($a), array_fill(0, count($a), 1)) : 1;
    for ($i=1,$n=func_num_args(); $i<$n; $i++) {
      $b = func_get_arg($i);

      if (!is_array($a)) {
        if (!is_array($b)) {
          // compare single a value to single b value
          $c *= ($a<=$b) ? 1 : 0;
          continue;
        }
        // Prepare single a value as an array, keyed like b
        $a = self::combine(array_keys($b), array_fill(0, count($b), $a));
        $c = self::combine(array_keys($b), array_fill(0, count($b), 1));
      }

      if( !is_array($b) ) { 
        // Prepare a single b value as an array, keyed like a
        $b = self::combine(array_keys($a), array_fill(0, count($a), $b));
      }

      foreach (array_keys($a) as $j) {
        $c[$j] *= ($a[$j]<=$b[$j]) ? 1 : 0;
      }
    }
    return $c;
  } // END: function lte($a, $b)


  function eq($a, $b) {
    $a = func_get_arg(0);
    $c = (is_array($a)) ? self::combine(
      array_keys($a), array_fill(0, count($a), 1)) : 1;
    for ($i=1,$n=func_num_args(); $i<$n; $i++) {
      $b = func_get_arg($i);

      if (!is_array($a)) {
        if (!is_array($b)) {
          // compare single a value to single b value
          $c *= (self::isZero($a-$b)) ? 1 : 0;
          continue;
        }
        // Prepare single a value as an array, keyed like b
        $a = self::combine(array_keys($b), array_fill(0, count($b), $a));
        $c = self::combine(array_keys($b), array_fill(0, count($b), 1));
      }

      if( !is_array($b) ) { 
        // Prepare a single b value as an array, keyed like a
        $b = self::combine(array_keys($a), array_fill(0, count($a), $b));
      }

      foreach (array_keys($a) as $j) {
        $c[$j] *= (self::isZero($a[$j]-$b[$j])) ? 1 : 0;
      }
    }
    return $c;
  } // END: function eq($a, $b)


  function neq($a, $b) {
    $a = func_get_arg(0);
    $c = (is_array($a)) ? self::combine(
      array_keys($a), array_fill(0, count($a), 1)) : 1;
    for ($i=1,$n=func_num_args(); $i<$n; $i++) {
      $b = func_get_arg($i);

      if (!is_array($a)) {
        if (!is_array($b)) {
          // compare single a value to single b value
          $c *= (!self::isZero($a-$b)) ? 1 : 0;
          continue;
        }
        // Prepare single a value as an array, keyed like b
        $a = self::combine(array_keys($b), array_fill(0, count($b), $a));
        $c = self::combine(array_keys($b), array_fill(0, count($b), 1));
      }

      if( !is_array($b) ) { 
        // Prepare a single b value as an array, keyed like a
        $b = self::combine(array_keys($a), array_fill(0, count($a), $b));
      }

      foreach (array_keys($a) as $j) {
        $c[$j] *= (!self::isZero($a[$j]-$b[$j])) ? 1 : 0;
      }
    }
    return $c;
  } // END: function neq($a,$b)


  function filter($a, $b) {
    $a = func_get_arg(0);
    $c = (is_array($a)) ? self::combine(
      array_keys($a), array_fill(0, count($a), 1)) : 1;
    for ($i=1,$n=func_num_args(); $i<$n; $i++) {
      $b = func_get_arg($i);

      if (!is_array($a)) {
        if (!is_array($b) && !$b) {
          // If any parameter is non-array and false: result is empty.
          return array();
        }
        // Prepare single a value as an array, keyed like b
        $a = self::combine(array_keys($b), array_fill(0, count($b), $a));
        // Redefine $c as array with keys comparable to $b.
        $c = self::combine(array_keys($b), array_fill(0, count($b), 1));
      }

      if( is_array($b) ) { 
        foreach (array_keys($a) as $j) {
          $c[$j] *= ($b[$j]) ? 1 : 0;
        }
      } else if( !$b ) { 
        // If any parameter is non-array and false: result is empty.
        return array();
      }
    }

    $d = array();
    foreach(array_keys($c) as $i) { 
      if( $c[$i] ) { 
        $d[$i] = $a[$i];
      }
    }
    return $d;
  } // END: function filter($a,$b)


  /**
   * compute addition for each element of subsequent arrays.
   * out[i] = a[i]+b[i]+c[i]+... left-to-right for all elements, all arrays.
   *
   * @public
   * @param array|number $a first in series of arrays of nums to calc op on.
   * @param array|number $b second in series of arrays of nums to calc op on.
   * @param array|number $c (optional) third in series...
   * @return array|number a op b for each element in a, b.
   */
  function add($a, $b) {
    $a = func_get_arg(0);
    for ($i=1,$n=func_num_args(); $i<$n; $i++) { 
      $b = func_get_arg($i);

      if (!is_array($a)) {
        if (!is_array($b)) {
          // compare single a value to single b value
          $a += $b;
          continue;
        }
        // Prepare single a value as an array, keyed like b
        $a = self::combine(array_keys($b), array_fill(0, count($b), $a));
      }

      if( !is_array($b) ) { 
        // Prepare a single b value as an array, keyed like a
        $b = self::combine(array_keys($a), array_fill(0, count($a), $b));
      }

      foreach (array_keys($a) as $j) { 
        $a[$j] += $b[$j];
      }
    }
    return $a;
  } // END: function add($a, $b)


  /**
   * compute subtraction for each element of subsequent arrays.
   * out[i] = a[i]-b[i]-c[i]-... left-to-right for all elements, all arrays.
   *
   * @public
   * @param array|number $a first in series of arrays of nums to calc op on.
   * @param array|number $b second in series of arrays of nums to calc op on.
   * @param array|number $c (optional) third in series...
   * @return array|number a op b for each element in a, b.
   */
  function sub($a, $b) {
    $a = func_get_arg(0);
    for ($i=1,$n=func_num_args(); $i<$n; $i++) { 
      $b = func_get_arg($i);

      if (!is_array($a)) {
        if (!is_array($b)) {
          // compare single a value to single b value
          $a -= $b;
          continue;
        }
        // Prepare single a value as an array, keyed like b
        $a = self::combine(array_keys($b), array_fill(0, count($b), $a));
      }

      if( !is_array($b) ) { 
        // Prepare a single b value as an array, keyed like a
        $b = self::combine(array_keys($a), array_fill(0, count($a), $b));
      }

      foreach (array_keys($a) as $j) { 
        $a[$j] -= $b[$j];
      }
    }
    return $a;
  } // END: function sub($a,$b)


  /**
   * compute multiplication for each element of subsequent arrays.
   * out[i] = a[i]*b[i]*c[i]*... left-to-right for all elements, all arrays.
   *
   * @public
   * @param array|number $a first in series of arrays of nums to calc op on.
   * @param array|number $b second in series of arrays of nums to calc op on.
   * @param array|number $c (optional) third in series...
   * @return array|number a op b for each element in a, b.
   */
  function mul($a, $b) {
    $a = func_get_arg(0);
    for ($i=1,$n=func_num_args(); $i<$n; $i++) { 
      $b = func_get_arg($i);

      if (!is_array($a)) {
        if (!is_array($b)) {
          // compare single a value to single b value
          $a *= $b;
          continue;
        }
        // Prepare single a value as an array, keyed like b
        $a = self::combine(array_keys($b), array_fill(0, count($b), $a));
      }

      if( !is_array($b) ) { 
        // Prepare a single b value as an array, keyed like a
        $b = self::combine(array_keys($a), array_fill(0, count($a), $b));
      }

      foreach (array_keys($a) as $j) { 
        $a[$j] *= $b[$j];
      }
    }
    return $a;
  } // END: function mul($a,$b)



  /**
   * compute division for each element of subsequent arrays.
   * out[i] = a[i]/b[i]/c[i]/... left-to-right for all elements, all arrays.
   *
   * @public
   * @param array|number $a first in series of arrays of nums to calc op on.
   * @param array|number $b second in series of arrays of nums to calc op on.
   * @param array|number $c (optional) third in series, up to any num of args
   * @return array|number a op b for each element in a, b.
   */
  function div($a, $b) {
    $a = func_get_arg(0);
    for ($i=1,$n=func_num_args(); $i<$n; $i++) { 
      $b = func_get_arg($i);

      if (!is_array($a)) {
        if (!is_array($b)) {
          // compare single a value to single b value
          $a = (self::isZero($b)) ? self::$fillvalue : $a/$b;
          continue;
        }
        // Prepare single a value as an array, keyed like b
        $a = self::combine(array_keys($b), array_fill(0, count($b), $a));
      }

      if( !is_array($b) ) { 
        // Prepare a single b value as an array, keyed like a
        $b = self::combine(array_keys($a), array_fill(0, count($a), $b));
      }

      foreach (array_keys($a) as $j) { 
        $a[$j] = (self::isZero($b[$j])) ? self::$fillvalue : $a[$j]/$b[$j];
      }
    }
    return $a;
  } // END: function div($a,$b)


  /**
   * Create tuples at each index of input arrays.
   *
   * out[i] = array(a[i],b[i],...)
   *
   * @public
   * @param array $a first in series of arrays of numbers
   * @param array $b second in series of arrays of numbers
   * @param array $c (optional) third in series...up to any number of args.
   * @return array|number a op b for each element in a, b.
   */
  function zip($a, $b) {
    $out = array();
    $a = func_get_arg(0);
    foreach (array_keys($a) as $i) { 
      $out[$i] = array($a[$i]);
    }
    for ($i=1,$n=func_num_args(); $i<$n; $i++) {
      $b = func_get_arg($i);
      foreach (array_keys($b) as $j) {
        if (isset($out[$j])) { 
          $out[$j][] = $b[$j];
        }
      }
    }
    return $out;
  } // END: function zip($a, $b)


  /**
   * Apply the callback $cb expecting N args, where N=num arrays after $cb
   *
   * out[i] = callback(a[i],b[i],...)
   *
   * @public
   * @param callback $cb The method to call accepting single args.
   * @param array $a first in series of arrays of numbers
   * @param array $b second in series of arrays of numbers
   * @param array $c (optional) third in series...up to any number of args.
   * @return array|number a op b for each element in a, b.
   */
  function map($cb, $a, $b) {
    $out = array();
    $args = func_get_args();
    $cb = array_shift($args);
    $tuples = call_user_func_array(array(self, 'zip'), $args);
    foreach( array_keys($tuples) as $i ) {
      $out[$i] = call_user_func_array($cb, $tuples[$i]);
    }
    return $out;
  } // END: function map($cb, $a, $b)


  /**
   * compute modulus remainder for each element of subsequent arrays.
   * out[i] = fmod(fmod(a[i],b[i]),c[i]) for all elements.
   *
   * @public
   * @param array|number $a first in series of arrays of nums to calc op on.
   * @param array|number $b second in series of arrays of nums to calc op on.
   * @param array|number $c (optional) third in series...
   * @return array|number a op b for each element in a, b.
   */
  function mod($a, $b) {
    $a = func_get_arg(0);
    for ($i=1,$n=func_num_args(); $i<$n; $i++) { 
      $b = func_get_arg($i);

      if (!is_array($a)) {
        if (!is_array($b)) {
          // compare single a value to single b value
          $a = (self::isZero($b)) ? self::$fillvalue : fmod($a, $b);
          continue;
        }
        // Prepare single a value as an array, keyed like b
        $a = self::combine(array_keys($b), array_fill(0, count($b), $a));
      }

      if( !is_array($b) ) { 
        // Prepare a single b value as an array, keyed like a
        $b = self::combine(array_keys($a),array_fill(0,count($a),$b));
      }

      foreach (array_keys($a) as $j) { 
        $a[$j] = (self::isZero($b[$j])) ? self::$fillvalue : fmod(
          $a[$j], $b[$j]);
      }
    }
    return $a;
  } // END: function mod($a, $b)


  /**
   * Compare each element in $a against each element in $b.
   * returns out[i] = min(a[i],b[i],c[i]), or min(a[i],b), if b is a number.
   *
   * @public
   * @param array|number $a first in series of arrays of nums to calc op on.
   * @param array|number $b second in series of arrays of nums to calc op on.
   * @param array|number $c (optional) third in series...
   * @return array|number a op b for each element in a, b.
   */
  function min($a, $b=null) {
    $a = func_get_arg(0);
    if (func_num_args() < 2) {
      return (is_array($a)) ? min($a) : $a;
    }
    for ($i=1,$n=func_num_args(); $i<$n; $i++) { 
      $b = func_get_arg($i);
      if (!is_array($a)) {
        if (!is_array($b)) {
          // compare single a value to single b value
          if ($b < $a) {
            $a = $b;
          }
          continue;
        }
        // Prepare single a value as an array, keyed like b
        $a = self::combine(array_keys($b), array_fill(0, count($b), $a));
      }
      if( !is_array($b) ) { 
        // Prepare a single b value as an array, keyed like a
        $b = self::combine(array_keys($a), array_fill(0, count($a), $b));
      }
      foreach (array_keys($a) as $j) { 
        if ($b[$j] < $a[$j]) { 
          $a[$j] = $b[$j];
        }
      }
    }
    return $a;
  } // END: function min($a, $b)


  /**
   * Compare each element in $a against each element in $b.
   * returns out[i] = max(a[i],b[i],c[i]), or max(a[i],b), if b is a number.
   *
   * @public
   * @param array|number $a first in series of arrays of nums to calc op on.
   * @param array|number $b second in series of arrays of nums to calc op on.
   * @param array|number $c (optional) third in series...
   * @return array|number a op b for each element in a, b.
   */
  function max($a, $b=null) {
    $a = func_get_arg(0);
    if (func_num_args() < 2) {
      return (is_array($a)) ? max($a) : $a;
    }

    for ($i=1,$n=func_num_args(); $i<$n; $i++) { 
      $b = func_get_arg($i);
      if (!is_array($a)) { 
        if (!is_array($b)) { 
          // compare single a value to single b value
          if ($a < $b) { 
            $a = $b;
          }
          continue;
        }
        // Prepare single a value as an array, keyed like b
        $a = self::combine(array_keys($b), array_fill(0, count($b), $a));
      }
      if( !is_array($b) ) { 
        // Prepare a single b value as an array, keyed like a
        $b = self::combine(array_keys($a), array_fill(0, count($a), $b));
      }
      // compare each array a item to each array b item
      foreach (array_keys($a) as $j) { 
        if ($a[$j] < $b[$j]) { 
          $a[$j] = $b[$j];
        }
      }
    }
    return $a;
  } // END: function max($a, $b)


  /**
   * Rank a set of arrays, where keys are shared across set.
   */
  function mkrank() { 
    $args = func_get_args();
    $wi = array_pop($args);
    // if no weights provided, default to equal weight.
    if( count($args)!==count($wi)
    ||  array_keys($wi)!==range(0,count($wi)-1)
    ) {
      array_unshift($args,$wi);
      $wi = array_fill(0, count($args), 1);
    }

    // calc length of each array.
    $alen = array();

    // compute weight-normalizer
    $wis = array_sum(array_map('abs', $wi));
    $wis = ($wis) ?  1/$wis : 1;

    foreach( array_keys($args) as $i ) { 
      $alen[$i] = count($args[$i]);
      if( $wi[$i]<0 ) { 
        // sort desc
        arsort($args[$i]);
        $wi[$i] = 0-$wi[$i];
      } else { 
        // sort asc
        asort($args[$i]);
      }
      // normalize weight.
      $wi[$i] *= $wis;
    }

    // only return rank for symbols that exist across all arrays.
    $keys = array_keys(current($args));
    $dist = array();
    $na = count($args);
    foreach( $keys as $k ) { 
      $d = 0;
      for($i=0; $i<$na; $i++) { 
        if( !isset($args[$i][$k]) ) { 
          $d = -1;
          break;
        }
        // compute [0,1) rank.
        $pct = $args[$i][$k]/$alen[$i];
        $d += $wi[$i] * ($pct*$pct);
      }
      if( $d>=0 ) { 
        $dist[$k] = sqrt($d);
      }
    }
    asort($dist);
    return $dist;
  } // END: function mkrank()


  /**
   * Compare each element in $a against each element in $b (default=0).
   * Returns a[i] = +1, -1, or 0 is a[i]>b[i], a[i]<b[i], or a[i]==b[i]
   *
   * @public
   * @param array|number $a first in series of arrays of nums to calc op on.
   * @param array|number $b second in series of arrays of nums to calc op on.
   * @param array|number $c (optional) third in series...
   * @return array|number a op b for each element in a, b.
   */
  function sign($a, $b=0) {
    $a = func_get_arg(0);

    if (func_num_args() < 2) { 
      if (is_array($a)) { 
        // compare each array item to single b value
        foreach (array_Keys($a) as $i) { 
          $a[$i] = ($a[$i] < $b) ? -1 : ( ($a[$i] > $b) ? +1 : 0 );
        }
        return $a;
      } 
      // compare single a value to single b value
      return ($a < $b) ? -1 : ( ($a > $b) ? +1 : 0 );
    }

    for ($i=1,$n=func_num_args(); $i<$n; $i++) { 
      $b = func_get_arg($i);
      if (!is_array($a)) { 
        if (!is_array($b)) { 
          // compare single a value to single b value
          $a = ($a < $b) ? -1 : ( ($a > $b) ? +1 : 0 );
          continue;
        }
        // Prepare single a value as an array, keyed like b
        $a = self::combine(array_keys($b), array_fill(0, count($b), $a));
      }
      // compare each array a item to each array b item
      foreach (array_keys($a) as $j) { 
        $a[$j] = ($a[$j] < $b[$j]) ? -1 : ( ($a[$j] > $b[$j]) ? +1 : 0 );
      }
    }
    return $a;
  } // END: function sign($a,$b)


  /**
   * @access protected
   */
  function combine($k, $v) {
    if (function_exists("array_combine")) { 
      return array_combine($k, $v);
    }
    $out = array();
    foreach (array_keys($k) as $i) { 
      $out[$k[$i]] = $v[$i];
    }
    return $out;
  } // END: function combine($k, $v)


  /**
   * Transpose a multi-dimensional matrix.
   * B[j][i] = A[i][j] | forall i, j
   *
   * @access public
   * @param array $array A multi-dimensional array to transpose.
   * @return array transposed version of input $array.
   */
  function transpose($array) {
    if( count($array)==1 ) {
      $b = array();
      foreach(current($array) as $a) {
        $b[] = array($a);
      }
      return $b;
    }
    array_unshift($array, null);
    return call_user_func_array('array_map', $array);
  } // END: function transpose($array)


  /**
   * @access protected
   */
  function isZero($v) { 
    return ($v<self::$epsilon && $v>-(self::$epsilon));
  } // END: function isZero($v)



  /**
   * Fill NaN/null values with prior value.
   * Performs fill-forward and back-fill.
   * First non-NaN value will be carried forward to start of array.
   *
   * @access public
   * @param array $v Array of values containing $nan to fill.
   * @param mixed $nan A NaN value to replace. (default=null)
   * @return array of values that don't contain $nan.
   */
  function fillna($v,$nan=null) {
    $pval = current($v);
    $firstVal = $nan;
    $nans = array();
    foreach(array_keys($v) as $i) {
      // carry-forward previous value to fill nan.
      if( $v[$i]===$nan ) {
        $v[$i] = $pval;
      }
      // update pval for next iteration.
      $pval = $v[$i];

      // track nans from start of sequence.
      if( $firstVal===$nan ) {
        if( $pval===$nan ) {
          $nans[] = $i;
        } else {
          // backfill
          $firstVal = $pval;
          foreach($nans as $j) {
            $v[$j] = $firstVal;
          }
        }
      }
    }
    return $v;
  } // END: function fillna($v,$nan=null)


  /**
   * Fill $find values with $repl value.
   *
   * @access public
   * @param array $v Array of values containing $find to replace.
   * @param mixed $find Any value to look for.
   * @param mixed $repl Any value that will replace $find if found (dft=null)
   * @return array of values that don't contain $find.
   */
  function replace($a, $find, $repl=null) {
    foreach(array_keys($a) as $i) {
      if( $a[$i]===$find ) {
        $a[$i] = $repl;
      }
    }
    return $a;
  } // END: function replace($a, $find, $repl=null)

} // END: class A


// EOF -- A.php
?>
