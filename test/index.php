<?php
require_once("stats.php");
start_timer();

require_once("models.php");


/**
 * Creating new data object
 */

//$blogPost = ActiveRecord::getNewInstance("BlogPost");
$blogPost = ActiveRecord::getInstanceByID("BlogPost", 2, BlogPost::LOAD_DATA);

//$title = array("en" => "test title", "lt" => "Bandomoji antraste");
//$blogPost->title->set($title);
//$blogPost->save();

debug("Blog post data: ");
debug($blogPost->toArray());


?>