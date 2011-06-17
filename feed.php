<?php

// The only thing we need is include base class file
require_once('FeedParser.php');

// Get XML serialization of feed
$xml = file_get_contents($_POST['filename']);

// This is great. To work with feed we invoke only base class. All other work is 
// transparent.
$feed = new FeedParser($xml);

//Because we have interface for feeds, we invoke interface methods
echo '<b>Type:</b>'.$feed->getFeedType()."<br/>";
echo '<b>Title:</b>'.$feed->getTitle()."<br/>";
echo '<b>Description:</b>'.$feed->getDescription()."<br/>";
echo '<b>Feed link:</b>'.$feed->getFeedLink()."<br/>";
echo '<b>Link:</b>'.$feed->getLink()."<br/>";

$items = $feed->getItems();

// Stuff in your items can be empty, so you should somehow handle it.
// I've prepared is_empty function for you - enjoy.
$i=1;
foreach($items as $item)
{
	//Because we have interface for items, we invoke interface methods
	echo "<h1>";
	if(is_empty($item->getLink()))
		echo '<a href="#">';
	else
		echo '<a href="'.$item->getLink().'">';

	if(is_empty($item->getTitle()))
		echo "No title";
	else
		echo "$i. ".$item->getTitle();
	echo "</a>";

	echo "</h1>";

	if(is_empty($item->getPubDate()))
		echo "<i>"."No date"."</i><br/>";
	else
		echo "<i>".$item->getPubDate()."</i><br/>";

	if(is_empty($item->getContent()))
		echo "<i>"."No content"."</i><br/>";
	else
		echo $item->getContent()."<hr/>";

	$i++;
}

?>
