<?php

 /*****************************
 * Includes & Globals
 */
//ini_set("memory_limit","2048M");

	/****************************
	 * KChunkedEncodeJobData
	 */
	class KChunkedEncodeJobData
	{
		const STATE_PENDING = 0;
		const STATE_RUNNING = 1;
		const STATE_SUCCESS = 2;
		const STATE_FAIL = -1;
		const STATE_RETRY = -2;
		
		public $session = null;
		public $keyIdx = 0;			// memcache ogject id (1..N)
		
		public $id = null;			// chunk id (0..N)
		public $cmdLine = null;
		public $state = self::STATE_PENDING;

		public $maxExecTime = 0; 	// Max chunk execution time
		public $sessionTime = 0;	// Session creation time (secs), batch asset start time
		public $createTime = 0;		// Chunk job creation time (secs), by batch srv
		public $queueTime = 0;		// Retrived from the queue time (secs), by scheduler
		public $startTime = 0;		// Excution start/finish times (secs), by final exection party
		public $finishTime = 0;

		public $process = 0;		// Linux process id
		public $hostname = 0;
		
		public $attempt = 0; //Job execution attempt
		public $history = null; //job execution history in case of failures
		
		/* ---------------------------
		 *
		 */
		public function __construct($session=null, $id=null, $cmdLine=null, $sessionTime=0)
		{
			$this->session = $session;
			
			$this->id = $id;
			$this->cmdLine = $cmdLine;

			$this->sessionTime = $sessionTime;
			$this->createTime = time();
		}
		
		/* ---------------------------
		 *
		 */
		public function isFinished()
		{
			if($this->state==self::STATE_SUCCESS || $this->state==self::STATE_FAIL) {
				return true;
			}
			return false;
		}
		
		/* ---------------------------
		 *
		 */
		public function isRetry()
		{
			if($this->state==self::STATE_RETRY) {
				return true;
			}
			return false;
		}
		
		/* ---------------------------
		 *
		 */
		public function resetParams()
		{
			$this->history[] = array('h' => $this->hostname, 'id' => $this->id, 'st' =>$this->startTime);
			
			//Reset job timestamps
			$this->queueTime = 0;
			$this->startTime = 0;
		}
	}
	/*****************************
	 * End of KChunkedEncodeJobData
	 *****************************/
	
	/****************************
	 * KChunkedEncodeJobsContainer
	 */
	class KChunkedEncodeJobsContainer {
		public $jobs = array();
		public $failed = array();
		public $states = array();

		/* ---------------------------
		 *
		 */
		public function sumJobsStates()
		{
			$states = array(KChunkedEncodeJobData::STATE_PENDING=>0,
							KChunkedEncodeJobData::STATE_RUNNING=>0,
							KChunkedEncodeJobData::STATE_SUCCESS=>0,
							KChunkedEncodeJobData::STATE_FAIL=>0,
							KChunkedEncodeJobData::STATE_RETRY=>0);
			foreach($this->jobs as $job){
				$states[$job->state] = $states[$job->state]+1;
				if($job->state==KChunkedEncodeJobData::STATE_FAIL) {
					if(!array_key_exists($job->id, $this->failed)){
						$this->failed[$job->id] = $job->keyIdx;
					}
				}
			}
			$this->states = $states;
		}

		/* ---------------------------
		 * Fetch
		 */
		public function Fetch($manager)
		{

			foreach($this->jobs as $idx=>$job) {
				if(!isset($job))
					continue;
				$job = $manager->FetchJob($job->keyIdx);
				if($job===false)
					return false;

				$this->jobs[$job->id] = $job;
			}
			return true;
		}
		
		/* ---------------------------
		 * detectErrors
		 */
		public function detectErrors($manager, $chunkedEncodeReadIdx, $chunker)
		{
			$this->sumJobsStates();
			$setup = $chunker->setup;	

			$maxChunks = $chunker->GetMaxChunks();
			$chunkDurThreshInSec=$chunker->chunkDurThreshInFrames*$chunker->params->frameDuration;
			foreach($this->jobs as $idx=>$job) {
				if($job->state==$job::STATE_SUCCESS) {			
					$chunkData = $chunker->GetChunk($job->id);
					if(isset($job->stat)){
							/*
							 * Validate chunk dur
							 */
							// Calc the generated chunk duration
						$generatedChunkDur = $job->stat->finish-$job->stat->start;
							// For the last chunk - no need to validate chunk dur
						if($job->id==$maxChunks-1)
							continue;
						if($chunkData->gap-$chunkDurThreshInSec > $generatedChunkDur){
								// Too short chunk duration mostly caused by low framerate 
								// and missing frames at the chunk end (regular P/B, not I).
								// Solution - gradually increase the chunk overlap, 
								// by 1 sec on each failed attempt.
							$job->state = $job::STATE_FAIL;
							$overlap=$setup->chunkOverlap + 1*($job->attempt+1);
							KalturaLog::log("Chunk job (id:$job->id) failed on too short chunk dur. Overlap increased to $overlap sec");
								// Update the chunk duration with increased overlapping 
							$pattern = '/-t\s+([\w.]+)/';
							$dur = $setup->chunkDuration+$overlap;
							$cmdLine = preg_replace_callback($pattern, function ($matches) use ($dur) {
											return '-t '.$dur;}, $job->cmdLine[0]);
							$job->cmdLine[0]=$cmdLine;
							$manager->SaveJob($job);
							$this->jobs[$idx] = $job;
							$this->sumJobsStates();
						}
					}
					continue;
				}
				
				if($job->state==$job::STATE_FAIL) {
						// The last chunk generation might be affected by improperly set source metadata.
						// Occationally, the file ends before reaching the timing that was declared in the metadata.
						// This results the last chunk failure.
						// To handle such cases, the failed job of the last chunk, forced to SUCCESS state after 
						// few generation attempts.
					if($job->id==$maxChunks-1 && $job->msg=="missing chunk stat"){
						KalturaLog::log("Chunk job (id:$job->id, last) failed on missing chunk stat. Retry attempt $job->attempt");
						if($job->attempt>1) {
							$job->state = $job::STATE_SUCCESS;
							$manager->SaveJob($job);
						}
					}
				}
				
				if($job->state==$job::STATE_RETRY) {
					$job->queueTime = 0;
					$job->startTime = 0;
					$job->state = $job::STATE_PENDING;
					$tmpKey = $job->keyIdx;
					$manager->AddJob($job);
					$this->jobs[$idx] = $job;
					KalturaLog::log("Retrying chunk ($job->id) - oldKeyIdx:$tmpKey, newKeyIdx:$job->keyIdx, rdIdx:$chunkedEncodeReadIdx");
				}
				
				if($job->startTime==0 || $job->state==$job::STATE_RETRY){
						/*
						 * Check for 'job skip' condition
						 * when scheduler skips over a valid job in the queue
						 */
					if($job->keyIdx<$chunkedEncodeReadIdx-1) {
						KalturaLog::log("Potential 'job skip' case - jobId:$job->id,state: $job->state,jobKeyIdx:$job->keyIdx,rdIdx:$chunkedEncodeReadIdx");
						/*
						 * Try 10 attempts to re-fetch the 'skipped' job -
						 * in order to give the sceduler an opportunity to update the job status
						 */
						$maxTry=10;
						for($try=0; $try<$maxTry; $try++) {
							$job = $manager->FetchJob($job->keyIdx);
							if($job===false || !(($job->startTime==0 || $job->state==$job::STATE_RETRY))){
								break;
							}
							KalturaLog::log("Attempt($try) to refetch job ($job->id)");
							sleep(1);
						}
						/*
						 * If failed to refetch - push the job into the jobs queue
						 */
						if($job===false) {
							$job = $this->jobs[$idx];
							KalturaLog::log("Retry chunk ($job->id) - failed to fetch job (jobKeyIdx:$job->keyIdx,state: $job->state,rdIdx:$chunkedEncodeReadIdx)");
							$this->retryJob($manager, $this->jobs[$idx]);
						}
						/*
						 * if the job still 'skipped' - push the job into the jobs queue
						 */
						else if($try==$maxTry) {
							KalturaLog::log("Retry chunk ($job->id) - skipped by the chunk job scheduler (jobKeyIdx:$job->keyIdx,state: $job->state,rdIdx:$chunkedEncodeReadIdx)");
							$this->retryJob($manager, $job);
						}
					}
					continue;
				}
				$elapsed = time()-$job->startTime;

				if($job->maxExecTime>0)
					$maxExecutionTime = $job->maxExecTime;
				else
					$maxExecutionTime = $this->maxExecutionTime;
				if($elapsed>$maxExecutionTime) {
					/*
					 * The bellow cond was DISABLED to prevent endless loop and stuck chk on 2nd and further chk job retries.
					 * Remarked and left as a reference, since the original need for that cond is unclear
					 */
//					if(!array_key_exists($job->id, $this->failed))
					{
						$job->timeout=1;
						KalturaLog::log("($job->id, atm:$job->attempt)doubled the maxExecutionTime,$job->maxExecTime,elapsed:$elapsed");
						$this->failed[$job->id] = $job->keyIdx;
					}
				}
			}
			return true;
		}

		/* ---------------------------
		 * retryJob
		 */
		protected function retryJob($manager, $job)
		{
			KalturaLog::log("id:$job->id, keyIdx:$job->keyIdx, state:$job->state");
			$job->state = $job::STATE_RETRY;
			$manager->SaveJob($job);
		}
		
	}
	/*****************************
	 * End of KChunkedEncodeJobsContainer
	 *****************************/

	 /****************************
	 * KChunkedEncodeSessionManager
	 */
	class KChunkedEncodeSessionManager extends KBaseChunkedEncodeSessionManager 
	{
			/*
			 * Video/Audio chunk jobs, indexed with chunk id
			 */
		protected $videoJobs = null;	
		protected $audioJobs = null;
		
		protected $storeManager = null;

		/* ---------------------------
		 * C'tor
		 */
		public function __construct(KChunkedEncodeSetup $setup, $storeManager, $name=null)
		{
			parent::__construct($setup, $name);
			if(!isset($this->chunker->setup->concurrent))
				$this->chunker->setup->concurrent = 20;
			
			$this->storeManager = $storeManager;

			$this->videoJobs = new KChunkedEncodeJobsContainer();	
			$this->audioJobs = new KChunkedEncodeJobsContainer();
		}

		/* ---------------------------
		 *
		 */
		public function getVideoJobs() { return $this->videoJobs->jobs; }
		public function getAudioJobs() { return $this->audioJobs->jobs; }
		//public function setVideoJobs($val) { $this->videoJobs->jobs = $val; }
		
		/* ---------------------------
		 *
		 */
		public function getName() { return $this->name;}
		public function setName($val) { $this->name = $val;}
		
		/* ---------------------------
		 *
		 */
		public function getMaxConcurrent() { return $this->chunker->setup->concurrent;}
		public function setMaxConcurrent($val) { $this->chunker->setup->concurrent = $val;}
		
		/* ---------------------------
		 * getJobsStates
		 */
		public function getJobsStates(&$videoStats, &$audioStats) { 
			$videoStats = $this->videoJobs->states;
			$audioStats = $this->audioJobs->states;
		}

		/* ---------------------------
		 * getElapsed
		 */
		public function getElapsed() { 
			return (time()-$this->createTime);
		}

		/* ---------------------------
		 * IsFinished
		 */
		public function IsFinished()
		{
			$videoStats = $this->videoJobs->states;
			$audioStats = $this->audioJobs->states;
			$finished = $videoStats[KChunkedEncodeJobData::STATE_SUCCESS] +
						$videoStats[KChunkedEncodeJobData::STATE_FAIL] +
						$audioStats[KChunkedEncodeJobData::STATE_SUCCESS] +
						$audioStats[KChunkedEncodeJobData::STATE_FAIL];
			return ($finished==(count($this->videoCmdLines)+count($this->audioCmdLines)));
		}
		
		/* ---------------------------
		 * GenerateContent
		 */
		public function GenerateContent()
		{
			$this->addAudioJobs();
				/*
				 * Concurrency statistics calcs 
				 */
			$this->concurrencyHistogram = array();
			$this->concurrencyAccum = 0;
			$curr = microtime(true);
			while(1) {
				$rv=$this->processVideoJobs();
				if($rv!==null)
					break;
				sleep(2);
				$tm = microtime(true);
				
				$running = $this->videoJobs->states[KChunkedEncodeJobData::STATE_RUNNING]
						 + $this->audioJobs->states[KChunkedEncodeJobData::STATE_RUNNING];
				$elapsed = round(($tm-$curr)*1000);
				if(!array_key_exists($running, $this->concurrencyHistogram)){
					$this->concurrencyHistogram[$running] = $elapsed;
				}
				else {
					$this->concurrencyHistogram[$running]+= $elapsed;
				}
				$this->concurrencyAccum+=($running*$elapsed);
				$curr = $tm;
			}
			return $rv;
		}
		
		/* ---------------------------
		 * Analyze
		 */
		public function Analyze()
		{
			$chunker = $this->chunker;
			$videoJobs = $this->getVideoJobs();
			foreach($videoJobs as $job) {
				$chunker->updateChunkFileStatData($job->id,$job->stat,isset($job->outFileSizes) ? $job->outFileSizes : array());
				if(isset($chunker->setup->sharedChunkPath)){
					$logFilename = $chunker->getChunkName($job->id,"shared_base").".log";
				}
				else
					$logFilename = $chunker->getChunkName($job->id,".log");

				$execData = new KProcessExecutionData($job->process, $logFilename);
				$execData->startedAt = $job->startTime;
				$this->chunkExecutionDataArr[$job->id] = $execData;
			}
			$toFixCnt = $chunker->CheckChunksContinuity();
			return $toFixCnt;
		}
		
		/* ---------------------------
		 * fetch
		 */
		protected function fetch()
		{
			if($this->videoJobs->Fetch($this->storeManager)===false)
				return false;
			if($this->audioJobs->Fetch($this->storeManager)===false)
				return false;
			
			return true;
		}

		/* ---------------------------
		 * detectErrors
		 */
		protected function detectErrors($chunkedEncodeReadIdx, $chunker)
		{		
			if($this->videoJobs->detectErrors($this->storeManager, $chunkedEncodeReadIdx, $chunker)!=true)
				return false;
			return $this->audioJobs->detectErrors($this->storeManager, $chunkedEncodeReadIdx, $chunker);
		}

		/* ---------------------------
		 * processFailed
		 */
		protected function processFailed()
		{
			if(count($this->videoJobs->failed)>$this->maxFailures) {
				KalturaLog::log("FAILED - too many failures per session (".count($this->videoJobs->failed)
.", maxFailures:$this->maxFailures)");
				return false;
			}
			if(count($this->videoJobs->failed)>0)
				KalturaLog::log("Retrying failed chunks(".count($this->videoJobs->failed).")");

			foreach($this->videoJobs->failed as $idx=>$keyIdx){
				$job = $this->videoJobs->jobs[$idx];
				if($job->state==$job::STATE_SUCCESS) {
					unset($this->videoJobs->failed[$idx]);
					continue;
				}

				if($this->processFailedJob($job)==false){
					return false;
				}

				$this->videoJobs->jobs[$job->id] = $job;
			}

			if(count($this->audioJobs->jobs)>0) {
			$job = $this->audioJobs->jobs[0];
				if($job->state!=$job::STATE_SUCCESS) {
					if($this->processFailedJob($job)==false){
						return false;
					}
					$this->audioJobs->jobs[$job->id] = $job;
				}
			}
			return true;
		}
		
		/* ---------------------------
		 * processVideoJobs
		 */
		protected function processVideoJobs()
		{
			if($this->fetch()==false) {
				KalturaLog::log($msgStr="Session($this->name) - Result:FAILED to fetch chunk jobs!");
				$this->returnMessages[] = $msgStr;
				$this->returnStatus = KChunkedEncodeReturnStatus::GenerateVideoError;
				return false;			
			}
			
			$writeIndex = $readIndex = null;
			if($this->storeManager->fetchReadWriteIndexes($writeIndex, $readIndex)===false){
				KalturaLog::log($msgStr="Session($this->name) - Result:FAILED could not get RD/WR indexes!");
				$this->returnMessages[] = $msgStr;
				$this->returnStatus = KChunkedEncodeReturnStatus::GenerateVideoError;
				return false;
			}
			
			if($this->detectErrors($readIndex, $this->chunker)===false){
				KalturaLog::log($msgStr="Session($this->name) - Result:FAILED to handle broken chunks!");
				$this->returnMessages[] = $msgStr;
				$this->returnStatus = KChunkedEncodeReturnStatus::GenerateVideoError;
				return false;			
			}

			$videoStats = $this->videoJobs->states;
			$audioStats = $this->audioJobs->states;

			$pending = $videoStats[KChunkedEncodeJobData::STATE_PENDING]
					 + $audioStats[KChunkedEncodeJobData::STATE_PENDING];
			$running = $videoStats[KChunkedEncodeJobData::STATE_RUNNING]
					 + $audioStats[KChunkedEncodeJobData::STATE_RUNNING];
			$succeed = $videoStats[KChunkedEncodeJobData::STATE_SUCCESS]
					 + $audioStats[KChunkedEncodeJobData::STATE_SUCCESS];
			$failed  = $videoStats[KChunkedEncodeJobData::STATE_FAIL]
					 + $audioStats[KChunkedEncodeJobData::STATE_FAIL];
			
			$concurrent = $pending+$running;
			$finished   = $succeed+$failed;
			//count($cmdLineArr)+1 ==> video chunks and audio
			$left = count($this->videoCmdLines)+count($this->audioCmdLines)-$finished;
			$loaded = count($this->videoJobs->jobs);

			
			/*
			 * Log statuses
			 */
			{
				$msgStr = "Session($this->name)-stats: ";
				$msgStr.= "rn:$running,pn:$pending,su:$succeed,fa:$failed,lf:$left, ";
				$msgStr.= "vi.ld:$loaded, el:".($this->getElapsed())."s";
				$msgStr.= ", conc:".round($this->concurrencyAccum/(microtime(true)-$this->createTime)/1000,2);
				if(count($this->videoJobs->failed)>0) $msgStr.= ", failedJobs:".serialize($this->videoJobs->failed);
				KalturaLog::log($msgStr);
			}
			
			if($this->IsFinished() && $failed==0) {
				KalturaLog::log("Session($this->name) - Result:SUCCESS! (jobs:$succeed,elapsed:".($this->getElapsed())."sec)");
				return true;
			}

			if($this->processFailed()==false){
				KalturaLog::log($msgStr="Session($this->name) - Result:FAILED too many failed chunks!");
				$this->returnMessages[] = $msgStr;
				$this->returnStatus = KChunkedEncodeReturnStatus::GenerateVideoError;
				return false;			
			}

			/*
			 * Adjust dynamically the new concurrency level to the current chunk Q status/backlog
			 * There are 3 levels that much various chunk job Q states
			 * - low load (job Q < 100) - keep predefined max concurrency (setup::concurrent)
			 * - med load (job Q < 500) - concurrency 10 
			 * - hi  load (job Q < 1000) - concurrency 2
			 *
			 * Make sure that final concurrency match at least the setup::concurrentMin value
			 */
			{
				$globalChunkQueueSize = $writeIndex-$readIndex;
				$setup = $this->chunker->setup;
				if($globalChunkQueueSize<100)
					$newConcurrency = $setup->concurrent;
				else if($globalChunkQueueSize<500)
					$newConcurrency = min(10,$setup->concurrent);
				else if($globalChunkQueueSize<1000)
					$newConcurrency = min(5,$setup->concurrent);
				else 
					$newConcurrency = min(2,$setup->concurrent);

				if(isset($setup->concurrentMin) && $setup->concurrentMin>0)
					$newConcurrency = max($newConcurrency,$setup->concurrentMin);

				KalturaLog::log("Session($this->name)-chkQu: Q:$globalChunkQueueSize,maxConcurr:$setup->concurrent,minConcurr:$setup->concurrentMin,newConcurr:$newConcurrency");
			}
			
			while($loaded<count($this->videoCmdLines)) {
				$cnt = $this->addVideoJobs($concurrent,$newConcurrency);
				if($cnt===false) {
					KalturaLog::log($msgStr="Session($this->name) - Result:FAILED to add jobs");
					$this->returnMessages[] = $msgStr;
					$this->returnStatus = KChunkedEncodeReturnStatus::GenerateVideoError;
					return false;
				}
				if($cnt==0) {
					return null;
				}
				$loaded = count($this->videoJobs->jobs);
				$concurrent+=$cnt;
			}
			return null;
		}
		
		/* ---------------------------
		 * addVideoJobs
		 */
		protected function addVideoJobs($concurrent,$newConcurrency)
		{
			KalturaLog::log("Session($this->name) - concurrent:$concurrent, newConcurrency:$newConcurrency");
			list($vidMaxExecutionTime,$aMax) = $this->chunker->calcMaxExecutionTime($this->maxExecutionTime);
			
			$startChunk = count($this->videoJobs->jobs);
			$chunksToProcess = count($this->videoCmdLines)-$startChunk;
			
			if($chunksToProcess<1){
				KalturaLog::log("Session($this->name) - Bad positioning/count settings");
				return false;
			}

			/*
			 * Evaluate concurrency  
			 */
			{
				if(($concurrent+$chunksToProcess)>$newConcurrency) {
					if($newConcurrency-$concurrent<1){
						KalturaLog::log("Session($this->name) - Reached max concurrent jobs per session($newConcurrency), toProcess:$chunksToProcess,concurrent:$concurrent");
						return 0;
					}
					else
						$chunksToProcess = $newConcurrency-$concurrent;
				}
			}
			
			$cnt = $this->addJobs($startChunk, $chunksToProcess, $vidMaxExecutionTime, $this->videoJobs->jobs);
			return $cnt;
		}

		/* ---------------------------
		 * addAudioJobs
		 */
		protected function addAudioJobs($cmdLines=null)
		{
		        list($vMax,$audMaxExecutionTime) = $this->chunker->calcMaxExecutionTime($this->maxExecutionTime);

			if(isset($cmdLines))
				$this->audioCmdLines = $cmdLines;
			else if(isset($this->audioCmdLines))
				$cmdLines = $this->audioCmdLines;
			if(isset($cmdLines)) {
				foreach($cmdLines as $idx=>$cmdLine) {
					$jobIdx = $idx;
					$job = $this->addJob($jobIdx, $cmdLine, $audMaxExecutionTime);
					if($job===false) {
						KalturaLog::log("Session($this->name) - Failed to add job($jobIdx)");
						return false;
					}
					$this->audioJobs->jobs[$idx] = $job;
				}
			}
			return count($cmdLines);
		}
		
		/* ---------------------------
		 * processFailedJob
		 */
		protected function processFailedJob($job)
		{
			if($job->state==$job::STATE_PENDING)
				return true;
			if($job->state==$job::STATE_RUNNING && !(isset($job->timeout) && $job->timeout==1)) 
				return true;

			KalturaLog::log("Job dump:".serialize($job));
			if(array_key_exists($job->id, $this->audioJobs->jobs) && $this->audioJobs->jobs[$job->id]->process==$job->process)
				$logFilename = $this->chunker->getSessionName("audio").".log";		
			else 
				$logFilename = $this->chunker->getChunkName($job->id,".log");
			$logTail = self::getLogTail($logFilename);
			if(isset($logTail))
				KalturaLog::log("Log dump:\n".$logTail);
			
			if(isset($job->attempt) && $job->attempt>$this->maxRetries){
				KalturaLog::log("FAILED - job id($job->id) exeeded retry limit ($job->attempt, max:$this->maxRetries)");
				return false;
			}
			$job->attempt++;
			
				// For chunk timeouts 'retry attmept' means - extend jobs maxExecutionTime
			if(isset($job->timeout) && $job->timeout==1) {
				$job->maxExecTime=round($job->maxExecTime*1.15);
				$job->timeout=0;
				$this->storeManager->SaveJob($job);
				KalturaLog::log("Extend execution timeout, chunk ($job->id, maxExecTime:$job->maxExecTime, attempt:$job->attempt");
			}
			else {
				$failedIdx = $job->keyIdx;
				$job->state = $job::STATE_RETRY;
				$this->storeManager->SaveJob($job);
		
					//Reset job time stamps to avoid exec timeout when retrying failed job
				$job->resetParams();
				$job->state = $job::STATE_PENDING;
				
				if($this->storeManager->AddJob($job)===false) {
					KalturaLog::log("FAILED to retry job($job->id)");
					return false;
				}
				KalturaLog::log("Retry chunk ($job->id, failedKey:$failedIdx,newKey:$job->keyIdx, attempt:$job->attempt");
			}
			return true;
		}

		/* ---------------------------
		 * addJobs
		 */
		protected function addJobs($startChunk, $countChunks, $maxExecutionTime, &$jobs)
		{
			KalturaLog::log("Session($this->name) - params:startChunk($startChunk), countChunks($countChunks)");
			
			$cmdLines = $this->videoCmdLines;
			if($countChunks>count($cmdLines)){
				KalturaLog::log("Session($this->name) - Invalid countChunks($countChunks), larger than num of cmdlines (".count($cmdLines).")");
				return false;
			}
			
			if($startChunk+$countChunks>count($cmdLines)){
				KalturaLog::log("Session($this->name) - Invalid startChunk($startChunk), final position larger than num of cmdlines (".count($cmdLines).")");
				return false;
			}
			
			for($jobIdx = $startChunk; $jobIdx<$startChunk+$countChunks; $jobIdx++) {
				if(!array_key_exists($jobIdx, $cmdLines)){
					KalturaLog::log("Session($this->name) - Bad cmd-lines index ($jobIdx)");
					return false;
				}

				$job = $this->addJob($jobIdx, $cmdLines[$jobIdx], $maxExecutionTime);
				if($job===false) {
					KalturaLog::log("Session($this->name) - Failed to add job($jobIdx)");
					return false;
				}
				$jobs[$job->id] = $job;
			}

			$cnt = $jobIdx - $startChunk;
			KalturaLog::log("Session($this->name) - Added ($cnt) jobs");
			
			return $cnt;
		}
		
		/* ---------------------------
		 * addJob
		 */
		protected function addJob($jobIdx, $cmdLine, $maxExecutionTime)
		{
			$params = $this->chunker->params;
			$source = $params->resolveSourcePath();
			
			//In PHP8 sending associative array as the subject will cause the returned value to be invalid
			$cmdLine[0] = str_replace($params->unResolvedSourcePath,$source,$cmdLine[0]);
			$job = new KChunkedEncodeJobData($this->name, $jobIdx, $cmdLine, $this->createTime);
			$job->maxExecTime = $maxExecutionTime;
			if($this->storeManager->AddJob($job)===false) {
				return false;
			}
			return $job;
		}
		
		/********************
		 * executeCmdline
		 */
		protected function executeCmdline($cmdLine, $logFile=null)
		{
			$cmdLine = "time $cmdLine >> $logFile 2>&1 ";
			$started = time();
			file_put_contents($logFile, "Started:".date('Y-m-d H:i:s', $started)."\n");
			KalturaLog::log("cmdLine:\n$cmdLine\n");
			return parent::executeCmdline($cmdLine);
		}


	}
	/*****************************
	 * End of KChunkedEncodeSessionManager
	 *****************************/
	
