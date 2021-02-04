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

		if ( !$wgSharedDB || !class_exists( 'PageImages' ) ) {
			return;
		}

		$page = $this->appFactory->newWikiPage( $semanticData->getSubject()->getTitle() );
		$title = $page->getTitle();

		try {
			$foreignDb = wfGetDB( DB_MASTER, [], $wgSharedDB );

			// Copied from PageImages::getPageImage()
			$fileName = $foreignDb->selectField( 'page_props',
				'pp_value',
				[
					'pp_page' => $title->getArticleID(),
					'pp_propname' => [ PageImages::PROP_NAME, PageImages::PROP_NAME_FREE ]
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
}
