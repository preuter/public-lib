<?php
 /**
  * Perform Hierarchial Clustering on a matrix of dissimilarities.
  * To perform clustering on correlations, use a dissimilarity function:
  *
  *   Dissimilarity = 1 - Correlation
  *   Dissimilarity = (1 - Correlation)/2
  *   Dissimilarity = 1 - Abs(Correlation)
  *   Dissimilarity = Sqrt(1 - Correlation2)
  *
  * @file HClust.php
  * @date 2015-02-13 11:32 PST (A Friday)
  * @author Paul Reuter
  * @version 1.0.6
  *
  * @modifications <pre>
  * 1.0.0 - 2015-02-11 - Created from template: phpclass
  * 1.0.1 - 2015-02-11 - BugFix: Avoid undefined index toLowerTriangle
  * 1.0.2 - 2015-02-11 - BugFix: Avoid assignment if value==0
  * 1.0.3 - 2015-02-11 - Add: flatten($a) to flatten the clusters.
  * 1.0.4 - 2015-02-11 - Add: Option to include distance with cluster pairs.
  * 1.0.5 - 2015-02-13 - Modify: flatten defaults to bfs_flatten.
  * 1.0.6 - 2015-02-13 - Modify: flatten is tallest branch first.
  * </pre>
  */



/**#@+
 * Defined constants:
 */
// None
/**#@-*/


/**
 * Perform Hierarchial Clustering on a matrix of dissimilarities
 * @package HClust
 */
class HClust {
  /**
   * Matrix of distance values.
   * @access private
   */
  var $dist = array();
  /**
   * Method used to pick and update distances.
   * @access private
   */
  var $method = 'complete'; // single: shortest dist. any to any.
                            // complete: longest dist. any to any.
  /**
   * Stores row or column labels if specified.
   * @access private
   */
  var $labels = array();
  /**
   * Stores computed clusters
   * @access private
   */
  var $clusters = array();

  /**
   * bool flag: Do we include distance with set pairing?
   * @access private
   */
  var $groupWithDistance = false;

  /**
   * Perform Hierarchial Clustering on a matrix of dissimilarities
   *
   * @access public
   * @param matrix $distances A matrix of dissimilarity values.
   * @return new HClust object
   */
  function HClust($distances, $method='complete', $labels=null) { 
    $this->dist = $this->toLowerTriangle($distances);
    $this->setMethod($method);
    $this->setLabels($labels);
    return $this;
  } // END: constructor HClust


  /**
   * Assign what kind of linkage method to use (single, complete).
   * Supported methods: single, complete.
   * Other methods: average, median, centroid, ... 
   *
   * @access public
   * @param string $method (single, complete, average... )
   * @return bool true if supported, false otherwise.
   * @link https://stat.ethz.ch/R-manual/R-patched/library/stats/html/hclust.html
   */
  function setMethod($method='complete') {
    $this->clusters = array();
    $method = strtolower(trim($method));
    switch($method) {
      case 'single':
      case 'complete':
        $this->method = $method;
        return true;
      case 'average': // TODO
      default:
        error_log("Unsupported method: $method not in (single|complete)");
    }
    return false;
  } // END: function setMethod($method='complete')


  /**
   * Assign column labels.
   *
   * @access public
   * @param array $labels array of string Column labels.
   * @reutrn bool true if $labels is array, false otherwise.
   */
  function setLabels($labels) {
    $this->clusters = array();
    $this->labels = $labels;
    return (is_array($labels)) ? true : false;
  } // END: function setLabels($labels)


  /**
   * Set flag to include (exclude) the distance value when returning clusters.
   *
   * @access public
   * @param bool $b true to include distance in group, false not to.
   * @return bool always true.
   */
  function setWithDistance($b=true) {
    $this->groupWithDistance = ($b) ? true : false;
    return true;
  } // END: function setWithDistance($b=true)


  /**
   * Get a list of ordered clusters.
   *
   * @access public
   * @return array of cluster hierarchy.
   */
  function getClusters() {
    if( empty($this->clusters) ) {
      if( !$this->buildClusters() ) {
        return false;
      }
    }
    if( !empty($this->labels) ) {
      return $this->indexToLabel($this->clusters,2);
    }
    return $this->clusters;
  } // END: function getClusters()


  /**
   * Helper function: M[i][j] = 1 - M[i][j];
   * Compute a dissimilarity function for all elements in a correlation table.
   *
   * @access public
   * @param array $m Matrix of correlation coefficients.
   * @return array A matrix of dissimilarity values.
   * @see one_minus_abs
   * @see sqrt_one_minus_x2
   */
  function one_minus_x($m) {
    $m = self::toLowerTriangle($m);
    for($i=0,$n=count($m); $i<$n; $i++) {
      for($j=0; $j<=$i; $j++) {
        $m[$i][$j] = 1 - $m[$i][$j];
      }
    }
    return $m;
  } // END: function one_minus_x($m)


  /**
   * Helper function: M[i][j] = 1 - abs(M[i][j]);
   * Compute a dissimilarity function for all elements in a correlation table.
   *
   * @access public
   * @param array $m Matrix of correlation coefficients.
   * @return array A matrix of dissimilarity values.
   * @see one_minus_x
   * @see sqrt_one_minus_x2
   */
  function one_minus_abs($m) {
    $m = self::toLowerTriangle($m);
    for($i=0,$n=count($m); $i<$n; $i++) {
      for($j=0; $j<=$i; $j++) {
        $m[$i][$j] = 1 - abs($m[$i][$j]);
      }
    }
    return $m;
  } // END: function one_minus_abs($m)


  /**
   * Helper function: M[i][j] = sqrt(1 - M[i][j]**2);
   * Compute a dissimilarity function for all elements in a correlation table.
   *
   * @access public
   * @param array $m Matrix of correlation coefficients.
   * @return array A matrix of dissimilarity values.
   * @see one_minus_x
   * @see one_minus_abs
   */
  function sqrt_one_minus_x2($m) {
    $m = self::toLowerTriangle($m);
    for($i=0,$n=count($m); $i<$n; $i++) {
      for($j=0; $j<=$i; $j++) {
        $m[$i][$j] = sqrt(1 - $m[$i][$j]*$m[$i][$j]);
      }
    }
    return $m;
  } // END: function sqrt_one_minus_x2($m)


  /**
   * Convert the matrix to a lower triangle of distances.
   *
   * @access private
   * @param matrix $A An array of arrays containing distances.
   * @return matrix Same size of A, with lower triangle.
   */
  function toLowerTriangle($A) {
    reset($A);
    $ni = $nj = max(array_map('count',$A));
    $B = self::init($ni, $nj, 0);
    for($i=0; $i<$ni; $i++) {
      for($j=0; $j<=$i; $j++) {
        if( isset($A[$j][$i]) && $A[$j][$i]!=0 ) {
          $B[$i][$j] = $A[$j][$i];
        } else if( isset($A[$i][$j]) ) {
          $B[$i][$j] = $A[$i][$j];
        }
      }
    }
    return $B;
  } // END: function toLowerTriangle($A)


  /**
   * Utilize :dist, produce hierarchy of clusters, store in :clusters.
   *
   * @access private
   * @return bool true on success, false on failure.
   */
  function buildClusters() {
    // Initialize clusters
    $dist = $this->dist;
    $sets = array();
    for($i=0,$n=count($dist); $i<$n; $i++) {
      $sets[$i] = array($i);
    }
    // Find closest pair of clusters.
    $ni = count($sets);
    $odist = 0;
    while( $ni > 1 ) {
      switch( strtolower($this->method) ) {
        case 'single':
          // Identify two sets to merge.
          list($i,$j,$d) = $this->_fn_select($dist, 'min');
          // update distances
          $dist = $this->_fn_update($dist, $i, $j, 'min');
          break;
        case 'complete':
          // Identify two sets to merge.
          list($i,$j,$d) = $this->_fn_select($dist, 'min');
          // update distances
          $dist = $this->_fn_update($dist, $i, $j, 'max');
          break;
        case 'average': // TODO
        default:
          error_log("Unsupported method: ".$this->method);
          return false;
      }
      // merge
      if( $this->groupWithDistance ) {
        $sets[$i] = array($sets[$i], $sets[$j], $d);
      } else {
        $sets[$i] = array($sets[$i], $sets[$j]);
      }

      array_splice($sets,$j,1);
      $ni--;
    }

    $this->clusters = $this->collapseSingles($sets);
    return true;
  } // END: function buildClusters()


  /**
   * Convert singleton sets to simple values|labels.
   *
   * @access private
   * @param array $seq A sequence of sets.
   * @return array a collapsed representation of sets.
   */
  function collapseSingles($seq) {
    if( !is_array($seq) ) {
      return $seq;
    }
    if( count($seq)==1 ) {
      return $this->collapseSingles(current($seq));
    }
    foreach( array_keys($seq) as $i ) {
      $seq[$i] = $this->collapseSingles($seq[$i]);
    }
    return $seq;
  } // END: function collapseSingles($seq)


  /**
   * Find nearest cluster by minimizing distance.
   *
   * @access private
   * @param array $dist lower matrix of distances.
   * @return array (i, j) index of the two sets to join.
   * @link https://stat.ethz.ch/R-manual/R-patched/library/stats/html/hclust.html
   */
  function _fn_select($dist,$fn='min') {
    $n = count($dist);
    $xi = 1;
    $xj = 0;
    $val = $dist[$xi][$xj];
    for($i=1,$n=count($dist); $i<$n; $i++) {
      for($j=0; $j<$i; $j++) {
        if( $fn($dist[$i][$j], $val) != $val ) {
          $val = $fn($dist[$i][$j], $val);
          $xi = $i;
          $xj = $j;
        }
      }
    }
    return array($xi, $xj, $val);
  } // END: function _fn_select($dist,$fn='min')


  /**
   * Compute the new minimum distance from the new cluster to each of the old.
   *
   * @access private
   * @param array $dist lower matrix of distances.
   * @param uint $i index of new cluster.
   * @param uint $j index of old cluster.
   * @param callback $fn A function to compare new and old values.
   * @return array lower matrix of distances to new cluster.
   */
  function _fn_update($dist, $i, $j, $fn='min') {
    // update future set with min per column.
    for($c=0; $c<=$i; $c++) {
      $dist[$i][$c] = $fn($dist[$i][$c], $dist[$j][$c]);
    }
    // update future set with min per row.
    for($r=$i, $n=count($dist); $r<$n; $r++) {
      $dist[$r][$i] = $fn($dist[$r][$i], $dist[$r][$j]);
    }
    // remove old set from each column.
    for($r=0, $n=count($dist); $r<$n; $r++) {
      array_splice($dist[$r], $j, 1);
    }
    // remove old set from list of rows.
    array_splice($dist, $j, 1);
    return $dist;
  } // END: function _fn_update($dist, $i, $j, $fn='min')


  /**
   * Convert column index to labels.
   *
   * @access public
   * @param array $seq Array of column indexes.
   * @param int $stopIx Stop replacing index with label at stopIx (less than)
   * @return array Same shape as $seq
   */
  function indexToLabel($seq,$stopIx=-1) {
    if( empty($this->labels) ) {
      return $seq;
    }
    if( !is_array($seq) ) {
      return $this->labels[$seq];
    }
    foreach( array_keys($seq) as $i ) {
      if( $stopIx < 0 || $i < $stopIx ) {
        if( is_array($seq[$i]) ) {
          $seq[$i] = $this->indexToLabel($seq[$i], $stopIx);
        } else {
          $seq[$i] = $this->labels[$seq[$i]];
        }
      }
    }
    return $seq;
  } // END: function indexToLabel($seq, $stopIx=-1)


  /**
   * Create a matrix of $r rows by $c cols, initialized with $val value.
   *
   * @access protected
   * @param uint $r Number of rows.
   * @param uint $c Number of cols.
   * @param mixed $val Initialized value of each cell.
   * @return matrix of $r by $c dimensions.
   */
  function init($r,$c=null,$val=0) {
    $c = ($c===null) ? $r : $c;
    return array_fill(0,$r, array_fill(0,$c, $val));
  } // END: function init($r,$c=null,$val=0)


  /**
   * Recursively flatten an array depth-first.
   *
   * @access public
   * @param array $a an array, possibly containing multiple arrays.
   * @return array A 1-dimensional array of values found in post-order.
   */
  function dfs_flatten($a) {
    if( !is_array($a) ) {
      return array($a);
    }
    $c = array();
    foreach( $a as $b ) {
      $c = array_merge($c, self::dfs_flatten($b));
    }
    return $c;
  } // END: function dfs_flatten($a)


  /**
   * Flatten an array (prefer longer branches first).
   *
   * @access public
   * @param array $a an array, possibly containing multiple arrays.
   * @return array A 1-dimensional array of values found in-order.
   */
  function flatten($a) {
    if( !is_array($a) ) {
      return array($a);
    }
    $b = array();
    $c = array();
    foreach($a as $d) {
      $set = self::flatten($d);
      $c[] = count($set);
      $b[] = $set;
    }
    array_multisort($c,SORT_DESC,$b,SORT_DESC);
    $e = array();
    foreach( $b as $d ) {
      $e = array_merge($e, $d);
    }
    return $e;
  } // END: function flatten($a)


  /**
   * Iteratively flatten an array breadth-first.
   *
   * @access public
   * @param array $a an array, possibly containing multiple arrays.
   * @return array A 1-dimensional array of values found in pre-order.
   * @see bfs_flatten
   */
  function bfs_flatten($a) {
    if( !is_array($a) ) {
      return array($a);
    }
    $b = array();
    while( !empty($a) ) {
      $c = array_shift($a);
      if( is_array($c) ) {
        foreach($c as $d) {
          if( is_array($d) ) {
            array_push($a,$d);
          } else {
            array_push($b,$d);
          }
        }
      } else {
        array_push($b, $c);
      }
    }
    return $b;
  } // END: function bfs_flatten($a)


} // END: class HClust



/* **** **
  DEBUG
  Example from: http://www.analytictech.com/networks/hiclus.htm
** **** */
/*
$labels = array('BOS','NY','DC','MIA','CHI','SEA','SF','LA','DEN');
$dist = array(
array(0),
array(206,0),
array(429,233,0),
array(1504,1308,1075,0),
array(963,802,671,1329,0),
array(2976,2815,2684,3273,2013,0),
array(3095,2934,2799,3053,2142,808,0),
array(2979,2786,2631,2687,2054,1131,379,0),
array(1949,1771,1616,2037,996,1307,1235,1059,0)
);
$hc = new HClust($dist, 'complete', $labels);
print_r( $hc->getClusters() );
$hc->setMethod('single');
print_r( $hc->getClusters() );
*/


// EOF -- HClust.php
?>
