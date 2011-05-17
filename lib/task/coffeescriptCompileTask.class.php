<?php

/*
 * This file is part of the sfCoffeeScriptPlugin.
 * (c) 2010 Alexey Tyagunov <atyaga@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * coffeescriptCompileTask compiles CoffeeScript files thru symfony cli task system.
 *
 * @package	sfCoffeeScriptPlugin
 * @subpackage tasks
 * @author	 Alexey Tyagunov <atyaga@gmail.com>
 * @version	1.0.0
 */
class coffeescriptCompileTask extends sfBaseTask {
	/**
	 * @see sfTask
	 */
	protected function configure() {
		$this->addArguments(array(
			new sfCommandArgument('file', sfCommandArgument::OPTIONAL, 'coffee file to compile')
		));

		$this->addOptions(array(
			new sfCommandOption(
				'application', null, sfCommandOption::PARAMETER_OPTIONAL,
				'The application name', null
			),
			new sfCommandOption(
				'env', null, sfCommandOption::PARAMETER_REQUIRED,
				'The environment', 'prod'
			),
			new sfCommandOption(
				'clean', null, sfCommandOption::PARAMETER_NONE,
				'Removing all compiled JS in web/js before compile'
			),
			new sfCommandOption(
				'compress', null, sfCommandOption::PARAMETER_NONE,
				'Compress final JS file'
			),
			new sfCommandOption(
				'debug', null, sfCommandOption::PARAMETER_NONE,
				'Outputs debug info'
			)
		));

		$this->namespace = 'coffeescript';
		$this->name = 'compile';
		$this->briefDescription = 'Recompiles CoffeeScript scripts into web/js';
		$this->detailedDescription = <<<EOF
The [coffeescript:compile|INFO] task recompiles CoffeeScript scripts and puts compiled JS into web/js folder.
Call it with:

  [php symfony coffeescript:compile|INFO]
EOF;
	}

	/**
	 * @see sfTask
	 */
	protected function execute($arguments = array(), $options = array()) {

        if( $options['application'] !== null ) {
            $configuration = ProjectConfiguration::getApplicationConfiguration(
	            $options['application'], $options['env'] ? $options['env'] : 'prod',
                true
            );
      
            $this->setConfiguration($configuration);
        }

		// Remove old JS files if --clean option specified
		if (isset($options['clean']) && $options['clean']) {
			foreach (sfCoffeeScript::findJsFiles() as $jsFile)
			{
				if (!isset($arguments['file']) || (false !== strpos($jsFile, $arguments['file'] . '.js'))) {
					unlink($jsFile);
					$this->logSection('removed', str_replace(sfCoffeeScript::getJsPaths(), '', $jsFile));
				}
			}
		}

		// Inits sfCoffeeScript instance for compilation help
		$csHelper = new sfCoffeeScript(false, isset($options['compress']) && $options['compress']);

		// Outputs debug info
		if (isset($options['debug']) && $options['debug']) {
			foreach ($csHelper->getDebugInfo() as $key => $value)
			{
				$this->logSection('debug', sprintf("%s:\t%s", $key, $value), null, 'INFO');
			}
		}

		// Compiles coffee files
		foreach (sfCoffeeScript::findCsFiles() as $csFile)
		{
			if (!isset($arguments['file']) || (false !== strpos($csFile, $arguments['file'] . '.coffee'))) {
				if ($csHelper->compile($csFile)) {
					if (isset($options['debug']) && $options['debug']) {
						$this->logSection('compiled', sprintf("%s => %s",
							sfCoffeeScript::getProjectRelativePath($csFile),
							sfCoffeeScript::getProjectRelativePath(sfCoffeeScript::getJsPathOfCoffee($csFile))
						), null, 'COMMAND');
					}
					else
					{
						$this->logSection(
							'compiled', sfCoffeeScript::getProjectRelativePath($csFile), null, 'COMMAND'
						);
					}
				}
			}
		}
	}
}
