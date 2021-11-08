<?php

/**
 * Allows raygun log data to be processed prior to being sent to raygun.
 */
abstract class RaygunLogProcessor
{
	/**
	 * Process tags and customdata for raygun logs.
	 * e.g. to add new tags or custom data.
	 *
	 * @param array|null $tags
	 * @param array|null $customData
	 */
	public abstract function processLogData(?array &$tags, ?array &$customData);
}
