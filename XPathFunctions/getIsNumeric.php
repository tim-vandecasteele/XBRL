<?php

/**
 * XPath 2.0 for PHP
 *  _					   _	 _ _ _
 * | |   _   _  __ _ _   _(_) __| (_) |_ _   _
 * | |  | | | |/ _` | | | | |/ _` | | __| | | |
 * | |__| |_| | (_| | |_| | | (_| | | |_| |_| |
 * |_____\__, |\__, |\__,_|_|\__,_|_|\__|\__, |
 *	     |___/	  |_|					 |___/
 *
 * @author Bill Seddon
 * @version 0.9
 * @Copyright ( C ) 2017 Lyquidity Solutions Limited
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * ( at your option ) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace XBRL\functions;

use lyquidity\XPath2\CoreFuncs;
use lyquidity\xml\MS\XmlTypeCardinality;
use lyquidity\xml\MS\XmlTypeCode;
use lyquidity\XPath2\NodeProvider;
use lyquidity\XPath2\Properties\Resources;
use lyquidity\XPath2\SequenceType;
use lyquidity\XPath2\XPath2Context;
use lyquidity\XPath2\XPath2NodeIterator;
use lyquidity\XPath2\Value\QNameValue;
use lyquidity\XPath2\DOM\DOMXPathNavigator;
use lyquidity\XPath2\XPath2Exception;

/**
 * Returns true if the concept has a numeric data type, as defined in the XBRL specification and false otherwise.
 * @param XPath2Context $context
 * @param NodeProvider $provider
 * @param array $args
 * @return XPath2NodeIterator	Returns true if the concept has a numeric data type, as defined in the XBRL
 * 								specification and false otherwise. Note that this function returns false for
 * 								concepts with a data type that is the xbrli:fractionItemType or that is derived
 * 								from that data type. Note also that this function returns false for concepts that
 * 								are tuples.
 *
 * This function has one real parameter:
 *
 * concept	xs:QName	The QName of the concept being tested.
 */
function getIsNumeric( $context, $provider, $args )
{
	if ( count( $args ) != 1 )
	{
		throw XPath2Exception::withErrorCodeAndParams( "XPST0017", Resources::XPST0017,
			array(
				"is-numeric",
				count( $args ),
				\XBRL_Constants::$standardPrefixes[ STANDARD_PREFIX_FUNCTION_INSTANCE ],
			)
		);
	}

	/**
	 * @var XPath2NodeIterator $arg
	 */
	$arg = $args[0];
	if ( is_null( $arg ) ) return $args;

	$result = null;

	try
	{
		/**
		 * @var QNameValue $qn
		 */
		$qn = $arg instanceof QNameValue
			? $arg
			: ( $arg instanceof DOMXPathNavigator
					? QNameValue::fromXPathNavigator( $arg )
					: CoreFuncs::CastArg( $context, $arg, SequenceType::WithTypeCodeAndCardinality( XmlTypeCode::QName, XmlTypeCardinality::One ) )
			  );

		// Look up the type
		$types = \XBRL_Types::getInstance();
		$prefix = $types->getPrefixForNamespace( $qn->NamespaceUri );
		if ( ! $prefix )
		{
			throw new \InvalidArgumentException();
		}

		$element = $types->getElement( $qn->LocalName, $prefix );
		if ( ! $element )
		{
			throw new \InvalidArgumentException();
		}

		if ( isset( $element['substitutionGroup'] ) && $types->resolveToSubstitutionGroup( $element['substitutionGroup'], array( \XBRL_Constants::$xbrliTuple ) ) )
		{
			return CoreFuncs::$False;
		}

		$elementType = $element['types'][0];

		// Special case.  xbrli:dateTimeItemType is a union type and currently the type system
		// does not support union types.  The resolvesToBaseType function will return true but
		// this case must return false.
		if ( $elementType == "xbrli:dateTimeItemType" )
		{
			return CoreFuncs::$False;
		}

		if ( is_array( $elementType ) )
		{
			throw new \InvalidArgumentException();
		}

		// BMS 2018-04-09 Test candidates changed.
		return ( $types->resolvesToBaseType( $elementType, array( 'xbrli:fractionItemType', 'xs:decimal', 'xs:double', 'xs:float', 'xsd:decimal', 'xsd:double', 'xsd:float' ) ) )
			? CoreFuncs::$True
			: CoreFuncs::$False;
	}
	catch ( XPath2Exception $ex)
	{
		// Do nothing
	}
	catch ( \Exception $ex)
	{
		// Do nothing
	}

	if ( isset( $ex ) )	\XBRL_Log::getInstance()->debug( $ex->getTraceAsString() );

	throw XPath2Exception::withErrorCode( "XPTY0004", Resources::GeneralXFIFailure );
}
