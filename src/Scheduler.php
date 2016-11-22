<?php
 /**
  * Process events, allowing for execution of parallel processes.
  *
  * @file Scheduler.php
  * @date 2010-09-28 13:15 HST
  * @author Paul Reuter
  * @version 1.0.2
  *
  * @modifications <pre>
  * 1.0.0 - 2010-09-27 - File created, untested.
  * 1.0.1 - 2010-09-28 - Tested on Ubuntu 10.4
  * 1.0.2 - 2010-09-29 - Add: init() to free memory; shutdown child process.
  * </pre>
  */

/**
 * @package System
 */
class Scheduler {
  var $procs = array(); // callbacks to call in order
  var $args  = array(); // arguments to pass to callback, tied to procs
  var $pids  = array(); // active child processes, array of pids
  var $numActive   = 0; // same as count($this->pids)
  var $numParallel = 1; // Limit number of parallel processes to this.


  /**
   * Create a parallel scheduler object.  The parameter indicates the number
   * of simultanious processes to allow.
   *
   * @param uint $numParallel Number of parallel processes allowed; >= 1.
   * @return Scheduler|false A new Scheduler or false on error.
   */
  function Scheduler($numParallel=1) {
    if( (string)$numParallel !== (string)intVal($numParallel) ) { 
      trigger_error("Parameter must be an integer.",E_USER_ERROR);
      return false;
    }
    if( $numParallel < 1 ) { 
      trigger_error("Parameter must be >= 1.",E_USER_WARNING);
      $numParallel = 1;
    }
    $this->numParallel = $numParallel;
    register_shutdown_function(array($this,'cleanup'));
    return $this;
  } // END: constructor Scheduler($numParallel=1)


  /**
   * Initialize a Scheduler object.
   *
   * Used in child processes to clear active and future processes.  
   * Helpful to conserve memory and avoid shutdown confusion caused when
   * there are no pending processes in the parent. The parent must issue
   * a pcntl_waitpid($pid,$status) on all presently running processes to
   * syncronize and signal proper termination.
   */
  function init() { 
    $this->procs = array();
    $this->args  = array();
    $this->pids  = array();
    $this->numActive   = 0;
    $this->numParallel = 1;
    return true;
  } // END: function init()


  /**
   * Change the number of parallel processes to allow.
   *
   * @param uint $numParallel Number of parallel processes allowed; >= 1.
   * @return bool true on success, false on error.
   */
  function setProcLimit($numParallel) { 
      if( (string)$numParallel !== (string)intVal($numParallel) ) { 
      trigger_error("Parameter must be an integer.",E_USER_NOTICE);
      return false;
    }
    if( $numParallel < 1 ) { 
      trigger_error("Parameter must be >= 1.",E_USER_NOTICE);
      return false;
    }
    $this->numParallel = $numParallel;
    return true;
  } // END: function setProcLimit($numParallel)


  /**
   * Append a process callback to end of process queue.
   *
   * @func bool append($cb[, $arg1[, $arg2...]]);
   * @param callback $cb A process to call when ready.
   * @param mixed $arg Zero or more arguments to pass to the callback.
   * @return bool success or failure
   */
  function append() {
    $args = func_get_args();
    $cb = array_shift($args);
    if( !is_callable($cb) ) {
      return false;
    }
    $this->procs[] = $cb;
    $this->args[] = $args;

    $this->dispatch();
    return true;
  } // END: function append()


  /**
   * Prepend a process callback to the front of process queue.
   *
   * @func bool prepend($cb[, $arg1[, $arg2...]]);
   * @param callback $cb A process to call when ready.
   * @param mixed $arg Zero or more arguments to pass to the callback.
   * @return bool success or failure
   */
  function prepend() {
    $args = func_get_args();
    $cb = array_shift($args);
    if( !is_callable($cb) ) {
      return false;
    }
    array_unshift($this->procs,$cb);
    array_unshift($this->args,$args);

    $this->dispatch();
    return true;
  } // END: function prepend()


  /**
   * Attempts to run the process at the front of the queue, provided that
   * starting the next process would not cause more than $numParallel
   * processes to be active.
   *
   * @return bool true if process was started, false otherwise
   */
  function dispatch() {
    if( count($this->procs) < 1 ) { 
    // nothing to do.
      return false;
    }

    if( $this->numActive >= $this->numParallel ) { 
      // Harvest any terminated processes, or wait for one.
      $pid = pcntl_wait($status);
      if( $pid <= 0 ) { 
        trigger_error("Couldn't wait for child process.",E_USER_ERROR);
        return false;
      }
      if( !pcntl_wifexited($status) ) { 
        trigger_error(
          "Abnormal child termination: (pid=$pid, code=".
            pcntl_wexitstatus($status).")",
          E_USER_NOTICE
        );
      }
      unset($this->pids[$pid]);
      $this->numActive -= 1;
    }

    $cb = array_shift($this->procs);
    $args = array_shift($this->args);
    $this->numActive += 1;
    $pid = pcntl_fork();

    if( $pid < 0 ) { 
      trigger_error("Couldn't fork new process.", E_USER_ERROR);
      exit(0);
    } else if( $pid===0 ) { 
    // in the child
      $this->init();
      call_user_func_array($cb,$args);
      exit(0);
    } else { 
    // in the parent
      $this->pids[$pid] = true;
      return true;
    }
    error_log("Unreachable statement.");
    return false;
  } // END: function dispatch()


  function cleanup() { 
    foreach( array_keys($this->pids) as $pid ) { 
      pcntl_waitpid($pid,$status);
    }
    return true;
  } // END: function cleanup()

} // END: class Scheduler


// EOF -- Scheduler.php
?>
