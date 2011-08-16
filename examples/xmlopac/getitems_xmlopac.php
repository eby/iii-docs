<?php

$server = "iii.mylibrary.org";
$maxitems = 100;
$bibnum = 12345678;

$xmlopacurl = 'http://' . $server . '/xmlopac/.b' . $bibnum . '?noexclude=WXROOT.Heading.Title.IIIRECORD&links=i1-'.$maxitems;

$xml = simplexml_load_file($xmlopacurl);
$records = array();
foreach($xml->xpath('//LINKFIELD') as $link) {
  foreach($link->Link as $item) {
    $inum = (string) $item->RecordId->RecordKey;
    $records[$inum] = array();
    foreach($item->IIIRECORD->TYPEINFO->ITEM->FIXFLD as $field){
      $label = (string) $field->FIXLABEL;
      $value = (string) $field->FIXLONGVALUE;
      $itemarray[$label] = $value;
    }
    $records[$inum] = $itemarray;
    unset($itemarray);
  }
}
print_r($records);

?>
