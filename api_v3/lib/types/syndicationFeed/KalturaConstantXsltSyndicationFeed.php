<?php
/**
 * @abstract
 * @package api
 * @subpackage objects
 */
abstract class KalturaConstantXsltSyndicationFeed extends KalturaGenericXsltSyndicationFeed
{
	protected $xsltPath;

	public function doFromObject($source_object, KalturaDetachedResponseProfile $responseProfile = null)
	{
		parent::doFromObject($source_object, $responseProfile);

		if($this->shouldGet('xslt', $responseProfile))
		{
			$real_path = realpath( $this->xsltPath );
			if ( file_exists ( $real_path ) )
			{
				$startTime = microtime(true);
				$contents = file_get_contents( $real_path);
				KalturaLog::info("Roku xslt file was found [$real_path] fgc took [".(microtime(true) - $startTime)."]");
				$this->xslt = $contents;
			}
			else
			{
				KalturaLog::info("Roku xslt file was not found [$this->xsltPath]");
				throw new KalturaAPIException(KalturaErrors::FILE_NOT_FOUND);
			}
		}
	}
	
	/**
	 * @param SyndicationDistributionProfile $object_to_fill
	 * @param array $props_to_skip
	 * @return genericSyndicationFeed
	 */
	public function toInsertableObject ( $object_to_fill = null , $props_to_skip = array() )
	{
		if($this->xslt)
			throw new KalturaAPIException(KalturaErrors::PROPERTY_VALIDATION_NO_INSERT_PERMISSION, 'xslt');
		
		return parent::toInsertableObject($object_to_fill, $props_to_skip);
	}
	
	/**
	 * @param SyndicationDistributionProfile $object_to_fill
	 * @param array $props_to_skip
	 * @return genericSyndicationFeed
	 */
	public function toUpdatableObject ( $object_to_fill , $props_to_skip = array() )
	{
		if($this->xslt)
			throw new KalturaAPIException(KalturaErrors::PROPERTY_VALIDATION_NOT_UPDATABLE, 'xslt');

		return parent::toUpdatableObject($object_to_fill, $props_to_skip );
	}
}