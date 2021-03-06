<?php

/**
 * @file lib/pkp/tests/WebTestCase.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class WebTestCase
 * @ingroup tests
 *
 * @brief Base test class for Selenium functional tests.
 */

import('lib.pkp.tests.PKPTestHelper');
import('lib.pkp.tests.PKPTestCase');

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Interactions\WebDriverActions;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverSelect;
use Facebook\WebDriver\Chrome\ChromeOptions;

abstract class WebTestCase extends PKPTestCase {
	/** @var string Base URL provided from environment */
	public static $baseUrl;

	/** @var int Timeout limit for tests in seconds */
	static protected $timeout;

	protected $captureScreenshotOnFailure = true;
	protected $screenshotPath, $screenshotUrl;

	protected $coverageScriptPath = 'lib/pkp/lib/vendor/phpunit/phpunit-selenium/PHPUnit/Extensions/SeleniumCommon/phpunit_coverage.php';
	protected $coverageScriptUrl = '';

	protected static $driver;

	const CSS_PREFIX = 'css=';
	const ID_PREFIX = 'id=';
	const LABEL_PREFIX='label=';
	const LINK_PREFIX='link=';
	const VALUE_PREFIX='value=';

	/**
	 * Override this method if you want to backup/restore
	 * tables before/after the test.
	 * @return array|PKP_TEST_ENTIRE_DB A list of tables to backup and restore.
	 */
	protected function getAffectedTables() {
		return array();
	}

	/**
	 * @copydoc PHPUnit_Framework_TestCase::setUpBeforeClass()
	 */
	public static function setUpBeforeClass() : void {
		// Retrieve and check configuration.
		self::$baseUrl = getenv('BASEURL');
		self::$timeout = (int) getenv('TIMEOUT') ?: 60; // Default 60 seconds
		if (!self::$driver) {
			$options = new ChromeOptions();

			$browserBinary = getenv('BROWSER_BINARY');
			if ($browserBinary) $options->setBinary($browserBinary);

			$options->addArguments(array('--whitelisted-ips=\'\''));
			$browsersize = getenv('BROWSERSIZE') ?: '1280,960';
			$options->addArguments(array('--window-size=' . $browsersize));
			$caps = DesiredCapabilities::chrome();
			$caps->setCapability(ChromeOptions::CAPABILITY, $options);
			self::$driver = RemoteWebDriver::create(
				'http://localhost:4444/wd/hub',
				$caps,
				self::$timeout * 1000,
				self::$timeout * 1000
			);
		}
		parent::setUpBeforeClass();
	}

	/**
	 * @copydoc PHPUnit_Framework_TestCase::setUp()
	 */
	protected function setUp() : void {
		if (empty(self::$baseUrl)) {
			$this->markTestSkipped(
				'Please set BASEURL as an environment variable.'
			);
		}

		// Set the URL for the script that generates the selenium coverage reports
		$this->coverageScriptUrl = self::$baseUrl . '/' .  $this->coverageScriptPath;

		// See PKPTestCase::setUp() for an explanation
		// of this code.
		if(function_exists('_array_change_key_case')) {
			global $ADODB_INCLUDED_LIB;
			$ADODB_INCLUDED_LIB = 1;
		}

		if (Config::getVar('general', 'installed')) {
			$affectedTables = $this->getAffectedTables();
			if (is_array($affectedTables)) {
				PKPTestHelper::backupTables($affectedTables, $this);
			}
		}

		$cacheManager = CacheManager::getManager();
		$cacheManager->flush(null, CACHE_TYPE_FILE);
		$cacheManager->flush(null, CACHE_TYPE_OBJECT);

		// Clear ADODB's cache
		if (Config::getVar('general', 'installed')) {
			$userDao = DAORegistry::getDAO('UserDAO'); // As good as any
			$userDao->flushCache();
		}

		parent::setUp();
	}

	/**
	 * @copydoc PHPUnit_Framework_TestCase::tearDown()
	 */
	protected function tearDown() : void {
		parent::tearDown();
		if (Config::getVar('general', 'installed')) {
			$affectedTables = $this->getAffectedTables();
			if (is_array($affectedTables)) {
				PKPTestHelper::restoreTables($this->getAffectedTables(), $this);
			} elseif ($affectedTables === PKP_TEST_ENTIRE_DB) {
				PKPTestHelper::restoreDB($this);
			}
		}
	}

	/**
	 * Log in.
	 * @param $username string
	 * @param $password string Optional -- defaults to usernameusername
	 */
	protected function logIn($username, $password = null) {
		// Default to twice username (convention for test data)
		if ($password === null) $password = $username . $username;

		$this->open(self::$baseUrl);
		$this->click('//ul[@id="navigationUser"]//a[contains(text(),"Login")]');
		sleep(5);
		$this->waitForElementPresent($selector='//form[@id="login"]//input[@id="username"]');
		$this->type($selector, $username);
		$this->type('//form[@id="login"]//input[@id="password"]', $password);
		$this->click('//form[@id="login"]//button');
	}

	/**
	 * Self-register a new user account.
	 * @param $data array
	 */
	protected function register($data) {
		// Check that the required parameters are provided
		foreach (array(
			'username', 'givenName', 'familyName'
		) as $paramName) {
			$this->assertTrue(isset($data[$paramName]));
		}

		$username = $data['username'];
		$data = array_merge(array(
			'email' => $username . '@mailinator.com',
			'password' => $username . $username,
			'password2' => $username . $username,
			'roles' => array()
		), $data);

		// Find registration page
		$this->open(self::$baseUrl);
		$registerLink = $this->click('//ul[@id="navigationUser"]//a[contains(text(),"Register")]');
		self::$driver->wait()->until(WebDriverExpectedCondition::stalenessOf($registerLink));

		// Fill in user data
		$this->waitForElementPresent($selector='//form[@id="register"]//input[@id="givenName"]');
		sleep(2); // Avoid intermittent failures to fill in fields in Travis tests
		$this->type($selector, $data['givenName']);
		$this->type('//form[@id="register"]//input[@id="familyName"]', $data['familyName']);
		$this->type('//form[@id="register"]//input[@id="username"]', $username);
		$this->type('//form[@id="register"]//input[@id="email"]', $data['email']);
		$this->type('//form[@id="register"]//input[@id="password"]', $data['password']);
		$this->type('//form[@id="register"]//input[@id="password2"]', $data['password2']);
		if (isset($data['affiliation'])) $this->type('//form[@id="register"]//input[@id="affiliation"]', $data['affiliation']);
		if (isset($data['country'])) $this->select('id=country', 'label=' . $data['country']);

		// Select the specified roles
		foreach ($data['roles'] as $role) {
			$this->click('//label[contains(., \'' . htmlspecialchars($role) . '\')]');
		}

		$this->click('//input[@name=\'privacyConsent\']');

		// Save the new user
		$this->waitForElementPresent($formButtonSelector = '//button[contains(.,\'Register\')]');
		$formButton = $this->click($formButtonSelector);
		self::$driver->wait()->until(WebDriverExpectedCondition::stalenessOf($formButton));
	}

	/**
	 * Log out.
	 */
	protected function logOut() {
		$this->open(self::$baseUrl);
		$actions = new WebDriverActions(self::$driver);
		$actions->moveToElement($this->waitForElementPresent('css=ul#navigationUser>li.profile>a'))
			->perform();
		$actions = new WebDriverActions(self::$driver);
		$actions->click($this->waitForElementPresent('//ul[@id="navigationUser"]//a[contains(text(),"Logout")]'))
			->perform();
	}

	/**
	 * Check for verification errors and
	 * clean the verification error list.
	 */
	protected function verified() {
		if (!$verified = empty($this->verificationErrors)) {
			$this->verificationErrors = array();
		}
		return $verified;
	}

	/**
	 * Open a URL but only if it's not already
	 * the current location.
	 * @param $url string
	 */
	protected function verifyAndOpen($url) {
		$this->verifyLocation('exact:' . $url);
		if (!$this->verified()) {
			$this->open($url);
		}
		$this->waitForLocation($url);
	}

	/**
	 * Types a text into an input field.
	 *
	 * This is done using low-level methods in a way
	 * to simulate actual key-press events that can
	 * trigger autocomplete events or similar.
	 *
	 * @param $box string the locator of the box
	 * @param $letters string the text to type
	 */
	protected function typeText($box, $letters) {
		$this->focus($box);
		$currentContent = '';
		foreach(str_split($letters) as $letter) {
			// The following hack makes jQueryUI behave as
			// if typing in letters manually.
			$currentContent .= $letter;
			$this->type($box, $currentContent);
			$this->typeKeys($box, $letter);
			usleep(300000);
		}
		// Fix one more timing problem on the test server:
		sleep(1);
	}

	/**
	 * Save an Ajax form, waiting for the loading sprite
	 * to be hidden to continue the test execution.
	 * @param $formLocator String
	 */
	protected function submitAjaxForm($formId) {
		$this->assertElementPresent($formId, 'The passed form locator do not point to any form element at the current page.');
		$this->click('css=#' . $formId . ' #submitFormButton');

		// First make sure that the progress indicator is visible.
		$element = $this->find($selector = "css=#$formId .formButtons .pkp_spinner");
		self::$driver->wait()->until($visibilityCondition = WebDriverExpectedCondition::visibilityOf($element));

		// Wait until it disappears (the form submit process is finished).
		self::$driver->wait()->until(WebDriverExpectedCondition::not($visibilityCondition));
	}

	/**
	 * Upload a file using plupload interface.
	 * @param $file string Path to the file relative to the
	 * OmpWebTestCase class file location.
	 */
	protected function uploadFile($file) {
		$this->assertTrue(file_exists($file), 'Test file does not exist.');
		$testFile = realpath($file);

		$this->waitForElementPresent('css=div.moxie-shim-html5 input[type="file"]');
		$this->type('css=div.moxie-shim-html5 input[type="file"]', $testFile);
		self::$driver->wait()->until(WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::xpath('//button[@id="continueButton"]')));
	}

	/**
	 * Download the passed file.
	 * @param $filename string
	 */
	protected function downloadFile($filename) {
		$fileXPath = $this->getEscapedXPathForLink($filename);
		$this->waitForElementPresent($fileXPath);
		$this->click($fileXPath);
		$this->assertAlertNotPresent(); // An authentication failure will lead to a js alert.
		$downloadLinkId = $this->getAttribute($fileXPath . '/@id');
		$this->waitForCondition("window.jQuery('#" . htmlspecialchars($downloadLinkId) . "').hasClass('ui-state-disabled') == false");
	}

	/**
	 * Type a value into a TinyMCE control.
	 * @param $controlPrefix string Prefix of control name
	 * @param $value string Value to enter into control
	 * @param $inline boolean Whether or not the tinymce control is inline
	 */
	protected function typeTinyMCE($controlPrefix, $value, $inline = false) {
		if ($inline) {
			$this->waitForElementPresent('css=div[id^="' . $controlPrefix . '"].mce-content-body');
			self::$driver->executeScript("tinyMCE.get('" . $controlPrefix . "').setContent('" . htmlspecialchars($value, ENT_QUOTES) . "');");
			self::$driver->executeScript("tinyMCE.get('" . $controlPrefix . "').fire('blur');");
		} else {
			$this->waitForElementPresent('css=iframe[id^="' . $controlPrefix . '"]'); // Wait for TinyMCE to init
			self::$driver->executeScript("tinyMCE.get($('textarea[id^=\\'" . htmlspecialchars($controlPrefix) . "\\']').attr('id')).setContent('" . htmlspecialchars($value, ENT_QUOTES) . "');");
		}
	}

	/**
	 * Set the value of an input field and fire the `input` event
	 *
	 * This is required to trigger the event listeners in Vue.js and support the
	 * data binding in form fields. Otherwise, selenium does not fire the event
	 * and the DOM is updated but Vue's data model is not synced.
	 *
	 * @param $selector string A CSS selector compatible with document.querySelector()
	 * @param $value string Value to enter into the control
	 */
	protected function setInputValue($selector, $value) {
		$this->waitForElementPresent('css=' . $selector);
		$this->type('css=' . $selector, $value);
	}

	/**
	 * Add a tag to a TagIt-enabled control
	 * @param $controlPrefix string Prefix of control name
	 * @param $value string Value of new tag
	 */
	protected function addTag($controlPrefix, $value) {
		self::$driver->executeScript('$(\'[id^=\\\'' . htmlspecialchars($controlPrefix) . '\\\']\').tagit(\'createTag\', \'' . htmlspecialchars($value) . '\');');
	}

	/**
	 * Click a button with the specified text
	 * @param $text string
	 */
	protected function clickButton($text) {
		$this->click('//button[text()=\'' . $this->escapeJS($text) . '\']');
	}

	/**
	 * Click a link action with the specified name.
	 * @param $name string Name of link action.
	 */
	protected function clickLinkActionNamed($name) {
		$this->clickButton($name);
	}

	/**
	 * Escape a string for inclusion in JS, typically as part of a selector.
	 * WARNING: This is probably not safe for use outside the test suite.
	 * @param $value string The value to escape.
	 * @return string Escaped string.
	 */
	protected function escapeJS($value) {
		return str_replace('\'', '\\\'', $value);
	}

	/**
	 * Scroll a grid down until it loads all elements.
	 * @param $gridContainerId string The grid container id.
	 */
	protected function scrollGridDown($gridContainerId) {
		$this->waitForElementPresent('css=#' . $gridContainerId . ' .scrollable');
		$loadedItems = 0;
		$totalItems = 1; // Just to start.
		while($loadedItems < $totalItems) {
			self::$driver->executeScript('$(\'.scrollable\', \'#' . $gridContainerId . '\').find(\'tr:visible\').last()[0].scrollIntoView()');
			$this->waitForElementPresent($selector='css=#' . $gridContainerId . ' .gridPagingScrolling');
			$pagingInfo = $this->getText($selector);
			if (!$pagingInfo) break;

			$pagingInfo = explode(' ', $pagingInfo);
			$loadedItems = $pagingInfo[1];
			$totalItems = $pagingInfo[3];
		}
	}

	/**
	 * Scroll page down until the end.
	 */
	protected function scrollPageDown() {
		self::$driver->executeScript('scroll(0, document.body.scrollHeight()');
	}

	protected function _webDriverBy($selector) {
		if (substr($selector,0,strlen(self::CSS_PREFIX))==self::CSS_PREFIX) return WebDriverBy::cssSelector(substr($selector,strlen(self::CSS_PREFIX)));
		if (substr($selector,0,strlen(self::LINK_PREFIX))==self::LINK_PREFIX) return WebDriverBy::linkText(substr($selector,strlen(self::LINK_PREFIX)));
		if (substr($selector,0,strlen(self::ID_PREFIX))==self::ID_PREFIX) return WebDriverBy::id(substr($selector,strlen(self::ID_PREFIX)));
		return WebDriverBy::xpath($selector);

	}

	protected function find($selector) {
		$element = self::$driver->findElement($this->_webDriverBy($selector));
		$element->getLocationOnScreenOnceScrolledIntoView();
		return $element;
	}

	protected function waitForElementPresent($selector) {
		self::$driver->wait()->until(WebDriverExpectedCondition::presenceOfElementLocated($this->_webDriverBy($selector)));
		$element = $this->find($selector);
		$this->assertFalse(empty($element));
		return $element;
	}

	protected function type($selector, $text) {
		$element = $this->waitForElementPresent($selector);
		$element->clear();
		$element->sendKeys($text);
	}

	protected function select($elementSelector, $optionSelector) {
		$element = $this->waitForElementPresent($elementSelector);
		$select = new \Facebook\WebDriver\WebDriverSelect($element);
		if (substr($optionSelector,0,strlen(self::LABEL_PREFIX))==self::LABEL_PREFIX) return $select->selectByVisibleText(substr($optionSelector,strlen(self::LABEL_PREFIX)));
		elseif (substr($optionSelector,0,strlen(self::VALUE_PREFIX))==self::VALUE_PREFIX) return $select->selectByValue(substr($optionSelector,strlen(self::VALUE_PREFIX)));
		else throw new Exception('Unknown selector type!');
	}

	protected function click($selector) {
		self::$driver->wait()->until(WebDriverExpectedCondition::elementToBeClickable($findBy = $this->_webDriverBy($selector)));
		$actions = new WebDriverActions(self::$driver);
		$actions->click($element = self::$driver->findElement($findBy))->perform();
		return $element;
	}

	protected function quoteXpath($string) {
		// Use an xpath concat to escape quotes in literals.
		// http://kushalm.com/the-perils-of-xpath-expressions-specifically-escaping-quotes
		return 'concat(\'' . strtr($this->escapeJS($string),
			array(
				'\\\'' => '\', "\'", \''
			)
		) . '\',\'\')';
	}

	protected function open($url) {
		self::$driver->get($url);
	}

	protected function waitJQuery() {
		$driver = self::$driver;
		self::$driver->wait()->until(function() use ($driver) {
			return $driver->executeScript("return typeof jQuery !== 'undefined' && jQuery.active == 0;");
		});
	}

	protected function onNotSuccessfulTest(Throwable $t) : void {
		// Take a screenshot.
		$screenshotsFolder = BASE_SYS_DIR . '/lib/pkp/tests/results/';
		self::$driver->takeScreenshot($screenshotsFolder . time() . '.png');

		parent::onNotSuccessfulTest($t);
	}
}
