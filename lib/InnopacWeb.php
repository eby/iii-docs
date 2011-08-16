<?php

/**
 * Get bibliographic data, item status, course reserves, and patron information from an Innovative
 * Interfaces Catalog. Conforms to the DLF ILS Discovery Interface Task Group (ILS-DI) Technical 
 * Recommendation 1.0.
 * 
 * @author David Walker
 * @copyright 2008 The California State University
 * @link http://xerxes.calstate.edu
 * @version 2.0
 * @package Mango
 * @license http://www.gnu.org/licenses/
 */

class InnopacWeb
{
	private $strServer = ""; // server address
	private $strIndex = ""; // search index		
	private $strPhrase = ""; // search phrase
	private $strNormalized = ""; // search phrase encoded for url
	private $iTotal = 0; // total number of hits in a search
	private $iScope = null; // limit: numeric scope
	private $arrLimit = array ( ); // limits
	private $arrURL = array ( ); // list of url's accessed, mostly for debugging
	private $bolInnReach = false; // whether this is an innreach system

	private $strMarcNS = "http://www.loc.gov/MARC21/slim"; // marc namespace

	/**
	 * Constructor
	 *
	 * @param string $strServer		server address
	 */
	
	public function __construct($strServer)
	{
		if ( substr( $strServer, strlen( $strServer ) - 1, 1 ) == "/" )
		{
			$strServer = substr( $strServer, 0, strlen( $strServer ) - 1 );
		}
		
		$this->strServer = $strServer;
	}
	
	
	### HARVESTING FUNCTIONS
	
	
	public function harvestBibliographicRecords() 
	{ 
		throw new InnopacWeb_NotSupportedException("harvestBibliographicRecords not currently supported");
	}
	
	public function harvestExpandedRecords()
	{
		throw new InnopacWeb_NotSupportedException("harvestExpandedRecords not currently supported");
	}
	
	public function harvestAuthorityRecords()
	{
		throw new InnopacWeb_NotSupportedException("harvestAuthorityRecords not currently supported");
	}
	
	public function harvestHoldingsRecords()
	{
		throw new InnopacWeb_NotSupportedException("harvestHoldingsRecords not currently supported");
	}
	
	
	### REAL-TIME SEARCH FUNCTIONS 
	
	
	/**
	 * Given a set of bibliographic or item identifiers, returns a list with availability of the
	 * items associated with the identifiers.
	 *
	 * @param string $id			list of either bibliographic or item identifiers, seperate multiple ids by space
	 * @param string $id_type		the type of record identifier being used in $id, currently only 'bibliographic'
	 * @return array				a list of item availability objects that represent all the availability of the 
	 * 								items associated with the requested bibliographic / item identifiers
	 */
	
	public function getAvailability($id, $id_type)
	{
		$arrResults = array ( ); // results array
		$x = 0; // counter to keep track of found records
		$arrIDs = explode( " ", $id ); // ids as array
		
		if ( $id_type != "bibliographic")
		{
			throw new InnopacWeb_UnsupportedTypeException("currently only supports availability look-up by bibliographic id");
		}
		
		foreach ( $arrIDs as $strBibId )
		{
			// get marc page

			$strResponse = $this->getRecordMarcPage($strBibId);
			
			if ($strResponse != null )
			{
				// extract availability info and add to list
				
				$arrItems = $this->extractHoldings($strResponse);
				$arrResults[$strBibId] = $arrItems;
				
				// mark that we got it
				$x++;
			}
			else
			{
				$arrResults[$strBibId] = null;
			}
		}
		
		if ( $x == 0)
		{
			return null;	
		}
		else
		{
			return $arrResults;
		}
	}
	
	/**
	 * Given a list of record identifiers, returns a list of record objects that contain
	 * bibliographic information, as well as associated holdings and item information.
	 *
	 * @param string $id			one or more id numbers, seperate multiple ids by space
	 * @param string $schema		defines the metadata schema in which the records are returned, currently only marc-xml
	 * @return array				a list of record objects that represents the bibliographic record, as well as
	 * 								associated holdings and item information for each requested bibliographic identifier
	 */
	
	public function getRecords($id, $schema = 'marc-xml')
	{
		$arrResults = array ( ); // results array
		$x = 0; // counter to keep track of found records
		$arrIDs = explode( " ", $id ); // ids as array
		
		if ( $schema != "marc-xml")
		{
			throw new InnopacWeb_UnsupportedSchemaException("marc-xml is the only currently supported schema");
		}

		foreach ( $arrIDs as $strBibId )
		{
			$strResponse = $this->getRecordMarcPage($strBibId);
			if ( $strResponse != null )
			{
				// get record and add to list
				
				$objRecord = $this->extractRecord( $strResponse );
				$arrResults[$strBibId] = $objRecord;
				
				// mark it as found
				$x++;
			}
			else
			{
				$arrResults[$strBibId] = null;
			}
		}
		
		if ( $x == 0 )
		{
			$this->iTotal = 0;
			return null;
		}
		else
		{	
			$this->iTotal = 1;
			return $arrResults;
		}
	}

	/**
	 * Returns a list of records in the ILS matching a search query
	 *
	 * @param string $index			one letter index to search on, this will differ depending on catalog
	 * @param string $query			search phrase
	 * @param string $schema		[optional] schema for bibliographic records, only option now is marc-xml
	 * @param string $recordType	[optional] fullness of the record response, only option now is 'full'
	 * @param int $offset			[optional] starting record in the set to return, defaults to 1
	 * @param int $max				[optional] maximum number of records to return, defaults to 10
	 * @param string $sort			[optional] sort order for keyword searches, defaults to date, acceptable values:
	 * 								- 'D' date
	 * 								- 'R' rank 
	 * 								- 'A' alphabetical
	 * @return array				array of Record objects with attached Item objects
	 */
	
	public function search($index, $query, $schema = "marc-xml", $recordType = "full", $offset = 1, $max = 10, $sort = "D")
	{
		// type check

		if ( ! is_int( $offset ) )
		{
			throw new Exception( "param 4 (offset) must be of type int" );
		}
		if ( ! is_int( $max ) )
		{
			throw new Exception( "param 5 (max) must be of type int" );
		}

		if ( $schema != "marc-xml")
		{
			throw new InnopacWeb_UnsupportedSchemaException("marc-xml is the only currently supported schema");
		}	

		if ( $recordType != "full")
		{
			throw new InnopacWeb_UnsupportedTypeException("'full' is the only supported record type");
		}			
		
		// search and retrieve records
		
		$this->iTotal = $this->doSearch( $index, $query );
		return $this->retrieve( $offset, $max, $sort );
	}
	
	public function scan()
	{
		throw new InnopacWeb_NotSupportedException("scan not currently supported");
	}
	
	public function getAuthorityRecords()
	{
		throw new InnopacWeb_NotSupportedException("getAuthorityRecords not currently supported");
	}

	public function searchCourseReserves()
	{
		throw new InnopacWeb_NotSupportedException("searchCourseReserves not currently supported");
	}
	
	public function explain()
	{
		throw new InnopacWeb_NotSupportedException("explain not currently supported");
	}
	
	
	### PATRON FUNCTIONS ###
	
	
	public function lookupPatron()
	{
		throw new InnopacWeb_NotSupportedException("lookupPatron not currently supported");
	}
	
	public function authenticatePatron()
	{
		throw new InnopacWeb_NotSupportedException("authenticatePatron not currently supported");
	}
	
	public function getPatronInfo()
	{
		throw new InnopacWeb_NotSupportedException("getPatronInfo not currently supported");
	}
	
	public function getPatronStatus()
	{
		throw new InnopacWeb_NotSupportedException("getPatronStatus not currently supported");
	}
	
	public function getServices()
	{
		throw new InnopacWeb_NotSupportedException("getServices not currently supported");
	}
	
	public function renewLoan()
	{
		throw new InnopacWeb_NotSupportedException("renewLoan not currently supported");
	}
	
	public function holdTitle()
	{
		throw new InnopacWeb_NotSupportedException("holdTitle not currently supported");
	}
	
	public function holdItem()
	{
		throw new InnopacWeb_NotSupportedException("holdItem not currently supported");
	}
	
	public function cancelHold()
	{
		throw new InnopacWeb_NotSupportedException("cancelHold not currently supported");
	}
	
	public function recallItem()
	{
		throw new InnopacWeb_NotSupportedException("recallItem not currently supported");
	}
	
	public function cancelRecall()
	{	
		throw new InnopacWeb_NotSupportedException("cancelRecall not currently supported");
	}
	
	
	### OPAC INTERACTION FUNCTIONS ###
	
	
	public function goToBibliographicRequestPage($bibid)
	{
		return $this->strServer . "/record=" . $bibid;
	}
	
	public function outputRewritablePage()
	{
		throw new InnopacWeb_NotSupportedException("outputRewritablePage not currently supported");
		
		/*  
		$strResponse = file_get_contents($this->strServer . "/record=" . $bibid);
		$strResponse = str_ireplace("<base ", "<base href=\"" . $this->strServer . "/\" ", $strResponse);
		return $strResponse;
		*/
	}
	
	public function outputIntermediateFormat()
	{
		throw new InnopacWeb_NotSupportedException("outputIntermediateFormat not currently supported");
	}
	
	
	### PRIVATE FUNCTIONS ###
	
	
	/**
	 * Return the MARC record page
	 *
	 * @param string $strBibId		bib id
	 * @return string				URI path
	 */
	
	private function getRecordMarcPage($strBibId)
	{
		$strId = substr( $strBibId, 1 );
		$strQuery = "/search/.$strBibId/.$strBibId/1,1,1,B/detlmarc~$strId&FF=&1,0,";
		
		$strResponse = file_get_contents( $this->strServer . $strQuery );
		array_push( $this->arrURL, $this->strServer . $strQuery );
		
		if ( ! stristr($strResponse, "<pre>") )
		{
			// didn't find a record
			
			return null;
		}
		else
		{
			return $strResponse;
		}
	}
	
	/**
	 * Initiate a search using the supplied query
	 *
	 * @param string $strIndex		one letter index to search on, this will differ depending on catalog
	 * @param string $strPhrase		search phrase
	 * @return int					number of records found
	 */
	
	private function doSearch($strIndex, $strPhrase)
	{
		$strQuery = ""; // query part of url
		$strResponse = ""; // html response from the catalog
		$arrMatches = array ( ); // regex match holding array

		// set the values into the object property

		$this->strIndex = $strIndex;
		$this->strPhrase = $strPhrase;
		
		// normalize the search phrase for urls

		$this->strNormalized = strtolower( $strPhrase );
		
		// browse (i.e., non-keyword) searches require a special set
		// of normalization rules

		if ( $strIndex != "X" && $strIndex != "Y" )
		{
			// replace all non-indexed punctuation with space;
			// indexed punctuation includes: @ # $ + |
			// special handling below for: ' & -

			$this->strNormalized = preg_replace( "/[^a-zA-Z0-9 \@\#\$\+\|\&\-\']/", " ", $this->strNormalized );
			
			// special cases

			$this->strNormalized = str_replace( "'", "", $this->strNormalized );
			$this->strNormalized = str_replace( " & ", " and ", $this->strNormalized );
			
			// standard numbers should have the dash removed completely

			if ( $strIndex == "i" )
			{
				$this->strNormalized = str_replace( "-", "", $this->strNormalized );
			} 
			else
			{
				$this->strNormalized = str_replace( "-", " ", $this->strNormalized );
			}
			
			// remove leading article for title searches
			
			if ( substr( $this->strNormalized, 0, 2 ) == "a " )
			{
				$this->strNormalized = substr( $this->strNormalized, 2 );
			} 
			elseif ( substr( $this->strNormalized, 0, 3 ) == "an " )
			{
				$this->strNormalized = substr( $this->strNormalized, 3 );
			} 
			elseif ( substr( $this->strNormalized, 0, 4 ) == "the " )
			{
				$this->strNormalized = substr( $this->strNormalized, 4 );
			}
			
			// remove double-spaces

			while ( strstr( $this->strNormalized, "  " ) )
			{
				$this->strNormalized = str_replace( "  ", " ", $this->strNormalized );
			}
			
			// convert all accented characters to their latin equivalent

			$this->strNormalized = strtr( $this->strNormalized, "ŠŒŽšœžŸ¥µÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýÿ", "SOZsozYYuAAAAAAACEEEEIIIIDNOOOOOOUUUUYsaaaaaaaceeeeiiiionoooooouuuuyy" );
			
			// pad certain occurances of numbers with added spaces: 
			//    +++++++9 if the search started with a number-space
			//    ++++9 for any space-number in the search not at the beginning

			$this->strNormalized = preg_replace( "/ ([0-9]{1})/", "++++$1", $this->strNormalized );
			$this->strNormalized = preg_replace( "/^([0-9]{1}) /", "+++++++$1", $this->strNormalized );
		}
		
		$this->strNormalized = urlencode( $this->strNormalized );
		
		// build base url query 
		
		$strQuery = "/search/" . $strIndex . "?" . urlencode( $strPhrase );
		
		// add limits

		$strQuery .= $this->appendLimits();
		
		// get initial search results page

		$strResponse = file_get_contents( $this->strServer . $strQuery );
		array_push( $this->arrURL, $this->strServer . $strQuery );
		
		// extract the total number of hits in the results page;

		if ( preg_match( "/Entries<br \/> ([0-9]{1,10}) Found/", $strResponse, $arrMatches ) != 0 )
		{
			return $arrMatches[1]; // browse search
		} 
		elseif ( preg_match( "/\(1-[0-9]{1,2} of ([0-9]{1,10})\)/", $strResponse, $arrMatches ) != 0 )
		{
			return $arrMatches[1]; // keyword search
		} 
		elseif ( ! stristr( $strResponse, "No matches found" ) && ! stristr( $strResponse, "NO ENTRIES FOUND" ) )
		{
			return 1; // only found one response, catalog jumped right to full display 
		} 
		else
		{
			return 0;
		}
	}
	
	/**
	 * Retrieve records from a previously initiated search
	 *
	 * @param int $iStart			starting record in the set to return, defaults to 1
	 * @param int $iLimit			maximum number of records to return, defaults to 10
	 * @param string $strSort		[optional] sort order for keyword searches, defaults to date, acceptable values:
	 * 								- 'D' date
	 * 								- 'R' rank 
	 * 								- 'A' alphabetical
	 * @return array				array of DOMDocuments as MARC-XML
	 */
	
	private function retrieve($iStart, $iLimit, $strSort = "D")
	{
		// type check

		if ( ! is_int( $iStart ) )
		{
			throw new Exception( "param 1 (start record) must be of type int" );
		}
		if ( ! is_int( $iLimit ) )
		{
			throw new Exception( "param 2 (limit) must be of type int" );
		}
		
		$arrResults = array ( ); // results array

		if ( $this->iTotal > 0 )
		{
			// set the end point in the range

			$iEnd = $iStart + ($iLimit - 1);
			
			if ( $iEnd > $this->iTotal )
			{
				$iEnd = $this->iTotal;
			}

			// some adjustments for keyword searches

			if ( $this->strIndex == "X" )
			{
				$this->strNormalized = "(" . $this->strNormalized . ")";
			}			
			
			// cycle through the results, pulling back each
			// record and adding it to the response

			while ( $iStart <= $iEnd )
			{
				$strRecord = "";
				$strResponse = "";
				$objRecord = new DOMDocument( );
				
				// build the url query for each individual record

				$strRecord = "/search?/" . $this->strIndex . $this->strNormalized . "&SORT=" . $strSort . $this->appendLimits() . "/" . $this->strIndex . $this->strNormalized . "&SORT=" . $strSort . $this->appendLimits() . "/" . $iStart . "," . $this->iTotal . "," . $this->iTotal . ",B/detlmarc&FF=" . $this->strIndex . $this->strNormalized . "&SORT=" . $strSort . $this->appendLimits() . "&" . $iStart . "," . $iStart . ",";
				
				// get marc display html page from the server
				// and convert it to XML

				$strResponse = file_get_contents( $this->strServer . $strRecord );
				array_push( $this->arrURL, $this->strServer . $strRecord );
				
				$objRecord = $this->extractRecord( $strResponse );
				
				// add the document to the results array

				$arrResults[$iStart] = $objRecord;
				$iStart ++;
			}
		}
		
		return $arrResults;
	}
	
	/**
	 * Append the param limits set in properties below
	 *
	 * @return string		URL params
	 */
	
	private function appendLimits()
	{
		$strReturn = "";
		
		if ( $this->iScope != null )
		{
			$strReturn .= "&searchscope=" . $this->iScope;
		}
		
		foreach ( $this->arrLimit as $key => $value )
		{
			$strReturn .= "&$key=" . urlencode( $value );
		}
		
		return $strReturn;
	}
	
	/**
	 * Convenience class to get both the marc record and the item objects
	 *
	 * @param string $strResponse	HTML MARC display page from catalog
	 * @return DOMDocument			MARC-XML response
	 */
	
	private function extractRecord($strResponse)
	{
		$objXml = $this->extractMarc( $strResponse );
//		$arrHoldings = $this->extractHoldings( $strResponse );
		
//		$objRecord = new InnopacWeb_Record( );
//		$objRecord->bibliographicRecord = $objXml;
//		$objRecord->items = $arrHoldings;
		
		return $objXml;
	}
	
	/**
	 * Extracts data from the holdings table in the HTML response
	 *
	 * @param string $strHtml		html response from catalog
	 * @param bool $bolRecursive	[optional] whether this is a recursive call from inside the function
	 * @return array 				array of Item objects
	 */
	
	private function extractHoldings($strHtml, $bolRecursive = false)
	{	
		$bolOrderNote = false; // whether holdings table shows an ordered noted
		$bolFieldNotes = false; // whether holdings table includes comments indicating item record marc fields
		
		// see if there is more than one page of holdings, in which case get the
		// expanded (holdings) page instead; performance hit, but gotta do it
		
		if ( stristr($strHtml, "additional copies") )
		{
			if ( $bolRecursive == true )
			{
				throw new Exception("recursion error getting additional copies");
			}
			
			$strHoldingsUrl = ""; // uri to full holdings list
			$arrMatches = array(); // matches from the regex below
			
			// get all the post form actions, one of them will be for the 
			// function that gets the additional items (holdings) page
			
			if ( preg_match_all("/<form method=\"post\" action=\"([^\"]*)\"/", $strHtml, $arrMatches, PREG_PATTERN_ORDER))
			{
				foreach($arrMatches[1] as $strPostUrl)
				{
					if ( stristr($strPostUrl, "/holdings") )
					{
						$strHoldingsUrl = $this->strServer . $strPostUrl;
						break;
					}
				}
			}
			
			// get the full response page now and redo the function call
			
			$strResponse = file_get_contents($strHoldingsUrl);
			
			return $this->extractHoldings($strResponse, true);
		}
		
		$strTable = ""; // characters that mark the start of the holding table
		$arrHoldings = array ( ); // master array we'll used to hold the holdings

		// check to see if there are holdings as well as item records
		
		if ( strpos( $strHtml, "class=\"bibHoldings\">" ) !== false )
		{
			// do something!
			
			return null;
		}
		
		// look to see which template this is and adjust the 
		// parser to accomadate the html structure

		if ( strpos( $strHtml, "class=\"bibItems\">" ) !== false )
		{
			// most library innopac systems
			$strTable = "class=\"bibItems\">";
		} 
		elseif ( strpos( $strHtml, "BGCOLOR=#DDEEFF>" ) !== false )
		{
			// old innreach system
			$strTable = "BGCOLOR=#DDEEFF>";
		} 
		elseif ( strpos( $strHtml, "centralHolding" ) !== false )
		{
			// newer innreach system
			$strTable = "centralHolding";
		} 
		elseif ( strpos( $strHtml, "class=\"bibOrder" ) !== false )
		{
			// this is just a note saying the item has been ordered
			
			$strTable = "class=\"bibOrder";
			$bolOrderNote = true;
		} 
		elseif ( strpos( $strHtml, "class=\"bibHoldings" ) !== false )
		{
			$strTable = "class=\"bibHoldings";
		} 
		elseif ( strpos( $strHtml, "class=\"bibDetail" ) !== false )
		{
			$strTable = "class=\"bibDetail";
		} 
		else
		{
			return $arrHoldings;
		}
		
		// narrow the page initially to the holdings table

		$strHtml = $this->removeLeft( $strHtml, $strTable );
		$strHtml = $this->removeRight( $strHtml, "</table>" );
		
		// we'll use the table row as the delimiter of each holding

		while ( strstr( $strHtml, "<tr" ) )
		{
			$arrItem = array ( );
			$strItem = "";
			
			// remove everything left of the table row, dump the row content into a
			// local variable, while removing it from the master variable so
			// our loop above continues to cycle thru the results

			$strHtml = $this->removeLeft( $strHtml, "<tr" );
			$strItem = "<tr" . $this->removeRight( $strHtml, "</tr>" );
			$strHtml = $this->removeLeft( $strHtml, "</tr>" );
			
			// make sure this isn't the header row

			if ( strpos( $strItem, "<th" ) === false )
			{
				// extract any url in item, especially for InnReach

				$strUrl = null;
				$arrUrl = array ( );
				
				if ( preg_match( "/<a href=\"([^\"]{1,})\">/", $strItem, $arrUrl ) )
				{
					$strUrl = $arrUrl[1];
				}
				
				// replace the item record marc field comments with place holders
				// so we can grab them later after removing the html tags
				
				$strItem = preg_replace("/<\!-- field (.{1}) -->/", "+~\$1~", $strItem);
				
				// strip out tags and non-breaking spaces 

				$strItem = strip_tags( $strItem );
				$strItem = str_replace( "&nbsp;", "", $strItem );
				
				// normalize spaces
				
				while ( strstr( $strItem, "  " ) )
				{
					$strItem = str_replace( "  ", " ", $strItem );
				}
				
				$strItem = trim( $strItem );
				
				// now split the remaining data out into an array
				
				$arrItem = array();
				
				// the display included the item record field comments, in which
				// case we will use these over a general column-based approach
				// since it is more precise; this should be the case on all local systems
									
				if ( strstr($strItem, "+~"))
				{
					$bolFieldNotes = true;
					$arrItemTemp = explode( "+~", $strItem );
					
					foreach ($arrItemTemp as $strItemTemp)
					{
						if ( strstr($strItemTemp, "~") )
						{
							$arrItemField = explode("~", $strItemTemp);
							$strFieldKey = trim($arrItemField[0]);
							$strFieldValue = trim($arrItemField[1]);
							$arrItem[$strFieldKey] = $strFieldValue;
						}
					}					
				}
				else
				{
					$arrItem = explode( "\n", $strItem );

					// add url back into the array
				
					if ( $strUrl != null )
					{
						array_push( $arrItem, $strUrl );
					}				
				}
				
				// final clean-up, assignment
				
				$objItem = new InnopacWeb_Item();
				
				if ( $bolFieldNotes == true )
				{
					foreach ( $arrItem as $key => $strData )
					{
						switch ( $key )
						{
							case "1" :
								$objItem->location = $strData;
								break;
							
							case "C" :
								$objItem->call_number = $strData;
								break;

							case "v" :
								$objItem->volume = $strData;
								break;
																
							case "%" :
								$objItem->status = $strData;
								break;
						}
					}
				}
				else
				{
					for ( $x = 0 ; $x < count( $arrItem ) ; $x ++ )
					{
						$strData = trim( $arrItem[$x] );
						
						if ( $bolOrderNote == true )
						{
							$objItem->status = "ON ORDER";
							$objItem->note = $strData;
						}
						elseif ( $this->bolInnReach == true )
						{
							switch ( $x )
							{
								case 0 :
									$objItem->institution = $strData;
									break;
								
								case 1 :
									$objItem->location = $strData;
									break;
								
								case 2 :
									
									// note for accessing item online
									
									$objItem->note = $strData;
									break;
								
								case 3 :
									
									// this is a link if the second position had an
									// online access note
									
									if ( $objItem->note != "" )
									{
										$objItem->link = $strData;
									}
									else
									{
										$objItem->call_number = $strData;
									}
									
									break;
								
								case 4 :
									$objItem->status = $strData;
									break;
							}
						}
						else
						{
							switch ( $x )
							{
								case 0 :
									$objItem->location = $strData;
									break;
								
								case 2 :
									$objItem->call_number = $strData;
									break;
								
								case 3 :
									$objItem->status = $strData;
									break;
							}
						}
					}
				}
				
				$arrMatches = array();
				
				if ( preg_match("/([0-9]{2})-([0-9]{2})-([0-9]{2})/", $objItem->status, $arrMatches) )
				{
					$objDate = new DateTime($arrMatches[1] . "/" . $arrMatches[2] . "/" . $arrMatches[3]);
					$objItem->dateAvailable = $objDate;
				}
				
				array_push( $arrHoldings, $objItem );
			}
		}
		
		return $arrHoldings;
	}
	
	/**
	 * Extracts the MARC data from the HTML response and converts it to MARC-XML
	 *
	 * @param string $strMarc	marc data as string
	 * @return DOMDocument		marc-xml document
	 */
	
	private function extractMarc($strResponse)
	{
		$objXml = array();
		
		$strMarc = ""; // marc data as text
		$arrTags = array ( ); // array to hold each MARC tag

		// parse out MARC data

		$strMarc = $this->removeLeft( $strResponse, "<pre>" );
		$strMarc = $this->removeRight( $strMarc, "</pre>" );

		// remove break-tabs for easier parsing

		$strMarc = str_replace( "\n       ", "", $strMarc );
		$strMarc = trim( $strMarc );
		
		// assign the marc values to the array based on Unix LF as delimiter

		$arrTags = explode( "\n", $strMarc );

		// begin building the XML response

		
		foreach ( $arrTags as $strTag )
		{
			// assign tag # and identifiers

			$strTagNumber = substr( $strTag, 0, 3 );
			$strId1 = substr( $strTag, 4, 1 );
			$strId2 = substr( $strTag, 5, 1 );
			
			// assign data and clean it up

			$strData = substr( $strTag, 7 );
			$strData = utf8_encode( $strData );
			$strData = $this->escapeXml( $strData );
			$strData = trim( $strData );
			
			if ( ( int ) $strTagNumber <= 8 )
			{
				// control fields

			} 
			else
			{
				// data fields


				//$objDataField->setAttribute( "ind1", $strId1 );
				//$objDataField->setAttribute( "ind2", $strId2 );
				
				// if first character is not a pipe symbol, then this is the default |a subfield
				// so make that explicit for the array

				if ( substr( $strData, 0, 1 ) != "|" )
				{
					$strData = "|a " . $strData;
				}
				
				// split the subfield data on the pipe and add them in using the first
				// character after the delimiter as the subfield code
				
				$arrSubFields = explode( "|", $strData );

				foreach ( $arrSubFields as $strSubField )
				{
					if ( $strSubField != "" )
					{
            $objXml[] = array('tag' => $strTagNumber, 'subtag' => substr( $strSubField, 0, 1 ), 'value' => trim( substr( $strSubField, 1 ) ));
					}
				}
				
			}
		}
		return $objXml;
	}
	
	/**
	 * Simple function to strip off the previous part of a string
	 * from the start of the term to the beginning, including the term itself
	 * 
	 * @param string $strExpression		whole string to search 
	 * @param string $strRemove			term to match and remove left of from 
	 * @return string 					chopped string
	 */
	
	private function removeLeft($strExpression, $strRemove)
	{
		$iStartPos = 0; // start position of removing term
		$iStopPos = 0; // end position of removing term
		$strRight = ""; // right remainder of the srtring to return

		// if it really is there

		if ( strpos( $strExpression, $strRemove ) !== false )
		{
			// find the starting position of string to remove
			$iStartPos = strpos( $strExpression, $strRemove );
			
			// find the end position of string to remove
			$iStopPos = $iStartPos + strlen( $strRemove );
			
			// return everything after that
			$strRight = substr( $strExpression, $iStopPos, strlen( $strExpression ) - $iStopPos );
			
			return $strRight;
		} 
		else
		{
			return $strExpression;
		}
	}
	
	/**
	 * Simple function to strip off the remainder of a string
	 * from the start of the term to the end of the string, including the term itself
	 * 
	 * @param string $strExpression		whole string to search 
	 * @param string $strRemove			term to match and remove right of from 
	 * @return string chopped string
	 */
	
	private function removeRight($strExpression, $strRemove)
	{
		$iStartPos = 0; // start position of removing term
		$strLeft = ""; // left portion of to return

		// if it really is there
		
		if ( strpos( $strExpression, $strRemove ) !== false )
		{
			// find the starting position of to remove
			$iStartPos = strpos( $strExpression, $strRemove );
			
			// get everything before that
			$strLeft = substr( $strExpression, 0, $iStartPos );
			
			return $strLeft;
		} 
		else
		{
			return $strExpression;
		}
	}
	
	/**
	 * Clean data for inclusion in an XML document, escaping illegal
	 * characters
	 *
	 * @param string $string data to be cleaned
	 * @return string cleaned data
	 */
	
	private function escapeXml($string)
	{
		$string = str_replace( '&', '&amp;', $string );
		$string = str_replace( '<', '&lt;', $string );
		$string = str_replace( '>', '&gt;', $string );
		$string = str_replace( '\'', '&#39;', $string );
		$string = str_replace( '"', '&quot;', $string );
		
		$string = str_replace( "&amp;#", "&#", $string );
		$string = str_replace( "&amp;amp;", "&amp;", $string );
		
		return $string;
	}

	
	### PROPERTIES ###
	
	
	public function getTotal()
	{
		return $this->iTotal;
	}
	
	public function getURL()
	{
		return $this->arrURL;
	}
	
	public function getLimits()
	{
		return $this->arrLimit;
	}
	
	public function getLimit($key)
	{
		if ( array_key_exists( $key, $this->arrLimit ) )
		{
			return $this->arrLimit[$key];
		} 
		else
		{
			return null;
		}
	}
	
	public function setLimit($key, $value)
	{
		$this->arrLimit[$key] = $value;
	}
	
	public function getScope()
	{
		return $this->iScope;
	}
	
	public function setScope($value)
	{
		$this->iScope = $value;
	}
	
	public function getInnReach()
	{
		return $this->bolInnReach;
	}
	
	public function setInnReach($value)
	{
		if ( is_bool( $value ) )
		{
			$this->bolInnReach = $value;
		} else
		{
			throw new Exception( "parameter must be of type bool" );
		}
	}
}

class InnopacWeb_Record
{
	public $bibliographicRecord; // DOMDocument
	public $items = array ( ); // array
}

class InnopacWeb_Item
{
	public $bibliographicIdentifer; // string
	public $itemIdentifier; // string
	public $dateAvailable; // DateTime
	public $status; // string
	public $institution; // (NON-SPEC) string
	public $location; // string
	public $call_number; // string
	public $volume; // NON-SPEC) string
	public $link; // (NON-SPEC) string
	public $circulating; // bool
	public $holdQueueLength; // int
	public $note; // (NON-SPEC) string
}

class InnopacWeb_NotSupportedException extends Exception {}
class InnopacWeb_UnsupportedSchemaException extends Exception {}
class InnopacWeb_UnsupportedProfileException extends Exception {}
class InnopacWeb_UnsupportedTypeException extends Exception {}

?>
