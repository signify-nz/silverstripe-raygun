# RayGun.io integration for SilverStripe

This is a simple module that binds RayGun.io to the error & exception handler of SilverStripe.

## Setup

First, add the composer package as a dependency to your project:

	composer require silverstripe/raygun:*

## Configuration

Then, load in the RayGun application key. Thi is defined in `_ss_environment.php`, like this:

	define('SS_RAYGUN_APP_KEY', 'dwhouq3845y98uiof==');

Alternatively, the API key can be defined in a yaml config file, as well as the minimum level for the error reporting

	RaygunLogWriter:
	  api_key: 'ABCDEF123456=='
	  level: 'SS_Log::WARNING'

### Disable log writer

The `RaygunLogWriter` is enabled by default when the API key is available. There may be some situations where you need the API key to be set but you don't want the log writer enabled by default (e.g. you may not want the log writer enabled in dev or test environments except when triggering some test exception via a `BuildTask`).

```yml
---
Only:
  environment: 'dev'
---
RaygunLogWriter:
  enabled: false
```

Then in your `BuildTask` you can enable that handler as required.

```php
class TriggerTestExtensionTask extends BuildTask
{
    protected $title = 'Trigger Test Exception';
    protected $description = 'Throws an exception. Useful for checking raygun integration is working as expected.';

    public function run($request)
    {
        $env = Director::get_environment_type();
        Config::inst()->update('RaygunLogWriter', 'enabled', true);
        throw new Exception("Test exception thrown from '$env' environment.");
    }
}
```

### Proxy

If you need to forward outgoing requests through a proxy (such as for sites hosted in CWP), you can set the proxy host and optional port via yaml config:

```yml
RaygunLogWriter:
  proxy_host: 'https://proxy.example/'
  proxy_port: '4343'
```

## Filtering

Some error data will be too sensitive to transmit to an external service, such as credit card details or passwords. Since this data is very application specific, Raygun doesn't filter out anything by default. You can configure to either replace or otherwise transform specific values based on their keys. These transformations apply to form data (`$_POST`), custom user data, HTTP headers, and environment data (`$_SERVER`). It does not filter the URL or its `$_GET` parameters, or custom message strings. Since Raygun doesn't log method arguments in stack traces, those don't need filtering. All key comparisons are case insensitive.

Example implementation:

```php
<?php
class CustomRaygunLogWriter extends RaygunLogWriter {

	function getClient() {
		if(!$this->client) {
			$this->client = new \Raygun4php\RaygunClient($this->apiKey);
			$this->client->setFilterParams(array(
				'php_auth_pw' => true,
				'/password/i' => true,
				'firstname' => array($this, 'truncate'),
				'surname' => array($this, 'truncate'),
				'email' => array($this, 'truncate'),
			));
		}

		return $this->client;
	}

	/**
	 * Truncate value, with special treatment for email addresses,
	 * where only the first part is truncated, and the domain is left intact.
	 */
	function truncate($key, $val) {
		if(strpos($val, '@') !== FALSE) {
			return preg_replace('/([\w]{4})(.*)@(.*)/', '$1..@$3', $val);
		} else {
			return substr($val, 0, 4) . '...';
		}
	}
}
```

Can you ask SilverStripe to use this class for logging by dependency injection,
e.g. configured in your `app/_config/config.yml`:

```yml
Injector:
  RaygunLogWriter:
    class: CustomRaygunLogWriter
```

More information about accepted filtering formats is available
in the [Raygun4php](https://github.com/MindscapeHQ/raygun4php) documentation.
