<?php
/**
 * @package plugins.tvinciDistribution
 * @subpackage api.objects
 */
class KalturaTvinciDistributionJobProviderData extends KalturaConfigurableDistributionJobProviderData
{
	/**
	 * @var string
	 */
	public $xml;

	public function __construct(KalturaDistributionJobData $distributionJobData = null)
	{
		parent::__construct($distributionJobData);

		if(!$distributionJobData)
			return;

		if(!($distributionJobData->distributionProfile instanceof KalturaTvinciDistributionProfile))
			return;

		$fieldValues = unserialize($this->fieldValues);

		$entry = entryPeer::retrieveByPK($distributionJobData->entryDistribution->entryId);
		$extraData = array(
				'entryId' => $entry->getId(),
				'createdAt' => $entry->getCreatedAtAsInt(),
				'broadcasterName' => 'Kaltura-' . $entry->getPartnerId(),
			);

		$thumbAssets = assetPeer::retrieveByIds(explode(',', $distributionJobData->entryDistribution->thumbAssetIds));
		$picRatios = array();
		foreach ( $thumbAssets as $thumbAsset )
		{
			$thumbDownloadUrl = $this->getAssetDownloadUrl( $thumbAsset );

			$ratio = KDLVideoAspectRatio::ConvertFrameSize($thumbAsset->getWidth(), $thumbAsset->getHeight());

			$picRatios[] = array(
					'url' => $thumbDownloadUrl,
					'ratio' => $ratio,
				);

			if ( $thumbAsset->hasTag(thumbParams::TAG_DEFAULT_THUMB) )
			{
				$extraData['defaultThumbUrl'] = $thumbDownloadUrl;
			}
		}

		$extraData['picRatios'] = $picRatios;
		if ( ! isset($extraData['defaultThumbUrl']) && count($picRatios) )
		{
			// Choose the URL of the first resource in the array
			$extraData['defaultThumbUrl'] = $picRatios[0]['url'];
		}

		$flavorAssets = assetPeer::retrieveByIds(explode(',', $distributionJobData->entryDistribution->flavorAssetIds));
		$assetInfo = array();
		foreach ( $flavorAssets as $flavorAsset )
		{
			$this->updateFlavorAssetInfo($assetInfo, $flavorAsset, $fieldValues);
		}

		if ( count($assetInfo) )
		{
			$extraData['assetInfo'] = $assetInfo;
		}

		$feed = new TvinciDistributionFeedHelper($distributionJobData->distributionProfile, $fieldValues, $extraData);

		if ($distributionJobData instanceof KalturaDistributionSubmitJobData)
		{
			$this->xml = $feed->buildSubmitFeed();
		}
		elseif ($distributionJobData instanceof KalturaDistributionUpdateJobData)
		{
			$this->xml = $feed->buildUpdateFeed();
		}
		elseif ($distributionJobData instanceof KalturaDistributionDeleteJobData)
		{
			$this->xml = $feed->buildDeleteFeed();
		}
	}

	private function updateFlavorAssetInfo(array &$assetInfo, $flavorAsset, $fieldValues)
	{
		$assetFlavorParams = assetParamsPeer::retrieveByPK( $flavorAsset->getFlavorParamsId() );
		$assetFlavorParamsName = $assetFlavorParams->getName();

		$videoAssetFieldNames = array(
				TvinciDistributionField::VIDEO_ASSET_MAIN,
				TvinciDistributionField::VIDEO_ASSET_TABLET_MAIN,
				TvinciDistributionField::VIDEO_ASSET_SMARTPHONE_MAIN,
			);

		foreach ( $videoAssetFieldNames as $videoAssetFieldName )
		{
			if ( isset($fieldValues[$videoAssetFieldName]) )
			{
				$configFlavorParamName = $fieldValues[$videoAssetFieldName];

				if ( $configFlavorParamName == $assetFlavorParamsName )
				{
					if ( $videoAssetFieldName == TvinciDistributionField::VIDEO_ASSET_MAIN )
					{
						// Main video asset if PC oriented, so we'll fetch a full path (.mp4) download file URL
						$url = $this->getAssetDownloadUrl($flavorAsset);
					}
					else
					{
						// Other assets will be converted to a .m3u8 URL
						$url = $this->getAssetM3U8DownloadUrl($flavorAsset);
					}

					$assetInfo[$videoAssetFieldName] = array(
							'url' => $url,
							'name' => $assetFlavorParamsName,
						);

					// Note: instead of 'break'ing here, we'll continue to loop in case
					//       the same flavor asset is required by another $videoAssetField
				}
			}
		}
	}

	private function getAssetDownloadUrl($asset)
	{
		$downloadUrl = myPartnerUtils::getCdnHost($asset->getPartnerId()) . $asset->getFinalDownloadUrlPathWithoutKs();
		$downloadUrl .= '/f/' . $asset->getId() . '.' . $asset->getFileExt();
		return $downloadUrl;
	}

	private function getAssetM3U8DownloadUrl($asset)
	{
		$downloadUrl = myPartnerUtils::getCdnHost($asset->getPartnerId())
						. "/index.php/extwidget/playManifest"
						. "/entryId/{$asset->getEntryId()}"
						. "/format/applehttp/protocol/http"
						. "/preferredBitrate/{$asset->getBitrate()}"
						. "/a.m3u8";
		return $downloadUrl;
	}

	private static $map_between_objects = array
	(
		'xml',
	);

	public function getMapBetweenObjects()
	{
		return array_merge(parent::getMapBetweenObjects(), self::$map_between_objects);
	}
}
