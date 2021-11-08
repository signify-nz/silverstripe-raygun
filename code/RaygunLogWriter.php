<?php
require_once BASE_PATH . '/vendor/autoload.php';
require_once THIRDPARTY_PATH . '/Zend/Log/Writer/Abstract.php';

use Raygun4php\RaygunClient;

class RaygunLogWriter extends Zend_Log_Writer_Abstract {

	/**
	 * @config
	 * @var string The API Key for your application, given on the Raygun 'Application Settings' page
	 */
	private static $api_key;

	/**
	 * @config
	 * @var boolean If false, no errors will be reported to raygun.
	 */
	private static $enabled = true;

	/**
	 * @var string
	 */
	protected $apiKey;

	/**
	 * @var RaygunClient
	 */
	protected $client;

	/**
	 * @config
	 * @var array Parameters to filter from sending to Raygun. {@link RaygunClient::filterParamsFromMessage}
	 */
	private static $filter_params = [
		'/SS_DATABASE_USERNAME/' => true,
		'/SS_DEFAULT_ADMIN_USERNAME/' => true,
		'/KEY/i' => true,
		'/TOKEN/i' => true,
		'/PASSWORD/i' => true,
		'/SECRET/i' => true,
		'/HTTP_AUTHORIZATION/' => true,
		'/PHP_AUTH_PW/' => true,
		'/HTTP_COOKIE/' => true,
		'Authorization' => true,
		'Cookie' => true,
	];

	/**
	 * @param String $apiKey
	 */
	function __construct($apiKey) {
		$this->apiKey = $apiKey;
	}

	function _write($message) {
		// Don't log anything to raygun if this writer has been disabled.
		if (!(bool)Config::inst()->get('RaygunLogWriter', 'enabled')) {
			return;
		}

		$disableTracking = (bool)Config::inst()->get(RaygunClient::class, 'disable_user_tracking');
		// keep track of the current user (if available) so we can identify it in Raygun
		if (!$disableTracking && Member::currentUserID()) {
			$this->getClient()->SetUser(Member::currentUser()->Email);
		}

		// Reverse-engineer the SilverStripe-repackaged exception
		$ex = $this->getException($message);
		if($ex instanceof ReportedException) {
			$this->exception_handler($ex);
		// Regular error handling
		} else {
			// errno param can't be empty for Raygun, as it uses \ErrorException to create the error
			if(empty($message['message']['errno'])) {
				$message['message']['errno'] = 0;
			}

			$this->error_handler($message['message']['errno'], $message['message']['errstr'], $message['message']['errfile'], $message['message']['errline'], array($message['priorityName']));
		}
	}

	/**
	 * Checks if the error reported in the message is an exception and in that case returns an ReportedException
	 *
	 * @param array $message
	 * @return null|\ReportedException
	 */
	protected function getException($message) {
		// the triple '\' in the pattern is in practicality a single backslash to include namespace separators
		if (!preg_match('/^Uncaught ([A-Za-z0-9_\\\]+):(.*)$/', $message['message']['errstr'], $matches)) {
			return null;
		}
		if ($matches[1] != 'Exception' && !is_subclass_of($matches[1], 'Exception')) {
			return null;
		}
		$message['message']['errstr'] = $matches[1] . ': ' . $matches[2];
		return new ReportedException($message['message']);
	}

	public static function factory($config) {
		return Injector::inst()->create('RaygunLogWriter', $config['app_key']);
	}

	function exception_handler($exception) {
		$tags = null;
		$customData = null;
		$this->updateData($tags, $customData);
		$this->getClient()->SendException($exception, $tags, $customData);
	}

	function error_handler($errno, $errstr, $errfile, $errline, $tags ) {
		if($errno === '') $errno = 0; // compat with ErrorException
		$customData = null;
		$this->updateData($tags, $customData);
		$this->getClient()->SendError($errno, $errstr, $errfile, $errline, $tags, $customData);
	}

	function updateData(&$tags, &$customData)
	{
		foreach (ClassInfo::subclassesFor(RaygunLogProcessor::class) as $class) {
			if ($class === RaygunLogProcessor::class) {
				continue;
			}
			singleton($class)->processLogData($tags, $customData);
		}
	}

	function shutdown_function() {
		$error = error_get_last();
		if($error && ($error['type'] & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_PARSE)) != 0) {
			$this->error_handler($error['type'], $error['message'], $error['file'], $error['line'], null);
		}
	}

	/**
	 * @return RaygunClient
	 */
	function getClient() {
		if(!$this->client) {
			$this->client = new RaygunClient($this->apiKey);
			$this->filterSensitiveData();

			// set proxy if configured.
			$proxyHost = Config::inst()->get('RaygunLogWriter', 'proxy_host');
			if (!empty($proxyHost)) {
				$proxy = $proxyHost;
				$proxyPort = Config::inst()->get('RaygunLogWriter', 'proxy_port');
				if (!empty($proxyPort)) {
					$proxy .= ':' . $proxyPort;
				}
				$this->client->setProxy($proxy);
			}
		}

		return $this->client;
	}

	/**
	 * @param RaygunClient
	 */
	function setClient($client) {
		$this->client = $client;
	}

	protected function filterSensitiveData() {
		$this->client->setFilterParams(Config::inst()->get('RaygunLogWriter', 'filter_params'));
	}
}

/**
 * Deal with SilverStripe's limited support for custom exeption handlers.
 * Can't be a real exception as then we can't override final methods.
 */
class ReportedException {
	protected $data;

	function __construct($data) {
		$this->data = $data;
	}

	function getMessage() {
		return $this->data['errstr'];
	}
	function getCode() {
		return $this->data['errno'];
	}
	function getTrace() {
		return $this->data['errcontext'];
	}
	function getFile() {
		return $this->data['errfile'];
	}
	function getLine() {
		return $this->data['errline'];
	}
	function getPrevious() {
		return null;
	}
}
