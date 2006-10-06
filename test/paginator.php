<?php

require_once("stats.php");
require_once("models.php");
require_once("../util/paginator/Paginator.php");


//$query = ActiveRecord::createSelectQuery("BlogComment", true);
//echo $query->createString();

//$comment = ActiveRecord::getInstanceByID("BlogComment", 10, BlogComment::LOAD_DATA, BlogComment::LOAD_REFERENCES);
//debug($comment->toArray());


/*
$postFilter = new ARSelectFilter();
$postFilter->setOrder("createdAt", ARSelectFilter::ORDER_DESC);
$postFilter->setLimit(10, 10);
$blogPostList = ActiveRecord::getRecordSet("BlogPost", $postFilter);
$paginator = new Paginator($blogPostList);
*/
//debug($paginator->toArray());
//debug($postFilter);

$blogCommentList = ActiveRecord::getRecordSet("BlogComment", new ARSelectFilter(), ActiveRecord::LOAD_DATA);
debug($blogCommentList->toArray());

?>