<?php
namespace SESP\PropertyAnnotators;

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
class PageImagesPropertyAnnotator implements PropertyAnnotator {

	/**
	 * Predefined property ID
	 */
	const PROP_ID = '___PAGEIMAGE';

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
		if ( !class_exists( 'PageImages' ) || !method_exists( 'PageImages', 'getPageImage' ) ) {
			return;
		}

		$page = $this->appFactory->newWikiPage( $semanticData->getSubject()->getTitle() );
		$title = $page->getTitle();
		$file = PageImages::getPageImage( $title );
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
	}
}
