<?php
 /**
  * Utility class to calculate times relating to the US Equities Markets
  *
  * @file USMarketCalendar.php
  * @date 2013-07-28 14:43 PDT
  * @author Paul Reuter
  * @version 1.0.2
  *
  * @modifications <pre>
  * 1.0.0 - 2013-02-18 - Created from template: phpclass (incomplete)
  * 1.0.1 - 2013-03-01 - Completed.
  * 1.0.2 - 2013-07-28 - Add: numDaysOpen/numDaysClosed
  * </pre>
  */



/**#@+
 * Defined constants:
 */
// None
/**#@-*/


/**
 * Utility class to calculate times relating to the US Equities Markets
 * @package USMarketCalendar
 */
class USMarketCalendar {
  var $timezone = null;  // user-specified
  var $otimezone = null; // prior to change

  /**
   * Utility class to calculate times relating to the US Equities Markets
   *
   * @public
   * @return new USMarketCalendar object
   */
  function USMarketCalendar() { 
    return $this;
  } // END: constructor USMarketCalendar


  function useDefaultTimezone() { 
    $this->timezone = null;
    return true;
  } // END: function useDefaultTimezone()


  function setTimezone($tz) { 
    $this->timezone = $tz;
    return true;
  } // END: function setTimezone($tz)


  function isHoliday($ts=null) { 
    $this->_tzstart();
    $ts = $this->_time($ts);
    $str = date("Ymd",$ts);
    $holi = $this->getHolidays($ts);
    $this->_tzstop();
    return (isset($holi[$str]));
  } // END: function isHoliday($ts=null)


  function isWeekend($ts=null) { 
    $this->_tzstart();
    $dow = date("w",$this->_time($ts));
    $this->_tzstop();
    return ($dow==0||$dow==6);
  } // END: function isWeekend($ts=null)


  function isMarketDay($ts=null) { 
    return !($this->isWeekend($ts) || $this->isHoliday($ts));
  } // END: function isMarketDay($ts=null)


  function isNormalMarketDay($ts=null) { 
    return ($this->isMarketDay($ts) && !$this->isShortMarketDay($ts));
  } // END: funciton isNormalMarketDay($ts=null)


  function isShortMarketDay($ts=null) { 
    $this->_tzstart();
    $ts = $this->_time($ts);
    $str = date("Ymd",$ts);
    $holi = $this->getShortDays($ts);
    $this->_tzstop();
    return (isset($holi[$str]));
  } // END: function isShortMarketDay($ts=null)


  function isOpen($ts=null) { 
    return !$this->isClosed($ts);
  } // END: function isOpen($ts=null)


  function isClosed($ts=null) { 
    if( !$this->isMarketDay($ts) ) { 
      return true;
    }
    $this->_tzstart();
    $ts = $this->_time($ts);
    if( $this->_time('09:30',$ts) > $ts ) { 
      $this->_tzstop();
      return true;
    }
    if( $this->_time('16:00',$ts) <= $ts
    ||  ( $this->_time('13:00',$ts) <= $ts && $this->isShortMarketDay($ts) )
    ) { 
      $this->_tzstop();
      return true;
    }
    $this->_tzstop();
    return false;
  } // END: function isClosed($ts=null)


  function numDaysOpen($t0,$t1=null) { 
    $this->_tzstart();
    $t1 = ($t1===null) ? time() : $t1;
    $t0 = $this->_time('16:00',$t0);
    $t1 = $this->_time('17:00',$t1);
    $numDays = 0;
    while( $t0<$t1 ) { 
      if( $this->isMarketDay($t0) ) {
        $numDays += 1;
      }
      $t0 += 86400;
    }
    $this->_tzstop();
    return $numDays;
  } // END: function numDaysOpen($t0,$t1=null)


  function numDaysClosed($t0,$t1=null) { 
    $this->_tzstart();
    $t1 = ($t1===null) ? time() : $t1;
    $t0 = $this->_time('16:00',$t0);
    $t1 = $this->_time('17:00',$t1);
    $numDays = 0;
    while( $t0<$t1 ) { 
      if( !$this->isMarketDay($t0) ) {
        $numDays += 1;
      }
      $t0 += 86400;
    }
    $this->_tzstop();
    return $numDays;
  } // END: function numDaysClosed($t0,$t1=null)


  /**
   * Return the timestamp of a market open.
   *
   * @param int $ts Relative time to calculate market open from.
   * @param int $rel Indicates which market day to use.
   *    If N > 0: Get the Nth next market open.
   *    If N < 0: Get the Nth last market open.
   *    If N == 0: Get today's market open, or prev if not market day.
   * @return int epoch timestamp of market open.
   *
   * @see getClose
   */
  function getOpen($ts=null,$rel=0) { 
    $this->_tzstart();
    // Find start of market day boundary
    $thr = $this->_time('09:30',$ts);
    // Find last time market opened.
    while( !$this->isMarketDay($thr) ) { 
      $thr = $this->_time('-1 day',$thr);
    }

    $ts = $this->_time($ts);
    // if we want prior opens and market opened today, count today.
    if( $rel < 0 && $thr < $ts ) { 
      $rel++;
    }
    // if we want future opens and market hasn't opened today, count today.
    if( $rel > 0 && $thr > $ts ) { 
      $rel--;
    }
    // start with known market open.
    $ts = $thr;
    // count back $rel number of market openings.
    while( $rel < 0 ) { 
      $ts = $this->_time('-1 day',$ts);
      if( $this->isMarketDay($ts) ) { 
        $rel++;
      }
    }
    // count forward $rel number of market openings.
    while( $rel > 0 ) { 
      $ts = $this->_time('+1 day',$ts);
      if( $this->isMarketDay($ts) ) { 
        $rel--;
      }
    }
    $this->_tzstop();
    return $ts;
  } // END: function getOpen($ts=null,$rel=0)


  function getClose($ts=null,$rel=0) { 
    $this->_tzstart();
    // Find end of market day boundary
    $thr = $this->_time('16:00',$ts);

    // Find last time market opened.
    while( !$this->isMarketDay($thr) ) {
      $thr = $this->_time('-1 day',$thr);
    }

    $ts = $this->_time($ts);
    // If the market has already closed today and is short day,
    // fake the timestamp to be after normal market close.
    if( $this->isShortMarketDay($ts) && $ts >= $this->_time('13:00',$ts) ) { 
      $ts = $thr;
    }

    // if we want prior closes and market closed today, count today.
    if( $rel < 0 && $thr < $ts ) {
      $rel++;
    }
    // if we want future opens and market hasn't closed today, count today.
    if( $rel > 0 && $thr > $ts ) {
      $rel--;
    }
    // start with known market day.
    $ts = $thr;
    // count back $rel number of market days.
    while( $rel < 0 ) {
      $ts = $this->_time('-1 day',$ts);
      if( $this->isMarketDay($ts) ) {
        $rel++;
      }
    }
    // count forward $rel number of market days.
    while( $rel > 0 ) {
      $ts = $this->_time('+1 day',$ts);
      if( $this->isMarketDay($ts) ) {
        $rel--;
      }
    }

    // Adjust clock to appropriate close time.
    if( $this->isShortMarketDay($ts) ) { 
      $ts = $this->_time('13:00',$ts);
    }
    $this->_tzstop();

    return $ts;
  } // END: function getClose($ts=null,$rel=0)


  function getShortDays($ts=null) { 
    $this->_tzstart();
    $ts = $this->_time($ts);
    $yr = date("Y",$ts);
    if( isset($this->_sholi[$yr]) ) { 
      $this->_tzstop();
      return $this->_sholi[$yr];
    }
    $days = array();

    // Independence Day Eve (July 3)
    $tmp = strtotime("July 3, $yr");
    $dow = date("w",$tmp);
    if( 6-$dow > 0 ) { 
      $days[date("Ymd",$tmp)] = "Independence";
    }

    // Day after Thanksgiving
    $tmp = strtotime("November 1, $yr",$ts);
    $tmp = strtotime("+3 weeks",strtotime("Thursday",$tmp));
    $days[date("Ymd",$tmp+86400)] = "Thanksgiving";

    // Christmas Eve (Dec 24)
    $tmp = strtotime("December 24",$ts);
    $dow = date("w",$tmp);
    if( 6-$dow > 0 ) { 
      $days[date("Ymd",$tmp)] = "Christmas";
    }

    $this->_tzstop();
    $this->_sholi[$yr] = $days;
    return $days;
  } // END: function getShortDays($ts=null)


  function getHolidays($ts=null) { 
    $this->_tzstart();
    $ts = $this->_time($ts);
    $yr = date("Y",$ts);
    if( isset($this->_holi[$yr]) ) { 
      $this->_tzstop();
      return $this->_holi[$yr];
    }
    $days = array();

    // New Year's Day:
    $tmp = strtotime("January 1, $yr");
    if( date("w",$tmp)==0 ) { 
      // Observe on monday if sunday was holiday.
      $tmp+=86400;
    }
    $days[date("Ymd",$tmp)] = "New Year's";

    // Martin Luther King, Jr.
    // 3rd Monday in January
    $tmp = strtotime("January 1, $yr");
    $tmp = strtotime("+2 weeks",strtotime("Monday",$tmp));
    $days[date("Ymd",$tmp)] = "Martin Luther King, Jr.";

    // Washington's birthday is Feb 22
    // President's day is the 3rd Monday in Feb.
    // We observe President's day rules.
    $tmp = strtotime("February 1, $yr",$ts);
    $tmp = strtotime("+2 weeks",strtotime("Monday",$tmp));
    $days[date("Ymd",$tmp)] = "Presidents'";

    // Good Friday (non-federal)
    $tmp = strtotime("-2 days",easter_date($yr));
    $days[date("Ymd",$tmp)] = "Good Friday";

    // Memorial Day (federal)
    // Last Monday of May
    $tmp = strtotime("June 1, $yr",$ts);
    $tmp = strtotime("-1 week",strtotime("Monday",$tmp));
    $days[date("Ymd",$tmp)] = "Memorial";

    // Independence Day (federal)
    // July 4th
    $tmp = strtotime("July 4, $yr");
    $dow = date("w",$tmp);
    if( $dow == 0 ) { 
      $tmp += 86400;
    } else if( $dow == 6 ) { 
      $tmp -= 86400;
    }
    $days[date("Ymd",$tmp)] = "Independence";

    // Labor Day (federal)
    // First monday of September
    $tmp = strtotime("September 1, $yr",$ts);
    $tmp = strtotime("Monday",$tmp);
    $days[date("Ymd",$tmp)] = "Labor";

    // Thanksgiving Day
    // 4th Thursday in November
    $tmp = strtotime("November 1, $yr",$ts);
    $tmp = strtotime("+3 weeks",strtotime("Thursday",$tmp));
    $days[date("Ymd",$tmp)] = "Thanksgiving";

    // Christmas
    // December 25
    $tmp = strtotime("December 25",$ts);
    $dow = date("w",$tmp);
    if( $dow == 0 ) { 
      $tmp += 86400;
    } else if( $dow == 6 ) { 
      $tmp -= 86400;
    }
    $days[date("Ymd",$tmp)] = "Christmas";

    $this->_tzstop();
    $this->_holi[$yr] = $days;
    return $days;
  } // END: function getHolidays($ts=null)


  function _tzstart() { 
    if( $this->timezone ) { 
      if( function_exists('date_default_timezone_get') ) {
        $otimezone = date_default_timezone_get();
        if( $otimezone != $this->timezone ) { 
          date_default_timezone_set($this->timezone);
          $this->otimezone = $otimezone;
        }
      } else {
        $otimezone = getenv("TZ");
        if( $otimezone != $this->timezone ) { 
          putenv("TZ=".$this->timezone);
          $this->otimezone = $otimezone;
        }
      }
    }
    return true;
  } // END: function _tzstart()


  function _tzstop() { 
    if( $this->otimezone ) { 
      if( function_exists('date_default_timezone_set') ) {
        date_default_timezone_set($this->otimezone);
      } else {
        setenv("TZ=".$this->otimezone);
      }
      $this->otimezone = null;
    }
    return true;
  } // END: function _tzstop()


  function _time($ts=null,$ref=null) { 
    if( $ts === null ) {
      return time();
    } else if( (string)intVal($ts) === trim((string)$ts) ) { 
      return intVal($ts);
    }
    return strtotime($ts,self::_time($ref));
  } // END: function _time($ts=null,$ref=null)


} // END: class USMarketCalendar


// EOF -- USMarketCalendar.php
?>
