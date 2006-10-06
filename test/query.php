<?php

require_once("models.php");

$query = new ARSelectQueryBuilder();

$query->includeTable("BlogPost");
$query->joinTable("BlogPost", "BlogUser", "userID", "ID");
$query->addField("title", "BlogPost", "BlogPost_title");
$query->addField("name");
$query->addField("*", "BlogPostComment");

echo $query->createString();

?>