<?php
 /**
  * Register a function to execute after a certain timeout, or at a timestamp.
  *
  * @file Alarm.php
  * @date 2014-07-17 13:31 PDT
  * @author Paul Reuter
  * @version 1.0.4
  *
  * @modifications <pre>
  * 1.0.0 - 2013-05-21 - Created from template: phpclass
  * 1.0.1 - 2013-05-21 - Completed as list of time stamps.
  * 1.0.2 - 2013-05-22 - Add: comments, autodoc.
  * 1.0.3 - 2014-07-10 - More autodoc.
  * 1.0.4 - 2014-07-17 - Add: cancel_before(ts), cancel_after(ts)
  * </pre>
  */



/**
 * Register a function to execute after a certain timeout, or at a timestamp.
 * @package Alarm
 */
class Alarm {
  /**
   * Array of timestamps after which we trigger an alarm.
   * @access private
   */
  var $a_times = array();
  /**
   * Array of callback arguments to apply when alarm signals.
   * @access private
   */
  var $a_params = array();

  /**
   * Register a function to execute after a certain timeout, or at a timestamp.
   *
   * @access public
   * @return new Alarm object
   */
  function Alarm() { 
    declare(ticks=1);
    pcntl_signal(SIGALRM,array($this,'trigger'),false);
    return $this;
  } // END: function Alarm()


  /**
   * Create an alarm after certain amount of time (seconds).
   * function set_delay($delay,$callback=null,$arg0...)
   *
   * @access public
   * @param int $delay Number of seconds to wait before alarm triggers.
   * @param callback $callback A function to execute when alarm triggers.
   * @param mixed $arg0 One or more optional arguments.
   * @return bool true if created, false otherwise.
   */
  function set_delay($delay,$callback=null,$arg0=null) { 
    $timestamp = time() + max(0,intVal($delay));
    if( !is_callable($callback) ) { 
      $callback = array($this,'default_callback');
    }
    $args = array_slice(func_get_args(),2);
    return $this->insert($timestamp,array($callback,$args));
  } // END: function set_delay($delay,$callback)


  /**
   * Create an alarm at a specified time (epoch timestamp seconds).
   * function set_timestamp($timestamp,$callback=null,$arg0...)
   *
   * @access public
   * @param int $timestamp Epoch timestamp. Num sec since 1970-01-01.
   * @param callback $callback A function to execute when alarm triggers.
   * @param mixed $arg0 One or more optional arguments.
   * @return bool true if created, false otherwise.
   */
  function set_timestamp($timestamp,$callback=null,$arg0=null) { 
    if( !is_callable($callback) ) { 
      $callback = array($this,'default_callback');
    }
    $args = array_slice(func_get_args(),2);
    return $this->insert($timestamp,array($callback,$args));
  } // END: function set_timestamp($timestamp,$callback=null)


  /**
   * Clears all active alarms.
   *
   * @access public
   * @return bool always true
   */
  function clear() { 
    pcntl_alarm(0);
    $this->a_times = array();
    $this->a_params = array();
    return true;
  } // END: function clear()


  /**
   * Clears all active alarms that would trigger on or before $ts timestamp.
   *
   * @see clear
   * @see cancel_after
   * @access public
   * @param int $ts Epoch timestamp - all alarms before then will be cancelled.
   * @return uint number of signals cancelled.
   */
  function cancel_before($ts) { 
    pcntl_alarm(0);
    $nc = 0;
    while( !empty($this->a_times) ) { 
      if( current($this->a_times) > $ts ) { 
        break;
      }
      array_shift($this->a_times);
      array_shift($this->a_params);
      $nc++;
    }
    $this->updated();
    return $nc;
  } // END: function cancel_before($ts)

  /**
   * Clears all alerts that would trigger on or after $ts timestamp.
   *
   * @see clear
   * @see cancel_after
   * @return uint number of signals cancelled.
   */
  function cancel_after($ts) {
    pcntl_alarm(0);
    $nc = 0;
    $i = count($this->a_times)-1;
    while( $i>=0 ) { 
      if( $this->a_times[$i] < $ts ) { 
        break;
      }
      array_pop($this->a_times);
      array_pop($this->a_params);
      $nc++;
    }
    $this->updated();
    return $nc;
  } // END: function cancel_after($ts)


  /**
   * Echos message when alarm is triggered. (default callback)
   *
   * @access private
   * @see set_delay
   * @see set_timestamp
   * @return void
   */
  function default_callback() { 
    echo("SIGALRM\n");
  } // END: function default_callback()


  /**
   * Create an alarm at timestamp with callback.
   *
   * @access private
   * @param int $timestamp Epoch timestamp when we should trigger.
   * @param mixed $params Array of params to call user callback with.
   */
  function insert($timestamp,$params) { 
    $ix = $this->bsearchR($this->a_times,$timestamp)+1;
    array_splice($this->a_times,$ix,0,array($timestamp));
    array_splice($this->a_params,$ix,0,array($params));
    $this->updated();
    return true;
  } // END: function insert($delay,$params)


  /**
   * Remove a pending alarm from the list.
   *
   * @access private
   * @param int $timestamp Epoch timestamp when we should trigger.
   * @param mixed $params Array of params to call user callback with.
   * @return bool true if found and removed, false otherwise.
   */
  function remove($timestamp,$params) {
    $ix = max(0,$this->bsearchR($this->a_times,$timestamp));
    while( $ix>=0 && $this->a_times[$ix]===$timestamp ) { 
      if( $this->a_params[$ix] === $params ) { 
        array_splice($this->a_times,$ix,1);
        array_splice($this->a_params,$ix,1);
        $this->updated();
        return true;
      }
      $ix--;
    }
    return false;
  } // END: function remove($timestamp,$params)


  /**
   * Detect if alarm should signal.
   * Function executed after each processing unit of time.
   *
   * @access private
   * @return bool always true
   */
  function updated() { 
    if( empty($this->a_times) ) { 
      pcntl_alarm(0);
    } else { 
      $delay = current($this->a_times) - time();
      if( $delay>0 ) { 
        pcntl_alarm($delay);
      } else { 
        $this->trigger();
      }
    }
    return true;
  } // END: function updated()


  /**
   * Method to dispatch user's callback with arguments.
   *
   * @access private
   * @return bool always true
   */
  function trigger() { 
    while( !empty($this->a_times) && time() >= current($this->a_times) ) { 
      array_shift($this->a_times);
      list($callback,$params) = array_shift($this->a_params);
      if( empty($params) ) { 
        call_user_func($callback);
      } else { 
        call_user_func_array($callback,$params);
      }
      $this->updated();
    }
    return true;
  } // END: function trigger()


  /**
   * Return index of cell that matches or preceeds $needle
   *
   * @access private
   * @param array &$haystack An array of items to compare to.
   * @param mixed $needle Something to look for (<> comparable)
   * @param int $lo Internal min index to comapre.
   * @param int $hi Internal max index to comapre.
   * @return int The index of the item <= $needle; -1 if item[0] > needle
   */
  function bsearch(&$haystack,$needle,$lo=0,$hi=null) {
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
      return self::bsearch($haystack,$needle,$lo,$mid-1);
    }
    if ($needle > $haystack[$mid]) {
      return self::bsearch($haystack,$needle,$mid,$hi);
    }
    return $mid;
  } // END: function bsearch(&$haystack,$needle,$i=0,$n=null)


  /**
   * Return right-most index of a cell that matches or preceeds $needle.
   * Note that this turns bsearch into an O(n) worst-case search.
   *
   * @access private
   * @see bsearch
   * @return int The right-most index of $haystack matching $needle.
   */
  function bsearchR(&$haystack,$needle,$lo=0,$hi=null) {
    $ix = self::bsearch($haystack,$needle,$lo,$hi);
    $nk = count($haystack) - 1;
    while ($ix<$nk && $haystack[$ix+1]===$needle) {
      $ix += 1;
    }
    return $ix;
  } // END: function bsearchR(&$haystack,$needle,$i=0,$n=null)


} // END: class Alarm


// EOF -- Alarm.php
?>
