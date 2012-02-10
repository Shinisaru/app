<?php

abstract class VideoFeedIngester {
	const PROVIDER_SCREENPLAY = 'screenplay';
	const PROVIDER_MOVIECLIPS = 'movieclips';
	const PROVIDER_REALGRAVITY = 'realgravity';
	public static $PROVIDERS = array(self::PROVIDER_SCREENPLAY, self::PROVIDER_MOVIECLIPS, self::PROVIDER_REALGRAVITY);
	protected static $CLIP_TYPE_BLACKLIST = array();
	protected static $API_WRAPPER;
	protected static $PROVIDER;
	private static $instances = array();
	
	protected static $CACHE_KEY = 'videofeedingester';
	protected static $CACHE_EXPIRY = 3600;

	private static $WIKI_INGESTION_DATA_VARNAME = 'wgPartnerVideoIngestionData';
	private static $WIKI_INGESTION_DATA_FIELDS = array('keyphrases', 'movieclipsIds');

	abstract public function import($file, $params);
	abstract protected function generateName(array $data);
	abstract protected function generateTitleName(array $data);
	abstract protected function generateParsedData(array $data, &$errorMsg);
	abstract protected function generateCategories(array $data, $addlCategories);
	
	public static function getInstance($provider='') {
		if (empty($provider)) {
			$className = __CLASS__;
		}
		else {
			$className = ucfirst($provider) . 'FeedIngester';
			if (!class_exists($className)) {
				return null;
			}
		}
		
		if (empty(self::$instances[$className])) {
			self::$instances[$className] = new $className();
		}
		
		return self::$instances[$className];
	}

	public function createVideo(array $data, &$msg, $params=array()) {
		$debug = !empty($params['debug']);
		$addlCategories = !empty($params['addlCategories']) ? $params['addlCategories'] : array();
		
		$id = $data['videoId'];
		$name = $this->generateName($data);
		$parsedData = $this->generateParsedData($data, $msg);
		if (!empty($msg)) {
			return 0;
		}
		
		$title = $this->makeTitleSafe($name);
		if(is_null($title)) {
			$msg = "article title was null: clip id $id. name: $name";
			return 0;
		}
		if(!$debug && $title->exists()) {
			// don't output duplicate error message
			return 0;
		}	

		$categories = $this->generateCategories($data, $addlCategories);
		$categories[] = 'Video';
		$categoryStr = '';
		foreach ($categories as $categoryName) {
			$category = Category::newFromName($categoryName);
			$categoryStr .= '[[' . $category->getTitle()->getFullText() . ']]';
		}
		
		if ($debug) {
			print "parsed partner clip id $id. name: {$title->getText()}. categories: " . implode(',', $categories) . ". ";
			print "metadata: \n";
			print_r($parsedData);
			return 1;
		}
		else {
			$apiParams = array('parsedData'=>$parsedData);
			
			if (is_subclass_of(static::$API_WRAPPER, 'WikiaVideoApiWrapper')) {
				$apiParams['videoId'] = $id;
				$videoId = $name;
			}
			else {
				$videoId = $id;
				
			}			

			$apiWrapper = new static::$API_WRAPPER($videoId, $apiParams);
			$uploadedTitle = null;
			$result = VideoHandlersUploader::uploadVideo(static::$PROVIDER, $videoId, $uploadedTitle, null, $categoryStr.$apiWrapper->getDescription(), false);
			if ($result->ok) {
				print "Ingested {$uploadedTitle->getText()} from partner clip id $id. {$uploadedTitle->getFullURL()}\n\n";
				return 1;
			}
		}

		return 0;
	}

	protected function makeTitleSafe($name) {
		return Title::makeTitleSafe(NS_FILE, $name);    // makeTitleSafe strips '#' and anything after. just leave # out
	}		
	
	public function getWikiIngestionData() {
		$data = array();
		
		// merge data from datasource into a data structure keyed by 
		// partner API search keywords. Value is an array of categories
		// relevant to wikis
		$rawData = $this->getWikiIngestionDataFromSource();
		foreach ($rawData as $cityId=>$cityData) {
			if (is_array($cityData)) {
				foreach (self::$WIKI_INGESTION_DATA_FIELDS as $field) {
					if (!empty($cityData[$field]) && is_array($cityData[$field])) {
						foreach ($cityData[$field] as $fieldVal) {
							if (!empty($data[$field][$fieldVal]) && is_array($data[$field][$fieldVal])) {
								$data[$field][$fieldVal] = array_merge($data[$field][$fieldVal], $cityData['categories']);
							}
							else {
								$data[$field][$fieldVal] = $cityData['categories'];
							}
						}
					}
				}
			}
		}
		
		return $data;
	}

	protected function getWikiIngestionDataFromSource() {
		global $wgExternalSharedDB, $wgMemc;
		
		
		$memcKey = wfMemcKey( self::$CACHE_KEY );
		$aWikis = $wgMemc->get( $memcKey );
		if ( !empty( $aWikis ) ) {
			return $aWikis;
		}

		$aWikis = array();
		
		// fetch data from DB
		// note: as of 2011/11, this function is referred to by only one
		// calling function, a script that is run once per day. No need 
		// to memcache result yet.
		$dbr = wfGetDB(DB_SLAVE, array(), $wgExternalSharedDB);
		
		$aTables = array(
			'city_variables',
			'city_variables_pool',
			'city_list',
		);
		$varName = mysql_real_escape_string(self::$WIKI_INGESTION_DATA_VARNAME);
		$aWhere = array('city_id = cv_city_id', 'cv_id = cv_variable_id');
		
		$aWhere[] = "cv_value is not null";	
		
		$aWhere[] = "cv_name = '$varName'";


		$oRes = $dbr->select(
			$aTables,
			array('city_id', 'cv_value'),
			$aWhere,
			__METHOD__,
			array('ORDER BY' => 'city_sitename')
		);

		while ($oRow = $dbr->fetchObject($oRes)) {
			$aWikis[$oRow->city_id] = unserialize($oRow->cv_value);
		}
		$dbr->freeResult( $oRes );
		
		$wgMemc->set( $memcKey, $aWikis, self::$CACHE_EXPIRY );

		return $aWikis;
	}

	protected function getUrlContent($url) {
		return Http::get($url);
	}
	
	/**
	 * Try to find keyphrase in the subject. A keyphrase could be 
	 * "harry potter". A keyphrase is present in the subject if "harry" and
	 * "potter" are present.
	 * @param string $subject
	 * @param string $keyphrase
	 * @return boolean 
	 */
	protected function isKeyphraseInString($subject, $keyphrase) {
		$keyphraseFound = false;
		$keywords = explode(' ', $keyphrase);
		$keywordMissing = false;
		foreach ($keywords as $keyword) {
			if (stripos($subject, $keyword) === false) {
				$keywordMissing = true;
				break;
			}
		}
		if (!$keywordMissing) {
			$keyphraseFound = true;
		}
		
		return $keyphraseFound;
	}

	protected function isClipTypeBlacklisted(array $clipData) {
		// assume that a clip with properties that match exactly undesired
		// values should not be imported. This assumption will have to
		// change if we consider values that fall into a range, such as
		// duration < MIN_VALUE
		if (is_array(static::$CLIP_TYPE_BLACKLIST)) {
			$arrayIntersect = array_intersect(static::$CLIP_TYPE_BLACKLIST, $clipData);
			if ($arrayIntersect == static::$CLIP_TYPE_BLACKLIST) {
				return true;
			}
		}
		
		return false;
	}
	
}