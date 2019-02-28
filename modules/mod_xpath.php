<?php

class mod_xpath
{

  public function perform_xpath( $html, $config )
  {
    $doc = $this->getDOM( $html );
    $basenode = false;
    $xpathdom = new DOMXPath($doc);

    $xpaths = Feediron_Helper::check_array( $config['xpath'] );

    $htmlout = array();

    foreach($xpaths as $key=>$xpath){
      Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Perfoming xpath", $xpath);
      $index = 0;
      if(is_array($xpath) && array_key_exists('index', $xpath)){
        $index = $xpath['index'];
        $xpath = $xpath['xpath'];
      }
      $entries = $xpathdom->query('(//'.$xpath.')');   // find main DIV according to config

      if ($entries->length > 0) {
        $basenode = $entries->item($index);
      }

      if (!$basenode && count($xpaths) == ( $key + 1 )) {
        Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "removed all content, reverting");
        return $html;
      } elseif (!$basenode && count($xpaths) > 1){
        continue;
      }

      Feediron_Logger::get()->log_html(Feediron_Logger::LOG_VERBOSE, "Extracted node", $this->getHtmlNode($basenode));
      // remove nodes from cleanup configuration
      $basenode = $this->cleanupNode($xpathdom, $basenode, $config);

      //render nested nodes to html
      $inner_html = $this->getInnerHtml($basenode);
      if (!$inner_html){
        //if there's no nested nodes, render the node itself
        $inner_html = $basenode->ownerDocument->saveXML($basenode);
      }
      array_push($htmlout, $inner_html);
    }

    $content = join((array_key_exists('join_element', $config)?$config['join_element']:''), $htmlout);
    if(array_key_exists('start_element', $config)){
      Feediron_Logger::get()->log_html(Feediron_Logger::LOG_VERBOSE, "Adding start element", $config['start_element']);
      $content = $config['start_element'].$content;
    }

    if(array_key_exists('end_element', $config)){
      Feediron_Logger::get()->log_html(Feediron_Logger::LOG_VERBOSE, "Adding end element", $config['end_element']);
      $content = $content.$config['end_element'];
    }

    return $content;
  }

  private function getDOM( $html ){
    $doc = new DOMDocument();
    if ($this->charset) {
      $html = '<?xml encoding="' . $this->charset . '">' . $html;
    }
    libxml_use_internal_errors(true);
    $doc->loadHTML($html);
    if(!$doc)
    {
      Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, "The content is not a valid xml format");
      if( $this->debug )
      {
        foreach (libxml_get_errors() as $value)
        {
          Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, $value);
        }
      }
      return new DOMDocument();
    }
    return $doc;
  }

  private function getHtmlNode( $node ){
    if (is_object($node)){
      $newdoc = new DOMDocument();
      if ($node->nodeType == XML_ATTRIBUTE_NODE) {
        // appendChild will fail, so make it a text node
        $imported = $newdoc->createTextNode($node->value);
      } else {
        $cloned = $node->cloneNode(TRUE);
        $imported = $newdoc->importNode($cloned,TRUE);
      }
      $newdoc->appendChild($imported);
      return $newdoc->saveHTML();
    } else {
      return $node;
    }
  }

  private function cleanupNode( $xpath, $basenode, $config )
  {
    if(($cconfig = Feediron_Helper::getCleanupConfig($config))!== FALSE)
    {
      foreach ($cconfig as $cleanup)
      {
        Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "cleanup", $cleanup);
        if(strpos($cleanup, "./") !== 0)
        {
          $cleanup = '//'.$cleanup;
        }
        $nodelist = $xpath->query($cleanup, $basenode);
        foreach ($nodelist as $node)
        {
          if ($node instanceof DOMAttr)
          {
            $node->ownerElement->removeAttributeNode($node);
          }
          else
          {
            $node->parentNode->removeChild($node);
          }
        }
        Feediron_Logger::get()->log_html(Feediron_Logger::LOG_VERBOSE, "Node after cleanup", $this->getHtmlNode($basenode));
      }
    }
    return $basenode;
  }

  private function getInnerHtml( $node ) {
    $innerHTML= '';
    $children = $node->childNodes;

    foreach ($children as $child) {
      $innerHTML .= $child->ownerDocument->saveXML( $child );
    }

    return $innerHTML;
  }

}