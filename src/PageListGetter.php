<?php

namespace App;

use Mediawiki\Api\Service\PageListGetter as AddWikiPageListGetter;
use Mediawiki\Api\MediawikiFactory;

class PageListGetter extends AddWikiPageListGetter {

	/** @var int[] */
	protected $namespaceIds;

	public function getNamespaceId( $canonicalName ) {
		if ( isset( $this->namespaceIds[$canonicalName] ) ) {
			return $this->namespaceIds[$canonicalName];
		}
		$factory = new MediawikiFactory( $this->api );
		$this->namespaceIds[$canonicalName] = $factory
			->newNamespaceGetter()
			->getNamespaceByCanonicalName( $canonicalName )
			->getId();

		return $this->namespaceIds[$canonicalName];
	}

	/**
	 * @return \Mediawiki\DataModel\Pages
	 */
	public function getPagesInNamespace( $canonicalName ) {
		$params = [
			'list' => 'allpages',
			'apnamespace' => $this->getNamespaceId( $canonicalName ),
			'apfrom' => '0',
			'apfilterredir' => 'nonredirects',
			'aplimit' => 500,
		];

		return $this->runQuery( $params, 'apcontinue', 'allpages' );
	}

	/**
	 * Get all pages that have the given prefix.
	 *
	 * @link https://www.mediawiki.org/wiki/API:Allpages
	 *
	 * @param string $prefix The page title prefix.
	 *
	 * @return Pages
	 */
	public function getFromPrefix($prefix, $nsId = 0) {
		$params = [
			'list' => 'allpages',
			'apprefix' => $prefix,
			'apnamespace' => $nsId,
		];
		return $this->runQuery($params, 'apcontinue', 'allpages');
		// $contName = 'apcontinue';
		// $resName = 'allpages';

		// do {
		// 	// Set up continue parameter if it's been set already.
		// 	if ( isset( $result['continue'][$contName] ) ) {
		// 		$params[$contName] = $result['continue'][$contName];
		// 	}

		// 	// Run the actual query.
		// 	$result = $this->api->getRequest( new SimpleRequest( 'query', $params ) );
		// 	if ( !array_key_exists( 'query', $result ) ) {
		// 		return $pages;
		// 	}

		// 	// Add the results to the output page list.
		// 	foreach ( $result['query'][$resName] as $member ) {
		// 		// Assign negative pageid if page is non-existent.
		// 		if ( !array_key_exists( $pageIdName, $member ) ) {
		// 			$member[$pageIdName] = $negativeId;
		// 			$negativeId = $negativeId - 1;
		// 		}

		// 		$pageTitle = new Title( $member['title'], $member['ns'] );
		// 		$page = new Page( new PageIdentifier( $pageTitle, $member[$pageIdName] ) );
		// 		$pages->addPage( $page );
		// 	}

		// } while ( $cont && isset( $result['continue'] ) );
	}
}
