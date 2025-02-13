<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
$this->setFrameMode(true);
//test_dump($USER,$arResult);
?>

<?if($arParams["USE_RSS"]=="Y"):?>
	<?
	$rss_url = str_replace(
		array("#SECTION_ID#", "#SECTION_CODE#")
		,array(urlencode($arResult["VARIABLES"]["SECTION_ID"]), urlencode($arResult["VARIABLES"]["SECTION_CODE"]))
		,$arResult["FOLDER"].$arResult["URL_TEMPLATES"]["rss_section"]
	);
	if(method_exists($APPLICATION, 'addheadstring'))
		$APPLICATION->AddHeadString('<link rel="alternate" type="application/rss+xml" title="'.$rss_url.'" href="'.$rss_url.'" />');
	?>
	<!--<a href="<?=$rss_url?>" title="rss" target="_self"><img alt="RSS" src="<?=$templateFolder?>/images/gif-light/feed-icon-16x16.gif" border="0" align="right" /></a>-->
<?endif;?>
	<div class='box'> <!-- МЕНЮ -->
			<nav>
				<?$APPLICATION->IncludeComponent("bitrix:menu", "menu_top", array(
					"ROOT_MENU_TYPE" => "top",
					"MENU_CACHE_TYPE" => "A",
					"MENU_CACHE_TIME" => "360000",
					"MENU_CACHE_USE_GROUPS" => "Y",
					"MENU_CACHE_GET_VARS" => array(
					),
					"MAX_LEVEL" => "1",
					"CHILD_MENU_TYPE" => "top",
					"USE_EXT" => "Y",
					"DELAY" => "N",
					"ALLOW_MULTI_SELECT" => "N"
				),
					$component
				);?>
			</nav>
		</div> <!--end menu_wrapper -->
<div class='box'>
    <div id="makeMeScrollable">
        <?$iblock_id=SITE_ID=='s1'?2:16;
		$arFilter = Array("IBLOCK_ID"=>$iblock_id,'CODE' => $arResult['VARIABLES']['SECTION_CODE']);
		$dbSec = CIBlockSection::GetList(array(),$arFilter);
		$section=$dbSec->GetNext();
		$section_filter=$section['DEPTH_LEVEL']=='1'?$section['ID']:$section['IBLOCK_SECTION_ID'];
		$arFilter = Array("IBLOCK_ID"=>$iblock_id,'SECTION_ID' => $section_filter);
		$db_list = CIBlockSection::GetList(Array("SORT" => "ASC"), $arFilter);

		while ($ar_result = $db_list->GetNext()) {
		?>
		<p>
			<a href="<?='/mobile'.$ar_result['SECTION_PAGE_URL']?>"><?= $ar_result['NAME'] ?></a>
		</p>
		<?
		}
		?>
    </div>
</div>
<?
	$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH.'/css/smoothDivScroll.css');
	$APPLICATION->AddHeadScript(SITE_TEMPLATE_PATH .'/js/jquery.smoothdivscroll-1.3-min.js');
	?>
	<script type="text/javascript">
		(function() {
			$("#makeMeScrollable").smoothDivScroll({
				touchScrolling: true,
				manualContinuousScrolling: false,
				hotSpotScrolling: false,
				mousewheelScrolling: false
			});
		})()
</script>
	<div class="media_block_head">
			<h1>
				<?=$section['NAME']?>
			</h1>
		<span class="now"></span>
	</div>
<?$APPLICATION->IncludeComponent(
	"bitrix:news.list",
	"",
	Array(
		"IBLOCK_TYPE"	=>	$arParams["IBLOCK_TYPE"],
		"IBLOCK_ID"	=>	$arParams["IBLOCK_ID"],
		"NEWS_COUNT"	=>	$arParams["NEWS_COUNT"],
		"SORT_BY1"	=>	$arParams["SORT_BY1"],
		"SORT_ORDER1"	=>	$arParams["SORT_ORDER1"],
		"SORT_BY2"	=>	$arParams["SORT_BY2"],
		"SORT_ORDER2"	=>	$arParams["SORT_ORDER2"],
		"FIELD_CODE"	=>	$arParams["LIST_FIELD_CODE"],
		"PROPERTY_CODE"	=>	$arParams["LIST_PROPERTY_CODE"],
		"DISPLAY_PANEL"	=>	$arParams["DISPLAY_PANEL"],
		"SET_TITLE"	=>	$arParams["SET_TITLE"],
		"SET_STATUS_404" => $arParams["SET_STATUS_404"],
		"INCLUDE_IBLOCK_INTO_CHAIN"	=>	$arParams["INCLUDE_IBLOCK_INTO_CHAIN"],
		"ADD_SECTIONS_CHAIN"	=>	$arParams["ADD_SECTIONS_CHAIN"],
		"CACHE_TYPE"	=>	$arParams["CACHE_TYPE"],
		"CACHE_TIME"	=>	$arParams["CACHE_TIME"],
		"CACHE_FILTER"	=>	$arParams["CACHE_FILTER"],
		"CACHE_GROUPS" => $arParams["CACHE_GROUPS"],
		"DISPLAY_TOP_PAGER"	=>	$arParams["DISPLAY_TOP_PAGER"],
		"DISPLAY_BOTTOM_PAGER"	=>	$arParams["DISPLAY_BOTTOM_PAGER"],
		"PAGER_TITLE"	=>	$arParams["PAGER_TITLE"],
		"PAGER_TEMPLATE"	=>	$arParams["PAGER_TEMPLATE"],
		"PAGER_SHOW_ALWAYS"	=>	$arParams["PAGER_SHOW_ALWAYS"],
		"PAGER_DESC_NUMBERING"	=>	$arParams["PAGER_DESC_NUMBERING"],
		"PAGER_DESC_NUMBERING_CACHE_TIME"	=>	$arParams["PAGER_DESC_NUMBERING_CACHE_TIME"],
		"PAGER_SHOW_ALL" => $arParams["PAGER_SHOW_ALL"],
		"DISPLAY_DATE"	=>	$arParams["DISPLAY_DATE"],
		"DISPLAY_NAME"	=>	"Y",
		"DISPLAY_PICTURE"	=>	$arParams["DISPLAY_PICTURE"],
		"DISPLAY_PREVIEW_TEXT"	=>	$arParams["DISPLAY_PREVIEW_TEXT"],
		"PREVIEW_TRUNCATE_LEN"	=>	$arParams["PREVIEW_TRUNCATE_LEN"],
		"ACTIVE_DATE_FORMAT"	=>	$arParams["LIST_ACTIVE_DATE_FORMAT"],
		"USE_PERMISSIONS"	=>	$arParams["USE_PERMISSIONS"],
		"GROUP_PERMISSIONS"	=>	$arParams["GROUP_PERMISSIONS"],
		"FILTER_NAME"	=>	$arParams["FILTER_NAME"],
		"HIDE_LINK_WHEN_NO_DETAIL"	=>	$arParams["HIDE_LINK_WHEN_NO_DETAIL"],
		"CHECK_DATES"	=>	$arParams["CHECK_DATES"],
		"PARENT_SECTION"	=>	$arResult["VARIABLES"]["SECTION_ID"],
		"PARENT_SECTION_CODE"	=>	$arResult["VARIABLES"]["SECTION_CODE"],
		"DETAIL_URL"	=>	$arResult["FOLDER"].$arResult["URL_TEMPLATES"]["detail"],
		"DISPLAY_IMG_WIDTH"	=>	$arParams["DISPLAY_IMG_WIDTH"],
		"DISPLAY_IMG_HEIGHT"	=>	$arParams["DISPLAY_IMG_HEIGHT"],
		"DISPLAY_IMG_MEDIUM_WIDTH"	=>	$arParams["DISPLAY_IMG_MEDIUM_WIDTH"],
		"DISPLAY_IMG_MEDIUM_HEIGHT"	=>	$arParams["DISPLAY_IMG_MEDIUM_HEIGHT"],	
	),
	$component
);?>