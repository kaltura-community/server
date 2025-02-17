<?php
/**
 * Subclass for representing a row from the 'entry' table.
 *
 *
 *
 * @package Core
 * @subpackage model
 */
class entry extends Baseentry implements ISyncableFile, IIndexable, IOwnable, IRelatedObject, IElasticIndexable, IMultiLingual
{
	protected $new_categories = '';
	protected $new_categories_ids = '';
	protected $old_categories;
	protected $is_categories_modified = false;
	protected $is_categories_names_modified = false;
	protected $creator_kuser_id = null;
	protected $playsViewsData = null;
	protected $playsViewsDataInitialized = false;
	
	const MINIMUM_ID_TO_DISPLAY = 8999;
	
	const ROOTS_FIELD_PREFIX = 'K_Pref';
	const ROOTS_FIELD_ENTRY_PREFIX = 'KP_Entry';
	const ROOTS_FIELD_PARENT_ENTRY_PREFIX = 'KP_Parent';
	const ROOTS_FIELD_BULK_UPLOAD_PREFIX = 'KP_Bulk';

	// NOTE - CHANGES MUST BE MADE TO LAYOUT.PHP JS PART AS WELL
	// different sort orders for browsing entries
	const ENTRY_SORT_MOST_VIEWED = 0;
	const ENTRY_SORT_MOST_RECENT = 1;
	const ENTRY_SORT_MOST_COMMENTS = 2;
	const ENTRY_SORT_MOST_FAVORITES = 3;
	const ENTRY_SORT_RANK = 4;
	const ENTRY_SORT_MEDIA_TYPE = 5;
	const ENTRY_SORT_NAME = 6;
	const ENTRY_SORT_KUSER_SCREEN_NAME = 7;

	// NOTE - CHANGES MUST BE MADE TO LAYOUT.PHP JS PART AS WELL
	const ENTRY_MEDIA_TYPE_AUTOMATIC = -1;
	const ENTRY_MEDIA_TYPE_ANY = 0;
	const ENTRY_MEDIA_TYPE_VIDEO = 1;
	const ENTRY_MEDIA_TYPE_IMAGE = 2;
	const ENTRY_MEDIA_TYPE_TEXT = 3;
	const ENTRY_MEDIA_TYPE_HTML = 4;
	const ENTRY_MEDIA_TYPE_AUDIO = 5;
	const ENTRY_MEDIA_TYPE_SHOW = 6;
	const ENTRY_MEDIA_TYPE_SHOW_XML = 7; // for the kplayer: the data contains the xml itself and not a url
	const ENTRY_MEDIA_TYPE_BUBBLES = 9;
	const ENTRY_MEDIA_TYPE_XML = 10;
	const ENTRY_MEDIA_TYPE_DOCUMENT = 11;
	const ENTRY_MEDIA_TYPE_SWF = 12;
	const ENTRY_MEDIA_TYPE_PDF = 13;
	
	const ENTRY_MEDIA_TYPE_GENERIC_1= 101;	// these types can be used for derived classes - assume this is some kind of TXT file
	const ENTRY_MEDIA_TYPE_GENERIC_2= 102;	// these types can be used for derived classes
	const ENTRY_MEDIA_TYPE_GENERIC_3= 103;	// these types can be used for derived classes
	const ENTRY_MEDIA_TYPE_GENERIC_4= 104;	// these types can be used for derived classes
	
	const ENTRY_MEDIA_TYPE_LIVE_STREAM_FLASH = 201;
	const ENTRY_MEDIA_TYPE_LIVE_STREAM_WINDOWS_MEDIA = 202;
	const ENTRY_MEDIA_TYPE_LIVE_STREAM_REAL_MEDIA = 203;
	const ENTRY_MEDIA_TYPE_LIVE_STREAM_QUICKTIME = 204;
	
	// NOTE - CHANGES MUST BE MADE TO LAYOUT.PHP JS PART AS WELL
	const ENTRY_MEDIA_SOURCE_FILE = 1;
	const ENTRY_MEDIA_SOURCE_WEBCAM = 2;
	const ENTRY_MEDIA_SOURCE_FLICKR = 3;
	const ENTRY_MEDIA_SOURCE_YOUTUBE = 4;
	const ENTRY_MEDIA_SOURCE_URL = 5;
	const ENTRY_MEDIA_SOURCE_TEXT = 6;
	const ENTRY_MEDIA_SOURCE_MYSPACE = 7;
	const ENTRY_MEDIA_SOURCE_PHOTOBUCKET = 8;
	const ENTRY_MEDIA_SOURCE_JAMENDO = 9;
	const ENTRY_MEDIA_SOURCE_CCMIXTER = 10;
	const ENTRY_MEDIA_SOURCE_NYPL = 11;
	const ENTRY_MEDIA_SOURCE_CURRENT = 12;
	const ENTRY_MEDIA_SOURCE_MEDIA_COMMONS = 13;
	const ENTRY_MEDIA_SOURCE_KALTURA = 20;
	const ENTRY_MEDIA_SOURCE_KALTURA_USER_CLIPS = 21;
	const ENTRY_MEDIA_SOURCE_ARCHIVE_ORG = 22;
	const ENTRY_MEDIA_SOURCE_KALTURA_PARTNER = 23;
	const ENTRY_MEDIA_SOURCE_METACAFE = 24;
	const ENTRY_MEDIA_SOURCE_KALTURA_QA = 25;
	const ENTRY_MEDIA_SOURCE_KALTURA_KSHOW = 26;
	const ENTRY_MEDIA_SOURCE_KALTURA_PARTNER_KSHOW = 27;
	const ENTRY_MEDIA_SOURCE_SEARCH_PROXY = 28;
	const ENTRY_MEDIA_SOURCE_AKAMAI_LIVE = 29;
	const ENTRY_MEDIA_SOURCE_MANUAL_LIVE_STREAM = 30;
	const ENTRY_MEDIA_SOURCE_AKAMAI_UNIVERSAL_LIVE = 31; // Deprecate
	const ENTRY_MEDIA_SOURCE_LIVE_STREAM = 32;
	const ENTRY_MEDIA_SOURCE_LIVE_CHANNEL = 33;
	const ENTRY_MEDIA_SOURCE_RECORDED_LIVE = 34;
	const ENTRY_MEDIA_SOURCE_CLIP = 35;
	const ENTRY_MEDIA_SOURCE_PARTNER_SPECIFIC = 100;
		
	const ENTRY_MODERATION_STATUS_PENDING_MODERATION = 1;
	const ENTRY_MODERATION_STATUS_APPROVED = 2;
	const ENTRY_MODERATION_STATUS_REJECTED = 3;
	const ENTRY_MODERATION_STATUS_FLAGGED_FOR_REVIEW = 5;
	const ENTRY_MODERATION_STATUS_AUTO_APPROVED = 6;
	
	const MAX_NORMALIZED_RANK = 5;

	const MAX_CATEGORIES_PER_ENTRY = 32;
	const MAX_CATEGORIES_PER_ENTRY_DISABLE_LIMIT_FEATURE = 1000;
	
	const MIX_EDITOR_TYPE_SIMPLE = 1;
	const MIX_EDITOR_TYPE_ADVANCED = 2;

	const ENTRY_CATEGORY_ESCAPE = "_";
	const ENTRY_CATEGORY_SEPARATOR = ",";
	
	const ENTRY_ID_THAT_DOES_NOT_EXIST = 'nonExistingId';
	
	const CATEGORY_SEARCH_PERFIX = 'c';
	const CATEGORY_PARENT_SEARCH_PERFIX = 'p';
	const CATEGORY_OR_PARENT_SEARCH_PERFIX = 'pc';
	const CATEGORY_SEARCH_STATUS = 's';
	const PARTNER_STATUS_FORMAT = 'P%sST%s';
	const CATEGORIES_INDEXED_FIELD_PREFIX = 'pid';
	const PLAYSVIEWS_CACHE_KEY_PREFIX = 'plays_views_';
	const PLAYS_CACHE_KEY = 'plays';
	const VIEWS_CACHE_KEY = 'views';
	const LAST_PLAYED_AT_CACHE_KEY = 'last_played_at';
	const PLAYS_30_DAYS_CACHE_KEY = 'plays_30days';
	const VIEWS_30_DAYS_CACHE_KEY = 'views_30days';
	const PLAYS_7_DAYS_CACHE_KEY = 'plays_7days';
	const VIEWS_7_DAYS_CACHE_KEY = 'views_7days';
	const PLAYS_1_DAY_CACHE_KEY = 'plays_1day';
	const VIEWS_1_DAY_CACHE_KEY = 'views_1day';

	const DEFAULT_IMAGE_HEIGHT = 480;
	const DEFAULT_IMAGE_WIDTH = 640;

	const CAPABILITIES = "capabilities";
	const TEMPLATE_ENTRY_ID = "templateEntryId";

	const LIVE_THUMB_PATH = "content/templates/entry/thumbnail/live_thumb.jpg";
	const CUSTOM_DATA_INTERACTIVITY_VERSION = 'interactivity_version';
	const CUSTOM_DATA_VOLATILE_INTERACTIVITY_VERSION = 'volatile_interactivity_version';
	
	const NAME = 'name';
	const DESCRIPTION = 'description';
	const TAGS = 'tags';
	
	const MAX_NAME_LEN = 256;
	
	protected static $multiLingualSupportedFields = array(
		self::NAME => self::NAME,
		self::DESCRIPTION => self::DESCRIPTION,
		self::TAGS => self::TAGS
	);

	private $appears_in = null;

	private $m_added_moderation = false;
	private $should_call_set_data_content = false;
	private $data_content = null;
	
	private $desired_version = null;

	//An attribute that contains the rules by which this entry was created, only in case it was created
	// via clone operation
	private $clone_options = null;

	private $archive_extension = null;
	
	private $copy_metadata = true;
	
	private static $mediaTypeNames = array(
		self::ENTRY_MEDIA_TYPE_AUTOMATIC => 'AUTOMATIC',
		self::ENTRY_MEDIA_TYPE_ANY => 'ANY',
		self::ENTRY_MEDIA_TYPE_VIDEO => 'VIDEO',
		self::ENTRY_MEDIA_TYPE_IMAGE => 'IMAGE',
		self::ENTRY_MEDIA_TYPE_TEXT => 'TEXT',
		self::ENTRY_MEDIA_TYPE_HTML => 'HTML',
		self::ENTRY_MEDIA_TYPE_AUDIO => 'AUDIO',
		self::ENTRY_MEDIA_TYPE_SHOW => 'SHOW',
		self::ENTRY_MEDIA_TYPE_SHOW_XML => 'SHOW_XML',
		self::ENTRY_MEDIA_TYPE_BUBBLES => 'BUBBLES',
		self::ENTRY_MEDIA_TYPE_XML => 'XML',
		self::ENTRY_MEDIA_TYPE_DOCUMENT => 'DOCUMENT',
		self::ENTRY_MEDIA_TYPE_SWF => 'SWF',
		self::ENTRY_MEDIA_TYPE_PDF => 'PDF',
		
		self::ENTRY_MEDIA_TYPE_GENERIC_1 => 'GENERIC_1',
		self::ENTRY_MEDIA_TYPE_GENERIC_2 => 'GENERIC_2',
		self::ENTRY_MEDIA_TYPE_GENERIC_3 => 'GENERIC_3',
		self::ENTRY_MEDIA_TYPE_GENERIC_4 => 'GENERIC_4',
		
		self::ENTRY_MEDIA_TYPE_LIVE_STREAM_FLASH => 'LIVE_STREAM_FLASH',
		self::ENTRY_MEDIA_TYPE_LIVE_STREAM_WINDOWS_MEDIA => 'LIVE_STREAM_WINDOWS_MEDIA',
		self::ENTRY_MEDIA_TYPE_LIVE_STREAM_REAL_MEDIA => 'LIVE_STREAM_REAL_MEDIA',
		self::ENTRY_MEDIA_TYPE_LIVE_STREAM_QUICKTIME => 'LIVE_STREAM_QUICKTIME',
	);

	private static $allow_override_read_only_fields = false;
	
	/**
	 * Applies default values to this object.
	 * This method should be called from the object's constructor (or
	 * equivalent initialization method).
	 * @see        __construct()
	 */
	public function applyDefaultValues()
	{
		parent::applyDefaultValues();
		$this->setStatus(entryStatus::PENDING);
		$this->setModerationStatus(self::ENTRY_MODERATION_STATUS_AUTO_APPROVED);
	}
	
	// the columns names is a list of all fields that will participate in the search_text
	// TODO - add the admin_tags to the column names
	public static function getColumnNames()	{		return array ( "name" , "tags" ,"description" , "admin_tags" );	}
	public static function getSearchableColumnName () { return "search_text" ; }

	// don't stop until a unique hash is created for this object
	private static function calculateId ( )
	{
		$dc = kDataCenterMgr::getCurrentDc();
		for ( $i = 0 ; $i < 10 ; ++$i)
		{
			$id = $dc["id"].'_'.kString::generateStringId();
			$existing_object = entryPeer::retrieveByPKNoFilter( $id );
			
			if ( ! $existing_object ) return $id;
		}
		
		die();
	}
	
	public function justSave($con = null)
	{
		return parent::save( $con );
	}
	
	public function save(PropelPDO $con = null, $skipReload = false)
	{
		$is_new = false;
		if ( $this->isNew() )
		{
			$this->setId(self::calculateId());

			// start by setting the modified_at to the current time
			$this->setModifiedAt( time() ) ;
			$this->setModerationCount(0);
			
			if (is_null($this->getAccessControlId()))
			{
				$partner = $this->getPartner();
				if($partner)
					$this->setAccessControlId($partner->getDefaultAccessControlId());
			}
			// only media clips should increments - not roughcuts or backgrounds
			if ( $this->type == entryType::MEDIA_CLIP )
				myStatisticsMgr::addEntry( $this );

			$is_new = true;
		}

		if ( $this->type == entryType::MIX )
		{
			// some of the properties should be copied to the kshow
			$kshow = $this->getkshow();
			if ( $kshow )
			{
				$modified  = false;
				if ( $kshow->getRank() != $this->getRank() )
				{
					$kshow->setRank( $this->getRank() );
					$modified = true;
				}
				if ( $kshow->getLengthInMsecs() != $this->getLengthInMsecs() )
				{
					$kshow->setLengthInMsecs ( $this->getLengthInMsecs() );
					$modified = true;
				}

				if ( $modified ) $kshow->save();
			}
			else
			{
				$this->log( "entry [" . $this->getId() . "] does not have a real kshow with id [" . $this->getKshowId() . "]", Propel::LOG_WARNING );
			}
		}

		myPartnerUtils::setPartnerIdForObj($this);
		
		if($this->getDisplayInSearch() != mySearchUtils::DISPLAY_IN_SEARCH_SYSTEM && $this->getDisplayInSearch() != mySearchUtils::DISPLAY_IN_SEARCH_RECYCLED)
		{
			mySearchUtils::setDisplayInSearch($this);
		}
			
		ktagword::updateAdminTags($this);
		
		// same for puserId ...
		$this->getPuserId();

		// make sure this entry is saved before calling updateAllMetadataVersionsRelevantForEntry, since fixMetadata retrieves the entry from the DB
		// and checks its data path which was modified above.
		$res = parent::save($con, $skipReload);
		if ($is_new)
		{
			// when retrieving the entry - ignore thr filter - when in partner has moderate_content =1 - the entry will have status=3 and will fail the retrieveByPk
			entryPeer::setUseCriteriaFilter(false);
			$obj = entryPeer::retrieveByPk($this->getId());
			$this->setIntId($obj->getIntId());
			entryPeer::setUseCriteriaFilter(true);
		}
		
		if ( $this->should_call_set_data_content )
		{
			// calling the function with null will cause it to use the $this->data_content
			$this->setDataContent( null );
			$res = parent::save($con, $skipReload);
		}
		
		// the fix should be done whether the status is READY or ERROR_CONVERTING
		if ( $this->getStatus() == entryStatus::READY || $this->getStatus() == entryStatus::ERROR_CONVERTING )
		{
			// fire some stuff due to the new status
			$version_to_update = $this->getUpdateWhenReady();

			if ( $version_to_update )
			{
				try{
					myMetadataUtils::updateAllMetadataVersionsRelevantForEntry ( $this);
					$this->resetUpdateWhenReady();
					$res = parent::save($con, $skipReload);
				}
				catch(Exception $e)
				{
					KalturaLog::err($e->getMessage());
				}
			}
		}
		
		$this->syncCategories();
		
		return $res;
	}


	// TODO - PERFORMANCE DB - move to use cache !!
	// will increment the views by 1
	public function incViews ( $should_save = true )
	{
		myStatisticsMgr::incEntryViews( $this );
	}
	
	public static function getMultiLingualSupportedFields()
	{
		return self::$multiLingualSupportedFields;
	}
	
	public function getDefaultLanguage()
	{
		return $this->getFromCustomData('defaultLanguage',null, null);
	}
	
	public function getResponseLanguage()
	{
		return $this->getFromCustomData('responseLanguage', null, null);
	}
	
	public function setDefaultLanguage($v)
	{
		$this->putInCustomData('defaultLanguage',$v);
	}
	
	public function setResponseLanguage($v)
	{
		$this->putInCustomData('responseLanguage', $v);
	}
	
	/**
	 * @param $v
	 * @param $fieldName
	 * @return string|null
	 * @throws KalturaAPIException
	 *
	 * This function calculates and returns the value that is going to be set in the relevant field in the db (called defaultValue)
	 * It also updates the multilingual object of the entry if applicable
	 */
	protected function getValueToSetInDbAndUpdateMultiLangObject($v, $fieldName)
	{
		if(is_null($v))
		{
			return $v;
		}
		$multiLingualValue = (is_string($v)) ? multiLingualUtils::getMultiLingualStringArrayFromString($v)->toObjectsArray() : $v;
		$dbValue = isset($multiLingualValue['default']) ? $multiLingualValue['default'] : null;
		if (multiLingualUtils::isMultiLingualRequest($multiLingualValue))
		{
			$defaultValues = multiLingualUtils::getFieldDefaultValuesFromNewMapping($this, $fieldName, $multiLingualValue);
			$dbValue = $defaultValues['defaultValue'];
			$multiLingualValue = ($fieldName === self::TAGS) ? $this->updateMultiLingualTags($multiLingualValue) : $multiLingualValue;
			multiLingualUtils::updateMultiLanguageObject($this, $fieldName, $multiLingualValue, $defaultValues);
		}
		return $dbValue;
	}
	
	public function setName($v)
	{
		$name = $this->getValueToSetInDbAndUpdateMultiLangObject($v, self::NAME);
		PeerUtils::setExtension($this, $name, self::MAX_NAME_LEN, __FUNCTION__);
		return !is_null($name) ? parent::setName(kString::alignUtf8String($name, self::MAX_NAME_LEN)) : null;
	}
	
	public function setDescription ($v)
	{
		if(is_null($v))
		{
			return parent::setDescription($v);
		}
		$description = $this->getValueToSetInDbAndUpdateMultiLangObject($v, self::DESCRIPTION);
		return !is_null($description) ? parent::setDescription($description): null;
	}
	
	public function setTags($tags , $update_db = true )
	{
		$newTags = $this->getValueToSetInDbAndUpdateMultiLangObject($tags, self::TAGS);
		if (!is_null($newTags))
		{
			if($this->tags !== $newTags)
			{
				$newTags = ktagword::updateTags($this->tags, $newTags, $update_db);
			}
			return parent::setTags(trim($newTags));
		}
		return null;
	}
	
	protected function updateMultiLingualTags($multiLingualTags)
	{
		$updatedMultiLingualTags = array();
		foreach ($multiLingualTags as $language => $tags)
		{
			$updatedMultiLingualTags[$language] = ktagword::updateTags($this->tags, $tags);
		}
		return $updatedMultiLingualTags;
	}

	public function getName()
	{
		return parent::getName() . PeerUtils::getExtension($this, __FUNCTION__);
	}
	
	public function getDescription()
	{
		$description =  parent::getDescription();
		return !is_null($description) ? $description : '';
	}
	
	public function getTags()
	{
		$tags = parent::getTags();
		return !is_null($tags) ? $tags : '';
	}
	
	public function getDefaultFieldValue($fieldName)
	{
		if (!in_array($fieldName, $this->getMultiLingualSupportedFields()))
		{
			return;
		}
		switch ($fieldName)
		{
			case self::NAME:
				return $this->getName();
			case self::DESCRIPTION:
				return $this->getDescription();
			case self::TAGS:
				return $this->getTags();
		}
	}

	/**
	 * will handle the flow in case of need to moderate.
	 */
	public function setStatusReady ( $force = false )
	{
		$this->setStatus( entryStatus::READY );
		$this->setDefaultModerationStatus();
		
		return $this->getStatus();
	}
	
	/* (non-PHPdoc)
	 * @see Baseentry::setModerationStatus()
	 */
	public function setModerationStatus($v)
	{
			
		$moderationStatuses = array(entry::ENTRY_MODERATION_STATUS_PENDING_MODERATION, entry::ENTRY_MODERATION_STATUS_FLAGGED_FOR_REVIEW);
		if (in_array($v, $moderationStatuses))
		{
			$this->incModerationCount();
		}
		if($v == $this->getModerationStatus())
		{
			return $this;
		}
		
		parent::setModerationStatus($v);
	}
	
	public function setDefaultModerationStatus()
	{
		$should_moderate = false;
		// in this case no configuration really matters
		if ( $this->getModerate() )
		{
			$should_moderate = true;
		}
		else
		{
			$should_moderate = myPartnerUtils::shouldModerate( $this->getPartnerId(), $this);
		}

		if( $should_moderate )
		{
			if ( ! $this->getId() )
			 	$this->save(); // save to DB so we'll have the id for the moderation list

			$this->setModerationStatus( self::ENTRY_MODERATION_STATUS_PENDING_MODERATION );
			if ( !$this->m_added_moderation )
			{
				myModerationMgr::addToModerationList( $this );
				$this->m_added_moderation = true;
			}
		}
		else
		{
			$this->setModerationStatus( self::ENTRY_MODERATION_STATUS_AUTO_APPROVED );
		}
	}

	public function isReady()
	{
		return ( $this->getStatus() == entryStatus::READY ) ;
	}

	public function getNormalizedRank ()
	{
		$res = round($this->rank / 1000);

		if ( $res > self::MAX_NORMALIZED_RANK ) return self::MAX_NORMALIZED_RANK;

		return $res;
	}

	/*
	 *  return an array of tuples of the file's version: [name, size, time, version]
	 */
	public function getAllVersions ()
	{
		$current_version = $this->getData();
		$c = strstr($current_version, '^') ?  '^' : '&';
		$parts = explode($c, $current_version);
		
		if (count($parts) == 2 && strlen($parts[1]))
		{
			return null;
		}
		
		// create an array to hold versions list
		$results = array();
		for ($version = 100000; $version <= $current_version; $version++ )
		{
			$version_sync_key = $this->getSyncKey( kEntryFileSyncSubType::DATA , $version);
			$local_file_sync = kFileSyncUtils::getLocalFileSyncForKey($version_sync_key, false);
			if ($local_file_sync)
			{
				$result = array();
				// first - file name (with the full path)
				$result[] = $local_file_sync->getFilePath();
				// second - size
				$result[] = $local_file_sync->getFileSize();
				// third - time
				$result[] = file_exists($local_file_sync->getFullPath()) ? filemtime ( $local_file_sync->getFullPath()) : false;
				// forth - version
				$result[] = substr( kFile::getFileNameNoExtension ( $local_file_sync->getFilePath() ) , strlen ($this->getId().'_') );
				$results[] = $result;
			}
		}

		return $results;
	}


	public function getAllVersionsFormatted()
	{
		$res = $this->getAllVersions();
		$formatted = array ();

		if ( ! is_array ( $res )  ) return null;
		
		foreach ( $res as $version_info )
		{
			$formatted []= array (
				"version" => $version_info[3] ,
				"rawData" =>  $version_info[2] ,
				"date" => strftime( "%d/%m/%y %H:%M:%S" , $version_info[2] ) );
		}
		return $formatted;
	}

	public function getLastVersion()
	{
		$version = kFile::getFileNameNoExtension ( $this->getData() );
		return $version;
	}

	public function getFormattedLengthInMsecs ()
	{
		return dateUtils::formatDuration ( $this->getLengthInMsecs() );
	}


	/**
	 * (non-PHPdoc)
	 * @see lib/model/ISyncableFile#getSyncKey()
	 * @param $sub_type
	 * @param $version
	 * @return FileSyncKey
	 * @throws FileSyncException
	 */
	public function getSyncKey ( $sub_type , $version = null )
	{
		static::validateFileSyncSubType ( $sub_type );
		$key = new FileSyncKey();
		$key->object_type = FileSyncObjectType::ENTRY;
		$key->object_sub_type = $sub_type;
		$key->object_id = $this->getId();

		$key->version = $this->getVersionForSubType ( $sub_type, $version );
		$key->partner_id = $this->getPartnerId();
		
		return $key;
	}


	/**
	 * @param $sub_type
	 * @param $version
	 * @return string
	 */
	public function generateBaseFileName( $sub_type, $version = null)
	{
		if(is_null($version))
			$version = $this->getVersion();
			
		// TODO - remove after Akamai bug fixed and create the file names with sub type
		if($sub_type == kEntryFileSyncSubType::ISM)
			return "_{$version}";
			
		if($sub_type == kEntryFileSyncSubType::ISMC)
			return "_{$version}";
		// remove till here
			
		return "{$sub_type}_{$version}";
	}
	
	/* (non-PHPdoc)
	 * @see lib/model/ISyncableFile#generateFileName()
	 */
	public function generateFileName( $sub_type, $version = null)
	{
		if($sub_type == kEntryFileSyncSubType::ISM)
			return $this->getId() . '_' . $this->generateBaseFileName(0, $version) . '.ism';
			
		if($sub_type == kEntryFileSyncSubType::ISMC)
			return $this->getId() . '_' . $this->generateBaseFileName(0, $version) . '.ismc';
			
		return $this->getId() . '_' . $this->generateBaseFileName($sub_type, $version);
	}


	/**
	 * (non-PHPdoc)
	 * @see lib/model/ISyncableFile#generateFilePathArr()
	 * @param $sub_type
	 * @param null $version
	 * @param bool $externalStorageMode
	 * @return array
	 * @throws FileSyncException
	 */
	public function generateFilePathArr($sub_type, $version = null, $externalStorageMode = false )
	{
		static::validateFileSyncSubType ( $sub_type );
		switch ($sub_type)
		{
			case kEntryFileSyncSubType::DATA:
				$data = $this->getData();
				if($this->getType() == entryType::MIX && (!$this->getData() || !strpos($this->getData(), 'xml')))
				{
					$data .= '.xml';
				}

				$res = myContentStorage::getGeneralEntityPath('entry/data', $this->getIntId(), $this->getId(), $data, $version, $externalStorageMode);
				break;
			case kEntryFileSyncSubType::DATA_EDIT:
				$res =  myContentStorage::getFileNameEdit( myContentStorage::getGeneralEntityPath('entry/data', $this->getIntId(), $this->getId(), $this->getData(), $version, $externalStorageMode) );
				break;
			case kEntryFileSyncSubType::THUMB:
				$res =  myContentStorage::getGeneralEntityPath('entry/bigthumbnail', $this->getIntId(), $this->getId(), $this->getThumbnail() , $version, $externalStorageMode);
				break;
			case kEntryFileSyncSubType::ARCHIVE:
				$res = null;
				$data_path = myContentStorage::getGeneralEntityPath('entry/data', $this->getIntId(), $this->getId(), $this->getData(), $version, $externalStorageMode);
				// assume the suffix is not the same as the one on the data
				$archive_path = dirname ( str_replace ( 'content/entry/' , 'archive/' , $data_path ) ) . '/' . $this->getId();
				if ($this->getArchiveExtension())
				{
					$res = $archive_path  . '.' . $this->getArchiveExtension();
				}
				else
				{
					$archive_pattern =  $archive_path . '.*' ;
					$arc_files =  glob ( myContentStorage::getFSContentRootPath( ) . $archive_pattern );
					foreach ( $arc_files as $full_path_name )
					{
						// return the first file found
						$res =  $full_path_name;
						break;
					}

					if ( ! $res )
					{
						$res = $archive_pattern;
					}
				}
				break;
			case  kEntryFileSyncSubType::DOWNLOAD:
				// in this case the $version is used as the format
				$basename = kFile::getFileNameNoExtension ( $this->getData() );
				$path = myContentStorage::getGeneralEntityPath('entry/download', $this->getIntId(), $this->getId(), $basename, null, $externalStorageMode);
				$download_path = $path.'.$version';
				$res =  $download_path;
				break;
			case kEntryFileSyncSubType::ISM:
				$path =  'entry/data';
				$basename = $this->generateBaseFileName(0, $this->getIsmVersion()) . '.ism';
				$res = myContentStorage::getGeneralEntityPath($path, $this->getIntId(), $this->getId(), $basename, null, $externalStorageMode);
				break;
			case kEntryFileSyncSubType::ISMC:
				$path =  'entry/data';
				$basename = $this->generateBaseFileName(0, $this->getIsmVersion()) . '.ismc';
				$res = myContentStorage::getGeneralEntityPath($path, $this->getIntId(), $this->getId(), $basename, null, $externalStorageMode);
				break;
			case kEntryFileSyncSubType::CONVERSION_LOG:
				$path =  'entry/data';
				$basename = $this->generateBaseFileName(0, $this->getIsmVersion()) . '.log';
				$res = myContentStorage::getGeneralEntityPath($path, $this->getIntId(), $this->getId(), $basename, null, $externalStorageMode);
				break;
			default:
				$path =  'entry/data';
				$res = myContentStorage::getGeneralEntityPath($path, $this->getIntId(), $this->getId(), $this->generateBaseFileName($sub_type, $version), null, $externalStorageMode);
		}

		return array ( myContentStorage::getFSContentRootPath( ) , $res );
	}
	
	protected function getVersionForSubType ( $sub_type, $version = null  )
	{
		if (
				$sub_type == kEntryFileSyncSubType::ISM
				||
				$sub_type == kEntryFileSyncSubType::ISMC
				||
				$sub_type == kEntryFileSyncSubType::CONVERSION_LOG
			)
		{
			if(!is_null($version))
				return $version;
				
			return $this->getIsmVersion();
		}
			
		$new_version = "";
		if ( $version )
		{
			if ( $sub_type == kEntryFileSyncSubType::DOWNLOAD )
			{
				// MUST have A VERSION !
				$new_version = $this->getVersion() . "." . $version;
			}
			else
			{
				$new_version = $version;
			}
		}
		else
		{
			switch($sub_type)
			{
				case kEntryFileSyncSubType::DATA:
				case kEntryFileSyncSubType::DATA_EDIT:
					$new_version = $this->getVersion();
					break;
				case kEntryFileSyncSubType::THUMB:
					$new_version = $this->getThumbnailVersion();
					break;
				case kEntryFileSyncSubType::ARCHIVE:
					$new_version = "";
					break;
				case kEntryFileSyncSubType::DOWNLOAD:
					// MUST have A VERSION !
					$new_version = $this->getVersion();
					break;
				case kEntryFileSyncSubType::INTERACTIVITY_DATA:
					$new_version = $this->getInteractivityVersion();
					if(is_null($new_version))
					{
						$new_version = 0;
					}
					break;
				case kEntryFileSyncSubType::VOLATILE_INTERACTIVITY_DATA:
					$new_version = $this->getVolatileInteractivityVersion();
					if(is_null($new_version))
					{
						$new_version = 0;
					}
					break;
			}
		}

		return $new_version;
	}
	
	/**
	 * Enter description here...
	 *
	 * @var FileSync
	 */
	private $m_file_sync;
	
	/**
	 * @return FileSync
	 */
	public function getFileSync ( )
	{
		return $this->m_file_sync;
	}
	
	public function setFileSync ( FileSync $file_sync )
	{
		 $this->m_file_sync = $file_sync;
	}

	
	protected static function validateFileSyncSubType ( $sub_type )
	{
		if ($sub_type != kEntryFileSyncSubType::DATA &&
			$sub_type != kEntryFileSyncSubType::DATA_EDIT &&
			$sub_type != kEntryFileSyncSubType::THUMB &&
			$sub_type != kEntryFileSyncSubType::ARCHIVE &&
			$sub_type != kEntryFileSyncSubType::DOWNLOAD  &&
			$sub_type != kEntryFileSyncSubType::ISM  &&
			$sub_type != kEntryFileSyncSubType::ISMC  &&
			$sub_type != kEntryFileSyncSubType::CONVERSION_LOG  &&
			$sub_type != kEntryFileSyncSubType::OFFLINE_THUMB &&
			$sub_type != kEntryFileSyncSubType::INTERACTIVITY_DATA &&
			$sub_type != kEntryFileSyncSubType::VOLATILE_INTERACTIVITY_DATA
		)
			throw new FileSyncException ( FileSyncObjectType::ENTRY ,
				 $sub_type , self::getEntryFileSyncSubTypes());
	}

	public static function getEntryFileSyncSubTypes()
	{
		return array
		(
			kEntryFileSyncSubType::DATA,
			kEntryFileSyncSubType::DATA_EDIT,
			kEntryFileSyncSubType::THUMB,
			kEntryFileSyncSubType::ARCHIVE,
			kEntryFileSyncSubType::DOWNLOAD,
			kEntryFileSyncSubType::OFFLINE_THUMB,
			kEntryFileSyncSubType::ISM,
			kEntryFileSyncSubType::ISMC,
			kEntryFileSyncSubType::CONVERSION_LOG,
			kEntryFileSyncSubType::INTERACTIVITY_DATA,
			kEntryFileSyncSubType::VOLATILE_INTERACTIVITY_DATA,
		);
	}

	// return the full path on the disk
	public function getFullDataPath( $version = NULL )
	{
		return myContentStorage::getFSContentRootPath() . $this->getDataPath($version);
	}
	
	/**
	 * This function returns the file system path for a requested content entity.
	 * @return string the content path
	 */
	public function getDataPath( $version = NULL )
	{
		if ( $version == NULL || $version == -1 )
		{
			return myContentStorage::getGeneralEntityPath("entry/data", $this->getIntId(), $this->getId(), $this->getData());
		}
		else
		{
			$ext = pathinfo ($this->getData(), PATHINFO_EXTENSION);
			$file_version = myContentStorage::getGeneralEntityPath("entry/data", $this->getIntId(), $this->getId(), $version);
			return $file_version . "." . $ext;
		}
	}

	public function getDataUrl( $version = NULL )
	{
		if( $this->getType() == entryType::PLAYLIST )
		{
			return myPlaylistUtils::getExecutionUrl( $this );
		}

		$current_version = $this->getVersion();

		$entryId = $this->getId();
		$media_type = $this->getMediaType();

		if ($media_type == self::ENTRY_MEDIA_TYPE_VIDEO || $media_type == self::ENTRY_MEDIA_TYPE_AUDIO)
		{
			$url = $this->createPlayManifestUrlByFormat("url");
		}
		else if ($media_type == self::ENTRY_MEDIA_TYPE_IMAGE  )
		{
			$width = self::DEFAULT_IMAGE_WIDTH;
			$height = self::DEFAULT_IMAGE_HEIGHT;

			$url = myPartnerUtils::getCdnHost($this->getPartnerId());
			$url .= myPartnerUtils::getUrlForPartner( $this->getPartnerId() , $this->getSubpId() );
			if (!$version)
				$version = $current_version;

			$url .= "/thumbnail/entry_id/$entryId/def_height/$height/def_width/$width/version/$version/type/1";
		}
		else
			return null;

		return $url;
	}

	/**
	 * This function sets and returns a new path for a requested content entity.
	 * @param string $filename = the original fileName from which the extension is cut.
	 * @return string the content file name
	 */
	public function setData($filename , $force = false )
	{
		if (  $force )
			$data = $filename;
		else
			$data = myContentStorage::generateRandomFileName($filename, $this->getData());
	
		Baseentry::setData( $data );
		return $this->getData();
	}

	/**
	 *
	 * @param $version
	 * @param $format
	 * @return FileSync
	 */
	public function getDownloadFileSyncAndLocal ( $version = NULL , $format = null , $sub_type = null )
	{
		$sync_key = null;
		
		if ( $this->getType() == entryType::MEDIA_CLIP)
		{
			if ( $this->getMediaType() == self::ENTRY_MEDIA_TYPE_VIDEO || $this->getMediaType() == self::ENTRY_MEDIA_TYPE_AUDIO)
			{
				$flavor_assets = assetPeer::retrieveBestPlayByEntryId($this->getId()); // uset the format as the extension
				if($flavor_assets)
					$sync_key = $flavor_assets->getSyncKey(flavorAsset::FILE_SYNC_FLAVOR_ASSET_SUB_TYPE_ASSET);
			}
			elseif ( $this->getMediaType() == self::ENTRY_MEDIA_TYPE_IMAGE )
			{
				$sync_key = $this->getSyncKey( kEntryFileSyncSubType::DATA , $version );
			}
		}
		elseif ( $this->getType() == entryType::MIX )
		{
			// if roughcut - the version should be used
			$sync_key = $this->getSyncKey( kEntryFileSyncSubType::DOWNLOAD , $version );
		}
		else
		{
			// if not roughcut -  the format should be used
			$sync_key = $this->getSyncKey( kEntryFileSyncSubType::DOWNLOAD , $format );
		}
		
		if(!$sync_key)
			return null;
			
		return kFileSyncUtils::getReadyFileSyncForKey ( $sync_key , true , false );
	}
	
	// return the file
	public function getDownloadPath( $version = NULL , $format = null , $sub_type = null )
	{
		
		// fetch the path (from remote if no local)
		list ( $file_sync , $local ) = $this->getDownloadFileSyncAndLocal( $version , $format , $sub_type );
		
		if ( ! $file_sync )
			return null;
			
		return $file_sync->getFullPath();
	}

	public function getDownloadSize( $version = NULL )
	{
		// fetch the path (from remote if no local)
		list ( $file_sync , $local ) = $this->getDownloadFileSyncAndLocal( $version , null );
		
		if ( ! $file_sync )
			return 0;
		
		return $file_sync->getFileSize();
	}
	
	public function getDownloadUrl( $version = NULL )
	{		
		if(in_array($this->getMediaType(), array(self::ENTRY_MEDIA_TYPE_VIDEO, self::ENTRY_MEDIA_TYPE_AUDIO )))
		{
			//Request the soruce flavor to be compaitble to raw action default behavior
			return $this->createPlayManifestUrlByFormat("download") . "/flavorParamIds/0"; 
		}
		
		// always return the URL for the download - there is enough logic there to fix problems return the correct version/flavor
		return myPartnerUtils::getCdnHost($this->getPartnerId()). myPartnerUtils::getUrlForPartner( $this->getPartnerId() , $this->getSubpId() ) . "/raw/entry_id/" . $this->getId() . "/version/" . $this->getVersion();
	}
	
	
	public function getDownloadPathForFormat ($format, $version = NULL)
	{
		// used by ppt-convert flow (downloadPath in addDownload response)
		// and perhaps by other clients as name
		$download_path = $this->getDownloadPath( $version , $format , kEntryFileSyncSubType::DOWNLOAD );
		if($download_path)
			return $download_path;
		
		// if did not return anything, probably due to missing fileSync
		// missing fileSync - if conversion was not done yet
		$key = $this->getSyncKey(kEntryFileSyncSubType::DOWNLOAD, $format);
		return kFileSyncUtils::getLocalFilePathForKey($key);
	}
	
/*
 * deprecated - was called from myBatchDownloadVideoServer which is no longer used
	// given the path of the converted file (not an FLV file) - create its URL
	public function getConvertedDownoadUrl ( $file_real_path )
	{
		$path = str_replace ( myContentStorage::getFSContentRootPath() , "" , $file_real_path );
		$path = str_replace ( "\\" , "" , $path );
		return myPartnerUtils::getCdnHost($this->getPartnerId()). myPartnerUtils::getUrlForPartner( $this->getPartnerId() , $this->getSubpId() ) . $path;
	}
	*/
	
	public function setDesiredVersion ( $v )
	{
		$this->desired_version = $v;
	}

	public function getDesiredVersion (  )
	{
		return $this->desired_version ;
	}
	
	public function setArchiveExtension($v)
	{
		$this->archive_extension = $v;
	}
	
	public function getArchiveExtension()
	{
		return $this->archive_extension;
	}
	
	public function setCopyMetadata($v)
	{
		$this->copy_metadata = $v;
	}
	
	public function getCopyMetadata()
	{
		return $this->copy_metadata;
	}

	/**
	 * The method returns the var_dumpd_options, in case the entry was created via clone operation.
	 * Otherwise; it returns null
	 * @return null|array kBaseEntryCloneOptionComponent
     */
	public function getCloneOptions()
	{
		return $this->clone_options;
	}


	/**
	 * The method sets the options by which the current entry was cloned.
	 * @param array $cloneOptionsArray
     */
	public function setCloneOptions(array $cloneOptionsArray)
	{
		$this->clone_options = $cloneOptionsArray;
	}

	// will work only for types that the data can be served as an a response to the service
	public function getDataContent ( $from_cache = false )
	{
		if ( $this->getType() == entryType::MIX ||
			$this->getType() == entryType::DATA ||
			$this->getType() == entryType::PLAYLIST ||
			$this->getMediaType() == self::ENTRY_MEDIA_TYPE_XML ||
			$this->getMediaType() == self::ENTRY_MEDIA_TYPE_TEXT ||
			$this->getMediaType() == self::ENTRY_MEDIA_TYPE_GENERIC_1 )
		{
			if ( $from_cache ) return $this->data_content;
			$version = $this->desired_version;
			if ( ! $version || $version == -1 ) $version = null;
			
			$sync_key = $this->getSyncKey( kEntryFileSyncSubType::DATA , $version );
			$content = kFileSyncUtils::file_get_contents( $sync_key , true , false ); // don't be strict when fetching this content
				
			if ( $content )
			{
				// patch for fixing old AE roughcuts without cross="0"
				if ($this->getType() == entryType::MIX)
				{
					$data2 = str_replace('<EndTransition type', '<EndTransition cross="0" type', $content);
					return $data2;
				}
				
				return $content;
			}
		}
		return null;
	}
	
	// will work only for types that the data can be served as an a response to the service
	public function setDataContent ( $v , $increment_version = true , $allow_type_roughcut = false )
	{
		// simple patch when setDataContent is called with $v = '' so that we check if that's the current active file_sync content and avoid writing new fs to db.
		// avoiding a more elegant fixes like 'empty($v)' or '!is_null($v)' because '$v = null' is being used intentionally at entry.php:312
		// and other places may return 'false' when fetching $v like DataService.php:239 which will change backward compatability
		if(($v || $v === '') && $v == $this->getDataContent())
		{
			return;
		}
		
//		if ( $v === null ) return ;
		// DON'T do this for ENTRY_TYPE_SHOW unless $allow_type_roughcut is true
		// - the metadata is handling is complex and is done in other places in the code
		if ( ($allow_type_roughcut && $this->getType() == entryType::MIX) ||
			$this->getType() == entryType::DATA ||
			$this->getType() == entryType::PLAYLIST ||
			$this->getMediaType() == self::ENTRY_MEDIA_TYPE_XML ||
			$this->getMediaType() == self::ENTRY_MEDIA_TYPE_SHOW ||
			$this->getMediaType() == self::ENTRY_MEDIA_TYPE_TEXT ||
			$this->getMediaType() == self::ENTRY_MEDIA_TYPE_GENERIC_1 )
		{
			if ( $this->getId() == null )
			{
				// come back when there is an ID
				$this->data_content = $v;
				$this->should_call_set_data_content = true;
				return ;
			}
			
			// 	if increment_version is false - don't be strict
			$strict = false;
			if ( ! $increment_version || $v == $this->data_content )
			{
				// attempting to update the same value
				$strict = false ;
			}
			
			if ( $v === null ) $v = $this->data_content;
			else $this->data_content = $v;  // store it so it can be used with getDataContent(true) is called

			if ( $v !== null )
			{
				// increment the version
				if ( $increment_version ) $this->setData ( parent::getData() . $this->getFileSuffix() ) ;
				$this->should_call_set_data_content = false;
				$this->save();
				
				$sync_key = $this->getSyncKey( kEntryFileSyncSubType::DATA );
				kFileSyncUtils::file_put_contents( $sync_key , $v , $strict );
			}
		}
	}
	
	// return the default file suffix according to the entry type
	private function getFileSuffix ( )
	{
		if ( $this->getType() == entryType::MIX ||
			 $this->getMediaType() == self::ENTRY_MEDIA_TYPE_SHOW ||
			 $this->getMediaType() == self::ENTRY_MEDIA_TYPE_XML )
		{
			return ".xml";
		}
		elseif ( $this->getMediaType() == self::ENTRY_MEDIA_TYPE_TEXT ||
				$this->getMediaType() == self::ENTRY_MEDIA_TYPE_GENERIC_1 ||
				$this->getMediaType() == self::ENTRY_MEDIA_TYPE_GENERIC_2 )
		{
			return ".txt";
		}
		return "";
	}

	public function getAssetCacheTime()			{ return $this->getFromCustomData( "assetCacheTime", null, null ); }
	
	protected function setAssetCacheTime( $v )	{ $this->putInCustomData( "assetCacheTime" , $v ); }

	public function getThumbnail()
	{
		$thumbnail = parent::getThumbnail();
		
		if (!$thumbnail && $this->getMediaType() == entry::ENTRY_MEDIA_TYPE_AUDIO)
			$thumbnail = "&audio_thumb.jpg";
			
		return $thumbnail;
	}
	
	/**
	 * This function returns the file system path for a requested content entity.
	 * @return string the content path
	 */
	public function getThumbnailPath( $version = NULL )
	{
		return myContentStorage::getGeneralEntityPath("entry/thumbnail", $this->getIntId(), $this->getId(), $this->getThumbnail() , $version );
	}

	/**
	 * @param null $version
	 * @param null $protocol
	 * @return string|string[]|null
	 * @throws FileSyncException
	 * @throws KalturaAPIException
	 * @throws kCoreException
	 */
	public function getThumbnailUrl($version = null, $protocol = null)
	{
		if(PermissionPeer::isValidForPartner(PermissionName::FEATURE_DISABLE_KMC_DRILL_DOWN_THUMB_RESIZE, $this->getPartnerId()))
		{
			$subType = kEntryFileSyncSubType::DATA;
			if ($this->getType() == entryType::MEDIA_CLIP && $this->getMediaType() != entry::ENTRY_MEDIA_TYPE_IMAGE)
				$subType = kEntryFileSyncSubType::THUMB;

			$syncKey = $this->getSyncKey($subType);
			try
			{
				list($fileSync, $serveRemote) = kFileSyncUtils::getFileSyncByStoragePriority($this->getPartnerId(), $syncKey);
			}
			catch (kCoreException $ex)
			{
				switch ($ex->getCode())
				{
					case kCoreException::FILE_NOT_FOUND:
						return null;
						break;
					default:
						throw $ex;
				}
			}
			if ($serveRemote && $fileSync)
			{
				$url = $fileSync->getExternalUrl($this->getId());
				if (!is_null($protocol))
					$url = preg_replace('/^https?/', $protocol, $url);

				return $url;
			}
		}
		
		//$path = $this->getThumbnailPath ( $version );
		$path =  myPartnerUtils::getUrlForPartner( $this->getPartnerId() , $this->getSubpId() ) . "/thumbnail/entry_id/" . $this->getId() ;

		$partner = $this->getPartner();

		$current_version = $this->getThumbnailVersion();
		if ($partner && $this->getMediaType() == entry::ENTRY_MEDIA_TYPE_AUDIO && $partner->getAudioThumbEntryId()  && $partner->getAudioThumbEntryVersion())
		{
			$thumbEntryId = $partner->getAudioThumbEntryId();
			$thumbVersion = $partner->getAudioThumbEntryVersion();
			$current_version .= "/thumb_entry_id/$thumbEntryId/thumb_entry_version/$thumbVersion";
		}
		elseif  ($partner && in_array($this->getType(), array(entryType::LIVE_STREAM , entryType::LIVE_CHANNEL)) && $partner->getLiveThumbEntryId()  && $partner->getLiveThumbEntryVersion())
		{
			$thumbEntryId = $partner->getLiveThumbEntryId();
			$thumbVersion = $partner->getLiveThumbEntryVersion();
			$current_version .= "/thumb_entry_id/$thumbEntryId/thumb_entry_version/$thumbVersion";
		}

		if ( $version )
			$path .= "/version/$version";
		else
			$path .= "/version/$current_version";

		$url = myPartnerUtils::getThumbnailHost($this->getPartnerId(), $protocol) . $path ;
		return $url;
	}

	public function getBigThumbnailPath($revertToSmall = false , $version = NULL )
	{
		if ( $this->getMediaType() == self::ENTRY_MEDIA_TYPE_IMAGE )
		{
			// we dont need to make a copy for the big thumbnail - we can use the image itself
			return $this->getDataPath();
		}

		$path = myContentStorage::getGeneralEntityPath("entry/bigthumbnail", $this->getIntId(), $this->getId(), $this->getThumbnail() , $version );
		if ($revertToSmall && !file_exists(myContentStorage::getFSContentRootPath().$path))
			$path = $this->getThumbnailPath();

		return $path;
	}

	public function getBigThumbnailUrl( $version = NULL )
	{

		$path = $this->getBigThumbnailPath ( $version );
		$url = requestUtils::getRequestHost() . $path ;
		return $url;
	}

	/**
	 * This function sets and returns a new path for a requested content entity.
	 * @param string $filename = the original fileName from which the extension is cut.
	 * @return string the content file name
	 */
	public function setThumbnail($filename , $force = false )
	{
		if (  $force )
			$data = $filename;
		else
			$data = myContentStorage::generateRandomFileName($filename, $this->getThumbnail());


		parent::setThumbnail($data);
		return $this->getThumbnail();
	}

	public function setAdminTags($tags)
	{
		if ( $tags === null ) return ;
		
		if ( $tags == "" || $this->getAdminTags() !== $tags ) {
			parent::setAdminTags(trim(ktagword::fixAdminTags( $tags)));
		}
	}
	
	/* (non-PHPdoc)
	 * @see IIndexable::indexToSearchIndex()
	 */
	public function indexToSearchIndex()
	{
		kEventsManager::raiseEventDeferred(new kObjectReadyForIndexEvent($this));
	}
	
	/**
	 * Used to add dynamic JSON attributes to the search index
	 */
	public function getDynamicAttributes()
	{
		$dynamicAttributes = array();

		// Map catrgories to creation date
		$categoryEntries = categoryEntryPeer::selectByEntryId( $this->getId() );
		foreach ( $categoryEntries as $categoryEntry )
		{
			$createdAt = $categoryEntry->getCreatedAt( null ); // Passing null in order to get a numerical Unix Time Stamp instead of a string
			
			// Get the dyn. attrib. name in the format of: cat_{cat id}_createdAt (e.g.: cat_123_createdAt)
			$dynAttribName = kCategoryEntryAdvancedFilter::getCategoryCreatedAtDynamicAttributeName( $categoryEntry->getCategoryId() );
			
			$dynamicAttributes[$dynAttribName] = $createdAt;
		}

		$pluginInstances = KalturaPluginManager::getPluginInstances('IKalturaDynamicAttributesContributer');
		foreach($pluginInstances as $pluginName => $pluginInstance) {
			try {
				$dynamicAttributes += $pluginInstance->getDynamicAttributes($this);
			} catch (Exception $e) {
				KalturaLog::err($e->getMessage());
				continue;
			}
		}

		return $dynamicAttributes;
	}
	
	public function getMaxCategoriesPerEntry($numberOfPrivacyContext = null)
	{
		$maxCategoriesPerEntry = entry::MAX_CATEGORIES_PER_ENTRY;
		if (PermissionPeer ::isValidForPartner(PermissionName::FEATURE_DISABLE_CATEGORY_LIMIT,
		                                       $this -> getPartnerId()))
		{
			if (!is_null($numberOfPrivacyContext) && $numberOfPrivacyContext < 2)
			{
				$maxCategoriesPerEntry = entry::MAX_CATEGORIES_PER_ENTRY_DISABLE_LIMIT_FEATURE;
			}
		}
			
		// When batch move entry between categories it's adding the new category before deleting the old one
		if(kCurrentContext::$ks_partner_id == Partner::BATCH_PARTNER_ID && kCurrentContext::$ks_object)
		{
			$batchJobType = kCurrentContext::$ks_object->getPrivilegeValue(ks::PRIVILEGE_BATCH_JOB_TYPE);
			if(intval($batchJobType) == BatchJobType::MOVE_CATEGORY_ENTRIES)
			{
				$maxCategoriesPerEntry *= 2;
			}
		}
		
		return $maxCategoriesPerEntry;
	}
	
	/**
	 * Set the categories (use only the most child categories)
	 *
	 * @param string $categories
	 */
	public function setCategories($newCats)
	{
		if(PermissionPeer::isValidForPartner(PermissionName::FEATURE_DISABLE_CATEGORY_LIMIT, $this->getPartnerId()))
			return;
			
		$newCats = !is_null($newCats) ? explode(self::ENTRY_CATEGORY_SEPARATOR, $newCats) : array();
		
		$this->trimCategories($newCats);
		
		$maxCategoriesPerEntry = $this->getMaxCategoriesPerEntry();
		if (count($newCats) > $maxCategoriesPerEntry)
			throw new kCoreException("Max number of allowed entries per category was reached", kCoreException::MAX_CATEGORIES_PER_ENTRY, $maxCategoriesPerEntry);

		// remove duplicates
		$newCats = array_unique($newCats);

		$this->new_categories = implode(self::ENTRY_CATEGORY_SEPARATOR, $newCats);
		$this->modifiedColumns[] = entryPeer::CATEGORIES;
		$this->is_categories_modified = true;
		$this->is_categories_names_modified = true;
	}
	
	public function setCategoriesIds($v)
	{
		if(PermissionPeer::isValidForPartner(PermissionName::FEATURE_DISABLE_CATEGORY_LIMIT, $this->getPartnerId()))
			return;
		
		$newCats = !is_null($v) ? explode(self::ENTRY_CATEGORY_SEPARATOR, $v) : array();
		
		$this->trimCategories($newCats);
		
		$maxCategoriesPerEntry = $this->getMaxCategoriesPerEntry();
		if (count($newCats) > $maxCategoriesPerEntry)
			throw new kCoreException("Max number of allowed entries per category was reached", kCoreException::MAX_CATEGORIES_PER_ENTRY, $maxCategoriesPerEntry);

		// remove duplicates
		$newCats = array_unique($newCats);
		
		$this->new_categories_ids = implode(self::ENTRY_CATEGORY_SEPARATOR, $newCats);
		$this->modifiedColumns[] = entryPeer::CATEGORIES;
		$this->is_categories_modified = true;
	}
	
	public function getCategories()
	{
		if(PermissionPeer::isValidForPartner(PermissionName::FEATURE_DISABLE_CATEGORY_LIMIT, $this->getPartnerId()))
			return null;
		$result = parent::getCategories();
		return is_null($result) ? '' : $result;
	}

	public function getCategoriesIds()
	{
		if(PermissionPeer::isValidForPartner(PermissionName::FEATURE_DISABLE_CATEGORY_LIMIT, $this->getPartnerId()))
			return null;
		
		$result = parent::getCategoriesIds();
		return is_null($result) ? '' : $result;
	}

	/*public function renameCategory($oldFullName, $newFullName)
	{
		$categories = explode(self::ENTRY_CATEGORY_SEPARATOR, $this->categories);
		foreach($categories as &$category)
		{
			$oldFullName = str_replace(array ('(', ')'), array ('\(', '\)'), $oldFullName);
			$category = preg_replace("/^".$oldFullName."/", $newFullName, $category);
		}
		$this->setCategories(implode(self::ENTRY_CATEGORY_SEPARATOR, $categories));
		$this->old_categories = $this->categories; // so the sync won't increment the count on categories
		$this->modifiedColumns[] = entryPeer::CATEGORIES;
		$this->is_categories_modified = true;
	}*/
	
	public function removeCategory($fullName)
	{
		$this->old_categories = $this->categories;
		$categories = explode(self::ENTRY_CATEGORY_SEPARATOR, $this->categories);
		$newCategories = array();
		foreach($categories as $category)
		{
			if (!preg_match("/^".$fullName."/", $category))
				$newCategories[] = $category;
		}
		$this->setCategories(implode(self::ENTRY_CATEGORY_SEPARATOR, $newCategories));
		$this->modifiedColumns[] = entryPeer::CATEGORIES;
		$this->is_categories_modified = true;
	}
		
	private function trimCategories(&$categories)
	{
		$trimedCategories = array();
		foreach($categories as &$cat)
		{
			$cat = trim($cat);
			$catExploded = explode(categoryPeer::CATEGORY_SEPARATOR, $cat);
			$fixedCat = array();
			
			foreach($catExploded as $subCat)
			{
				if (strlen($subCat) > 0)
				{
					$fixedCat[] = $subCat;
				}
			}
			
			$cat = implode(categoryPeer::CATEGORY_SEPARATOR, $fixedCat);
			
			if (strlen($cat) > 0)
				$trimedCategories[] = $cat;
 		}
 		
 		$categories = $trimedCategories;
	}
	
	public function getCreatedAtAsInt ()
	{
		return $this->getCreatedAt( null );
	}

	public function getUpdateAtAsInt ()
	{
		return $this->getUpdatedAt( null );
	}

	public function getFormattedCreatedAt( $format = dateUtils::KALTURA_FORMAT )
	{
		return dateUtils::formatKalturaDate( $this , 'getCreatedAt' , $format );
	}

	public function getFormattedUpdatedAt( $format = dateUtils::KALTURA_FORMAT )
	{
		return dateUtils::formatKalturaDate( $this , 'getUpdatedAt' , $format );
	}

	public function getAppearsIn ( )
	{
		if ( $this->appears_in == NULL )
		{
			if ( $this->getkshow() )
			{
				$this->setAppearsIn ( $this->getkshow()->getName() );
			}
			else
			{
				return ""; // strange - no kshow ! must be a dangling entry
			}
		}

		return $this->appears_in;
	}

	public function setAppearsIn ( $name )
	{
		$this->appears_in = $name;
	}

	public function getWidgetImagePath()
	{
		return myContentStorage::getGeneralEntityPath("entry/widget", $this->getIntId(), $this->getId(), ".gif" );
	}

	// when calling duration - use seconds rather than msecs
	public function getDuration ()
	{
		$t = $this->getLengthInMsecs();
		if ( $t == null ) return 0;
		return ( $t / 1000 );
	}

	/**
	 * returns the duration as int
	 * @return int
	 */
	public function getDurationInt()
	{
		return (int)round($this->getDuration());
	}

	/**
	 * @return string
	 */
	public function getDurationType()
	{
		return entryPeer::getDurationType($this->getDurationInt());
	}

	public function getMetadata( $version = null)
	{
		if ( $this->getMediaType() != entry::ENTRY_MEDIA_TYPE_SHOW )
		{
			return null;
		}

		if ( $version <= 0  ) $version=null;
		$sync_key = $this->getSyncKey( kEntryFileSyncSubType::DATA , $version );
		$content = kFileSyncUtils::file_get_contents( $sync_key , true , false ); // don't be strict when fetching metadata
		if ( $content )
			return $content;
		else
			return  "<xml></xml>";
	}


	// will place the metadata in the entry (as long as it's of type show)
	// TODO - maybe change this because entries can change their types - intro of type video can become a show !!
	// by default will override the existing file - this will be starnge because there is not supposed to be a file with that name yet -
	//  all the indexes up to this point where smaller then this futurae one.
/**
 	Writes the content of the metadata to a new file.
	Returns the number of bytes written to disk
 */
	// TODO - is this really what should be returned ??
	public function setMetadata ( $kshow , $content , $override_existing=true , $total_duration = null , $specific_version = null )
	{
		if ( $this->getMediaType() != entry::ENTRY_MEDIA_TYPE_SHOW )
		{
			return null;
		}

		// TODO - better to call this with slight modifications
		//myMetadataUtils::setMetadata ($content, $kshow, $this , $override_existing );
		if ( $specific_version == null )
		{
			// 	increment the counter of the file
			$this->setData ( parent::getData() );
		}
		// check that the file of the desired version really exists
//		$content_dir =  myContentStorage::getFSContentRootPath();
//		$file_name = $content_dir . $this->getDataPath( $specific_version ); // replaced__getDataPath

		$sync_key = $this->getSyncKey ( kEntryFileSyncSubType::DATA , $specific_version );
		
		if ( $override_existing || ! kFileSyncUtils::file_exists( $sync_key ,false )  )
		{
			$duration = $total_duration ? $total_duration : myMetadataUtils::getDuration ( $content );
			$this->setLengthInMsecs ( $duration * 1000 );
			$total_duration = null;
			$editor_type = null;
			$version = myContentStorage::getVersion( kFileSyncUtils::getReadyLocalFilePathForKey ( $sync_key ) );
			$fixed_content = myFlvStreamer::fixMetadata( $content , $version, $total_duration , $editor_type);
			
			$this->setModifiedAt(time());		// update the modified_at date
			$this->save();
			
			$sync_key = $this->getSyncKey ( kEntryFileSyncSubType::DATA , $version );
			// TODO: here we assume we are UPDATING an exising version of the file - make sure all the following functions are tolerant.
			kFileSyncUtils::file_put_contents( $sync_key , $fixed_content , false ); // replaced__setFileContent

			// update the roughcut_entry table
			if  ( $kshow != null ) $kshow_id = $kshow->getId();
			else $kshow_id = $this->getKshowId();

			$all_entries_for_roughcut = myMetadataUtils::getAllEntries ( $fixed_content );
			roughcutEntry::updateRoughcut( $this->getId() , $version , $kshow_id  , $all_entries_for_roughcut );

			return ;
		}
		else
		{
			// no need to save changes - why increment the count if failed ??
			return  -1;
		}
	}

	public function fixMetadata ( $increment_version = true ,  $content = null , $total_duration = null , $specific_version = null )
	{
		// check that the file of the desired version really exists
		$content_dir =  myContentStorage::getFSContentRootPath();
		if ( !$content ) $content = $this->getMetadata( $specific_version );

		if ( $increment_version )
		{
			// 	increment the counter of the file
			$this->setData ( parent::getData() );
		}

		$file_name = kFileSyncUtils::getLocalFilePathForKey($this->getSyncKey(kEntryFileSyncSubType::DATA, $specific_version)); // replaced__getDataPath
		
		$duration = $total_duration ? $total_duration : myMetadataUtils::getDuration ( $content );
		$this->setLengthInMsecs ( $duration * 1000 );
		$total_duration = null;
		$editor_type = null;
		$version = myContentStorage::getVersion($file_name);
		$fixed_content = myFlvStreamer::fixMetadata( $content , $version, $total_duration , $editor_type);
		
		$this->save();
		
		$sync_key = $this->getSyncKey ( kEntryFileSyncSubType::DATA , $version );
		kFileSyncUtils::file_put_contents( $sync_key , $fixed_content , false ); // replaced__setFileContent
		
		return $fixed_content;
	}

	public function getVersion()
	{
		$version = parent::getData();

		if ($version)
		{
			$c = strstr($version, '^') ?  '^' : '&';
			$parts = explode( $c, $version);
		}
		else
			$parts = array('');

		if (strlen($parts[0]))
			$current_version = pathinfo($parts[0], PATHINFO_FILENAME) ;
		else
			$current_version = 0;

		return $current_version;
	}

	public function getThumbnailVersion()
	{
		// For image entry, the data file sync sub type is used as thumbnail
		if(($this->getType() == entryType::MEDIA_CLIP && $this->getMediaType() == self::ENTRY_MEDIA_TYPE_IMAGE) || ($this->getType() == entryType::PLAYLIST))
			return $this->getVersion();
			
		$version = parent::getThumbnail();

		if ($version)
		{
			$c = strstr($version, '^') ?  '^' : '&';
			$parts = explode( $c, $version);
		}
		else
			$parts = array('');

		if (strlen($parts[0]))
			$current_version = pathinfo($parts[0], PATHINFO_FILENAME) ;
		else
			$current_version = 0;

		return $current_version;
	}
	// makes a copy of the desired version from the past as the next coming version of the entry
	//   $desired_version = 100003, current_version = 100006 -> will conpy the content of <id>_100003.xxx -> <id>_1000007.xxx and will increment the version
	// so current_version = 100007.xxx now
	public function rollbackVersion( $desired_version )
	{
		// don't duplicate if staying in hte same version
		$current_version = $this->getVersion();
		if ( $desired_version ==  $current_version)
			return $current_version;

		$source_syc_key = $this->getSyncKey( kEntryFileSyncSubType::DATA , $desired_version );
/*
		// check that the file of the desired version really exists
		$content =  myContentStorage::getFSContentRootPath();
		$path = $content . $this->getDataPath( $desired_version ); // replaced__getDataPath

		if ( ! file_exists( $path ))
		{
			return null;
		}
*/
		
		// increment the counter of the file
		$this->setData ( parent::getData() );

		$target_syc_key = $this->getSyncKey( kEntryFileSyncSubType::DATA );
/*
		$new_path = $content . $this->getDataPath( ); //replaced__getDataPath

		// make a copy
		kFile::moveFile( $path , $new_path , true , true );
*/
		kFileSyncUtils::copy( $source_syc_key , $target_syc_key );
		
		$this->save();
		// return the new version
		return $this->getVersion();
	}

	// if has status self::ENTRY_S
	public function getImportInfo ()
	{
		 if ( $this->getStatus() == entryStatus::IMPORT )
		 {
		 	$c = new Criteria();
		 	$c->add ( BatchJobPeer::ENTRY_ID , $this->getId() );
		 	$c->addDescendingOrderByColumn(  BatchJobPeer::ID );
	 	  	$import =  BatchJobPeer::doSelectOne ( $c );
	 	  	return $import;
		 }
		 return  null;
	}


	public function getDisplayCredit ( )
	{
		if ($this->getCredit())
			return $this->getCredit();
		else if ( $this->getScreenName() )
		{
			return $this->getScreenName();
		}
		else
		{
			$kuser = $this->getkuser();
			return ( $kuser ? $kuser->getScreenName() : "" );
		}
	}

	public function moderate ($new_moderation_status , $fix_moderation_objects = false )
	{
		$error_msg = "Moderation status [$new_moderation_status] not supported by entry";
		switch($new_moderation_status)
		{
			case moderation::MODERATION_STATUS_APPROVED:
				// a new notification that is sent when an entry was founc to be ok after moderation
				myNotificationMgr::createNotification(kNotificationJobData::NOTIFICATION_TYPE_ENTRY_UPDATE , $this );
				break;
			case moderation::MODERATION_STATUS_BLOCK:
				myNotificationMgr::createNotification(kNotificationJobData::NOTIFICATION_TYPE_ENTRY_BLOCK , $this->getid());
				break;
			case moderation::MODERATION_STATUS_DELETE:
				// physical disk deletion
				myEntryUtils::deleteEntry($this);
				myNotificationMgr::createNotification(kNotificationJobData::NOTIFICATION_TYPE_ENTRY_BLOCK , $this->getid());
				break;
			case moderation::MODERATION_STATUS_PENDING:
//				$this->setStatus(entryStatus::MODERATE);
//				throw new Exception($error_msg);
				break;
			case moderation::MODERATION_STATUS_REVIEW:
				// in this case the status of the entry should not change
//				throw new Exception($error_msg);
				break;
			default:
				throw new Exception($error_msg);
				break;
		}

		$this->setModerationStatus( $new_moderation_status );
		
		// TODO - fix loop of updating from entry ot moderation back to entry ...
		if ( $fix_moderation_objects )
		{
			myModerationMgr::updateModerationsForObject ( $this , $new_moderation_status );
		}
		$this->save();
	}

	public function setEditorType ( $editor_type )	{		$this->putInCustomData ( "editor_type" , $editor_type );	}

	public function getEditorType (  )
	{
		if ( $this->getType() != entryType::MIX ) return null;
		$res = $this->getFromCustomData( "editor_type" );
		if ( $res == null ) return "Keditor"; // no value means Keditor == advanced
		return $res;
	}
	
	public function setConversionProfileId($conversion_quality)
	{
		$this->setConversionQuality($conversion_quality);
	}
	public function setConversionQuality($conversion_quality)
	{
		parent::setConversionProfileId($conversion_quality);
		$this->putInCustomData("conversion_quality", $conversion_quality);
	}
	public function getConversionQuality (  ){return $this->getFromCustomData( "conversion_quality" );}
	
	public function setBulkUploadId ( $bulkUploadId )	{		$this->putInCustomData ( "bulk_upload_id" , $bulkUploadId );	}
	public function getBulkUploadId (  )	{		return $this->getFromCustomData( "bulk_upload_id" );	}
	
	public function setModerate ( $should_moderate )	{		$this->putInCustomData ( "moderate" , $should_moderate );	}
	public function getModerate (  )	{		return $this->getFromCustomData( "moderate" );	}
	
	public function resetUpdateWhenReady ( )	{		$this->putInCustomData ( "current_kshow_version" , null );	}
	public function setUpdateWhenReady ( $current_kshow_version )	{		$this->putInCustomData ( "current_kshow_version" , $current_kshow_version );	}

	public function getUpdateWhenReady (  )	{		return $this->getFromCustomData( "current_kshow_version" );	}

	// will be set if the entry has a real download path (
	public function setHasDownload ( $v )	{	$this->putInCustomData ( "hasDownload" , $v);	}
	public function getHasDownload (  )		{	return $this->getFromCustomData( "hasDownload" );	}
	

	public function setCount ( $v )	{	$this->putInCustomData ( "count" , $v );	}
	public function getCount (  )		{	return $this->getFromCustomData( "count" );	}

	public function setCountDate ( $v )	{	$this->putInCustomData ( "count_date" , $v );	}
	public function getCountDate (  )		{	return $this->getFromCustomData( "count_date" );	}

	protected function setIsmVersion ( $v )	{	$this->putInCustomData ( "ismVersion" , $v );	}
	public function getIsmVersion (  )		{	return (int) $this->getFromCustomData( "ismVersion" );	}
	
	public function setReferenceID  ( $v )	{	$this->putInCustomData ( "referenceID" , $v );	}
	public function getReferenceID (  )		{	return $this->getFromCustomData( "referenceID" );	}
	
	public function setPartnerSortValue ( $v )	{	$this->putInCustomData ( "partnerSortValue" , $v );	}
	public function getPartnerSortValue (  )	{	return (int) $this->getFromCustomData( "partnerSortValue" );	}
	
	public function setReplacementStatus ( $v )	{	$this->putInCustomData ( "replacementStatus" , $v );	}
	public function getReplacementStatus (  )	{	return (int) $this->getFromCustomData( "replacementStatus" );	}
	
	public function setReplacingEntryId ( $v )	{	$this->putInCustomData ( "replacingEntryId" , $v );	}
	public function getReplacingEntryId (  )	{	return $this->getFromCustomData( "replacingEntryId" );	}
	
	public function setReplacedEntryId ( $v )	{	$this->putInCustomData ( "replacedEntryId" , $v );	}
	public function getReplacedEntryId (  )		{	return $this->getFromCustomData( "replacedEntryId" );	}

	public function setIsTemporary ( $v )	{	$this->putInCustomData ( "isTemporary" , $v );	}
	public function getIsTemporary (  )		{	return $this->getFromCustomData( "isTemporary", null, false );	}

	public function setReplacementOptions ($v)  {	$this->putInCustomData ( "replacementOptions" , $v );	}
	public function getReplacementOptions (  )	{	return $this->getFromCustomData( "replacementOptions", null, new kEntryReplacementOptions() );	}

	public function setIsRecordedEntry( $v )                { $this->putInCustomData( "isRecordedEntry" , $v ); }
	public function getIsRecordedEntry()                    { return $this->getFromCustomData( "isRecordedEntry", null, false ); }

	public function setRedirectEntryId ( $v )	{	$this->putInCustomData ( "redirectEntryId" , $v );	}
	public function getRedirectEntryId (  )		{	return $this->getFromCustomData( "redirectEntryId" );	}

	public function setIsTrimDisabled ( $v )	{	$this->putInCustomData ( "isTrimDisabled" , $v );	}
	public function getIsTrimDisabled (  )		{	return $this->getFromCustomData( "isTrimDisabled" );	}

	public function setStreams ( $v )	{	$this->putInCustomData ( "streams" , $v );	}
	public function getStreams(  )		{	return $this->getFromCustomData( "streams" );	}
	
	public function setRecordedEntrySegmentCount ( $v )	{	$this->putInCustomData ( "recordedEntrySegmentCount" , $v );	}
	public function getRecordedEntrySegmentCount(  )		{	return $this->getFromCustomData( "recordedEntrySegmentCount", null, 0 );	}

	public function setTempTrimEntry ($v)	    { $this->putInCustomData ( "tempTrimEntry" , $v );	}
	public function getTempTrimEntry ()		{	return $this->getFromCustomData( "tempTrimEntry", null, false );	}
	
	// indicates that thumbnail shouldn't be auto captured, because it already supplied by the user
	public function setCreateThumb ( $v, thumbAsset $thumbAsset = null)		
	{	
		if(!$v)
			assetPeer::removeThumbAssetDeafultTags($this->getId(), $thumbAsset ? $thumbAsset->getId() : null); 
		
		$this->putInCustomData ( "createThumb" , (bool) $v );	
	}
	public function getCreateThumb (  )			{	return (bool) $this->getFromCustomData( "createThumb" ,null, true );	}
	
	// indicates that duration shouldn't be auto calculated, because it already supplied by the user
	public function setCalculateDuration ( $v )		{	$this->putInCustomData ( "calculateDuration" , (bool) $v );	}
	public function getCalculateDuration (  )			{	return (bool) $this->getFromCustomData( "calculateDuration" ,null, true );	}
	
	public function setThumbBitrate ( $v )		{	$this->putInCustomData ( "thumbBitrate" , $v );	}
	public function getThumbBitrate (  )			{	return $this->getFromCustomData( "thumbBitrate", null, 0 );	}
	
	public function setThumbHeight ( $v )		{	$this->putInCustomData ( "thumbHeight" , $v );	}
	public function getThumbHeight (  )			{	return $this->getFromCustomData( "thumbHeight", null, 0 );	}
	
	public function setThumbGrabbedFromAssetId ( $v ){	$this->putInCustomData ( "thumbGrabbedFromAssetId" , $v );	}
	public function getThumbGrabbedFromAssetId (  )	{	return $this->getFromCustomData( "thumbGrabbedFromAssetId", null, null );	}
	
	public function setMarkedForDeletion ( $v )	{	$this->putInCustomData ( "markedForDeletion" , (bool) $v );	}
	public function getMarkedForDeletion (  )	{	return (bool) $this->getFromCustomData( "markedForDeletion" ,null, false );	}
	
	public function setRootEntryId($v)	{	$this->putInCustomData("rootEntryId", $v); }
	
	public function setParentEntryId($v)	{ $this->putInCustomData("parentEntryId", $v); }
	public function getParentEntryId() 		{ return $this->getFromCustomData( "parentEntryId", null, null ); }

	public function setSourceEntryId($v)	{ $this->putInCustomData("sourceEntryId", $v); }
	public function getSourceEntryId() 		{ return $this->getFromCustomData( "sourceEntryId", null, null ); }

	public function setReachedMaxRecordingDuration ( $v )	{	$this->putInCustomData ( "reachedMaxRecordingDuration" , (bool) $v );	}
	public function getReachedMaxRecordingDuration() 	{	return (bool) $this->getFromCustomData( "reachedMaxRecordingDuration" ,null, false );	}

	public function setOriginalCreationDate ( $v )	{	$this->putInCustomData ( "originalCreationDate" , $v);	}
	public function getOriginalCreationDate() 	{	return $this->getFromCustomData( "originalCreationDate", null, null);	}

	public function setApplication ( $v )	{	$this->putInCustomData ( "application" , $v);	}
	public function getApplication() 	{	return $this->getFromCustomData( "application", null, null);	}

	public function setApplicationVersion ( $v )	{	$this->putInCustomData ( "applicationVersion" , $v);	}
	public function getApplicationVersion() 	{	return $this->getFromCustomData( "applicationVersion", null, null);	}

	public function setSourceVersion( $v ) {	$this->putInCustomData ( "sourceVersion" , $v);	}
	public function getSourceVersion() 	{	return $this->getFromCustomData( "sourceVersion", null, null);	}
	
	public function setBlockAutoTranscript($v)  {$this->putInCustomData('blockAutoTranscript', $v);}
	public function getBlockAutoTranscript()    {return $this->getFromCustomData('blockAutoTranscript', null, false);}
	
	public function setRecycledAt($v)
	{
		$this->putInCustomData('recycledAt', $v);
	}
	
	public function getRecycledAt()
	{
		return $this->getFromCustomData('recycledAt', null, null);
	}
	
	public function setPreviousDisplayInSearchStatus($v)
	{
		$this->putInCustomData('previousDisplayInSearchStatus', $v);
	}
	
	public function getPreviousDisplayInSearchStatus()
	{
		return $this->getFromCustomData('previousDisplayInSearchStatus', null, null);
	}
	
	
	public function getParentEntry()
	{
		if(!$this->getParentEntryId())
		{
			KalturaLog::info("Attempting to get parent entry of entry " . $this->getId() . " but parent does not exist, returning original entry");
			return $this;
		}
		
		$parentEntry = entryPeer::retrieveByPK($this->getParentEntryId());
		
		return $parentEntry;
	}
	
	public function getSecurityParentId()
	{
		// avoid the permission query if there is no parent
		if (!$this->getParentEntryId())
		{
			return null;
		}

		if (PermissionPeer::isValidForPartner(PermissionName::FEATURE_DISABLE_PARENT_ENTRY_SECURITY_INHERITANCE, $this->getPartnerId()))
		{
			return null;
		}

		return $this->getParentEntryId();
	}

	//If entry has parent we need to retrieve access control from the parent
	public function getaccessControl(PropelPDO $con = null)
	{
		if(!$this->getSecurityParentId())
			return accessControlPeer::retrieveByPK($this->access_control_id, $con);
			
		$parentEntry = $this->getParentEntry();
		if($parentEntry)
			return $parentEntry->getaccessControl($con);
			
		return null;
	}
	
	public function getSphinxMatchOptimizations() {
		$objectName = $this->getIndexObjectName();
		return $objectName::getSphinxMatchOptimizations($this);
	}
	
	public function setCreatorPuserId( $v )		{	$this->putInCustomData ( "creatorPuserId" , $v );	}
	
	public function getCreatorPuserId ( )
	{
		$creatorPuserId = $this->getFromCustomData( "creatorPuserId", null, null );

		if(is_null($creatorPuserId))
		{
			$creatorPuserId =  $this->getPuserId();
		}
		
		return $creatorPuserId;
	}
	
	public function getCreatorKuserId ( )
	{
		$creatorKuserId = $this->getFromCustomData( "creatorKuserId", null, null );

		if(is_null($creatorKuserId))
			return $this->getKuserId();
		else
			return $creatorKuserId;
	}
	
	public function setEntitledPusersEdit($v)
	{
		$entitledUserPuserEdit = array();
		
		$v = !is_null($v) ? trim($v) : '';
		if($v == '')
		{
			$this->putInCustomData ( "entitledUserPuserEdit" , serialize($entitledUserPuserEdit) );
			return;
		}
		
		$entitledPusersEdit = explode(',',$v);

		foreach ($entitledPusersEdit as $puserId)
		{
			$puserId = trim($puserId);
			if ( $puserId === '' )
			{
				continue;
			}

			$partnerId = kCurrentContext::$partner_id ? kCurrentContext::$partner_id : kCurrentContext::$ks_partner_id;
			$kuser = kuserPeer::getActiveKuserByPartnerAndUid($partnerId, $puserId);
			if (!$kuser)
				throw new kCoreException('Invalid user id', kCoreException::INVALID_USER_ID);

			$entitledUserPuserEdit[$kuser->getId()] = $kuser->getPuserId();
		}

		$this->putInCustomData ( "entitledUserPuserEdit" , serialize($entitledUserPuserEdit) );
	}
	
	public function getEntitledKusersEdit()
	{
		return implode(',', array_keys($this->getEntitledUserPuserEditArray()));
	}

	public function getEntitledKusersEditArray()
	{
		return array_keys($this->getEntitledUserPuserEditArray());
	}
	
	public function getEntitledKusersView()
	{
		return implode(',', array_keys($this->getEntitledPusersViewArray()));
	}

	public function getEntitledKusersViewArray()
	{
		return array_keys($this->getEntitledPusersViewArray());
	}
	
	public function getEntitledPusersEdit()
	{
		return implode(',', $this->getEntitledUserPuserEditArray());
	}
	
	public function setEntitledPusersView($v)
	{
		$entitledUserPuserView = array();
		if(is_null($v) || trim($v) == '')
		{
			$this->putInCustomData ( "entitledUserPuserView" , serialize($entitledUserPuserView) );
			return;
		}
		
		$entitledPusersView = explode(',', trim($v));
		$partnerId = kCurrentContext::$partner_id ? kCurrentContext::$partner_id : kCurrentContext::$ks_partner_id;
		foreach ($entitledPusersView as $puserId)
		{
			$puserId = trim($puserId);
			if ( $puserId === '' )
			{
				continue;
			}

			$kuser = kuserPeer::getActiveKuserByPartnerAndUid($partnerId, $puserId);
			if (!$kuser)
				throw new kCoreException('Invalid user id', kCoreException::INVALID_USER_ID);
			
			$entitledUserPuserView[$kuser->getId()] = $kuser->getPuserId();
		}
				
		$this->putInCustomData ( "entitledUserPuserView" , serialize($entitledUserPuserView) );
	}
	
	public function isOwnerActionsAllowed($kuserId)
	{
		$ownerKuserId = $this->getKuserId();
		if($kuserId == $ownerKuserId)
			return true;

		$kuserKGroupIds = KuserKgroupPeer::retrieveKgroupIdsByKuserIds(array($kuserId));
		return in_array($ownerKuserId, $kuserKGroupIds);
	}
	
	
	private function isEntitledKuser ($kuserId, $entitledKuserArray)
	{
		if(in_array(trim($kuserId), $entitledKuserArray))
			return true;

		$kuserKGroupIds = KuserKgroupPeer::retrieveKgroupIdsByKuserIds(array($kuserId));
		foreach($kuserKGroupIds as $groupKId)
			if(in_array($groupKId, $entitledKuserArray))
				return true;

		return $this->isOwnerActionsAllowed($kuserId);
	}
	
	public function isEntitledKuserEdit($kuserId)
	{
		$entitledKuserArray = array_keys($this->getEntitledUserPuserEditArray());
		
		return $this->isEntitledKuser($kuserId, $entitledKuserArray);
	}
	
	public function isEntitledKuserView($kuserId)
	{
		$entitledKuserArray = array_keys($this->getEntitledPusersViewArray());

		return $this->isEntitledKuser($kuserId, $entitledKuserArray);
	}

	private function getEntitledUserPuserEditArray()
	{
		$entitledUserPuserEdit = $this->getFromCustomData( "entitledUserPuserEdit", null, 0 );
		if (!$entitledUserPuserEdit)
			return array();

		return unserialize($entitledUserPuserEdit);
	}

	public function setEntitledPusersPublish($v)
	{
		$partnerId = kCurrentContext::$partner_id ? kCurrentContext::$partner_id : kCurrentContext::$ks_partner_id;
		$entitledUserPuserPublish = array();
		
		if(is_null($v) || trim($v) == '')
		{
			$this->putInCustomData ( "entitledUserPuserPublish" , serialize($entitledUserPuserPublish) );
			return;
		}
		
		$entitledPusersPublish = explode(',', trim($v));
		if(!count($entitledPusersPublish))
			return;
			
		foreach ($entitledPusersPublish as $puserId)
		{
			$puserId = trim($puserId);
			if ( $puserId === '' )
			{
				continue;
			}
			
			$kuser = kuserPeer::getActiveKuserByPartnerAndUid($partnerId, $puserId);
			if (!$kuser)
				throw new kCoreException('Invalid user id', kCoreException::INVALID_USER_ID);
			
			$entitledUserPuserPublish[$kuser->getId()] = $kuser->getPuserId();
		}
		$this->putInCustomData ( "entitledUserPuserPublish" , serialize($entitledUserPuserPublish) );
	}
	
	private function getEntitledPusersPublishArray()
	{
		$entitledUserPuserPublish = $this->getFromCustomData( "entitledUserPuserPublish", null, 0 );
		if (!$entitledUserPuserPublish)
			return array();
		
		return unserialize($entitledUserPuserPublish);
	}
	
	private function getEntitledPusersViewArray()
	{
		$entitledUserPuserView = $this->getFromCustomData( "entitledUserPuserView", null, 0 );
		if (!$entitledUserPuserView)
			return array();
		
		return unserialize($entitledUserPuserView);
	}
	
	public function getEntitledKusersPublish()
	{
		return implode(',', array_keys($this->getEntitledPusersPublishArray()));
	}

	public function getEntitledKusersPublishArray()
	{
		$entitledUserPuserPublish = $this->getFromCustomData( "entitledUserPuserPublish", null, 0 );
		if(!$entitledUserPuserPublish)
			return array();

		return array_keys(unserialize($entitledUserPuserPublish));
	}
	
	public function getEntitledPusersPublish()
	{
		return implode(',', $this->getEntitledPusersPublishArray());
	}
	
	public function getEntitledPusersView()
	{
		return implode(',', $this->getEntitledPusersViewArray());
	}
	
	public function isEntitledKuserPublish($kuserId, $useUserGroups = true)
	{
		$entitledKusersArray = explode(',', $this->getEntitledKusersPublish());
		if(in_array(trim($kuserId), $entitledKusersArray))
			return true;

		if($useUserGroups == true)
		{
			$kuserKGroupIds = KuserKgroupPeer::retrieveKgroupIdsByKuserIds(array($kuserId));
			foreach($kuserKGroupIds as $groupKId)
			{
				if(in_array($groupKId, $entitledKusersArray))
					return true;
			}
		}

		return false;	
	}

	public function getRoots()
	{
		// the prefix required becaue combined sphinx match is rrequired,
		// only negative expression won't work such as '@roots -entry',
		// the prefix will enable '@roots prefix -entry'
		$ret = array(entry::ROOTS_FIELD_PREFIX);
		 
		if($this->getBulkUploadId())
			$ret[] = entry::ROOTS_FIELD_BULK_UPLOAD_PREFIX . ' ' . $this->getBulkUploadId();
			
		if($this->getRootEntryId() != $this->getId())
			$ret[] = entry::ROOTS_FIELD_ENTRY_PREFIX . ' ' . $this->getRootEntryId();
			
		if($this->getParentEntryId())
			$ret[] = entry::ROOTS_FIELD_PARENT_ENTRY_PREFIX . '_' . $this->getParentEntryId();
		
		return implode(',', $ret);
	}
	
	public function getRootEntryId($deep = false)
	{
		$rootEntryId = $this->getFromCustomData("rootEntryId", null, null);
		if(is_null($rootEntryId))
			return $this->getId();
			
		if(!$deep)
			return $rootEntryId;
			
		$rootEntry = entryPeer::retrieveByPKNoFilter($rootEntryId);
		if($rootEntry)
			$rootEntryId = $rootEntry->getRootEntryId($deep);

		return $rootEntryId;
	}
	
	public function getCustomDataRootEntryId()
	{
		return $this->getFromCustomData("rootEntryId", null, null);
	}
	
	public function setDynamicFlavorAttributes(array $v)
	{
		$this->putInCustomData("dynamicFlavorAttributes", serialize($v));
	}
	
	public function setOperationAttributes(array $operationAttributes)
	{
		$this->putInCustomData("operationAttributes", $operationAttributes);
	}
	
	public function getOperationAttributes()
	{
		return $this->getFromCustomData("operationAttributes", null, array());
	}

	public function setFlowType($v)
	{
		$this->putInCustomData('flowType', $v);
	}

	public function getFlowType()
	{
		return $this->getFromCustomData('flowType');
	}
	
	public function getDynamicFlavorAttributes()
	{
		$value = $this->getFromCustomData("dynamicFlavorAttributes");
		if(!$value)
			return array();
			
		try
		{
			$arr = unserialize($value);
			if(!is_array($arr))
				return array();
				
			return $arr;
		}
		catch(Exception $e)
		{
			return array();
		}
	}
	
	// privacySettyings is an alias for permissions
	public function getPrivacySettings ()	{		return $this->getPermissions();	}
	public function setPrivacySettings ( $v )	{		return $this->setPermissions( $v );	}

	public function incrementIsmVersion (  )
	{
		$newVersion = kFileSyncUtils::calcObjectNewVersion($this->getId(), $this->getIsmVersion(), FileSyncObjectType::ENTRY, kEntryFileSyncSubType::ISM);

		$this->setIsmVersion($newVersion);
		
		return $newVersion;
	}
	
	public function getHeight()
	{
		// return null if media_type is NOT image OR video
		if ( $this->getMediaType() != self::ENTRY_MEDIA_TYPE_IMAGE && $this->getMediaType() != self::ENTRY_MEDIA_TYPE_VIDEO ) return null;
		return $this->getFromCustomData( "height" );
	}

	public function getWidth()
	{
		// return null if media_type is NOT image OR video
		if ( $this->getMediaType() != self::ENTRY_MEDIA_TYPE_IMAGE && $this->getMediaType() != self::ENTRY_MEDIA_TYPE_VIDEO ) return null;
		return $this->getFromCustomData( "width" );
	}
	
	public function getMediaTypeName()
	{
		$type = $this->getMediaType();
		if(isset(self::$mediaTypeNames[$type]))
			return self::$mediaTypeNames[$type];
			
		return null;
	}

	public  function setDimensions (  $width , $height )
	{
		$this->putInCustomData( "height" , $height );
		$this->putInCustomData( "width" , $width );
	}
	
	public function setDimensionsIfBigger ($width, $height)
	{
		if( (is_null($this->getFromCustomData("width")) && is_null($this->getFromCustomData("height"))) 
			|| ($width>$this->getFromCustomData("width") && $height>$this->getFromCustomData("height")) )
			$this->setDimensions($width, $height);
	}
	
	public function updateDimensions ( )
	{
		if ( $this->getMediaType() == self::ENTRY_MEDIA_TYPE_IMAGE)
			$this->updateImageDimensions();
		else if ($this->getMediaType() == self::ENTRY_MEDIA_TYPE_VIDEO )
			$this->updateVideoDimensions();
	}
	
	public function updateImageDimensions ( )
	{
		$data = kFileSyncUtils::file_get_contents($this->getSyncKey(kEntryFileSyncSubType::DATA));
		list ($width, $height) = myFileConverter::getImageDimensionsFromString($data);
		if ( $width )
		{
			$this->putInCustomData( "height" , $height );
			$this->putInCustomData( "width" , $width );
		}
		return array($width, $height);
	}
	
	public function updateVideoDimensions ( )
	{
		$asset = assetPeer::retrieveHighestBitrateByEntryId($this->getId());
		if(!$asset)
			return array($this->getFromCustomData('width'), $this->getFromCustomData('height'));
			
		$syncKey = $asset->getSyncKey(asset::FILE_SYNC_ASSET_SUB_TYPE_ASSET);
		$dataPath = kFileSyncUtils::getLocalFilePathForKey($syncKey);
		list ( $width , $height ) = $arr = myFileConverter::getVideoDimensions( $dataPath );
		
		if ( $width )
		{
			$this->putInCustomData( "height" , $height );
			$this->putInCustomData( "width" , $width );
		}
		return $arr;
	}
	
	public function getDisplayScope()
	{
		if( $this->getDisplayInSearch() == 0 ) return "";
		if( $this->getDisplayInSearch() == 1 ) return "_PRIVATE_";
		if ($this->getDisplayInSearch () >= 2)
			return "_KN_";
	}
	
	protected function incModerationCount()
	{
		$this->setModerationCount($this->getModerationCount() + 1);
	}
		
	/**
	 * @return partner
	 */
	public function getPartner()	{		return PartnerPeer::retrieveByPK( $this->getPartnerId() );	}
	
	public function getSubpId()
	{
		return ($this->subp_id != null ? $this->subp_id : 0);
	}
		
	public function getPartnerLandingPage ()
	{
		if ( ! $this->getPartner() ) return null;
		$url = $this->getPartner()->getLandingPage();
		if ( $url )
		{
			if ( strpos ( $url , '{id}'  ) > 0 )
			{
				return str_replace( '{id}' , $this->getId() , $url );
			}
			else
				return $url . $this->getId();
//			return objectWrapperBase::parseString ( $url , $this );//
		}
		else
		{
			return null;
		}
	}

	public function getUserLandingPage ()
	{
		if ( ! $this->getPartner() ) return null;
		$url = $this->getPartner()->getUserLandingPage();
		if ( $url )
		{
			if ( strpos ( $url , '{uid}'  ) > 0 )
			{
				return str_replace( '{uid}' , $this->getPuserId() , $url );
			}
			else
				return $url . $this->getPuserId();
//		return objectWrapperBase::parseString ( $url , $this );//
		}
		else
		{
			return null;
		}
	}
	
	public function getConversionProfile()
	{
		$conversion_profile = $this->getConversionQuality();
		if ( $conversion_profile )
		{
			return ConversionProfilePeer::retrieveByPK( $conversion_profile );
		}
		return null;
	}
	
	public function getRankAsFloat()
	{
		return (float)($this->getRank() / 1000);
	}

	
	public function setSecurityPolicy ( $security_policy )	{		$this->putInCustomData ( "security_policy" , $security_policy );	}
	public function getSecurityPolicy (  )	{		return $this->getFromCustomData( "security_policy" );	}

	public function setStorageSize ( $storage_size )	{		$this->putInCustomData ( "storage_size" , $storage_size );	}
	public function getStorageSize (  )	{		return $this->getFromCustomData( "storage_size" );	}

	public function setExtStorageUrl( $v ) { $this->putInCustomData("ext_storage_url", $v); }
	public function getExtStorageUrl() { return $this->getFromCustomData("ext_storage_url"); }

	public function setCacheFlavorVersion($v)       {$this->putInCustomData("cache_flavor_version", $v);}
	public function getCacheFlavorVersion()       {return $this->getFromCustomData("cache_flavor_version");}
	
	public function setCacheThumbnailVersion($v)       {$this->putInCustomData("cache_thumb_version", $v);}
	public function getCacheThumbnailVersion()       {return $this->getFromCustomData("cache_thumb_version");}
	
	private $m_puser_id = null;
	public function tempSetPuserId ( $puser_id )
	{
		$this->m_puser_id = $puser_id;
	}
	public function getPuserId()
	{
		if (kCurrentContext::isApiV3Context())
			return parent::getPuserId();
			
		$puser_id = $this->getFromCustomData( "puserId" );
		if ( $this->m_puser_id ) // ! $puser_id )
		{
			return $this->m_puser_id;
		}
		else
		{
			if (  $this->getKuserId() )
			{
				$puser_id =	PuserKuserPeer::getPuserIdFromKuserId ( $this->getPartnerId(), $this->getKuserId() );
				$this->putInCustomData( "puserId" , $puser_id );
				$this->m_puser_id = $puser_id;
			}
		}
		return $puser_id;
	}

	public function getContributorScreenName()
	{
		if ($this->getCredit())
			return $this->getCredit();
		else
		{
			return $this->getUserScreenName();
		}
	}

	// will return the user's screen name - not the credit even if exists
	public function getUserScreenName()
	{
		$kuser = $this->getkuser();
		
		return ( $kuser ? $kuser->getScreenName() : "" );
	}
		
	public function getSearchText()
	{
		$displayInSearch = $this->getDisplayInSearch();
		
		$words = "";
		$fields_to_use = $this->getColumnNames();
		foreach ( $fields_to_use  as $field )
		{
			$field_str = $this->getByName ( $field , BasePeer::TYPE_FIELDNAME );
			$words .= " " . $field_str;
		}
			
		$extra_invisible_data = null;
			
		$extra_invisible_data = "_MEDIA_TYPE_" . $this->getMediaType();
		$type = $this->getType();
		// add the SEARCH_ENTRY_TYPE_RC to the words
		if ( $type == entryType::MIX )
			$extra_invisible_data .= " " . mySearchUtils::SEARCH_ENTRY_TYPE_RC ;

		$prepared_text = mySearchUtils::prepareSearchText ( $words );
			
		$partner_id = $this->getPartnerId();
		
		// if res == 1 - only for partner , if == 2 - also for kaltura network
		return mySearchUtils::addPartner($partner_id, $prepared_text, $displayInSearch, $extra_invisible_data);
	}

	public function getTypeAsString()
	{
		$t = $this->getMediaType();
		if ( $t == self::ENTRY_MEDIA_TYPE_AUDIO ) return "audio";
		if ( $t == self::ENTRY_MEDIA_TYPE_VIDEO ) return "video";
		if ( $t == self::ENTRY_MEDIA_TYPE_IMAGE ) return "image";
		if ( $t == self::ENTRY_MEDIA_TYPE_SHOW ) return "roughcut";
		return "";
	}
	
	public function getFileSize()
	{
		return 0; // temp fix
		$dataFileKey = $this->getSyncKey(kEntryFileSyncSubType::DATA);
		$fileSync = kFileSyncUtils::getLocalFileSyncForKey($dataFileKey);
		if($fileSync && $fileSync->getStatus() == FileSync::FILE_SYNC_STATUS_READY) return $fileSync->getFileSize();
		return "";
	}
	 
	 
	// -- retrieveDataContentByGet --
	// disable/enable sending data content for data entries in API v3 (for binary-data-entry use-case)
	public function setRetrieveDataContentByGet( $v ) { $this->putInCustomData("retrieveDataContentByGet", $v); }
	public function getRetrieveDataContentByGet() { return $this->getFromCustomData("retrieveDataContentByGet", "", 1); }
	
	
	// ------------------------------------------------------------
	// setters & gettes for entry when callnig addentry
	private $m_url;
	public function getUrl() { return $this->m_url; }
	public function setUrl ( $v ) { $this->m_url = $v ;}

	private $m_thumb_url;
	public function getThumbUrl() { return $this->m_thumb_url; }
	public function setThumbUrl ( $v ) { $this->m_thumb_url = $v ;}
	
	private $m_filename;
	public function getFilename() { return $this->m_filename; }
	public function setFilename ( $v ) { $this->m_filename= $v ;}

	private $m_realFilename;
	public function getRealFilename() { return $this->m_realFilename; }
	public function setRealFilename ( $v ) { $this->m_realFilename= $v ;}
	
	private $m_mediaId;
	public function getMediaId() { return $this->m_mediaId; }
	public function setMediaId ( $v ) { $this->m_mediaId= $v ;}
	// ------------------------------------------------------------
	
	private $m_thumbOffset;
	
	public function getThumbOffset($default_offset = 3)
	{
		$offset = $this->getFromCustomData ( "thumb_offset" );
		if(is_null($offset))
		{
			// get from partner if null on entry:
			$partner = $this->getPartner();
			$offset = $partner ? $partner->getDefThumbOffset() : $default_offset;
			if(is_null($offset) || $offset === false)
				return $default_offset;
		}
		
		return $offset;
	}
	
	public function setThumbOffset ( $v ) { 	$this->putInCustomData ( "thumb_offset" , $v ); }

	public function getBestThumbOffset( $default_offset = 3 )
	{
		if ($default_offset === null)
			$default_offset = 3;
			
		$offset = $this->getThumbOffset();
		$duration = $this->getLengthInMsecs();
		if (!$duration && $this->getSourceType() == EntrySourceType::KALTURA_RECORDED_LIVE)
			$duration = $this->getRecordedLengthInMsecs();
		
		if(!$offset || $offset < 0)
			$offset = $default_offset;

		return max(0 ,min($offset, $duration));
	}
	
	public function getHasRealThumb()
	{
		$thumb = $this->getThumbnail();
		return myContentStorage::isTemplate( $thumb );
	}
	
	public function setKuserId($v)
	{
		// if we set the kuserId when not needed - this causes the kuser object to be reset (even if the joinKuser was done properly)
		if ( self::getKuserId() == $v )  // same value - don't set for nothing
			return;

		$this->setCreatorKuserPuserIdMigration();
		
		parent::setKuserId($v);
		
		$kuser = $this->getKuser();
		if ($kuser)
			$this->setPuserId($kuser->getPuserId());
	}
	
	/**
	 *
	 * Lazy migration for old entries for cases where creator Kuser and Puser id
	 * wasn't initialized
	 */
	private function setCreatorKuserPuserIdMigration()
	{
		$creatorKuserId = $this->getFromCustomData( "creatorKuserId", null, null );
		if (is_null($creatorKuserId) && !is_null($this->getKuserId()))
		{
			$this->setCreatorKuserId($this->getKuserId());
		}
	}
	
	public function setCreatorKuserId($v)
	{
		$this->creator_kuser_id = $v;
		// if we set the kuserId when not needed - this causes the kuser object to be reset (even if the joinKuser was done properly)
		if ( $this->getFromCustomData( "creatorKuserId", null, null ) == $v )  // same value - don't set for nothing
			return;

		$this->putInCustomData ( "creatorKuserId" , $v );
		$kuser = kuserPeer::retrieveByPk($v);
		if ($kuser)
		{
			$this->setCreatorPuserId($kuser->getPuserId());
		}
	}
	
	public function getNewCategories()
	{
		return $this->new_categories;
	}
	
	public function getNewCategoriesIds()
	{
		return $this->new_categories_ids;
	}
	
	public function getOldCategories()
	{
		return $this->old_categories;
	}
	
	public function syncCategories()
	{
		if (!$this->is_categories_modified)
			return;
		
		if(!kEntitlementUtils::getEntitlementEnforcement() || !kEntitlementUtils::isKsPrivacyContextSet())
			categoryEntryPeer::syncEntriesCategories($this, $this->is_categories_names_modified);
		
		parent::save ();
		$this->is_categories_modified = false;
	}
	
	public function parentSetCategories ( $categories )
	{
		parent::setCategories ( $categories );
	}
	
	public function parentSetCategoriesIds ( $categoriesIds )
	{
		parent::setCategoriesIds ( $categoriesIds );
	}
	
	public function isScheduledNow($time = null)
	{
		if(is_null($time))
		{
			$time = time();

			// entry scheduling status changes within 24H
			if (($this->getStartDate() && abs($this->getStartDate(null) - time()) <= 86400) ||
				($this->getEndDate() &&   abs($this->getEndDate(null) - time())   <= 86400))
			{
				kApiCache::setConditionalCacheExpiry(600);
			}
			
			// entry scheduling status changes within 10 min
			if (($this->getStartDate() && abs($this->getStartDate(null) - time()) <= 600) ||
				($this->getEndDate() &&   abs($this->getEndDate(null) - time())   <= 600))
			{
				kApiCache::setExpiry(60);
				kApiCache::setConditionalCacheExpiry(60);
			}
		}
			
		$startDateCheck = (!$this->getStartDate() || $this->getStartDate(null) <= $time);
		$endDateCheck = (!$this->getEndDate() || $this->getEndDate(null) >= $time);
		return $startDateCheck && $endDateCheck;
	}
	
	/**
	 * Force modifiedColumns to be affected even if the value not changed
	 * @param mixed $v string, integer (timestamp), or DateTime value.  Empty string will
	 *						be treated as NULL for temporal objects.
	 * @return entry The current object (for fluent API support)
	 * @see Baseentry::setUpdatedAt()
	 */
	public function setUpdatedAt($v)
	{
		parent::setUpdatedAt($v);
		if(!in_array(entryPeer::UPDATED_AT, $this->modifiedColumns, false))
			$this->modifiedColumns[] = entryPeer::UPDATED_AT;
			
		return $this;
	}
	
	/**
	 * @see Baseentry::setLengthInMsecs()
	 * Make sure that the set value is positive
	 */
	public function setLengthInMsecs($v)
	{
		if(is_null($v) || $v < 0) // null ot negative
			return;

		if(is_string($v) && !is_numeric($v)) // not numeric
			return;

		if ($this->getIsRecordedEntry())
			$this->setRecordedLengthInMsecs(0);

		return parent::setLengthInMsecs($v);
	}
	
	/* (non-PHPdoc)
	 * @see Baseentry::setAccessControlId()
	 */
	public function setAccessControlId($v)
	{
		if ($v === 0 || $v === -1) // handle 0 and -1 as null
			$v = null;
			
		parent::setAccessControlId($v);
	}
	
	public function syncFlavorParamsIds()
	{
		if($this->getStatus() == entryStatus::DELETED)
		{
			return;
		}
			
		$entryFlavors = assetPeer::retrieveFlavorsByEntryIdAndStatus($this->getId(), null, array(flavorAsset::ASSET_STATUS_READY));
		if (!$entryFlavors)
		{
			$this->setStatus(entryStatus::NO_CONTENT);
			$this->setFlavorParamsIds(null);
		}
		else
		{
			$flavorParamIdsArray = array();
			/* @var $flavorAsset flavorAsset */
			foreach ($entryFlavors as $flavorAsset) {
				$flavorParamIdsArray[] = $flavorAsset->getFlavorParamsId();
			}
			asort($flavorParamIdsArray);
			$flavorParamIdsArray = array_unique($flavorParamIdsArray);
			$flavorParamIds = implode(",", $flavorParamIdsArray);
			$this->setFlavorParamsIds($flavorParamIds);
		}
	}
	
	public function getRawDownloadUrl()
	{
		$finalPath = "/downloadUrl?url=".
			myPartnerUtils::getUrlForPartner($this->getPartnerId(), $this->getSubpId()).
			"/raw/entry_id/".
			$this->getId();
		
		$downloadUrl = myPartnerUtils::getCdnHost($this->getPartnerId()).$finalPath;
		
		return $downloadUrl;
	}
	
	public function setEndDate($date)
	{
		if (!is_null($date) && $date > 0)
		{
			parent::setEndDate($date);
		}
		else
		{
			parent::setEndDate(null);
		}
	}
	
	public function setStartDate($date)
	{
		if (!is_null($date) && $date > 0)
		{
			parent::setStartDate($date);
			parent::setAvailableFrom($date);
		}
		else // restore availableFrom from the createdAt
		{
			parent::setStartDate(null);
			parent::setAvailableFrom(parent::getCreatedAt());
		}
	}
	
	public function setCreatedAt($date)
	{
		parent::setCreatedAt($date);
		if (is_null($this->getAvailableFrom())) // only if the availableFrom was not set yet
			parent::setAvailableFrom($date);
	}
	
// ----------- Extra object connections ----------------
	public function getBatchJobs()
	{
		return BatchJobPeer::retrieveByEntryId( $this->getId() );
	}
	
	public function getRoughcutId()
	{
		$kshow = $this->getKshow();
		return $kshow ? $kshow->getShowEntryId() : null;
	}
	
	// get all related roughcuts where this entry appears
	public function getRoughcuts()
	{
		return roughcutEntry::getAllRoughcuts( $this->getId() );
	}
	
// ----------- Extra object connections ----------------
	/**
	 * Code to be run before updating the object in database
	 * @param PropelPDO $con
	 * @return boolean
	 */
	public function preUpdate(PropelPDO $con = null)
	{
		if(!self::$allow_override_read_only_fields || !in_array(entryPeer::UPDATED_AT, $this->modifiedColumns, false))
		{
			return parent::preUpdate($con);
		}

		$currUpdatedAt = $this->getUpdatedAt(null);
		$ret = parent::preUpdate($con);
		$this->setUpdatedAt($currUpdatedAt);
		return $ret;
	}

	/**
	 * Code to be run before updating the object in database
	 * @param PropelPDO $con
	 * @return bloolean
	 */
	public function preSave(PropelPDO $con = null)
	{
		if($this->customDataValueHasChanged('parentEntryId'))
		{
			$parentEntry = $this->getParentEntry();
			if($parentEntry->getId() != $this->getId() && $parentEntry->getPuserId() != $this->getPuserId())
			{
				if(!in_array($parentEntry->getPuserId(), $this->getEntitledUserPuserEditArray()))
					$this->setEntitledPusersEdit(implode(",", array_merge($this->getEntitledUserPuserEditArray(), array($parentEntry->getPuserId()))));
				
				if(!in_array($parentEntry->getPuserId(), $this->getEntitledPusersPublishArray()))
					$this->setEntitledPusersPublish(implode(",", array_merge($this->getEntitledPusersPublishArray(), array($parentEntry->getPuserId()))));

				if(!in_array($parentEntry->getPuserId(), $this->getEntitledPusersViewArray()))
					$this->setEntitledPusersView(implode(",", array_merge($this->getEntitledPusersViewArray(), array($parentEntry->getPuserId()))));
			}
		}
		
		return parent::preSave($con);
	}
	
	/* (non-PHPdoc)
	 * @see lib/model/om/Baseentry#postUpdate()
	 */
	public function postUpdate(PropelPDO $con = null)
	{
		if(!$this->wasObjectSaved())
			return;
			
		if ($this->alreadyInSave)
			return parent::postUpdate($con);
		
		$objectUpdated = $this->isModified();
		$objectDeleted = false;
		if($this->isColumnModified(entryPeer::STATUS) && $this->getStatus() == entryStatus::DELETED)
			$objectDeleted = true;

		if ($this->isColumnModified(entryPeer::DATA) && $this->getMediaType() == entry::ENTRY_MEDIA_TYPE_IMAGE)
		{
			$partner = $this->getPartner();
			
			if ($partner)
			{
				$dataArr = explode('.',$this->getData());
				$id = $this->getId();
				if ($id == $partner->getAudioThumbEntryId())
				{
					$partner->setAudioThumbEntryVersion($dataArr[0]);
					$partner->save();
				}

				if ($id == $partner->getLiveThumbEntryId())
				{
					$partner->setLiveThumbEntryVersion($dataArr[0]);
					$partner->save();
				}
			}
		}
		
		
		$trackColumns = $this->getTrackColumns();
		
		$changedProperties = array();
		foreach($trackColumns as $namespace => $trackColumn)
		{
			if(is_array($trackColumn))
			{
				if(isset($this->oldCustomDataValues[$namespace]))
				{
					foreach($trackColumn as $trackCustomData)
					{
						if(isset($this->oldCustomDataValues[$namespace][$trackCustomData]))
						{
							$column = $trackCustomData;
							if($namespace)
								$column = "$namespace.$trackCustomData";
								
							$previousValue = $this->oldCustomDataValues[$namespace][$trackCustomData];
							$previousValue = is_scalar ($previousValue) ? $previousValue : $this->getTrackEntryString($namespace, $trackCustomData, $previousValue);
							$newValue = $this->getFromCustomData($trackCustomData, $namespace);
							$newValue = is_scalar ($newValue) ? $newValue : $this->getTrackEntryString($namespace, $trackCustomData, $newValue);
							$changedProperties[] = "$column [{$previousValue}]->[{$newValue}]";
						}
					}
				}
			}
			elseif($this->isColumnModified($trackColumn))
			{
				$column = entryPeer::translateFieldName($trackColumn, BasePeer::TYPE_COLNAME, BasePeer::TYPE_STUDLYPHPNAME);
				$previousValue = $this->getColumnsOldValue($trackColumn);
				$newValue = $this->getByName($trackColumn, BasePeer::TYPE_COLNAME);
				$changedProperties[] = "$column [{$previousValue}]->[{$newValue}]";
			}
		}
		
		if($this->getRedirectEntryId() && array_key_exists('', $this->oldCustomDataValues) && array_key_exists('redirectEntryId', $this->oldCustomDataValues['']))
		{
			$redirectEntry = entryPeer::retrieveByPK($this->getRedirectEntryId());
			if($redirectEntry)
			{
				$redirectEntry->setModerationStatus($this->getModerationStatus());
				$redirectEntry->save();
			}
		}

		if ($this->customDataValueHasChanged('entitledUserPuserEdit') || $this->customDataValueHasChanged('entitledUserPuserPublish') || $this->customDataValueHasChanged('entitledUserPuserView') || $this->isColumnModified(entryPeer::PUSER_ID))
		{
			$childEntries = entryPeer::retrieveChildEntriesByEntryIdAndPartnerId($this->getId(), $this->getPartnerId());
			foreach ($childEntries as $childEntry)
			{
				$this->syncEntitlement($childEntry);
			}
		}

		$ret = parent::postUpdate($con);
	
		if($objectDeleted)
		{
			kEventsManager::raiseEvent(new kObjectDeletedEvent($this));
			myStatisticsMgr::deleteEntry($this);
			
			$trackEntry = new TrackEntry();
			$trackEntry->setEntryId($this->getId());
			$trackEntry->setTrackEventTypeId(TrackEntry::TRACK_ENTRY_EVENT_TYPE_DELETED_ENTRY);
			$trackEntry->setChangedProperties(implode("\n", $changedProperties));
			$trackEntry->setDescription(__METHOD__ . "[" . __LINE__ . "]");
			TrackEntry::addTrackEntry($trackEntry);
			
			//In case this entry has sub streams assigned to it we should delete them as well
			$subStreamEntries = entryPeer::retrieveChildEntriesByEntryIdAndPartnerId($this->id, $this->partner_id);
			foreach ($subStreamEntries as $subStreamEntry)
			{
				myEntryUtils::deleteEntry($subStreamEntry);
			}
		}
			
		if($objectUpdated)
		{
			kEventsManager::raiseEvent(new kObjectUpdatedEvent($this));

			if(!$objectDeleted && count($changedProperties))
			{
				$trackEntry = new TrackEntry();
				$trackEntry->setEntryId($this->getId());
				$trackEntry->setTrackEventTypeId(TrackEntry::TRACK_ENTRY_EVENT_TYPE_UPDATE_ENTRY);
				$trackEntry->setChangedProperties(implode("\n", $changedProperties));
				$trackEntry->setDescription(__METHOD__ . "[" . __LINE__ . "]");
				TrackEntry::addTrackEntry($trackEntry);
			}
		}
		
		return $ret;
	}
	
	private function syncEntitlement(Entry $target)
	{
		$shouldSave = false;
		$entitledPusersEditArray = $this->getEntitledUserPuserEditArray();
		$entitledPusersPublishArray = $this->getEntitledPusersPublishArray();
		$entitledPusersViewArray = $this->getEntitledPusersViewArray();
		
		if(count(array_diff($target->getEntitledUserPuserEditArray(), $entitledPusersEditArray)))
		{
			$entitledPusersEditArray = array_merge($target->getEntitledUserPuserEditArray(), $entitledPusersEditArray);
			$shouldSave = true;
		}
		
		if(count(array_diff($target->getEntitledPusersPublishArray(), $entitledPusersPublishArray)))
		{
			$entitledPusersPublishArray = array_merge($target->getEntitledPusersPublishArray(), $entitledPusersPublishArray);
			$shouldSave = true;
		}

                if(count(array_diff($target->getEntitledPusersViewArray(), $entitledPusersViewArray)))
                {
                        $entitledPusersViewArray = array_merge($target->getEntitledPusersViewArray(), $entitledPusersViewArray);
                        $shouldSave = true;
                }

		if($target->getPuserId() != $this->getPuserId())
		{
			$entitledPusersEditArray = array_merge($entitledPusersEditArray, array($this->getPuserId()));
			$entitledPusersPublishArray = array_merge($entitledPusersPublishArray, array($this->getPuserId()));
			$entitledPusersViewArray = array_merge($entitledPusersViewArray, array($this->getPuserId()));
			$shouldSave = true;
		}
		
		if(!$shouldSave)
			return;
		
		$target->setEntitledPusersEdit(implode(",", array_unique($entitledPusersEditArray)));
		$target->setEntitledPusersPublish(implode(",", array_unique($entitledPusersPublishArray)));
		$target->setEntitledPusersView(implode(",", array_unique($entitledPusersViewArray)));
		$target->save();
	}
	
	/* (non-PHPdoc)
	 * @see lib/model/om/Baseentry#postInsert()
	 */
	public function postInsert(PropelPDO $con = null)
	{
		if(!$this->wasObjectSaved())
			return;
			
		parent::postInsert($con);
	
		if (!$this->alreadyInSave)
			kEventsManager::raiseEvent(new kObjectAddedEvent($this));
	}
	
	/*************** Bulk download functions - start ******************/
	
	/**
	 * Check if the returned asset (flavor asset or entry file sync could be downloaded)
	 *
	 * @param int $flavorParamsId
	 * @return bool
	 */
	public function hasDownloadAsset($flavorParamsId)
	{
		if($this->getType() == entryType::MIX)
			return false; // always create new flattening job
			
		$flavorAsset = assetPeer::retrieveByEntryIdAndParams($this->getId(), $flavorParamsId);
		if ($flavorAsset && $flavorAsset->getStatus() == flavorAsset::FLAVOR_ASSET_STATUS_READY)
			return true;
			
		if(assetPeer::retrieveOriginalByEntryId($this->getId()))
			return false; // flavor asset should be created
			
		// entry file sync should be used (data or download)
		return true;
	}
	
	/**
	 * @param BatchJob $parentJob
	 * @param int $flavorParamsId
	 * @param string $puserId
	 * @return int job id
	 */
	public function createDownloadAsset(BatchJob $parentJob = null, $flavorParamsId, $puserId = null)
	{
		$job = null;
		
		if ($this->getType() == entryType::MIX)
		{
			KalturaLog::err("Entry ID [".$this->getId()."] is of type mix. The batch job for flattening a mix is no longer supported");
		}
		else
		{
			$err = '';
			$job = kBusinessPreConvertDL::decideAddEntryFlavor($parentJob, $this->getId(), $flavorParamsId, $err);
		}
		
		if($job)
			return $job->getId();
			
		return null;
	}
	
	/**
	 * @param int $flavorParamsId
	 * @return string
	 */
	public function getDownloadAssetUrl($flavorParamsId)
	{
		if($flavorParamsId === 0)
        	{
        	    $flavorAsset = assetPeer::retrieveOriginalByEntryId($this->getId());
        	}
        	else
        	{
        	    $flavorAsset = assetPeer::retrieveByEntryIdAndParams($this->getId(), $flavorParamsId);
        	}
	
        	if($flavorAsset && $flavorAsset->getStatus() == flavorAsset::ASSET_STATUS_READY)
        	{
        	    return $flavorAsset->getDownloadUrl();
        	}
	
        	if(assetPeer::retrieveOriginalByEntryId($this->getId()))
        	{
        	    return null; // flavor asset should be created
        	}
	
        	// entry file sync should be used (data or download)
        	return $this->getRawDownloadUrl();
	}
	
	/*************** Bulk download functions - end ******************/
	
	/**
	 * @return int sorting value
	 */
	public function getSortName()
	{
		return kUTF8::str2int64($this->getName());
	}
		
	/**
	 * @return int
	 */
	public function getIndexedId()
	{
		return sprintf('%u', crc32($this->getId()));
	}
	
	/*
	 * get all categoryEntry objects from categoryEntryPeer
	 * to make search query shorter and to solve search problem when category tree is big.
 	 *
	 *	lets say entry belong to 2 categories with these full_ids
	 * 	111>222>333
	 *	111>444
	 * Old categories fields was:
	 *	333,444
	 *
	 * New categories filed:
	 * pc111s2,p111s2,pc222s2,p222s2,pc333s2,c333s2,pc444s2,c444s2
	 *
	 *
	 * so why do we need pc111?
	 * If baseEntry->list with filter categoriesMatchOr= xxxxx you need to search for match pc111s2
	 *
	 * so why do we need p111?
	 * If baseEntry->list with filter categoriesMatchOr= xxxxx you need to search for match p111s2
	 */
	public function getCategoriesEntryIds()
	{
		$allCategoriesEntry = categoryEntryPeer::selectByEntryId($this->getId());
		
		$categoriesEntryStringIndex = array();
		foreach($allCategoriesEntry as $categoryEntry)
		{
			$categoriesEntryStringIndex[] = self::CATEGORY_SEARCH_PERFIX . $categoryEntry->getCategoryId() .
				self::CATEGORY_SEARCH_STATUS . $categoryEntry->getStatus();
			
			//index all category's parents - for easier searchs on entry->list with filter of categoriesMatchOr
			$categoryFullIds = explode(categoryPeer::CATEGORY_SEPARATOR, $categoryEntry->getCategoryFullIds());
			
			foreach($categoryFullIds as $categoryId)
			{
				if(!trim($categoryId))
					continue;
					
				if($categoryId != $categoryEntry->getCategoryId())
				{
					//parent category
					$categoriesEntryStringIndex[] = self::CATEGORY_PARENT_SEARCH_PERFIX . $categoryId .
						self::CATEGORY_SEARCH_STATUS . $categoryEntry->getStatus();
				}
				
				//parent category or category itself
				$categoriesEntryStringIndex[] = self::CATEGORY_OR_PARENT_SEARCH_PERFIX . $categoryId .
						self::CATEGORY_SEARCH_STATUS . $categoryEntry->getStatus();
			}
				
			if($categoryEntry->getStatus() == CategoryEntryStatus::ACTIVE || $categoryEntry->getStatus() == CategoryEntryStatus::PENDING)
				$categoriesEntryStringIndex[] = $categoryEntry->getCategoryId();
		}
		
		$categoriesEntryStringIndex = array_unique($categoriesEntryStringIndex);
		
		return implode(' ', $categoriesEntryStringIndex);
	}

	public function enrichCategoriesEntryIds($originalValue)
	{
		return self::CATEGORIES_INDEXED_FIELD_PREFIX . $this->getPartnerId() . " " .  $originalValue;
	}

	/*
	 * get all categoryEntry objects from categoryEntryPeer
	 */
	public function getCategoriesWithNoPrivacyContext()
	{
		$allCategoriesEntry = categoryEntryPeer::retrieveActiveAndPendingByEntryId($this->getId());
		
		$categoriesWithNoPrivacyContext = array();
		foreach($allCategoriesEntry as $categoryEntry)
		{
			$category = categoryPeer::retrieveByPK($categoryEntry->getCategoryId());

			if($category && $category->getPrivacyContexts() == null)
				$categoriesWithNoPrivacyContext[] = $category;
		}
		
		return $categoriesWithNoPrivacyContext;
	}
	
	/* (non-PHPdoc)
	 * @see IIndexable::getEntryId()
	 */
	public function getEntryId()
	{
		return $this->getId();
	}
	
	public function getIndexObjectName() {
		return "entryIndex";
	}
	
	public function getSphinxIndexName()
	{
		return kSphinxSearchManager::getSphinxIndexName(entryIndex::getObjectIndexName(), entryIndex::getSphinxSplitIndexId($this->getPartnerId(), entryIndex::getObjectName()));
	}
	
	public function getCacheInvalidationKeys()
	{
		return array("entry:id=".strtolower($this->getId()), "entry:partnerId=".strtolower($this->getPartnerId()));
	}
	
	protected function copyTypedDependentFieldFromTemplate($template) 
	{
		$this->setConversionProfileId($template->getConversionProfileId());
	}
	
	public function copyTemplate($template, $copyPartnerId = false)
	{
		if (!$template)
			return null;
		/* entry $template */
		$this->setTemplateEntryId($template->getId());
		$this->setKuserId($template->getKuserId());
		$this->setName($template->getName());
		$this->setTags($template->getTags());
		$this->setAnonymous($template->getAnonymous());
		$this->setSource($template->getSource());
		$this->setSourceId($template->getSourceId());
		$this->setSourceLink($template->getSourceLink());
		$this->setLicenseType($template->getLicenseType());
		$this->setCredit($template->getCredit());
		$this->setScreenName($template->getScreenName());
		$this->setSiteUrl($template->getSiteUrl());
		$this->setPermissions($template->getPermissions());
		$this->setGroupId($template->getGroupId());
		$this->setPartnerData($template->getPartnerData());
		$this->setIndexedCustomData1($template->getIndexedCustomData1());
		$this->setDescription($template->getDescription());
		$this->setAdminTags($template->getAdminTags());
		$this->setPuserId($template->getPuserId());
		$this->setAccessControlId($template->getAccessControlId());
		$this->setEntitledPusersEdit($template->getEntitledPusersEdit());
		$this->setEntitledPusersPublish($template->getEntitledPusersPublish());
		$this->setEntitledPusersView($template->getEntitledPusersView());
		multiLingualUtils::copyMultiLingualValues($this, $template);

		if ($this instanceof $template)
		{
			$this->copyTypedDependentFieldFromTemplate($template);
		}
	
		if($copyPartnerId)
		{
			$this->setPartnerId($template->getPartnerId());
		}
		if(is_null($this->getStartDate()) && !is_null($template->getStartDate()))
		{
			$this->setStartDate($template->getStartDate());
		}
		if(is_null($this->getEndDate()) && !is_null($template->getEndDate()))
		{
			$this->setEndDate($template->getEndDate());
		}
	
		$this->setNew(true);
		$this->setCopiedFrom($template);
		return $this;
	}
	
	public function getDynamicFlavorAttributesForAssetParams($assetParamsId)
	{
	    // set dynamic flavor attributes
		$dynamicFlavorAttributes = array();
		$dynamicEntryAttributes = $this->getDynamicFlavorAttributes(); // dynamic attributes for all entry flavors
	    // if dynamic attributes are set for this specific flavor - use them
		if (isset($dynamicEntryAttributes[$assetParamsId]))
	    {
	        $dynamicFlavorAttributes = $dynamicEntryAttributes[$assetParamsId];
	    }
	    // if not, and the flavor is not source - use attributes defined for flavor id -2
	    else if (isset($dynamicEntryAttributes[flavorParams::DYNAMIC_ATTRIBUTES_ALL_FLAVORS_INDEX]))
	    {
	        $assetParamsDb = assetParamsPeer::retrieveByPK($assetParamsId);
	        if (!$assetParamsDb->hasTag(flavorParams::TAG_SOURCE)) // not the source flavor
	        {
		        $dynamicFlavorAttributes = $dynamicEntryAttributes[flavorParams::DYNAMIC_ATTRIBUTES_ALL_FLAVORS_INDEX];
	        }
		}
		return $dynamicFlavorAttributes;
	}
	
	public function getPrivacyByContexts()
	{
		$privacyContextForEntry = kEntitlementUtils::getPrivacyContextForEntry($this);
		$privacyContextForEntry = kEntitlementUtils::addPrivacyContextsPrefix( $privacyContextForEntry, $this->getPartnerId() );

		return implode(' ',$privacyContextForEntry);
	}
	
	
	public function getEntitledKusers()
	{
		$entitledKusersPublish = explode(',', $this->getEntitledKusersPublish());
		$entitledKusersEdit = explode(',', $this->getEntitledKusersEdit());
		$entitledKusersView = explode(',', $this->getEntitledKusersView());
		
		$entitledKusersNoPrivacyContext = array_merge($entitledKusersPublish, $entitledKusersEdit, $entitledKusersView);
		$entitledKusersNoPrivacyContext[] = $this->getKuserId();
		
		foreach ($entitledKusersNoPrivacyContext as $key => $value)
		{
			if(!$value)
				unset($entitledKusersNoPrivacyContext[$key]);
		}
				
		$entitledKusers = array();
		
		if(count(array_unique($entitledKusersNoPrivacyContext)))
			$entitledKusers[kEntitlementUtils::ENTRY_PRIVACY_CONTEXT] = array_unique($entitledKusersNoPrivacyContext);
		
		$allCategoriesIds = $this->getAllCategoriesIds(true);
		if (!count($allCategoriesIds))
			return kEntitlementUtils::ENTRY_PRIVACY_CONTEXT . '_' . implode(' ' . kEntitlementUtils::ENTRY_PRIVACY_CONTEXT . '_', $entitledKusersNoPrivacyContext);
		
		$categoryGroupSize = kConf::get('max_number_of_memebrs_to_be_indexed_on_entry');
		$partner = $this->getPartner();
		if($partner && $partner->getCategoryGroupSize())
			$categoryGroupSize = $partner->getCategoryGroupSize();
			
		//get categories for this entry that have small amount of members.
		$c = KalturaCriteria::create(categoryPeer::OM_CLASS);
		$c->add(categoryPeer::ID, $allCategoriesIds, Criteria::IN);
		$c->add(categoryPeer::MEMBERS_COUNT, $categoryGroupSize, Criteria::LESS_EQUAL);
		$c->add(categoryPeer::ENTRIES_COUNT, kConf::get('category_entries_count_limit_to_be_indexed'), Criteria::LESS_EQUAL);
		$c->dontCount();
		
		KalturaCriterion::disableTag(KalturaCriterion::TAG_ENTITLEMENT_CATEGORY);
		$categories	= categoryPeer::doSelect($c);
		KalturaCriterion::restoreTag(KalturaCriterion::TAG_ENTITLEMENT_CATEGORY);
		
		//get all members
		foreach ($categories as $category)
		{
			if(!count($category->getMembers()))
				continue;
				
			$privacyContexts = explode(',', $category->getPrivacyContexts());
			if(!count($privacyContexts))
				$privacyContexts = array(kEntitlementUtils::DEFAULT_CONTEXT . $this->getPartnerId());
							
			foreach ($privacyContexts as $privacyContext)
			{
				$privacyContext = trim($privacyContext);
				if(isset($entitledKusers[$privacyContext]))
					$entitledKusers[$privacyContext] = array_merge($entitledKusers[$privacyContext], $category->getMembers());
				else
					$entitledKusers[$privacyContext] = $category->getMembers();
			}
		}
		
		$entitledKusersByContexts = array();
		
		foreach($entitledKusers as $privacyContext => $kusers)
			$entitledKusersByContexts[] =  $privacyContext . '_' . implode(' ' . $privacyContext . '_', $kusers);
			
		return implode(' ', $entitledKusersByContexts);
	}
	
	public function getAllCategoriesIds($includePending = false)
	{
		if(!$includePending)
		{
			$categoriesEntry = categoryEntryPeer::retrieveActiveByEntryId($this->getId());
		}
		else
		{
			$categoriesEntry = categoryEntryPeer::retrieveActiveAndPendingByEntryId($this->getId());
		}
		
		
		$categoriesIds = array();
		foreach($categoriesEntry as $categoryEntry)
			$categoriesIds[] = $categoryEntry->getCategoryId();
			
		return $categoriesIds;
	}
	
	public function reSetCategories()
	{
		$this->is_categories_modified = true;
	}
	
	public function setCategoriesWithNoSync()
	{
		
	}
	
	static public function isSearchProviderSource($source)
	{
		$refClass = new ReflectionClass('EntrySourceType');
		$coreConstants = $refClass->getConstants();
		if (in_array($source, array_values($coreConstants)))
			return false;
	
		$typeMap = kPluginableEnumsManager::getCoreMap('EntrySourceType');
		if (isset($typeMap[$source]))
			return false;
	
		return true;
	}
	
	public function getSourceType()
	{
		if (self::isSearchProviderSource($this->getSource()))
			return (string)EntrySourceType::SEARCH_PROVIDER;
	
		return (string)$this->getSource();
	}
	
	public function getSearchProviderType()
	{
		$sourceValue = $this->getSource();
		
		if(is_null($sourceValue))
			return null;
		
		if (self::isSearchProviderSource($sourceValue))
			return (string)$sourceValue;
	
		return null;
	}
	
	public function setSourceType($value , $forceSet = false)
	{
		if ($value != EntrySourceType::SEARCH_PROVIDER)
			$this->setSource($value , $forceSet);
    }
	
	public function setSource($v , $forceSet=false)
	{
		if($forceSet || !in_array($this->getSource(), array(EntrySourceType::KALTURA_RECORDED_LIVE, EntrySourceType::LECTURE_CAPTURE)) || $v == EntrySourceType::RECORDED_LIVE)
			parent::setSource($v);
	}
	
	public function setSearchProviderType($value)
	{
		$this->setSource($value);
	}

	public function addClonePendingEntry($entryId)
	{
		$clonePendingEntries = $this->getClonePendingEntries();
		$clonePendingEntries[] = $entryId;
		$this->setClonePendingEntries($clonePendingEntries);
	}

	public function setClonePendingEntries(array $clonePendingEntries)
	{
		$this->putInCustomData("clonePendingEntries", $clonePendingEntries);
	}

	public function getClonePendingEntries()
	{
		return $this->getFromCustomData("clonePendingEntries", null, array());
	}

	/**
	 * 
	 * @return array
	 */
	protected function getTrackColumns ()
	{
		return array(
			entryPeer::STATUS,
			entryPeer::MODERATION_STATUS,
			entryPeer::KUSER_ID,
			entryPeer::CREATOR_KUSER_ID,
			entryPeer::ACCESS_CONTROL_ID,
			'' => array(
				'replacementStatus',
				'replacingEntryId',
				'replacedEntryId',
				'redirectEntryId',
			)
		);
	}
	
	protected function getTrackEntryString ($namespace, $customDataColumn, $value)
	{
		//No need for implementation - for example, see extending logic in class LiveEntry
	}
	
/**
	 * will create thumbnail according to the entry type
	 * @return the thumbnail path.
	 */
	public function getLocalThumbFilePath($version , $width , $height , $type , $bgcolor ="ffffff" , $crop_provider=null, $quality = 0,
		$src_x = 0, $src_y = 0, $src_w = 0, $src_h = 0, $vid_sec = -1, $vid_slice = 0, $vid_slices = -1, $density = 0, $stripProfiles = false, $flavorId = null, $fileName = null, $start_sec = null, $end_sec = null)
	{
		$contentPath = myContentStorage::getFSContentRootPath ();
		// if entry type is audio - serve generic thumb:
		if ($this->getMediaType () == entry::ENTRY_MEDIA_TYPE_AUDIO) {
			if ($this->getStatus () == entryStatus::DELETED || $this->getModerationStatus () == moderation::MODERATION_STATUS_BLOCK) {
				KalturaLog::log ( "rejected audio entry - not serving thumbnail" );
				KExternalErrors::dieError ( KExternalErrors::ENTRY_DELETED_MODERATED );
			}
			
			$audioEntryExist = false;
			$audioThumbEntry = null;
			$audioThumbEntryId = null;
			
			$partner = $this->getPartner();
			if ($partner)
				$audioThumbEntryId = $partner->getAudioThumbEntryId();
			if($audioThumbEntryId)
				$audioThumbEntry = entryPeer::retrieveByPK($audioThumbEntryId);

			if ($audioThumbEntry && $audioThumbEntry->getMediaType() == entry::ENTRY_MEDIA_TYPE_IMAGE)
			{
				$fileSyncVersion = $partner->getAudioThumbEntryVersion();
				$audioEntryKey = $audioThumbEntry->getSyncKey(kEntryFileSyncSubType::DATA,$fileSyncVersion);
				$contentPath = kFileSyncUtils::getLocalFilePathForKey($audioEntryKey);
				if ($contentPath)
				{
					$msgPath = $contentPath;
					$audioEntryExist = true;
				}
				else
					KalturaLog::err('no local file sync for entry id');
			}

			if (!$audioEntryExist)
				$msgPath = $contentPath . "content/templates/entry/thumbnail/audio_thumb.jpg";
			
			return myEntryUtils::resizeEntryImage ( $this, $version, $width, $height, $type, $bgcolor, $crop_provider, $quality, $src_x, $src_y, $src_w, $src_h, $vid_sec, $vid_slice, $vid_slices, $msgPath, $density, $stripProfiles );
		
		} elseif ($this->getMediaType () == entry::ENTRY_MEDIA_TYPE_SHOW) { // roughcut without any thumbnail, probably just created
			$msgPath = $contentPath . "content/templates/entry/thumbnail/auto_edit.jpg";
			return myEntryUtils::resizeEntryImage ( $this, $version, $width, $height, $type, $bgcolor, $crop_provider, $quality, $src_x, $src_y, $src_w, $src_h, $vid_sec, $vid_slice, $vid_slices, $msgPath, $density, $stripProfiles );
		
		}
		elseif ($this->getType () == entryType::MEDIA_CLIP || $this->getType() == entryType::PLAYLIST) {
			try {
				$msgPath = $this->getDefaultThumbPath($this->getType());
				return myEntryUtils::resizeEntryImage ( $this, $version, $width, $height, $type, $bgcolor, $crop_provider, $quality, $src_x, $src_y, $src_w, $src_h, $vid_sec, $vid_slice, $vid_slices, $msgPath, 0, false, null, null, null, $start_sec, $end_sec);
			} catch ( Exception $ex ) {
				if ($ex->getCode () == kFileSyncException::FILE_DOES_NOT_EXIST_ON_CURRENT_DC) {
					// get original flavor asset
					$origFlavorAsset = assetPeer::retrieveOriginalByEntryId ( $this->getId() );
					if ($origFlavorAsset) {
						$syncKey = $origFlavorAsset->getSyncKey ( flavorAsset::FILE_SYNC_FLAVOR_ASSET_SUB_TYPE_ASSET );
						
						list ( $readyFileSync, $isLocal ) = kFileSyncUtils::getReadyFileSyncForKey ( $syncKey, TRUE, FALSE );
						if ($readyFileSync) {
							if ($isLocal) {
								KalturaLog::err ( 'Trying to redirect to myself - stop here.' );
								KExternalErrors::dieError ( KExternalErrors::MISSING_THUMBNAIL_FILESYNC );
							}
							//Ready fileSync is on the other DC - dumping
							kFileUtils::dumpApiRequest ( kDataCenterMgr::getRemoteDcExternalUrlByDcId ( 1 - kDataCenterMgr::getCurrentDcId () ) );
						}
						KalturaLog::err ( 'No ready fileSync found on any DC.' );
						KExternalErrors::dieError ( KExternalErrors::MISSING_THUMBNAIL_FILESYNC );
					}
				}
			}
		}
	}

	public function isSecuredEntry() {
		
		$invalidModerationStatuses = array(
				entry::ENTRY_MODERATION_STATUS_PENDING_MODERATION,
				entry::ENTRY_MODERATION_STATUS_REJECTED
		);
		
		if (in_array($this->getModerationStatus(), $invalidModerationStatuses) ||
				($this->getStartDate() !== null && $this->getStartDate(null) >= time()) ||
				($this->getEndDate() !== null && $this->getEndDate(null) <= time() + 86400))
			return true;
		
		$accessControl = $this->getaccessControl();
		if (!$accessControl || $accessControl->getRulesArray())
			return true;
		
		return false;
	}
	
	/**
	 * @param      string $name
	 * @param      string $namespace
	 * @return     boolean True if $name has been modified.
	 */
	public function isCustomDataModified($name = null, $namespace = '')
	{
		if(isset($this->oldCustomDataValues[$namespace]) && (is_null($name) || array_key_exists($name, $this->oldCustomDataValues[$namespace])))
		{
			return true;
		}
		
		return false;
	}

	/**
	 * @return array comma separated ID
	 */
	public function getReadyFlavorAssetIds(){
		$flavorAssetIds = array();
		$entryFlavors = assetPeer::retrieveFlavorsByEntryIdAndStatus($this->getId(), null, array(flavorAsset::ASSET_STATUS_READY));
		if ($entryFlavors && is_array($entryFlavors))
		{
			foreach($entryFlavors as $entryFlavor){
				$flavorAssetIds[] = $entryFlavor->getId();
			}
		}
		return $flavorAssetIds;
	}
	
	public function getUserNames()
	{
		$kuser = $this->getkuser();
		if($kuser)
		{
			$userNames = $this->getUserNamesAsArray($kuser);
			if ($userNames)
				return implode(" ", $userNames);
		}

		return "";
	}

	private function getAllUserNamesAsArray()
	{

		$result = array();
		$users = $this->getAllUsers();

		foreach ($users as $user)
		{
			$result2 = $this->getUserNamesAsArray($user, false);
			$result = array_merge($result, $result2);
		}

		if($result)
		{
			$result = array_unique($result);
			$result = array_values($result);
			return $result;
		}

		return null;
	}

	private function getAllUsers()
	{
		$kUsersIds = array();

		if($this->getKuserId())
			$kUsersIds[] = $this->getKuserId();

		if($this->getCreatorKuserId())
			$kUsersIds[] = $this->getCreatorKuserId();

		$entitledKusersIds = $this->getEntitledKusersEditArray();
		$kUsersIds = array_merge($kUsersIds, $entitledKusersIds);

		$entitledKusersIds = $this->getEntitledKusersPublishArray();
		$kUsersIds = array_merge($kUsersIds, $entitledKusersIds);

		return  kuserPeer::retrieveByPKs($kUsersIds);
	}

	private function getUserNamesAsArray($kuser, $includeNullValues = true)
	{
		$userNames = array();
		if($includeNullValues || $kuser->getFirstName())
			$userNames[] = $kuser->getFirstName();

		if($includeNullValues || $kuser->getLastName())
			$userNames[] = $kuser->getLastName();

		if($includeNullValues || $kuser->getScreenName())
			$userNames[] = $kuser->getScreenName();

		return $userNames;
	}

	public function getCapabilities()
	{
		$capabilitiesArr = $this->getFromCustomData(self::CAPABILITIES, null, array());
		$capabilitiesStr = implode(",", $capabilitiesArr);
		return $capabilitiesStr;
	}


	public function hasCapability($capability)
	{
		$capabilitiesArr = $this->getFromCustomData(self::CAPABILITIES, null, array());
		return array_key_exists($capability, $capabilitiesArr);
	}

	public function addCapability($capability)
	{
		$capabilities = $this->getFromCustomData(self::CAPABILITIES, null, array());
		$capabilities[$capability] = $capability;
		$this->putInCustomData( self::CAPABILITIES, $capabilities);
	}

	public function removeCapability($capabilityToRemove)
	{
		$capabilities = $this->getFromCustomData(self::CAPABILITIES, null, array());
		$newCapabilties = array();
		foreach ($capabilities as $capability)
		{
			if($capability !== $capabilityToRemove)
			{
				$newCapabilties[$capability] = $capability;
			}
		}

		$this->putInCustomData( self::CAPABILITIES, $newCapabilties);
	}

	/**
	 * Sets contents of passed object to values from current object.
	 *
	 * If desired, this method can also make copies of all associated (fkey referrers)
	 * objects.
	 *
	 * @param      object $copyObj An object of entry (or compatible) type.
	 * @param      boolean $deepCopy Whether to also copy all rows that refer (by fkey) to the current row.
	 * @throws     PropelException
	 */
	public function copyInto($copyObj, $deepCopy = false)
	{
		parent::copyInto($copyObj,$deepCopy);
		try
		{
			$copyObj->setEntitledPusersEdit($this->getEntitledPusersEdit());
			$copyObj->setEntitledPusersPublish($this->getEntitledPusersPublish());
			$copyObj->setEntitledPusersView($this->getEntitledPusersView());
		}
		catch(kCoreException $e)
		{
			KalturaLog::warning($e->getMessage());
		}
		$copyObj->setPartnerSortValue($this->getPartnerSortValue());
	}
	
	public function getkshow(PropelPDO $con = null)
	{
		if (!$this->kshow_id)
		{
			return null;
		}
		return kshowPeer::retrieveByPK($this->kshow_id, $con);
	}
	
	public function getconversionProfile2(PropelPDO $con = null)
	{
		return conversionProfile2Peer::retrieveByPK($this->conversion_profile_id, $con);
	}

	/**
	 * @param int $property
	 * @param bool $shouldClone
	 * @return bool
	 */
	public function shouldCloneByProperty($property, $shouldClone = true)
	{
		if (!is_null($this->clone_options))
		{
			foreach ($this->clone_options as $cloneOption)
			{
				if ($cloneOption->getItemType() == $property)
				{
					$currentRule = $cloneOption->getRule();
					if ($currentRule == CloneComponentSelectorType::EXCLUDE_COMPONENT)
						$shouldClone = false;
					else if ($currentRule == CloneComponentSelectorType::INCLUDE_COMPONENT)
						$shouldClone = true;
					break;
				}
			}
		}
		return $shouldClone;
	}

	public function getReferenceIdWithMd5()
	{
		$refId = $this->getReferenceID();
		if ( $refId != null )
			$refId .= " " . mySearchUtils::getMd5EncodedString($refId);

		return $refId;
	}

	public function getTemplateEntryId()
	{
		return $this->getFromCustomData(self::TEMPLATE_ENTRY_ID);
	}
	
	public function setTemplateEntryId($v)
	{
		$this->putInCustomData(self::TEMPLATE_ENTRY_ID, $v);
	}

	public function customDataValueHasChanged($name, $namespace = '')
	{
		if ( isset($this->oldCustomDataValues[$namespace]) 
				&& ( isset($this->oldCustomDataValues[$namespace][$name]) || array_key_exists($name, $this->oldCustomDataValues[$namespace]) )
				&& $this->oldCustomDataValues[$namespace][$name] != $this->getFromCustomData($name, $namespace) )
				return true;
		return false;
	}

	public function setInClone($v)
	{
		$this->putInCustomData("in_clone", $v);
	}
	public function getInClone()
	{
		return $this->getFromCustomData("in_clone");
	}

	public function setRecordedLengthInMsecs($v)
	{
		$this->putInCustomData("recorded_entry_length_in_msecs", $v);
	}

	public function getRecordedLengthInMsecs()
	{
		return $this->getFromCustomData("recorded_entry_length_in_msecs",null, 0);
	}

	public function createPlayManifestUrlByFormat($format)
	{
		$entryId = $this->getId();
		$protocolStr = infraRequestUtils::getProtocol();
		
		$url = requestUtils::getApiCdnHost();
		$url .= myPartnerUtils::getUrlForPartner( $this->getPartnerId() , $this->getSubpId() );
		$url .= "/playManifest/entryId/$entryId/format/$format/protocol/$protocolStr";
		
		return $url;
	}

	/**
	 * return the name of the elasticsearch index for this object
	 */
	public function getElasticIndexName()
	{
		return ElasticIndexMap::ELASTIC_ENTRY_INDEX;
	}

	/**
	 * return the name of the elasticsearch type for this object
	 */
	public function getElasticObjectType()
	{
		return ElasticIndexMap::ELASTIC_ENTRY_TYPE;
	}

	/**
	 * return the elasticsearch id for this object
	 */
	public function getElasticId()
	{
		return $this->getId();
	}

	/**
	 * return the elasticsearch parent id or null if no parent
	 */
	public function getElasticParentId()
	{
		return null;
	}

	/**
	 * get the params we index to elasticsearch for this object
	 */
	public function getObjectParams($params = null)
	{
		$body = array(
			'parent_id' => $this->getParentEntryId(),
			'status' => $this->getStatus(),
			'entitled_kusers_edit' => $this->getEntitledKusersEditArray(),
			'entitled_kusers_publish' => $this->getEntitledKusersPublishArray(),
			'entitled_kusers_view' => $this->getEntitledKusersViewArray(),
			'kuser_id' => $this->getKuserId(),
			'creator_kuser_id' => $this->getCreatorKuserId(),
			'name' => multiLingualUtils::getElasticFieldValue($this, self::NAME),
			'description' =>  multiLingualUtils::getElasticFieldValue($this, self::DESCRIPTION),
			'tags' =>  multiLingualUtils::getElasticFieldValue($this, self::TAGS, true),
			'partner_id' => $this->getPartnerId(),
			'partner_status' => elasticSearchUtils::formatPartnerStatus($this->getPartnerId(), $this->getStatus()),
			'reference_id' => $this->getReferenceID(),
			'conversion_profile_id' => $this->getConversionProfileId(),
			'template_entry_id' => $this->getTemplateEntryId(),
			'display_in_search' => $this->getDisplayInSearch(),
			'media_type' => $this->getMediaType(),
			'source_type' => $this->getSourceType(),
			'length_in_msecs' => $this->getLengthInMsecs(),
			'admin_tags' => explode(',',!is_null($this->getAdminTags()) ? $this->getAdminTags() : ""),
			'credit' => $this->getCredit(),
			'site_url' => $this->getSiteUrl(),
			'start_date' => $this->getStartDate(null),
			'end_date' => $this->getEndDate(null),
			'entry_type' => $this->getType(),
			'moderation_status' => $this->getModerationStatus(),
			'created_at' => $this->getCreatedAt(null),
			'updated_at' => $this->getUpdatedAt(null),
			'modified_at' => $this->getModifiedAt(null),
			'total_rank' => $this->getTotalRank(),
			'rank' => $this->getRank(),
			'access_control_id' => $this->getAccessControlId(),
			'group_id' => $this->getGroupId(),
			'partner_sort_value' => $this->getPartnerSortValue(),
			'redirect_entry_id' => $this->getRedirectEntryId(),
			'views' => $this->getViews(),
			'votes' => $this->getVotes(),
			'plays' => $this->getPlays(),
			'last_played_at' => $this->getLastPlayedAt(null),
			'user_names' => $this->getAllUserNamesAsArray(),
			'root_id' => $this->getRootEntryId(),
			'views_30days' => $this->getViewsLast30Days(),
			'plays_30days' => $this->getPlaysLast30Days(),
			'views_7days' => $this->getViewsLast7Days(),
			'plays_7days' => $this->getPlaysLast7Days(),
			'views_1day' => $this->getViewsLastDay(),
			'plays_1day' => $this->getPlaysLastDay(),
			'recycled_at' => $this->getRecycledAt(),
		);

		$this->addCategoriesToObjectParams($body);
		
		if($this->getParentEntryId())
			$this->addParentEntryToObjectParams($body);

		elasticSearchUtils::cleanEmptyValues($body);

		return $body;
	}

	/**
	 * return the save method to elastic: ElasticMethodType::INDEX or ElasticMethodType::UPDATE
	 */
	public function getElasticSaveMethod()
	{
		return ElasticMethodType::INDEX;
	}

	public function getElasticEntryId()
	{
		return $this->getId();
	}

	/**
	 * Index the object into elasticsearch
	 */
	public function indexToElastic($params = null)
	{
		kEventsManager::raiseEventDeferred(new kObjectReadyForElasticIndexEvent($this));
	}
	
	protected function addParentEntryToObjectParams(&$body)
	{
		$parentEntry = $this->getParentEntry();
		if (!$parentEntry || $parentEntry->getId() == $this->getId())
		{
			return;
		}

		$parentCategoryIdsSearchData = array();
		$parentEntryPrivacyContexts = array();
		$parentCategoryEntries = categoryEntryPeer::retrieveActiveAndPendingByEntryId($parentEntry->getId());
		list($categoryIds, $mapCategoryEntryStatus) = $this->buildCategoryEntryIdsSearchData($parentCategoryIdsSearchData, $parentCategoryEntries);

		if (count($categoryIds))
		{
			list($categoryNamesSearchData, $parentEntryPrivacyContexts) = $this->buildCategoriesSearchData($categoryIds, $mapCategoryEntryStatus, false);
		}

		$parentCategoryIdsSearchData = array_unique($parentCategoryIdsSearchData);
		$parentCategoryIdsSearchData = array_values($parentCategoryIdsSearchData);

		$body['parent_entry'] = array(
			'entry_id' => $parentEntry->getId(),
			'status' => $parentEntry->getStatus(),
			'partner_status' => elasticSearchUtils::formatPartnerStatus($parentEntry->getPartnerId(), $parentEntry->getStatus()),
			'entitled_kusers_edit' => $parentEntry->getEntitledKusersEditArray(),
			'entitled_kusers_publish' => $parentEntry->getEntitledKusersPublishArray(),
			'entitled_kusers_view' => $parentEntry->getEntitledKusersViewArray(),
			'kuser_id' => $parentEntry->getKuserId(),
			'creator_kuser_id' => $parentEntry->getCreatorKuserId(),
			'categories_ids' => $parentCategoryIdsSearchData,
			'privacy_by_contexts' => $parentEntryPrivacyContexts
		);
	}

	protected function addCategoriesToObjectParams(&$body)
	{
		$categoryEntries = categoryEntryPeer::selectByEntryId($this->getId());
		list($categoryIdsSearchData, $categoryNamesSearchData, $categoryPrivacyByContextSearchData) = $this->getCategoriesElasticSearchData($categoryEntries);
		$body['categories_ids'] = $categoryIdsSearchData;
		$body['categories_names'] = $categoryNamesSearchData;
		$body['privacy_by_contexts'] = $categoryPrivacyByContextSearchData;
	}

	protected function shouldAddCategoryPrivacyByContextsSearchData($categoryEntryStatus)
	{
		$privacyByContextCategoryEntryStatuses = array(CategoryEntryStatus::ACTIVE, CategoryEntryStatus::PENDING);

		return in_array($categoryEntryStatus, $privacyByContextCategoryEntryStatuses);
	}

	protected function buildCategoryEntryIdsSearchData(&$categoryIdsSearchData, $categoryEntries)
	{
		$categoryIds = array();
		$mapCategoryEntryStatus = array();
		foreach ($categoryEntries as $categoryEntry)
		{
			/** @var categoryEntry $categoryEntry */
			self::getCategoryEntryElasticSearchData($categoryEntry, $categoryEntry->getStatus(), $categoryIdsSearchData);
			$categoryIds[] = $categoryEntry->getCategoryId();
			$mapCategoryEntryStatus[$categoryEntry->getCategoryId()] = $categoryEntry->getStatus();
		}

		return array($categoryIds, $mapCategoryEntryStatus);
	}

	protected function buildCategoriesSearchData($categoryIds, $mapCategoryEntryStatus, $shouldAddCategoryNamesData = true)
	{
		$categoryNamesSearchData = array();
		$categoryPrivacyByContextSearchData = array();

		$categories = categoryPeer::retrieveByPKs($categoryIds);
		foreach ($categories as $category)
		{
			$categoryEntryStatus = $mapCategoryEntryStatus[$category->getId()];
			if ($shouldAddCategoryNamesData)
			{
				self::getCategoryNamesSearchData($category->getFullName(), $categoryEntryStatus, $category->getName(), $categoryNamesSearchData);
			}

			if ($this->shouldAddCategoryPrivacyByContextsSearchData($categoryEntryStatus))
			{
				self::getCategoryPrivacyByContextsSearchData($category, $categoryPrivacyByContextSearchData);
			}
		}

		$entryPrivacyContexts = self::getCategoriesPrivacyByContextsSearchData($categoryPrivacyByContextSearchData, $this->getPartnerId());

		return array($categoryNamesSearchData, $entryPrivacyContexts);
	}

	protected function getCategoriesElasticSearchData($categoryEntries)
	{
		$categoryIdsSearchData = array();
		$categoryNamesSearchData = array();
		$entryPrivacyContexts = array();

		list($categoryIds, $mapCategoryEntryStatus) = $this->buildCategoryEntryIdsSearchData($categoryIdsSearchData, $categoryEntries);

		if (count($categoryIds))
		{
			list($categoryNamesSearchData, $entryPrivacyContexts) = $this->buildCategoriesSearchData($categoryIds, $mapCategoryEntryStatus, true);
		}

		$categoryIdsSearchData = array_unique($categoryIdsSearchData);
		$categoryIdsSearchData = array_values($categoryIdsSearchData);
		$categoryNamesSearchData = array_unique($categoryNamesSearchData);
		$categoryNamesSearchData = array_values($categoryNamesSearchData);

		return array($categoryIdsSearchData, $categoryNamesSearchData, $entryPrivacyContexts);
	}

	/**
	 * @param $type
	 * @return null|string
	 */
	protected function getDefaultThumbPath()
	{
		//// in case of a recorded entry from live that doesn't have flavors yet nor thumbs we will use the live default thumb.
		if ((($this->getSourceType() == EntrySourceType::RECORDED_LIVE || $this->getSourceType() == EntrySourceType::KALTURA_RECORDED_LIVE) && !assetPeer::countByEntryId($this->getId(), array(assetType::FLAVOR, assetType::THUMBNAIL)))
				|| (myEntryUtils::shouldServeVodFromLive($this)))
			return myContentStorage::getFSContentRootPath() . self::LIVE_THUMB_PATH;
		return null;
	}

	protected static function getCategoryEntryElasticSearchData($categoryEntry, $categoryEntryStatus,  &$categoryIdsSearchArr)
	{
		$fullIds = $categoryEntry->getCategoryFullIds();
		$categoryIdsSearchArr[] = elasticSearchUtils::formatCategoryFullIdStatus($fullIds, $categoryEntryStatus);
		$categoryIdsSearchArr[] = elasticSearchUtils::formatCategoryIdStatus($categoryEntry->getCategoryId(),$categoryEntryStatus);
		$categoryIdsSearchArr[] = elasticSearchUtils::formatCategoryEntryStatus($categoryEntryStatus);
		$categoryEntryFullIds = explode(categoryPeer::CATEGORY_SEPARATOR, $fullIds);
		foreach($categoryEntryFullIds as $categoryId)
		{
			if (!trim($categoryId))
				continue;

			if($categoryId != $categoryEntry->getCategoryId())
				$categoryIdsSearchArr[] = elasticSearchUtils::formatParentCategoryIdStatus($categoryId, $categoryEntryStatus);
		}
	}

	protected static function getCategoryPrivacyByContextsSearchData($category, &$categoryPrivacyByContextSearchData)
	{
		$categoryPrivacy = $category->getPrivacy();
		$categoryPrivacyContexts = $category->getPrivacyContexts();
		if ($categoryPrivacyContexts)
		{
			$categoryPrivacyContexts = explode(',', $categoryPrivacyContexts);
			foreach ($categoryPrivacyContexts as $categoryPrivacyContext)
			{
				if (trim($categoryPrivacyContext) == '')
				{
					$categoryPrivacyContext = kEntitlementUtils::DEFAULT_CONTEXT;
				}

				if (!isset($categoryPrivacyByContextSearchData[$categoryPrivacyContext]) || $categoryPrivacyByContextSearchData[$categoryPrivacyContext] > $categoryPrivacy)
				{
					$categoryPrivacyByContextSearchData[trim($categoryPrivacyContext)] = $categoryPrivacy;
				}
			}
		}
		else
		{
			$categoryPrivacyByContextSearchData[kEntitlementUtils::DEFAULT_CONTEXT] = PrivacyType::ALL;
		}
	}

	protected static function getCategoriesPrivacyByContextsSearchData(&$categoryPrivacyByContextSearchData, $partnerId)
	{
		$entryPrivacyContexts = array();
		foreach ($categoryPrivacyByContextSearchData as $categoryPrivacyContext => $privacy)
		{
			$entryPrivacyContexts[] = $categoryPrivacyContext . kEntitlementUtils::TYPE_SEPERATOR . $privacy;
		}

		return kEntitlementUtils::addPrivacyContextsPrefix($entryPrivacyContexts, $partnerId);
	}

	protected static function getCategoryNamesSearchData($categoryFullName, $categoryEntryStatus, $categoryName, &$categoryNameSearchArr)
	{
		$categoryNameSearchArr[] = elasticSearchUtils::formatCategoryNameStatus($categoryName, $categoryEntryStatus);
		$categoryFullNames = explode(categoryPeer::CATEGORY_SEPARATOR, $categoryFullName);
		foreach($categoryFullNames as $categoryFName)
		{
			if (!trim($categoryFName))
				continue;

			if($categoryName != $categoryFName)
				$categoryNameSearchArr[] = elasticSearchUtils::formatParentCategoryNameStatus($categoryFName, $categoryEntryStatus);
		}
	}

	public function getTagsArr()
	{
		$tags = explode(",", $this->getTags());
		$tagsToReturn = array();
		foreach($tags as $tag)
		{
			$tag = trim($tag);
			if ($tag){
				$tagsToReturn[] = $tag;
			}
		}
		return array_unique($tagsToReturn);
	}

	/**
	 * return true if the object needs to be deleted from elastic
	 */
	public function shouldDeleteFromElastic()
	{
		if($this->getStatus() == entryStatus::DELETED)
			return true;
		return false;
	}

	/**
	 * return the name of the object we are indexing
	 */
	public function getElasticObjectName()
	{
		return 'entry';
	}
	
	public function isInsideDeleteGracePeriod()
	{
		return $this->getCreatedAt(null) > (time() - dateUtils::DAY * 7);
	}
	
	public function getGeneralEncryptionKey()
	{
		return $this->id . "_GENERAL_ENTRY_KEY";
	}

	public function getEncryptionIv()
	{
		return kConf::get("encryption_iv");
	}

	/**
	 * @param array $playsViewsData
	 */
	public function setPlaysViewsData($playsViewsData)
	{
		if ($playsViewsData)
		{
			$this->playsViewsData = json_decode($playsViewsData, true);
		}
		$this->playsViewsDataInitialized = true;
	}

	protected function usePlaysViewsCache()
	{
		return $this->playsViewsDataInitialized || kCacheManager::getCacheSectionNames(kCacheManager::CACHE_TYPE_PLAYS_VIEWS);
	}

	protected function fetchPlaysViewsData()
	{
		$cache = kCacheManager::getSingleLayerCache(kCacheManager::CACHE_TYPE_PLAYS_VIEWS);
		if (!$cache)
		{
			return;
		}

		$data = $cache->get(self::PLAYSVIEWS_CACHE_KEY_PREFIX . $this->getId());
		$this->setPlaysViewsData($data);
	}

	protected function getValueFromPlaysViewsData($key)
	{
		if (!$this->playsViewsDataInitialized)
		{
			$this->fetchPlaysViewsData();
		}

		if (isset($this->playsViewsData[$key]))
		{
			return $this->playsViewsData[$key];
		}
		return 0;
	}

	public function getPlays()
	{
		if (!$this->usePlaysViewsCache())
		{
			return parent::getPlays();
		}

		return $this->getValueFromPlaysViewsData(self::PLAYS_CACHE_KEY);
	}

	public function getViews()
	{
		if (!$this->usePlaysViewsCache())
		{
			return parent::getViews();
		}

		return $this->getValueFromPlaysViewsData(self::VIEWS_CACHE_KEY);
	}

	public function getLastPlayedAt($format = 'Y-m-d H:i:s')
	{
		if (!$this->usePlaysViewsCache())
		{
			return parent::getLastPlayedAt($format);
		}

		$cacheValue = $this->getValueFromPlaysViewsData(self::LAST_PLAYED_AT_CACHE_KEY);
		if (!$cacheValue)
		{
			return null;
		}
		return self::getDateByFormat($cacheValue, $format);
	}

	public function getPlaysLast30Days()
	{
		return $this->getValueFromPlaysViewsData(self::PLAYS_30_DAYS_CACHE_KEY);
	}

	public function getViewsLast30Days()
	{
		return $this->getValueFromPlaysViewsData(self::VIEWS_30_DAYS_CACHE_KEY);
	}

	public function getPlaysLast7Days()
	{
		return $this->getValueFromPlaysViewsData(self::PLAYS_7_DAYS_CACHE_KEY);
	}

	public function getViewsLast7Days()
	{
		return $this->getValueFromPlaysViewsData(self::VIEWS_7_DAYS_CACHE_KEY);
	}

	public function getPlaysLastDay()
	{
		return $this->getValueFromPlaysViewsData(self::PLAYS_1_DAY_CACHE_KEY);
	}

	public function getViewsLastDay()
	{
		return $this->getValueFromPlaysViewsData(self::VIEWS_1_DAY_CACHE_KEY);
	}

	protected static function getDateByFormat($timestamp, $format)
	{
		if ($format === null)
		{
			return $timestamp;
		}
		elseif (strpos($format, '%') !== false)
		{
			return strftime($format, $timestamp);
		}

		return date($format, $timestamp);
	}

	public function shouldFetchPlaysViewData()
	{
		switch ($this->getType())
		{
			case entryType::DATA:
			case entryType::DOCUMENT:
				return false;

			default:
				return true;
		}
	}

	public function setSyncFlavorsOnceReady($v)
	{
		$this->putInCustomData ( "syncFlavorsOnceReady" , (bool) $v );
	}

	public function getSyncFlavorsOnceReady()
	{
		return (bool) $this->getFromCustomData( "syncFlavorsOnceReady" ,null, false );
	}

	public function setInteractivityVersion($version)
	{
		$this->putInCustomData( self::CUSTOM_DATA_INTERACTIVITY_VERSION ,$version, null);
	}

	public function getInteractivityVersion()
	{
		return $this->getFromCustomData( self::CUSTOM_DATA_INTERACTIVITY_VERSION ,null, null);
	}

	public function setVolatileInteractivityVersion($version)
	{
		$this->putInCustomData( self::CUSTOM_DATA_VOLATILE_INTERACTIVITY_VERSION ,$version, null);
	}

	public function getVolatileInteractivityVersion()
	{
		return $this->getFromCustomData( self::CUSTOM_DATA_VOLATILE_INTERACTIVITY_VERSION ,null, null);
	}

	/**
	 * Check if entry contains adminTag
	 *
	 * @param string $adminTag
	 * @return boolean
	 */
	public function isContainsAdminTag($adminTag)
	{
		return in_array($adminTag, explode(',', $this->getAdminTags()));
	}

	/**
	 * @return array
	 */
	public function getAdminTagsArr()
	{
		$tags = explode(",", !is_null($this->getAdminTags()) ? $this->getAdminTags() : "");
		$tagsToReturn = array();
		foreach($tags as $tag)
		{
			$tag = trim($tag);
			if($tag)
			{
				$tagsToReturn[] = $tag;
			}
		}
		return array_unique($tagsToReturn);
	}

	/**
	 * allow edit or change related metadata
	 * @return boolean
	 */
	public function allowEdit()
	{
		//Admin's should be allowed to edit
		if(kCurrentContext::$is_admin_session)
		{
			return true;
		}

		//If current user is entitled to edit the entry
		if($this->isEntitledKuserEdit(kCurrentContext::getCurrentKsKuserId()))
		{
			return true;
		}

		//If provided KS contains edit privilege for the given entry ID
		if(isset(kCurrentContext::$ks_object) && (kCurrentContext::$ks_object->hasPrivilege(ks::PRIVILEGE_WILDCARD) ||
				kCurrentContext::$ks_object->verifyPrivileges(kSessionBase::PRIVILEGE_EDIT, $this->getId())))
		{
			return true;
		}

		return false;
	}

	public static function setAllowOverrideReadOnlyFields($v)
	{
		self::$allow_override_read_only_fields = $v;
	}
	
	public function getEnforceDeliveries()
	{
		$enforceDeliveries = array();
		foreach ($this->getAdminTagsArr() as $tag) {
			if (kString::beginsWith($tag, 'enforce_delivery:'))
			{
				$enforceDeliveries[] = explode(':', $tag)[1];
			}
		}
		return $enforceDeliveries;
	}

}
