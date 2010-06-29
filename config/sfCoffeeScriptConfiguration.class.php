<?php

/*
 * This file is part of the sfCoffeeScriptPlugin.
 * (c) 2010 Alexey Tyagunov <atyaga@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * sfCoffeeScriptPluginConfiguration configures application to use coffee-script compiler.
 *
 * @package	sfCoffeeScriptPlugin
 * @subpackage configuration
 * @author	 Alexey Tyagunov <atyaga@gmail.com>
 * @version	1.0.0
 */
class sfCoffeeScriptPluginConfiguration extends sfPluginConfiguration {
	/**
	 * @see sfPluginConfiguration
	 */
	public function initialize() {
		// Register listener to routing.load_configuration event
		$this->dispatcher->connect(
			'context.load_factories',
			array('sfCoffeeScript', 'findAndCompile')
		);

		// If app_sf_coffeescript_plugin_toolbar in app.yml is set to true (by default)
		if (sfConfig::get('sf_web_debug') && sfConfig::get('app_sf_coffeescript_plugin_toolbar', true)) {
			// Add coffeescript toolbar to Web Debug toolbar
			$this->dispatcher->connect('debug.web.load_panels', array(
				'sfWebDebugPanelCoffeeScript',
				'listenToLoadDebugWebPanelEvent'
			));
		}
	}
}
