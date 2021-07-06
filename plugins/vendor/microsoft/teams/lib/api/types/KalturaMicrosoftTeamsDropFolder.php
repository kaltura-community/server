<?php

/**
 * @package plugins.microsoftTeamsDropFolder
 * @subpackage api.objects
 */
class KalturaMicrosoftTeamsDropFolder extends KalturaRemoteDropFolder
{
	/**
	 * ID of the integration being fulfilled by the drop folder
	 *
	 * @var int
	 */
	public $integrationId;

	/**
	 * @var string
	 * @readonly
	 */
	public $tenantId;

	/**
	 * @var string
	 * @readonly
	 */
	public $clientSecret;

	/**
	 * @var string
	 * @readonly
	 */
	public $clientId;

	/**
	 * @var KalturaStringArray
	 * @readonly
	 */
	public $sites;

	/**
	 * @var KalturaStringArray
	 * @readonly
	 */
	public $drives;

	/**
	 * Associative array, connecting each drive ID with the token for its most recent items.
	 * @var KalturaKeyValueArray
	 * @readonly
	 */
	public $driveTokens;

	/*
	 * mapping between the field on this object (on the left) and the setter/getter on the entry object (on the right)
	 */
	private static $map_between_objects = array
	(
		'tenantId',
		'clientSecret',
		'clientId',
		'sites',
		'drives',
		'driveTokens',
	);

	public function getMapBetweenObjects()
	{
		return array_merge(parent::getMapBetweenObjects(), self::$map_between_objects);
	}

	public function toObject($dbObject = null, $skip = array())
	{
		if (is_null($dbObject)) {
			$dbObject = new MicrosoftTeamsDropFolder();
		}

		if ($this->integrationId)
		{
			$dbVendorIntegrationItem = VendorIntegrationPeer::retrieveByPK($this->integrationId);
			if (!$dbVendorIntegrationItem)
			{
				throw new KalturaAPIException(APIErrors::INVALID_OBJECT_ID, $this->integrationId);
			}

			if ($dbVendorIntegrationItem->getVendorType() != MicrosoftTeamsDropFolderPlugin::getVendorTypeCoreValue(MicrosoftTeamsVendorType::MS_TEAMS))
			{
				throw new KalturaAPIException(APIErrors::INVALID_OBJECT_ID, $this->integrationId);
			}
		}

		if (!$dbObject->getType())
		{
			$dbObject->setType(MicrosoftTeamsDropFolderPlugin::getDropFolderTypeCoreValue(MicrosoftTeamsDropFolderType::MS_TEAMS));
		}

		return parent::toObject($dbObject, $skip);
	}

	public function validateForUsage($sourceObject, $propertiesToSkip = array())
	{
		if (!MicrosoftTeamsDropFolderPlugin::isAllowedPartner(kCurrentContext::getCurrentPartnerId()) || !MicrosoftTeamsDropFolderPlugin::isAllowedPartner($this->partnerId))
		{
			throw new KalturaAPIException (KalturaErrors::PERMISSION_NOT_FOUND, 'Permission not found to use the Microsoft Teams Drop Folder feature.');
		}

		parent::validateForUsage($sourceObject, $propertiesToSkip);
	}
}