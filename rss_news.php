<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Информационный портал");
$GLOBALS["arrFilterMain"] = array("PROPERTY_MAIN_VALUE" => 1);
?><?$APPLICATION->IncludeComponent(
	"bitrix:rss.out",
	"",
	Array(
		"IBLOCK_TYPE" => "news",
		"IBLOCK_ID" => "2",
		"SECTION_ID" => "",
		"SECTION_CODE" => "",
		"NUM_NEWS" => "8",
		"NUM_DAYS" => "30",
		"RSS_TTL" => "60",
		"YANDEX" => "N",
		"SORT_BY1" => "ACTIVE_FROM",
		"SORT_ORDER1" => "DESC",
		"SORT_BY2" => "SORT",
		"SORT_ORDER2" => "ASC",
		"CACHE_TYPE" => "A",
		"CACHE_TIME" => "36000000",
		"CACHE_NOTES" => "",
		"CACHE_GROUPS" => "N"
	),
false
);?> 
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>