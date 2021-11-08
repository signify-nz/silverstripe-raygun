<?php

class RaygunSiteConfigExtension extends DataExtension
{
	private static $db = [
		'RaygunAPIKey' => 'Varchar',
	];

	public function updateCMSFields(FieldList $fields)
	{
		if (Permission::check('Admin')) {
			$fields->addFieldToTab('Root.Main', $apiKeyField = PasswordField::create('RaygunAPIKey', 'Raygun API Key'));
			$description = 'Note: There may be environment-specific values. If that is the case, the value of this field is ignored.';
			if (!$this->owner->RaygunAPIKey) {
				$description .= ' <strong>No key currently set in this field.</strong>';
			}
			$apiKeyField->setDescription($description);
		}
	}
}
