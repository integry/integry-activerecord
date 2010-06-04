<?php

/**
 * Shorthand functions for query building
 *
 * @package activerecord
 * @author Integry Systems
 */

/**
 * Create field handle from string
 *
 * f('Product.ID') is the same as new ARFieldHandle('Product', 'ID')
 **/
function f($field)
{
	if ($field instanceof ARFieldHandleInterface)
	{
		return $field;
	}

	if (!strpos($field, '.'))
	{
		return new ARExpressionHandle($field);
	}

	list($tableName, $fieldName) = explode('.', $field);
	return new ARFieldHandle($tableName, $fieldName);
}

function select()
{
	return new ARSelectFilter(Condition::mergeFromArray(func_get_args()));
}

function lt($field, $secondField)
{
	return new LessThanCond(f($field), $secondField);
}

function gt($field, $secondField)
{
	return new MoreThanCond(f($field), $secondField);
}

function lte($field, $secondField)
{
	return new OperatorCond(f($field), $secondField, '<=');
}

function gte($field, $secondField)
{
	return new OperatorCond(f($field), $secondField, '>=');
}

function eq($field, $secondField)
{
	return new EqualsCond(f($field), $secondField);
}

function neq($field, $secondField)
{
	return new NotEqualsCond(f($field), $secondField);
}

function isnull($field)
{
	return new IsNullCond(f($field));
}

function isnotnull($field)
{
	return new IsNotNullCond(f($field));
}

function IN($field, $array)
{
	return new INCond(f($field), $array);
}
