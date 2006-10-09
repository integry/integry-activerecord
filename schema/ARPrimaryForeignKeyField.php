<?php

require_once("schema/ARPrimaryKey.php");

/**
 * Primary-foreign key field (PF) class that is mainly used in many-to-many relationships
 * 
 * @package activerecord.schema
 */
class ARPrimaryForeignKeyField extends ARForeignKeyField implements ARPrimaryKey 
{
}

?>
