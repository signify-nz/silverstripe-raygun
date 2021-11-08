<?php

// Check yml and env config for the API key.
$raygunAPIKey = Config::inst()->get('RaygunLogWriter', 'api_key');
if(empty($raygunAPIKey) && defined('SS_RAYGUN_APP_KEY')) {
	$raygunAPIKey = SS_RAYGUN_APP_KEY;
}
// If the SiteConfig table is ready for reads, and the raygun extension is applied, check the DB for an API key.
if (
	empty($raygunAPIKey)
	&& DBTableChecker::databaseIsReady(SiteConfig::class)
	&& SiteConfig::has_extension(RaygunSiteConfigExtension::class)
) {
	$siteConfig = SiteConfig::current_site_config();
	$raygunAPIKey = $siteConfig->RaygunAPIKey;
}

// If we have an API key, set up the raygun log writer.
if(!empty($raygunAPIKey)) {
	$raygun = Injector::inst()->create('RaygunLogWriter', $raygunAPIKey);
	$levelConfig = Config::inst()->get('RaygunLogWriter', 'level');
	$level = defined($levelConfig) ? constant($levelConfig) : SS_Log::WARN;
	SS_Log::add_writer($raygun, $level, '<=');
	register_shutdown_function(array($raygun, 'shutdown_function'));
} else {
	if(Director::isLive()) {
		user_error("SilverStripe Raygun module installed, but API key not defined.", E_USER_WARNING);
	}
}
