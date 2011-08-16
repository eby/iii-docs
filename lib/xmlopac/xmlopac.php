<?
/**
* This is a class that returns data from the III XML OPAC
* This file is released under the GNU Public License
* @package PHP III-XMLOPAC Class
* @author John Blyberg <blybergj@aadl.org>
* @contributors Casey Bisson <cbisson@mail.plymouth.edu>
* @version 1.11
*/

class xmlopac {

	public $opacserver;
	public $dc_xslt = './iii_to_dc.xsl'; // May need to add the path

	public function get_opac_data($bibnum, $extend = NULL, $utfproc = NULL) {
		$xmlopacurl = 'http://' . $this->opacserver . '/xmlopac/.b' . trim($bibnum) . '?noexclude=WXROOT.Heading.Title.IIIRECORD';
		$xml = self::prep_xml($xmlopacurl);
		$iiiobj = (array) $xml[raw]->Heading->Title->IIIRECORD;
		$dcobj = (array) $xml[dc][records];		// Yes, this seems neccesary.
		$dcobj = (array) $dcobj[record];		// For some reason, Xpath
		$dcobj = (array) $dcobj[recordData];		// doesn't seem to work
		$dcobj = (array) $dcobj[dc];			//
		$holds = 0;
		if($xml) {
		foreach ($iiiobj[VARFLDPRIMARYALTERNATEPAIR] as $iiifld) {

			switch ($iiifld->VARFLDPRIMARY->VARFLD->HEADER->NAME) {

				case "AUTHOR":
					$result[author] = self::cleanse_string($iiifld->VARFLDPRIMARY->VARFLD->DisplayForm, $utfproc);
					break;
				case "TITLE":
					$result[fulltitle] = self::cleanse_string($iiifld->VARFLDPRIMARY->VARFLD->DisplayForm, $utfproc);
					$titlefields = array();
					$subfields = $iiifld->VARFLDPRIMARY->VARFLD->MARCSUBFLD;
					foreach($subfields as $subfield) {
						if ($subfield->SUBFIELDINDICATOR == 'a' ||
								$subfield->SUBFIELDINDICATOR == 'b' ||
								$subfield->SUBFIELDINDICATOR == 'h' ||
								$subfield->SUBFIELDINDICATOR == 'n' ||
								$subfield->SUBFIELDINDICATOR == 'p' ||
								$subfield->SUBFIELDINDICATOR == 'v') {
							$titlefields[] = $subfield->SUBFIELDDATA;
						}
					}
					$result[title] = rtrim(self::cleanse_string(implode(" ", $titlefields), $utfproc), " ./");
					break;
				case "EDITION":
					$result[edition] = self::cleanse_string($iiifld->VARFLDPRIMARY->VARFLD->DisplayForm, $utfproc);
					break;
				case "PUB INFO":
					$result[pubinfo] = self::cleanse_string($iiifld->VARFLDPRIMARY->VARFLD->DisplayForm, $utfproc);
					break;
				case "DESCRIPT":
					$result[desc] = self::cleanse_string($iiifld->VARFLDPRIMARY->VARFLD->DisplayForm, $utfproc);
					break;
				case "STANDARD #":
					$stdnum_arr = explode(':', self::cleanse_string($iiifld->VARFLDPRIMARY->VARFLD->DisplayForm, $utfproc));
					if (!$firm_isbn) {
						$result[isbn] = trim($stdnum_arr[0]);
					} else {
						$result[stdnum] = trim($stdnum_arr[0]);
					}
					if (preg_match('/\$/', $stdnum_arr[1])) {
						$result[price] = trim($stdnum_arr[1]);
						$firm_isbn = $result[isbn];
					}
					break;
				case "NOTE":
					$notetype = strtolower((string) $iiifld->VARFLDPRIMARY->VARFLD->HEADER->LABEL);
					if ($notetype != 'note') {
						$result[$notetype] = self::cleanse_string($iiifld->VARFLDPRIMARY->VARFLD->DisplayForm, $utfproc);
					}
					break;
				case "CALL #":
					$result[callnum] = self::cleanse_string($iiifld->VARFLDPRIMARY->VARFLD->DisplayForm, $utfproc);
					break;
				case "SERIES":
					$result[series] = self::cleanse_string($iiifld->VARFLDPRIMARY->VARFLD->DisplayForm, $utfproc);
					break;
				case "HOLD":
					$holds++;
					$result[holds] = $holds;
					break;
				case "SUBJECT":
					$result[subject][] = self::cleanse_string($iiifld->VARFLDPRIMARY->VARFLD->DisplayForm, $utfproc);
					break;
			}
		}
		}
		if (is_object($iiiobj[TYPEINFO]->BIBLIOGRAPHIC->FIXFLD)) {
			foreach ($iiiobj[TYPEINFO]->BIBLIOGRAPHIC->FIXFLD as $iiifld) {
				switch ($iiifld->FIXLABEL) {
					case "LANG":
						$result[lang] = (string) $iiifld->FIXVALUE;
						break;
					case "COPIES":
						$result[copies] = (string) $iiifld->FIXVALUE;
						break;
					case "CAT DATE":
						$result[catdate] = (string) $iiifld->FIXVALUE;
						break;
					case "MAT TYPE":
						$result[mattype] = (string) $iiifld->FIXVALUE;
						break;
				}
			}
		}
		if ($result[mattype] == 'a') {
			$isbn_arr = explode(' ', $result[isbn]);
			$result[isbn] = $isbn_arr[0];
		} else {
			if (!$result[stdnum]) {	$result[stdnum] = $result[isbn]; }
			if ($result[stdnum] == $result[isbn]) { unset($result[isbn]); }
		}
		if ($extend && $result[isbn]) {
			$altisbn = self::get_altisbn($result[isbn]);
			if (count($altisbn)) { $result[altisbn] = $altisbn; }
			$audience_level = self::get_audience_level($result[isbn]);
			if ($audience_level[audience_level_manifest]) { $result[audience_level] = $audience_level; }
		}

		$result[avail] = (string) $iiiobj[BIBCOPIESAVAILABLE]->BIBCOPIESFORMATTED;

//		if (count($dcobj[subject])) {
//			$result[subject] = $dcobj[subject];
//		}

		return $result;
	}

	// Thanks to Casey Bisson
	public function get_opac_bibns($searchterms, $utfproc = NULL) {
		$xmlopacurl = 'http://' . $this->opacserver . '/xmlopac/' . $searchterms;
		$result[xmlurl] = $xmlopacurl;
		$xml = self::prep_xml($xmlopacurl);
		foreach ($xml[raw]->xpath('//Title') as $iiifld) {
			$bnum = substr(((string) $iiifld->RecordId->RecordKey), 1);
			$result[bibns][] = $bnum;
			$title = self::cleanse_string($iiifld->TitleField->VARFLDPRIMARYALTERNATEPAIR->VARFLDPRIMARY->VARFLD->DisplayForm, $utfproc);
			$result[titles][$bnum] = $title;
		}

		return $result;
	}

	// Thanks to Casey Bisson
	public function get_altisbn($isbn) {
		$oclc_url = 'http://labs.oclc.org/xisbn/' . $isbn;

		$xml = simplexml_load_file($oclc_url);
		$result = array();
		foreach ($xml->xpath('/idlist/isbn') as $isbn) {
			$isbn_arr[] = (string) $isbn;
		}
		if (is_array($isbn_arr)) {
			array_shift($isbn_arr);
		}
		return $isbn_arr;
	}

	public function get_audience_level($isbn) {
		$oclc_url = 'http://researchprojects.oclc.org/al/al.xml?oclcno=' . $isbn;
		$xml = simplexml_load_file($oclc_url);
		$totalsobj = "workset-totals";
		$audlvl = (array) $xml->workset->$totalsobj;
		$result[audience_level_manifest] = $audlvl['total-manifestations'];
		$result[audience_level] = $audlvl['total-audience-level'];
		$result[audience_level_percentile] = $audlvl['total-audience-level-percentile'];
		return $result;
	}

	private function prep_xml($xmlsrc, $is_string = NULL) {
		if ($is_string) {
//			$xmlraw = utf8_encode($xmlsrc);
			$xmlraw = $xmlsrc;
		} else {
			$xmlraw = file_get_contents($xmlsrc);
//			$xmlraw = utf8_encode($xmlsrc);

		}
		if($xmlraw) {
		$xslt = new xsltProcessor;
		$xslt->importStyleSheet(DomDocument::load($this->dc_xslt));
		$dcxml = $xslt->transformToXML(DomDocument::loadXML($xmlraw));
		$xml[dc] = (array) simplexml_load_string($dcxml);

		// This is where we compensate for stoned XML
		$xmlraw = preg_replace_callback('%DisplayForm>[#&\x80-\xFF](.*?)<\/%s', array('self', 'fix_bad_field'), $xmlraw);
//		$xmlraw = preg_replace('%>[#&\x80-\xFF](.*?)<\/%s', '>Display Error</', $xmlraw);
		$xmlraw = preg_replace('/&#/s', 'DisplayError', $xmlraw);
		$xmlraw = str_replace('&', '&amp;', $xmlraw);
		$xmlraw = str_replace('&&amp;', '&amp;', $xmlraw);
		$xml[raw] = simplexml_load_string($xmlraw);


		return $xml;
		} else {
		return FALSE;
		}
	}

	private function fix_bad_field($matches) {
		$trans_tbl = get_html_translation_table (HTML_ENTITIES, ENT_QUOTES);
		$trans_tbl = array_flip ($trans_tbl);
		$trans_tbl += array('&apos;' => "'");
		$ret = strtr ($matches[0], $trans_tbl);
		//return preg_replace('/\&\#(\d{1,3});/me', "chr('\\1')",$ret);  // Not sure if we need this...
		return $ret;
	}

	private function cleanse_string($string, $utfproc = NULL) {
		$string = (string) $string;
		$strpos = ((strlen($string)) - 1);
		if ($string[$strpos] == ".") {
			$string = substr($string, 0, -1);
		}
		$string = str_replace (array ( '&amp;' , '&quot;', '&apos;' , '&lt;' , '&gt;' ), array ( '&', '"', "'", '<', '>' ), $string );
		//return (string) utf8_decode(trim($string));
		$str = trim(mb_convert_encoding($string, "auto", "auto"));
		if (strtolower($utfproc) == 'encode' || strtolower($utfproc) == 'decode') {
			eval("\$str = utf8_\$utfproc(\$str);");
		}
		return $str;

	}

}

?>
