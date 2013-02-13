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
 * @file Atom.php
 * @brief Atom classes.
 * 
 * Contains FeedParserAtom and FeedParserAtomElement.
 */

/**
 * @brief Handles Atom feeds. 
 *
 * Here is a place where we get if in FeedParser we've detected that feed is 
 * Atom. Atom is documented in RFC 4287.
 *
 * We do not support Atom 0.3 because it is deprecated and we treat it as Atom 
 * 1.0.
 * 
 * This class implements IFeed interface.
 * 
 * @author  Alex Dzyoba <finger@reduct.ru>
 */
class FeedParserAtom extends FeedParser implements IFeed  
{

	/**
	 * We're likely to use XPath, so let's keep it global 
	 */
	public $xpath;

    /**
     * When performing XPath queries we will use this prefix 
     */
    private $xpathPrefix = '//';

    /**
     * The feed type we are parsing 
     */
    private $feedType = 'Atom 1.0';

	/**
	 * Contains array of items
	 */
	public $items;

    /**
     * Our constructor does nothing more than its parent.
     * 
	 * @param $xml
	 *     a dom object representing the feed
	 * @param $strict
	 *     (optional) whether or not to validate this feed
     */
    function __construct(DOMDocument $xml, $strict = false)
    {
    	$strict=(bool)$strict;
        $this->model = $xml;

		if ($strict)
			if (! $this->relaxNGValidate())
				throw new Exception('Failed required validation');


        $this->xpath = new DOMXPath($this->model);
		$this->xpath->registerNamespace('Atom', 'http://www.w3.org/2005/Atom');
		//$this->numberEntries = $this->count('entry');
    }

	/**
	 *	Fetch items from feed as array of FeedParserAtomElement objects.
	 *	
	 *	The only reason why we don't keep it as property and fetch in 
	 *	constructor is performance, i.e. if we only want to build list of feeds 
	 *	then we do not need to fetch and parse all items of every feed. That's 
	 *	why we fetch it on demand
	 *
	 *	@return 
	 *	    Array of FeedParserAtomElement class instances
	 */
	public function getItems()
	{
		// Evaluate XPath query over DOMDocument.
		// We look for top level link with 'rel=self' attribute
		$entries = $this->xpath->evaluate("//Atom:entry");

		echo "Entries: $entries->length\n";
		$items = array();
		foreach($entries as $entry)
		{
			// We should make DOMDocument object from DOMNode object to pass it 
			// to FeedParserAtomElement constructor (otherwise there will be 
			// fatal error despite DOMDocument extends from DOMNode)
			$doc = new DOMDocument;
			$doc->appendChild($doc->importNode($entry, TRUE));
			
			// Add to array new object representing entry
			$items[] = new FeedParserAtomElement($doc);
		}

		return $items;
	}

	/**
	 * Return link that holds alternative to feed content. Everybody understands 
	 * it as link to website where feed came from
	 *
	 * @return
	 *     String with link
	 */
	public function getLink()
	{
		// Evaluate XPath query over DOMDocument.
		
		// First, we look for top level link with 'rel=alternate' attribute
		$items = $this->xpath->evaluate("/Atom:feed/Atom:link[@rel='alternate']");
		
		// Return href attribute value of found link
		if($items->length != 0)
		{
			return $items->item(0)->attributes->getNamedItem('href')->nodeValue;
		}
		else
		{
			// If we fall here it means that we don't have <link> with 
			// rel="alternate" attribute.
			
			// Get link without "rel" attribute at all
			$items = $this->xpath->evaluate("/Atom:feed/Atom:link[not(@*)]");

			// According to RFC we MUST interpret it as link with relation type 
			// alternate
			
			// Return href attribute value of found link
			if($items->length === 0)
				return '';
			return $items->item(0)->attributes->getNamedItem('href')->nodeValue;
		}
	}

	/**
	 * Return link that holds Atom feed itself.
	 *
	 * @return
	 *     String with feed link
	 */
	public function getFeedLink()
	{
		// Evaluate XPath query over DOMDocument.
		// We look for top level link with 'rel=self' attribute
		$items = $this->xpath->evaluate("/Atom:feed/Atom:link[@rel='self']");

		// Return href attribute value of found link
		if($items->length === 0)
			return '';
		return $items->item(0)->attributes->getNamedItem('href')->nodeValue;
	}
	
	/**
	 * Return title of feed. (IFeed interface implementation)
	 * 
	 * @return 
	 *     String with feed title
	 */ 
	public function getTitle()
	{
		// Evaluate XPath query over DOMDocument.
		// We basicaly look for top-level <title>.
		$items = $this->xpath->evaluate('/Atom:feed/Atom:title');

		// If we have more than one top-level <title> then notify
		if($items->length > 1)
			echo 'Strange feed title';

		// Even if we have more than 1 <title> we will return content of first
		if($items->length === 0)
			return '';
		return $items->item(0)->nodeValue;
	}
	
	/**
	 * Return description for Atom feed. Description is containing in 
	 * <subtitle>. (IFeed interface implementation)
	 *
	 * @return 
	 *     String with feed description
	 */
	public function getDescription()
	{
		// Evaluate XPath query over DOMDocument.
		// We basicaly look for top-level <subtitle>.
		$items = $this->xpath->evaluate('/Atom:feed/Atom:subtitle');

		// If we have more than one top-level <subtitle> then notify
		if($items->length > 1)
			echo 'Strange feed description';

		// Even if we have more than 1 <subtitle> we will return content of first
		if($items->length === 0)
			return '';
		return $items->item(0)->nodeValue;
	}
	
	/**
	 * Return type of feed. In this case we return $feedType constant containing 
	 * string 'Atom 1.0'. (IFeed interface implementation)
	 * 
	 * @return 
	 *     String with feed type
	 */
	public function getFeedType()
	{
		return $this->feedType;
	}

}


/**
 * @brief Handles Atom feed items. 
 *
 * This class represents Atom <entry> node. We make array of this class 
 * instances in FeedParserAtom::getItems() .
 *
 * This class implements IItem interface.
 *
 * @author  Alex Dzyoba <finger@reduct.ru>
 */
class FeedParserAtomElement implements IItem
{
	/** 
	 * Contains parsed XML document
	 */
	public $model;

	/**
	 *	Constructor. It takes DOMDocument representing tree under <entry> tag 
	 *	and creates DOMXPath object for it.
	 *
	 * @param $xml
	 *     A DOM object representing the entry
	 * @param $strict
	 *     (optional) Whether or not to validate this feed
	 */
	function __construct(DOMDocument $xml, $strict = false)
	{
        $this->model = $xml;
		
		if ($strict)
			if (! $this->relaxNGValidate())
				throw new Exception('Failed required validation');
		
		$this->xpath = new DOMXPath($this->model);
		$this->xpath->registerNamespace('Atom', 'http://www.w3.org/2005/Atom');
	}	

	/**
	 * Get title of entry. Query over tree for <title> and get content of tag
	 *
	 * @return 
	 *     String with title
	 */
	public function getTitle()
	{
		// Evaluate XPath query over DOMDocument.
		// Because our DOMDocument root element is <entry> we have only one 
		// <title> element that we query. 
		$items = $this->xpath->evaluate('//Atom:title'); 

		//var_dump($items);

		// If we have more than one <title> then notify
		if($items->length > 1)
			echo 'Strange entry title';

		// Even if we have more than 1 <title> we will return content of first
		if($items->length === 0)
			return '';
		return $items->item(0)->nodeValue;
	}

	/**
	 * Get content of entry. Query over tree for <content> and get content of tag
	 * 
	 * @todo Parse content type(text,html,xhtml)
	 *
	 * @return
	 *     String with content
	 */
	public function getContent()
	{
		// Evaluate XPath query over DOMDocument.
		// Because our DOMDocument root element is <entry> we have only one 
		// <content> element that we query. 
		$items = $this->xpath->evaluate('//Atom:content');

		if($items->length === 0)
			return '';
		return $items->item(0)->nodeValue;
	}

	/**
	 * Get date of entry. Query over tree for <updated> and get content of tag
	 *
	 * @return 
	 *     String with publication date
	 */
	public function getPubDate()
	{
		// We have 2 options to get date from. It's <updated> and <published>.
		// According to RFC every Atom document MUST contain exactly one 
		// <updated> while MUST NOT contain more than one <published>, i.e. <published> 
		// may be absent.
		// That's why we get date from <updated>
		$items = $this->xpath->evaluate('//Atom:updated');

		if($items->length === 0)
			return '';
		return $items->item(0)->nodeValue;
	}

	/**
	 * Get link of entry. Query over tree for <link> with rel=alternate and get 
	 * content of tag
	 *
	 * @return 
	 *     String with item link
	 */
	public function getLink()
	{

		// Evaluate XPath query over DOMDocument.
		
		// First, we look for top level link with 'rel=alternate' attribute
		$items = $this->xpath->evaluate("//Atom:link[@rel='alternate']");
		
		// Return href attribute value of found link
		if($items->length != 0)
		{
			return $items->item(0)->attributes->getNamedItem('href')->nodeValue;
		}
		else
		{
			// If we fall here it means that we don't have <link> with 
			// rel="alternate" attribute.
			
			// Get link without "rel" attribute at all
			$items = $this->xpath->evaluate("//Atom:link[not(@*)]");

			// According to RFC we MUST interpret it as link with relation type 
			// alternate
			
			// Return href attribute value of found link
			if($items->length === 0)
				return '';
			return $items->item(0)->attributes->getNamedItem('href')->nodeValue;
		}
	}
	
}	

