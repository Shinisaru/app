<?php

class TemplateDraftController extends WikiaController {

	/**
	 * Properties used in page_props table, construction:
	 * * tc- - prefix for "template classification"
	 * * -marked- - signifies classification decision made by human
	 * * -auto- - signifies classification decision made by AI
	 * * -infobox - suffix denoting the type of template we identified
	 */
	const TEMPLATE_INFOBOX_PROP = 'tc-marked-infobox';

	/**
	 * Flags indicating type of the template
	 */
	const TEMPLATE_GENERAL = 1;
	const TEMPLATE_INFOBOX = 2;

	/**
	 * Converts the content of the template according to certain flags
	 *
	 * @param $content
	 * @param $flags Array
	 * @return string
	 */
	public function createDraftContent( $content, Array $flags ) {

		/**
		 * This is just a placeholder method
		 *
		 * TODO: Add methods converting the content based on $flags
		 */
		$flagsSum = array_sum( $flags );

		if ( self::TEMPLATE_GENERAL & $flagsSum ) {
			return $content;
		} elseif ( self::TEMPLATE_INFOBOX & $flagsSum ) {
			return $content;
		}

		return $content;
	}
}
