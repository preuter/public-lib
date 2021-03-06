<?php
 /**
  * Parse an AIS message into a hash.
  * TODO: implement DAC+FID lookup tables for binary data.
  *
  * @file AIS.php
  * @date 2014-06-24 21:26 PDT
  * @author Paul Reuter
  * @version 1.0.5
  *
  * @modifications <pre>
  * 1.0.0 - 2014-06-13 - Created from template: phpclass
  * 1.0.1 - 2014-06-17 - First fully functional release.
  * 1.0.2 - 2014-06-18 - Add: decode_27 and normalized fields.
  * 1.0.3 - 2014-06-18 - BugFix: hasCoordinate/getCoordinate
  * 1.0.4 - 2014-06-19 - BugFix: return this->decode_1
  * 1.0.5 - 2014-06-24 - Modify: getCoordinate returns float values.
  * </pre>
  */



/**#@+
 * Defined constants:
 */
// None
/**#@-*/


/**
 * Parse an AIS message into a hash.
 * @package AIS
 */
class AIS {
  /**
   * @access private
   */
  var $_cb_oncomplete = null;
  /**
   * @access private
   */
  var $_cb_onerror = null;
  /**
   * @access private
   */
  var $_pending = array();


  /**
   * Parse an AIS message into a hash.
   *
   * @access public
   * @return new AIS object
   */
  function AIS() {
    return $this;
  } // END: constructor AIS


  /**
   * @access public
   * @param string $message Raw AIS message !AIVDM,1,1,,blah,0*42
   */
  function receive($message) {
    $nmsg = $this->parse($message);

    if( !$nmsg || $nmsg->isError() ) {
      if( $this->_cb_onerror ) {
        $errmsg = (!$nmsg) ? "Unable to parse." : $nmsg->error;
        call_user_func_array($this->_cb_onerror,array($message,$errmsg));
      }
      return false;
    }

    $pid = $this->getPendingId($nmsg);

    // Test: is this a fresh, new message
    if( $nmsg->isFirst() ) { 
      // Yes (fresh): Reset message parser state.

      // Test: was the previous message incomplete?
      if( isset($this->_pending[$pid]) ) { 
        // yes (was incomplete)
        $omsg = $this->_pending[$pid];
        if( $this->_cb_onerror ) { 
          $err = ($omsg->error) ? $omsg->error : "Unterminated message.";
          call_user_func_array($this->_cb_onerror,array($omsg->message,$err));
        }
        unset($this->_pending[$pid]);
      }
      // Ok, we start fresh with this new message.

      // Test: If message not complete, store as pending.
      if( !$nmsg->isComplete() ) { 
        $this->_pending[$pid] = $nmsg;
        return true;
      }

    } else { 
    // No (not fresh).
      // test: was there a message waiting?
      // test: is current message the one we were waiting for?
      if( !isset($this->_pending[$pid])
      ||  !($nmsg=$this->append($nmsg))
      ){
        // no: decode error.
        if( $this->_cb_onerror ) { 
          $err = (!$nmsg || !$nmsg->error)? "Unable to append." : $nmsg->error;
          call_user_func_array($this->_cb_onerror,array($message,$err));
        }
        unset($this->_pending[$pid]);
        return false;
      }
    }

    // Test: after possible append, is the message complete now?
    if( $nmsg->isComplete() ) { 
      unset($this->_pending[$pid]);
      if( !$nmsg->decode() ) { 
        if( $this->_cb_onerror ) { 
          $err = ($nmsg->error) ? $nmsg->error : "Unable to decode.";
          $msg = ($nmsg->message) ? $nmsg->message : $message;
          call_user_func_array($this->_cb_onerror,array($msg,$err));
        }
        return false;
      }
      if( $this->_cb_oncomplete ) {
        call_user_func($this->_cb_oncomplete,$nmsg);
      }
    }
    return true;
  } // END: function receive($message)



  /**
   * Assign callback upon error encountered.
   * function callback(string[] $raw_message, string $error_message)
   *
   * @access public
   * @param function $cb Callback.
   * @return bool true if $cb is a function, false otherwise.
   */
  function onerror($cb) {
    $this->_cb_onerror = $cb;
    return (is_callable($cb));
  } // END: function onerror($cb)


  /**
   * Assign callback upon completion of successful message parsing.
   * function callback(string[] $raw_message, hashmap[] $parsed)
   *
   * @access public
   * @param function $cb Callback.
   * @return bool true if $cb is a function, false otherwise.
   */
  function oncomplete($cb) {
    $this->_cb_oncomplete = $cb;
    return (is_callable($cb));
  } // END: function oncomplete($cb)


  /**
   * Identify a message by an AIS message type.
   *
   * @access public
   * @param uint $ais_type ID Parsed from payload of AIS message type.
   * @return string 1-line description of the AIS message.
   */
  function getMessageTitle($ais_type) {
    switch($ais_type) {
      case  1: return "Position Report (SOTDMA)";
      case  2: return "Position Report (SOTDMA)";
      case  3: return "Position Report (ITDMA)";
      case  4: return "Base Station Report (SOTDMA)";
      case  5: return "Static and Voyage Related Data";
      case  6: return "Binary Addressed Message";
      case  7: return "Binary Acknowledge";
      case  8: return "Binary Broadcast Message";
      case  9: return "Standard SAR Aircraft Position Report";
      case 10: return "UTC/Date Inquiry";
      case 11: return "UTC/Date Response";
      case 12: return "Addressed Safety Related Message";
      case 13: return "Safety Related Acknowledge";
      case 14: return "Safety Related Broadcast Message";
      case 15: return "Interrogation";
      case 16: return "Assigned Mode Command";
      case 17: return "DGNSS Broadcast Binary Message";
      case 18: return "Standard Class B Equipment Position Report";
      case 19: return "Extended Class B Equipment Position Report";
      case 20: return "Data Link Management Message";
      case 21: return "Aids-to-Navigation Report";
      case 22: return "Channel Management";
      case 23: return "Group Assignment Command";
      case 24: return "Static Data Report";
      case 25: return "Single Slot Binary Message";
      case 26: return "Multiple Slot Binary Message with Communications State";
      default: return false;
    }
    return false;
  } // END: function getMessageTitle(ais_type)


  /**
   * @access protected
   */
  function extract_message($str) {
    $pat = '/[\!\$]?[A-Z]{2}VD[MO],[^\*]+\*[0-9A-F]{2}/i';
    if( preg_match($pat,$str,$pts) ) {
      return $pts[0];
    }
    return false;
  } // END: function extract_message($str)


  /**
   * @access protected
   */
  function parse($str) {
    $msg = new AIS_Message($this->extract_message($str));
    // We modify the AIS_Message to track original strings.
    $msg->raw = array($str);
    return $msg;
  } // END: function parse($str)


  /**
   * @access protected
   */
  function append($aism) { 
    $pid = $this->getPendingId($aism);
    if(!isset($this->_pending[$pid]) || !$this->_pending[$pid]->append($aism)){ 
      return false;
    }
    $this->_pending[$pid]->raw[] = current($aism->raw);
    return $this->_pending[$pid];
  } // END: function append($aism)


  /**
   * @access protected
   */
  function getPendingId(&$aism) { 
    return $aism->msgid.'|'.$aism->totno.'|'.$aism->channel.'|'.$aism->seqno;
  } // END: function getPendingId(&$aism)


} // END: class AIS


/**
 * @package AIS
 */
class AIS_Message {
  var $msgid;    // 5-letter NMEA code
  var $totno;    // total number of messages to wait for
  var $msgno;    // current message number (msgno of totno)
  var $seqno;    // a sequence identifier for multi-part messages
  var $channel;  // channel received on (A or B)
  var $payload;  // data (6-bit) encoded
  var $parity;   // this many bits are garbage at the end of the data
  var $checksum; // NMEA checksum

  var $message = null;   // raw message.
  var $hash = array();   // parsed message as key:value
  var $error = null;     // Last error during parsing.
  var $sixbit = null;


  function AIS_Message($message) {
    $this->parse($message);
    return $this;
  } // END: function AIS_Message($message)


  function isAIS() {
    $pat = '/[\!\$]?[A-Z]{2}VD[MO],[^\*]+\*[0-9A-F]{2}/i';
    return (preg_match($pat,$this->message));
  } // END: function isAIS()


  function isFirst() { 
    return ( $this->msgno==1 );
  } // END: function isFirst()


  function isComplete() {
    return ( $this->totno>0 && $this->totno==$this->msgno );
  } // END: function isComplete()


  function isError() {
    return (!empty($this->error));
  } // END: function isError()


  function hasCoordinate() {
    return (isset($this->hash['lat']) && isset($this->hash['lon']));
  } // END: function hasCoordinate()

  function getCoordinate() {
    return array(
      floatVal($this->hash['lon']), floatVal($this->hash['lat'])
    );
  } // END: function getCoordinate()


  function inPolygon(&$poly) {
    if( !$this->hasCoordinate() ) {
      return false;
    }
    list($x,$y) = $this->getCoordinate();

    $j = 0;
    $oddNodes = false;
    for($i=0,$n=count($poly); $i<$n; $i++) {
      $j++;
      if($j==$n) {
        $j = 0;
      }
      if((($poly[$i][1] < $y) && ($poly[$j][1] >= $y))
      || (($poly[$j][1] < $y) && ($poly[$i][1] >= $y))) {
        if ( $poly[$i][0] + ($y - $poly[$i][1])
        /  ($poly[$j][1]-$poly[$i][1])
        *  ($poly[$j][0] - $poly[$i][0]) < $x ) {
          $oddNodes = !$oddNodes;
        }
      }
    }
    return $oddNodes;
  } // END: function inPolygon(&$poly)


  function append($aism) {
    if( $this->seqno!=$aism->seqno
    ||  $this->msgno+1!=$aism->msgno ) {
      return false;
    }
    $this->sixbit->append(new AIS_Sixbit($aism->payload,$aism->parity));
    $this->msgno = $aism->msgno;
    return true;
  } // END: function append($other)



  function parse($message=null) {
    if( $message===null ) {
      $message = $this->message;
    } else {
      if( !$this->initialize($message) ) {
        return false;
      }
    }
    if( !empty($this->hash) ) {
      return true;
    }

    $this->sixbit = new AIS_Sixbit($this->payload,$this->parity);
    return true;
  } // END: function parse()


  function decode() {
    // Determine the AIS sub-message type.
    $ais_type = $this->sixbit->getBits(6);

    if( $ais_type<1 || $ais_type>27 ) {
      $this->error = "AIS Message ID out of range [1,27].";
      return false;
    }

    switch($ais_type) {
      case 1: return $this->decode_1();
      case 2: return $this->decode_2();
      case 3: return $this->decode_3();
      case 4: return $this->decode_4();
      case 5: return $this->decode_5();
      case 6: return $this->decode_6();
      case 7: return $this->decode_7();
      case 8: return $this->decode_8();
      case 9: return $this->decode_9();
      case 10: return $this->decode_10();
      case 11: return $this->decode_11();
      case 12: return $this->decode_12();
      case 13: return $this->decode_13();
      case 14: return $this->decode_14();
      case 15: return $this->decode_15();
      case 16: return $this->decode_16();
      case 17: return $this->decode_17();
      case 18: return $this->decode_18();
      case 19: return $this->decode_19();
      case 20: return $this->decode_20();
      case 21: return $this->decode_21();
      case 22: return $this->decode_22();
      case 23: return $this->decode_23();
      case 24: return $this->decode_24();
      case 25: return $this->decode_25();
      case 26: return $this->decode_26();
      case 27: error_log("TODO: decode_27"); break;
      //case 27: return $this->decode_27();
      default:
        error_log("Unreachable statement.");
        return false;
    }

    return (empty($this->error));
  } // END: function decode()



  function decode_1() {
    // Start from the beginning, no matter where we may have left off.
    $this->sixbit->reset();

    if( $this->sixbit->bitsRemaining() != 168 ) {
      $this->error = "decode_1: Unexpected number of bits remaining.";
      return false;
    }

    // physical message shape, raw bits
    $res = array(
      "type"       => $this->sixbit->getBits(6),
      "repeat"     => $this->sixbit->getBits(2),
      "mmsi"       => $this->sixbit->getBits(30),
      "status"     => $this->sixbit->getBits(4),
      "turn"       => $this->sixbit->getBits(8),
      "speed"      => $this->sixbit->getBits(10),
      "accuracy"   => $this->sixbit->getBits(1),
      "lon"        => $this->sixbit->getBits(28),
      "lat"        => $this->sixbit->getBits(27),
      "course"     => $this->sixbit->getBits(12),
      "heading"    => $this->sixbit->getBits(9),
      "second"     => $this->sixbit->getBits(6),
      "maneuver"   => $this->sixbit->getBits(2),
      "spare"      => $this->sixbit->getBits(3),
      "raim"       => $this->sixbit->getBits(1),
      "radio" => array(
        "sync_state"   => $this->sixbit->getBits(2),
        "slot_timeout" => $this->sixbit->getBits(3),
        "sub_message"  => $this->sixbit->getBits(14)
      )
    );

    // alterations to the values depending on contents

    // rate of turn (deg/minute)
    $res["turn"] = $this->sign_unsigned($res["turn"],8);
    if( $res["turn"] == -128 ) {
      $res["turn"] = null;
    }

    // speed over ground (1/10 knots)
    $res["speed"] = $this->paddedFloat($res["speed"]/10,1,1,'0');

    // longitude (1/10000 minutes == 1/(60*10000) degrees)
    $res["lon"] = $this->sign_unsigned($res["lon"],28) / 600000;
    if( abs($res["lon"]) > 180 ) {
      $res["lon"] = null;
    } else {
      $res["lon"] = $this->paddedFloat($res["lon"], 1, 5, '0');
    }

    // latitude (1/10000 minutes == 1/(60*10000) degrees)
    $res["lat"]  = $this->sign_unsigned($res["lat"],27) / 600000;
    if( abs($res["lat"]) > 90 ) {
      $res["lat"] = null;
    } else {
      $res["lat"]  = $this->paddedFloat($res["lat"], 1, 5, '0');
    }

    // course over ground (1/10 degrees N)
    $res["course"] = $this->paddedFloat($res["course"]/10,1,1,'0');

    // Apply lookups.

    // direction the ship points in (degrees N)
    $res["accuracy"] = $this->_lu_accuracy($res["accuracy"]);
    $res["heading"] = $this->_lu_heading($res["heading"]);
    $res["course"] = $this->_lu_course($res["course"]);
    $res["second"] = $this->_lu_second($res["second"]);
    $res["status"] = $this->_lu_status($res["status"]);
    $res["maneuver"] = $this->_lu_maneuver($res["maneuver"]);
    $res["raim"] = $this->_lu_raim($res["raim"]);
    unset($res["spare"]);

    $this->hash = $res;
    return true;
  } // END: function decode_1()


  function decode_2() {
    return $this->decode_1();
  } // END: function decode_2()


  function decode_3() {
    $this->sixbit->reset();
    if( $this->sixbit->bitsRemaining() != 168 ) {
      $this->error = "decode_3: Unexpected number of bits remaining.";
      return false;
    }

    $res = array(
      'type'     => $this->sixbit->getBits(6),
      'repeat'   => $this->sixbit->getBits(2),
      'mmsi'     => $this->sixbit->getBits(30),
      'status'   => $this->sixbit->getBits(4),
      'turn'     => $this->sixbit->getBits(8),
      'speed'    => $this->sixbit->getBits(10),
      'accuracy' => $this->sixbit->getBits(1),
      'lon'      => $this->sixbit->getBits(28),
      'lat'      => $this->sixbit->getBits(27),
      'course'   => $this->sixbit->getBits(12),
      'heading'  => $this->sixbit->getBits(9),
      'second'   => $this->sixbit->getBits(6),
      'maneuver' => $this->sixbit->getBits(2),
      'spare'    => $this->sixbit->getBits(3),
      'raim'     => $this->sixbit->getBits(1),
      'radio'    => array(
        'sync_state' => $this->sixbit->getBits(2),
        'slot_alloc' => $this->sixbit->getBits(13),
        'num_slots'  => $this->sixbit->getBits(3),
        'keep_flag'  => $this->sixbit->getBits(1)
      )
    );


    // alterations to the values depending on contents

    // rate of turn (deg/minute)
    $res["turn"] = $this->sign_unsigned($res["turn"],8);
    if( $res["turn"] == -128 ) {
      $res["turn"] = null;
    }

    // speed over ground (1/10 knots)
    $res["speed"] = $this->paddedFloat($res["speed"]/10,1,1,'0');

    // longitude (1/10000 minutes == 1/(60*10000) degrees)
    $res["lon"] = $this->sign_unsigned($res["lon"],28) / 600000;
    if( abs($res["lon"]) > 180 ) {
      $res["lon"] = null;
    } else {
      $res["lon"] = $this->paddedFloat($res["lon"], 1, 5, '0');
    }

    // latitude (1/10000 minutes == 1/(60*10000) degrees)
    $res["lat"] = $this->sign_unsigned($res["lat"],27) / 600000;
    if( abs($res["lat"]) > 90 ) {
      $res["lat"] = null;
    } else {
      $res["lat"] = $this->paddedFloat($res["lat"], 1, 5, '0');
    }

    // course over ground (1/10 degrees N)
    $res["course"] = $this->paddedFloat($res["course"]/10,1,1,'0');

    // Apply lookups.

    // direction the ship points in (degrees N)
    $res["accuracy"] = $this->_lu_accuracy($res["accuracy"]);
    $res["heading"]  = $this->_lu_heading($res["heading"]);
    $res["course"]   = $this->_lu_course($res["course"]);
    $res["second"]   = $this->_lu_second($res["second"]);
    $res["status"]   = $this->_lu_status($res["status"]);
    $res["maneuver"] = $this->_lu_maneuver($res["maneuver"]);
    $res["raim"]     = $this->_lu_raim($res["raim"]);
    unset($res["spare"]);

    $this->hash = $res;
    return true;
  } // END: function decode_3()


  function decode_4() {
    // Start from the beginning, no matter where we may have left off.
    $this->sixbit->reset();

    $len = $this->sixbit->bitsRemaining();
    if( $len != 168 ) {
      $this->error = "decode_4: Unexpected number of bits remaining.";
      return false;
    }

    // physical message shape, raw bits
    $res = array(
      "type"     => $this->sixbit->getBits(6),
      "repeat"   => $this->sixbit->getBits(2),
      "mmsi"     => $this->sixbit->getBits(30),
      "year"     => $this->sixbit->getBits(14),
      "month"    => $this->sixbit->getBits(4),
      "day"      => $this->sixbit->getBits(5),
      "hour"     => $this->sixbit->getBits(5),
      "minute"   => $this->sixbit->getBits(6),
      "second"   => $this->sixbit->getBits(6),
      "accuracy" => $this->sixbit->getBits(1),
      "lon"      => $this->sixbit->getBits(28),
      "lat"      => $this->sixbit->getBits(27),
      "epfd"     => $this->sixbit->getBits(4),
      "spare"    => $this->sixbit->getBits(10),
      "raim"     => $this->sixbit->getBits(1),
      "radio"    => array(
        "sync_state"   => $this->sixbit->getBits(2),
        "slot_timeout" => $this->sixbit->getBits(3),
        "sub_message"  => $this->sixbit->getBits(14)
      )
    );

    // alterations to the values depending on contents

    // longitude (1/10000 minutes == 1/(60*10000) degrees)
    $res["lon"] = $this->sign_unsigned($res["lon"],28) / 600000;
    if( abs($res["lon"]) > 180 ) {
      $res["lon"] = null;
    } else {
      $res["lon"] = $this->paddedFloat($res["lon"], 1, 5, '0');
    }

    // latitude (1/10000 minutes == 1/(60*10000) degrees)
    $res["lat"]  = $this->sign_unsigned($res["lat"],27) / 600000;
    if( abs($res["lat"]) > 90 ) {
      $res["lat"] = null;
    } else {
      $res["lat"]  = $this->paddedFloat($res["lat"], 1, 5, '0');
    }

    // Lookup codes.
    $res["accuracy"] = $this->_lu_accuracy($res["accuracy"]);
    $res['epfd'] = $this->_lu_epfd($res['epfd']);
    unset($res['spare']);

    $this->hash = $res;
    return true;
  } // END: function decode_4()


  function decode_5() {
    // Start from the beginning, no matter where we may have left off.
    $this->sixbit->reset();

    $len = $this->sixbit->bitsRemaining();

    if( $len < 416 || $len > 426 ) {
      $this->error = "decode_5: Unexpected number of bits remaining ($len).";
      return false;
    }

    $res = array(
      "type"        => $this->sixbit->getBits(6),
      "repeat"      => $this->sixbit->getBits(2),
      "mmsi"        => $this->sixbit->getBits(30),
      "ais_version" => $this->sixbit->getBits(2),
      "imo"         => $this->sixbit->getBits(30),
      "callsign"    => $this->sixbit->getBitsAscii(42),
      "shipname"    => $this->sixbit->getBitsAscii(120),
      "shiptype"    => $this->sixbit->getBits(8),
      "antenna"     => array(
        "to_bow"         => $this->sixbit->getBits(9),
        "to_stern"       => $this->sixbit->getBits(9),
        "to_port"        => $this->sixbit->getBits(6),
        "to_starboard"   => $this->sixbit->getBits(6)
      ),
      "epfd"        => $this->sixbit->getBits(4),
      "eta"         => array(
        "month"  => $this->sixbit->getBits(4),
        "day"    => $this->sixbit->getBits(5),
        "hour"   => $this->sixbit->getBits(5),
        "minute" => $this->sixbit->getBits(6)
      ),
      "draught"     => $this->sixbit->getBits(8),
      "destination" => $this->sixbit->getBitsAscii(120),
      "dte"         => $this->sixbit->getBits(1)
    );

    // alterations to the values depending on contents

    // alter draught (1/10 meters, max 25.5m)
    $res["draught"] = $this->paddedFloat($res["draught"]/10,1,1,'0');

    // Apply lookups
    $res["shiptype"] = $this->_lu_shiptype($res["shiptype"]);
    $res["epfd"] = $this->_lu_epfd($res["epfd"]);
    $res["dte"] = $this->_lu_dte($res["dte"]);

    $this->hash = $res;
    return true;
  } // END: function decode_5()


  function decode_6() {
    // Start from the beginning, no matter where we may have left off.
    $this->sixbit->reset();

    $len = $this->sixbit->bitsRemaining();
    if( $len < 88 || $len > 1008 ) {
      $this->error = "decode_6: Unexpected number of bits remaining.";
      return false;
    }

    // physical message shape, raw bits
    $res = array(
      "type"       => $this->sixbit->getBits(6),
      "repeat"     => $this->sixbit->getBits(2),
      "mmsi"       => $this->sixbit->getBits(30),
      "seqno"      => $this->sixbit->getBits(2),
      "dest_mmsi"  => $this->sixbit->getBits(30),
      "retransmit" => $this->sixbit->getBits(1),
      "spare"      => $this->sixbit->getBits(1),
      //"app_id"     => array(
      "dac"        => $this->sixbit->getBits(10),
      "fid"        => $this->sixbit->getBits(6),
      //),
      "data"       => $this->sixbit->getBits(-1)
    );

    // alterations to the values depending on contents

    // Apply lookups
    $res["retransmit"] = $this->_lu_retransmit($res["retransmit"]);
    unset($res["spare"]);

    // TODO: parse the various dac-fid pair data payloads.

    $this->hash = $res;
    return true;
  } // END: function decode_6()


  function decode_7() {
    // Start from the beginning, no matter where we may have left off.
    $this->sixbit->reset();

    $len = $this->sixbit->bitsRemaining();
    if( $len < 72 || $len > 168 ) {
      $this->error = "decode_7: Unexpected number of bits remaining.";
      return false;
    }

    // physical message shape, raw bits
    $res = array(
      "type"    => $this->sixbit->getBits(6),
      "repeat"  => $this->sixbit->getBits(2),
      "mmsi"    => $this->sixbit->getBits(30),
      "spare_0" => $this->sixbit->getBits(2),
      "mmsi1"   => $this->sixbit->getBits(30),
      "spare_1" => $this->sixbit->getBits(2),
      "mmsi2"   => $this->sixbit->getBits(30),
      "spare_2" => $this->sixbit->getBits(2),
      "mmsi3"   => $this->sixbit->getBits(30),
      "spare_3" => $this->sixbit->getBits(2),
      "mmsi4"   => $this->sixbit->getBits(30),
      "spare_4" => $this->sixbit->getBits(2)
    );

    // alterations to the values depending on contents

    unset($res["spare_0"]);
    for($i=1;$i<5;$i++) { 
      unset($res["spare${i}"]);
      if( $res["mmsi${i}"] == 0 ) { 
        unset($res["mmsi${i}"]);
      }
    }

    $this->hash = $res;
    return true;
  } // END: function decode_7()


  function decode_8() {
    // Start from the beginning, no matter where we may have left off.
    $this->sixbit->reset();

    $len = $this->sixbit->bitsRemaining();
    if( $len < 40 || $len > 1008 ) {
      $this->error = "decode_8: Unexpected number of bits remaining.";
      return false;
    }

    // physical message shape, raw bits
    $res = array(
      "type"   => $this->sixbit->getBits(6),
      "repeat" => $this->sixbit->getBits(2),
      "mmsi"   => $this->sixbit->getBits(30),
      "spare"  => $this->sixbit->getBits(2),
      //"app_id"  => array(
      "dac"    => $this->sixbit->getBits(10),
      "fid"    => $this->sixbit->getBits(6),
      //),
      "data"   => $this->sixbit->getBits(-1)
    );

    // alterations to the values depending on contents

    unset($res["spare"]);

    $this->hash = $res;
    return true;
  } // END: function decode_8()


  function decode_9() {
    // Start from the beginning, no matter where we may have left off.
    $this->sixbit->reset();

    $len = $this->sixbit->bitsRemaining();
    if( $len != 168 ) {
      $this->error = "decode_9: Unexpected number of bits remaining.";
      return false;
    }

    // physical message shape, raw bits
    $res = array(
      "type"     => $this->sixbit->getBits(6),
      "repeat"   => $this->sixbit->getBits(2),
      "mmsi"     => $this->sixbit->getBits(30),
      "alt"      => $this->sixbit->getBits(12),
      "speed"    => $this->sixbit->getBits(10),
      "accuracy" => $this->sixbit->getBits(1),
      "lon"      => $this->sixbit->getBits(28),
      "lat"      => $this->sixbit->getBits(27),
      "course"   => $this->sixbit->getBits(12),
      "second"   => $this->sixbit->getBits(6),
      "regional" => $this->sixbit->getBits(8),
      "dte"      => $this->sixbit->getBits(1),
      "spare"    => $this->sixbit->getBits(3),
      "assigned" => $this->sixbit->getBits(1),
      "raim"     => $this->sixbit->getBits(1),
      "radio"    => $this->sixbit->getBits(1)
    );

    if( $res["radio"] == 0 ) {
      // SOTDMA
      $res["comm_state"] = array(
        "sync_state"   => $this->sixbit->getBits(2),
        "slot_timeout" => $this->sixbit->getBits(3),
        "sub_message"  => $this->sixbit->getBits(14)
      );
    } else {
      // ITDMA
      $res["radio"] = array(
        "sync_state" => $this->sixbit->getBits(2),
        "slot_alloc" => $this->sixbit->getBits(13),
        "num_slots"  => $this->sixbit->getBits(3),
        "keep_flag"  => $this->sixbit->getBits(1)
      );
    }

    // alterations to the values depending on contents

    // longitude (1/10000 minutes == 1/(60*10000) degrees)
    $res["lon"] = $this->sign_unsigned($res["lon"],28) / 600000;
    if( abs($res["lon"]) > 180 ) {
      $res["lon"] = null;
    } else {
      $res["lon"] = $this->paddedFloat($res["lon"], 1, 5, '0');
    }

    // latitude (1/10000 minutes == 1/(60*10000) degrees)
    $res["lat"]  = $this->sign_unsigned($res["lat"],27) / 600000;
    if( abs($res["lat"]) > 90 ) {
      $res["lat"] = null;
    } else {
      $res["lat"]  = $this->paddedFloat($res["lat"], 1, 5, '0');
    }

    // course over ground (1/10 degrees N)
    $res["course"] = $this->paddedFloat($res["course"]/10,1,1,'0');

    // Apply lookups
    $res["accuracy"] = $this->_lu_accuracy($res["accuracy"]);
    $res["heading"]  = $this->_lu_heading($res["heading"]);
    $res["course"]   = $this->_lu_course($res["course"]);
    $res["alt"] = $this->_lu_alt($res["alt"]);
    $res["dte"] = $this->_lu_dte($res["dte"]);
    $res["second"]   = $this->_lu_second($res["second"]);
    $res["assigned"] = $this->_lu_assigned($res["assigned"]);

    $this->hash = $res;
    return true;
  } // END: function decode_9()


  function decode_10() {
    // Start from the beginning, no matter where we may have left off.
    $this->sixbit->reset();

    $len = $this->sixbit->bitsRemaining();
    if( $len != 72 ) {
      $this->error = "decode_10: Unexpected number of bits remaining.";
      return false;
    }

    // physical message shape, raw bits
    $res = array(
      "type"      => $this->sixbit->getBits(6),
      "repeat"    => $this->sixbit->getBits(2),
      "mmsi"      => $this->sixbit->getBits(30),
      "spare_0"   => $this->sixbit->getBits(2),
      "dest_mmsi" => $this->sixbit->getBits(30),
      "spare_1"   => $this->sixbit->getBits(2)
    );

    // alterations to the values depending on contents

    unset($res["spare_0"]);
    unset($res["spare_1"]);

    $this->hash = $res;
    return true;
  } // END: function decode_10()


  function decode_11() {
    return $this->decode_4();
  } // END: function decode_11()


  function decode_12() {
    // Start from the beginning, no matter where we may have left off.
    $this->sixbit->reset();

    $len = $this->sixbit->bitsRemaining();
    if( $len < 88 || $len > 1008 ) {
      $this->error = "decode_12: Unexpected number of bits remaining.";
      return false;
    }

    // physical message shape, raw bits
    $res = array(
      "type"       => $this->sixbit->getBits(6),
      "repeat"     => $this->sixbit->getBits(2),
      "mmsi"       => $this->sixbit->getBits(30),
      "seqno"      => $this->sixbit->getBits(2),
      "dest_mmsi"  => $this->sixbit->getBits(30),
      "retransmit" => $this->sixbit->getBits(1),
      "spare"      => $this->sixbit->getBits(1),
      "text"       => $this->sixbit->getBits(-1)
      /*
      //"app_id"     => array(
        "dac"         => $this->sixbit->getBits(10),
        "fid" => $this->sixbit->getBits(6),
      //),
      "data"       => $this->sixbit->getBits(-1)
      */
    );

    // alterations to the values depending on contents

    unset($res["spare"]);

    $this->hash = $res;
    return true;
  } // END: function decode_12()


  function decode_13() {
    return $this->decode_7();
  } // END: function decode_13()


  function decode_14() {
    // Start from the beginning, no matter where we may have left off.
    $this->sixbit->reset();

    $len = $this->sixbit->bitsRemaining();
    if( $len < 40 || $len > 1008 ) {
      $this->error = "decode_14: Unexpected number of bits remaining.";
      return false;
    }

    // physical message shape, raw bits
    $res = array(
      "type"   => $this->sixbit->getBits(6),
      "repeat" => $this->sixbit->getBits(2),
      "mmsi"   => $this->sixbit->getBits(30),
      "spare"  => $this->sixbit->getBits(2),
      "text"   => $this->sixbit->getBitsAscii($len-40)
    );

    // alterations to the values depending on contents

    unset($res["spare"]);

    $this->hash = $res;
    return true;
  } // END: function decode_14()


  function decode_15() {
    // Start from the beginning, no matter where we may have left off.
    $this->sixbit->reset();

    $len = $this->sixbit->bitsRemaining();
    if( $len < 88 || $len > 160 ) {
      $this->error = "decode_15: Unexpected number of bits remaining.";
      return false;
    }

    // physical message shape, raw bits
    $res = array(
      "type"       => $this->sixbit->getBits(6),
      "repeat"     => $this->sixbit->getBits(2),
      "mmsi"       => $this->sixbit->getBits(30),
      "spare_0"    => $this->sixbit->getBits(2),
      "mmsi1"      => $this->sixbit->getBits(30),
      "type1_1"    => $this->sixbit->getBits(6),
      "offset1_1"  => $this->sixbit->getBits(12),
      "spare_1"    => $this->sixbit->getBits(2),
      "type1_2"    => $this->sixbit->getBits(6),
      "offset1_2"  => $this->sixbit->getBits(12),
      "spare_2"    => $this->sixbit->getBits(2),
      "mmsi2"      => $this->sixbit->getBits(30),
      "type2_1"    => $this->sixbit->getBits(6),
      "offset2_1"  => $this->sixbit->getBits(12),
      "spare_3"    => $this->sixbit->getBits(2)
    );

    // alterations to the values depending on contents

    for($i=0;$i<=3;$i++) { 
      unset($res["spare${i}"]);
    }

    $this->hash = $res;
    return true;
  } // END function decode_15()


  function decode_16() {
    // Start from the beginning, no matter where we may have left off.
    $this->sixbit->reset();

    $len = $this->sixbit->bitsRemaining();
    if( !($len == 96 || $len == 144) ) {
      $this->error = "decode_16: Unexpected number of bits remaining.";
      return false;
    }

    // physical message shape, raw bits
    $res = array(
      "type"       => $this->sixbit->getBits(6),
      "repeat"     => $this->sixbit->getBits(2),
      "mmsi"       => $this->sixbit->getBits(30),
      "spare_0"    => $this->sixbit->getBits(2),
      "mmsi1"      => $this->sixbit->getBits(30),
      "offset1"    => $this->sixbit->getBits(12),
      "increment1" => $this->sixbit->getBits(10)
    );

    if( $len == 96 ) {
      $res["spare_1"]    = $this->sixbit->getBits(4);
    } else {
      $res["mmsi2"]      = $this->sixbit->getBits(30);
      $res["offset2"]    = $this->sixbit->getBits(12);
      $res["increment2"] = $this->sixbit->getBits(10);
    }


    // alterations to the values depending on contents

    unset($res["spare_0"]);
    if( isset($res["spare_1"]) ) { 
      unset($res["spare_1"]);
    }

    $this->hash = $res;
    return true;
  } // END: function decode_16()


  function decode_17() {
    // Start from the beginning, no matter where we may have left off.
    $this->sixbit->reset();

    $len = $this->sixbit->bitsRemaining();
    if( $len < 80 || $len > 816 ) {
      $this->error = "decode_17: Unexpected number of bits remaining.";
      return false;
    }

    // physical message shape, raw bits
    $res = array(
      "type"      => $this->sixbit->getBits(6),
      "repeat"    => $this->sixbit->getBits(2),
      "mmsi"      => $this->sixbit->getBits(30),
      "spare_0"   => $this->sixbit->getBits(2),
      "lon"       => $this->sixbit->getBits(18),
      "lat"       => $this->sixbit->getBits(17),
      "spare_1"   => $this->sixbit->getBits(5)
    );

    if ( $len > 80 ) {
      $res["data"]  = array(
        "msg_type"  => $this->sixbit->getBits(6),
        "staid"     => $this->sixbit->getBits(10),
        "z_count"   => $this->sixbit->getBits(13),
        "seq_no"    => $this->sixbit->getBits(3),
        "n_words"   => $this->sixbit->getBits(5),
        "health"    => $this->sixbit->getBits(3),
        "words"     => array()
      );

      // quick test to make sure length is valid after learned information
      $n_words = $res["data"]["n_words"];
      if( $len != (80 + 40 + 24*$n_words) ) {
        return false;
      }

      // continue getting raw bits, 24 bits for each satellite record
      for($i=0; $i< $n_words; $i++ ) {
        $res["data"]["words"][] = $this->sixbit->getBits(24);
      }

    }

    // alterations to the values depending on contents

    // longitude (1/10 minutes == 1/(60*10) degrees)
    $res["lon"] = $this->sign_unsigned($res["lon"],18) / 600;
    if( abs($res["lon"]) > 180 ) {
      $res["lon"] = null;
    } else {
      $res["lon"] = $this->paddedFloat($res["lon"], 1, 5, '0');
    }

    // latitude (1/10 minutes == 1/(60*10) degrees)
    $res["lat"]  = $this->sign_unsigned($res["lat"],17) / 600;
    if( abs($res["lat"]) > 90 ) {
      $res["lat"] = null;
    } else {
      $res["lat"]  = $this->paddedFloat($res["lat"], 1, 5, '0');
    }

    if( $len > 80 ) {
      // z_count (Time value in 0.6s (0-3599.4)
      $res["data"]["z_count"] = 0.6 * parseFloat($res["data"]["z_count"]);
    }

    $this->hash = $res;
    return true;
  } // END: function decode_17()


  function decode_18() {
    // Start from the beginning, no matter where we may have left off.
    $this->sixbit->reset();

    $len = $this->sixbit->bitsRemaining();
    if( $len != 168 ) {
      $this->error = "decode_18: Unexpected number of bits remaining.";
      return false;
    }

    // physical message shape, raw bits
    $res = array(
      "type"        => $this->sixbit->getBits(6),
      "repeat"      => $this->sixbit->getBits(2),
      "mmsi"        => $this->sixbit->getBits(30),
      "reserved"    => $this->sixbit->getBits(8),
      "speed"       => $this->sixbit->getBits(10),
      "accuracy"    => $this->sixbit->getBits(1),
      "lon"         => $this->sixbit->getBits(28),
      "lat"         => $this->sixbit->getBits(27),
      "course"      => $this->sixbit->getBits(12),
      "heading"     => $this->sixbit->getBits(9),
      "second"      => $this->sixbit->getBits(6),
      "regional"    => $this->sixbit->getBits(2),
      "cs"          => $this->sixbit->getBits(1),
      "display"     => $this->sixbit->getBits(1),
      "dsc"         => $this->sixbit->getBits(1),
      "band"        => $this->sixbit->getBits(1),
      "msg22"       => $this->sixbit->getBits(1),
      "assigned"    => $this->sixbit->getBits(1),
      "raim"        => $this->sixbit->getBits(1),
      "radio"       => $this->sixbit->getBits(1)
    );

    if( $res["radio"] == 0 ) {
      // SOTDMA
      $res["radio"] = array(
        "sync_state"   => $this->sixbit->getBits(2),
        "slot_timeout" => $this->sixbit->getBits(3),
        "sub_message"  => $this->sixbit->getBits(14)
      );
    } else {
      // ITDMA
      $res["radio"] = array(
        "sync_state" => $this->sixbit->getBits(2),
        "slot_alloc" => $this->sixbit->getBits(13),
        "num_slots"  => $this->sixbit->getBits(3),
        "keep_flag"  => $this->sixbit->getBits(1)
      );
    }

    // alterations to the values depending on contents

    // speed over ground (1/10 knots)
    $res["speed"] = $this->paddedFloat($res["speed"]/10,1,1,'0');

    // longitude (1/10000 minutes == 1/(60*10000) degrees)
    $res["lon"] = $this->sign_unsigned($res["lon"],28) / 600000;
    if( abs($res["lon"]) > 180 ) {
      $res["lon"] = null;
    } else {
      $res["lon"] = $this->paddedFloat($res["lon"], 1, 5, '0');
    }

    // latitude (1/10000 minutes == 1/(60*10000) degrees)
    $res["lat"]  = $this->sign_unsigned($res["lat"],27) / 600000;
    if( abs($res["lat"]) > 90 ) {
      $res["lat"] = null;
    } else {
      $res["lat"]  = $this->paddedFloat($res["lat"], 1, 5, '0');
    }

    // course over ground (1/10 degrees N)
    $res["course"] = $this->paddedFloat($res["course"]/10,1,1,'0');


    // Lookups
    $res["accuracy"] = $this->_lu_accuracy($res["accuracy"]);
    $res["heading"] = $this->_lu_heading($res["heading"]);
    $res["course"] = $this->_lu_course($res["course"]);
    $res["second"] = $this->_lu_second($res["second"]);
    $res["cs"] = $this->_lu_cs($res["cs"]);
    $res["display"] = $this->_lu_display($res["display"]);
    $res["dsc"] = $this->_lu_dsc($res["dsc"]);
    $res["band"] = $this->_lu_band($res["band"]);
    $res["msg22"] = $this->_lu_msg22($res["msg22"]);
    $res["assigned"] = $this->_lu_assigned($res["assigned"]);

    $this->hash = $res;
    return true;
  } // END: function decode_18()


  function decode_19() {
    // Start from the beginning, no matter where we may have left off.
    $this->sixbit->reset();

    $len = $this->sixbit->bitsRemaining();
    if( $len != 312 ) {
      $this->error = "decode_19: Unexpected number of bits remaining.";
      return false;
    }

    // physical message shape, raw bits
    $res = array(
      "type"        => $this->sixbit->getBits(6),
      "repeat"      => $this->sixbit->getBits(2),
      "mmsi"        => $this->sixbit->getBits(30),
      "reserved"    => $this->sixbit->getBits(8),
      "speed"       => $this->sixbit->getBits(10),
      "accuracy"    => $this->sixbit->getBits(1),
      "lon"         => $this->sixbit->getBits(28),
      "lat"         => $this->sixbit->getBits(27),
      "course"      => $this->sixbit->getBits(12),
      "heading"     => $this->sixbit->getBits(9),
      "second"      => $this->sixbit->getBits(6),
      "regional"    => $this->sixbit->getBits(4),
      "shipname"    => $this->sixbit->getBitsAscii(120),
      "shiptype"    => $this->sixbit->getBits(8),
      "antenna"     => array(
        "to_bow"         => $this->sixbit->getBits(9),
        "to_stern"       => $this->sixbit->getBits(9),
        "to_port"        => $this->sixbit->getBits(6),
        "to_starboard"   => $this->sixbit->getBits(6)
      ),
      "epfd"        => $this->sixbit->getBits(4),
      "raim"        => $this->sixbit->getBits(1),
      "dte"         => $this->sixbit->getBits(1),
      "assigned"    => $this->sixbit->getBits(1),
      "spare"       => $this->sixbit->getBits(4)
    );

    // alterations to the values depending on contents

    // speed over ground (1/10 knots)
    $res["speed"] = $this->paddedFloat($res["speed"]/10,1,1,'0');

    // longitude (1/10000 minutes == 1/(60*10000) degrees)
    $res["lon"] = $this->sign_unsigned($res["lon"],28) / 600000;
    if( abs($res["lon"]) > 180 ) {
      $res["lon"] = null;
    } else {
      $res["lon"] = $this->paddedFloat($res["lon"], 1, 5, '0');
    }

    // latitude (1/10000 minutes == 1/(60*10000) degrees)
    $res["lat"]  = $this->sign_unsigned($res["lat"],27) / 600000;
    if( abs($res["lat"]) > 90 ) {
      $res["lat"] = null;
    } else {
      $res["lat"]  = $this->paddedFloat($res["lat"], 1, 5, '0');
    }

    // course over ground (1/10 degrees N)
    $res["course"] = $this->paddedFloat($res["course"]/10,1,1,'0');

    // Lookups
    $res["accuracy"] = $this->_lu_accuracy($res["accuracy"]);
    $res["second"]   = $this->_lu_second($res["second"]);
    $res["heading"]  = $this->_lu_heading($res["heading"]);
    $res["course"]   = $this->_lu_course($res["course"]);
    $res["shiptype"] = $this->_lu_shiptype($res["shiptype"]);
    $res["epfd"]     = $this->_lu_epfd($res["epfd"]);
    $res["dte"]      = $this->_lu_dte($res["dte"]);
    $res["assigned"] = $this->_lu_assigned($res["assigned"]);

    unset($res["spare"]);

    $this->hash = $res;
    return true;
  } // END: function decode_19()


  function decode_20() {
    // Start from the beginning, no matter where we may have left off.
    $this->sixbit->reset();

    $len = $this->sixbit->bitsRemaining();
    if( $len < 72 || $len > 160 ) {
      $this->error = "decode_20: Unexpected number of bits remaining.";
      return false;
    }

    // physical message shape, raw bits
    $res = array(
      "type"       => $this->sixbit->getBits(6),
      "repeat"     => $this->sixbit->getBits(2),
      "mmsi"       => $this->sixbit->getBits(30),
      "spare_0"    => $this->sixbit->getBits(2),

      "offset1"    => $this->sixbit->getBits(12),
      "number1"    => $this->sixbit->getBits(4),
      "timeout1"   => $this->sixbit->getBits(3),
      "increment1" => $this->sixbit->getBits(11),

      "offset2"    => $this->sixbit->getBits(12),
      "number2"    => $this->sixbit->getBits(4),
      "timeout2"   => $this->sixbit->getBits(3),
      "increment2" => $this->sixbit->getBits(11),

      "offset3"    => $this->sixbit->getBits(12),
      "number3"    => $this->sixbit->getBits(4),
      "timeout3"   => $this->sixbit->getBits(3),
      "increment3" => $this->sixbit->getBits(11),

      "offset4"    => $this->sixbit->getBits(12),
      "number4"    => $this->sixbit->getBits(4),
      "timeout4"   => $this->sixbit->getBits(3),
      "increment4" => $this->sixbit->getBits(11),

      "spare_1"    => $this->sixbit->getBits(6)
    );

    // alterations to the values depending on contents

    unset($res["spare_0"]);
    unset($res["spare_1"]);

    $this->hash = $res;
    return true;
  } // END: function decode_20()


  function decode_21() {
    // Start from the beginning, no matter where we may have left off.
    $this->sixbit->reset();

    $len = $this->sixbit->bitsRemaining();
    if( $len < 272 || $len > 360 ) {
      $this->error = "decode_21: Unexpected number of bits remaining.";
      return false;
    }

    // physical message shape, raw bits
    $res = array(
      "type"     => $this->sixbit->getBits(6),
      "repeat"   => $this->sixbit->getBits(2),
      "mmsi"     => $this->sixbit->getBits(30),
      "aid_type" => $this->sixbit->getBits(5),
      "name"     => $this->sixbit->getBitsAscii(120),
      "accuracy" => $this->sixbit->getBits(1),
      "lon"      => $this->sixbit->getBits(28),
      "lat"      => $this->sixbit->getBits(27),
      "antenna"  => array(
        "to_bow"        => $this->sixbit->getBits(9),
        "to_stern"      => $this->sixbit->getBits(9),
        "to_port"       => $this->sixbit->getBits(6),
        "to_starboard"  => $this->sixbit->getBits(6)
      ),
      "epfd"        => $this->sixbit->getBits(4),
      "second"      => $this->sixbit->getBits(6),
      "off_position"  => $this->sixbit->getBits(1),
      "regional"    => $this->sixbit->getBits(8),
      "raim"        => $this->sixbit->getBits(1),
      "virtual_aid" => $this->sixbit->getBits(1),
      "assigned"    => $this->sixbit->getBits(1),
      "spare_0"     => $this->sixbit->getBits(1)
    );

    $rem = 0;
    if( $len > 272 ) {
      $rem = ($len-272)%6;
      $res["aid_name_ext"] = $this->sixbit->getBitsAscii($len-272-$rem);
    }
    $res["spare_1"] = $this->sixbit->getBits($rem);


    // alterations to the values depending on contents

    // longitude (1/10000 minutes == 1/(60*10000) degrees)
    $res["lon"] = $this->sign_unsigned($res["lon"],28) / 600000;
    if( abs($res["lon"]) > 180 ) {
      $res["lon"] = null;
    } else {
      $res["lon"] = $this->paddedFloat($res["lon"], 1, 5, '0');
    }

    // latitude (1/10000 minutes == 1/(60*10000) degrees)
    $res["lat"]  = $this->sign_unsigned($res["lat"],27) / 600000;
    if( abs($res["lat"]) > 90 ) {
      $res["lat"] = null;
    } else {
      $res["lat"]  = $this->paddedFloat($res["lat"], 1, 5, '0');
    }

    // Lookups
    $res["accuracy"] = $this->_lu_accuracy($res["accuracy"]);
    $res["aid_type"] = $this->_lu_aid_type($res["aid_type"]);
    $res["epfd"] = $this->_lu_epfd($res["epfd"]);
    $res["virtual_aid"] = $this->_lu_virtual_aid($res["virtual_aid"]);
    $res["assigned"] = $this->_lu_assigned($res["assigned"]);

    if( $res["second"] >= 60 ) { 
      $res["second"] = $this->_lu_second($res["second"]);
      unset($res["off_position"]);
    } else { 
      $res["off_position"] = $this->_lu_off_position($res["off_position"]);
    }

    unset($res["spare_0"]);
    unset($res["spare_1"]);


    $this->hash = $res;
    return true;
  } // END: function decode_21()


  function decode_22() {
    // Start from the beginning, no matter where we may have left off.
    $this->sixbit->reset();

    $len = $this->sixbit->bitsRemaining();
    if( $len != 168 ) {
      $this->error = "decode_22: Unexpected number of bits remaining.";
      return false;
    }

    // physical message shape, raw bits
    $res = array(
      "type"       => $this->sixbit->getBits(6),
      "repeat"     => $this->sixbit->getBits(2),
      "mmsi"       => $this->sixbit->getBits(30),
      "spare_0"    => $this->sixbit->getBits(2),
      "channel_a"  => $this->sixbit->getBits(12),
      "channel_b"  => $this->sixbit->getBits(12),
      "rxtx"       => $this->sixbit->getBits(4),
      "power"      => $this->sixbit->getBits(1),
      "ne_lon"     => $this->sixbit->getBits(18),
      "ne_lat"     => $this->sixbit->getBits(17),
      "sw_lon"     => $this->sixbit->getBits(18),
      "sw_lat"     => $this->sixbit->getBits(17),
      "dest1"      => false,
      "dest2"      => false,
      "addressed"  => $this->sixbit->getBits(1),
      "band_a"     => $this->sixbit->getBits(1),
      "band_b"     => $this->sixbit->getBits(1),
      "zonesize"   => $this->sixbit->getBits(3),
      "spare_1"    => $this->sixbit->getBits(23)
    );

    // alterations to the values depending on contents
    if( $res["addressed"] ) {
      $res["dest1"] = ($res["ne_lon"]<<12)|($res["ne_lat"]>>5);
      unset($res["ne_lon"]);
      unset($res["ne_lat"]);

      $res["dest2"] = ($res["sw_lon"]<<12)|($res["sw_lat"]>>5);
      unset($res["sw_lon"]);
      unset($res["sw_lat"]);

    } else {
      unset($res["dest1"]);
      unset($res["dest2"]);

      // longitude (1/10 minutes == 1/(60*10) degrees)
      $res["ne_lon"] = $this->sign_unsigned($res["ne_lon"],18) / 600;
      if( abs($res["ne_lon"]) > 180 ) {
        $res["ne_lon"] = null;
      } else {
        $res["ne_lon"] = $this->paddedFloat($res["ne_lon"], 1, 5, '0');
      }

      // latitude (1/10 minutes == 1/(60*10) degrees)
      $res["ne_lat"]  = $this->sign_unsigned($res["ne_lat"],17) / 600;
      if( abs($res["ne_lat"]) > 90 ) {
        $res["ne_lat"] = null;
      } else {
        $res["ne_lat"]  = $this->paddedFloat($res["ne_lat"], 1, 5, '0');
      }

      // longitude (1/10 minutes == 1/(60*10) degrees)
      $res["sw_lon"] = $this->sign_unsigned($res["sw_lon"],18) / 600;
      if( abs($res["sw_lon"]) > 180 ) {
        $res["sw_lon"] = null;
      } else {
        $res["sw_lon"] = $this->paddedFloat($res["sw_lon"], 1, 5, '0');
      }

      // latitude (1/10 minutes == 1/(60*10) degrees)
      $res["sw_lat"]  = $this->sign_unsigned($res["sw_lat"],17) / 600;
      if( abs($res["sw_lat"]) > 90 ) {
        $res["sw_lat"] = null;
      } else {
        $res["sw_lat"]  = $this->paddedFloat($res["sw_lat"], 1, 5, '0');
      }

    }

    $res["txrx"] = $this->_lu_txrx($res["txrx"]&0x03);
    $res["power"] = $this->_lu_power($res["power"]);
    $res["addressed"] = $this->_lu_addressed($res["addressed"]);

    unset($res["spare_0"]);
    unset($res["spare_1"]);

    $this->hash = $res;
    return true;
  } // END: function decode_22()


  function decode_23() {
    // Start from the beginning, no matter where we may have left off.
    $this->sixbit->reset();

    $len = $this->sixbit->bitsRemaining();
    if( $len != 160 ) {
      $this->error = "decode_23: Unexpected number of bits remaining.";
      return false;
    }

    // physical message shape, raw bits
    $res = array(
      "type"        => $this->sixbit->getBits(6),
      "repeat"      => $this->sixbit->getBits(2),
      "mmsi"        => $this->sixbit->getBits(30),
      "spare_0"     => $this->sixbit->getBits(2),
      "ne_lon"      => $this->sixbit->getBits(18),
      "ne_lat"      => $this->sixbit->getBits(17),
      "sw_lon"      => $this->sixbit->getBits(18),
      "sw_lat"      => $this->sixbit->getBits(17),
      "stationtype" => $this->sixbit->getBits(4),
      "shiptype"    => $this->sixbit->getBits(8),
      "spare_1"     => $this->sixbit->getBits(22),
      "txrx"        => $this->sixbit->getBits(2),
      "interval"    => $this->sixbit->getBits(4),
      "quiet"       => $this->sixbit->getBits(4),
      "spare_2"     => $this->sixbit->getBits(6)
    );

    // alterations to the values depending on contents

    // longitude (1/10 minutes == 1/(60*10) degrees)
    $res["ne_lon"] = $this->sign_unsigned($res["ne_lon"],18) / 600;
    if( abs($res["ne_lon"]) > 180 ) {
      $res["ne_lon"] = null;
    } else {
      $res["ne_lon"] = $this->paddedFloat($res["ne_lon"], 1, 5, '0');
    }

    // latitude (1/10 minutes == 1/(60*10) degrees)
    $res["ne_lat"]  = $this->sign_unsigned($res["ne_lat"],17) / 600;
    if( abs($res["ne_lat"]) > 90 ) {
      $res["ne_lat"] = null;
    } else {
      $res["ne_lat"]  = $this->paddedFloat($res["ne_lat"], 1, 5, '0');
    }

    // longitude (1/10 minutes == 1/(60*10) degrees)
    $res["sw_lon"] = $this->sign_unsigned($res["sw_lon"],18) / 600;
    if( abs($res["sw_lon"]) > 180 ) {
      $res["sw_lon"] = null;
    } else {
      $res["sw_lon"] = $this->paddedFloat($res["sw_lon"], 1, 5, '0');
    }

    // latitude (1/10 minutes == 1/(60*10) degrees)
    $res["sw_lat"]  = $this->sign_unsigned($res["sw_lat"],17) / 600;
    if( abs($res["sw_lat"]) > 90 ) {
      $res["sw_lat"] = null;
    } else {
      $res["sw_lat"]  = $this->paddedFloat($res["sw_lat"], 1, 5, '0');
    }

    $res["stationtype"] = $this->_lu_stationtype($res["stationtype"]);
    $res["shiptype"] = $this->_lu_shiptype($res["shiptype"]);
    $res["txrx"] = $this->_lu_txrx($res["txrx"]);
    $res["interval"] = $this->_lu_interval($res["interval"]);

    unset($res["spare_0"]);
    unset($res["spare_1"]);
    unset($res["spare_2"]);

    $this->hash = $res;
    return true;
  } // END: function decode_23()


  function decode_24() {
    // Start from the beginning, no matter where we may have left off.
    $this->sixbit->reset();

    $len = $this->sixbit->bitsRemaining();

    $junk = $this->sixbit->getBits(38);
    $partno = $this->sixbit->getBits(2);

    if( $len == 160 || $partno==0 ) {
      return $this->decode_24A();

    } else if( $len == 168 ) {
      return $this->decode_24B();

    } else {
      $this->error = "decode_24: Unexpected number of bits remaining ($len).";
      return false;
    }
  } // END: function decode_24()


  function decode_24A() {
    // Start from the beginning, no matter where we may have left off.
    $this->sixbit->reset();

    $len = $this->sixbit->bitsRemaining();
    if( $len < 160 || $len>168 ) {
      $this->error = "decode_24A: Unexpected number of bits remaining.";
      return false;
    }

    // physical message shape, raw bits
    $res = array(
      "type"         => $this->sixbit->getBits(6),
      "repeat"       => $this->sixbit->getBits(2),
      "mmsi"         => $this->sixbit->getBits(30),
      "partno"       => $this->sixbit->getBits(2),
      "shipname"     => $this->sixbit->getBitsAscii(120),
    );

    // alterations to the values depending on contents
    // None.

    $this->hash = $res;
    return true;
  } // END: function decode_24A


  function decode_24B() {
    // Start from the beginning, no matter where we may have left off.
    $this->sixbit->reset();

    $len = $this->sixbit->bitsRemaining();
    if( $len != 168 ) {
      $this->error = "decode_24B: Unexpected number of bits remaining.";
      return false;
    }

    // physical message shape, raw bits
    $res = array(
      "type"        => $this->sixbit->getBits(6),
      "repeat"      => $this->sixbit->getBits(2),
      "mmsi"        => $this->sixbit->getBits(30),
      "partno"      => $this->sixbit->getBits(2),
      "shiptype"    => $this->sixbit->getBits(8),
      //"vendorid"    => $this->sixbit->getBitsAscii(42),
      "vendorid"    => $this->sixbit->getBitsAscii(18),
      "model"       => $this->sixbit->getBits(4),
      "serial"      => $this->sixbit->getBits(20),
      "callsign"    => $this->sixbit->getBitsAscii(42)
    );

    // alterations to the values depending on contents

    $mmsi = $this->sixbit->getBits(30);

    if( floor($res["mmsi"]/1e6) == 98 ) { 
      $res["mothership_mmsi"] = $mmsi;
    } else {
      $res["antenna"] = array(
        "to_bow"         => (($mmsi>>21) & 0x1ff),
        "to_stern"       => (($mmsi>>12) & 0x1ff),
        "to_port"        => (($mmsi>>6 ) &  0x3f),
        "to_starboard"   => (($mmsi    ) &  0x3f)
      );
    }

    $this->hash = $res;
    return true;
  } // END: function decode_24B


  function decode_25() {
    // Start from the beginning, no matter where we may have left off.
    $this->sixbit->reset();

    $len = $this->sixbit->bitsRemaining();
    if( $len < 40 || $len > 168) {
      $this->error = "decode_25: Unexpected number of bits remaining.";
      return false;
    }

    // physical message shape, raw bits
    $res = array(
      "type"        => $this->sixbit->getBits(6),
      "repeat"      => $this->sixbit->getBits(2),
      "mmsi"        => $this->sixbit->getBits(30),
      "addressed"   => $this->sixbit->getBits(1),
      "structured"  => $this->sixbit->getBits(1)
    );

    if( $res["addressed"] ) {
      $res["dest_mmsi"] = $this->sixbit->getBits(30);
    }

    if( $res["structured"] ) {
      //$res["app_id"] = $this->sixbit->getBits(16);
      /*$res["app_id"] = array(
        "dac"         => $this->sixbit->getBits(10),
        "fid" => $this->sixbit->getBits(6)
      );*/
      $res["dac"] = $this->sixbit->getBits(10);
      $res["fid"] = $this->sixbit->getBits(6);
    }
    $res["data"] = $this->sixbit->getBits(-1);

    $this->hash = $res;
    return true;
  } // END: function decode_25()


  function decode_26() {
    // Start from the beginning, no matter where we may have left off.
    $this->sixbit->reset();

    $len = $this->sixbit->bitsRemaining();
    if( $len < 40 || $len > 1064) {
      $this->error = "decode_26: Unexpected number of bits remaining.";
      return false;
    }


    // physical message shape, raw bits
    $res = array(
      "type"        => $this->sixbit->getBits(6),
      "repeat"      => $this->sixbit->getBits(2),
      "mmsi"        => $this->sixbit->getBits(30),
      "addressed"   => $this->sixbit->getBits(1),
      "structured"  => $this->sixbit->getBits(1)
    );
    $used_bits = 40;

    if( $res["addressed"] ) {
      $res["dest_mmsi"] = $this->sixbit->getBits(30);
      $used_bits += 30;
    }

    if( $res["structured"] ) {
      /*$res["app_id"] = array(
        "dac"         => $this->sixbit->getBits(10),
        "fid" => $this->sixbit->getBits(6)
      );*/
      $res["dac"] = $this->sixbit->getBits(10);
      $res["fid"] = $this->sixbit->getBits(6);
      $used_bits += 16;
    }

    $res["data"] = $this->sixbit->getBits($len - $used_bits - 20);
    $res["radio"] = $this->sixbit->getBits(1);

    if( $res["radio"] == 0 ) {
      // SOTDMA
      $res["radio"] = array(
        "sync_state"   => $this->sixbit->getBits(2),
        "slot_timeout" => $this->sixbit->getBits(3),
        "sub_message"  => $this->sixbit->getBits(14)
      );
    } else {
      // ITDMA
      $res["radio"] = array(
        "sync_state" => $this->sixbit->getBits(2),
        "slot_alloc" => $this->sixbit->getBits(13),
        "num_slots"  => $this->sixbit->getBits(3),
        "keep_flag"  => $this->sixbit->getBits(1)
      );
    }

    $this->hash = $res;
    return true;
  } // END: function decode_26()


  function decode_27() { 
    $this->sixbit->reset();
    $len = $this->sixbit->bitsRemaining();
    if( $len < 96 || $len > 168 ) { 
      $this->error = "decode_27: Unexpected number of bits remaining.";
      return false;
    }

    $res = array(
      'type'     => $this->sixbit->getBits(6),
      'repeat'   => $this->sixbit->getBits(2),
      'mmsi'     => $this->sixbit->getBits(30),
      'accuracy' => $this->sixbit->getBits(1),
      'raim'     => $this->sixbit->getBits(1),
      'status'   => $this->sixbit->getBits(4),
      'lon'      => $this->sixbit->getBits(18),
      'lat'      => $this->sixbit->getBits(17),
      'speed'    => $this->sixbit->getBits(6),
      'course'   => $this->sixbit->getBits(9),
      'gnss'     => $this->sixbit->getBits(1)
    );


    // alterations to the values depending on contents

    // speed over ground (1/10 knots)
    $res["speed"] = $this->paddedFloat($res["speed"]/10,1,1,'0');

    // longitude (1/10000 minutes == 1/(60*10000) degrees)
    $res["lon"] = $this->sign_unsigned($res["lon"],28) / 600000;
    if( abs($res["lon"]) > 180 ) {
      $res["lon"] = null;
    } else {
      $res["lon"] = $this->paddedFloat($res["lon"], 1, 5, '0');
    }

    // latitude (1/10000 minutes == 1/(60*10000) degrees)
    $res["lat"] = $this->sign_unsigned($res["lat"],27) / 600000;
    if( abs($res["lat"]) > 90 ) {
      $res["lat"] = null;
    } else {
      $res["lat"] = $this->paddedFloat($res["lat"], 1, 5, '0');
    }

    // course over ground (1/10 degrees N)
    $res["course"] = $this->paddedFloat($res["course"]/10,1,1,'0');

    // Apply lookups.

    // direction the ship points in (degrees N)
    $res["accuracy"] = $this->_lu_accuracy($res["accuracy"]);
    $res["raim"]     = $this->_lu_raim($res["raim"]);
    $res["status"]   = $this->_lu_status($res["status"]);
    $res["heading"]  = $this->_lu_heading($res["heading"]);
    $res["course"]   = $this->_lu_course($res["course"]);
    $res["gnss"]     = $this->_lu_gnss($res["gnss"]);

    $this->hash = $res;
    return true;
  } // END: function decode_27()


  function _lu_accuracy($accuracy) { 
    switch($accuracy) { 
      case 0: return "Unaugmented GNSS fix (accuracy > 10m)";
      case 1: return "DGPS-quality fix (accuracy < 10m)";
    }
    return $accuracy;
  } // END: function _lu_accuracy($accuracy)

  function _lu_heading($heading) { 
    return ($heading==511) ? "Not available" : $heading;
  } // END: function _lu_heading($heading)

  function _lu_course($course) { 
    return (($course|0)==511 || ($course|0)==360) ? "Not available" : $course;
  } // END: function _lu_course($course)

  function _lu_second($second) { 
    switch($second) { 
      case 60: return "Time stamp is not available (default)";
      case 61: return "Positioning system is in manual input mode";
      case 62: return "Electronic Position Fixing System operates in estimated (dead reckoning) mode";
      case 63: return "Positioning system is inoperative.";
    }
    return $second;
  } // END: function _lu_second($second)


  function _lu_status($status) { 
    switch($status) { 
      case 0: return "Under way using engine";
      case 1: return "At anchor";
      case 2: return "Not under command";
      case 3: return "restricted manoeverability";
      case 4: return "Constrained by her draught";
      case 5: return "Moored";
      case 6: return "Aground";
      case 7: return "Engaged in Fishing";
      case 8: return "Under way sailing";
      case 9: return "Reserved for future amendment of Navigational Status for HSC";
      case 10: return "Reserved for future amendment of Navigational Status for WIG";
      case 11: return "Reserved for future use";
      case 12: return "Reserved for future use";
      case 13: return "Reserved for future use";
      case 14: return "AIS-SART is active";
      case 15: return "Not defined (default)";
    }
    return $status;
  } // END: function _lu_status($status)


  function _lu_maneuver($maneuver) { 
    switch($maneuver) { 
      case 0: return "Not available (default)";
      case 1: return "No special maneuver";
      case 2: return "Special maneuver (such as regional passing arrangement)";
    }
    return $maneuver;
  } // END: function _lu_maneuver($maneuver)


  function _lu_raim($raim) { 
    switch($raim) { 
      case 0: return "RAIM not in use (default)";
      case 1: return "RAIM in use";
    }
    return $raim;
  } // END: function _lu_raim($raim)


  function _lu_epfd($epfd) { 
    switch($epfd) { 
      case 0: return "Undefined (default)";
      case 1: return "GPS";
      case 2: return "GLONASS";
      case 3: return "Combined GPS/GLONASS";
      case 4: return "Loran-C";
      case 5: return "Chayka";
      case 6: return "Integrated navigation system";
      case 7: return "Surveyed";
      case 8: return "Galileo";
    }
    return $epfd;
  } // END: function _lu_epfd($epfd)


  function _lu_shiptype($shiptype) { 
    if( $shiptype<20 && $shiptype>0 ) { 
      return "Reserved for future use";
    }
    switch($shiptype) { 
      case 0: return "Not available (default)";
      case 20: return "Wing in ground (WIG), all ships of this type";
      case 21: return "Wing in ground (WIG), Hazardous category A";
      case 22: return "Wing in ground (WIG), Hazardous category B";
      case 23: return "Wing in ground (WIG), Hazardous category C";
      case 24: return "Wing in ground (WIG), Hazardous category D";
      case 25:
      case 26:
      case 27:
      case 28:
      case 29: return "Wing in ground (WIG), Reserved for future use";
      case 30: return "Fishing";
      case 31: return "Towing";
      case 32: return "Towing: length exceeds 200m or breadth exceeds 25m";
      case 33: return "Dredging or underwater ops";
      case 34: return "Diving ops";
      case 35: return "Military ops";
      case 36: return "Sailing";
      case 37: return "Pleasure Craft";
      case 38:
      case 39: return "Reserved";
      case 40: return "High speed craft (HSC), all ships of this type";
      case 41: return "High speed craft (HSC), Hazardous category A";
      case 42: return "High speed craft (HSC), Hazardous category B";
      case 43: return "High speed craft (HSC), Hazardous category C";
      case 44: return "High speed craft (HSC), Hazardous category D";
      case 45:
      case 46:
      case 47:
      case 48: return "High speed craft (HSC), Reserved for future use";
      case 49: return "High speed craft (HSC), No additional information";
      case 50: return "Pilot Vessel";
      case 51: return "Search and Rescue vessel";
      case 52: return "Tug";
      case 53: return "Port Tender";
      case 54: return "Anti-pollution equipment";
      case 55: return "Law Enforcement";
      case 56: return "Spare - Local Vessel";
      case 57: return "Spare - Local Vessel";
      case 58: return "Medical Transport";
      case 59: return "Noncombatant ship according to RR Resolution No. 18";
      case 60: return "Passenger, all ships of this type";
      case 61: return "Passenger, Hazardous category A";
      case 62: return "Passenger, Hazardous category B";
      case 63: return "Passenger, Hazardous category C";
      case 64: return "Passenger, Hazardous category D";
      case 65: 
      case 66: 
      case 67: 
      case 68: return "Passenger, Reserved for future use";
      case 69: return "Passenger, No additional information";
      case 70: return "Cargo, all ships of this type";
      case 71: return "Cargo, Hazardous category A";
      case 72: return "Cargo, Hazardous category B";
      case 73: return "Cargo, Hazardous category C";
      case 74: return "Cargo, Hazardous category D";
      case 75:
      case 76:
      case 77:
      case 78: return "Cargo, Reserved for future use";
      case 79: return "Cargo, No additional information";
      case 80: return "Tanker, all ships of this type";
      case 81: return "Tanker, Hazardous category A";
      case 82: return "Tanker, Hazardous category B";
      case 83: return "Tanker, Hazardous category C";
      case 84: return "Tanker, Hazardous category D";
      case 85:
      case 86:
      case 87:
      case 88: return "Tanker, Reserved for future use";
      case 89: return "Tanker, No additional information";
      case 90: return "Other Type, all ships of this type";
      case 91: return "Other Type, Hazardous category A";
      case 92: return "Other Type, Hazardous category B";
      case 93: return "Other Type, Hazardous category C";
      case 94: return "Other Type, Hazardous category D";
      case 95:
      case 96:
      case 97:
      case 98: return "Other type, Reserved for future use";
      case 99: return "Other type, no additional information";
      default: return "Not available (default)";
    }
    return $shiptype;
  } // END: function _lu_shiptype($shiptype)


  function _lu_alt($alt) { 
    return ($alt==4095) ? "Not available" : $alt;
  } // END: function _lu_alt($alt)


  function _lu_dte($dte) { 
    switch($dte) { 
      case 0: return "Data terminal ready";
      case 1: return "Not ready (default)";
    }
    return $dte;
  } // END: function _lu_dte($dte)


  function _lu_assigned($assigned) { 
    return ($assigned) ? "Autonomous mode (default)" : "Assigned mode";
  } // END: function _lu_assigned($assigned)


  function _lu_cs($cs) { 
    return ($cs==1) ? "Class B CS (Carrier Sense) unit" : "Class B SOTDMA";
  } // END: function _lu_cs($cs)

  function _lu_display($display) { 
    return ($display==1) ? "Has display" : "No visual display";
  } // END: function _lu_display($display)

  function _lu_dsc($dsc) { 
    return ($dsc==1) ? "Yes" : "No";
  } // END: function _lu_dsc($dsc)

  function _lu_band($band) { 
    return ($band==1) ? "Yes" : "No";
  } // END: function _lu_band($band)

  function _lu_msg22($msg22) { 
    return ($msg22==1) ? "Yes" : "No";
  } // END: function _lu_msg22($msg22)

  function _lu_aid_type($aid_type) { 
    switch($aid_type) { 
      case 0: return "Default, Type of Aid to Navigation not specified";
      case 1: return "Reference point";
      case 2: return "ACON (radar transponder marking a navigation hazard)";
      case 3: return "Fixed structure off shore, such as oil platforms, wind farms, rigs";
      case 4: return "Spare, Reserved for future use.";
      case 5: return "Light, without sectors";
      case 6: return "Light, with sectors";
      case 7: return "Leading Light Front";
      case 8: return "Leading Light Rear";
      case 9: return "Beacon, Cardinal N";
      case 10: return "Beacon, Cardinal E";
      case 11: return "Beacon, Cardinal S";
      case 12: return "Beacon, Cardinal W";
      case 13: return "Beacon, Port hand";
      case 14: return "Beacon, Starboard hand";
      case 15: return "Beacon, Preferred Channel port hand";
      case 16: return "Beacon, Preferred Channel starboard hand";
      case 17: return "Beacon, Isolated danger";
      case 18: return "Beacon, Safe water";
      case 19: return "Beacon, Special mark";
      case 20: return "Cardinal Mark N";
      case 21: return "Cardinal Mark E";
      case 22: return "Cardinal Mark S";
      case 23: return "Cardinal Mark W";
      case 24: return "Port hand Mark";
      case 25: return "Starboard hand Mark";
      case 26: return "Preferred Channel Port hand";
      case 27: return "Preferred Channel Starboard hand";
      case 28: return "Isolated danger";
      case 29: return "Safe Water";
      case 30: return "Special Mark";
      case 31: return "Light Vessel / LANBY / Rigs";
    }
    return $aid_type;
  } // END: function _lu_aid_type($aid_type)


  function _lu_off_position($off_position) { 
    return ($off_position==1) ? "Off" : "On";
  } // END: function _lu_off_position($off_position)

  function _lu_virtual_aid($virtual_aid) { 
    switch($virtual_aid) {
      case 0: return "real Aid to Navigation at indicated position (default)";
      case 1: return "virtual Aid to Navigation simulated by nearby AIS station";
    }
    return $virtual_aid;
  } // END: function _lu_virtual_aid($virtual_aid)

  function _lu_txrx($txrx) { 
    switch($txrx) { 
      case 0: return "TxA/TxB, RxA/RxB (default)";
      case 1: return "TxA, RxA/RxB";
      case 2: return "TxB, RxA/RxB";
      case 3: return "Reserved for Future Use";
    }
    return $txrx;
  } // END: function _lu_txrx($txrx)


  function _lu_power($power) { 
    return ($power==1) ? "High" : "Low";
  } // END: function _lu_power($power)


  function _lu_addressed($addressed) { 
    return ($addressed==1) ? "Addressed" : "Broadcast";
  } // END: function _lu_addressed($addressed)


  function _lu_stationtype($stationtype) { 
    switch($stationtype) { 
      case 0: return "All types of mobiles (default)";
      case 1: return "Reserved for future use";
      case 2: return "All types of Class B mobile stations";
      case 3: return "SAR airborne mobile station";
      case 4: return "Aid to Navigation station";
      case 5: return "Class B shipborne mobile station (IEC62287 only)";
      case 6:
      case 7:
      case 8:
      case 9: return "Regional use and inland waterways";
      case 10:
      case 11:
      case 12:
      case 13:
      case 14:
      case 15: return "Reserved for future use";
    }
    return $stationtype;
  } // END: function _lu_stationtype($stationtype)


  function _lu_interval($interval) { 
    switch($interval) { 
      case 0: return "As given by the autonomous mode";
      case 1: return "10 Minutes";
      case 2: return "6 Minutes";
      case 3: return "3 Minutes";
      case 4: return "1 Minute";
      case 5: return "30 Seconds";
      case 6: return "15 Seconds";
      case 7: return "10 Seconds";
      case 8: return "5 Seconds";
      case 9: return "Next Shorter Reporting Interval";
      case 10: return "Next Longer Reporting Interval";
      case 11:
      case 12:
      case 13:
      case 14:
      case 15: return "Reserved for future use";
    }
    return $interval;
  } // END: function _lu_interval($interval)


  function _lu_gnss($gnss) { 
    return ($gnss==1) ? "Not GNSS position (default)" : "Current GNSS position";
  } // END: functino _lu_gnss($gnss)

  /*
  function _lu_dac_fid($dac,$fid) { 
  // http://nmearouter.com/docs/ais/ais_decoder_binary.html
    if( $dac==1 ) { 
      switch($fid) { 
        case 12: return "Dangerous cargo indication";
        case 14: return "Tidal window";
        case 16: return "Num persons on board";
        case 18: return "Clearance time to enter port";
        case 20: return "Berthing data (addressed)";
        case 23: return "Area notice (addressed)";
        case 25: return "Dangerous Cargo indication";
        case 28: return "Route info addressed";
        case 30: return "Text description addressed";
        case 32: return "Tidal Window";
      }
    } else if( $dac==200 ) { 
      switch($fid) { 
        case 21: return "ETA at lock/bridge/terminal";
        case 22: return "RTA at lock/bridge/terminal";
        case 55: return "Number of persons on board";
      }
    } else if( $fid==10 ) { 
      if( $dac==235 ) { 
        return "AtoN monitoring data (UK)";
      }
      if( $dac==250 ) { 
        return "AtoN monitoring data (ROI)";
      }
    }
    return false;
  } // END: function _lu_dac_fid($dac,$fid)
  */


  function _lu_retransmit($retx) { 
    return ($retx) ? "retransmitted" : "no retransmit (default)";
  } // END: function _lu_retransmit($retx)


  function initialize($message) {
    $this->message = ltrim($message,'!$');
    $this->error = null;

    $body = explode(',',$this->message);
    if( count($body) != 7 ) {
      $this->error = "Unexpected NMEA sentence.";
      return false;
    }
    list($parity,$chksm) = explode('*',array_pop($body));
    $this->msgid = $body[0];
    $this->totno = $body[1];
    $this->msgno = $body[2];
    $this->seqno = $body[3];
    $this->channel = $body[4];
    $this->payload = $body[5];
    $this->parity = $parity;
    $this->checksum = $chksm;

    if( !$this->hasValidChecksum() ) {
      $this->error = "Invalid checksum.";
      return false;
    }
    return true;
  } // END: function initialize($message)


  /**
   * Tests to ensure that current message has a valid checksum.
   *
   * @access public
   * @return {boolean} yes or no
   */
  function hasValidChecksum() {
    return (hexdec($this->checksum)==$this->computeChecksum());
  } // END: function hasValidChecksum()


  /**
   * Computes the checksum of the current message.
   *
   * @access public
   * @return {int} checksum
   */
  function computeChecksum() {
    list($str,$chksm) = explode('*',$this->message);
    $chksm = 0;
    for($i=0,$n=strlen($str); $i<$n; $i++) {
      $chksm ^= ord($str{$i});
    }
    return $chksm;
  } // END: function computeChecksum()


  /**
   * @access private
   */
  function sign_unsigned($num,$n_bit) {
    $n_bit = (!$n_bit) ? 28 : $n_bit;

    if($n_bit>7 && $n_bit < 31) {
      $bit = 0x1<<($n_bit-1);
      $inv = 0x0;
      for($i=0;$i<$n_bit;$i++) {
        $inv = $inv<<1 | 0x1;
      }
      if( $num & $bit ) {
        $num = 0 - (((0xffffffff^$num)&$inv)+1);
      }
    }
    return $num;
  } // END: function sign_unsigned($num,$n_bit)


  /**
   * @access private
   */
  function paddedFloat($value,$n_left,$n_right,$ch) {
    $len = $n_left + $n_right + ($n_right>0) ? 1 : 0;
    return sprintf( sprintf("%%%s%d.%df", $ch, $len, $n_right), $value );

    $ch = (!$ch) ? '0' : $ch;
    $sign = ($value<0) ? '-' : '';
    $pts = explode(".",(string)abs($value));
    if( count($pts) > 2 ) {
      return $value;
    }
    while( count($pts) < 2 ) {
      $pts[] = '';
    }

    // handle the whole number portion
    $delta = ($n_left  - strlen($pts[0]));
    if( $delta>0 ) {
      // needs padding
      $pts[0] = str_repeat($ch,$delta) . $pts[0];
    }
    // handle fractional portion
    $delta = ($n_right - strlen($pts[1]));
    if( $delta > 0 ) {
      $pts[1] .= str_repeat($ch,$delta);
    } else {
      $pts[1] = substr($pts[1],0,$n_right);
    }
    return $sign.implode('.',$pts);
  }  // END: function paddedFloat($value,$n_left,$n_right,$ch)

} // END: class AIS_Message



/**
 * @package AIS
 */
class AIS_Sixbit {
  var $data      = '' ; // a byte string
  var $data_ix   = 0;   // index of current data character
  var $remainder = 0;   // bit field remainder (actual bits)
  var $rem_len   = 0;   // number of bits (unprocessed) in remainder
  var $rem_end   = 0;   // number of bits at end of string to ignore
  var $masks     = array( 0x00, 0x01, 0x03, 0x07, 0x0f, 0x1f, 0x3f );

  /**
   * A 6-bit state container for parsing AIS 6-bit messages.  The use of encode
   *  and decode char is what ties this implementation to AIS.
   *
   * @constructor
   * @param {string} data A 6-bit encoded data string.
   * @param {uint} end_state Number of bits to ignore at end of string.
   * @return {object} new sixbit state container.
   */
  function AIS_Sixbit($data,$end_state) {
    $this->initialize($data,$end_state);
    return $this;
  } // END: constructor AIS_Sixbit()


  /**
   * Assign a new 6-bit data payload, resetting state.
   *
   * @access public
   * @param {string} data 6-bit packed data.
   * @param {uint} end_state Number of bits to ignore at end of string.
   * @return {boolean} always true.
   */
  function set($data,$end_state) {
    $data = (!$data) ? '' : $data;
    return $this->initialize($data,$end_state);
  } // END: function set(data)


  /**
   * Appends extra 6-bit data to existing data.  Assumes bits remaining won't
   *  occur in first n-1 messages of an n-part message.
   *
   * @access protected
   * @param {string} data 6-bit packed data.
   * @return {boolean} success or failure.
   */
  function append($other_six) {
    $this->data = $this->data . '' . $other_six->data;
    $this->rem_end = $other_six->rem_end;

    return true;
  } // END: function append(data)


  /**
   * Extracts the requested number of bits from the 6-bit packed data.  The
   *  extraction process is like a DFA (deterministic finite automata) that
   *  can't be accessed out of order.  Once the data has been set, you can
   *  only march forward through the data.  If you need to regrab an earlier
   *  portion of data, you'll have to reset().
   *
   * @access public
   * @param {int} n_bits The number of bits to return, -1 for all remaining.
   * @return {int} The value of the bits at the current state pointer.
   */
  function getBits($n_bits) {
    if( $n_bits < 0 ) {
      $n_bits = $this->bitsRemaining();
    }
    if( $n_bits <= 32 ) {
      return $this->getBits_32($n_bits);
    }

    $str = '';
    while( $n_bits > 0 ) {
      $ch = $this->getBits_32(min($n_bits,8));
      if( $ch == null ) {
        return $str;
      }
      $str .= chr($ch);
      $n_bits -= 8;
    }
    return $str;
  } // END: function getBits(n_bits)


  /**
   * Extracts the requested number of bits from the 6-bit packed data.  The
   *  extraction process is like a DFA (deterministic finite automata) that
   *  can't be accessed out of order.  Once the data has been set, you can
   *  only march forward through the data.  If you need to regrab an earlier
   *  portion of data, you'll have to reset().
   *
   * @access private
   * @param {int} n_bits The number of bits to return [0..32]
   * @return {int} The value of the bits at the current state pointer.
   */
  function getBits_32($n_bits) {
    $bits = 0;
    $bitten = false; // true when bits are extracted

    while( $n_bits > 0 ) {
      // If anything left over from previous call to getBits
      if( $this->rem_len > 0 ) {
        // Yes (leftover)
        // If we need another byte to return n_bits
        if( $this->rem_len <= $n_bits ) {
          // Yes (need byte)
          // slurp up and reset the remainder
          $bits = ($bits << 6) + $this->remainder;
          $n_bits -= $this->rem_len;
          $this->remainder = 0;
          $this->rem_len = 0;
          $bitten = true;
        } else {
          // No (no byte need)
          // scoot return bits over by requested amount
          $bits = $bits << $n_bits;
          // move remainder bits out of high-bit position, then add them in.
          $bits += ($this->remainder >> ($this->rem_len - $n_bits));
          // recount remainder bits available and clear the ones we used.
          $this->rem_len -= $n_bits;
          $this->remainder = $this->remainder & $this->masks[$this->rem_len];
          // n_bits have been read into variable 'bits'.
          return $bits;
        }
      } // end: if( rem_len > 0 ) AKA: if (have remainder)

      if( $this->data_ix < strlen($this->data) ) {
        $this->remainder = $this->decodeByte($this->data{$this->data_ix});
        $this->rem_len = 6;
        $this->data_ix += 1;
      } else {
        // nothing left in data portion, return what we have.
        return ($bitten) ? $bits : null;
      }
    } // end: while( n_bits > 0 )

    return ($bitten) ? $bits : null;
  } // END: function getBits_32(n_bits)


  /**
   * Returns a string of embedded ascii characters.
   *
   * @access public
   * @param {uint} n_bits The number of bits to return.
   * @return {string} An unencoded ascii string.
   */
  function getBitsAscii($n_bits) {
    if( $n_bits%6 != 0 ) {
      return false;
    }
    $str = '';
    for($i=0,$n=$n_bits/6; $i<$n; $i++) {
      $str .= $this->decodeEmbeddedAscii( $this->getBits(6) );
    }
    return trim($str);
  } // END: function getBitsAscii


  /**
   * Performs an ASCII to Binary conversion on the 6-bit packed character as
   * a bloated ASCII character.
   *
   * @access public
   * @param {char} ch An AIS-supported ASCII character.
   * @return {int} A 6-bit number [0..63] (-1 for failure).
   */
  function decodeByte($ch) {
    $ch = ord($ch{0});
    if( $ch < 0x30 || $ch > 0x77 || ($ch > 0x57 && $ch < 0x60) ) {
      return -1;
    }
    if( $ch<0x60 ) {
      return ($ch-0x30)&0x3f;
    }
    return ($ch-0x38)&0x3f;
  } // END: function decodeByte(ch)


  /**
   * Performs a Binary to ASCII conversion on a 6-bit value, to transform it
   *  to the AIS ASCII representation. (perform bit stuffing)
   *
   * @access public
   * @param {int} A 6-bit number [0..63].
   * @return {char} ch An AIS-supported ASCII character (zero for failure).
   */
  function encodeByte($bin) {
    if( $bin > 0x3f ) {
      return 0;
    }
    return ($bin < 0x27) ? chr($bin + 0x30) : chr($bin + 0x38);
  } // END: function encodeByte(bin);



  /**
   * Decode embedded ASCII from within an AIS message.  This is different from
   *  the usual decodeByte because here, the character represent actual data
   *  AIS may also embed ASCII within its messages.  For these embedded ASCII
   *  characters, we simply add an offset where applicable.
   *
   * @access public
   * @param {char} val An unpacked AIS encoded character.
   * @return {char} A printable character which had been previously encoded.
   */
  function decodeEmbeddedAscii($val) {
    $val = $val & 0x3f;
    $val = ($val < 0x20) ? ($val+0x40) : $val;
    return ($val==0x40) ? '' : chr($val);
  } // END: function decodeEmbeddedAscii(ch)


  /**
   * Resets the parser back to the beginning of the data.  Does not clear
   *  the data.  Allows you to parse the data from the beginning, again.
   *
   * @access public
   * @return {boolean} true always.
   */
  function reset() {
    $this->data_ix   = 0;
    $this->remainder = 0;
    $this->rem_len   = 0;
    return true;
  } // END: function reset()


  /**
   * Calculate the number of bits remaining in the string.
   *
   * @access public
   * @return {int} Number of bits left unprocessed in the string.
   */
  function bitsRemaining() {
    // this.remainder stores some unprocessed bits
    // this.rem_len stores the number of these remaining unprocessed bits
    // this.data stores 8-bit characters, each representing only 6 bits
    // this.data_ix points to first unused character in data string
    // this.rem_end is the number of bits to ignore
  return ($this->rem_len+(strlen($this->data)-$this->data_ix)*6-$this->rem_end);
  } // END: function bitsRemaining()


  /**
   * Copy another sixbit object to this one.
   *
   * @access public
   * @param {Sixbit} sixbit A live Sixbit object.
   * @return {boolean} true always.
   */
  function copy($sixbit) {
    $this->data      = $sixbit->data;
    $this->data_ix   = $sixbit->data_ix;
    $this->remainder = $sixbit->remainder;
    $this->rem_len   = $sixbit->rem_len;
    $this->rem_end   = $sixbit->rem_end;
    return true;
  } // END: function coyp(sixbit)


  /**
   * Initializes the sixbit object.
   *
   * @access private
   * @return {boolean} true always.
   */
  function initialize($data,$end_state) {
    $this->data = ($data) ? $data : '';     // zero-terminated byte string
    $this->data_ix   = 0;              // index of current data character
    $this->remainder = 0;              // bit field remainder (actual bits)
    $this->rem_len   = 0;              // num bits (unprocessed) in remainder
    $this->rem_end   = ($end_state)?$end_state:0; // num bits to ignore at end
    return true;
  } // END: function initialize()


} // END: class AIS_Sixbit


/*
$ais = new AIS();
//$ais->onerror('var_dump');
$ais->oncomplete('print_r');
$ais->receive('-b-!AIVDM,1,1,,B,35?I5`1003oS2TvCC@Cju5`:0Dtb,0*3F');
$ais->receive('i-a-!AIVDM,3,1,4,A,88AmaI1KfM4JkIJk0k`gWTN;Hgmg;NGI:H;e:<abTAM,0*3D');
//$ais->receive('!AIVDM,3,2,4,A,Vt9AhaAI=WkPLI1EIfWW1dDCrEW`Qc>GfP>iOmV@Ino,0*43');
$ais->receive('i-4a!AIVDM,3,3,4,A,WgksFfc=0,2*76');
$ais->receive('i--a!AIVDM,1,1,,A,14`V2I0000oRUUJCDFTIcV::0<21,0*7F');
*/

// EOF -- AIS.php
?>
