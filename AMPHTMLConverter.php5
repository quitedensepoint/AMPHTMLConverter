//<?php

/*
 * Class        : AMPHTMLConverter
 * Description  : This class takes a string input in the constructor and
                  provides many utility functions to convert the given string
                  into AMP compatible HTML.
 * Methods      : fullConvert()
                  stripUnsupportedTags()
                  formatTables()
                  validateLinks()
                  forceHTTPS()
                  removeUnsupportedAttributes()
                  convertTagsToAMPEquivalents()
                  validateLinks()
                  validateRels()
 * Created      : 20160711 CAO
**/

/* If this class is not instantiated from external script, instantiate and fully
 * convert the _VariableValue input from ObjectScript $S function
**/

$input = $SOSE->GetVar('_VariableValue');
if(!is_null($input) && strlen($input) > 0){
  $converter = new AMPHTMLConverter($input);
  $converter->fullConvert(null);
}

class AMPHTMLConverter
{
  private $html;

  function __construct($string){
    if($string instanceof MyDOMDocument) {
      $this->html = $string;
    } else {
      $this->html = new MyDOMDocument();
      //Suppress warnings for HTML5 tags libxml doesn't recognize
      libxml_use_internal_errors(true);
      $this->html->loadHTML($string);
      libxml_use_internal_errors(false);
    }
  }

  public function fullConvert(){
    //Performs all standard HTML to AMP reformat functions.
    try{
      self::stripUnsupportedTags($this->html);
      self::formatTables($this->html);
      self::validateLinks($this->html);
      self::forceHTTPS($this->html);
      self::removeUnsupportedAttributes($this->html);
      self::convertTagsToAMPEquivalents($this->html);
      self::validateLinks($this->html);
      self::validateRels($this->html);
      $SOSE->Echo(self::extractFragment($this->html));
    } catch (Exception $e){
      self::forceInvalidOutput($html);
      $SOSE->Echo('<!-- HTML AMP conversion error: '.$e->getMessage().' -->');
    }
  }

  private function forceInvalidOutput(){
    //Forcibly outputs HTML in AMP-invalid format if reformat fails.
    try{
      $SOSE->Echo($this->html->saveHTML());
    } catch (Exception $e){
      $SOSE->Echo($SOSE->GetVar('_VariableValue')
                  .'<!-- HTML AMP conversion error: '.$e->getMessage().' -->');
    }
  }

  public function extractFragment() {
    //Removes the tags automatically added to HTML by DOMDocument
    $htmlFragment = preg_replace('/^<!DOCTYPE.+?>/', '', str_replace(array(
      '<html>',
      '</html>',
      '<body>',
      '</body>'
    ) , array(
      '',
      '',
      '',
      ''
    ) , $this->html->saveHTML()));
    return $htmlFragment;
  }

  public function stripUnsupportedTags() {
    //Strips tags that are completely unsupported by AMP standard.
      $xpaths = array(
        "//base",
        "//frame",
        "//frameset",
        "//object",
        "//param",
        "//applet",
        "//embed",
        "//form",
        "//input",
        "//textarea",
        "//select",
        "//option",
        "//script[@type!='application/ld+json']",
        "//a[contains(@href,'javascript:') and not(@target='_blank')]",
        "//style",
        "//a[not(@href)]",
        "//script"
      );
      foreach($xpaths as $xpath) {
        $results = $this->html->xpath($xpath);
        foreach($results as $result) {
          self::deleteNode($result);
        }
      }
      return $this->html;
  }

  public function validateLinks() {
    //Ensures that tags containing links do in fact have valid URLs
    $xpaths = array(
      "//a",
      "//amp-iframe",
      "//iframe",
      "//img",
      "//amp-img",
      "//video",
      "//amp-video",
      "//audio",
      "//amp-audio"
    );
    foreach($xpaths as $xpath) {
      $nodes = $this->html->xpath($xpath);
      foreach($nodes as $node) {
        if ($node->getAttribute('href')
            && !self::isValidURL($node->getAttribute('href'))) {
          self::deleteNode($node);
        }

        if ($node && $node->getAttribute('src')
            && !self::isValidURL($node->getAttribute('src'))) {
          self::deleteNode($node);
        }
      }
    }
    return $this->html;
  }

  public function convertTagsToAMPEquivalents() {
    // Converts HTML tags that have AMP equivalent
    $xpaths = array(
      "//img",
      "//video",
      "//audio",
      "//iframe"
    );
    foreach($xpaths as $xpath) {
      $nodes = $this->html->xpath($xpath);
      foreach($nodes as $node) {
        $replacement = $html->createElement('amp-' . $node->tagName);
        foreach($node->attributes as $attribute) {
          $replacement->setAttribute($attribute->nodeName,
                                     $attribute->nodeValue);
        }
        $node->parentNode->replaceChild($replacement, $node);
      }
    }
    return $this->html;
  }

  public function removeUnsupportedAttributes() {
    //Removes unsupported attributes
    $xpaths = array(
      "//*[@style]"
    );
    foreach($xpaths as $xpath) {
      $nodes = $this->html->xpath($xpath);
      foreach($nodes as $node) {
        $node->removeAttributeNode($node->getAttributeNode('style'));
      }
    }
    return $this->html;
  }

  public function formatTables() {
    //Removes height attribute. Could be merged into removeUnsupportedAttributes
    $xpath = "//table[@height]";
    $nodes = $this->html->xpath($xpath);
    foreach($nodes as $node) {
      $node->removeAttributeNode($node->getAttributeNode('height'));
    }
    return $this->html;
  }

  public function forceHTTPS() {
    //Replaces HTTP with HTTPS for hrefs as required by AMP rules
    $xpath = "//*[contains(@href,'http:')]";
    $nodes = $this->html->xpath($xpath);
    foreach($nodes as $node) {
      $node->getAttributeNode('href')->value
      = preg_replace('/.*http:/', 'https:',
                     htmlspecialchars($node->getAttributeNode('href')->value));
    }
    return $this->html;
  }

  public function isValidURL($string) {
    return filter_var($string, FILTER_VALIDATE_URL,
                      FILTER_FLAG_HOST_REQUIRED);
  }

  public function validateRels() {
    //Removes nodes that use invalid rel attributes not part of AMP specs
    $validRels = array(
      "canonical",
      "components",
      "dns-prefetch",
      "import",
      "manifest",
      "preconnect",
      "prefetch",
      "preload",
      "prerender",
      "serviceworker",
      "stylesheet",
      "subresource"
    );
    $xpath = "//*[@rel]";
    $nodes = $this->html->xpath($xpath);
    foreach($nodes as $node) {
      if (array_search($node['rel'], $validRels) == -1) {
        self::deleteNode($node);
      }
    }
    return $this->html;
  }

  public function deleteNode(&$node) {
    $node->parentNode->removeChild($node);
    return $node;
  }
}

class MyDOMDocument extends DOMDocument {
  //Enables DOMDocument to directly use XPath method without instantiating
  //DOMXPath manually each time.
  public function xpath($xpath) {
    $query = new DOMXPath($this);
    return $query->query($xpath);
  }
}
