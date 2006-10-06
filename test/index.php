<?php
require_once("stats.php");
start_timer();

require_once("models.php");


/**
 * Creating new data object
 */

$blogPost = ActiveRecord::getNewInstance("BlogPost");
$blogPost->title->set("Test title");
$blogPost->body->set("Here goes my blog post body from a demo app " . rand());
$blogPost->save();
debug("Blog post data: ");
debug($blogPost->toArray());


/**
 * Getting a list of related records (by foreign key)
 * Gets a list of comments created for a blog post (post ID = 2)
 */
$blogPost = ActiveRecord::getInstanceByID("BlogPost", 457);

$commentsOfPost = $blogPost->getRelatedRecordSet("BlogComment", new ARSelectFilter());
$commentArray = $blogPost->getRelatedRecordSetArray("BlogComment", new ARSelectFilter());


$demoObj = ActiveRecord::getNewInstance("Demo");
$demoObj->setID("my_test_id_const");
$demoObj->value->set("test value");
$demoObj->save(ActiveRecord::PERFORM_INSERT);
//echo "<pre>";
//debug(ActiveRecord::getLogger()->output());
//echo "</pre>";

//end_timer();
//show_includes();

?>