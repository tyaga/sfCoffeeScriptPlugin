<?php

/*
 * This file is part of the sfCoffeeScriptPlugin.
 * (c) 2010 Alexey Tyagunov <atyaga@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * sfWebDebugPanelCoffeeScript implements CoffeeScript web debug panel.
 *
 * @package	sfCoffeeScriptPlugin
 * @subpackage debug
 * @author	 Alexey Tyagunov <atyaga@gmail.com>
 * @version	1.0.0
 */
class sfWebDebugPanelCoffeeScript extends sfWebDebugPanel {
	/**
	 * Listens to LoadDebugWebPanel event & adds this panel to the Web Debug toolbar
	 *
	 * @param   sfEvent $event
	 */
	public static function listenToLoadDebugWebPanelEvent(sfEvent $event) {
		$event->getSubject()->setPanel(
			'coffee-script',
			new self($event->getSubject())
		);
	}

	/**
	 * @see sfWebDebugPanel
	 */
	public function getTitle() {
		return '<img src="/sfCoffeeScriptPlugin/images/javascript.png" alt="" height="16" width="16" /> CoffeeScript';
	}

	/**
	 * @see sfWebDebugPanel
	 */
	public function getPanelTitle() {
		return 'CoffeeScript scripts';
	}

	/**
	 * @see sfWebDebugPanel
	 */
	public function getPanelContent() {
		$panel = $this->getConfigurationContent() .
			'<table class="sfWebDebugLogs" style="width: 300px">
			<tr><th>coffee file</th>
			<th>js file</th>
			<th style="text-align:center;">time (ms)</th></tr>';
		$errorDescriptions = sfCoffeeScript::getCompileErrors();
		foreach (sfCoffeeScript::getCompileResults() as $info)
		{
			$info['error'] = isset($errorDescriptions[$info['csFile']]) ? $errorDescriptions[$info['csFile']] : false;
			$panel .= $this->getInfoContent($info);
		}
		$panel .= '</table>';

		return $panel;
	}

	/**
	 * Returns configuration information for CoffeeScript compiler
	 *
	 * @return  string
	 */
	protected function getConfigurationContent() {
		$debugInfo = '<dl id="coffeescript_debug" style="display: none;">';
		$csHelper = new sfCoffeeScript;
		foreach ($csHelper->getDebugInfo() as $name => $value)
		{
			$debugInfo .= sprintf('<dt style="float:left; width: 100px"><strong>%s:</strong></dt>
	  <dd>%s</dd>', $name, $value);
		}
		$debugInfo .= '</dl>';

		return sprintf(<<<EOF
	  <h2>configuration %s</h2>
	  %s<br/>
EOF
			, $this->getToggler('coffeescript_debug', 'Toggle debug info')
			, $debugInfo
		);
	}

	/**
	 * Returns information row for CoffeeScript style compilation
	 *
	 * @param   array   $info info of compilation process
	 * @return  string
	 */
	protected function getInfoContent($info, $error = false) {
		// ID of error row
		$errorId = md5($info['csFile']);

		// File link for preferred editor
		$fileLink = $this->formatFileLink(
			$info['csFile'], 1, str_replace(sfCoffeeScript::getCsPaths(), '', $info['csFile'])
		);

		// Checking compile & error statuses
		if ($info['isCompiled']) {
			$trStyle = 'background-color:#a1d18d;';
		}
		elseif ($info['error'])
		{
			$this->setStatus(sfLogger::ERR);
			$trStyle = 'background-color:#f18c89;';
			$fileLink .= ' ' . $this->getToggler('cs_error_' . $errorId, 'Toggle error info');
		}
		else
		{
			$trStyle = '';
		}

		// Generating info rows
		$infoRows = sprintf(<<<EOF
	  <tr style="%s">
		<td class="sfWebDebugLogType">%s</td>
		<td class="sfWebDebugLogType">%s</td>
		<td class="sfWebDebugLogNumber" style="text-align:center;">%.2f</td>
	  </tr>
	  <tr id="cs_error_%s" style="display:none;background-color:#f18c89;">
		  <td style="padding-left:15px" colspan="2">%s<td></tr>
EOF
			, $trStyle
			, $fileLink
			, str_replace(sfCoffeeScript::getJsPaths(), '', $info['jsFile'])
			, ($info['isCompiled'] ? $info['compTime'] * 1000 : 0)
			, $errorId
			, $info['error']
		);

		return $infoRows;
	}
}
