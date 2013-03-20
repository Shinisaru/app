<?php

class WikiaHubsServicesHelper
{
	const HUBSV2_IMAGES_MEMC_KEY_VER = '1.03';
	const HUBSV2_IMAGES_MEMC_KEY_PREFIX = 'hubv2images';

	/**
	 * Get memcache key for hub images on WikiaHomepage
	 *
	 * @param $lang
	 *
	 * @return string
	 */
	static public function getWikiaHomepageHubsMemcacheKey($lang) {
		return F::app()->wf->SharedMemcKey(
			'wikiahomepage',
			self::HUBSV2_IMAGES_MEMC_KEY_PREFIX,
			$lang,
			self::HUBSV2_IMAGES_MEMC_KEY_VER
		);
	}

	/**
	 * Purge corporate main page varnish
	 *
	 * @param $lang
	 *
	 * @throws Exception
	 */
	public static function purgeHomePageVarnish($lang) {
		$wikiId = static::getCorporateWikiIdByLang($lang);

		$mainPageName = static::getMainPageNameByWikiId($wikiId);
		$mainPageTitle = GlobalTitle::newFromText($mainPageName, NS_MAIN, $wikiId);
		$mainPageTitle->purgeSquid();
	}

	/**
	 * Purge Hub varnish
	 *
	 * @param $lang
	 * @param $verticalId
	 */
	public static function purgeHubVarnish($lang, $verticalId) {
		$wikiId = static::getCorporateWikiIdByLang($lang);
		$hubTitle = GlobalTitle::newFromText(static::getHubName($wikiId, $verticalId), NS_MAIN, $wikiId);
		$hubTitle->purgeSquid();
	}

	/**
	 * Get hub name
	 *
	 * @param $wikiId
	 * @param $verticalId
	 *
	 * @return mixed
	 *
	 * @throws Exception
	 */
	public static function getHubName($wikiId, $verticalId) {
		$hubsV2Pages = static::getHubsV2Pages($wikiId);
		if (!isset($hubsV2Pages[$verticalId])) {
			throw new Exception('There is no hub page for selected wikiId and vertical');
		}
		return $hubsV2Pages[$verticalId];
	}

	/**
	 * Get corporate wikiId by content lang
	 *
	 * @param $lang
	 *
	 * @return int
	 *
	 * @throws Exception
	 */
	public static function getCorporateWikiIdByLang($lang) {
		$visualizationData = static::getVisualizationData();
		if (!isset($visualizationData[$lang]['wikiId'])) {
			throw new Exception('Corporate Wiki not defined for this lang');
		}
		return $visualizationData[$lang]['wikiId'];
	}

	/**
	 * Get mainPage name by wikiId
	 *
	 * @param $wikiId
	 *
	 * @return string
	 *
	 * @throws Exception
	 */
	protected function getMainPageNameByWikiId($wikiId) {
		$dbname = WikiFactory::IDtoDB($wikiId);

		$param = array(
			'action'      => 'query',
			'meta'        => 'allmessages',
			'ammessages'  => 'mainpage',
		);

		$response = ApiService::foreignCall($dbname, $param);
		if (!isset($response['query']['allmessages'][0]['*'])) {
			throw new Exception('There is a problem with getting translation for corporate mainPage name');
		}
		$mainPageName = $response['query']['allmessages'][0]['*'];

		return $mainPageName;
	}

	/**
	 * get data about corporate wikis
	 * @return array
	 */
	protected static function getVisualizationData() {
		$visualizationModel = new CityVisualization();
		return $visualizationModel->getVisualizationWikisData();
	}

	/**
	 * get hubsV2Pages for selected wiki
	 *
	 * @param $wikiId
	 *
	 * @return mixed
	 */
	protected static function getHubsV2Pages($wikiId) {
		return WikiFactory::getVarValueByName('wgWikiaHubsV2Pages', $wikiId);
	}
}
