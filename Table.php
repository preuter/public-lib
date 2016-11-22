<?php
 /**
  * Provide basic functions for loading/storing/scanning/extracting data
  * from a simple delimited table file.
  *
  * @file Table.php
  * @date 2013-08-15 11:36 PDT
  * @author Paul Reuter
  * @version 1.1.6
  *
  * @modifications <pre>
  * 1.0.0 - 2011-08-04 - Created
  * 1.0.1 - 2011-08-07 - Add: apply(mat,callback) to each row, return result.
  * 1.0.2 - 2011-08-08 - Add: flip($mat) transposes a matrix.
  * 1.0.3 - 2011-11-10 - BugFix: apply incorrectly filtering.
  * 1.0.4 - 2012-07-19 - Add: sort(&$mat,$ix0,$ix1..)
  * 1.1.0 - 2012-08-15 - Modify: constructor, static/method types.
  *                      Add: insert/remove/find/replace methods, autodoc.
  * 1.1.1 - 2012-08-15 - Add: applyCell fully recursive callback.
  * 1.1.2 - 2012-08-30 - Add: toString(&$mat,$hdr=null,$cdelim=" ",$rdelim="\n")
  * 1.1.3 - 2012-09-13 - Add: toText(&$mat,$cd,$rd), fromText($text,$cd,$rd)
  * 1.1.4 - 2012-10-22 - Modify: Whitespace for best practices.
  * 1.1.5 - 2013-02-18 - BugFix: m_mkdir failed to mkdir.
  * 1.1.6 - 2013-08-15 - BugFix: remove all self:: resolvers.
  * </pre>
  */



/**
 * @package Core
 * @subpackage Table
 */
class Table { 
  var $_cd = "\t";
  var $_rd = "\n";

  /**
   * Construct new Table object (optional)
   *
   * @param string $cd Column delimiter.
   * @param string $rd Record delimiter.
   * @return Table Instantiated object with insert/remove/etc methods.
   */
  function Table($cd="\t",$rd="\n") { 
    $this->_cd = $cd;
    $this->_rd = $rd;
    return $this;
  } // END: function Table($cd="\t",$rd="\n")


  /**
   * Load a tab-delimited data file.
   *
   * @depricated
   * @public
   * @static
   * @param string $fpath Path to file to read (\t,\n delimited)
   * @return array Matrix of rows by columns.
   */
  function load($fpath,$cd="\t",$rd="\n") { 
    return $this->loadFile($fpath,$cd,$rd);
  } // END: function load($fpath)


  /**
   * Load a delimited data file, semi-static.
   * If either $cd or $rd isn't specified, Table must be instantiated.
   *
   * @public
   * @static
   * @param string $fpath Path to file to read/parse.
   * @param string $cd Column delimiter
   * @param string $rd Record delimiter
   * @return array Matrix of rows by columns
   */
  function loadFile($fpath,$cd=null,$rd=null) { 
    if (!is_readable($fpath)) { 
      return array();
    }
    return $this->fromText(file_get_contents($fpath),$cd,$rd);
  } // END: function loadFile($fpath,$cd=null,$rd=null)


  /**
   * Parse a table from a block of text.
   *
   * @param string $text A matrix, formatted by toText, or stored by Table.
   * @param string $cd Column delimiter.
   * @param string $rd Record delimiter.
   * @return array A matrix or records by columns.
   */
  function fromText($text,$cd=null,$rd=null) { 
    $mat = array();
    $cd = ($cd===null) ? $this->_cd : $cd;
    $rd = ($rd===null) ? $this->_rd : $rd;
    $nl = array("\r\n","\r","\n");
    if (in_array($rd,$nl)) { 
      $text = str_replace($nl,$rd,$text);
    } else if (in_array($cd,$nl) ) { 
      $text = str_replace($nl,$cd,$text);
    }
    if (!empty($text)) { 
      foreach (explode($rd,rtrim($text,$rd)) as $line) { 
        $mat[] = explode($cd,$line);
      }
    }
    return $mat;
  } // END: function fromText($text,$cd=null,$rd=null)


  /**
   * Store a matrix to a file in ascii.
   *
   * @depricated
   * @public
   * @static
   * @param string $fpath Path to file to read/parse.
   * @param array &$mat A matrix of records by columns.
   * @return bool true if file stored, false otherwise.
   * @see storeFile
   */
  function store($fpath,&$mat) { 
    return $this->storeFile($fpath,$mat,"\t","\n");
  } // END: function store($fpath,&$mat)


  /**
   * Store to a file, the matrix, delimited as specified.
   *
   * @param string $fpath Target local file path.
   * @param array &$mat A table of data (rows by columns)
   * @param string $cd Column delimiter
   * @param string $rd Record delimiter
   * @return bool true if file stored, false otherwise.
   */
  function storeFile($fpath,&$mat,$cd=null,$rd=null) { 
    if (!$this->m_mkdir(dirname($fpath))) {
      return false;
    }
    $fp = fopen($fpath,'wb+');
    return !( !$fp || !fwrite($fp,$this->toText($mat,$cd,$rd)) || !fclose($fp) );
  } // END: function store($fpath,&$mat)


  /**
   * Formats a matrix for storage, returns the text.
   *
   * @param array &$mat Array of rows.
   * @param string $cd Column delimiter
   * @param string $rd Record delimiter
   * @return string Text formatted for storage.
   */
  function toText(&$mat,$cd=null,$rd=null) { 
    $cd = ($cd===null) ? $this->_cd : $cd;
    $rd = ($rd===null) ? $this->_rd : $rd;
    $txt = '';
    foreach ($mat as $row) {
      $txt .= implode($cd,$row).$rd;
    }
    return $txt;
  } // END: function toText(&$mat,$cd=null,$rd=null)



  /**
   * Alias for getCol
   * @see getCol
   */
  function col(&$mat,$ix,$row0=0) { 
    return $this->getCol($mat,$ix,$row0);
  } // END: function col(&$mat,$ix,$row0=0)


  /**
   * Extract a column vector from the matrix.
   *
   * @public
   * @static
   * @param array &$mat Source matrix to read from.
   * @param uint $ix Column index to extract.
   * @param uint $row0 Starting row, effectively number of rows skipped.
   * @return array A column vector, where a[i] is the mat[i][ix]'th value.
   * @see getRow
   * @see getRows
   * @see getCols
   */
  function getCol(&$mat,$ix,$row0=0) { 
    $vect = array();
    foreach ($mat as $row) { 
      if ($row0>0) { 
        $row0--;
        continue;
      }
      $vect[] = (isset($row[$ix])) ? $row[$ix] : null;
    }
    return $vect;
  } // END: function col(&$mat,$ix,$row0=0)


  /**
   * Alias for getCols
   * @see getCols
   */
  function cols(&$mat,$ixs,$row0=0) { 
    return $this->getCols($mat,$ixs,$row0);
  } // END: function cols(&$mat,$ixs,$row0)


  /**
   * Extract multiple column vectors from the matrix.
   *
   * @public
   * @static
   * @param array &$mat Source matrix to read from.
   * @param array $ixs Array of uint indexes to extract.
   * @param uint $row0 Starting row, effectively number of rows skipped.
   * @return array Array of column vectors, where a[i][j] = mat[ixs[i]][j].
   * @see getRow
   * @see getRows
   * @see getCol
   */
  function getCols(&$mat,$ixs,$row0=0) { 
    $sub = array();
    foreach ($mat as $row) { 
      if ($row0 > 0) { 
        $row0--;
        continue;
      }
      $tmp = array();
      foreach ($ixs as $ix) { 
        $tmp[] = (isset($row[$ix])) ? $row[$ix] : null;
      }
      $sub[] = $tmp;
    }
    return $sub;
  } // END: function cols(&$mat,$ixs,$row0=0)


  /**
   * Return the $ix'th row of &$mat.
   *
   * @public
   * @static
   * @param array &$mat Matrix.
   * @param uint $ix Row index.
   * @return array $mat[$ix].
   * @see getRows
   * @see getCol
   * @see getCols
   */
  function getRow(&$mat,$ix) { 
    return (isset($mat[$ix])) ? $mat[$ix] : null;
  } // END: function getRow(&$mat,$ix)


  /**
   * Returns a subset matrix of rows identified by $ixs.
   *
   * @public
   * @static
   * @param array &$mat Matrix.
   * @param array $ixs Array of uint row indexes.
   * @return array Matrix of [mat[ixs[0]],mat[ixs[1]]...]
   * @see getRow
   * @see getCol
   * @see getCols
   */
  function getRows(&$mat,$ixs) { 
    $sub = array();
    foreach ($ixs as $ix) { 
      $sub[] = (isset($mat[$ix])) ? $mat[$ix] : null;
    }
    return $sub;
  } // END: function getRows(&$mat,$ixs)


  /**
   * Insert a column vector into a matrix.
   *
   * @public
   * @static
   * @param array &$mat Destination matrix.
   * @param array $col A column vector.
   * @param uint $ix Target column index. 
   *                 Elements currently at this index are pushed to the right.
   *                 If not specified, appends the column.
   * @return array A modified matrix.
   * @see putCols
   * @see putRow
   * @see putRows
   */
  function putCol(&$mat,$col,$ix=null) { 
    $ix = ($ix===null) ? count(current($mat)) : $ix;
    foreach (array_keys($mat) as $rix) { 
      array_splice($mat[$rix],$ix,0,array($col[$rix]));
    }
    return $mat;
  } // END: function putCol(&$mat,$col,$ix=null)

  
  /**
   * Insert multiple column vectors into a matrix.
   *
   * @public
   * @static
   * @param array &$mat Destination matrix.
   * @param array $cols Array of column vectors. $cols[1] inserts at $ix+1.
   * @param uint $ix Target column index. 
   *                 Elements currently at this index are pushed to the right.
   *                 If not specified, appends the columns.
   * @return array A modified matrix.
   * @see putCol
   * @see putRow
   * @see putRows
   */
  function putCols(&$mat,$cols,$ix=null) { 
    $ix = ($ix===null) ? count(current($mat)) : $ix;
    foreach (array_keys($mat) as $rix) { 
      array_splice($mat[$rix],$ix,0,$cols[$rix]);
    }
    return $mat;
  } // END: function putCols(&$mat,$cols,$ix=null)


  /**
   * Insert a row into the matrix.
   *
   * @public
   * @static
   * @param array &$mat The destination matrix.
   * @param array $row The row to insert.
   * @param uint $ix Target row index. if not set, appends row.
   * @return array A modified matrix.
   * @see putCol
   * @see putCols
   * @see putRows
   */
  function putRow(&$mat,$row,$ix=null) { 
    $ix = ($ix===null) ? count($mat) : $ix;
    array_splice($mat,$ix,0,array($row));
    return $mat;
  } // END: function putRow(&$mat,$row,$ix=null)


  /**
   * Insert multiple rows into the matrix.
   *
   * @public
   * @static
   * @param array &$mat The destination matrix.
   * @param array $rows A set of rows to insert/append.
   * @param uint $ix Target row index. if not set, appends rows.
   * @return array A modified matrix.
   * @see putCol
   * @see putCols
   * @see putRow
   */
  function putRows(&$mat,$rows,$ix=null) { 
    $ix = ($ix===null) ? count($mat) : $ix;
    array_splice($mat,$ix,0,$rows);
    return $mat;
  } // END: function putRows(&$mat,$rows,$ix=null)


  /**
   * Search the table for a row matching $keys key-value hash.
   *
   * @public
   * @static
   * @param array &$mat Source matrix to search. First row is header.
   * @param array $keys A key=>value hash.
   * @param bool $hasHeader true if first row is header row, false to use int.
   * @return array Indexes of rows where each row[ix(keys[i])] == keys[i] 
   * @see removeByKey
   * @see replaceByKeyValue
   * @see replaceByKeyRow
   */
  function findByKey(&$mat,$keys,$hasHeader=true) {
    reset($mat);
    $ixs = array();
    $rixs = array_keys($mat);
    if ($hasHeader) { 
      $ihdr = array_flip($mat[array_shift($rixs)]);
    } else { 
      $ihdr = range(0,count(current($mat))-1);
    }
    // foreach data row in $mat
    foreach ($rixs as $rix) {
      $row = $mat[$rix];
      // Check that keys match the row
      $isMatch = true;
      foreach (array_keys($keys) as $k) {
        if ($row[$ihdr[$k]] !== $keys[$k]) {
          $isMatch = false;
          break;
        }
      }
      if ($isMatch) {
        $ixs[] = $rix;
      }
    }
    return $ixs;
  } // END: function findByKey(&$mat,$keys)


  /**
   * Remove rows from $mat matching $keys key-value hash.
   *
   * @public
   * @static
   * @param array &$mat Source matrix to search.
   * @param array $keys A key=>value hash.
   * @param bool $hasHeader true if first row is header row, false to use int.
   * @return array Matrix of rows removed.
   * @see findByKey
   * @see replaceByKeyValue
   * @see replaceByKeyRow
   */
  function removeByKey(&$mat,$keys,$hasHeader=true) {
    $ixs = $this->findByKey($mat,$keys,$hasHeader);
    $rem = array();
    rsort($ixs);
    foreach ($ixs as $ix) {
      $rem[] = array_splice($mat,$ix,1);
    }
    return $rem;
  } // END: function removeByKey(&$mat,$keys,$hasHeader=true)


  /**
   * For each row matching $keys hash, replace values with $vals hash.
   *
   * @public
   * @static
   * @param array &$mat Source matrix to search/replace.
   * @param array $keys A key=>value search hash.
   * @param array $vals A key=>value replace hash.
   * @param bool $hasHeader true if first row is header row, false to use int.
   * @return array Matrix of original records before modification.
   * @see findByKey
   * @see removeByKey
   * @see replaceByKeyRow
   */
  function replaceByKeyValue(&$mat,$keys,$vals,$hasHeader=true) {
    $ixs = $this->findByKey($mat,$keys,$hasHeader);
    $rem = array();
    foreach ($ixs as $rix) {
      $rem[] = $mat[$rix];
      $row = $mat[$rix];
      foreach (array_keys($vals) as $k) {
        $row[$k] = $vals[$k];
      }
      $mat[$rix] = $row;
    }
    return $rem;
  } // END: function replaceByKeyValue(&$mat,$keys,$vals,$hasHeader=true)


  /**
   * For each row matching $keys hash, replace entire row with $row record.
   *
   * @public
   * @static
   * @param array &$mat Source matrix to search/replace.
   * @param array $keys A key=>value search hash.
   * @param array $row A row of values.
   * @param bool $hasHeader true if first row is header row, false to use int.
   * @return array Matrix of original records before modification.
   * @see replaceByKeyValue
   * @see insertByKeyRow
   * @see putRow
   */
  function replaceByKeyRow(&$mat,$keys,$row,$hasHeader=true) {
    return $this->insertByKeyRow($mat,$keys,$row,true,$hasHeader);
  } // END: function replaceByKeyRow(&$mat,$keys,$row)


  /**
   * If $keys not found, appends $row to $mat, else replaces existing or fails.
   *
   * @public
   * @static
   * @param array &$mat Source matrix to search/replace/append.
   * @param array $keys A key=>value search hash.
   * @param array $row A row of values to insert.
   * @param bool $overwrite Signal to replace existing rows matching $keys.
   *                        If false and matches found, method returns false.
   * @param bool $hasHeader true if first row is header row, false to use int.
   * @return mixed false if $overwrite==false and $keys matched.
   *               array Matrix of original values before modification.
   * @see replaceByKeyRow
   * @see findByKeys
   * @see putRow
   * @see putRows
   */
  function insertByKeyRow(&$mat,$keys,$row,$overwrite=false,$hasHeader=true) {
    $ixs = $this->findByKey($mat,$keys,$hasHeader);
    $rem = array();
    if (empty($ixs)) { 
      $mat[] = $row;
      return $rem;
    }
    if (!$overwrite) { 
      return false;
    }
    foreach ($ixs as $ix) {
      $rem[] = $mat[$ix];
      $mat[$ix] = $row;
    }
    return $rem;
  } // END: function insertByKeyRow(&$mat,$keys,$row,$overwrite=0,$hasHeader=1)


  /**
   * Apply a filter function to each row in &$mat
   *
   * @param array &$mat A matrix of rows-by-columns.
   * @param callback $callback Accepts a row, +optional args, returns bool.
   * @param mixed $arg0 Argument to pass to filter callback.
   * @return array Matrix of rows where filter callback returned non-false.
   * @see apply
   *
   * Example: <pre>
   *   function cb($row,$ihdr) { return (rand(0,1)==0); }
   *   $mat = Table::loadFile($fpath);
   *   $ihdr = array_flip(array_shift($mat));
   *   $sub = Table::filter($mat,'cb',$ihdr);
   * </pre>
   */
  function filter(&$mat,$callback) { 
    if (func_num_args() < 3) { 
      return array_filter($mat,$callback);
    }
    $args = array(null);
    for ($i=2,$n=func_num_args(); $i<$n; $i++) { 
      $args[] = func_get_arg($i);
    }
    $sub = array();
    foreach (array_keys($mat) as $rix) { 
      $row = $mat[$rix];
      $args[0] = $row;
      if (call_user_func_array($callback,$args)) { 
        $sub[$rix] = $row;
      }
    }
    return $sub;
  } // END: function filter(&$mat,$callback,$arg0,$arg1...)


  /**
   * Apply a callback to each row in &$mat
   *
   * @param array &$mat A matrix of rows-by-columns.
   * @param callback $callback A callable function that accepts a row.
   * @return array A matrix altered by the callback on row-by-row basis.
   * @see filter
   * @see applyCell
   *
   * Example: <pre>
   *   function cb($row,$ix) { $row[$ix] = 0; return $row; }
   *   $mat = Table::loadFile($fpath);
   *   $ihdr = array_flip(array_shift($mat));
   *   $mat = Table::apply($mat,'cb',$ihdr);
   * </pre>
   */
  function apply(&$mat,$callback) { 
    $sub = array();
    if (func_num_args() < 3) { 
      foreach (array_keys($mat) as $i) { 
        $sub[$i] = call_user_func($callback,$mat[$i]);
      }
      return $sub;
    }
    $args = array(null);
    for ($i=2,$n=func_num_args(); $i<$n; $i++) { 
      $args[] = func_get_arg($i);
    }
    foreach (array_keys($mat) as $rix) { 
      $row = $mat[$rix];
      $args[0] = $row;
      $sub[$rix] = call_user_func_array($callback,$args);
    }
    return $sub;
  } // END: function apply(&$mat,$callback,$arg0,$arg1...)


  /**
  /**
   * Apply a callback to each cell in &$mat
   *
   * @param array &$mat A matrix of rows-by-columns.
   * @param callback $callback A callable function that accepts a cell.
   * @return array A matrix altered by callback on each cell. mat[rix][cix]
   * @see apply
   *
   * Example: <pre>
   *   $mat = Table::loadFile($fpath);
   *   $mat = Table::applyCell($mat,'strip_tags');
   * </pre>
   */
  function applyCell(&$mat,$callback) { 
    $sub = array();
    if (func_num_args() < 3) { 
      foreach (array_keys($mat) as $i) { 
        $row = $mat[$i];
        foreach (array_keys($row) as $j) { 
          $row[$j] = call_user_func($callback,$row[$j]);
        }
        $sub[$i] = $row;
      }
      return $sub;
    }
    $args = array(null);
    for ($i=2,$n=func_num_args(); $i<$n; $i++) { 
      $args[] = func_get_arg($i);
    }
    foreach (array_keys($mat) as $rix) { 
      $row = $mat[$rix];
      foreach (array_keys($row) as $cix) { 
        $args[0] = $row[$cix];
        $row[$cix] = call_user_func_array($callback,$args);
      }
      $sub[$rix] = $row;
    }
    return $sub;
  } // END: function applyCell(&$mat,$callback,$arg0,$arg1...)


  /**
   * Transpose a matrix
   *
   * @public
   * @static
   * @param array &$mat A matrix of rows-by-columns.
   * @return array A matrix of cols-by-rows.
   */
  function flip($mat) {
    $out = array();
    foreach ($mat as $rix=>$row) {
      foreach ($row as $cix=>$col) {
        if (!isset($out[$cix]) ) {
          $out[$cix] = array();
        }
        $out[$cix][$rix] = $col;
      }
    }
    return $out;
  } // END: function flip($mat)


  /**
   * Get the size of the matrix (nrows,ncols)
   *
   * @public
   * @static
   * @param array &$mat Source matrix.
   * @return array (nrows,ncols).
   * @see size
   */
  function sizeRC(&$mat) { 
    return array(count($mat),count(current($mat)));
  } // END: function sizeRC(&$mat)


  /**
   * Sort a table. Convenience function.
   *
   * @param array &$mat A matrix of rows-by-columns.
   * @param uint $ix0 First column to sort by.
   * @param uint $ix1 Second column to sort by...
   * @param uint ... 
   * @return bool result of usort.
   */
  function sort(&$mat) { 
    $cixs = array();
    for ($i=1,$n=func_num_args(); $i<$n; $i++) { 
      $arg = func_get_arg($i);
      if (is_array($arg)) { 
        $cixs = array_merge($cixs,$arg);
      } else { 
        $cixs[] = $arg;
      }
    }
    $this->_sort_cixs = $cixs;
    $b = usort($mat,array($this,'_sort'));
    unset($this->_sort_cixs);
    return $b;
  } // END: function sort(&$mat)


  /**
   * Display a matrix in clean ascii
   *
   * @access public
   * @static
   * @param array &$mat A matrix of values.
   * @param array $hdr An optional header not used in justification step.
   * @param string $cdelim Text to implode columns with.
   * @param string $rdelim Text to implode records with.
   * @return string Pretty text for display.
   */
  function toString(&$mat,$hdr=null,$cdelim="  ",$rdelim="\n") {
    if (!is_array($mat) ) {
      return false;
    }
    // Compute max column width 
    $lens = array();
    $justify = array();
    if (is_array($hdr)) {
      foreach (array_keys($hdr) as $i) {
        if (!isset($lens[$i])) {
          $lens[$i] = strlen($hdr[$i]);
        } else {
          $lens[$i] = max($lens[$i],strlen($hdr[$i]));
        }
      }
    }
    foreach ($mat as $row) {
      foreach (array_keys($row) as $i) {
        if (!isset($lens[$i])) {
          $lens[$i] = strlen($row[$i]);
        } else {
          $lens[$i] = max($lens[$i],strlen($row[$i]));
        }
        if (!isset($justify[$i])) {
          // Right-justify all numeric columns.
          $justify[$i] = '+';
        }
        if (!empty($row[$i]) && !is_numeric($row[$i])) {
          $justify[$i] = '-';
        }
      }
    }
    // Generate sprintf format strings (right-justified)
    foreach (array_keys($lens) as $i) {
      $lens[$i] = '%'.$justify[$i].(int)$lens[$i].'s';
    }
    // Generate formatted text, row-by-row.
    $body = '';
    if (is_array($hdr)) {
      foreach (array_keys($lens) as $i) {
        $hdr[$i] = sprintf($lens[$i],(isset($hdr[$i])) ? $hdr[$i] : '');
      }
      $body .= implode($cdelim,$hdr).$rdelim;
    }
    foreach ($mat as $row) {
      foreach (array_keys($lens) as $i) {
        $row[$i] = sprintf($lens[$i],(isset($row[$i])) ? $row[$i] : '');
      }
      $body .= implode($cdelim,$row).$rdelim;
    }
    return $body;
  } // END: function toString(&$mat,$hdr,$cdelim="  ",$rdelim="\n")



  /**
   * Compare 2 elements.
   *
   * @private
   * @param array $a first paramter to compare against $b
   * @param array $b second parameter to compare against $a
   * @return int -1 if a < b. +1 if a > b. 0 if equal.
   */
  function _sort($a,$b) { 
    foreach ($this->_sort_cixs as $i) { 
      if ($a[$i]<$b[$i]) { 
        return -1;
      } else if ($a[$i]>$b[$i]) { 
        return +1;
      }
    }
    return 0;
  } // END: function _sort($a,$b)


  /**
   * Recursively mkdir.
   *
   * @private
   * @param string $dpath Directory path to create if not present.
   * @return bool true if directory exists after mkdir, false otherwise.
   */
  function m_mkdir($dpath) {
    if( empty($dpath) || is_dir($dpath) ) { 
      return true;
    }
    return ($this->m_mkdir(dirname($dpath)) && mkdir($dpath));
  } // END: function m_mkdir($dpath)

} // END: class Table

// EOF -- Table.php
?>
