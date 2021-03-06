<?php
 /**
  * Compute the confusion matrix of a set of observations against predicted.
  *
  * @file ConfusionMatrix.php
  * @date 2014-07-29 16:04 PDT
  * @author Paul Reuter
  * @version 1.0.1
  *
  * @modifications <pre>
  * 1.0.0 - 2014-07-17 - Created from template: phpclass
  * 1.0.1 - 2014-07-29 - Add: Unpublished ncfm (n-dime confusion matrix)
  * </pre>
  */


/**
 * Compute the confusion matrix of a set of observations against predicted.
 * @package ConfusionMatrix
 */
class ConfusionMatrix {
  /**
   * binary confusion matrix.
   * @access protected
   */
  var $cfm;

  /**
   * n-dimensional confusion matrix.
   * @access private
   */
  var $ncfm;


  /**
   * Compute the confusion matrix of a set of observations against predicted.
   *
   * @link http://en.wikipedia.org/wiki/Confusion_matrix
   * @access public
   * @param array &$observed Ground truth (correct) target values.
   * @param array &$predicted Estimated targets as returned by a classifier.
   * @param array $labels List of labels to index the matrix.
   * @return new ConfusionMatrix object
   */
  function ConfusionMatrix(&$observed,&$predicted,$labels=null) { 
    $tp = 0;
    $fp = 0; // predicted false, observed positive
    $fn = 0; // predicted true, observed negative (missing result)
    $tn = 0;

    // BEGIN: n-dimensional confusion matrix building.
    // Initialize confusion matrix.
    if( $labels === null ) { 
      $labels = array_unique(array_merge($observed,$predicted));
      sort($labels);
    }
    $ncfm = array_combine($labels, array_fill(0, count($labels), 0));
    $ncfm = array_combine($labels, array_fill(0, count($labels), $ncfm));

    foreach( array_keys($predicted) as $i ) {
      $ncfm[$observed[$i]][$predicted[$i]] += 1;
    }
    $this->ncfm = $ncfm;
    // end: n-dimensional confusion matrix building.

    // BEGIN: binary confusion matrix building.
    // NB: Ideally, this would go away, and we'd only keep the n-dimensional.
    // Unfortunately, the auxiliary functions need updated.
    foreach( array_keys($predicted) as $i ) {
      if( $predicted[$i] != $observed[$i] ) {
        if( $predicted[$i]!=0 ) {
          $fp++;
        } else {
          $fn++;
        }
      } else if( $observed[$i]!=0 ) {
        $tp++;
      } else {
        $tn++;
      }
    }
    $this->cfm = array(array($tp,$fn),array($fp,$tn));
    // end: binary confusion matrix building.

    return $this;
  } // END: constructor ConfusionMatrix


  /**
   * Compute the F1 Score: 2TP/(2TP+FP+FN)
   * F1 Score is the harmonic mean of precision and sensitivity (recall).
   *
   * @link http://en.wikipedia.org/wiki/F1_score
   * @return float
   */
  function f1() { 
    list(list($tp,$fn),list($fp,$tn)) = $this->cfm;
    return 2*$tp/(2*$tp+$fp+$fn);
  } // END: function f1()

  /**
   * Obtain precision metric: PPV = TP/(TP+FP)
   * @return float
   */
  function precision() { 
    list(list($tp,$fn),list($fp,$tn)) = $this->cfm;
    return $tp/($tp+$fp);
  } // END: function precision()

  /**
   * Obtain sensitivity metric (aka recall): TP/(TP+FN)
   *
   * @see recall
   * @return float
   */
  function sensitivity() { 
    list(list($tp,$fn),list($fp,$tn)) = $this->cfm;
    return $tp/($tp+$fn);
  } // END: function sensitivity()

  /**
   * Alias for sensitivity.
   *
   * @see sensitivity
   * @return float
   */
  function recall() { 
    return $this->sensitivity();
  } // END: function recall()

  /**
   * Obtain accuracy metric: ACC = (TP+TN)/(P+N)
   * @return float
   */
  function accuracy() { 
    list(list($tp,$fn),list($fp,$tn)) = $this->cfm;
    return ($tp+$tn)/($tp+$fp+$fn+$tn);
  } // END: function accuracy()

  /**
   * Obtain Matthews correlation coefficient (MCC) of the confusion matrix.
   *
   * @link http://en.wikipedia.org/wiki/Matthews_correlation_coefficient
   * @return float The MCC of a set of predicted vs expected results
   */
  function mcc() { 
    list(list($tp,$fn),list($fp,$tn)) = $this->cfm;
    $den = ($tp+$fp)*($tp+$fn)*($tn+$fp)*($tn+$fn);
    $den = (!$den) ? 1 : $den;
    return (($tp*$tn)-($fp*$fn))/sqrt($den);
  } // END: function mcc()

  /**
   * Obtain the confusion matrix.
   *
   * @param bool $flat If true, returns [TP,FN,FP,TN], else [[TP,FN],[FP,TN]]
   * @param bool $keyed If true, returned arrays are hashes.
   * @return mixed array(array(true-pos,false-neg),array(false-pos,true-neg))
   */
  function get($flat=false,$keyed=false) { 
    if( $flat || $keyed ) { 
      list(list($tp,$fn),list($fp,$tn)) = $this->cfm;
      if( $flat && $keyed ) { 
        return array('TP'=>$tp,'FN'=>$fn,'FP'=>$fp,'TN'=>$tn);
      } else if( $flat ) { 
        return array($tp,$fn,$fp,$tn);
      } else { 
        return array(array('TP'=>$tp,'FN'=>$fn),array('FP'=>$fp,'TN'=>$tn));
      }
    }
    return $this->cfm;
  } // END: function get()


  /**
   * Compute detailed metrics from the confusion matrix
   * returns array(array(true-pos,false-neg),array(false-pos,true-neg))
   *
   * @return array [[tp,fn],[fp,tn]]
   */
  function detailed() {
    list(list($tp,$fn),list($fp,$tn)) = $this->cfm;

    $den = ($tp+$fp)*($tp+$fn)*($tn+$fp)*($tn+$fn);
    $den = (!$den) ? 1 : $den;
    return array(
      'confusionMatrix' => $this->cfm,
      'truePositive' => $tp,
      'falseNegative' => $fn,
      'falsePositive' => $fp,
      'trueNegative' => $tn,
      'sensitivity' => $tp/max(1,$tp+$fn),
      'recall' => $tp/max(1,$tp+$fn),
      'specificity' => $tn/max(1,$fp+$tn),
      'precision' => $tp/max(1,$tp+$fp),
      'negativePredictiveValue' => $tn/max(1,$tn+$fn),
      'fallout' => $fp/max(1,$fp+$tn),
      'falseDiscoveryRate' => $fp/max(1,$fp+$tp),
      'missRate' => $fn/max(1,$fn+$tp),
      'accuracy' => ($tp+$tn)/max(1,$tp+$fp+$fn+$tn),
      'f1Score' => 2*$tp/max(1,2*$tp+$fp+$fn),
      'matthewsCorrelation' => (($tp*$tn)-($fp*$fn))/sqrt(max(1,$den)),
      'informedness' => $tp/max(1,$tp+$fn) + $tn/max(1,$fp+$tn) - 1,
      'markedness' => $tp/max(1,$tp+$fp) + $tn/max(1,$tn+$fn) - 1
    );
  } // END: function detailed()

} // END: class ConfusionMatrix


/*
$obs = array(2, 0, 2, 2, 0, 1);
$exp = array(0, 0, 2, 2, 0, 2);
$cfm = new ConfusionMatrix($obs,$exp);
print_r($cfm);
*/


// EOF -- ConfusionMatrix.php
?>
