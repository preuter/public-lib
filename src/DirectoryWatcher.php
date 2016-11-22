<?php
/**
 * Watch a directory and trigger a function on modified/created files.
 *
 * @file DirectoryWatcher.php
 * @date 2014-06-19 14:06 PDT
 * @author Paul Reuter
 * @version 1.0.3
 *
 * @modifications <pre>
 * 1.0.0 - 2014-06-12 - Created
 * 1.0.1 - 2014-06-12 - Add: setDuration, setInterval, setMinAge
 * 1.0.2 - 2014-06-12 - Add: get/setInitialModTime : for if mod since
 * 1.0.3 - 2014-06-19 - BugFix: don't automatically update minModTime in watch loop.
 * </pre>
 */


/**
 * Watch a directory and trigger a function on modified/created files.
 * Scan every X seconds.
 */
class DirectoryWatcher {
  /**
   * @public
   */
  var $VERBOSE = false;

  /**
   * @protected
   */
  var $dpath = null;
  var $interval = 5;       // Check every x seconds: full directory scan
  var $minAge = 0;         // Not modified at least this long.
  var $ifModSince = false; // min modtime for first loop through watch.
  var $recursive = true;   // look for modifications recursively
  var $canonical = true;   // expand paths for callbacks.
  var $duration = 0; // seconds into future to watch. LTE 0 implies no expire.

  /**
   * @private
   */
  var $_dirTrigger;
  var $_fileTrigger;


  /**
   * Create a directory watcher. Initialize with directory path to watch.
   *
   * @param string $dpath Directory path to watch.
   * @return DirectoryWatcher
   */
  function DirectoryWatcher($dpath=null) { 
    $this->dpath = $dpath;
    return true;
  } // END: constructor DirectoryWatcher($dpath=null)


  /**
   * Watching daemon loop. Enter watch mode.
   * Watch-mode expires after duration (setDuration) or on modify (setTrigger)
   *
   * @see setDuration
   * @see setTrigger
   * @param string $dpath (optional) Watch this path rather than the default.
   * @return array|bool array of modified files or boolean if expires.
   */
  function watch($dpath=null) {
    $haveTrigger = ($this->_dirTrigger || $this->_fileTrigger);
    $dpath = ($dpath===null) ? $this->dpath : $dpath;
    $maxModTime = time() - $this->minAge;
    $minModTime = $this->getInitialModTime();
    $expireAt = ($this->duration>0) ? time() + $this->duration : -1;

    while(1) {
      $t0 = time();
      if( $expireAt > 0 && $t0 >= $expireAt ) {
        if( $this->VERBOSE ) { echo("Watch expired.\n"); }
        return true;
      }

      if( $this->VERBOSE ) { echo("Scanning `$dpath`\n"); }
      $maxModTime = $t0 - $this->minAge;
      list($files,$mdirs) = $this->_dirscanm($dpath,$minModTime,$maxModTime);

      if( !$haveTrigger && !empty($files) ) {
        if( $this->VERBOSE ) { echo("A file was modified (no triggers).\n"); }
        return $files;
      }

      if( $this->_dirTrigger ) { 
        if( $this->canonical ) { 
          foreach( array_keys($mdirs) as $i ) { 
            $mdirs[$i] = realpath($mdirs[$i]);
          }
        }
        foreach( $mdirs as $fpath ) {            
          if( $this->VERBOSE ) { echo("Directory Modified: $fpath\n"); }
          call_user_func($this->_dirTrigger,$fpath);
        }
      }

      if( $this->_fileTrigger ) { 
        if( $this->canonical ) { 
          foreach( array_keys($files) as $i ) { 
            $files[$i] = realpath($files[$i]);
          }
        }
        foreach( $files as $fpath ) {
          if( $this->VERBOSE ) { echo("File Modified: $fpath\n"); }
          call_user_func($this->_fileTrigger,$fpath);
        }
      }

      // Update minModTime filter using files returned.
      // Avoid gaps with preserved file times during bulk copy.
      foreach($files as $fpath) { 
        $minModTime = max($minModTime,filemtime($fpath)+1);
      }
      foreach($mdirs as $fpath) { 
        $minModTime = max($minModTime,filemtime($fpath)+1);
      }

      if( $this->VERBOSE ) { echo("Sleep ".$this->interval.".\n"); }
      sleep($this->interval);
    }
    return false;
  } // END: function watch($dpath)


  /**
   * Executes a callback accepting directory path that was modified.
   *
   * @param callback $cb Callback accepting $dpath when directory is modified.
   * @return bool true if callable.
   */
  function setDirectoryTrigger($cb) { 
    $this->_dirTrigger = $cb;
    return (is_callable($cb));
  } // END: function setDirectoryTrigger($cb)

  
  /**
   * Executes a callback accepting file path that was modified.
   *
   * @param callback $cb Callback accepting $fpath when file is modified.
   * @return bool true if callable.
   */
  function setFileTrigger($cb) { 
    $this->_fileTrigger = $cb;
    return (is_callable($cb));
  } // END: function setFileTrigger($cb)


  /**
   * Shorthand to set trigger on file and/or on directory.
   *
   * @param callback $f_cb Method to execute when file is modified.
   * @param callback $d_cb Method to execute when directory is modified.
   * @return bool true if all callable, false if any non-callable.
   * @see setFileTrigger
   * @see setDirectoryTrigger
   */
  function setTrigger($f_cb,$d_cb=null) {
    $b = true;
    if( is_callable($f_cb) ) { 
      $b = $b && $this->setFileTrigger($f_cb);
    }
    if( is_callable($d_cb) ) { 
      $b = $b && $this->setDirectoryTrigger($d_cb);
    }
    return $b;
  } // END: function setTrigger($f_cb,$d_cb=null)


  /**
   * Whether modified files/directories for triggers are full or relative path.
   * 
   * @param bool $b Whether to expand paths for callbacks. true by default.
   * @return bool always true.
   */
  function useCanonical($b=true) { 
    $this->canonical = ($b) ? true : false;
    return true;
  } // END: function useCanonical($b=true)


  /**
   * Whether to search for modified files in all sub-directories.
   *
   * @param bool $b Whether to expand paths for callbacks. true by default.
   * @return bool always true.
   */
  function setRecursive($b=true) { 
    $this->recursive = ($b) ? true : false;
    return true;
  } // END: function setRecursive($b=true)


  /**
   * Set how long to watch a directory for. Default is to not expire.
   *
   * @param int $timeout_sec Seconds to expiration. num <= 0 imply no expiry.
   * @return bool always true
   */
  function setDuration($timeout_sec) { 
    $this->duration = intVal($timeout_sec);
    return true;
  } // END: function setDuration($timeout_sec)


  /**
   * Set how often (seconds) we rescan the directory being watched.
   *
   * @param int $interval_sec Seconds between scans.
   * @return bool true if interval > 0, false otherwise.
   */
  function setInterval($interval_sec) { 
    $this->interval = max(1,intVal($interval_sec));
    return ($interval_sec>0);
  } // END: function setInterval($interval_sec)


  /**
   * Avoid triggering on open files. Set seconds since last modified.
   *
   * @param int $age_sec Seconds since writing stopped before triggering.
   * @return bool true if age >= 0, false otherwise.
   */
  function setMinAge($age_sec) { 
    $this->minAge = max(0,intVal($age_sec));
    return ($age_sec>=0);
  } // END: function setMinAge($age_sec)


  /**
   * Find files modified since this time upon startup.
   *
   * @param int $ts Epoch timestamp for files modified since.
   * @return bool always true.
   */
  function setInitialModTime($ts) { 
    $this->ifModSince = intVal($ts);
    return true;
  } // END: function setInitialModTime($ts)


  /**
   * Return start minModTime for watch() method.
   *
   * @protected
   * @return int minModTime for first pass through watch.
   */
  function getInitialModTime() {
    if( $this->ifModSince!==false ) { 
      return $this->ifModSince;
    }
    return time() - $this->minAge - $this->interval;
  } // END: function getInitialModTime()


  /**
   * Scan $dpath, looking for files modified between min/max time.
   *
   * @param string $dpath path to scan for files/directories.
   * @param int $mintime epoch timestamp for files modified since (GTE).
   * @param int $maxtime epoch timestamp for files modified since (LT).
   * @param int $sort ASC or DESC. int<0 implies DESC. ASC otherwise.
   * @return tuple (array of mod_files,array of mod_dirs)
   */
  function _dirscanm($dpath,$mintime,$maxtime=null,$sort=+1) {
    $maxtime = ($maxtime===null) ? time()-max(0,$this->minAge) : $maxtime;
    $recursive = $this->recursive;
    $dirs = array($dpath);
    $files = array();
    $mdirs = array();

    if( is_dir($dpath) && ($tm=filemtime($dpath))>=$mintime && $tm<$maxtime ) { 
      $mdirs[] = $dpath;
    }

    while( !empty($dirs) ) {
      $dpath = array_shift($dirs);

      $dp = @opendir($dpath);
      if( !$dp ) {
        continue;
      }
      $sdirs = array();
      while( ($fname=readdir($dp)) !== false ) {
        if( $fname=='.' || $fname=='..' ) {
          continue;
        }
        $fpath = $dpath.DIRECTORY_SEPARATOR.$fname;
        if( is_link($fpath) ) {
          // Skip links
        } else if( is_file($fpath) ) {
          if( ($tm=filemtime($fpath))>=$mintime && $tm<$maxtime ) { 
            $files[] = $fpath;
          }
        } else if( is_dir($fpath) ) { 
          if( ($tm=filemtime($fpath))>=$mintime && $tm<$maxtime ) { 
            $mdirs[] = $fpath;
          }
          if($recursive) {
            $sdirs[] = $fpath;
          }
        }
      }
      @closedir($dp);

      if( $recursive ) {
        if( $sort<0 ) {
          rsort($sdirs);
        } else {
          sort($sdirs);
        }
        array_splice($dirs,0,0,$sdirs);
      }
    } // while not empty dirs

    if( $sort<0 ) {
      rsort($mdirs);
      rsort($files);
    } else {
      sort($mdirs);
      sort($files);
    }
    return array($files,$mdirs);
  } // END: function _dirscanm($dpath,$mintime,$maxtime=null,$sort=+1)


} // END: class DirectoryWatcher


// EOF -- DirectoryWatcher.php
?>
