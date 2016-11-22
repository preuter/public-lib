<?php
 /**
  * A really simple "Really Simple Syndication" class for generating RSS feeds.
  *
  * @file RSS.php
  * @date 2013-09-16 13:16 PDT
  * @author Paul Reuter
  * @version 1.1.0
  *
  * @modifications <pre>
  * 1.0.0 - 2010-09-17 - Created
  * 1.0.1 - 2010-09-19 - Added item accessor methods; bug fixes
  * 1.0.2 - 2010-09-22 - Made RSS ATOM compatable: setSelfUrl(url), newlines
  * 1.0.3 - 2010-09-22 - m_autoSetPubDate() thunderbird compatability.
  * 1.0.4 - 2010-09-23 - addItem(s) supports RSS limit.
  * 1.0.5 - 2010-10-27 - BugFix: toString() -> __toString()
  * 1.1.0 - 2013-09-16 - Modify: setSelfUrl -> setAtomUrl. triggers setLink.
  * </pre>
  */


/**
 * @package RSS
 */
class RSS { 
  /**
   * @access private
   */
  var $title = '';
  /**
   * @access private
   */
  var $link = '';
  /**
   * @access private
   */
  var $description = '';

  /**
   * @access private
   */
  var $language = 'en-US';
  /**
   * @access private
   */
  var $copyright = '';
  /**
   * @access private
   */
  var $managingEditor = '';
  /**
   * @access private
   */
  var $webMaster = 'preuter@gmail.com (Paul Reuter)';
  /**
   * @access private
   */
  var $pubDate = null;
  /**
   * @access private
   */
  var $lastBuildDate = null;
  /**
   * @access private
   */
  var $categories = array();
  /**
   * @access private
   */
  var $generator = "Paul's Really-Simple RSS Generator";
  /**
   * @access private
   */
  var $docs = 'http://blogs.law.harvard.edu/tech/rss';
  /**
   * @access private
   */
  var $cloud = null;
  /**
   * @access private
   */
  var $ttl = null;
  /**
   * @access private
   */
  var $image = null;
  /**
   * @access private
   */
  var $rating = null;
  /**
   * @access private
   */
  var $textInput = null;
  /**
   * @access private
   */
  var $skipHours = null;
  /**
   * @access private
   */
  var $skipDays = null;

  /**
   * @access protected
   */
  var $items = array();
  /**
   * @access private
   */
  var $encoding='UTF-8';
  /**
   * @access private
   */
  var $atomUrl = null;
  /**
   * @access private
   */
  var $limit = 0;


  /**
   * @access public
   */
  function RSS() { 
    $this->lastBuildDate = time();
    return $this;
  } // END: function RSS()


  /**
   * @access public
   */
  function setTitle($t) { 
    $this->title = $t;
    return (is_string($t));
  }

  /**
   * @access public
   */
  function setLink($link) { 
    $this->link = $link;
    return (is_string($link) && strpos($link,'://') > 0);
  }

  /**
   * @access public
   */
  function setDescription($desc) { 
    $this->description = $desc;
    return (is_string($desc));
  }

  /**
   * @access public
   */
  function setLanguage($lang) { 
    $this->language = $lang;
    return (is_string($lang));
  }

  /**
   * @access public
   */
  function setCopyright($copy) { 
    $this->copyright = $copy;
    return (is_string($copy));
  }

  /**
   * @access public
   */
  function setManagingEditor($me) { 
    $this->managingEditor = $me;
    return (is_string($me));
  }

  /**
   * @access public
   */
  function setWebMaster($wm) { 
    $this->webMaster = $wm;
    return (is_string($wm));
  }

  /**
   * @access public
   */
  function setPubDate($ts) { 
    if( (string)$ts===(string)intVal($ts) ) {
      $this->pubDate = intVal($ts);
      return true;
    }
    $ts0 = strtotime($ts);
    if( $ts0===false || $ts0===-1 ) { 
      return false;
    }
    $this->pubDate = $ts0;
    return true;
  } // END: function setPubDate($ts)

  /**
   * @access public
   */
  function setLastBuildDate($ts) { 
    if( (string)$ts===(string)intVal($ts) ) {
      $this->lastBuildDate = intVal($ts);
      return true;
    }
    $ts0 = strtotime($ts);
    if( $ts0===false || $ts0===-1 ) { 
      return false;
    }
    $this->lastBuildDate = $ts0;
    return true;
  } // END: function setLastBuildDate($ts)

  /**
   * @access public
   */
  function setCategory($cat,$domain=null) { 
    $this->categories = array( array($cat,$domain) );
    return (is_string($cat) && ($domain===null || is_string($domain)));
  } // END: function setCategory($cat)

  /**
   * @access public
   */
  function addCategory($cat,$domain=null) { 
    $this->categories[] = array($cat,$domain);
    return (is_string($cat) && ($domain===null || is_string($domain)));
  } // END: function addCategory($cat)

  /**
   * @access public
   */
  function setGenerator($gen) { 
    $this->generator = $gen;
    return (is_string($gen));
  }

  /**
   * @access public
   */
  function setDocs($docs) { 
    $this->docs = $docs;
    return (is_string($docs));
  }

  /**
   * @access public
   */
  function setCloud($domain,$port,$path,$proc,$proto) { 
    $this->cloud = array($domain,$port,$path,$proc,$proto);
    return (
      is_string($domain)
      && (string)$port===(string)intVal($port)
      && is_string($path)
      && is_string($proc)
      && is_string($proto)
    );
  }

  /**
   * @access public
   */
  function setTTL($ttl=60) { 
    $this->ttl = $ttl;
    return ( (string)$ttl===(string)intVal($ttl) && $ttl>0 );
  }

  /**
   * @access public
   */
  function setImage($url,$title,$link,$wid=88,$hei=31) { 
    $this->image = array($url,$title,$link,$desc,$wid,$hei);
    return(
      is_string($url) && strpos($url,'://') > 0
      && is_string($title)
      && is_string($link)
      && ($desc===null || is_string($desc))
      && ($wid===null || ($wid > 0 && $wid <= 144))
      && ($hei===null || ($hei > 0 && $hei <= 400))
    );
  }

  /**
   * @access public
   */
  function setRating($rate) { 
    $this->rating = $rate;
    return (is_string($rate));
  }

  /**
   * @access public
   */
  function setTextInput($title,$desc,$name,$link) { 
    $this->textInput = array($title,$desc,$name,$link);
    return (
      is_string($title)
      && is_string($desc)
      && is_string($name)
      && is_string($link) && strpos($link,'://') > 0
    );
  }

  /**
   * @access public
   */
  function setSkipHours() {
    $this->skipHours = func_get_args();
    foreach($this->skipHours as $hr) { 
      if( (string)$hr!==(string)intVal($hr) ) { 
        return false;
      }
    }
    return true;
  }

  /**
   * @access public
   */
  function setSkipDays() {
    $this->skipDays = func_get_args();
    $allow = array("Monday","Tuesday","Wednesday","Thursday",
      "Friday","Saturday","Sunday");
    foreach($this->skipDays as $day) { 
      if( !in_array($day,$allow) ) { 
        return false;
      }
    }
    return true;
  }


  /**
   * @access public
   */
  function setAtomUrl($url) { 
    $this->atomUrl = $url;
    if( !$this->link ) { 
      $this->setLink($url);
    }
    return (is_string($url) && strpos($url,'://') > 0);
  } // END: function setSelfUrl($url)


  /**
   * @access public
   */
  function getNewItem() { 
    return new RSS_Item();
  } // END: function getNewItem()


  /**
   * @access public
   */
  function getItems() { 
    return $this->items;
  }


  /**
   * @access public
   */
  function getItemByTitle($title) { 
    foreach($this->items as $item) { 
      if( $item->title===$title ) { 
        return $item;
      }
    }
    return null;
  } // END: function getItemByTitle($guid)


  /**
   * @access public
   */
  function getItemByGUID($guid) { 
    foreach($this->items as $item) { 
      if( $item->guid===$guid ) { 
        return $item;
      }
    }
    return null;
  } // END: function getItemByGUID($guid)


  /**
   * @access public
   */
  function setLimit($n) { 
    $this->limit = ($n>0) ? $n : 0;
    return is_int($n);
  } // END: function setLimit($n)


  /**
   * @access public
   */
  function addItem(&$item) { 
    if( !is_a($item,'RSS_Item') ) { 
      return false;
    }
    if( $this->limit > 0 ) { 
      $len = count($this->items);
      if( $len >= $this->limit ) { 
        // remove items from the beginning to make room for the new item
        array_splice($this->items,0,$len-$this->limit+1);
      }
    }
    $this->items[] = $item;
    return true;
  } // END: function addItem(&$item)


  /**
   * @access protected
   */
  function setItems($items) { 
    $this->items = array();
    return $this->addItems($items);
  } // END: function steItems($items)


  /**
   * @access protected
   */
  function addItems($items) { 
    if( !is_array($items) ) { 
      $items = array($items);
    }
    foreach($items as $item) { 
      if( !$this->addItem($item) ) { 
        return false;
      }
    }
    return true;
  } // END: function addItems($items)


  /**
   * @access public
   */
  function toString() { 
    return (string)$this;
  } // END: function toString()


  /**
   * @access private
   */
  function __toString() { 
    $opt = '';

    if( $this->language ) { 
      $opt .= '<language>'.htmlspecialchars($this->language)."</language>\n";
    }

    if( $this->copyright ) { 
      $opt .= '<copyright>'.htmlspecialchars($this->copyright)."</copyright>\n";
    }

    if( $this->managingEditor ) { 
      $opt .= '<managingEditor>'.htmlspecialchars($this->managingEditor).
        "</managingEditor>\n";
    }

    if( $this->webMaster ) { 
      $opt .= '<webMaster>'.htmlspecialchars($this->webMaster)."</webMaster>\n";
    }

    // Auto-detect max pubDate from items.
    // Do this because Thunderbird doesn't handle GUID correctly,
    // but does handle pubDate.
    if( !$this->pubDate ) { 
      $this->m_autoSetPubDate();
    }

    if( $this->pubDate ) {
      if( is_int($this->pubDate) ) {
        $opt .= '<pubDate>'.date('D, d M Y H:i:s O',$this->pubDate).
        "</pubDate>\n";
      } else {
        $opt .= '<pubDate>'.htmlspecialchars($this->pubDate)."</pubDate>\n";
      }
    }

    if( $this->lastBuildDate ) {
      if( is_int($this->lastBuildDate) ) {
        $opt .= '<lastBuildDate>'.
          date('D, d M Y H:i:s O',$this->lastBuildDate)."</lastBuildDate>\n";
      } else {
        $opt .= '<lastBuildDate>'.htmlspecialchars($this->lastBuildDate).
          "</lastBuildDate>\n";
      }
    }

    if( !empty($this->categories) ) {
      foreach( $this->categories as $cat ) {
        $att = '';
        if( $cat[1] ) {
          $att = ' domain="'.htmlspecialchars($cat[1],ENT_QUOTES).'"';
        }
        $opt .= '<category'.$att.'>'.htmlspecialchars($cat[0])."</category>\n";
      }
    }

    if( $this->generator ) { 
      $opt .= '<generator>'.htmlspecialchars($this->generator)."</generator>\n";
    }

    if( $this->docs ) { 
      $opt .= '<docs>'.htmlspecialchars($this->docs)."</docs>\n";
    }

    if( $this->cloud ) { 
      $doma  = htmlspecialchars($this->cloud[0],ENT_QUOTES);
      $port  = htmlspecialchars($this->cloud[1],ENT_QUOTES);
      $path  = htmlspecialchars($this->cloud[2],ENT_QUOTES);
      $rpro  = htmlspecialchars($this->cloud[3],ENT_QUOTES);
      $proto = htmlspecialchars($this->cloud[4],ENT_QUOTES);
      $opt .= '<cloud domain="'.$dom.'" port="'.$port.'" path="'.$path.
        '" registerProcedure="'.$rpro.'" protocol="'.$proto.'" />'."\n";
    }

    if( $this->ttl ) { 
      $opt .= '<ttl>'.htmlspecialchars($this->ttl)."</ttl>\n";
    }

    if( $this->image ) { 
      $url = htmlspecialchars($this->image[0]);
      $title = htmlspecialchars($this->image[1]);
      $link = htmlspecialchars($this->image[2]);
      $opt .= "<image>\n".
        '<url>'.$url."</url>\n".
        '<title>'.$title."</title>\n".
        '<link>'.$link."</link>\n";
      if( $this->image[3] ) { 
        $opt .= '<width>'.htmlspecialchars($this->image[3])."</width>\n";
      }
      if( $this->image[4] ) { 
        $opt .= '<height>'.htmlspecialchars($this->image[4])."</height>\n";
      }
      $opt .= "</image>\n";
    }

    if( $this->rating ) { 
      $opt .= '<rating>'.htmlspecialchars($this->rating)."</rating>\n";
    }

    if( $this->textInput ) { 
      $title = htmlspecialchars($this->textInput[0]);
      $desc = htmlspecialchars($this->textInput[1]);
      $name = htmlspecialchars($this->textInput[2]);
      $link = htmlspecialchars($this->textInput[3]);
      $opt .= "<textInput>\n".
        '<title>'.$title."</title>\n".
        '<description>'.$desc."</description>\n".
        '<name>'.$name."</name>\n".
        '<link>'.$link."</link>\n".
        "</textInput>\n";
    }

    if( $this->skipHours && !empty($this->skipHours) ) { 
      $opt .= "<skipHours>\n";
      foreach($this->skipHours as $hr) { 
        $opt .= '<hour>'.htmlspecialchars($hr)."</hour>\n";
      }
      $opt .= "</skipHours>\n";
    }

    if( $this->skipDays && !empty($this->skipDays) ) { 
      $opt .= "<skipDays>\n";
      foreach($this->skipDays as $day) { 
        $opt .= '<day>'.htmlspecialchars($day)."</day>\n";
      }
      $opt .= "</skipDays>\n";
    }


    // Begin ATOM compatability
    $atomNS = '';
    if( $this->m_isAtomCompatable() ) { 
      $atomNS = ' xmlns:atom="http://www.w3.org/2005/Atom"';

      if( $this->atomUrl ) { 
        $opt .= '<atom:link href="'.
          htmlspecialchars($this->atomUrl,ENT_QUOTES).
          '" rel="self" type="application/rss+xml" />'."\n";
      }
    }
    // End ATOM compatability


    // items must come at end of channel
    if( !empty($this->items) ) { 
      $opt .= "\n";
      foreach($this->items as $item) { 
        $opt .= $item->__toString()."\n";
      }
    }


    return '<rss version="2.0"'.$atomNS.">\n".
      "<channel>\n".
      '<title>'.htmlspecialchars($this->title)."</title>\n".
      '<link>'.htmlspecialchars($this->link)."</link>\n".
      '<description><![CDATA['.$this->description.']]></description>'."\n".
      $opt.
      "</channel>\n".
      '</rss>';
  } // END: function __toString()

  /**
   * @access private
   *
   * Auto-detect the max pubDate from items.
   * We do this because Thunderbird doesn't handle GUIDs correctly,
   * but it does handle changes to pubDate.
   */
  function m_autoSetPubDate() { 
    if( !empty($this->items) ) { 
      $pubDate = null;
      foreach($this->items as $item) { 
        if( $item->pubDate ) { 
          if( $pubDate===null || $item->pubDate > $pubDate ) { 
            $pubDate = $item->pubDate;
          }
        }
      }
      if( $pubDate !== null ) { 
        $this->setPubDate($pubDate);
      }
    }
  } // END: function m_autoSetPubDate()


  /**
   * @access private
   */
  function m_isAtomCompatable() { 
    return ($this->atomUrl);
  } // END: function m_isAtomCompatable()

} // END: class RSS


/**
 * @package RSS
 */
class RSS_Item { 
  /**
   * @access private
   */
  var $title = '';
  /**
   * @access private
   */
  var $link = '';
  /**
   * @access private
   */
  var $description = null;

  /**
   * @access private
   */
  var $source = '';
  /**
   * @access private
   */
  var $enclosures = array();
  /**
   * @access private
   */
  var $categories = array();
  /**
   * @access private
   */
  var $pubDate = null;
  /**
   * @access private
   */
  var $guid = '';
  /**
   * @access private
   */
  var $isPermaLink = false;
  /**
   * @access private
   */
  var $comments = '';
  /**
   * @access private
   */
  var $author = '';


  /**
   * @access public
   */
  function RSS_Item() { 
    return $this;
  }

  /**
   * @access public
   */
  function setTitle($t) { 
    $this->title = $t; 
    return (is_string($t));
  }

  /**
   * @access public
   */
  function setLink($lnk) { 
    $this->link = $lnk;
    return (is_string($lnk));
  }

  /**
   * @access public
   */
  function setDescription($desc) { 
    $this->description = $desc; 
    return (is_string($desc));
  }

  /**
   * @access public
   */
  function setSource($url) { 
    $this->source = $url;
    return (is_string($url));
  }

  /**
   * @access public
   */
  function setGUID($g,$isPermaLink=false) { 
    $this->guid = $g;
    $this->isPermaLink = ($isPermaLink);
    return (is_string($g));
  }

  /**
   * @access public
   */
  function getGUID() { 
    if( strlen($this->guid) > 0 ) { 
      return $this->guid;
    }
    return $this->link;
  }

  /**
   * @access public
   */
  function setPubDate($ts) { 
    if( (string)$ts===(string)intVal($ts) ) {
      $this->pubDate = intVal($ts);
      return true;
    }
    $ts0 = strtotime($ts);
    if( $ts0===false || $ts0===-1 ) { 
      return false;
    }
    $this->pubDate = $ts0;
    return true;
  } // END: function setPubDate($ts)

  /**
   * @access public
   */
  function setCategory($cat,$domain=null) { 
    $this->categories = array( array($cat,$domain) );
    return (is_string($cat) && ($domain===null || is_string($domain)));
  } // END: function setCategory($cat)

  /**
   * @access public
   */
  function addCategory($cat,$domain=null) { 
    $this->categories[] = array($cat,$domain);
    return (is_string($cat) && ($domain===null || is_string($domain)));
  } // END: function addCategory($cat)


  /**
   * @access public
   */
  function setEnclosure($url,$sizeBytes,$contentType) { 
    $this->enclosures = array( array($url,$sizeBytes,$contentType) );
    return ( is_string($url) && $sizeBytes>0 && is_string($contentType) );
  }

  /**
   * @access public
   */
  function addEnclosure($url,$sizeBytes,$contentType) { 
    $this->enclosures[] = array($url,$sizeBytes,$contentType);
    return ( is_string($url) && $sizeBytes>0 && is_string($contentType) );
  }

  /**
   * @access public
   */
  function setComments($cstr) { 
    $this->comments = $cstr;
    return (is_string($cstr) && strpos($cstr,'://') > 0);
  }

  /**
   * @access public
   */
  function setAuthor($auth) { 
    $this->author = $auth;
    return (is_string($auth));
  }

  /**
   * @access private
   */
  function __toString() { 
    $opt = '';

    if( $this->title ) { 
      $opt .= '<title>'.htmlspecialchars($this->title)."</title>\n";
    }

    if( $this->link ) { 
      $opt .= '<link>'.htmlspecialchars($this->link)."</link>\n";
    }

    if( $this->description ) { 
      $opt .= '<description><![CDATA['.$this->description.
        ']]></description>'."\n";
    }

    if( $this->source ) { 
      $opt .= '<source>'.htmlspecialchars($this->source)."</source>\n";
    }

    if( !empty($this->enclosures) ) { 
      foreach( $this->enclosures as $enc ) { 
        $opt .= '<enclosure url="'.htmlspecialchars($enc[0],ENT_QUOTES).
          '" length="'.htmlspecialchars($enc[1],ENT_QUOTES).
          '" type="'.htmlspecialchars($enc[2],ENT_QUOTES).'" />'."\n";
      }
    }

    if( !empty($this->categories) ) { 
      foreach( $this->categories as $cat ) { 
        $att = '';
        if( $cat[1] ) { 
          $att = ' domain="'.htmlspecialchars($cat[1],ENT_QUOTES).'"';
        }
        $opt .= '<category'.$att.'>'.htmlspecialchars($cat[0])."</category>\n";
      }
    }

    if( $this->pubDate ) { 
      if( is_int($this->pubDate) ) { 
        $opt .= '<pubDate>'.date('D, d M Y H:i:s O',$this->pubDate).
        "</pubDate>\n";
      } else { 
        $opt .= '<pubDate>'.htmlspecialchars($this->pubDate)."</pubDate>\n";
      }
    }

    if( $this->guid || $this->link ) { 
      $att = ($this->isPermaLink) ? 'true' : 'false';
      $guid = $this->getGUID();
      if( $this->isPermaLink || $guid===$this->link ) { 
        $att = 'true';
      }
      $opt .= '<guid isPermaLink="'.$att.'">'.
        htmlspecialchars($guid)."</guid>\n";
    }

    if( $this->comments ) { 
      $opt .= '<comments>'.htmlspecialchars($this->comments)."</comments>\n";
    }

    if( $this->author ) { 
      $opt .= '<author>'.htmlspecialchars($this->author)."</author>\n";
    }

    return "<item>\n".$opt."</item>\n";
  } // END: function __toString()


} // END: class RSS_Item


?>
