<?php
 /**
  * Implementation of a priority queue.
  *
  * @example <pre>
  *   $pq = new PriorityQueue();
  *   for($value=0; $value<10; $value++) { 
  *     $priority = rand(0,100);
  *     $pq->enqueue($value, $priority);
  *   }
  *   while( ($row=$pq->dequeue())!==false ) { 
  *     // list($value, $priority) = $row;
  *     print_r($row);
  *   }
  * </pre>
  * 
  * @file PriorityQueue.php
  * @date 2014-08-21 15:58 PDT
  * @author Paul Reuter
  * @version 1.2.0
  *
  * @modifications <pre>
  * 1.0.0 - 2014-07-02 - Created from template: phpclass
  * 1.0.1 - 2014-07-03 - shiftUp/Down split. Add example, docs.
  * 1.0.2 - 2014-07-03 - enqueue/dequeue/setCompare API
  * 1.1.0 - 2014-08-06 - Real Priority Queue.
  * 1.2.0 - 2014-08-21 - BugFix: return tuples. objects cannot be keys.
  * </pre>
  */




/**
 * Implementation of a priority queue.
 * @package Core
 */
class PriorityQueue {
  /**
   * Priority/SortKey
   * @access protected
   */
  var $qkeys;
  /**
   * Object/Payload
   * @access protected
   */
  var $queue;
  /**
   * Tracks insert numbers.
   */
  var $seque;
  /**
   * Insert sequence number.
   * @access private
   */
  var $seqno;
  /**
   * public int Callback($a,$b) returning -1,0,+1 sort order.
   * @access protected
   */
  var $comparitor;


  /**
   * Create a Priority Queue
   *
   * @constructor
   */
  function PriorityQueue() {
    $this->qkeys = array();
    $this->queue = array();
    $this->seque = array();
    $this->seqno = 0;
    $this->setCompare(array($this,'_asc'));
    return $this;
  } // END: constructor PriorityQueue()


  /**
   * Place smaller numbers at the front of the queue.
   *
   * @access public
   * @return bool true on success, false otherwise.
   */
  function smallestFirst() { 
    $this->setCompare(array($this,'_asc'));
  } // END: function smallestFirst()


  /**
   * Place larger numbers at the front of the queue.
   *
   * @access public
   * @return bool true on success, false otherwise.
   */
  function largestFirst() { 
    $this->setCompare(array($this,'_desc'));
  } // END: function largestFirst()


  /**
   * Establish a comparison function via callback.
   *
   * @access public
   * @param callback $callback Method taking two keys, returning -1,0,+1
   * @return bool true if callable, false otherwise.
   */
  function setCompare($callback) { 
    $this->comparitor = $callback;
    return (is_callable($callback));
  } // END: function setCompare($callback)


  /**
   * Counts the number of elements in the heap.
   *
   * @access public
   * @return uint Number of items in queue.
   */
  function size() { 
    return count($this->queue);
  } // END: function size()


  /**
   * Return current node pointed by the iterator
   *
   * @access public
   * @return tuple (value, priority)
   * @see dequeue
   */
  function peek() {
    return $this->at(0);
  } // END: function current()


  /**
   * Extracts a node from top of the heap and sift down.
   *
   * @access public
   * @return mixed false if empty, else tuple (value, priority)
   * @see peek
   */
  function dequeue() {
    $n = $this->size();
    if( $n < 1 ) { 
      return false;
    }

    // Replace the root of the heap with the last element on the last level.
    $k = array_shift($this->qkeys);
    $v = array_shift($this->queue);
    $s = array_shift($this->seque);
    if( --$n > 0 ) { 
      // Replace the root of the heap with the last element on the last level.
      array_unshift($this->qkeys,array_pop($this->qkeys));
      array_unshift($this->queue,array_pop($this->queue));
      array_unshift($this->seque,array_pop($this->seque));
      $pix = $this->_shiftDown(0);
    }

    return array($v, $k); // (value, priority)
  } // END: function dequeue()


  /**
   * Inserts an element in the heap by sifting it up.
   * If $v is omitted, $k is treated as the value to insert.
   * It is expected that a custom compare function is assigned.
   *
   * @access public
   * @param mixed $value Value|Ojbect|Payload of item in queue.
   * @param int $priority Key|Priority of item being inserted.
   * @return uint Index where item was inserted.
   */
  function enqueue($value, $priority=null) { 
    $ix = $this->size();
    if( $priority===null ) { 
      //$priority = (empty($this->qkeys)) ? 0 : end($this->qkeys);
      $priority = $value;
    }
    $this->seque[] = $this->seqno++;
    $this->qkeys[] = $priority;
    $this->queue[] = $value;
    return $this->_shiftUp($ix);
  } // END: function enqueue($value, $priority=null)


  /**
   * Checks whether the heap is empty.
   *
   * @access public
   * @return bool true if empty, false otherwise.
   */
  function isEmpty() {
    return (empty($this->queue)) ? true : false;
  } // END: function isEmpty()
  

  /**
   * Compare 2 objects by key/priority. Uses callback.
   *
   * @access protected
   * @return int -1,0,+1 for ordering a and b.
   */
  function compare($a,$b) { 
    return call_user_func($this->comparitor,$a,$b);
  } // END: function compare($a,$b)


  /**
   * Return current node index
   *
   * @access protected
   * @param uint $ix Index of node to obtain value of.
   * @return mixed tuple (value, priority) at $ix, or false if not found.
   */
  function at($ix=0) { 
    if( isset($this->queue[$ix]) ) { 
      return array($this->queue[$ix], $this->qkeys[$ix]);
    }
    return false;
  } // END: function at($ix=0)


  /**
   * Return current node index
   *
   * @access protected
   * @param uint $ix Index of node to obtain value of.
   * @return mixed The priority of node/object/item at index $ix.
   */
  function priorityAt($ix=0) { 
    return (isset($this->qkeys[$ix])) ? $this->qkeys[$ix] : false;
  } // END: function priorityAt($ix=0)

  /**
   * Return current node index
   *
   * @access protected
   * @param uint $ix Index of node to obtain value of.
   * @return mixed The value of node/object/item at index $ix.
   */
  function valueAt($ix=0) { 
    return (isset($this->queue[$ix])) ? $this->queue[$ix] : false;
  } // END: function valueAt($ix=0)


  /**
   * Return index where item was inserted.
   *
   * @access private
   * @param uint $ix Index of child node to promote to validate heap.
   * @return uint Index where child rests in queue upon success.
   */
  function _shiftUp($ix) { 
    $pix = $this->parentOf($ix);
    // bring the preferred priority to the front.
    while( $this->compare($this->priorityAt($pix),$this->priorityAt($ix))>0 ) { 
      $this->_swap($ix,$pix);
      $ix = $pix;
      $pix = $this->parentOf($ix);
    }
    return $ix;
  } // END: function _shiftUp($ix)


  /**
   * Compare parent at $pix to children, sift down until valid queue.
   * 
   * Called on dequeue, which replaces first priority with last prior to sift.
   * We must push this down the queue until priority strictly less than both
   * children, since technically, this item was added after other children.
   *
   * @access private
   * @param uint $pix Parent index to compare against children.
   * @return uint Index where parent rests in a stable heap.
   */
  function _shiftDown($pix=0) { 
    $n = $this->size();
    $lix = $this->leftChildOf($pix);
    while( $lix < $n ) { 
      // Compare the new root with its children;
      $rix = $lix+1;
      if( $this->compare($this->priorityAt($pix),$this->priorityAt($lix)) >= 0
      ||  ($rix<$n
          && $this->compare($this->priorityAt($pix),$this->priorityAt($rix))>=0)
      ) {
        // parent out of order with children.
        // identify which child should be swapped. then swap.
        if($rix<$n 
        && (($this->compare($this->priorityAt($lix),$this->priorityAt($rix))==0
            && $this->seque[$lix]>$this->seque[$rix])
            //&& $this->_seqGT($this->seque[$lix],$this->seque[$rix]))
        || $this->compare($this->priorityAt($lix),$this->priorityAt($rix))>0)) {
          // right child is present AND right child is preferred.
          // If left and right have equal priority, right was first in queue.
          //   Since right first in queue, move to parent (first).
          $this->_swap($pix,$rix);
          $pix = $rix;
        } else { 
          $this->_swap($pix,$lix);
          $pix = $lix;
        }
      } else { 
        // if they are in the correct order, stop.
        break;
      }
      // return to the previous step.
      $lix = $this->leftChildOf($pix);
    } // end while leftChildIx < length of queue.
    return $pix;
  } // END: function _shiftDown($pix=0)


  /**
   * Return index of the left child for the node at index $ix.
   * NB: Does not check if child exists.
   *
   * @access protected
   * @param uint $ix Index of parent node.
   * @return uint Index of left child, whether exists or not.
   */
  function leftChildOf($ix) { 
    // 0 -> 1; 1 -> 3; 2 -> 5; 5 -> 11 
    return 1+($ix<<1);
  } // END: function leftChildOf($ix)


  /**
   * Return index of the parent of the node at index $ix.
   *
   * @access protected
   * @param uint $ix Index of child node.
   * @return uint Index of parent.
   */
  function parentOf($ix) { 
    return $ix>>1;
  } // END: function parentOf($ix)


  /**
   * Swap two objects at positions $i0 and $i1.
   *
   * @access private
   * @param uint $i0 Index of first item to swap.
   * @param uint $i1 Index of second item to swap.
   * @return bool always true.
   */
  function _swap($i0,$i1) { 
    $k = $this->qkeys[$i0];
    $v = $this->queue[$i0];
    $s = $this->seque[$i0];
    $this->qkeys[$i0] = $this->qkeys[$i1];
    $this->queue[$i0] = $this->queue[$i1];
    $this->seque[$i0] = $this->seque[$i1];
    $this->qkeys[$i1] = $k;
    $this->queue[$i1] = $v;
    $this->seque[$i1] = $s;
    return true;
  } // END: function swap($i0,$i1)


  /**
   * Default comparison function.
   *
   * @access private
   * @return int -1 if a<b; +1 if a>b; 0 otherwise.
   */
  function _asc($a,$b) {
    return ($a<$b) ? -1 : (($a>$b) ? +1 : 0);
  } // END: function _asc($a,$b)


  /**
   * Default comparison function.
   *
   * @access private
   * @return int +1 if a<b; -1 if a>b; 0 otherwise.
   */
  function _desc($a,$b) {
    return ($a<$b) ? +1 : (($a>$b) ? -1 : 0);
  } // END: function _desc($a,$b)


} // END: class PriorityQueue


// EOF -- PriorityQueue.php
?>
