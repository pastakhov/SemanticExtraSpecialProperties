<?php
namespace SESP\PropertyAnnotators;

use Exception;
use PageImages;
use SMW\DIProperty;
use SMW\DIWikiPage;
use SMW\SemanticData;
use SMWDataItem as DataItem;
use SESP\PropertyAnnotator;
use SESP\AppFactory;
use Title;
use Wikimedia\Rdbms\DBConnRef;

/**
 * @private
 * @ingroup SESP
 *
 * @license GNU GPL v2+
 * @since 2.0
 *
 * @author Pavel Astakhov
 */
class SharedPageImagesPropertyAnnotator implements PropertyAnnotator {

	/**
	 * Predefined property ID
	 */
	const PROP_ID = '___SHAREDPAGEIMAGE';

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
		global $wgSharedDB;

		if ( !$wgSharedDB ) {
			return;
		}

		$page = $this->appFactory->newWikiPage( $semanticData->getSubject()->getTitle() );
		$title = $page->getTitle();
		if ( !$title ) {
			return;
		}

		try {
			$foreignDb = wfGetDB( DB_MASTER, [], $wgSharedDB );

			$foreignPageId = $this->getForeignPageId( $foreignDb, $title );

			// Copied from PageImages::getPageImage()
			$fileName = $foreignDb->selectField(
				'page_props',
				'pp_value',
				[
					'pp_page' => $foreignPageId,
					'pp_propname' => [ 'page_image', 'page_image_free' ]
				],
				__METHOD__,
				[ 'ORDER BY' => 'pp_propname' ]
			);
			$file = false;
			if ( $fileName ) {
				$file = wfFindFile( $fileName );
			}
			if ( !$file ) {
				return;
			}

			$fileTitle = $file->getTitle();
			$dataItem = null;
			if ( $fileTitle instanceof Title ) {
				$dataItem = DIWikiPage::newFromTitle( $fileTitle );
			}

			if ( $dataItem instanceof DataItem ) {
				$semanticData->addPropertyObjectValue( $property, $dataItem );
			}
		} catch ( Exception $ex ) {

		}
	}

	private function getForeignPageId( DBConnRef $foreignDb, Title $title ) {
		$pageId = $foreignDb->selectField(
			'page',
			'page_id',
			[
				'page_namespace' => $title->getNamespace(),
				'page_title' => $title->getDBkey(),
			],
			__METHOD__
		);
		if ( !$pageId ) {
			$pageId = $foreignDb->selectField(
				'page',
				'page_id',
				[
					'page_namespace' => 0,
					'page_title' => $title->getDBkey(),
				],
				__METHOD__
			);
		}
		return $pageId;
	}
}
