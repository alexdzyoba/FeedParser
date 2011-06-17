<?php
/************************************************************************** 
* Copyright (C) 2011 Alex Dzyoba <finger@reduct.ru>
* 
* This file is part of FeedParser.
*
* FeedParser is free software: you can redistribute it and/or modify
* it under the terms of the GNU Lesser General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* FeedParser is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU Lesser General Public License for more details.
*
* You should have received a copy of the GNU Lesser General Public License
* along with FeedParser. If not, see <http://www.gnu.org/licenses/>.
**************************************************************************/
 
/**
 * @file FeedParser.php
 * @brief FeedParser base class
 */

 /**
 * @brief Entry class
 *
 * This class is base class that works as proxy. It makes feed working 
 * transparent.
 *
 * IMPORTANT NOTES:
 * For now we're going to treat Atom 0.3 as Atom 1.0
 * but raise a warning. I do not intend to introduce full support for 
 * Atom 0.3 as it has been deprecated, but others are welcome to.
 *
 * About RSS. As we all know there are 2 branches of RSS:
 * - RDF branch - RSS 0.90, RSS 1.0, RSS 1.1
 * - RSS branch - RSS 0.91, RSS 0.92, RSS 2.0
 *
 * For RSS branch I implement only one class FeedParserRSS2, because RSS 
 * 0.91, 0.92 and 2.0 all have same tags and no XML namespace.
 *
 * For RDF branch I implement FeedParserRDF class, that handles all that crappy 
 * stuff with XML namespaces (each of them has own(!) default(!!) XML namespace)
 *
 */
class FeedParser 
{
	/**
	* Property containing feed. We proxy our request through object represented by
	* this property.
	*/
	private $feed;

	/** 
	* Property to store XML document parsed by DOMDocument 
	*/ 
	public $model;

    /**
     * A storage space for Namespace URIs. Used in determination of RSS 2 feed.
     */
    private $feedNamespaces = array(
        'rss2' => array(
            'http://backend.userland.com/rss',
            'http://backend.userland.com/rss2',
            'http://blogs.law.harvard.edu/tech/rss'));

    /**
     * Detects feed types and instantiate appropriate objects.
     *
     * Our constructor takes care of detecting feed types and instantiating
	 * appropriate classes.
	 * 
	 * @param $xml
	 *     XML serialization of the feed
	 */
	function __construct($xml)
	{
		// Parse XML with DOMDocument
        $this->model = new DOMDocument;
        if (!$this->model->loadXML($xml))
			throw new Exception('Error while parsing XML document');

        $doc_element = $this->model->documentElement;
        $error = false;

		// Main switch to determine feed type and instantiate appropriate object
		switch (true) 
		{
			// If we have root element namespace for Atom then it's Atom
			case ($doc_element->namespaceURI == 'http://www.w3.org/2005/Atom'):
				require_once('Atom.php');
				$this->feed = new FeedParserAtom($this->model);
				break;
			
			// If we have root element old namespace for Atom then make warning
			// and fallback to Atom 1.0
			case ($doc_element->namespaceURI == 'http://purl.org/atom/ns#'):

				$error = "Atom 0.3 deprecated, using 1.0 parser which won't
				          provide all options";
				require_once('Atom.php');
				$this->feed = new FeedParserAtom($this->model);				
				break;
			
			// If we have root element namespace for RSS 1.0 or child node with
			// RSS10 namespace then instantiate RSS 1.0
			case ($doc_element->namespaceURI == 'http://purl.org/rss/1.0/' || 
				  $doc_element->lookupPrefix('http://purl.org/rss/1.0/')!=NULL||
				 ($doc_element->hasChildNodes() 
			      && $doc_element->childNodes->length > 1 
			      && $doc_element->childNodes->item(1)->namespaceURI=='http://purl.org/rss/1.0/'
				 )):

				 require_once('RDF.php');

				 $this->feed = new FeedParserRDF($this->model, '1.0');
				 break;

			// If we have root element namespace for RSS 1.1 or child node with
			// RSS 1.1 namespace then instantiate RSS 1.1
			case ($doc_element->namespaceURI == 'http://purl.org/rss/1.1/' || 
			     ($doc_element->hasChildNodes() 
			      && $doc_element->childNodes->length > 1 
			      && $doc_element->childNodes->item(1)->namespaceURI=='http://purl.org/rss/1.1/'
				 )):
				require_once('RDF.php');

				$this->feed = new FeedParserRDF($this->model, '1.1');
				break;

			// If we have root element namespace for RSS 0.90 or child node with
			// RSS 0.90 namespace then instantiate RSS 0.90
			case ($doc_element->namespaceURI ==	'http://my.netscape.com/rdf/simple/0.9/' ||
			      ($doc_element->hasChildNodes() 
			       && $doc_element->childNodes->length > 1
			       && $doc_element->childNodes->item(1)->namespaceURI=='http://my.netscape.com/rdf/simple/0.9/'
				  )):

				require_once('RDF.php');

				$this->feed = new FeedParserRDF($this->model, '0.90');
				break;

			// If we have root element namespace for RSS 0.91 or child node with
			// RSS 0.91 namespace then make warning and fallback to RSS 2
			case ($doc_element->tagName == 'rss' 
			      && $doc_element->hasAttribute('version') 
			      && $doc_element->getAttribute('version') == 0.91):

				$error = 'RSS 0.91 has been superceded by RSS2.0. Using RSS2.0 parser.';
				require_once('RSS2.php');
				$this->feed = new FeedParserRSS2($this->model);
				break;

			// If we have root element namespace for RSS 0.92 or child node with
			// RSS 0.92 namespace then make warning and fallback to RSS 2
			case ($doc_element->tagName == 'rss' 
			      && $doc_element->hasAttribute('version') 
			      && $doc_element->getAttribute('version') == 0.92):

				$error = 'RSS 0.92 has been superceded by RSS2.0. Using RSS2.0 parser.';
				require_once('RSS2.php');
				$this->feed = new FeedParserRSS2($this->model);
				break;
		
			// If we have root element namespace from feedNamespaces property or
			// root element tag name is 'rss' then it's RSS 2
			case (in_array($doc_element->namespaceURI, $this->feedNamespaces['rss2'])
				  || $doc_element->tagName == 'rss'):

				if (!$doc_element->hasAttribute('version') 
				    || $doc_element->getAttribute('version') != 2) 
					$error = 'RSS version not specified. Parsing as RSS2.0';
			
				require_once('RSS2.php');
				$this->feed = new FeedParserRSS2($this->model);	
				break;
	
			// Finally, if we can't determine feed type throw exception	
			default:
				throw new Exception('Feed type unknown');
				break;
		}
	}
    
    /**
     * Proxy to allow feed element names to be used as method names
     *
     * For top-level feed elements we will provide access using methods or 
     * attributes. This function simply passes on a request to the appropriate 
     * feed type object.
     *
	 * @param $call
	 *     The method being called
	 * @param $attributes
	 * 		Attributes of called method
     */
    function __call($call, $attributes)
    {
        $attributes = array_pad($attributes, 5, false);
        list($a, $b, $c, $d, $e) = $attributes;
        return $this->feed->$call($a, $b, $c, $d, $e);
    }

	/**
     * Proxy to allow feed element names to be used as attribute names
     *
     * To allow variable-like access to feed-level data we use this
     * method. It simply passes along to __call() which in turn passes
     * along to the relevant object.
     *
	 * @param $val
	 *     The name of the variable required
     */
    function __get($val)
    {
		return $this->feed->$val;
    }
}

/**
 * @brief Interface for feed items.
 *
 * Basically we want 4 things from it.
 * - Retrieve item title
 * - Retrieve item publication date
 * - Retrieve item content
 * - Retrieve link where item locate
 *
 * For each of things above there must be implemented function.
 */
interface IItem
{
	function getTitle();
	function getContent();
	function getPubDate();
	function getLink();
}

/**
 * @brief Interface for feeds.
 *
 * When you work feeds you want to:
 * - Retrieve feed title
 * - Retrieve feed description
 * - Retrieve feed type 
 * - Retrieve feed items
 * - Retrieve feed links
 * 	- Link to feed itself
 * 	- Link to website that holds feed
 *
 * For each of things above there must be implemented function.
 */
interface IFeed
{
	function getTitle();
	function getDescription();
	function getItems();
	function getLink();
	function getFeedLink();
	function getFeedType();
}

/**
 * Function to check if result of method is empty. 
 *
 * We can't do this with built in PHP function "empty", because it makes fatal 
 * error:
 *
 * 		<pre>Can't use method return value in write context</pre>
 * 
 * But thanks to Google and StackOverflow I've found solution:
 * http://stackoverflow.com/questions/1075534/cant-use-method-return-value-in-write-context/4786313#4786313
 *
 * @return bool
 */
function is_empty($var)
{ 
	return empty($var);
}
?>
