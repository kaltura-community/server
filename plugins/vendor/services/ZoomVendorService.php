<?php
/**
 * @service zoomVendor
 * @package plugins.vendor
 * @subpackage api.services
 */
class ZoomVendorService extends KalturaBaseService
{

	/** API */
	const API_USERS_ME = '/v2/users/me';
	const API_PARTICIPANT = '/v2/report/meetings/@meetingId@/participants';
	const API_USERS_ME_PERMISSIONS = '/v2/users/me/permissions';

	/** php body */
	const PHP_INPUT = 'php://input';

	/** payload data */
	const ACCOUNT_ID = "account_id";
	const PAYLOAD = 'payload';
	const DOWNLOAD_TOKEN = 'download_token';
	const MEETING = 'meeting';
	const HOST_EMAIL = 'host_email';
	const RECORDING_FILES = 'recording_files';
	const MP4 = 'MP4';
	const FILE_TYPE = 'file_type';
	const DOWNLOAD_URL = 'download_url';
	const MEETING_ID = 'id';
	const USER_EMAIL = 'user_email';
	const PARTICIPANTS = 'participants';

	/**
	 * no partner will be provided by vendors as this called externally and not from kaltura
	 * @param string $actionName
	 * @return bool
	 */
	protected function partnerRequired($actionName)
	{
		if ($actionName == 'oauthValidation' || $actionName == 'recordingComplete')
			return false;
		return true;
	}

	/**
	 *
	 * @action oauthValidation
	 * @return string
	 * @throws Exception
	 */
	public function oauthValidationAction()
	{
		if(!kConf::hasMap('vendor'))
		{
			throw new KalturaAPIException("Vendor configuration file wasn't found!");
		}
		$zoomConfiguration = kConf::get('ZoomAccount', 'vendor');
		$clientId = $zoomConfiguration['clientId'];
		$zoomBaseURL = $zoomConfiguration['ZoomBaseUrl'];
		$redirectUrl = $zoomConfiguration['redirectUrl'];
		$isAdmin = false;
		$tokens = null;
		if (!array_key_exists('code', $_GET))
		{
			$url = $zoomBaseURL . '/oauth/authorize?' . 'response_type=code' . '&client_id=' . $clientId .  '&redirect_uri=' . $redirectUrl;
			$this->redirect($url);
		}
		else
		{
			$dataRetriever = new RetrieveDataFromZoom();
			list($tokens, $permissions) = $dataRetriever->retrieveZoomDataAsArray(self::API_USERS_ME_PERMISSIONS, true);
			list(, $user) = $dataRetriever->retrieveZoomDataAsArray(self::API_USERS_ME, false, $tokens, null);
			$accountId = $user[self::ACCOUNT_ID];
			$zoomIntegration = VendorIntegrationPeer::retrieveSingleVendorPerPartner($accountId, VendorTypeEnum::ZOOM_ACCOUNT);
			if($zoomIntegration && $zoomIntegration->getStatus() === VendorStatus::DELETED)
				$zoomIntegration->setStatus(VendorStatus::ACTIVE);
			$this->saveNewTokenData($tokens, $accountId, $zoomIntegration);
			$permissions = $permissions['permissions'];
			$isAdmin = $this->canConfigureEventSubscription($permissions);
		}
		if ($isAdmin)
		{
			$this->loadLoginPage($tokens);
		}
		throw new KalturaAPIException('Only Zoom admins are allowed to access kaltura configuration page, please check your user account');
	}


	/**
	 * @action deAuthorization
	 * @return string
	 * @throws Exception
	 */
	public function deAuthorizationAction()
	{
		$this->verifyHeaderToken();
		myPartnerUtils::resetAllFilters();
		$request_body = file_get_contents(self::PHP_INPUT);
		$data = json_decode($request_body, true);
		$accountId = $this->extractAccountIdFromDeAuthPayload($data);
		KalturaLog::info("Zoom changing account id: $accountId status to deleted , user de-authorized the app");
		$zoomIntegration = VendorIntegrationPeer::retrieveSingleVendorPerPartner($accountId,
			VendorTypeEnum::ZOOM_ACCOUNT);
		$zoomIntegration->setStatus(VendorStatus::DELETED);
		$zoomIntegration->save();
		return true;
	}

	/**
	 * @action fetchRegistrationPage
	 * @param string $tokensData
	 * @param string $iv
	 * @throws Exception
	 */
	public function fetchRegistrationPageAction($tokensData, $iv)
	{
		$tokensData = base64_decode($tokensData);
		$iv = base64_decode($iv);
		$zoomConfiguration = kConf::get('ZoomAccount', 'vendor');
		$verificationToken = $zoomConfiguration['verificationToken'];
		$tokens = AESOauthZoom::aesDecrypt($verificationToken, $tokensData, $iv);
		$tokens = json_decode($tokens, true);
		$accessToken = $tokens[kZoomOauth::ACCESS_TOKEN];
		$retrieveDataFromZoom = new RetrieveDataFromZoom();
		list($tokens, $zoomUserData) = $retrieveDataFromZoom->retrieveZoomDataAsArray(self::API_USERS_ME, false, $tokens, null);
		$accountId = $zoomUserData[self::ACCOUNT_ID];
		$zoomIntegration = VendorIntegrationPeer::retrieveSingleVendorPerPartner($accountId,
			VendorTypeEnum::ZOOM_ACCOUNT);
		if ($accessToken !== $tokens[kZoomOauth::ACCESS_TOKEN]) // token changed -> refresh tokens
			$this->saveNewTokenData($tokens, $accountId, $zoomIntegration);
		$partnerId = kCurrentContext::getCurrentPartnerId();
		if ($zoomIntegration && intval($partnerId) !==  $zoomIntegration->getPartnerId() && $partnerId !== 0)
		{
			$zoomIntegration->setPartnerId($partnerId);
			$zoomIntegration->save();
		}
		$this->loadSubmitPage($zoomIntegration, $accountId);
	}


	/**
	 * @action submitRegistration
	 * @param string $defaultUserId
	 * @param string $zoomCategory
	 * @param string $accountId
	 * @return string
	 * @throws PropelException
	 */
	public function submitRegistrationAction($defaultUserId, $zoomCategory, $accountId)
	{
		$partnerId = kCurrentContext::getCurrentPartnerId();
		$zoomIntegration = VendorIntegrationPeer::retrieveSingleVendorPerPartner($accountId,
			VendorTypeEnum::ZOOM_ACCOUNT, $partnerId);
		if(is_null($zoomIntegration))
		{
			$zoomIntegration = new VendorIntegration();
			$zoomIntegration->setAccountId($accountId);
			$zoomIntegration->setVendorType(VendorTypeEnum::ZOOM_ACCOUNT);
			$zoomIntegration->setPartnerId($partnerId);
		}
		$zoomIntegration->setStatus(VendorStatus::ACTIVE);
		$zoomIntegration->setDefaultUserEMail($defaultUserId);
		$zoomIntegration->setZoomCategory($zoomCategory);
		$zoomIntegration->save();
		return true;
	}

	/**
	 * @action recordingComplete
	 * @throws Exception
	 */
	public function recordingCompleteAction()
	{
		KalturaLog::info('Zoom - upload entry to Kaltura starter');
		$this->verifyHeaderToken();
		myPartnerUtils::resetAllFilters();
		$request_body = file_get_contents(self::PHP_INPUT);
		$data = json_decode($request_body, true);
		list($accountId, $downloadToken, $hostEmail, $downloadURL, $meetingId) = $this->extractDataFromRecordingCompletePayload($data);
		$zoomIntegration = VendorIntegrationPeer::retrieveSingleVendorPerPartner($accountId, VendorTypeEnum::ZOOM_ACCOUNT);
		$emails = $this->extractCoHosts($meetingId, $zoomIntegration, $accountId);
		// user logged in - need to re-init kPermissionManager in order to determine current user's permissions
		$ks = null;
		$dbUser = kuserPeer::getKuserByPartnerAndUid($zoomIntegration->getPartnerId(), $hostEmail, true);
		if (!$dbUser) //if not go to default user
		{
			$emails[] = $hostEmail;
			$dbUser = kuserPeer::getKuserByPartnerAndUid($zoomIntegration->getPartnerId(), $zoomIntegration->getDefaultUserEMail(), true);
		}
		kSessionUtils::createKSessionNoValidations($dbUser->getPartnerId() , $dbUser->getPuserId() , $ks, 86400 , false , "" , '*' );
		kCurrentContext::initKsPartnerUser($ks);
		kPermissionManager::init();
		$entryId = $this->createEntryForZoom($dbUser, $zoomIntegration->getZoomCategory(), $this->parseDownloadUrl($downloadURL, $downloadToken), $emails, $meetingId);
		KalturaLog::info('Zoom - upload entry to kaltura started, partner id: '. $zoomIntegration->getPartnerId() . 'host email: ' . $hostEmail . 'emails: ' . print_r($emails, true) .
		'meeting Id: ' . $meetingId . 'entry Id: ' . $entryId);
	}

	/**
	 * @param kuser $dbUser
	 * @param string $zoomCategory
	 * @param $url
	 * @param $emails
	 * @param $meetingId
	 * @return string
	 * @throws Exception
	 */
	private function createEntryForZoom($dbUser, $zoomCategory, $url, $emails, $meetingId)
	{
		$entry = new entry();
		$entry->setType(entryType::MEDIA_CLIP);
		$entry->setSourceType(EntrySourceType::URL);
		$entry->setMediaType(entry::ENTRY_MEDIA_TYPE_VIDEO);
		$entry->setName('Zoom_'. $meetingId);
		$entry->setPartnerId($dbUser->getPartnerId());
		$entry->setStatus(entryStatus::NO_CONTENT);
		$entry->setPuserId($dbUser->getPuserId());
		$entry->setKuserId($dbUser->getKuserId());
		$entry->setConversionProfileId(myPartnerUtils::getConversionProfile2ForPartner($dbUser->getPartnerId())->getId());
		$entry->setAdminTags('zoom');
		$entry->setCategories($zoomCategory);
		if ($emails)
		{
			foreach ($emails as $email)
			{
				kuserPeer::createUniqueKuserForPartner($dbUser->getPartnerId(),$email);
			}
			$entry->setEntitledPusersPublish(implode(",", array_unique($emails)));
		}
		$entry->save();
		KalturaLog::info('Zoom Entry Created, Entry ID:  ' . $entry->getId());
		kJobsManager::addImportJob(null, $entry->getId(), $entry->getPartnerId(), $url);
		return $entry->getId();
	}


	/**
	 * @param array $zoomUserPermissions
	 * @return bool
	 */
	private function canConfigureEventSubscription($zoomUserPermissions)
	{
		if (in_array('Recording:Read', $zoomUserPermissions) && in_array('Recording:Edit', $zoomUserPermissions))
			return true;
		return false;
	}
	/**
	 * redirects to new URL
	 * @param $url
	 */
	private function redirect($url)
	{
		$redirect  = new kRendererRedirect($url);
		$redirect->output();
		KExternalErrors::dieGracefully();
	}

	/**
	 * @param array $tokens
	 * @throws Exception
	 */
	private function loadLoginPage($tokens)
	{
		$file_path = dirname(__FILE__) . "/../lib/api/webPage/zoom/kalturaZoomLoginPage.html";
		if (file_exists($file_path)) {
			$page = file_get_contents($file_path);
			$tokensString = json_encode($tokens);
			$zoomConfiguration = kConf::get('ZoomAccount', 'vendor');
			$verificationToken = $zoomConfiguration['verificationToken'];
			list($enc, $iv) = AESOauthZoom::aesEncrypt($verificationToken, $tokensString);
			$page = str_replace('@BaseServiceUrl@', requestUtils::getHost(), $page);
			$page = str_replace('@encryptData@', base64_encode($enc), $page);
			$page = str_replace('@iv@', base64_encode($iv), $page);
			echo $page;
			die();
		}
	}

	/**
	 * @param VendorIntegration $zoomIntegration
	 * @param $accountId
	 * @throws Exception
	 */
	private function loadSubmitPage($zoomIntegration, $accountId)
	{
		$file_path = dirname(__FILE__) . "/../lib/api/webPage/zoom/KalturaZoomRegistrationPage.html";
		if (file_exists($file_path)) {
			$page = file_get_contents($file_path);
			$page = str_replace('@ks@', $this->getKs()->getOriginalString(), $page);
			$page = str_replace('@BaseServiceUrl@', requestUtils::getHost(), $page);
			if (!is_null($zoomIntegration))
			{
				$page = str_replace('@defaultUserID@', $zoomIntegration->getDefaultUserEMail() , $page);
				$page = str_replace('@zoomCategory@', $zoomIntegration->getZoomCategory() ? $zoomIntegration->getZoomCategory()  : 'Zoom Recordings'  , $page);
			}
			else {
				$page = str_replace('@defaultUserID@', '' , $page);
				$page = str_replace('@zoomCategory@', 'Zoom Recordings' , $page);
			}
			$page = str_replace('@accountId@', $accountId , $page);
			echo $page;
			die();
		}
		throw new KalturaAPIException('unable to find submit page, please contact support');
	}

	/**
	 * @param $tokensDataAsArray
	 * @param $accountId
	 * @param VendorIntegration $zoomClientData
	 * @throws PropelException
	 */
	private function saveNewTokenData($tokensDataAsArray, $accountId, $zoomClientData = null)
	{
		if (!$zoomClientData) // create new vendorIntegration during oauth first time
		{
			$zoomClientData = new VendorIntegration();
			$zoomClientData->setStatus(VendorStatus::DISABLED);
		}
		$zoomClientData->saveNewTokenData($tokensDataAsArray,$accountId);
	}

	/**
	 * @return array
	 */
	private function getAllHeaders()
	{
		if (!function_exists('getallheaders')) {
			$headers = array();
			foreach ($_SERVER as $name => $value) {
				/* RFC2616 (HTTP/1.1) defines header fields as case-insensitive entities. */
				if (strtolower(substr($name, 0, 5)) == 'http_') {
					$headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
				}
			}
			return $headers;
		} else {
			return getallheaders();
		}
	}

	/**
	 * verify headers tokens, if not equal die
	 * @throws Exception
	 */
	private function verifyHeaderToken()
	{
		$headers = $this->getAllHeaders();
		$zoomConfiguration = kConf::get('ZoomAccount', 'vendor');
		$verificationToken = $zoomConfiguration['verificationToken'];
		if ($verificationToken !== $headers['Authorization'])
			KExternalErrors::dieGracefully('ZOOM - Received verification token is different from existing token');
	}

	/**
	 * @param string $downloadURL
	 * @param string $downloadToken
	 * @return string
	 */
	private function parseDownloadUrl($downloadURL, $downloadToken)
	{
		return $downloadURL . '?access_token=' . $downloadToken;
	}

	/**
	 * @param array $recordingFiles
	 * @return string
	 */
	private function getDownloadUrl($recordingFiles)
	{
		$downloadURL = '';
		foreach ($recordingFiles as $recordingFile) {
			if ($recordingFile[self::FILE_TYPE] === self::MP4)
				$downloadURL = $recordingFile[self::DOWNLOAD_URL];
		}
		if (!$downloadURL)
			KExternalErrors::dieGracefully('Zoom - MP4 downland url was not found');
		return $downloadURL;
	}

	/**
	 * @param $data
	 * @return array
	 */
	private function extractDataFromRecordingCompletePayload($data)
	{
		$payload = $data[self::PAYLOAD];
		$accountId = $payload[self::ACCOUNT_ID];
		$downloadToken = $payload[self::DOWNLOAD_TOKEN];
		$meeting = $payload[self::MEETING];
		$hostEmail = $meeting[self::HOST_EMAIL];
		$recordingFiles = $meeting[self::RECORDING_FILES];
		$downloadURL = $this->getDownloadUrl($recordingFiles);
		$meetingId = $meeting[self::MEETING_ID];
		return array($accountId, $downloadToken, $hostEmail, $downloadURL, $meetingId);
	}

	/**
	 * @param $data
	 * @return string
	 */
	private function extractAccountIdFromDeAuthPayload($data)
	{
		$payload = $data[self::PAYLOAD];
		$accountId = $payload[self::ACCOUNT_ID];
		return $accountId;
	}


	/**
	 * @param $meetingId
	 * @param VendorIntegration $zoomIntegration
	 * @param $accountId
	 * @return array
	 * @throws Exception
	 */
	private function extractCoHosts($meetingId, $zoomIntegration, $accountId)
	{
		$emails = array();
		$retrieveDataFromZoom = new RetrieveDataFromZoom();
		$meetingApi = str_replace('@meetingId@', $meetingId, self::API_PARTICIPANT);
		list($tokens, $participants) = $retrieveDataFromZoom->retrieveZoomDataAsArray($meetingApi, false, $zoomIntegration->getTokens(), $accountId);
		if ($zoomIntegration->getAccessToken() !== $tokens[kZoomOauth::ACCESS_TOKEN]) // token changed -> refresh tokens
			$this->saveNewTokenData($tokens, $accountId, $zoomIntegration);
		if ($participants) {
			$participants = $participants[self::PARTICIPANTS];
			foreach ($participants as $participant) {
				if (isset($participant[self::USER_EMAIL]) && $participant[self::USER_EMAIL])
					$emails[] = $participant[self::USER_EMAIL];
			}
		}
		return $emails;
	}
}