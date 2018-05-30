<?php

namespace SnooPHP\Vue;

use SnooPHP\Utils\Utils;
use SnooPHP\Http\Request;
use SnooPHP\Http\Response;

/**
 * Root component
 */
class Vue extends Component
{
	/**
	 * @var Component[] $components list of registered component
	 */
	protected $components = [];

	/**
	 * @const FRAGMENT_DIR resource directory of vue fragments
	 */
	const FRAGMENT_DIR = "tmp/";

	/**
	 * Register component globally
	 * 
	 * @param string		$file		vue document filename
	 * @param array			$args		list of arguments to pass to the view
	 * @param Request|null	$request	custom request or current if null
	 */
	public function __construct($file, array $args = [], Request $request = null)
	{
		// Register globally
		$GLOBALS["vue"] = $this;

		// Call parent constructor
		parent::__construct($file, $args, $request);
	}

	/**
	 * The root component doesn't have a template block
	 */
	public function parse()
	{
		// Parse style blocks
		$this->parseScript();
		$this->parseStyle();
	}

	/**
	 * Parse script block
	 */
	protected function parseScript()
	{
		if (!empty($this->document) && preg_match("~<script>(.+)</script>~s", $this->document, $matches))
			$this->script = trim($matches[1]);
		else
			$this->valid = false;
	}

	/**
	 * Register a sub-component
	 * 
	 * @param Component $comp component to register
	 */
	public function register(Component $comp)
	{
		$this->components[] = $comp;
	}

	/**
	 * Get full document
	 * 
	 * @return string
	 */
	public function document()
	{
		// Remove style and script blocks
		$document	= $this->document;
		$document	= preg_replace("~<style[^>]*>[^<]*</style>~", "", $document);
		$document	= preg_replace("~<script>.+</script>~s", "", $document);

		// Get name and file paths
		$name		= str_replace("/", ".", path_relative($this->file, path("views/")));
		$scriptFile	= path("resources/".static::FRAGMENT_DIR."$name.js");
		$styleFile	= path("resources/".static::FRAGMENT_DIR."$name.css");

		// We're not caching scripts, otherwise we would lose php dynamic content
		$script = "";
		foreach($this->components as $comp)
		{
			$comp->parseTemplate();
			$comp->parseScript();
			if ($comp->valid()) $script .= $comp->script();
		}
		$this->parseScript();
		$script .= $this->script();

		// Minify javascript in production mode
		if (env("env", "development") === "production") $script = Utils::minifyJs($script);

		// Write script file
		write_file($scriptFile, $script);

		// If style files does not exists or it's development mode, rebuild them
		if (!file_exists($styleFile) || env("env", "development") === "development")
		{	
			// Process components style
			$style = "";
			foreach ($this->components as $comp)
			{
				$comp->parseStyle();
				$style	.= $comp->style();
			}
			$this->parseStyle();
			$style .= $this->style();
	
			// I specified, write style and script inline
			write_file($styleFile, $style);
		}
		else
			// Read style
			$style = read_file($styleFile);
	
		// Link externally
		$fragmentsHtml = '<script type="text/javascript" src="/'.static::FRAGMENT_DIR.$name.'.js?time='.time().'" defer></script>';
		if (!empty($style)) $fragmentsHtml .= '<link rel="stylesheet" type="text/css" href="/'.static::FRAGMENT_DIR.$name.'.css" media="none" onload="media = \'all\'"/>';
		$fragmentsHtml .= '</head>';
		return str_replace('</head>', $fragmentsHtml, $document);
	}
	
	/**
	 * Return a vue page
	 * 
	 * @param string	$file		vue file
	 * @param array		$args		list of arguments available to the view
	 * @param Request	$request	specify if differs from current request
	 * 
	 * @return Response
	 */
	public static function create($file, array $args = [], Request $request = null)
	{
		// Get request
		$request = $request ?: Request::current();
		
		// Create vue, register and return document
		$vue = new Vue(path("views/$file.php"), $args, $request);
		$GLOBALS["vue"] = $vue;
		return new Response($vue->document());
	}
}