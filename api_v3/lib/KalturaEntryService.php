<?php
/**
 * @package api
 * @subpackage services
 */
class KalturaEntryService extends KalturaBaseService 
{
	
	  //amount of time for attempting to grab kLock
	  const KLOCK_MEDIA_UPDATECONTENT_GRAB_TIMEOUT = 0.1;
	
	  //amount of time for holding kLock
	  const KLOCK_MEDIA_UPDATECONTENT_HOLD_TIMEOUT = 7;

	public function initService($serviceId, $serviceName, $actionName)
	{
		$ks = kCurrentContext::$ks_object ? kCurrentContext::$ks_object : null;
		
		if (($actionName == 'list' || $actionName == 'count' || $actionName == 'listByReferenceId') &&
		  (!$ks || (!$ks->isAdmin() && !$ks->verifyPrivileges(ks::PRIVILEGE_LIST, ks::PRIVILEGE_WILDCARD))))
		{			
			KalturaCriterion::enableTag(KalturaCriterion::TAG_WIDGET_SESSION);
			entryPeer::setUserContentOnly(true);
		}
		
		
/*		//to support list categories with entitlement for user that is a member of more then 100 large categories
 		//large category is a category with > 10 members or > 100 entries. 				
  		if ($actionName == 'list' && kEntitlementUtils::getEntitlementEnforcement())
		{
			$dispatcher = KalturaDispatcher::getInstance();
			$arguments = $dispatcher->getArguments();
			
			$categoriesIds = array();
			$categories = array();
			foreach($arguments as $argument)
			{
				if ($argument instanceof KalturaBaseEntryFilter)
				{
					if(isset($argument->categoriesMatchAnd))
						$categories = array_merge($categories, explode(',', $argument->categoriesMatchAnd));
						
					if(isset($argument->categoriesMatchOr))
						$categories = array_merge($categories, explode(',', $argument->categoriesMatchOr));
					
					if(isset($argument->categoriesFullNameIn))
						$categories = array_merge($categories, explode(',', $argument->categoriesFullNameIn));
						
					if(count($categories))
					{
						$categories = categoryPeer::getByFullNamesExactMatch($categories);
						
						foreach ($categories as $category)
							$categoriesIds[] = $category->getId();
					}
										
					if(isset($argument->categoriesIdsMatchAnd))
						$categoriesIds = array_merge($categoriesIds, explode(',', $argument->categoriesIdsMatchAnd));
					
					if(isset($argument->categoriesIdsMatchOr))
						$categoriesIds = array_merge($categoriesIds, explode(',', $argument->categoriesIdsMatchOr));
					
					if(isset($argument->categoryAncestorIdIn))
						$categoriesIds = array_merge($categoriesIds, explode(',', $argument->categoryAncestorIdIn));
				}
			}
			
			foreach($categoriesIds as $key => $categoryId)
			{
				if(!$categoryId)
				{
					unset($categoriesIds[$key]);
				}
			}
			
			if(count($categoriesIds))
				entryPeer::setFilterdCategoriesIds($categoriesIds);
		}*/
		
		parent::initService($serviceId, $serviceName, $actionName);
		$this->applyPartnerFilterForClass('ConversionProfile');
		$this->applyPartnerFilterForClass('conversionProfile2');
	}
	
	/**
	 * @param kResource $resource
	 * @param entry $dbEntry
	 * @param asset $asset
	 * @return asset
	 * @throws KalturaErrors::ENTRY_TYPE_NOT_SUPPORTED
	 */
	protected function attachResource(kResource $resource, entry $dbEntry, asset $asset = null)
	{
		throw new KalturaAPIException(KalturaErrors::ENTRY_TYPE_NOT_SUPPORTED, $dbEntry->getType());
	}
	
	/**
	 * @param KalturaResource $resource
	 * @param entry $dbEntry
	 */
	protected function replaceResource(KalturaResource $resource, entry $dbEntry)
	{
		throw new KalturaAPIException(KalturaErrors::ENTRY_TYPE_NOT_SUPPORTED, $dbEntry->getType());
	}
	
	/**
	 * General code that replaces given entry resource with a given resource, and mark the original
	 * entry as replaced
	 * @param KalturaEntry $dbEntry The original entry we'd like to replace
	 * @param KalturaResource $resource The resource we'd like to attach
	 * @param KalturaEntry $tempMediaEntry The replacing entry
	 * @throws KalturaAPIException
	 */
	protected function replaceResourceByEntry($dbEntry, $resource, $tempMediaEntry) 
	{
		$partner = $this->getPartner();
		if(!$partner->getEnabledService(PermissionName::FEATURE_ENTRY_REPLACEMENT))
		{
			throw new KalturaAPIException(KalturaErrors::FEATURE_FORBIDDEN, PermissionName::FEATURE_ENTRY_REPLACEMENT);
		}
		
		if($dbEntry->getReplacingEntryId())
			throw new KalturaAPIException(KalturaErrors::ENTRY_REPLACEMENT_ALREADY_EXISTS);
		
		$resource->validateEntry($dbEntry);

		// create the temp db entry first and mark it as isTemporary == true
		$tempDbEntry = self::getCoreEntry($tempMediaEntry->type);
		$tempDbEntry->setIsTemporary(true);
		$tempDbEntry->setDisplayInSearch(mySearchUtils::DISPLAY_IN_SEARCH_SYSTEM);
		$tempDbEntry->setReplacedEntryId($dbEntry->getId());

		//For static content trimming we need to pass the adminTags to the temp entry in order to convert with the same flow and the original 
		$adminTags = $dbEntry->getAdminTagsArr();
		$staticContentAdminTags = kConf::get('staticContentAdminTags','runtime_config',array());
		$tempAdminTags = array_intersect($adminTags,$staticContentAdminTags);
		$tempDbEntry->setAdminTags(implode(',',$tempAdminTags));

		$kResource = $resource->toObject();
		if ($kResource->getType() == 'kOperationResource')
			$tempDbEntry->setTempTrimEntry(true);

		$tempDbEntry = $this->prepareEntryForInsert($tempMediaEntry, $tempDbEntry);
		$tempDbEntry->setPartnerId($dbEntry->getPartnerId());
		$tempDbEntry->save();
		
		$dbEntry->setReplacingEntryId($tempDbEntry->getId());
		$dbEntry->setReplacementStatus(entryReplacementStatus::NOT_READY_AND_NOT_APPROVED);
		if(!$partner->getEnabledService(PermissionName::FEATURE_ENTRY_REPLACEMENT_APPROVAL) || $dbEntry->getSourceType() == EntrySourceType::KALTURA_RECORDED_LIVE)
			$dbEntry->setReplacementStatus(entryReplacementStatus::APPROVED_BUT_NOT_READY);
		$dbEntry->save();
		
		$this->updateTempEntryStatus($tempDbEntry);
		
		$this->attachResource($kResource, $tempDbEntry);
	}
	
	protected function updateTempEntryStatus($dbEntry)
	{
	
	}

	protected function validateEntryForReplace($entryId, $dbEntry, $entryType = null)
	{
		if (!$dbEntry)
		{
			throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $entryId);
		}

		if ($entryType && $dbEntry->getType() != $entryType)
		{
			throw new KalturaAPIException(KalturaErrors::INVALID_ENTRY_TYPE, $entryId, $dbEntry->getType(), $entryType);
		}
	}

	public function isApproveReplaceRequired($dbEntry)
	{
		if ($dbEntry->getMediaType() == KalturaMediaType::IMAGE)
		{
			return false;
		}
		return true;
	}

	/**
	 * Approves entry replacement
	 *
	 * @param $dbEntry
	 * @throws KalturaAPIException
	 */
	protected function approveReplace($dbEntry)
	{
		if (!$this->isApproveReplaceRequired($dbEntry))
		{
			return;
		}

		switch ($dbEntry->getReplacementStatus())
		{
			case entryReplacementStatus::APPROVED_BUT_NOT_READY:
				break;

			case entryReplacementStatus::READY_BUT_NOT_APPROVED:
				kBusinessConvertDL::replaceEntry($dbEntry);
				break;

			case entryReplacementStatus::NOT_READY_AND_NOT_APPROVED:
				$dbEntry->setReplacementStatus(entryReplacementStatus::APPROVED_BUT_NOT_READY);
				$dbEntry->save();

				//preventing race conditions of temp entry being ready just as you approve the replacement
				$dbReplacingEntry = entryPeer::retrieveByPK($dbEntry->getReplacingEntryId());
				if ($dbReplacingEntry && $dbReplacingEntry->getStatus() == entryStatus::READY)
					kBusinessConvertDL::replaceEntry($dbEntry);
				break;

			case entryReplacementStatus::NONE:
			case entryReplacementStatus::FAILED:
			default:
				throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_REPLACED, $dbEntry->getId());
				break;
		}
	}

	/**
	 * Cancels media replacement
	 * 
	 * @param $dbEntry
	 * @throws KalturaAPIException
	 */
	protected function cancelReplace($dbEntry)
	{
		if ($dbEntry->getReplacingEntryId())
		{
			$dbTempEntry = entryPeer::retrieveByPK($dbEntry->getReplacingEntryId());
			if ($dbTempEntry)
			{
				myEntryUtils::deleteEntry($dbTempEntry);
			}
		}

		$dbEntry->setReplacingEntryId(null);
		$dbEntry->setReplacementStatus(entryReplacementStatus::NONE);
		$dbEntry->save();
	}

	/**
	 * @param kFileSyncResource $resource
	 * @param entry $dbEntry
	 * @param asset $dbAsset
	 * @return asset | NULL in case of IMAGE entry
	 * @throws KalturaErrors::UPLOAD_ERROR
	 * @throws KalturaErrors::INVALID_OBJECT_ID
	 */
	protected function attachFileSyncResource(kFileSyncResource $resource, entry $dbEntry, asset $dbAsset = null)
	{
		$dbEntry->setSource(entry::ENTRY_MEDIA_SOURCE_KALTURA);
		$dbEntry->save();
		
		try{
			$syncable = kFileSyncObjectManager::retrieveObject($resource->getFileSyncObjectType(), $resource->getObjectId());
		}
		catch(kFileSyncException $e){
			throw new KalturaAPIException(KalturaErrors::INVALID_OBJECT_ID, $resource->getObjectId());
		}
		
		$srcSyncKey = $syncable->getSyncKey($resource->getObjectSubType(), $resource->getVersion());
		$encryptionKey = method_exists($syncable, 'getEncryptionKey') ? $syncable->getEncryptionKey() : null;
		$dbAsset = $this->attachFileSync($srcSyncKey, $dbEntry, $dbAsset, $encryptionKey);
		
		//In case the target entry's media type is image no asset is created and the image is set on a entry level file sync
		if(!$dbAsset && $dbEntry->getMediaType() == KalturaMediaType::IMAGE)
			return null;
		
		// Copy the media info from the old asset to the new one
		if($syncable instanceof asset && $resource->getObjectSubType() == asset::FILE_SYNC_FLAVOR_ASSET_SUB_TYPE_ASSET)
		{
			$mediaInfo = mediaInfoPeer::retrieveByFlavorAssetId($syncable->getId());
			if($mediaInfo)
			{
				$newMediaInfo = $mediaInfo->copy();
				$newMediaInfo->setFlavorAssetId($dbAsset->getId());
				$newMediaInfo->save();
			}
			
			if ($dbAsset->getStatus() == asset::ASSET_STATUS_READY)
			{
				$dbEntry->syncFlavorParamsIds();
				$dbEntry->save();
			}
		}
		
		return $dbAsset;
	}

	/**
	 * @param kLiveEntryResource $resource
	 * @param entry $dbEntry
	 * @param asset $dbAsset
	 * @return array $operationAttributes
	 * @return asset
	 */
	protected function attachLiveEntryResource(kLiveEntryResource $resource, entry $dbEntry, asset $dbAsset = null, array $operationAttributes = null)
	{
		$dbEntry->setRootEntryId($resource->getEntry()->getId());
		$dbEntry->setSource(EntrySourceType::RECORDED_LIVE);
		if ($operationAttributes)
			$dbEntry->setOperationAttributes($operationAttributes);
		$dbEntry->save();
	
		if(!$dbAsset)
		{
			$dbAsset = kFlowHelper::createOriginalFlavorAsset($this->getPartnerId(), $dbEntry->getId());
		}
		
		$offset = null;
		$duration = null;
		$requiredDuration = null;
		$clipAttributes = null;
		if(is_array($operationAttributes))
		{
			foreach($operationAttributes as $operationAttributesItem)
			{
				if($operationAttributesItem instanceof kClipAttributes)
				{
					$clipAttributes = $operationAttributesItem;
					
					// convert milliseconds to seconds
					$offset = $operationAttributesItem->getOffset();
					$duration = $operationAttributesItem->getDuration();
					$requiredDuration = $offset + $duration;
				}
			}
		}
		
		$dbLiveEntry = $resource->getEntry();
		$dbRecordedEntry = entryPeer::retrieveByPK($dbLiveEntry->getRecordedEntryId());
		
		if(!$dbRecordedEntry || ($requiredDuration && $requiredDuration > $dbRecordedEntry->getLengthInMsecs()))
		{
			$mediaServer = $dbLiveEntry->getMediaServer(true);
			if(!$mediaServer)
				throw new KalturaAPIException(KalturaErrors::NO_MEDIA_SERVER_FOUND, $dbLiveEntry->getId());
				
			$mediaServerLiveService = $mediaServer->getWebService($mediaServer->getLiveWebServiceName());
			if($mediaServerLiveService && $mediaServerLiveService instanceof KalturaMediaServerLiveService)
			{
				$mediaServerLiveService->splitRecordingNow($dbLiveEntry->getId());
				$dbLiveEntry->attachPendingMediaEntry($dbEntry, $requiredDuration, $offset, $duration);
				$dbLiveEntry->save();
			}
			else 
			{
				throw new KalturaAPIException(KalturaErrors::MEDIA_SERVER_SERVICE_NOT_FOUND, $mediaServer->getId(), $mediaServer->getLiveWebServiceName());
			}
			return $dbAsset;
		}
		
		$dbRecordedAsset = assetPeer::retrieveOriginalReadyByEntryId($dbRecordedEntry->getId());
		if(!$dbRecordedAsset)
		{
			$dbRecordedAssets = assetPeer::retrieveReadyFlavorsByEntryId($dbRecordedEntry->getId());
			$dbRecordedAsset = array_pop($dbRecordedAssets);
		}
		/* @var $dbRecordedAsset flavorAsset */
		
		$isNewAsset = false;
		if(!$dbAsset)
		{
			$isNewAsset = true;
			$dbAsset = kFlowHelper::createOriginalFlavorAsset($this->getPartnerId(), $dbEntry->getId());
		}
		
		if(!$dbAsset && $dbEntry->getStatus() == entryStatus::NO_CONTENT)
		{
			$dbEntry->setStatus(entryStatus::ERROR_CONVERTING);
			$dbEntry->save();
		}
		
		$sourceSyncKey = $dbRecordedAsset->getSyncKey(flavorAsset::FILE_SYNC_FLAVOR_ASSET_SUB_TYPE_ASSET);
		
		$dbAsset->setFileExt($dbRecordedAsset->getFileExt());
		$dbAsset->save();
		
		$syncKey = $dbAsset->getSyncKey(flavorAsset::FILE_SYNC_FLAVOR_ASSET_SUB_TYPE_ASSET);
		
		try {
			kFileSyncUtils::createSyncFileLinkForKey($syncKey, $sourceSyncKey);
		}
		catch (Exception $e) {
			
			if($dbEntry->getStatus() == entryStatus::NO_CONTENT)
			{
				$dbEntry->setStatus(entryStatus::ERROR_CONVERTING);
				$dbEntry->save();
			}

			$dbAsset->setStatus(flavorAsset::FLAVOR_ASSET_STATUS_ERROR);
			$dbAsset->save();												
			throw $e;
		}
		

		if($requiredDuration)
		{
			$errDescription = '';
 			kBusinessPreConvertDL::decideAddEntryFlavor(null, $dbEntry->getId(), $clipAttributes->getAssetParamsId(), $errDescription, $dbAsset->getId(), array($clipAttributes));
		}
		else
		{
			if($isNewAsset)
				kEventsManager::raiseEvent(new kObjectAddedEvent($dbAsset));
		}
		kEventsManager::raiseEvent(new kObjectDataChangedEvent($dbAsset));
			
		return $dbAsset;
	}
	
	/**
	 * @param kLocalFileResource $resource
	 * @param entry $dbEntry
	 * @param asset $dbAsset
	 * @return asset
	 */
	protected function attachLocalFileResource(kLocalFileResource $resource, entry $dbEntry, asset $dbAsset = null)
	{
		$dbEntry->setSource($resource->getSourceType());
		$dbEntry->save();

		if ($resource->getIsReady())
		{
			return $this->attachFile($resource->getLocalFilePath(), $dbEntry, $dbAsset, $resource->getKeepOriginalFile());
		}
	
		$lowerStatuses = array(
			entryStatus::ERROR_CONVERTING,
			entryStatus::ERROR_IMPORTING,
			entryStatus::PENDING,
			entryStatus::NO_CONTENT,
		);
		
		$entryUpdated = false;
		if(in_array($dbEntry->getStatus(), $lowerStatuses))
		{
			$dbEntry->setStatus(entryStatus::IMPORT);
			$entryUpdated = true;
		}
		
		if($dbEntry->getMediaType() == null && $dbEntry->getType() == entryType::MEDIA_CLIP)
		{
			$mediaType = $resource->getMediaType();
			if($mediaType)
			{
				$dbEntry->setMediaType($mediaType);
				$entryUpdated = true;
			}
		}
		
		if($entryUpdated)
			$dbEntry->save();
		
		// TODO - move image handling to media service
		if($dbEntry->getMediaType() == KalturaMediaType::IMAGE)
		{
			$resource->attachCreatedObject($dbEntry);
			return null;
		}
		
		$isNewAsset = false;
		if(!$dbAsset)
		{
			$isNewAsset = true;
			$dbAsset = kFlowHelper::createOriginalFlavorAsset($this->getPartnerId(), $dbEntry->getId());
		}
		
		if(!$dbAsset)
		{
			if($dbEntry->getStatus() == entryStatus::NO_CONTENT)
			{
				$dbEntry->setStatus(entryStatus::ERROR_CONVERTING);
				$dbEntry->save();
			}
			
			return null;
		}
		
		$dbAsset->setStatus(asset::FLAVOR_ASSET_STATUS_IMPORTING);
		$dbAsset->save();
		
		$resource->attachCreatedObject($dbAsset);
		
		return $dbAsset;
	}
	
	/**
	 * @param string $entryFullPath
	 * @param entry $dbEntry
	 * @param asset $dbAsset
	 * @return asset
	 * @throws KalturaErrors::UPLOAD_TOKEN_INVALID_STATUS_FOR_ADD_ENTRY
	 * @throws KalturaErrors::UPLOADED_FILE_NOT_FOUND_BY_TOKEN
	 */
	protected function attachFile($entryFullPath, entry $dbEntry, asset $dbAsset = null, $copyOnly = false)
	{
		if (myUploadUtils::isFileTypeRestricted($entryFullPath))
		{
			throw new KalturaAPIException(KalturaErrors::FILE_CONTENT_NOT_SECURE);
		}
		$ext = pathinfo($entryFullPath, PATHINFO_EXTENSION);
		
		if($dbEntry->getType() == entryType::DOCUMENT)
		{
			switch($ext)
			{
				case ('pdf'):
					$dbEntry->setDocumentType(KalturaDocumentType::PDF);
					break;
				case('swf'):
					$dbEntry->setDocumentType(KalturaDocumentType::SWF);
					break;
					
				default:
					$dbEntry->setDocumentType(KalturaDocumentType::DOCUMENT);
					break;
			}
		}
		// TODO - move image handling to media service
		if($dbEntry->getMediaType() == KalturaMediaType::IMAGE)
		{
			$exifImageType = @exif_imagetype($entryFullPath);
			$validTypes = array(
				IMAGETYPE_JPEG,
				IMAGETYPE_TIFF_II,
				IMAGETYPE_TIFF_MM,
				IMAGETYPE_IFF,
				IMAGETYPE_PNG
			);
			
			if(in_array($exifImageType, $validTypes))
			{
				$exifData = @exif_read_data($entryFullPath);
				if ($exifData && isset($exifData["DateTimeOriginal"]) && $exifData["DateTimeOriginal"])
				{
					$mediaDate = $exifData["DateTimeOriginal"];
					
					// handle invalid dates either due to bad format or out of range
					if (!strtotime($mediaDate)){
						$mediaDate=null;
					}
					$dbEntry->setMediaDate($mediaDate);
				}
			}

			$allowedImageTypes = kConf::get("image_file_ext");
			if (in_array($ext, $allowedImageTypes))
				$dbEntry->setData("." . $ext);		
 			else		
 				$dbEntry->setData(".jpg");

			list($width, $height, $type, $attr) = getimagesize($entryFullPath);
			$dbEntry->setDimensions($width, $height);
			$dbEntry->setData(".jpg"); // this will increase the data version
			$dbEntry->save();
			$syncKey = $dbEntry->getSyncKey(kEntryFileSyncSubType::DATA);
			try
			{
				kFileSyncUtils::moveFromFile($entryFullPath, $syncKey, true, $copyOnly);
			}
			catch (Exception $e) {
				if($dbEntry->getStatus() == entryStatus::NO_CONTENT)
				{
					$dbEntry->setStatus(entryStatus::ERROR_CONVERTING);
					$dbEntry->save();
				}											
				throw $e;
			}
			
			$dbEntry->setStatus(entryStatus::READY);
			$dbEntry->save();	
				
			return null;
		}
		
		$isNewAsset = false;
		if(!$dbAsset)
		{
			$isNewAsset = true;
			$dbAsset = kFlowHelper::createOriginalFlavorAsset($this->getPartnerId(), $dbEntry->getId());
		}
		
		if(!$dbAsset && $dbEntry->getStatus() == entryStatus::NO_CONTENT)
		{
			$dbEntry->setStatus(entryStatus::ERROR_CONVERTING);
			$dbEntry->save();
		}
		
		$dbAsset->setFileExt($ext);
		
		if($dbAsset && ($dbAsset instanceof thumbAsset))
		{
			list($width, $height, $type, $attr) = getimagesize($entryFullPath);
			$dbAsset->setWidth($width);
			$dbAsset->setHeight($height);
			$dbAsset->save();
		}
		
		$syncKey = $dbAsset->getSyncKey(flavorAsset::FILE_SYNC_FLAVOR_ASSET_SUB_TYPE_ASSET);
		
		try
		{
			kFileSyncUtils::moveFromFile($entryFullPath, $syncKey, true, $copyOnly);
			$fileSync = kFileSyncUtils::getLocalFileSyncForKey($syncKey);
			$dbAsset->setSize($fileSync->getFileSize());
			$dbAsset->save();
		}
		catch (Exception $e) {
			
			if($dbEntry->getStatus() == entryStatus::NO_CONTENT)
			{
				$dbEntry->setStatus(entryStatus::ERROR_CONVERTING);
				$dbEntry->save();
			}
			
			$dbAsset->setStatus(flavorAsset::FLAVOR_ASSET_STATUS_ERROR);
			$dbAsset->save();												
			throw $e;
		}
		
		if($dbAsset && !($dbAsset instanceof flavorAsset))
		{
		    $dbAsset->setStatusLocalReady();
				
			if($dbAsset->getFlavorParamsId())
			{
				$dbFlavorParams = assetParamsPeer::retrieveByPK($dbAsset->getFlavorParamsId());
				if($dbFlavorParams)
					$dbAsset->setTags($dbFlavorParams->getTags());
			}
			$dbAsset->save();
		}
		
		if($isNewAsset)
			kEventsManager::raiseEvent(new kObjectAddedEvent($dbAsset));
		kEventsManager::raiseEvent(new kObjectDataChangedEvent($dbAsset));
			
		return $dbAsset;
	}
	
	/**
	 * @param FileSyncKey $srcSyncKey
	 * @param entry $dbEntry
	 * @param asset $dbAsset
	 * @param string $encryptionKey
	 * @return asset
	 * @throws KalturaErrors::ORIGINAL_FLAVOR_ASSET_NOT_CREATED
	 */
	protected function attachFileSync(FileSyncKey $srcSyncKey, entry $dbEntry, asset $dbAsset = null, $encryptionKey = null)
	{
		// TODO - move image handling to media service
		if($dbEntry->getMediaType() == KalturaMediaType::IMAGE)
		{
			$syncKey = $dbEntry->getSyncKey(kEntryFileSyncSubType::DATA);
	   		kFileSyncUtils::createSyncFileLinkForKey($syncKey, $srcSyncKey);
	   		
			$dbEntry->setStatus(entryStatus::READY);
			$dbEntry->save();	
				
			return null;
		}
		
	  	$isNewAsset = false;
	  	if(!$dbAsset)
	  	{
	  		$isNewAsset = true;
			$fileExt = Null;
			$assetObject = assetPeer::retrieveById($srcSyncKey->getObjectId());
			if($assetObject)
			{
				$fileExt = $assetObject->getFileExt();
			}
			$dbAsset = kFlowHelper::createOriginalFlavorAsset($this->getPartnerId(), $dbEntry->getId(), $fileExt);
	  	}
	  	
		if(!$dbAsset)
		{
			KalturaLog::err("Flavor asset not created for entry [" . $dbEntry->getId() . "]");
			
			if($dbEntry->getStatus() == entryStatus::NO_CONTENT)
			{
				$dbEntry->setStatus(entryStatus::ERROR_CONVERTING);
				$dbEntry->save();
			}
			
			throw new KalturaAPIException(KalturaErrors::ORIGINAL_FLAVOR_ASSET_NOT_CREATED);
		}

		$newSyncKey = $dbAsset->getSyncKey(flavorAsset::FILE_SYNC_FLAVOR_ASSET_SUB_TYPE_ASSET);
		kFileSyncUtils::createSyncFileLinkForKey($newSyncKey, $srcSyncKey);

		if ($encryptionKey)
		{
			$dbAsset->setEncryptionKey($encryptionKey);
		}

		if($isNewAsset)
			kEventsManager::raiseEvent(new kObjectAddedEvent($dbAsset));
		kEventsManager::raiseEvent(new kObjectDataChangedEvent($dbAsset));
			
		return $dbAsset;
	}
	
	/**
	 * @param kOperationResource $resource
	 * @param entry $destEntry
	 * @param asset $dbAsset
	 * @return asset
	 */
	protected function attachOperationResource(kOperationResource $resource, entry $destEntry, asset $dbAsset = null)
	{
		$operationAttributes = $resource->getOperationAttributes();
		$internalResource = $resource->getResource();
		$srcEntry = self::getEntryFromContentResource($resource->getResource());
		$isLiveClippingFlow = $srcEntry && myEntryUtils::isLiveClippingEntry($srcEntry);
		if ($isLiveClippingFlow)
		{
			$this->handleLiveClippingFlow($srcEntry, $destEntry, $operationAttributes);
		}
		elseif($internalResource instanceof kLiveEntryResource)
		{
			$dbAsset = $this->attachLiveEntryResource($internalResource, $destEntry, $dbAsset, $operationAttributes);
		}
		else
		{
			$clipManager = new kClipManager();
			$this->handleMultiClipRequest($resource, $destEntry, $clipManager);
		}
		return $dbAsset;
	}

	/**
	 * @param kOperationResources $resources
	 * @param entry $destEntry
	 * @throws KalturaAPIException
	 */
	protected function attachOperationResources(kOperationResources $resources, entry $destEntry)
	{
		$clipManager = new kClipManager();
		$this->handleMultiResourceMultiClipRequest($resources, $destEntry, $clipManager);
	}

	protected function handleLiveClippingFlow($recordedEntry, $clippedEntry, $operationAttributes)
	{
		if (($recordedEntry->getId() == $clippedEntry->getId()) || ($recordedEntry->getId() == $clippedEntry->getReplacedEntryId()))
			throw new KalturaAPIException(KalturaErrors::LIVE_CLIPPING_UNSUPPORTED_OPERATION, "Trimming");
		$clippedTask = $this->createRecordedClippingTask($recordedEntry, $clippedEntry, $operationAttributes);
		$clippedEntry->setSource(EntrySourceType::KALTURA_RECORDED_LIVE);
		$clippedEntry->setConversionProfileId($recordedEntry->getConversionProfileId());
		$clippedEntry->setRootEntryId($recordedEntry->getRootEntryId());
		$clippedEntry->setIsRecordedEntry(true);
		$clippedEntry->setFlowType(EntryFlowType::LIVE_CLIPPING);
		$clippedEntry->setStatus(entryStatus::PENDING);
		$clippedEntry->save();
		return $clippedTask;
	}

	protected function createRecordedClippingTask(entry $srcEntry, entry $targetEntry, $operationAttributes)
	{
		$liveEntryId = $srcEntry->getRootEntryId();
		$entryServerNode = EntryServerNodePeer::retrieveByEntryIdAndServerType($liveEntryId, EntryServerNodeType::LIVE_PRIMARY);
		if (!$entryServerNode)
		{
			KalturaLog::debug("Can't create clipping task for SrcEntry: ". $srcEntry->getId() . " to entry:" . $targetEntry->getId() . " with: " . print_r($operationAttributes ,true));
			throw new KalturaAPIException(KalturaErrors::ENTRY_SERVER_NODE_NOT_FOUND, $liveEntryId, EntryServerNodeType::LIVE_PRIMARY);
		}
		$serverNode = ServerNodePeer::retrieveByPK($entryServerNode->getServerNodeId());

		$clippingTask = new ClippingTaskEntryServerNode();
		$clippingTask->setClippedEntryId($targetEntry->getId());
		$clippingTask->setLiveEntryId($liveEntryId);
		$clippingTask->setClipAttributes(self::getKClipAttributesForLiveClippingTask($operationAttributes));
		$clippingTask->setServerType(EntryServerNodeType::LIVE_CLIPPING_TASK);
		$clippingTask->setStatus(EntryServerNodeStatus::TASK_PENDING);
		$clippingTask->setEntryId($srcEntry->getId()); //recorded entry
		$clippingTask->setPartnerId($serverNode->getPartnerId()); //in case on eCDN it will get the local partner (not -5)
		$clippingTask->setServerNodeId($serverNode->getId());
		$clippingTask->save();
		return $clippingTask;
	}

	/**
	 * @param kContentResource $internalResource
	 * @return entry|null
	 */
	private static function getEntryFromContentResource($internalResource)
	{
		if ($internalResource && $internalResource instanceof kFileSyncResource)
		{
			$entryId = $internalResource->getOriginEntryId();
			if ($entryId)
				return entryPeer::retrieveByPK($entryId);
		}
		return null;
	}

	/**
	 * @return kClipAttributes
	 */
	protected static function getKClipAttributesForLiveClippingTask($operationAttributes)
	{
		if ($operationAttributes && count($operationAttributes) == 1 && $operationAttributes[0] instanceof kClipAttributes)
			return $operationAttributes[0];
		throw new KalturaAPIException(KalturaErrors::LIVE_CLIPPING_UNSUPPORTED_OPERATION, "Concat");
	}

	/**
	 * @param IRemoteStorageResource $resource
	 * @param entry $dbEntry
	 * @param asset $dbAsset
	 * @return asset
	 * @throws KalturaErrors::ORIGINAL_FLAVOR_ASSET_NOT_CREATED
	 * @throws KalturaErrors::STORAGE_PROFILE_ID_NOT_FOUND
	 */
	protected function attachRemoteStorageResource(IRemoteStorageResource $resource, entry $dbEntry, asset $dbAsset = null)
	{
		$resources = $resource->getResources();
		$fileExt = $resource->getFileExt();
		$dbEntry->setSource(KalturaSourceType::URL);
	
		// TODO - move image handling to media service
		if($dbEntry->getMediaType() == KalturaMediaType::IMAGE)
		{
			$syncKey = $dbEntry->getSyncKey(kEntryFileSyncSubType::DATA);
			foreach($resources as $currentResource)
			{
				$storageProfile = StorageProfilePeer::retrieveByPK($currentResource->getStorageProfileId());
				$fileSync = kFileSyncUtils::createReadyExternalSyncFileForKey($syncKey, $currentResource->getUrl(), $storageProfile);
			}
			
			$dbEntry->setStatus(entryStatus::READY);
			$dbEntry->save();
				
			return null;
		}
		$dbEntry->save();
		
	  	$isNewAsset = false;
	  	if(!$dbAsset)
	  	{
	  		$isNewAsset = true;
			$dbAsset = kFlowHelper::createOriginalFlavorAsset($this->getPartnerId(), $dbEntry->getId());
	  	}
	  	
		if(!$dbAsset)
		{
			KalturaLog::err("Flavor asset not created for entry [" . $dbEntry->getId() . "]");
			
			if($dbEntry->getStatus() == entryStatus::NO_CONTENT)
			{
				$dbEntry->setStatus(entryStatus::ERROR_CONVERTING);
				$dbEntry->save();
			}
			
			throw new KalturaAPIException(KalturaErrors::ORIGINAL_FLAVOR_ASSET_NOT_CREATED);
		}
				
		$syncKey = $dbAsset->getSyncKey(flavorAsset::FILE_SYNC_FLAVOR_ASSET_SUB_TYPE_ASSET);
	
		foreach($resources as $currentResource)
		{
			$storageProfile = StorageProfilePeer::retrieveByPK($currentResource->getStorageProfileId());
			$fileSync = kFileSyncUtils::createReadyExternalSyncFileForKey($syncKey, $currentResource->getUrl(), $storageProfile);
		}

		$dbAsset->setFileExt($fileExt);
				
		if($dbAsset instanceof flavorAsset && !$dbAsset->getIsOriginal())
			$dbAsset->setStatus(asset::FLAVOR_ASSET_STATUS_READY);
			
		$dbAsset->save();
		
		if($isNewAsset)
			kEventsManager::raiseEvent(new kObjectAddedEvent($dbAsset));
		kEventsManager::raiseEvent(new kObjectDataChangedEvent($dbAsset));
			
		if($dbAsset instanceof flavorAsset && !$dbAsset->getIsOriginal())
			kBusinessPostConvertDL::handleConvertFinished(null, $dbAsset);
		
		return $dbAsset;
	}

	/**
	 * @param kUrlResource $resource
	 * @param entry $dbEntry
	 * @param asset $dbAsset
	 * @return asset
	 */
	protected function attachUrlResource(kUrlResource $resource, entry $dbEntry, asset $dbAsset = null)
	{
		$dbEntry->setSource(entry::ENTRY_MEDIA_SOURCE_URL);
		$dbEntry->save();

		$url = $resource->getUrl();

		if (!$resource->getForceAsyncDownload())
		{
			$ext = pathinfo($url, PATHINFO_EXTENSION);
			// TODO - move image handling to media service
    		if($dbEntry->getMediaType() == KalturaMediaType::IMAGE)
    		{
    		    $entryFullPath = myContentStorage::getFSUploadsPath() . '/' . $dbEntry->getId() . '.' . $ext;

                 //curl does not supports sftp protocol, therefore we will use 'addImportJob'
                if (!kString::beginsWith( $url , infraRequestUtils::PROTOCOL_SFTP))
    		    {
                    if (KCurlWrapper::getDataFromFile($url, $entryFullPath) && !myUploadUtils::isFileTypeRestricted($entryFullPath))
                    {
                        return $this->attachFile($entryFullPath, $dbEntry, $dbAsset);
                    }

                    KalturaLog::err("Failed downloading file[$url]");
                    $dbEntry->setStatus(entryStatus::ERROR_IMPORTING);
                    $dbEntry->save();

                    return null;
                }
    		}

    		if($dbAsset && !($dbAsset instanceof flavorAsset))
    		{
    			$entryFullPath = myContentStorage::getFSUploadsPath() . '/' . $dbEntry->getId() . '.' . $ext;
    			if (KCurlWrapper::getDataFromFile($url, $entryFullPath) && !myUploadUtils::isFileTypeRestricted($entryFullPath))
    			{
    				$dbAsset = $this->attachFile($entryFullPath, $dbEntry, $dbAsset);
    				return $dbAsset;
    			}

    			KalturaLog::err("Failed downloading file[$url]");
    			$dbAsset->setStatus(asset::FLAVOR_ASSET_STATUS_ERROR);
    			$dbAsset->save();

    			return null;
    		}
		}

		kJobsManager::addImportJob(null, $dbEntry->getId(), $this->getPartnerId(), $url, $dbAsset, null, $resource->getImportJobData());

		return $dbAsset;
	}

	/**
	 * @param kAssetsParamsResourceContainers $resource
	 * @param entry $dbEntry
	 * @return asset
	 */
	protected function attachAssetsParamsResourceContainers(kAssetsParamsResourceContainers $resource, entry $dbEntry)
	{
		$ret = null;
		foreach($resource->getResources() as $assetParamsResourceContainer)
		{
			KalturaLog::debug("Resource asset params id [" . $assetParamsResourceContainer->getAssetParamsId() . "]");
			$dbAsset = $this->attachAssetParamsResourceContainer($assetParamsResourceContainer, $dbEntry);
			if(!$dbAsset)
				continue;

			KalturaLog::debug("Resource asset id [" . $dbAsset->getId() . "]");

			if($dbAsset->getIsOriginal())
				$ret = $dbAsset;
		}
		$dbEntry->save();

		return $ret;
	}

	/**
	 * @param kAssetParamsResourceContainer $resource
	 * @param entry $dbEntry
	 * @param asset $dbAsset
	 * @return asset
	 * @throws KalturaErrors::FLAVOR_PARAMS_ID_NOT_FOUND
	 */
	protected function attachAssetParamsResourceContainer(kAssetParamsResourceContainer $resource, entry $dbEntry, asset $dbAsset = null)
	{
		$assetParams = assetParamsPeer::retrieveByPK($resource->getAssetParamsId());
		if(!$assetParams)
			throw new KalturaAPIException(KalturaErrors::FLAVOR_PARAMS_ID_NOT_FOUND, $resource->getAssetParamsId());
			
		if(!$dbAsset)
			$dbAsset = assetPeer::retrieveByEntryIdAndParams($dbEntry->getId(), $resource->getAssetParamsId());
			
		$isNewAsset = false;
		if(!$dbAsset)
		{
			$isNewAsset = true;
			$dbAsset = assetPeer::getNewAsset($assetParams->getType());
			$dbAsset->setPartnerId($dbEntry->getPartnerId());
			$dbAsset->setEntryId($dbEntry->getId());
			$dbAsset->setStatus(asset::FLAVOR_ASSET_STATUS_QUEUED);
			
			$dbAsset->setFlavorParamsId($resource->getAssetParamsId());
			$dbAsset->setFromAssetParams($assetParams);
			if($assetParams->hasTag(assetParams::TAG_SOURCE))
				$dbAsset->setIsOriginal(true);
		}
		$dbAsset->incrementVersion();
		$dbAsset->save();
		
		$dbAsset = $this->attachResource($resource->getResource(), $dbEntry, $dbAsset);
		
		if($dbAsset && $isNewAsset && $dbAsset->getStatus() != asset::FLAVOR_ASSET_STATUS_IMPORTING)
			kEventsManager::raiseEvent(new kObjectAddedEvent($dbAsset));
		
		return $dbAsset;
	}
	
	/**
	 * @param KalturaBaseEntry $entry
	 * @param entry $dbEntry
	 * @return entry
	 */
	protected function prepareEntryForInsert(KalturaBaseEntry $entry, entry $dbEntry = null)
	{
		// create a default name if none was given
		if (!$entry->name && !($dbEntry && $dbEntry->getName()))
		{
			$generatedName = $this->getPartnerId() . '_' . time();
			$entry->name = multiLingualUtils::getMultiLingualStringArrayFromString($generatedName);
		}
			
		if ($entry->licenseType === null)
			$entry->licenseType = KalturaLicenseType::UNKNOWN;
		
		// first copy all the properties to the db entry, then we'll check for security stuff
		if(!$dbEntry)
		{
			$dbEntry = self::getCoreEntry($entry->type);
		}
			
		$dbEntry = $entry->toInsertableObject($dbEntry);

		$this->checkAndSetValidUserInsert($entry, $dbEntry);
		$this->checkAdminOnlyInsertProperties($entry);
		$this->validateAccessControlId($entry);
		$this->validateEntryScheduleDates($entry, $dbEntry);
			
		$dbEntry->setPartnerId($this->getPartnerId());
		$dbEntry->setSubpId($this->getPartnerId() * 100);
		$dbEntry->setDefaultModerationStatus();
				
		return $dbEntry;
	}

	protected static function getCoreEntry($entryApiType)
	{
		$entryCoreType = kPluginableEnumsManager::apiToCore('entryType', $entryApiType);
		$class = entryPeer::getEntryClassByType($entryCoreType);

		KalturaLog::debug("Creating new entry of API type [$entryApiType] core type [$entryCoreType] class [$class]");
		return new $class();
	}
	
	/**
	 * Adds entry
	 * 
	 * @param KalturaBaseEntry $entry
	 * @return entry
	 */
	protected function add(KalturaBaseEntry $entry, $conversionProfileId = null)
	{
		$dbEntry = $this->duplicateTemplateEntry($conversionProfileId, $entry->templateEntryId, self::getCoreEntry($entry->type));
		if ($dbEntry)
		{
			$dbEntry->save();
		}
		return $this->prepareEntryForInsert($entry, $dbEntry);
	}
	
	protected function duplicateTemplateEntry($conversionProfileId, $templateEntryId, $object_to_fill = null)
	{
		$templateEntry = $this->getTemplateEntry($conversionProfileId, $templateEntryId);
		if (!$object_to_fill)
			$object_to_fill = new entry();
		/* entry $baseTo */
		return $object_to_fill->copyTemplate($templateEntry, true);
	}

	protected function getTemplateEntry($conversionProfileId, $templateEntryId)
	{
		if(!$templateEntryId)
		{
			$conversionProfile = myPartnerUtils::getConversionProfile2ForPartner($this->getPartnerId(), $conversionProfileId);
			if($conversionProfile)
				$templateEntryId = $conversionProfile->getDefaultEntryId();
		}
		if($templateEntryId)
		{
			$templateEntry = entryPeer::retrieveByPKNoFilter($templateEntryId, null, false);
			return $templateEntry;
		}
		return null;
	}
	
	/**
	 * Convert entry
	 * 
	 * @param string $entryId Media entry id
	 * @param int $conversionProfileId
	 * @param KalturaConversionAttributeArray $dynamicConversionAttributes
	 * @return bigint job id
	 * @throws KalturaErrors::ENTRY_ID_NOT_FOUND
	 * @throws KalturaErrors::CONVERSION_PROFILE_ID_NOT_FOUND
	 * @throws KalturaErrors::FLAVOR_PARAMS_NOT_FOUND
	 */
	protected function convert($entryId, $conversionProfileId = null, KalturaConversionAttributeArray $dynamicConversionAttributes = null)
	{
		$entry = entryPeer::retrieveByPK($entryId);

		if (!$entry)
			throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $entryId);
		
		$srcFlavorAsset = assetPeer::retrieveOriginalByEntryId($entryId);
		if(!$srcFlavorAsset)
			throw new KalturaAPIException(KalturaErrors::ORIGINAL_FLAVOR_ASSET_IS_MISSING);
		
		if(is_null($conversionProfileId) || $conversionProfileId <= 0)
		{
			$conversionProfile = myPartnerUtils::getConversionProfile2ForEntry($entryId);
			if(!$conversionProfile)
				throw new KalturaAPIException(KalturaErrors::CONVERSION_PROFILE_ID_NOT_FOUND, $conversionProfileId);
			
			$conversionProfileId = $conversionProfile->getId();
		} 

		else {
			//The search is with the entry's partnerId. so if conversion profile wasn't found it means that the 
			//conversionId is not exist or the conversion profileId does'nt belong to this partner.
			$conversionProfile = conversionProfile2Peer::retrieveByPK ( $conversionProfileId );
			if (is_null ( $conversionProfile )) {
				throw new KalturaAPIException ( KalturaErrors::CONVERSION_PROFILE_ID_NOT_FOUND, $conversionProfileId );
			}
		}
		
		$srcSyncKey = $srcFlavorAsset->getSyncKey(flavorAsset::FILE_SYNC_FLAVOR_ASSET_SUB_TYPE_ASSET);
		
		// if the file sync isn't local (wasn't synced yet) proxy request to other datacenter
		list($fileSync, $local) = kFileSyncUtils::getReadyFileSyncForKey($srcSyncKey, true, false);
		if(!$fileSync)
		{
			throw new KalturaAPIException(KalturaErrors::FILE_DOESNT_EXIST);
		}
		
		// even if it null
		$entry->setConversionQuality($conversionProfileId);
		$entry->save();
		
		if($dynamicConversionAttributes)
		{
			$flavors = assetParamsPeer::retrieveByProfile($conversionProfileId);
			if(!count($flavors))
				throw new KalturaAPIException(KalturaErrors::FLAVOR_PARAMS_NOT_FOUND);
		
			$srcFlavorParamsId = null;
			$flavorParams = $entry->getDynamicFlavorAttributes();
			foreach($flavors as $flavor)
			{
				if($flavor->hasTag(flavorParams::TAG_SOURCE))
					$srcFlavorParamsId = $flavor->getId();
					
				$flavorParams[$flavor->getId()] = $flavor;
			}
			
			$dynamicAttributes = array();
			foreach($dynamicConversionAttributes as $dynamicConversionAttribute)
			{
				if(is_null($dynamicConversionAttribute->flavorParamsId))
					$dynamicConversionAttribute->flavorParamsId = $srcFlavorParamsId;
					
				if(is_null($dynamicConversionAttribute->flavorParamsId))
					continue;
					
				$dynamicAttributes[$dynamicConversionAttribute->flavorParamsId][trim($dynamicConversionAttribute->name)] = trim($dynamicConversionAttribute->value);
			}
			
			if(count($dynamicAttributes))
			{
				$entry->setDynamicFlavorAttributes($dynamicAttributes);
				$entry->save();
			}
		}
		
		$job = kJobsManager::addConvertProfileJob(null, $entry, $srcFlavorAsset->getId(), $fileSync);
		if(!$job)
			return null;
			
		return $job->getId();
	}
	
	protected function addEntryFromFlavorAsset(KalturaBaseEntry $newEntry, entry $srcEntry, flavorAsset $srcFlavorAsset)
	{
	  	$newEntry->type = $srcEntry->getType();
	  		
		if ($newEntry->name === null)
			$newEntry->name = $srcEntry->getName();
			
		if ($newEntry->description === null)
			$newEntry->description = $srcEntry->getDescription();
		
		if ($newEntry->creditUrl === null)
			$newEntry->creditUrl = $srcEntry->getSourceLink();
			
	   	if ($newEntry->creditUserName === null)
	   		$newEntry->creditUserName = $srcEntry->getCredit();
	   		
	 	if ($newEntry->tags === null)
	  		$newEntry->tags = $srcEntry->getTags();
	   		
		$newEntry->sourceType = KalturaSourceType::SEARCH_PROVIDER;
	 	$newEntry->searchProviderType = KalturaSearchProviderType::KALTURA;
	 	
		$dbEntry = $this->prepareEntryForInsert($newEntry);
	  	$dbEntry->setSourceId( $srcEntry->getId() );
	  	
		$flavorAsset = kFlowHelper::createOriginalFlavorAsset($this->getPartnerId(), $dbEntry->getId());
		if(!$flavorAsset)
		{
			KalturaLog::err("Flavor asset not created for entry [" . $dbEntry->getId() . "]");
			
			if($dbEntry->getStatus() == entryStatus::NO_CONTENT)
			{
				$dbEntry->setStatus(entryStatus::ERROR_CONVERTING);
				$dbEntry->save();
			}
			
			throw new KalturaAPIException(KalturaErrors::ORIGINAL_FLAVOR_ASSET_NOT_CREATED);
		}
				
		$srcSyncKey = $srcFlavorAsset->getSyncKey(flavorAsset::FILE_SYNC_FLAVOR_ASSET_SUB_TYPE_ASSET);
		$newSyncKey = $flavorAsset->getSyncKey(flavorAsset::FILE_SYNC_FLAVOR_ASSET_SUB_TYPE_ASSET);
		kFileSyncUtils::createSyncFileLinkForKey($newSyncKey, $srcSyncKey);

		kEventsManager::raiseEvent(new kObjectAddedEvent($flavorAsset));
				
		myNotificationMgr::createNotification( kNotificationJobData::NOTIFICATION_TYPE_ENTRY_ADD, $dbEntry);

		$newEntry->fromObject($dbEntry, $this->getResponseProfile());
		return $newEntry;
	}
	
	protected function getEntry($entryId, $version = -1, $entryType = null)
	{
		$dbEntry = entryPeer::retrieveByPK($entryId);

		if (!$dbEntry || ($entryType !== null && $dbEntry->getType() != $entryType))
			throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $entryId);

		if ($version !== -1)
			$dbEntry->setDesiredVersion($version);

		$ks = $this->getKs();
		$isAdmin = false;
		if($ks)
			$isAdmin = $ks->isAdmin();
		
		$entry = KalturaEntryFactory::getInstanceByType($dbEntry->getType(), $isAdmin);
		
		$entry->fromObject($dbEntry, $this->getResponseProfile());

		return $entry;
	}

	protected function getRemotePaths($entryId, $entryType = null)
	{
		$dbEntry = entryPeer::retrieveByPK($entryId);

		if (!$dbEntry || ($entryType !== null && $dbEntry->getType() != $entryType))
			throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $entryId);

		if ($dbEntry->getStatus() != entryStatus::READY)
			throw new KalturaAPIException(KalturaErrors::ENTRY_NOT_READY, $entryId);
		
		$fileSyncs = kFileSyncUtils::getReadyRemoteFileSyncsForAsset($entryId, $dbEntry, FileSyncObjectType::ENTRY, kEntryFileSyncSubType::DATA);

		$listResponse = new KalturaRemotePathListResponse();
		$listResponse->objects = KalturaRemotePathArray::fromDbArray($fileSyncs, $this->getResponseProfile());
		$listResponse->totalCount = count($listResponse->objects);
		return $listResponse;
	}
	
	protected function listEntriesByFilter(KalturaBaseEntryFilter $filter = null, KalturaFilterPager $pager = null)
	{
		myDbHelper::$use_alternative_con = myDbHelper::DB_HELPER_CONN_PROPEL3;

		$disableWidgetSessionFilters = false;
		if ($filter &&
			($filter->idEqual != null ||
			$filter->idIn != null ||
			$filter->referenceIdEqual != null ||
			$filter->redirectFromEntryId != null ||
			$filter->referenceIdIn != null || 
			$filter->parentEntryIdEqual != null))
			$disableWidgetSessionFilters = true;
			
		if (!$pager)
			$pager = new KalturaFilterPager();
		
		$c = $filter->prepareEntriesCriteriaFilter($pager);
		
		if ($disableWidgetSessionFilters)
		{
			if (kEntitlementUtils::getEntitlementEnforcement() && !kCurrentContext::$is_admin_session && entryPeer::getUserContentOnly())
				entryPeer::setFilterResults(true);

			KalturaCriterion::disableTag(KalturaCriterion::TAG_WIDGET_SESSION);
		}
		$list = entryPeer::doSelect($c);
		entryPeer::fetchPlaysViewsData($list);
		$totalCount = $c->getRecordsCount();
		
		if ($disableWidgetSessionFilters)
			KalturaCriterion::restoreTag(KalturaCriterion::TAG_WIDGET_SESSION);

		return array($list, $totalCount);		
	}
	
	protected function countEntriesByFilter(KalturaBaseEntryFilter $filter = null)
	{
		myDbHelper::$use_alternative_con = myDbHelper::DB_HELPER_CONN_PROPEL3;

		if(!$filter)
			$filter = new KalturaBaseEntryFilter();
			
		$c = $filter->prepareEntriesCriteriaFilter();
		$c->applyFilters();
		$totalCount = $c->getRecordsCount();
		
		return $totalCount;
	}
	
	/*
	 	The following table shows the behavior of the checkAndSetValidUser functions:
	 	
	 	 otheruser - any user that is not the user specified in the ks
	  
	 	Input	 	 											Result	 
		Action			API entry user		DB entry user		Admin KS			User KS
		----------------------------------------------------------------------------------------
		entry.add		null / ksuser		N/A					ksuser				ksuser
 						otheruser			N/A					otheruser			exception
		entry.update	null / ksuser		ksuser				stays ksuser		stays ksuser
 						otheruser			ksuser				otheruser			exception
 						ksuser				otheruser			ksuser				exception
 						null / otheruser	otheruser			stays otheruser		if has edit privilege on entry => stays otheruser (checked by checkIfUserAllowedToUpdateEntry), 
 																					otherwise exception
	 */
	
   	/**
   	 * Sets the valid user for the entry 
   	 * Throws an error if the session user is trying to add entry to another user and not using an admin session 
   	 *
   	 * @param KalturaBaseEntry $entry
   	 * @param entry $dbEntry
   	 */
	protected function checkAndSetValidUserInsert(KalturaBaseEntry $entry, entry $dbEntry)
	{	
		// for new entry, puser ID is null - set it from service scope
		if ($entry->userId === null)
		{
			KalturaLog::debug("Set creator id [" . $this->getKuser()->getId() . "] line [" . __LINE__ . "]");
			$dbEntry->setCreatorKuserId($this->getKuser()->getId());
			$dbEntry->setCreatorPuserId($this->getKuser()->getPuserId());
			
			$dbEntry->setPuserId($this->getKuser()->getPuserId());
			$dbEntry->setKuserId($this->getKuser()->getId());
			return;
		}
		
		if ((!$this->getKs() || !$this->getKs()->isAdmin()))
		{
			// non admin cannot specify a different user on the entry other than himself
			$ksPuser = $this->getKuser()->getPuserId();
			if (strtolower($entry->userId) != strtolower($ksPuser))
			{
				throw new KalturaAPIException(KalturaErrors::INVALID_KS, "", ks::INVALID_TYPE, ks::getErrorStr(ks::INVALID_TYPE));
			}
		}


		// need to create kuser if this is an admin creating the entry on a different user
		$kuser = kuserPeer::createKuserForPartner($this->getPartnerId(), trim($entry->userId));
		$creatorId = null;
		if (is_null($entry->creatorId))
		{
			if (!is_null($entry->userId))
			{
				$creatorId = trim($entry->userId);
			}
		}
		else
		{
			$creatorId = trim($entry->creatorId);
		}
		$creator = kuserPeer::createKuserForPartner($this->getPartnerId(), $creatorId);

		KalturaLog::debug("Set kuser id [" . $kuser->getId() . "] line [" . __LINE__ . "]");
		$dbEntry->setKuserId($kuser->getId());
		$dbEntry->setCreatorKuserId($creator->getId());
		$dbEntry->setCreatorPuserId($creator->getPuserId());
	}
	
   	/**
   	 * Sets the valid user for the entry 
   	 * Throws an error if the session user is trying to update entry to another user and not using an admin session 
   	 *
   	 * @param KalturaBaseEntry $entry
   	 * @param entry $dbEntry
   	 */
	protected function checkAndSetValidUserUpdate(KalturaBaseEntry $entry, entry $dbEntry)
	{
		KalturaLog::debug("DB puser id [" . $dbEntry->getPuserId() . "] kuser id [" . $dbEntry->getKuserId() . "]");

		// user id not being changed
		if ($entry->userId === null)
		{
			KalturaLog::log("entry->userId is null, not changing user");
			return;
		}

		$ks = $this->getKs();
		if (!$ks ||(!$this->getKs()->isAdmin() && !$ks->verifyPrivileges(ks::PRIVILEGE_EDIT_USER, $entry->userId)))
		{
			$entryPuserId = $dbEntry->getPuserId();
			
			// non admin cannot change the owner of an existing entry
			if (strtolower($entry->userId) != strtolower($entryPuserId))
			{
				KalturaLog::debug('API entry userId ['.$entry->userId.'], DB entry userId ['.$entryPuserId.'] - change required but KS is not admin');
				throw new KalturaAPIException(KalturaErrors::INVALID_KS, "", ks::INVALID_TYPE, ks::getErrorStr(ks::INVALID_TYPE));
			}
		}
		
		// need to create kuser if this is an admin changing the owner of the entry to a different user
		$kuser = kuserPeer::createKuserForPartner($dbEntry->getPartnerId(), $entry->userId); 

		KalturaLog::debug("Set kuser id [" . $kuser->getId() . "] line [" . __LINE__ . "]");
		$dbEntry->setKuserId($kuser->getId());
	}
	
   	/**
   	 * Throws an error if the non-onwer session user is trying to update entitledPusersEdit or entitledPusersPublish 
   	 *
   	 * @param KalturaBaseEntry $entry
   	 * @param entry $dbEntry
   	 */
	protected function validateEntitledUsersUpdate(KalturaBaseEntry $entry, entry $dbEntry)
	{	
		if ((!$this->getKs() || !$this->getKs()->isAdmin()))
		{
			//non owner cannot change entitledUsersEdit and entitledUsersPublish
			if(!$dbEntry->isOwnerActionsAllowed($this->getKuser()->getId()))
			{
				if($entry->entitledUsersEdit !== null && strtolower($entry->entitledUsersEdit) != strtolower($dbEntry->getEntitledPusersEdit())){
					throw new KalturaAPIException(KalturaErrors::INVALID_KS, "", ks::INVALID_TYPE, ks::getErrorStr(ks::INVALID_TYPE));					
					
				}
				
				if($entry->entitledUsersPublish !== null && strtolower($entry->entitledUsersPublish) != strtolower($dbEntry->getEntitledPusersPublish())){
					throw new KalturaAPIException(KalturaErrors::INVALID_KS, "", ks::INVALID_TYPE, ks::getErrorStr(ks::INVALID_TYPE));					
					
				}
			}
		}
	}
	
	/**
	 * Throws an error if trying to update admin only properties with normal user session
	 *
	 * @param KalturaBaseEntry $entry
	 */
	protected function checkAdminOnlyUpdateProperties(KalturaBaseEntry $entry)
	{
		if ($entry->adminTags !== null)
		{
			$ks = $this->getKs();
			if (!$ks || !$ks->verifyPrivileges(ks::PRIVILEGE_EDIT_ADMIN_TAGS, ks::PRIVILEGE_WILDCARD ))
				$this->validateAdminSession("adminTags");
		}

		if ($entry->categories !== null)
		{
			$cats = explode(entry::ENTRY_CATEGORY_SEPARATOR, $entry->categories);
			foreach($cats as $cat)
			{
				if(!categoryPeer::getByFullNameExactMatch($cat))
					$this->validateAdminSession("categories");
			}
		}
			
		if ($entry->startDate !== null)
			$this->validateAdminSession("startDate");
			
		if  ($entry->endDate !== null)
			$this->validateAdminSession("endDate");
	}
	
	/**
	 * Throws an error if trying to update admin only properties with normal user session
	 *
	 * @param KalturaBaseEntry $entry
	 */
	protected function checkAdminOnlyInsertProperties(KalturaBaseEntry $entry)
	{
		if ($entry->adminTags !== null)
		{
			$ks = $this->getKs();
			if (!$ks || !$ks->verifyPrivileges(ks::PRIVILEGE_EDIT_ADMIN_TAGS, ks::PRIVILEGE_WILDCARD ))
				$this->validateAdminSession("adminTags");
		}

		if ($entry->categories !== null)
		{
			$cats = explode(entry::ENTRY_CATEGORY_SEPARATOR, $entry->categories);
			foreach($cats as $cat)
			{
				if(!categoryPeer::getByFullNameExactMatch($cat))
					$this->validateAdminSession("categories");
			}
		}
			
		if ($entry->startDate !== null)
			$this->validateAdminSession("startDate");
			
		if  ($entry->endDate !== null)
			$this->validateAdminSession("endDate");
	}
	
	/**
	 * Validates that current session is an admin session 
	 */
	protected function validateAdminSession($property)
	{
		if (!$this->getKs() || !$this->getKs()->isAdmin())
			throw new KalturaAPIException(KalturaErrors::PROPERTY_VALIDATION_ADMIN_PROPERTY, $property);	
	}
	
	/**
	 * Throws an error if trying to set invalid Access Control Profile
	 * 
	 * @param KalturaBaseEntry $entry
	 */
	protected function validateAccessControlId(KalturaBaseEntry $entry)
	{
		if ($entry->accessControlId !== null) // trying to update
		{
			$this->applyPartnerFilterForClass('accessControl'); 
			$accessControl = accessControlPeer::retrieveByPK($entry->accessControlId);
			if (!$accessControl)
				throw new KalturaAPIException(KalturaErrors::ACCESS_CONTROL_ID_NOT_FOUND, $entry->accessControlId);
		}
	}
	
	/**
	 * Throws an error if trying to set invalid entry schedule date
	 * 
	 * @param KalturaBaseEntry $entry
	 */
	protected function validateEntryScheduleDates(KalturaBaseEntry $entry, entry $dbEntry)
	{
		if(is_null($entry->startDate) && is_null($entry->endDate))
			return; // no update

		if($entry->startDate instanceof KalturaNullField)
			$entry->startDate = -1;
		if($entry->endDate instanceof KalturaNullField)
			$entry->endDate = -1;
			
		// if input is null and this is an update pick the current db value 
		$startDate = is_null($entry->startDate) ?  $dbEntry->getStartDate(null) : $entry->startDate;
		$endDate = is_null($entry->endDate) ?  $dbEntry->getEndDate(null) : $entry->endDate;
		
		// normalize values for valid comparison later 
		if ($startDate < 0)
			$startDate = null;
		
		if ($endDate < 0)
			$endDate = null;
		
		if ($startDate && $endDate && $startDate >= $endDate)
		{
			throw new KalturaAPIException(KalturaErrors::INVALID_ENTRY_SCHEDULE_DATES);
		}
	}
	
	protected function updateEntry($entryId, KalturaBaseEntry $entry, $entryType = null)
	{
		$entry->type = null; // because it was set in the constructor, but cannot be updated
		
		$dbEntry = entryPeer::retrieveByPK($entryId);

		if (!$dbEntry || ($entryType !== null && $dbEntry->getType() != $entryType))
			throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $entryId);
		
		
		$this->checkAndSetValidUserUpdate($entry, $dbEntry);
		$this->checkAdminOnlyUpdateProperties($entry);
		$this->validateEntitledUsersUpdate($entry, $dbEntry);
		$this->validateAccessControlId($entry);
		$this->validateEntryScheduleDates($entry, $dbEntry); 
		
		$dbEntry = $entry->toUpdatableObject($dbEntry);
		/* @var $dbEntry entry */
		
		$updatedOccurred = $dbEntry->save();
		$entry->fromObject($dbEntry, $this->getResponseProfile());
		
		try 
		{
			$wrapper = objectWrapperBase::getWrapperClass($dbEntry);
			$wrapper->removeFromCache("entry", $dbEntry->getId());
		}
		catch(Exception $e)
		{
			KalturaLog::err($e);
		}
		
		if ($updatedOccurred)
		{
			myNotificationMgr::createNotification(kNotificationJobData::NOTIFICATION_TYPE_ENTRY_UPDATE, $dbEntry);
		}
		
		return $entry;
	}
	
	protected function deleteEntry($entryId, $entryType = null)
	{
		$entryToDelete = entryPeer::retrieveByPK($entryId);

		if (!$entryToDelete || ($entryType !== null && $entryToDelete->getType() != $entryType))
			throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $entryId);
		
		myEntryUtils::deleteEntry($entryToDelete);
		
		try
		{
			$wrapper = objectWrapperBase::getWrapperClass($entryToDelete);
			$wrapper->removeFromCache("entry", $entryToDelete->getId());
		}
		catch(Exception $e)
		{
			KalturaLog::err($e);
		}
	}
	
	protected function updateThumbnailForEntryFromUrl($entryId, $url, $entryType = null, $fileSyncType = kEntryFileSyncSubType::THUMB)
	{
		$dbEntry = entryPeer::retrieveByPK($entryId);

		if (!$dbEntry || ($entryType !== null && $dbEntry->getType() != $entryType))
			throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $entryId);
			
		// if session is not admin, we should check that the user that is updating the thumbnail is the one created the entry
		// FIXME: Temporary disabled because update thumbnail feature (in app studio) is working with anonymous ks
		/*if (!$this->getKs()->isAdmin())
		{
			if ($dbEntry->getPuserId() !== $this->getKs()->user)
			{
				throw new KalturaAPIException(KalturaErrors::PERMISSION_DENIED_TO_UPDATE_ENTRY);
			}
		}*/

		$content = KCurlWrapper::getContent($url);
		if (!$content)
		{
			throw new KalturaAPIException(KalturaErrors::THUMB_ASSET_DOWNLOAD_FAILED, $url);
		}
		myEntryUtils::updateThumbnailFromContent($dbEntry, $content, $fileSyncType);
		
		$entry = KalturaEntryFactory::getInstanceByType($dbEntry->getType());
		$entry->fromObject($dbEntry, $this->getResponseProfile());
		
		return $entry;
	}
	
	protected function updateThumbnailJpegForEntry($entryId, $fileData, $entryType = null, $fileSyncType = kEntryFileSyncSubType::THUMB)
	{
		$dbEntry = entryPeer::retrieveByPK($entryId);

		if (!$dbEntry || ($entryType !== null && $dbEntry->getType() != $entryType))
			throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $entryId);
			
		// if session is not admin, we should check that the user that is updating the thumbnail is the one created the entry
		// FIXME: Temporary disabled because update thumbnail feature (in app studio) is working with anonymous ks
		/*if (!$this->getKs()->isAdmin())
		{
			if ($dbEntry->getPuserId() !== $this->getKs()->user)
			{
				throw new KalturaAPIException(KalturaErrors::PERMISSION_DENIED_TO_UPDATE_ENTRY);
			}
		}*/
		if (myUploadUtils::isFileTypeRestricted($fileData["tmp_name"], $fileData['name']))
		{
			throw new KalturaAPIException(KalturaErrors::FILE_CONTENT_NOT_SECURE);
		}
		myEntryUtils::updateThumbnailFromContent($dbEntry, file_get_contents($fileData["tmp_name"]), $fileSyncType);
		
		$entry = KalturaEntryFactory::getInstanceByType($dbEntry->getType());
		$entry->fromObject($dbEntry, $this->getResponseProfile());
		
		return $entry;
	}
	
	protected function updateThumbnailForEntryFromSourceEntry($entryId, $sourceEntryId, $timeOffset, $entryType = null, $flavorParamsId = null)
	{
		$dbEntry = entryPeer::retrieveByPK($entryId);

		if (!$dbEntry || ($entryType !== null && $dbEntry->getType() != $entryType))
			throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $entryId);
			
		$sourceDbEntry = entryPeer::retrieveByPK($sourceEntryId);
		if (!$sourceDbEntry || $sourceDbEntry->getType() != KalturaEntryType::MEDIA_CLIP)
			throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $sourceDbEntry);
			
		// if session is not admin, we should check that the user that is updating the thumbnail is the one created the entry
		if (!$this->getKs() || !$this->getKs()->isAdmin())
		{
			if (strtolower($dbEntry->getPuserId()) !== strtolower($this->getKs()->user))
			{
				throw new KalturaAPIException(KalturaErrors::PERMISSION_DENIED_TO_UPDATE_ENTRY);
			}
		}
		
		$updateThumbnailResult = myEntryUtils::createThumbnailFromEntry($dbEntry, $sourceDbEntry, $timeOffset, $flavorParamsId);
		
		if (!$updateThumbnailResult)
		{
			throw new KalturaAPIException(KalturaErrors::INTERNAL_SERVERL_ERROR);
		}
		
		try
		{
			$wrapper = objectWrapperBase::getWrapperClass($dbEntry);
			$wrapper->removeFromCache("entry", $dbEntry->getId());
		}
		catch(Exception $e)
		{
			KalturaLog::err($e);
		}
		
		myNotificationMgr::createNotification(kNotificationJobData::NOTIFICATION_TYPE_ENTRY_UPDATE_THUMBNAIL, $dbEntry, $dbEntry->getPartnerId(), $dbEntry->getPuserId(), null, null, $entryId);

		$ks = $this->getKs();
		$isAdmin = false;
		if($ks)
			$isAdmin = $ks->isAdmin();
			
		$mediaEntry = KalturaEntryFactory::getInstanceByType($dbEntry->getType(), $isAdmin);
		$mediaEntry->fromObject($dbEntry, $this->getResponseProfile());
		
		return $mediaEntry;
	}
	
	protected function flagEntry(KalturaModerationFlag $moderationFlag, $entryType = null)
	{
		$moderationFlag->validatePropertyNotNull("flaggedEntryId");

		$entryId = $moderationFlag->flaggedEntryId;
		$dbEntry = kCurrentContext::initPartnerByEntryId($entryId);

		// before returning any error, let's validate partner's access control
		if ($dbEntry)
			$this->validateApiAccessControl($dbEntry->getPartnerId());

		if (!$dbEntry || ($entryType !== null && $dbEntry->getType() != $entryType))
			throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $entryId);

		$validModerationStatuses = array(
			KalturaEntryModerationStatus::APPROVED,
			KalturaEntryModerationStatus::AUTO_APPROVED,
			KalturaEntryModerationStatus::FLAGGED_FOR_REVIEW,
		);
		if (!in_array($dbEntry->getModerationStatus(), $validModerationStatuses))
			throw new KalturaAPIException(KalturaErrors::ENTRY_CANNOT_BE_FLAGGED);
			
		$dbModerationFlag = new moderationFlag();
		$dbModerationFlag->setPartnerId($dbEntry->getPartnerId());
		$dbModerationFlag->setKuserId($this->getKuser()->getId());
		$dbModerationFlag->setFlaggedEntryId($dbEntry->getId());
		$dbModerationFlag->setObjectType(KalturaModerationObjectType::ENTRY);
		$dbModerationFlag->setStatus(KalturaModerationFlagStatus::PENDING);
		$dbModerationFlag->setFlagType($moderationFlag->flagType);
		$dbModerationFlag->setComments($moderationFlag->comments);
		$dbModerationFlag->save();
		
		$dbEntry->setModerationStatus(KalturaEntryModerationStatus::FLAGGED_FOR_REVIEW);
		$updateOccurred = $dbEntry->save();
		
		$moderationFlag = new KalturaModerationFlag();
		$moderationFlag->fromObject($dbModerationFlag, $this->getResponseProfile());
		
		// need to notify the partner that an entry was flagged - use the OLD moderation onject that is required for the 
		// NOTIFICATION_TYPE_ENTRY_REPORT notification
		// TODO - change to moderationFlag object to implement the interface for the notification:
		// it should have "objectId", "comments" , "reportCode" as getters
		$oldModerationObj = new moderation();
		$oldModerationObj->setPartnerId($dbEntry->getPartnerId());
		$oldModerationObj->setComments( $moderationFlag->comments);
		$oldModerationObj->setObjectId( $dbEntry->getId() );
		$oldModerationObj->setObjectType( moderation::MODERATION_OBJECT_TYPE_ENTRY );
		$oldModerationObj->setReportCode( "" );
		if ($updateOccurred)
			myNotificationMgr::createNotification( kNotificationJobData::NOTIFICATION_TYPE_ENTRY_REPORT, $oldModerationObj ,$dbEntry->getPartnerId());
				
		return $moderationFlag;
	}
	
	protected function rejectEntry($entryId, $entryType = null)
	{
		$dbEntry = entryPeer::retrieveByPK($entryId);
		if (!$dbEntry || ($entryType !== null && $dbEntry->getType() != $entryType))
			throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $entryId);
			
		$dbEntry->setModerationStatus(KalturaEntryModerationStatus::REJECTED);
		$dbEntry->setModerationCount(0);
		$updateOccurred = $dbEntry->save();
		
		if ($updateOccurred)
			myNotificationMgr::createNotification(kNotificationJobData::NOTIFICATION_TYPE_ENTRY_UPDATE , $dbEntry, null, null, null, null, $dbEntry->getId() );
//		myNotificationMgr::createNotification(kNotificationJobData::NOTIFICATION_TYPE_ENTRY_BLOCK , $dbEntry->getId());
		
		moderationFlagPeer::markAsModeratedByEntryId($this->getPartnerId(), $dbEntry->getId());
	}
	
	protected function approveEntry($entryId, $entryType = null)
	{
		$dbEntry = entryPeer::retrieveByPK($entryId);
		if (!$dbEntry || ($entryType !== null && $dbEntry->getType() != $entryType))
			throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $entryId);
			
		$dbEntry->setModerationStatus(KalturaEntryModerationStatus::APPROVED);
		$dbEntry->setModerationCount(0);
		$updateOccurred = $dbEntry->save();
		
		if ($updateOccurred)
			myNotificationMgr::createNotification(kNotificationJobData::NOTIFICATION_TYPE_ENTRY_UPDATE , $dbEntry, null, null, null, null, $dbEntry->getId() );
//		myNotificationMgr::createNotification(kNotificationJobData::NOTIFICATION_TYPE_ENTRY_BLOCK , $dbEntry->getId());
		
		moderationFlagPeer::markAsModeratedByEntryId($this->getPartnerId(), $dbEntry->getId());
	}
	
	protected function listFlagsForEntry($entryId, KalturaFilterPager $pager = null)
	{
		if (!$pager)
			$pager = new KalturaFilterPager();
			
		$c = new Criteria();
		$c->addAnd(moderationFlagPeer::PARTNER_ID, $this->getPartnerId());
		$c->addAnd(moderationFlagPeer::FLAGGED_ENTRY_ID, $entryId);
		$c->addAnd(moderationFlagPeer::OBJECT_TYPE, KalturaModerationObjectType::ENTRY);
		$c->addAnd(moderationFlagPeer::STATUS, KalturaModerationFlagStatus::PENDING);
		
		$totalCount = moderationFlagPeer::doCount($c);
		$pager->attachToCriteria($c);
		$list = moderationFlagPeer::doSelect($c);
		
		$newList = KalturaModerationFlagArray::fromDbArray($list, $this->getResponseProfile());
		$response = new KalturaModerationFlagListResponse();
		$response->objects = $newList;
		$response->totalCount = $totalCount;
		return $response;
	}
	
	protected function anonymousRankEntry($entryId, $entryType = null, $rank)
	{
		$dbEntry = entryPeer::retrieveByPK($entryId);
		if (!$dbEntry || ($entryType !== null && $dbEntry->getType() != $entryType))
			throw new KalturaAPIException(KalturaErrors::ENTRY_ID_NOT_FOUND, $entryId);
			
		if ($rank <= 0 || $rank > 5)
		{
			throw new KalturaAPIException(KalturaErrors::INVALID_RANK_VALUE);
		}

		$kvote = new kvote();
		$kvote->setEntryId($entryId);
		$kvote->setKuserId($this->getKuser()->getId());
		$kvote->setRank($rank);
		$kvote->save();
	}

	/**
	 * @param kOperationResource $resource
	 * @param entry $destEntry
	 * @param kClipManager $clipManager
	 * @param entry $clipEntry
	 * @param int $rootJobId
	 * @param int $order
	 * @param string $conversionParams
	 * @throws KalturaAPIException
	 */
	protected function handleMultiClipRequest($resource, $destEntry, $clipManager, $clipEntry = null, $rootJobId = null, $order = null, $conversionParams = null)
	{
		KalturaLog::info("Clipping action detected start to create sub flavors;");
		if(!$clipEntry)
		{
			$clipEntry = $clipManager->createTempEntryForClip($this->getPartnerId());
		}
		$url = null;
		if ($resource->getResource() instanceof kFileSyncResource && $resource->getResource()->getOriginEntryId())
		{
			$url = $this->getImportUrl($resource->getResource()->getOriginEntryId());
		}
		if (!$url)
		{
			$clipDummySourceAsset = kFlowHelper::createOriginalFlavorAsset($this->getPartnerId(), $clipEntry->getId());
			$this->attachResource($resource->getResource(), $clipEntry, $clipDummySourceAsset);
		}
		$clipManager->startClipConcatBatchJob($resource, $destEntry, $clipEntry, $url, $rootJobId, $order, $conversionParams);
	}


	/**
	 * @param $resources
	 * @param entry $destEntry
	 * @param kClipManager $clipManager
	 * @throws KalturaAPIException
	 * @throws Exception
	 */
	protected function handleMultiResourceMultiClipRequest($resources, entry $destEntry, $clipManager)
	{
		KalturaLog::info("Multi resource clipping action detected, start to create multi template entry and sub template entries");

		$sourceEntryIds = array();
		$tempEntryIds = array();
		$resourcesData = array();

		// for each resource: 1.set sourceEntryId on resource 2.create temp entry for clip 3. retrieve media info object
		foreach ($resources->getResources() as $ind => $resource)
		{
			/** @var kOperationResource $resource **/
			$resourceObj = $resource->getResource();
			$sourceEntry = $this->getValidatedEntryForResource($resourceObj);
			$sourceEntryId = $sourceEntry->getId();
			$sourceEntryIds[] = $sourceEntryId;

			$tempEntry = $clipManager->createTempEntryForClip($this->getPartnerId(), "TEMP_$sourceEntryId" . "_");
			$tempEntryIds[] = $tempEntry->getId();

			$duration = 0;
			foreach ($resource->getOperationAttributes() as $operationAttribute)
			{
				/* @var $operationAttribute kClipAttributes **/
				$duration += $operationAttribute->getDuration();
			}

			if($sourceEntry->getMediaType() == KalturaMediaType::IMAGE)
			{
				foreach ($resource->getOperationAttributes() as $operationAttribute)
				{
					/* @var $operationAttribute kClipAttributes **/
					$operationAttribute->setOffset(0);
				}
				$syncKey = $sourceEntry->getSyncKey(kEntryFileSyncSubType::DATA);
				$sourceFilePath = kFileSyncUtils::getLocalFilePathForKey($syncKey);
				$mediaInfoParser = new KMediaInfoMediaParser($sourceFilePath, 'mediainfo');
				$mediaInfo = $mediaInfoParser->getMediaInfo();
				if(!$mediaInfo)
				{
					throw new KalturaAPIException(KalturaErrors::INVALID_MEDIA_INFO, $sourceEntryId);
				}
				$mediaInfoObj = $mediaInfo->toInsertableObject();
				$imageToVideo = 1;
			}
			else
			{
				$mediaInfoObj = mediaInfoPeer::retrieveByFlavorAssetId($resourceObj->getObjectId());
				$imageToVideo = 0;
			}
			if(!$mediaInfoObj)
			{
				$objectId = $resourceObj->getObjectId();
				KalturaLog::err("Could not retrieve media info object for object Id [$objectId] for source entry Id [$sourceEntryId]");
				throw new APIException(KalturaErrors::MEDIA_INFO_NOT_FOUND, $objectId);
			}

			$resourcesData[] = array(
				kClipManager::SOURCE_ENTRY => $sourceEntry,
				kClipManager::TEMP_ENTRY => $tempEntry,
				kClipManager::MEDIA_INFO_OBJECT => $mediaInfoObj,
				kClipManager::VIDEO_DURATION => $duration,
				kClipManager::IMAGE_TO_VIDEO => $imageToVideo
			);

		}

		$clipManager->calculateAndEditConversionParams($resourcesData, $destEntry->getConversionProfileId());
		$multiTempEntry = $clipManager->createTempEntryForClip($this->getPartnerId(), 'MULTI_TEMP_');
		$clipManager->addMultiClipTrackEntries($sourceEntryIds, $tempEntryIds, $multiTempEntry->getId(), $destEntry->getId());
		$rootJob = $clipManager->startMultiClipConcatBatchJob($resources, $destEntry, $multiTempEntry);
		foreach ($resources->getResources() as $key => $resource)
		{
			$tempEntry = $resourcesData[$key][kClipManager::TEMP_ENTRY];
			$conversionParams = $resourcesData[$key][kClipManager::CONVERSION_PARAMS];
			$this->handleMultiClipRequest($resource, $destEntry, $clipManager, $tempEntry, $rootJob->getId(), $key, $conversionParams);
		}
		$destEntry->setStatus(entryStatus::PENDING);
		$destEntry->save();
		kJobsManager::updateBatchJob($rootJob, BatchJob::BATCHJOB_STATUS_ALMOST_DONE);
	}

	/***
	 * @param kContentResource $resourceObj
	 * @throws APIException
	 */
	protected function getValidatedEntryForResource($resourceObj)
	{
		$sourceEntry = $this->getEntryFromContentResource($resourceObj);
		if(!$sourceEntry)
		{
			if($resourceObj instanceof kLiveEntryResource)
			{
				$sourceEntryId = $resourceObj->getEntry();
				throw new APIException(KalturaErrors::ENTRY_ID_TYPE_NOT_SUPPORTED, $sourceEntryId->getId(), $sourceEntryId->getType());
			}
			elseif($resourceObj instanceof kFileSyncResource)
			{
				throw new APIException(APIErrors::ENTRY_ID_NOT_FOUND, $resourceObj->getOriginEntryId());
			}
		}
		$sourceEntryId = $sourceEntry->getId();
		if(!in_array($sourceEntry->getType(), array(KalturaEntryType::MEDIA_CLIP, KalturaEntryType::DATA)))
		{
			throw new APIException(KalturaErrors::ENTRY_ID_TYPE_NOT_SUPPORTED, $sourceEntryId, $sourceEntry->getType());
		}
		if(!in_array($sourceEntry->getMediaType(), array(KalturaMediaType::VIDEO, KalturaMediaType::IMAGE)))
		{
			throw new APIException(KalturaErrors::ENTRY_ID_MEDIA_TYPE_NOT_SUPPORTED, $sourceEntryId, $sourceEntry->getMediaType());
		}
		return $sourceEntry;
	}

	/***
	 * @param null $entryId
	 * @return string $url
	 * @throws Exception
	 */
	protected function getImportUrl($entryId = null)
	{
		if ($entryId)
		{
			$originalFlavorAsset = assetPeer::retrieveOriginalReadyByEntryId($entryId);
			if ($originalFlavorAsset)
			{
				$srcSyncKey = $originalFlavorAsset->getSyncKey(flavorAsset::FILE_SYNC_FLAVOR_ASSET_SUB_TYPE_ASSET);
				list($fileSync, $local) = kFileSyncUtils::getReadyFileSyncForKey($srcSyncKey, true, false);
				//To-Do Change the import flow for periodic storage file syncs to work natively with the shared storage flow
				/* @var $fileSync FileSync */
				if ( $fileSync && (!$local ||
						($fileSync->getFileType() == FileSync::FILE_SYNC_FILE_TYPE_URL &&
							in_array($fileSync->getDc(), kDataCenterMgr::getSharedStorageProfileIds($fileSync->getPartnerId())) )
					))
				{
					$remoteDc = 1 - kDataCenterMgr::getCurrentDcId();
					if(myEntryUtils::shouldValidateLocal() && $fileSync->getDc() == $remoteDc)
					{
						KalturaLog::info("Source was not found locally, but was found in the remote dc [$remoteDc]");
						throw new KalturaAPIException(KalturaErrors::ENTRY_SOURCE_FILE_NOT_FOUND, $entryId);
					}

					return $fileSync->getExternalUrl($entryId, null, true);
				}
			}
		}
		return null;
	}

	/**
	 * Set the default status to ready if other status filters are not specified
	 * 
	 * @param KalturaBaseEntryFilter $filter
	 */
	private function setDefaultStatus(KalturaBaseEntryFilter $filter)
	{
		if ($filter->statusEqual === null && 
			$filter->statusIn === null &&
			$filter->statusNotEqual === null &&
			$filter->statusNotIn === null)
		{
			$filter->statusEqual = KalturaEntryStatus::READY;
		}
	}
	
	/**
	 * Set the default moderation status to ready if other moderation status filters are not specified
	 * 
	 * @param KalturaBaseEntryFilter $filter
	 */
	private function setDefaultModerationStatus(KalturaBaseEntryFilter $filter)
	{
		if ($filter->moderationStatusEqual === null && 
			$filter->moderationStatusIn === null && 
			$filter->moderationStatusNotEqual === null && 
			$filter->moderationStatusNotIn === null)
		{
			$moderationStatusesNotIn = array(
				KalturaEntryModerationStatus::PENDING_MODERATION, 
				KalturaEntryModerationStatus::REJECTED);
			$filter->moderationStatusNotIn = implode(",", $moderationStatusesNotIn); 
		}
	}
	
	/**
	 * Convert duration in seconds to msecs (because the duration field is mapped to length_in_msec)
	 * 
	 * @param KalturaBaseEntryFilter $filter
	 */
	private function fixFilterDuration(KalturaBaseEntryFilter $filter)
	{
		if ($filter instanceof KalturaPlayableEntryFilter) // because duration filter should be supported in baseEntryService
		{
			if ($filter->durationGreaterThan !== null)
				$filter->durationGreaterThan = $filter->durationGreaterThan * 1000;

			//When translating from seconds to msec need to subtract 500 msec since entries greater than 5500 msec are considered as entries with 6 sec
			if ($filter->durationGreaterThanOrEqual !== null)
				$filter->durationGreaterThanOrEqual = $filter->durationGreaterThanOrEqual * 1000 - 500;
				
			if ($filter->durationLessThan !== null)
				$filter->durationLessThan = $filter->durationLessThan * 1000;
				
			//When translating from seconds to msec need to add 499 msec since entries less than 5499 msec are considered as entries with 5 sec
			if ($filter->durationLessThanOrEqual !== null)
				$filter->durationLessThanOrEqual = $filter->durationLessThanOrEqual * 1000 + 499;
		}
	}
	
	// hack due to KCW of version  from KMC
	protected function getConversionQualityFromRequest () 
	{
		if(isset($_REQUEST["conversionquality"]))
			return $_REQUEST["conversionquality"];
		return null;
	}

	protected function validateContent($dbEntry)
	{
		try
		{
			myEntryUtils::validateObjectContent($dbEntry);
		}
		catch (Exception $e)
		{
			$dbEntry->setStatus(entryStatus::ERROR_IMPORTING);
			$dbEntry->save();
			throw new KalturaAPIException(KalturaErrors::FILE_CONTENT_NOT_SECURE);
		}
	}
	
	protected function handleErrorDuringSetResource($entryId, Exception $e)
	{
		if ($e->getCode() == APIErrors::getCode(APIErrors::ENTRY_ID_NOT_FOUND))
		{
			throw $e; //if no entry found then no need to do anything
		}
		KalturaLog::info("Exception was thrown during setContent on entry [$entryId] with error: " . $e->getMessage());
		$this->cancelReplaceAction($entryId);
		
		$errorCodeArr = array(kCoreException::SOURCE_FILE_NOT_FOUND, APIErrors::getCode(APIErrors::SOURCE_FILE_NOT_FOUND));
		if ((in_array($e->getCode(), $errorCodeArr)) && (kDataCenterMgr::dcExists(1 - kDataCenterMgr::getCurrentDcId())))
		{
			$remoteDc = 1 - kDataCenterMgr::getCurrentDcId();
			KalturaLog::info("Source file wasn't found on current DC. Dumping the request to DC ID [$remoteDc]");
			kFileUtils::dumpApiRequest(kDataCenterMgr::getRemoteDcExternalUrlByDcId($remoteDc), true);
		}
		throw $e;
	}

}
