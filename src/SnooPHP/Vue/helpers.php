<?php

use SnooPHP\Http\Request;
use SnooPHP\Vue\Component;

if (!function_exists("vue_component"))
{
	/**
	 * Include a vue component
	 * 
	 * @param string	$file		vue component filename
	 * @param array		$args		set of arguments to pass to the component
	 * @param Request	$request	specify if not current request
	 */
	function vue_component($file, array $args = [], Request $request = null)
	{
		Component::create($file, $args, $request);
	}

	/**
	 * Alias for @see vue_component
	 * 
	 * @deprecated v0.1.2
	 */
	function vueComponent($file, array $args = [], Request $request = null)
	{
		vue_component($file, $args, $request);
	}
}