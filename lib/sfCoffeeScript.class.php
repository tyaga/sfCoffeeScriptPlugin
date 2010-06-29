<?php

/*
 * This file is part of the sfCoffeeScriptPlugin.
 * (c) 2010 Alexey Tyagunov <atyaga@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * sfCoffeeScript is helper class to provide coffee-script compiling in symfony projects.
 *
 * @package	sfCoffeeScriptPlugin
 * @subpackage lib
 * @author	 Alexey Tyagunov <atyaga@gmail.com>
 * @version	1.0.0
 */
class sfCoffeeScript {
	/**
	 * Array of CoffeeScript scripts
	 *
	 * @var array
	 **/
	protected static $results = array();

	/**
	 * Errors of compiler
	 *
	 * @var array
	 **/
	protected static $errors = array();

	/**
	 * Do we need to check dates before compile
	 *
	 * @var boolean
	 */
	protected $checkDates = true;

	/**
	 * Do we need compression for JS files
	 *
	 * @var boolean
	 */
	protected $useCompression = false;

	/**
	 * Current coffee-script file to be parsed. This var used to help output errors in callCompiler()
	 *
	 * @var string
	 */
	protected $currentFile;

	/**
	 * Constructor
	 *
	 * @param   boolean $checkDates	 Do we need to check dates before compile
	 * @param   boolean $useCompression Do we need compression for JS files
	 */
	public function __construct($checkDates = true, $useCompression = false) {
		$this->setIsCheckDates($checkDates);
		$this->setIsUseCompression($useCompression);
	}

	/**
	 * Returns array of compiled styles info
	 *
	 * @return  array
	 */
	public static function getCompileResults() {
		return self::$results;
	}

	/**
	 * Returns array of compiled styles errors
	 *
	 * @return  array
	 */
	public static function getCompileErrors() {
		return self::$errors;
	}

	/**
	 * Returns debug info of the current state
	 *
	 * @return  array state
	 */
	public function getDebugInfo() {
		return array(
			'dates' => var_export($this->isCheckDates(), true),
			'compress' => var_export($this->isUseCompression(), true),
			'coffee' => $this->getCsPaths(),
			'js' => $this->getJsPaths()
		);
	}

	/**
	 * Returns path with changed directory separators to unix-style (\ => /)
	 *
	 * @param   string  $path basic path
	 *
	 * @return  string		unix-style path
	 */
	public static function getSepFixedPath($path) {
		return str_replace(DIRECTORY_SEPARATOR, '/', $path);
	}

	/**
	 * Returns relative path from the project root dir
	 *
	 * @param   string  $fullPath full path to file
	 *
	 * @return  string			relative path from the project root
	 */
	public static function getProjectRelativePath($fullPath) {
		return str_replace(
			self::getSepFixedPath(sfConfig::get('sf_root_dir')) . '/',
			'',
			self::getSepFixedPath($fullPath)
		);
	}

	/**
	 * Do we need to check dates before compile
	 *
	 * @return  boolean
	 */
	public function isCheckDates() {
		return sfConfig::get('app_sf_coffeescript_plugin_check_dates', $this->checkDates);
	}

	/**
	 * Set need of check dates before compile
	 *
	 * @param   boolean $checkDates Do we need to check dates before compile
	 */
	public function setIsCheckDates($checkDates) {
		$this->checkDates = $checkDates;
	}

	/**
	 * Do we need compression for JS files
	 *
	 * @return  boolean
	 */
	public function isUseCompression() {
		return sfConfig::get('app_sf_coffeescript_plugin_use_compression', $this->useCompression);
	}

	/**
	 * Set need of compression for JS files
	 *
	 * @param   boolean $useCompression Do we need compression for JS files
	 */
	public function setIsUseCompression($useCompression) {
		$this->useCompression = $useCompression;
	}

	/**
	 * Returns paths to JS files
	 *
	 * @return  string  a path to CSS files directory
	 */
	static public function getJsPaths() {
		return self::getSepFixedPath(sfConfig::get('sf_web_dir')) . '/js/';
	}

	/**
	 * Returns all coffee files under the coffee directory
	 *
	 * @return  array   an array of CSS files
	 */
	static public function findJsFiles() {
		return sfFinder::type('file')
				->exec(array('sfCoffeeScript', 'isJsCsCompiled'))
				->name('*.js')
				->in(self::getJsPaths());
	}

	/**
	 * Returns header text for JS files
	 *
	 * @return  string  a header text for JS files
	 */
	static protected function getCssHeader() {
		return '/* This JS is autocompiled by CoffeeScript parser. Don\'t edit it manually. */';
	}

	/**
	 * Checks if JS file was compiled from coffee
	 *
	 * @param   string  $dir	a path to file
	 * @param   string  $entry  a filename
	 *
	 * @return  boolean
	 */
	static public function isJsCsCompiled($dir, $entry) {
		$file = $dir . '/' . $entry;
		$fp = fopen($file, 'r');
		$line = stream_get_line($fp, 1024, "\n");
		fclose($fp);

		return (0 === strcmp($line, self::getCssHeader()));
	}

	/**
	 * Returns paths to coffee files
	 *
	 * @return  string  a path to coffee files directories
	 */
	static public function getCsPaths() {
		return self::getSepFixedPath(sfConfig::get('sf_web_dir')) . '/coffee/';
	}

	/**
	 * Returns all coffee files under the coffee directories
	 *
	 * @return  array   an array of coffee files
	 */
	static public function findCsFiles() {
		return sfFinder::type('file')
				->name('*.coffee')
				->discard('_*')
				->follow_link()
				->in(self::getCsPaths());
	}

	/**
	 * Returns JS file path by its coffee alternative
	 *
	 * @param   string  $csFile coffee file path
	 *
	 * @return  string			JS file path
	 */
	static public function getJsPathOfCs($csFile) {
		return str_replace(
			array(self::getLessPaths(), '.coffee'),
			array(self::getCssPaths(), '.js'),
			$csFile
		);
	}

	/**
	 * Listens to the routing.load_configuration event. Finds & compiles coffe files to JS
	 *
	 * @param   sfEvent $event  an sfEvent instance
	 */
	static public function findAndCompile(sfEvent $event) {
		// Start compilation timer for debug info
		$timer = sfTimerManager::getTimer('CoffeeScript compilation');

		// Create new helper object & compile CoffeeScript scripts with it
		$csHelper = new self;
		foreach (self::findCsFiles() as $csFile)
		{
			$csHelper->compile($csFile);
		}

		// Stop timer
		$timer->addTime();
	}

	/**
	 * Compiles coffee file to JS
	 *
	 * @param   string  $csFile a coffee file
	 *
	 * @return  boolean		   true if succesfully compiled & false in other way
	 */
	public function compile($csFile) {
		// Creates timer
		$timer = new sfTimer;

		// Gets JS file path
		$jsFile = self::getJsPathOfCs($csFile);

		// Checks if path exists & create if not
		if (!is_dir(dirname($jsFile))) {
			mkdir(dirname($jsFile), 0777, true);
			// PHP workaround to fix nested folders
			chmod(dirname($jsFile), 0777);
		}

		// Is file compiled
		$isCompiled = false;

		// If we check dates - recompile only really old CSS
		if ($this->isCheckDates()) {
			if (!is_file($jsFile) || filemtime($csFile) > filemtime($jsFile)) {
				$isCompiled = $this->callCompiler($csFile, $jsFile);
			}
		}
		else
		{
			$isCompiled = $this->callCompiler($csFile, $jsFile);
		}

		// Adds debug info to debug array
		self::$results[] = array(
			'csFile' => $csFile,
			'jsFile' => $jsFile,
			'compTime' => $timer->getElapsedTime(),
			'isCompiled' => $isCompiled
		);

		return $isCompiled;
	}

	/**
	 * Compress JS by removing whitespaces, tabs, newlines, etc.
	 *
	 * @param   string  $js  JS to be compressed
	 *
	 * @return  string		compressed JS
	 */
	static public function getCompressedJs($js) {
		return str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $js);
	}

	/**
	 * Calls current CoffeeScript compiler for single file
	 *
	 * @param   string  $csFile a coffee file
	 * @param   string  $jsFile  a JS file
	 *
	 * @return  boolean		   true if succesfully compiled & false in other way
	 */
	public function callCompiler($csFile, $jsFile) {
		// Setting current file. We will output this var if compiler throws error
		$this->currentFile = $csFile;

		// Do not try to change the permission of an existing file which we might not own
		$setPermission = !is_file($jsFile);

		// Call compiler
		$buffer = $this->callCoffeeCompiler($csFile, $jsFile);

		// Checks if compiler returns false
		if (false === $buffer) {
			return $buffer;
		}

		// Compress JS if we use compression
		if ($this->isUseCompression()) {
			$buffer = self::getCompressedCss($buffer);
		}

		// Add compiler header to JS & writes it to file
		file_put_contents($jsFile, self::getJsHeader() . "\n\n" . $buffer);

		if ($setPermission) {
			// Set permissions for fresh files only
			chmod($jsFile, 0666);
		}

		// Setting current file to null
		$this->currentFile = null;

		return true;
	}

	/**
	 * Calls coffee compiler for coffee file
	 *
	 * @param   string  $csFile a coffee file
	 * @param   string  $jsFile  a JS file
	 *
	 * @return  string			output
	 */
	public function callCoffeeCompiler($csFile, $jsFile) {
		// Compile with coffee
		$fs = new sfFilesystem;
		$command = sprintf('coffee "%s" "%s"', $csFile, $jsFile);

		if ('1.3.0' <= SYMFONY_VERSION) {
			try
			{
				$fs->execute($command, null, array($this, 'throwCompilerError'));
			}
			catch (RuntimeException $e)
			{
				return false;
			}
		}
		else
		{
			$fs->sh($command);
		}

		return file_get_contents($jsFile);
	}

	/**
	 * Returns true if compiler can throw RuntimeException
	 *
	 * @return boolean
	 */
	public function canThrowExceptions() {
		return (('prod' !== sfConfig::get('sf_environment') || !sfConfig::get('sf_app')) &&
				!(sfConfig::get('sf_web_debug') && sfConfig::get('app_sf_coffeescript_plugin_toolbar', true))
		);
	}

	/**
	 * Throws formatted compiler error
	 *
	 * @param   string  $line error line
	 *
	 * @return  boolean
	 */
	public function throwCompilerError($line) {
		// Generate error description
		$errorDescription = sprintf("CoffeeScript parser error in \"%s\":\n\n%s", $this->currentFile, $line);

		// Adds error description to list of errors
		self::$errors[$this->currentFile] = $errorDescription;

		// Throw exception if allowed & log error otherwise
		if ($this->canThrowExceptions()) {
			throw new sfException($errorDescription);
		}
		else
		{
			sfContext::getInstance()->getLogger()->err($errorDescription);
		}

		return false;
	}
}
