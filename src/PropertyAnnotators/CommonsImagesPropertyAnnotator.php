<?php
namespace SESP\PropertyAnnotators;

use FormatJson;
use Hooks;
use MediaWiki\MediaWikiServices;
use MWException;
use ObjectCache;
use SMW\DIProperty;
use SMW\SemanticData;
use SMWDIString as DIString;
use SESP\PropertyAnnotator;
use SESP\AppFactory;

/**
 * @private
 * @ingroup SESP
 *
 * @license GNU GPL v2+
 * @since 2.0
 *
 * @author Pavel Astakhov
 */
class CommonsImagesPropertyAnnotator implements PropertyAnnotator {

	/**
	 * Predefined property ID
	 */
	const PROP_ID = '___COMMONSIMAGE';

	const CACHE_VERSION = 3;

	const CACHE_TTL = 86400;

	/**
	 * @var AppFactory
	 */
	private $appFactory;

	/**
	 * @since 2.0
	 *
	 * @param AppFactory $appFactory
	 */
	public function __construct( AppFactory $appFactory ) {
		$this->appFactory = $appFactory;
	}

	/**
	 * @since 2.0
	 *
	 * {@inheritDoc}
	 */
	public function isAnnotatorFor( DIProperty $property ) {
		return $property->getKey() === self::PROP_ID;
	}

	/**
	 * @since 2.0
	 *
	 * {@inheritDoc}
	 */
	public function addAnnotation( DIProperty $property, SemanticData $semanticData ) {
		$page = $this->appFactory->newWikiPage( $semanticData->getSubject()->getTitle() );
		$title = $page->getTitle();
		if ( !$title ) {
			return;
		}

		$dbKey = $title->getDBkey();
		if ( !Hooks::run( 'CommonsImagesPropertyAnnotator', [ $title, &$dbKey ] ) ) {
			return;
		}

		$mwServices = MediaWikiServices::getInstance();
		$lang = $mwServices->getContentLanguage()->getCode();
		$url = "https://$lang.wikipedia.org/w/api.php?" . wfArrayToCgi( [
			'action' => 'query',
			'prop' => 'pageimages',
			'titles' => $dbKey,
			'redirects' => '1',
			'piprop' => 'name',
			'format' => 'json',
		] );
		$cache = ObjectCache::getInstance( CACHE_ANYTHING );
		$cacheKey = $cache->makeKey( __CLASS__, $url );
		$cacheValue = $cache->get( $cacheKey );
		if ( $cacheValue ) {
			if ( ( $cacheValue['version'] ?? null ) === self::CACHE_VERSION ) {
				$pageImage = $cacheValue['pageImage'] ?? null;
				if ( $pageImage ) {
					$semanticData->addPropertyObjectValue(
						$property,
						new DIString( $pageImage )
					);
				}
				return;
			}
		}
		$request = $mwServices->getHttpRequestFactory()->create( $url, [], __METHOD__ );

		try {
			$status = $request->execute();
			if ( $status->isOK() ) {
				$response = FormatJson::decode( $request->getContent(), true );
				$pages = $response['query']['pages'] ?? null;
				if ( $pages ) {
					$p = array_pop( $pages );
					$pageImage = $p['pageimage'] ?? null;
					$cache->set( $cacheKey, [ 'version' => self::CACHE_VERSION, 'pageImage' => $pageImage ], self::CACHE_TTL );
					if ( $pageImage ) {
						$semanticData->addPropertyObjectValue(
							$property,
							new DIString( $pageImage )
						);
						return;
					}
				}
			}
			$semanticData->removeProperty( $property );
		} catch ( MWException $ex ) {
			$semanticData->removeProperty( $property );
		}
	}
}
