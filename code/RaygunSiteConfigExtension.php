<?php

class RaygunSiteConfigExtension extends DataExtension
{
	private static $db = [
		'RaygunAPIKey' => 'Varchar',
	];

	public function updateCMSFields(FieldList $fields)
	{
		if (Permission::check('Admin')) {
			// Set up API key field.
			$apiKeyField = ConfirmedPasswordField::create('RaygunAPIKey', 'Raygun API Key', null, null, true, 'Confirm Raygun API Key')
				->setShowOnClickTitle('Change Raygun API Key')
				->setCanBeEmpty(true);
			$description = 'Note: There may be environment-specific values. If that is the case, the value of this field is ignored.';
			if (!$this->owner->RaygunAPIKey) {
				$description .= ' <strong>No key currently set in this field.</strong>';
			}
			$apiKeyField->setDescription($description);
			// Add the field.
			$fields->addFieldToTab('Root.Main', $apiKeyField);
		}
	}
}
