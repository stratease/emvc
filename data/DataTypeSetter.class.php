<?php
trait DataTypeSetter
{
		/**
	 *Convenience functions to enforce datatypes
	 */
	protected function setStr($v)
	{
		return (string)$v;
	}
	protected function setInt($v)
	{
		return (int)$v;
	}
	protected function setBool($v)
	{
		return (bool)$v;
	}
}
