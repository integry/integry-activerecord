<?php

require_once("models.php");

$simpleFilter = new ARSelectFilter();
$simpleFilter->setCondition(new MoreThanCond(new ARFieldHandle("BlogComment", "createdAt"), "2006.01.01"));

echo $simpleFilter->createString() . "\n<br/>";

/**
 * Sudetingu salygu aprasymas naudojant Condition subklases
 */

$filter = new ARSelectFilter();

$commentCond = new EqualsCond(new ARFieldHandle("BlogComment", "author"), "test");

$commentCond->addAND(new MoreThanCond(new ARFieldHandle("BlogComment", "createdAt"), "2001-01-01"));	 
$commentCond->addAND(new LessThanCond(new ARFieldHandle("BlogComment", "createdAt"), "2003-01-01"));

$subCond = new EqualsCond(new ARFieldHandle("BlogComment", "author"), "other");
$subCond->addAND(new MoreThanCond(new ARFieldHandle("BlogComment", "createdAt"), "2002-06-01"));

$commentCond->addOR($subCond);

$filter->setCondition($commentCond);
$filter->setOrder(new ARFieldHandle("BlogComment", "createdAt"));
$filter->setOrder(new ARFieldHandle("BlogComment", "author"), ARSelectFilter::ORDER_DESC);

echo $filter->createString();

?>