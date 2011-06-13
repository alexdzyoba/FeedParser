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
 * @file RSS090.php
 * @brief RSS 0.90 classes.
 * 
 * Contains FeedParserRSS090 and FeedParserRSS090Element.
 */

/**
 * @brief Handles RSS 0.90 feeds. 
 *
 * Here is a place where we get if in FeedParser we've detected that feed is 
 * RSS 0.90.
 *
 * RSS 0.90 is documented at http://www.rssboard.org/rss-0-9-0
 *
 * We have 2 main namespaces for that feeds.
 * - RDF : 'http://www.w3.org/1999/02/22-rdf-syntax-ns#
 * - RSS : 'http://my.netscape.com/rdf/simple/0.9/'
 *
 * First one is used for whole channel. Second is for the rest. 
 * 
 * This class implements IFeed interface.
 *
 * @author     Alex Dzyoba <finger@reduct.ru>
 */
class FeedParserRSS090 extends FeedParser implements IFeed
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
    private $feedType = 'RSS 0.9';


	/**
	 * Contains array of items
	 */
	public $items;

    /**
     * We will be working with multiple namespaces and it is useful to 
     * keep them together 
     */
    protected $namespaces = array(
        'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
        'rss' => 'http://my.netscape.com/rdf/simple/0.9/',
        'dc' => 'http://purl.org/rss/1.0/modules/dc/',
        'content' => 'http://purl.org/rss/1.0/modules/content/',
        'sy' => 'http://web.resource.org/rss/1.0/modules/syndication/');

    /**
     * Our constructor does nothing more than its parent.
     * 
	 * @param $xml
	 *     A DOM object representing the feed
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
		foreach ($this->namespaces as $key => $value)
            $this->xpath->registerNamespace($key, $value);
	}

	/**
	 * Return link that holds  link to website where feed came from
	 *
	 * @return 
	 *     String with link
	 */
	public function getLink()
	{
		// Evaluate XPath query over DOMDocument.
		
		// We look for top level <link> 
		$items = $this->xpath->evaluate("/rdf:RDF/rss:channel/rss:link");
		
		// If we have more than one top-level <title> then notify
		if($items->length > 1)
			echo 'Strange feed link';

		// Even if we have more than 1 <link> we will return content of first
		if($items->length === 0)
			return '';
		return $items->item(0)->nodeValue;
	}

	/**
	 * Return link that holds RSS1 feed itself.
	 * 
	 * Unlike Atom feeds, RSS1 feeds don't have any special tag or anything else 
	 * for that purpose.
	 *
	 * That's why we return empty string. In your application you should 
	 * reimplement this method, for example, to return this value from database.
	 *
	 * @return 
	 *     String with feed link
	 */
	public function getFeedLink()
	{
		return '';
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
		$items = $this->xpath->evaluate('/rdf:RDF/rss:channel/rss:title');

		// If we have more than one top-level <title> then notify
		if($items->length > 1)
			echo 'Strange feed title';

		// Even if we have more than 1 <title> we will return content of first
		if($items->length === 0)
			return '';
		return $items->item(0)->nodeValue;
	}

	/**
	 * Return description for RSS1 feed. Description is containing in 
	 * <description>. (IFeed interface implementation)
	 *
	 * @return 
	 *     String with feed description
	 */
	public function getDescription()
	{
		// Evaluate XPath query over DOMDocument.
		// We basicaly look for top-level <description>.
		$items = $this->xpath->evaluate('/rdf:RDF/rss:channel/rss:description');

		// If we have more than one top-level <description> then notify
		if($items->length > 1)
			echo 'Strange feed description';

		// Even if we have more than 1 <description> we will return content of first
		if($items->length === 0)
			return '';
		return $items->item(0)->nodeValue;
	}

	/**
	 * Return type of feed. In this case we return $feedType constant containing 
	 * string 'RSS 1.0'. (IFeed interface implementation)
	 * 
	 * @return 
	 *     String with feed type
	 */
	public function getFeedType()
	{
		return $this->feedType;
	}
	
	/**
	 *	Fetch items from feed as array of FeedParserRSS1Element objects.
	 *	
	 *	The only reason why we don't keep it as property and fetch in 
	 *	constructor is performance, i.e. if we only want to build list of feeds 
	 *	then we do not need to fetch and parse all items of every feed. That's 
	 *	why we fetch it on demand
	 *
	 *	@return 
	 *	    Array of FeedParserRSS090Element class instances
	 */
	public function getItems()
	{
		// Evaluate XPath query over DOMDocument.
		// We look for top level link with 'rel=self' attribute
		$entries = $this->xpath->evaluate("/rdf:RDF/rss:item");

		$items = array();
		foreach($entries as $entry)
		{
			// We should make DOMDocument object from DOMNode object to pass it 
			// to FeedParserRSS090Element constructor (otherwise there will be 
			// fatal error despite DOMDocument extends from DOMNode)
			$doc = new DOMDocument;
			$doc->appendChild($doc->importNode($entry, TRUE));
			
			// Add to array new object representing entry
			$items[] = new FeedParserRSS090Element($doc);
		}

		return $items;
	}
}


/**
 * @brief Handles RSS 0.90 feed items. 
 
 * This class represents RSS 0.90 <item> node. We make array of this class 
 * instances in FeedParserRSS090::getItems() .
 *
 * This class implements IItem interface.
 *
 * @author  Alex Dzyoba <finger@reduct.ru>
 */

class FeedParserRSS090Element implements IItem
{
	/** 
	 * Contains parsed XML document
	 */
	public $model;

    /**
     * We will be working with multiple namespaces and it is useful to 
     * keep them together 
     */
    protected $namespaces = array(
        'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
        'rss' => 'http://my.netscape.com/rdf/simple/0.9/',
        'dc' => 'http://purl.org/rss/1.0/modules/dc/',
        'content' => 'http://purl.org/rss/1.0/modules/content/',
        'sy' => 'http://web.resource.org/rss/1.0/modules/syndication/');

	/**
	 *	Constructor. It takes DOMDocument representing tree under <item> tag 
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
		foreach ($this->namespaces as $key => $value)
            $this->xpath->registerNamespace($key, $value);
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
		$items = $this->xpath->evaluate('//rss:title'); 

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
	 * @return 
	 *     String with content
	 */
	public function getContent()
	{
		// Basically content of item located in <description>. But in some cases 
		// generators put only brief content in that tag, while real content is 
		// in <content:encoded>
		
		//First, we look for <content:encoded>	
		$items = $this->xpath->evaluate('//content:encoded');
		if(empty($items->item(0)->nodeValue)) // If it's empty
		{
			//We look for <dc:encoded>
			$items = $this->xpath->evaluate('//dc:description');
			if(empty($items->item(0)->nodeValue)) // If it's empty
			{
				//We look for <rss:description>
				$items = $this->xpath->evaluate('//rss:description');
			}
		}

		if($items->length === 0)
			return '';
		else
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
		// We get date from <pubDate>
		$items = $this->xpath->evaluate('//rss:pubDate');
		if($items->length === 0)
			return '';
		else
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
		// We get date from <pubDate>
		$items = $this->xpath->evaluate('//rss:link');

		if(empty($items->item(0)->nodeValue))
		{
			return " ";
		}

		if($items->length === 0)
			return '';
		else
			return $items->item(0)->nodeValue;
	}
	
}	


