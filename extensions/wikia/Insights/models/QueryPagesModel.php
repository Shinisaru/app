<?php

/**
 * Class QueryPagesModel
 *
 * Model for pages which extends QueryPage
 */
class QueryPagesModel extends InsightsModel {
	private $queryPageInstance;


	public function __construct( $className, $wikiId ) {
		if ( !class_exists( $className ) ) {
			throw new MWException("Class $className doesn't exist.");
		}
		$this->queryPageInstance = new $className();
		parent::__construct( $wikiId );
	}

	/**
	 * Get list of article
	 *
	 * @param int $limit
	 * @return array
	 */
	public function getList( $offset = 0, $limit = 100 ) {
		$data = [];

		$res = $this->queryPageInstance->doQuery( $offset, $limit );
		if ( $res->numRows() > 0 ) {
			$data = $this->prepareData( $res );
			// TODO: initial work for fetching page views
			//$articleIds = array_keys( $data );
			//$this->getArticlesPageviews( $articleIds, $this->wikiId );
		}

		return $data;
	}

	private function prepareData( $res ) {
		$data = [];
		$dbr = wfGetDB( DB_SLAVE );

		while ( $row = $dbr->fetchRow( $res ) ) {
			if ( $row['title'] ) {
				$article = [];
				$title = Title::newFromText( $row['title'] );

				$article['title'] = $title->getText();
				$article['link'] = $title->getFullURL();

				$lastRev = $title->getLatestRevID();
				$rev = Revision::newFromId( $lastRev );

				if ( $rev ) {
					$article['revision'] = $this->prepareRevisionData( $rev );
					$data[ $title->getArticleID() ] = $article;
				}
			}
		}

		return $data;
	}

	/**
	 * Get data about revision
	 * Who and when made last edition
	 *
	 * @param Revision $rev
	 * @return mixed
	 */
	private function prepareRevisionData( Revision $rev ) {
		$data['timestamp'] = wfTimestamp(TS_UNIX, $rev->getTimestamp());

		$user = $rev->getUserText();
		$userpage = Title::newFromText( $user, NS_USER )->getFullURL();

		$data['username'] = $user;
		$data['userpage'] = $userpage;

		return $data;
	}
} 
