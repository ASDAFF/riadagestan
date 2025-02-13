<?
/** @global CMain $APPLICATION */
/** @global CDatabase $DB */
/** @global CUser $USER */

use Bitrix\Main\Loader,
	Bitrix\Main,
	Bitrix\Iblock,
	Bitrix\Currency,
	Bitrix\Catalog;

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
Loader::includeModule("iblock");
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/iblock/prolog.php");
IncludeModuleLangFile(__FILE__);
IncludeModuleLangFile($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/modules/main/interface/admin_lib.php");

$bBizproc = Loader::includeModule("bizproc");
$bWorkflow = Loader::includeModule("workflow");
$bFileman = Loader::includeModule("fileman");
$bExcel = isset($_REQUEST["mode"]) && ($_REQUEST["mode"] == "excel");
$dsc_cookie_name = (string)Main\Config\Option::get('main', 'cookie_name', 'BITRIX_SM')."_DSC";

$publicMode = $adminPage->publicMode;
$selfFolderUrl = $adminPage->getSelfFolderUrl();

$bSearch = false;
$bCurrency = false;
$arCurrencyList = array();
$elementsList = array();

$listImageSize = Main\Config\Option::get('iblock', 'list_image_size');
$minImageSize = array("W" => 1, "H"=>1);
$maxImageSize = array(
	"W" => $listImageSize,
	"H" => $listImageSize,
);
unset($listImageSize);
$useCalendarTime = (string)Main\Config\Option::get('iblock', 'list_full_date_edit') == 'Y';

if (isset($_REQUEST['mode']) && ($_REQUEST['mode']=='list' || $_REQUEST['mode']=='frame'))
	CFile::DisableJSFunction(true);

$type = '';
if (isset($_REQUEST['type']) && is_string($_REQUEST['type']))
	$type = trim($_REQUEST['type']);
if ($type === '')
	$APPLICATION->AuthForm(GetMessage("IBLOCK_BAD_BLOCK_TYPE_ID"));

$arIBTYPE = CIBlockType::GetByIDLang($type, LANGUAGE_ID);
if($arIBTYPE===false)
	$APPLICATION->AuthForm(GetMessage("IBLOCK_BAD_BLOCK_TYPE_ID"));

$IBLOCK_ID = 0;
if (isset($_REQUEST['IBLOCK_ID']))
	$IBLOCK_ID = (int)$_REQUEST["IBLOCK_ID"];

$arIBlock = CIBlock::GetArrayByID($IBLOCK_ID);
if($arIBlock)
	$bBadBlock = !CIBlockRights::UserHasRightTo($IBLOCK_ID, $IBLOCK_ID, "iblock_admin_display");
else
	$bBadBlock = true;

if($bBadBlock)
{
	$APPLICATION->SetTitle($arIBTYPE["NAME"]);
	require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
	ShowError(GetMessage("IBLOCK_BAD_IBLOCK"));?>
	<a href="<?echo htmlspecialcharsbx("iblock_admin.php?lang=".LANGUAGE_ID."&type=".urlencode($_REQUEST["type"]))?>"><?echo GetMessage("IBLOCK_BACK_TO_ADMIN")?></a>
	<?
	require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
	die();
}

$arIBlock["SITE_ID"] = array();
$rsSites = CIBlock::GetSite($IBLOCK_ID);
while($arSite = $rsSites->Fetch())
	$arIBlock["SITE_ID"][] = $arSite["LID"];

$bWorkFlow = $bWorkflow && (CIBlock::GetArrayByID($IBLOCK_ID, "WORKFLOW") != "N");
$bBizproc = $bBizproc && (CIBlock::GetArrayByID($IBLOCK_ID, "BIZPROC") != "N");

define("MODULE_ID", "iblock");
define("ENTITY", "CIBlockDocument");
define("DOCUMENT_TYPE", "iblock_".$IBLOCK_ID);

$bCatalog = Loader::includeModule("catalog");
$arCatalog = false;
$boolSKU = false;
$boolSKUFiltrable = false;
$strSKUName = '';
$uniq_id = 0;
$strUseStoreControl = '';
$strSaveWithoutPrice = '';
$boolCatalogRead = false;
$boolCatalogPrice = false;
$boolCatalogPurchasInfo = false;
$catalogPurchasInfoEdit = false;
$boolCatalogSet = false;
$showCatalogWithOffers = false;
$productTypeList = array();
if ($bCatalog)
{
	$strUseStoreControl = (string)Main\Config\Option::get('catalog', 'default_use_store_control');
	$strSaveWithoutPrice = (string)Main\Config\Option::get('catalog','save_product_without_price');
	$boolCatalogRead = $USER->CanDoOperation('catalog_read');
	$boolCatalogPrice = $USER->CanDoOperation('catalog_price');
	$boolCatalogPurchasInfo = $USER->CanDoOperation('catalog_purchas_info');
	$boolCatalogSet = CBXFeatures::IsFeatureEnabled('CatCompleteSet');
	$arCatalog = CCatalogSKU::GetInfoByIBlock($arIBlock["ID"]);
	if (empty($arCatalog))
	{
		$bCatalog = false;
	}
	else
	{
		if (CCatalogSKU::TYPE_PRODUCT == $arCatalog['CATALOG_TYPE'] || CCatalogSKU::TYPE_FULL == $arCatalog['CATALOG_TYPE'])
		{
			if (CIBlockRights::UserHasRightTo($arCatalog['IBLOCK_ID'], $arCatalog['IBLOCK_ID'], "iblock_admin_display"))
			{
				$boolSKU = true;
				$strSKUName = GetMessage('IBEL_A_OFFERS');
			}
		}
		if (!$boolCatalogRead && !$boolCatalogPrice)
			$bCatalog = false;
		$productTypeList = CCatalogAdminTools::getIblockProductTypeList($arIBlock['ID'], true);
	}
	$showCatalogWithOffers = ((string)Main\Config\Option::get('catalog', 'show_catalog_tab_with_offers') == 'Y');
	if ($boolCatalogPurchasInfo)
		$catalogPurchasInfoEdit = $boolCatalogPrice && $strUseStoreControl != 'Y';
}

$dbrFProps = CIBlockProperty::GetList(
	array(
		"SORT"=>"ASC",
		"NAME"=>"ASC"
	),
	array(
		"IBLOCK_ID"=>$IBLOCK_ID,
		"CHECK_PERMISSIONS"=>"N",
	)
);

$arFileProps = array();
$arProps = array();
while ($arProp = $dbrFProps->GetNext())
{
	if ($arProp["ACTIVE"] == "Y")
	{
		$arProp["PROPERTY_USER_TYPE"] = ('' != $arProp["USER_TYPE"] ? CIBlockProperty::GetUserType($arProp["USER_TYPE"]) : array());
		$arProps[] = $arProp;
	}

	if ($arProp["PROPERTY_TYPE"] == Iblock\PropertyTable::TYPE_FILE)
		$arFileProps[$arProp["ID"]] = $arProp;
}
unset($arProp, $dbrFProps);

if ($boolSKU)
{
	$dbrFProps = CIBlockProperty::GetList(
		array(
			"SORT" => "ASC",
			"NAME" => "ASC"
		),
		array(
			"IBLOCK_ID" => $arCatalog['IBLOCK_ID'],
			"ACTIVE" => "Y",
			"FILTRABLE" => "Y",
			"CHECK_PERMISSIONS" => "N",
		)
	);

	$arSKUProps = array();
	while ($arProp = $dbrFProps->GetNext())
	{
		if ($arProp['PROPERTY_TYPE'] == Iblock\PropertyTable::TYPE_FILE || $arCatalog['SKU_PROPERTY_ID'] == $arProp['ID'])
			continue;

		$arProp["PROPERTY_USER_TYPE"] = ('' != $arProp["USER_TYPE"] ? CIBlockProperty::GetUserType($arProp["USER_TYPE"]) : array());
		$boolSKUFiltrable = true;
		$arSKUProps[] = $arProp;
	}
	unset($arProp, $dbrFProps);
}

$sTableID = (defined("CATALOG_PRODUCT")? "tbl_product_admin_": "tbl_iblock_element_").md5($_REQUEST["type"].".".$IBLOCK_ID);
$oSort = new CAdminSorting($sTableID, "timestamp_x", "desc");
global $by, $order;
if (!isset($by))
	$by = 'ID';
if (!isset($order))
	$order = 'asc';
$by = strtoupper($by);
switch ($by)
{
	case 'ID':
		$arOrder = array('ID' => $order);
		break;
	case 'CATALOG_TYPE':
		$arOrder = array('CATALOG_TYPE' => $order, 'CATALOG_BUNDLE' => $order, 'ID' => 'ASC');
		break;
	default:
		$arOrder = array($by => $order, 'ID' => 'ASC');
		break;
}
$lAdmin = new CAdminUiList($sTableID, $oSort);
$lAdmin->bMultipart = true;

if(isset($_REQUEST["del_filter"]) && $_REQUEST["del_filter"] != "")
	$find_section_section = -1;
elseif(isset($_REQUEST["find_section_section"]))
	$find_section_section = $_REQUEST["find_section_section"];
else
	$find_section_section = -1;
//We have to handle current section in a special way
$section_id = intval($find_section_section);
if(!defined("CATALOG_PRODUCT"))
	$find_section_section = $section_id;
//This is all parameters needed for proper navigation
$sThisSectionUrl = '&type='.urlencode($type).'&lang='.LANGUAGE_ID.'&IBLOCK_ID='.$IBLOCK_ID.
	'&find_section_section='.intval($find_section_section);

$sectionItems = array();
$sectionQueryObject = CIBlockSection::GetTreeList(Array("IBLOCK_ID"=>$IBLOCK_ID), array("ID", "NAME", "DEPTH_LEVEL"));
while($arSection = $sectionQueryObject->Fetch())
	$sectionItems[$arSection["ID"]] = str_repeat(" . ", $arSection["DEPTH_LEVEL"]).$arSection["NAME"];

/*
 * TODO Initially, the ability to filter by these fields was not implemented.
 * "find_el_modified_by",
 * "find_el_created_by",
 */

$filterFields = array(
	array(
		"id" => "NAME",
		"name" => GetMessage("IBLOCK_FIELD_NAME"),
		"filterable" => "?",
		"quickSearch" => "?",
		"default" => true
	),
	array(
		"id" => "ID",
		"name" => rtrim(GetMessage("IBLOCK_FILTER_FROMTO_ID"), ":"),
		"type" => "number",
		"filterable" => ""
	)
);
if ($arIBTYPE["SECTIONS"] == "Y")
{
	$filterFields[] = array(
		"id" => "SECTION_ID",
		"name" => GetMessage("IBLOCK_FIELD_SECTION_ID"),
		"type" => "list",
		"items" => $sectionItems,
		"filterable" => ""
	);
}
$filterFields[] = array(
	"id" => "DATE_MODIFY_FROM",
	"name" => GetMessage("IBLOCK_FIELD_TIMESTAMP_X"),
	"type" => "date",
	"filterable" => ""
);
$filterFields[] = array(
	"id" => "MODIFIED_USER_ID",
	"name" => GetMessage("IBLOCK_FIELD_MODIFIED_BY"),
	"type" => "custom_entity",
	"selector" => array("type" => "user"),
	"filterable" => ""
);
$filterFields[] = array(
	"id" => "DATE_CREATE",
	"name" => GetMessage("IBLOCK_EL_ADMIN_DCREATE"),
	"type" => "date",
	"filterable" => ""
);
$filterFields[] = array(
	"id" => "CREATED_USER_ID",
	"name" => rtrim(GetMessage("IBLOCK_EL_ADMIN_WCREATE"), ":"),
	"type" => "custom_entity",
	"selector" => array("type" => "user"),
	"filterable" => ""
);
$filterFields[] = array(
	"id" => "DATE_ACTIVE_FROM",
	"name" => GetMessage("IBEL_A_ACTFROM"),
	"type" => "date",
	"filterable" => ""
);
$filterFields[] = array(
	"id" => "DATE_ACTIVE_TO",
	"name" => GetMessage("IBEL_A_ACTTO"),
	"type" => "date",
	"filterable" => ""
);
$filterFields[] = array(
	"id" => "ACTIVE",
	"name" => GetMessage("IBLOCK_FIELD_ACTIVE"),
	"type" => "list",
	"items" => array(
		"Y" => GetMessage("IBLOCK_YES"),
		"N" => GetMessage("IBLOCK_NO")
	),
	"filterable" => ""
);
$filterFields[] = array(
	"id" => "SEARCHABLE_CONTENT",
	"name" => rtrim(GetMessage("IBLOCK_EL_ADMIN_DESC"), ":"),
	"filterable" => "?"
);
if ($bWorkFlow)
{
	$workflowStatus = array();
	$rs = CWorkflowStatus::GetDropDownList("Y");
	while ($arRs = $rs->GetNext())
		$workflowStatus[$arRs["REFERENCE_ID"]] = $arRs["REFERENCE"];
	$filterFields[] = array(
		"id" => "WF_STATUS",
		"name" => GetMessage("IBLIST_A_STATUS"),
		"type" => "list",
		"items" => $workflowStatus,
		"filterable" => ""
	);
}
$filterFields[] = array(
	"id" => "CODE",
	"name" => GetMessage("IBEL_A_CODE"),
	"filterable" => ""
);
$filterFields[] = array(
	"id" => "EXTERNAL_ID",
	"name" => GetMessage("IBEL_A_EXTERNAL_ID"),
	"filterable" => ""
);
$filterFields[] = array(
	"id" => "TAGS",
	"name" => GetMessage("IBEL_A_TAGS"),
	"filterable" => "?"
);

if ($bCatalog)
{
	$filterFields[] = array(
		"id" => "CATALOG_TYPE",
		"name" => GetMessage("IBEL_CATALOG_TYPE"),
		"type" => "list",
		"items" => $productTypeList,
		"params" => array("multiple" => "Y"),
		"filterable" => ""
	);
	if ($boolCatalogSet)
	{
		$filterFields[] = array(
			"id" => "CATALOG_BUNDLE",
			"name" => GetMessage("IBEL_CATALOG_BUNDLE"),
			"type" => "list",
			"items" => array(
				"Y" => GetMessage("IBLOCK_YES"),
				"N" => GetMessage("IBLOCK_NO")
			),
			"filterable" => ""
		);
	}
	$filterFields[] = array(
		"id" => "CATALOG_AVAILABLE",
		"name" => GetMessage("IBEL_CATALOG_AVAILABLE"),
		"type" => "list",
		"items" => array(
			"Y" => GetMessage("IBLOCK_YES"),
			"N" => GetMessage("IBLOCK_NO")
		),
		"filterable" => ""
	);
}

$propertyManager = new Iblock\Helpers\Filter\PropertyManager($IBLOCK_ID);
$filterFields = array_merge($filterFields, $propertyManager->getFilterFields());
$lAdmin->BeginEpilogContent();
$propertyManager->renderCustomFields($sTableID);
$lAdmin->EndEpilogContent();
if ($boolSKU)
{
	$propertySKUManager = new Iblock\Helpers\Filter\PropertyManager($arCatalog["IBLOCK_ID"]);
	$propertySKUFilterFields = $propertySKUManager->getFilterFields();
	$lAdmin->BeginEpilogContent();
	$propertySKUManager->renderCustomFields($sTableID);
	$lAdmin->EndEpilogContent();
}

$arFilter = array(
	"IBLOCK_ID" => $IBLOCK_ID,
	"SHOW_NEW" => "Y",
	"CHECK_PERMISSIONS" => "Y",
	"MIN_PERMISSION" => "R",
);

$lAdmin->AddFilter($filterFields, $arFilter);
$propertyManager->AddFilter($sTableID, $arFilter);

$arSubQuery = array();
if ($boolSKU)
{
	$filterFields = array_merge($filterFields, $propertySKUFilterFields);
	if ($boolSKUFiltrable)
	{
		$arSubQuery = array("IBLOCK_ID" => $arCatalog["IBLOCK_ID"]);
		$lAdmin->AddFilter($propertySKUFilterFields, $arSubQuery);
		$propertySKUManager->AddFilter($sTableID, $arSubQuery);
	}
}

if (!is_null($arFilter["SECTION_ID"]))
{
	$find_section_section = intval($arFilter["SECTION_ID"]);
}
else
{
	if (isset($_REQUEST["apply_filter"]))
	{
		$find_section_section = $arFilter["SECTION_ID"] =
			(is_null($arFilter["SECTION_ID"]) ? "" : intval($arFilter["SECTION_ID"]));
	}
	else
	{
		$arFilter["SECTION_ID"] = $find_section_section;
	}
}

if ($bBizproc && "E" != $arIBlock["RIGHTS_MODE"])
{
	$strPerm = CIBlock::GetPermission($IBLOCK_ID);
	if ("W" > $strPerm)
	{
		unset($arFilter["CHECK_PERMISSIONS"]);
		unset($arFilter["MIN_PERMISSION"]);
		$arFilter["CHECK_BP_PERMISSIONS"] = "read";
	}
}

if (!empty($arFilter[">=DATE_MODIFY_FROM"]))
{
	$arFilter["DATE_MODIFY_FROM"] = $arFilter[">=DATE_MODIFY_FROM"];
	$arFilter["DATE_MODIFY_TO"] = $arFilter["<=DATE_MODIFY_FROM"];
	unset($arFilter[">=DATE_MODIFY_FROM"]);
	unset($arFilter["<=DATE_MODIFY_FROM"]);
}

if ($boolSKU && 1 < sizeof($arSubQuery))
{
	$arFilter["ID"] = CIBlockElement::SubQuery("PROPERTY_".$arCatalog["SKU_PROPERTY_ID"], $arSubQuery);
}

if (intval($find_section_section) < 0 || strlen($find_section_section) <= 0)
{
	unset($arFilter["SECTION_ID"]);
}
else
{
	$arFilter["INCLUDE_SUBSECTIONS"] = "Y";
}

if($lAdmin->EditAction())
{
	if(is_array($_FILES['FIELDS']))
		CAllFile::ConvertFilesToPost($_FILES['FIELDS'], $_POST['FIELDS']);

	if ($bCatalog)
	{
		Catalog\Product\Sku::enableDeferredCalculation();
	}

	if (is_array($_POST['FIELDS']))
	{
		foreach($_POST['FIELDS'] as $ID=>$arFields)
		{
			if(!$lAdmin->IsUpdated($ID))
				continue;
			$ID = (int)$ID;

			$arRes = CIBlockElement::GetByID($ID);
			$arRes = $arRes->Fetch();
			if(!$arRes)
				continue;

			$WF_ID = $ID;
			if($bWorkFlow)
			{
				$WF_ID = CIBlockElement::WF_GetLast($ID);
				if($WF_ID!=$ID)
				{
					$rsData2 = CIBlockElement::GetByID($WF_ID);
					if($arRes = $rsData2->Fetch())
						$WF_ID = $arRes["ID"];
					else
						$WF_ID = $ID;
				}

				if($arRes["LOCK_STATUS"]=='red' && !($_REQUEST['action']=='unlock' && CWorkflow::IsAdmin()))
				{
					$lAdmin->AddGroupError(GetMessage("IBEL_A_UPDERR1")." (ID:".$ID.")", $ID);
					continue;
				}
			}
			elseif ($bBizproc)
			{
				if (CIBlockDocument::IsDocumentLocked($ID, ""))
				{
					$lAdmin->AddGroupError(GetMessage("IBEL_A_UPDERR_LOCKED", array("#ID#" => $ID)), $ID);
					continue;
				}
			}

			if($bWorkFlow)
			{
				if (!CIBlockElementRights::UserHasRightTo($IBLOCK_ID, $ID, "element_edit"))
				{
					$lAdmin->AddGroupError(GetMessage("IBEL_A_UPDERR3")." (ID:".$ID.")", $ID);
					continue;
				}

				// handle workflow status access permissions
				if (CIBlockElementRights::UserHasRightTo($IBLOCK_ID, $ID, "element_edit_any_wf_status"))
					$STATUS_PERMISSION = true;
				elseif ($arFields["WF_STATUS_ID"] > 0)
					$STATUS_PERMISSION = CIBlockElement::WF_GetStatusPermission($arFields["WF_STATUS_ID"]) >= 1;
				else
					$STATUS_PERMISSION = CIBlockElement::WF_GetStatusPermission($arRes["WF_STATUS_ID"]) >= 2;

				if (!$STATUS_PERMISSION)
				{
					$lAdmin->AddGroupError(GetMessage("IBEL_A_UPDERR_ACCESS", array("#ID#" => $ID)), $ID);
					continue;
				}

			}
			elseif($bBizproc)
			{
				$bCanWrite = CIBlockDocument::CanUserOperateDocument(
					CBPCanUserOperateOperation::WriteDocument,
					$USER->GetID(),
					$ID,
					array(
						"IBlockId" => $IBLOCK_ID,
						'IBlockRightsMode' => $arIBlock['RIGHTS_MODE'],
						'UserGroups' => $USER->GetUserGroupArray()
					)
				);
				if(!$bCanWrite)
				{
					$lAdmin->AddGroupError(GetMessage("IBEL_A_UPDERR3")." (ID:".$ID.")", $ID);
					continue;
				}
			}
			elseif(!CIBlockElementRights::UserHasRightTo($IBLOCK_ID, $ID, "element_edit"))
			{
				$lAdmin->AddGroupError(GetMessage("IBEL_A_UPDERR3")." (ID:".$ID.")", $ID);
				continue;
			}

			if (array_key_exists("PREVIEW_PICTURE", $arFields))
			{
				$arFields["PREVIEW_PICTURE"] = CIBlock::makeFileArray(
					$arFields["PREVIEW_PICTURE"],
					$_REQUEST["FIELDS_del"][$ID]["PREVIEW_PICTURE"] === "Y",
					$_REQUEST["FIELDS_descr"][$ID]["PREVIEW_PICTURE"]
				);
			}

			if (array_key_exists("DETAIL_PICTURE", $arFields))
			{
				$arFields["DETAIL_PICTURE"] = CIBlock::makeFileArray(
					$arFields["DETAIL_PICTURE"],
					$_REQUEST["FIELDS_del"][$ID]["DETAIL_PICTURE"] === "Y",
					$_REQUEST["FIELDS_descr"][$ID]["DETAIL_PICTURE"]
				);
			}

			if(!is_array($arFields["PROPERTY_VALUES"]))
				$arFields["PROPERTY_VALUES"] = array();
			$bFieldProps = array();
			foreach($arFields as $k=>$v)
			{
				if(
					$k != "PROPERTY_VALUES"
					&& strncmp($k, "PROPERTY_", 9) == 0
				)
				{
					$prop_id = substr($k, 9);

					if (isset($arFileProps[$prop_id]))
					{
						foreach ($v as $prop_value_id => $file)
						{
							$v[$prop_value_id] = CIBlock::makeFilePropArray(
								$v[$prop_value_id],
								$_REQUEST["FIELDS_del"][$ID][$k][$prop_value_id]["VALUE"] === "Y",
								$_REQUEST["FIELDS_descr"][$ID][$k][$prop_value_id]["VALUE"]
							);
						}
					}

					if(isset($_REQUEST["FIELDS_descr"][$ID][$k]) && is_array($_REQUEST["FIELDS_descr"][$ID][$k]))
					{
						foreach($_REQUEST["FIELDS_descr"][$ID][$k] as $PROPERTY_VALUE_ID => $ar)
						{
							if(
								is_array($ar)
								&& isset($ar["VALUE"])
								&& isset($v[$PROPERTY_VALUE_ID]["VALUE"])
								&& is_array($v[$PROPERTY_VALUE_ID]["VALUE"])
							)
								$v[$PROPERTY_VALUE_ID]["DESCRIPTION"] = $ar["VALUE"];
						}
					}

					$arFields["PROPERTY_VALUES"][$prop_id] = $v;
					unset($arFields[$k]);
					$bFieldProps[$prop_id]=true;
				}

				if ($k == "TAGS" && is_array($v))
					$arFields[$k] = $v[0];
			}

			if(!empty($bFieldProps))
			{
				//We have to read properties from database in order not to delete its values
				if(!$bWorkFlow)
				{
					$dbPropV = CIBlockElement::GetProperty($IBLOCK_ID, $ID, "sort", "asc", array("ACTIVE"=>"Y"));
					while($arPropV = $dbPropV->Fetch())
					{
						if(!array_key_exists($arPropV["ID"], $bFieldProps) && $arPropV["PROPERTY_TYPE"] != "F")
						{
							if(!array_key_exists($arPropV["ID"], $arFields["PROPERTY_VALUES"]))
								$arFields["PROPERTY_VALUES"][$arPropV["ID"]] = array();

							$arFields["PROPERTY_VALUES"][$arPropV["ID"]][$arPropV["PROPERTY_VALUE_ID"]] = array(
								"VALUE" => $arPropV["VALUE"],
								"DESCRIPTION" => $arPropV["DESCRIPTION"],
							);
						}
					}
				}
			}
			else
			{
				//We will not update property values
				unset($arFields["PROPERTY_VALUES"]);
			}

			//All not displayed required fields from DB
			foreach($arIBlock["FIELDS"] as $FIELD_ID => $field)
			{
				if(
					$field["IS_REQUIRED"] === "Y"
					&& !array_key_exists($FIELD_ID, $arFields)
					&& $FIELD_ID !== "DETAIL_PICTURE"
					&& $FIELD_ID !== "PREVIEW_PICTURE"
				)
					$arFields[$FIELD_ID] = $arRes[$FIELD_ID];
			}
			if($arRes["IN_SECTIONS"] == "Y")
			{
				$arFields["IBLOCK_SECTION"] = array();
				$rsSections = CIBlockElement::GetElementGroups($arRes["ID"], true, array('ID', 'IBLOCK_ELEMENT_ID'));
				while($arSection = $rsSections->Fetch())
					$arFields["IBLOCK_SECTION"][] = $arSection["ID"];
			}

			$arFields["MODIFIED_BY"] = $USER->GetID();
			$ib = new CIBlockElement();
			$DB->StartTransaction();

			if(!$ib->Update($ID, $arFields, true, true, true))
			{
				$lAdmin->AddGroupError(GetMessage("IBEL_A_SAVE_ERROR", array("#ID#"=>$ID, "#ERROR_TEXT#"=>$ib->LAST_ERROR)), $ID);
				$DB->Rollback();
			}
			else
			{
				$ipropValues = new \Bitrix\Iblock\InheritedProperty\ElementValues($IBLOCK_ID, $ID);
				$ipropValues->clearValues();
				$DB->Commit();
			}

			if ($bCatalog)
			{
				if(
					$boolCatalogPrice
					&& CIBlockElementRights::UserHasRightTo($IBLOCK_ID, $ID, "element_edit_price")
				)
				{
					$arCatalogProduct = array();
					if (isset($arFields['CATALOG_WEIGHT']) && '' != $arFields['CATALOG_WEIGHT'])
						$arCatalogProduct['WEIGHT'] = $arFields['CATALOG_WEIGHT'];

					if (isset($arFields['CATALOG_WIDTH']) && '' != $arFields['CATALOG_WIDTH'])
						$arCatalogProduct['WIDTH'] = $arFields['CATALOG_WIDTH'];
					if (isset($arFields['CATALOG_LENGTH']) && '' != $arFields['CATALOG_LENGTH'])
						$arCatalogProduct['LENGTH'] = $arFields['CATALOG_LENGTH'];
					if (isset($arFields['CATALOG_HEIGHT']) && '' != $arFields['CATALOG_HEIGHT'])
						$arCatalogProduct['HEIGHT'] = $arFields['CATALOG_HEIGHT'];

					if (isset($arFields['CATALOG_VAT_INCLUDED']) && !empty($arFields['CATALOG_VAT_INCLUDED']))
						$arCatalogProduct['VAT_INCLUDED'] = $arFields['CATALOG_VAT_INCLUDED'];
					if (isset($arFields['CATALOG_QUANTITY_TRACE']) && !empty($arFields['CATALOG_QUANTITY_TRACE']))
						$arCatalogProduct['QUANTITY_TRACE'] = $arFields['CATALOG_QUANTITY_TRACE'];
					if (isset($arFields['CATALOG_MEASURE']) && is_string($arFields['CATALOG_MEASURE']) && (int)$arFields['CATALOG_MEASURE'] > 0)
						$arCatalogProduct['MEASURE'] = $arFields['CATALOG_MEASURE'];

					if ($catalogPurchasInfoEdit)
					{
						if (
							isset($arFields['CATALOG_PURCHASING_PRICE']) && is_string($arFields['CATALOG_PURCHASING_PRICE']) && $arFields['CATALOG_PURCHASING_PRICE'] != ''
							&& isset($arFields['CATALOG_PURCHASING_CURRENCY']) && is_string($arFields['CATALOG_PURCHASING_CURRENCY']) && $arFields['CATALOG_PURCHASING_CURRENCY'] != ''
						)
						{
							$arCatalogProduct['PURCHASING_PRICE'] = $arFields['CATALOG_PURCHASING_PRICE'];
							$arCatalogProduct['PURCHASING_CURRENCY'] = $arFields['CATALOG_PURCHASING_CURRENCY'];
						}
					}

					if ($strUseStoreControl != 'Y')
					{
						if (isset($arFields['CATALOG_QUANTITY']) && '' != $arFields['CATALOG_QUANTITY'])
							$arCatalogProduct['QUANTITY'] = $arFields['CATALOG_QUANTITY'];
					}

					$product = Catalog\Model\Product::getList(array(
						'select' => array('ID'),
						'filter' => array('=ID' => $ID)
					))->fetch();
					if (empty($product))
					{
						$arCatalogProduct['ID'] = $ID;
						$result = Catalog\Model\Product::add(array('fields' => $arCatalogProduct));
					}
					else
					{
						if (!empty($arCatalogProduct))
						{
							$result = Catalog\Model\Product::update($ID, array('fields' => $arCatalogProduct));
						}
					}
					unset($product);

					if (isset($arFields['CATALOG_MEASURE_RATIO']))
					{
						$newValue = trim($arFields['CATALOG_MEASURE_RATIO']);
						if ($newValue != '')
						{
							$intRatioID = 0;
							$ratio = Catalog\MeasureRatioTable::getList(array(
								'select' => array('ID', 'PRODUCT_ID'),
								'filter' => array('=PRODUCT_ID' => $ID, '=IS_DEFAULT' => 'Y'),
							))->fetch();
							if (!empty($ratio))
								$intRatioID = (int)$ratio['ID'];
							if ($intRatioID > 0)
								$ratioResult = CCatalogMeasureRatio::update($intRatioID, array('RATIO' => $newValue));
							else
								$ratioResult = CCatalogMeasureRatio::add(array('PRODUCT_ID' => $ID, 'RATIO' => $newValue, 'IS_DEFAULT' => 'Y'));
						}
						unset($newValue);
					}
				}
			}
		}
	}


	if($bCatalog)
	{
		if ($boolCatalogPrice && (isset($_POST["CATALOG_PRICE"]) || isset($_POST["CATALOG_CURRENCY"])))
		{
			$CATALOG_PRICE = $_POST["CATALOG_PRICE"];
			$CATALOG_CURRENCY = $_POST["CATALOG_CURRENCY"];
			$CATALOG_EXTRA = $_POST["CATALOG_EXTRA"];
			$CATALOG_PRICE_ID = $_POST["CATALOG_PRICE_ID"];
			$CATALOG_QUANTITY_FROM = $_POST["CATALOG_QUANTITY_FROM"];
			$CATALOG_QUANTITY_TO = $_POST["CATALOG_QUANTITY_TO"];
			$CATALOG_PRICE_old = $_POST["CATALOG_old_PRICE"];
			$CATALOG_CURRENCY_old = $_POST["CATALOG_old_CURRENCY"];

			$arCatExtraUp = array();
			$db_extras = CExtra::GetList(array("ID" => "ASC"));
			while ($extras = $db_extras->Fetch())
				$arCatExtraUp[$extras["ID"]] = $extras["PERCENTAGE"];

			$arBaseGroup = CCatalogGroup::GetBaseGroup();
			$arCatalogGroupList = CCatalogGroup::GetListArray();
			foreach($CATALOG_PRICE as $elID => $arPrice)
			{
				if (
					!(CIBlockElementRights::UserHasRightTo($IBLOCK_ID, $elID, "element_edit")
					&& CIBlockElementRights::UserHasRightTo($IBLOCK_ID, $elID, "element_edit_price"))
				)
					continue;

				$bError = false;

				if ($strSaveWithoutPrice != 'Y')
				{
					if (isset($arPrice[$arBaseGroup['ID']]))
					{
						if ($arPrice[$arBaseGroup['ID']] < 0)
						{
							$bError = true;
							$lAdmin->AddGroupError($elID.': '.GetMessage('IB_CAT_NO_BASE_PRICE'), $elID);
						}
					}
					else
					{
						$arBasePrice = CPrice::GetBasePrice(
							$elID,
							$CATALOG_QUANTITY_FROM[$elID][$arBaseGroup['ID']],
							$CATALOG_QUANTITY_FROM[$elID][$arBaseGroup['ID']],
							false
						);

						if (!is_array($arBasePrice) || $arBasePrice['PRICE'] < 0)
						{
							$bError = true;
							$lAdmin->AddGroupError($elID.': '.GetMessage('IB_CAT_NO_BASE_PRICE'), $elID);
						}
					}
				}

				if($bError)
					continue;

				$arCurrency = $CATALOG_CURRENCY[$elID];

				if (!empty($arCatalogGroupList))
				{
					foreach ($arCatalogGroupList as $arCatalogGroup)
					{
						if ($arPrice[$arCatalogGroup["ID"]] != $CATALOG_PRICE_old[$elID][$arCatalogGroup["ID"]]
							|| $arCurrency[$arCatalogGroup["ID"]] != $CATALOG_CURRENCY_old[$elID][$arCatalogGroup["ID"]])
						{
							if($arCatalogGroup["BASE"] == 'Y') // if base price check extra for other prices
							{
								$arFields = array(
									"PRODUCT_ID" => $elID,
									"CATALOG_GROUP_ID" => $arCatalogGroup["ID"],
									"PRICE" => $arPrice[$arCatalogGroup["ID"]],
									"CURRENCY" => $arCurrency[$arCatalogGroup["ID"]],
									"QUANTITY_FROM" => $CATALOG_QUANTITY_FROM[$elID][$arCatalogGroup["ID"]],
									"QUANTITY_TO" => $CATALOG_QUANTITY_TO[$elID][$arCatalogGroup["ID"]],
								);
								if (is_string($arFields['PRICE']))
									$arFields['PRICE'] = str_replace(',', '.', $arFields['PRICE']);
								if($arFields["PRICE"] < 0 || trim($arFields["PRICE"]) === '')
									CPrice::Delete($CATALOG_PRICE_ID[$elID][$arCatalogGroup["ID"]]);
								elseif((int)$CATALOG_PRICE_ID[$elID][$arCatalogGroup["ID"]] > 0)
									CPrice::Update($CATALOG_PRICE_ID[$elID][$arCatalogGroup["ID"]], $arFields);
								elseif($arFields["PRICE"] >= 0)
									CPrice::Add($arFields);

								$arPrFilter = array(
									"PRODUCT_ID" => $elID,
								);
								if ($arPrice[$arCatalogGroup["ID"]] >= 0)
								{
									$arPrFilter["!CATALOG_GROUP_ID"] = $arCatalogGroup["ID"];
									$arPrFilter["+QUANTITY_FROM"] = "1";
									$arPrFilter["!EXTRA_ID"] = false;
								}
								$db_res = CPrice::GetListEx(
									array(),
									$arPrFilter,
									false,
									false,
									array("ID", "PRODUCT_ID", "CATALOG_GROUP_ID", "PRICE", "CURRENCY", "QUANTITY_FROM", "QUANTITY_TO", "EXTRA_ID")
								);
								while($ar_res = $db_res->Fetch())
								{
									$arFields = array(
										"PRICE" => $arPrice[$arCatalogGroup["ID"]]*(1+$arCatExtraUp[$ar_res["EXTRA_ID"]]/100),
										"EXTRA_ID" => $ar_res["EXTRA_ID"],
										"CURRENCY" => $arCurrency[$arCatalogGroup["ID"]],
										"QUANTITY_FROM" => $ar_res["QUANTITY_FROM"],
										"QUANTITY_TO" => $ar_res["QUANTITY_TO"]
									);
									if ($arFields["PRICE"] <= 0)
										CPrice::Delete($ar_res["ID"]);
									else
										CPrice::Update($ar_res["ID"], $arFields);
								}
							}
							elseif (!isset($CATALOG_EXTRA[$elID][$arCatalogGroup["ID"]]))
							{
								$arFields = array(
									"PRODUCT_ID" => $elID,
									"CATALOG_GROUP_ID" => $arCatalogGroup["ID"],
									"PRICE" => $arPrice[$arCatalogGroup["ID"]],
									"CURRENCY" => $arCurrency[$arCatalogGroup["ID"]],
									"QUANTITY_FROM" => $CATALOG_QUANTITY_FROM[$elID][$arCatalogGroup["ID"]],
									"QUANTITY_TO" => $CATALOG_QUANTITY_TO[$elID][$arCatalogGroup["ID"]]
								);
								if (is_string($arFields['PRICE']))
									$arFields['PRICE'] = str_replace(',', '.', $arFields['PRICE']);
								if ($arFields["PRICE"] < 0 || trim($arFields["PRICE"]) === '')
									CPrice::Delete($CATALOG_PRICE_ID[$elID][$arCatalogGroup["ID"]]);
								elseif ((int)$CATALOG_PRICE_ID[$elID][$arCatalogGroup["ID"]] > 0)
									CPrice::Update($CATALOG_PRICE_ID[$elID][$arCatalogGroup["ID"]], $arFields);
								elseif($arFields["PRICE"] >= 0)
									CPrice::Add($arFields);
							}
						}
					}
					unset($arCatalogGroup);
				}

				$ipropValues = new \Bitrix\Iblock\InheritedProperty\ElementValues($IBLOCK_ID, $elID);
				$ipropValues->clearValues();
				\Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex($IBLOCK_ID, $elID);
			}
			unset($arCatalogGroupList);
		}
	}

	if ($bCatalog)
	{
		Catalog\Product\Sku::disableDeferredCalculation();
		Catalog\Product\Sku::calculate();
	}
}

if ($arID = $lAdmin->GroupAction())
{
	if ($_REQUEST['action_target']=='selected')
	{
		$rsData = CIBlockElement::GetList($arOrder, $arFilter, false, false, array('ID'));
		while($arRes = $rsData->Fetch())
			$arID[] = $arRes['ID'];
	}

	if ($bCatalog)
	{
		Catalog\Product\Sku::enableDeferredCalculation();
	}

	foreach($arID as $ID)
	{
		$ID = (int)$ID;
		if ($ID <= 0)
			continue;

		$arRes = CIBlockElement::GetByID($ID);
		$arRes = $arRes->Fetch();
		if(!$arRes)
			continue;

		$WF_ID = $ID;
		if($bWorkFlow)
		{
			$WF_ID = CIBlockElement::WF_GetLast($ID);
			if($WF_ID != $ID)
			{
				$rsData2 = CIBlockElement::GetByID($WF_ID);
				if($arRes = $rsData2->Fetch())
					$WF_ID = $arRes["ID"];
				else
					$WF_ID = $ID;
			}

			if($arRes["LOCK_STATUS"]=='red' && !($_REQUEST['action']=='unlock' && CWorkflow::IsAdmin()))
			{
				$lAdmin->AddGroupError(GetMessage("IBEL_A_UPDERR1")." (ID:".$ID.")", $ID);
				continue;
			}
		}
		elseif ($bBizproc)
		{
			if (CIBlockDocument::IsDocumentLocked($ID, "") && !($_REQUEST['action']=='unlock' && CBPDocument::IsAdmin()))
			{
				$lAdmin->AddGroupError(GetMessage("IBEL_A_UPDERR_LOCKED", array("#ID#" => $ID)), $ID);
				continue;
			}
		}

		$bPermissions = false;
		//delete and modify can:
		if ($bWorkFlow)
		{
			//For delete action we have to check all statuses in element history
			$STATUS_PERMISSION = CIBlockElement::WF_GetStatusPermission($arRes["WF_STATUS_ID"], $_REQUEST['action']=="delete"? $ID: false);
			if($STATUS_PERMISSION >= 2)
				$bPermissions = true;
		}
		elseif ($bBizproc)
		{
			$bCanWrite = CIBlockDocument::CanUserOperateDocument(
				CBPCanUserOperateOperation::WriteDocument,
				$USER->GetID(),
				$ID,
				array(
					"IBlockId" => $IBLOCK_ID,
					'IBlockRightsMode' => $arIBlock['RIGHTS_MODE'],
					'UserGroups' => $USER->GetUserGroupArray(),
				)
			);
			if ($bCanWrite)
				$bPermissions = true;
		}
		else
		{
			$bPermissions = true;
		}

		if(!$bPermissions)
		{
			$lAdmin->AddGroupError(GetMessage("IBEL_A_UPDERR3")." (ID:".$ID.")", $ID);
			continue;
		}

		switch($_REQUEST['action'])
		{
		case "delete":
			if(CIBlockElementRights::UserHasRightTo($IBLOCK_ID, $ID, "element_delete"))
			{
				@set_time_limit(0);
				$DB->StartTransaction();
				$APPLICATION->ResetException();
				if(!CIBlockElement::Delete($ID))
				{
					$DB->Rollback();
					if($ex = $APPLICATION->GetException())
						$lAdmin->AddGroupError(GetMessage("IBLOCK_DELETE_ERROR")." [".$ex->GetString()."]", $ID);
					else
						$lAdmin->AddGroupError(GetMessage("IBLOCK_DELETE_ERROR"), $ID);
				}
				else
				{
					$DB->Commit();
				}
			}
			else
			{
				$lAdmin->AddGroupError(GetMessage("IBLOCK_DELETE_ERROR")." [".$ID."]", $ID);
			}
			break;
		case "activate":
		case "deactivate":
			if(CIBlockElementRights::UserHasRightTo($IBLOCK_ID, $ID, "element_edit"))
			{
				$ob = new CIBlockElement();
				$arFields = array("ACTIVE"=>($_REQUEST['action']=="activate"?"Y":"N"));
				if(!$ob->Update($ID, $arFields, true))
					$lAdmin->AddGroupError(GetMessage("IBEL_A_UPDERR").$ob->LAST_ERROR, $ID);
			}
			else
			{
				$lAdmin->AddGroupError(GetMessage("IBEL_A_UPDERR3")." (ID:".$ID.")", $ID);
			}
			break;
		case "section":
		case "add_section":
			if (CIBlockElementRights::UserHasRightTo($IBLOCK_ID, $ID, "element_edit"))
			{
				$new_section = intval($_REQUEST["section_to_move"]);
				if($new_section >= 0)
				{
					if (CIBlockSectionRights::UserHasRightTo($IBLOCK_ID, $new_section, "section_element_bind"))
					{
						$obE = new CIBlockElement();

						$arSections = array($new_section);
						if($_REQUEST['action'] == "add_section")
						{
							$rsSections = $obE->GetElementGroups($ID, true, array('ID', 'IBLOCK_ELEMENT_ID'));
							while($ar = $rsSections->Fetch())
								$arSections[] = $ar["ID"];
						}

						$arFields = array(
							"IBLOCK_SECTION" => $arSections,
						);
						if ($_REQUEST["action"] == "section")
						{
							$arFields["IBLOCK_SECTION_ID"] = $new_section;
						}

						if(!$obE->Update($ID, $arFields))
							$lAdmin->AddGroupError(GetMessage("IBEL_A_SAVE_ERROR", array("#ID#" => $ID, "#ERROR_TEXT#" => $obE->LAST_ERROR)), $ID);
					}
					else
					{
						$lAdmin->AddGroupError(GetMessage("IBEL_A_UPDERR3")." (ID:".$ID.")", $ID);
					}
				}
			}
			else
			{
				$lAdmin->AddGroupError(GetMessage("IBEL_A_UPDERR3")." (ID:".$ID.")", $ID);
			}
			break;
		case "wf_status":
			if($bWorkFlow)
			{
				$new_status = intval($_REQUEST["wf_status_id"]);
				if(
					$new_status > 0
				)
				{
					if (CIBlockElement::WF_GetStatusPermission($new_status) > 0
						|| CIBlockElementRights::UserHasRightTo($IBLOCK_ID, $ID, "element_edit_any_wf_status"))
					{
						if($arRes["WF_STATUS_ID"] != $new_status)
						{
							$obE = new CIBlockElement();
							$res = $obE->Update($ID, array(
								"WF_STATUS_ID" => $new_status,
								"MODIFIED_BY" => $USER->GetID(),
							), true);
							if(!$res)
								$lAdmin->AddGroupError(GetMessage("IBEL_A_SAVE_ERROR", array("#ID#" => $ID, "#ERROR_TEXT#" => $obE->LAST_ERROR)), $ID);
						}
					}
					else
					{
						$lAdmin->AddGroupError(GetMessage("IBEL_A_UPDERR3")." (ID:".$ID.")", $ID);
					}
				}
			}
			break;
		case "lock":
			if($bWorkFlow && !CIBlockElementRights::UserHasRightTo($IBLOCK_ID, $ID, "element_edit"))
			{
				$lAdmin->AddGroupError(GetMessage("IBEL_A_UPDERR3")." (ID:".$ID.")", $ID);
				continue;
			}
			CIBlockElement::WF_Lock($ID);
			break;
		case "unlock":
			if ($bWorkFlow && !CIBlockElementRights::UserHasRightTo($IBLOCK_ID, $ID, "element_edit"))
			{
				$lAdmin->AddGroupError(GetMessage("IBEL_A_UPDERR3")." (ID:".$ID.")", $ID);
				continue;
			}
			if ($bBizproc)
				call_user_func(array(ENTITY, "UnlockDocument"), $ID, "");
			else
				CIBlockElement::WF_UnLock($ID);
			break;
		case 'clear_counter':
			if(CIBlockElementRights::UserHasRightTo($IBLOCK_ID, $ID, "element_edit"))
			{
				$ob = new CIBlockElement();
				$arFields = array('SHOW_COUNTER' => false, 'SHOW_COUNTER_START' => false);
				if (!$ob->Update($ID, $arFields, false, false))
					$lAdmin->AddGroupError(GetMessage("IBEL_A_UPDERR").$ob->LAST_ERROR, $ID);
			}
			else
			{
				$lAdmin->AddGroupError(GetMessage("IBEL_A_UPDERR3")." (ID:".$ID.")", $ID);
			}
			break;
		case 'change_price':
			if (CIBlockElementRights::UserHasRightTo($IBLOCK_ID, $ID, "element_edit"))
			{
				$elementsList['ELEMENTS'][] = $ID;
			}
			else
			{
				$lAdmin->AddGroupError(GetMessage("IBLIST_A_UPDERR_ACCESS", array("#ID#" => $ID)), $ID);
			}
			break;
		}
	}

	if (($_REQUEST['action']) === 'change_price' && !empty($_REQUEST['chprice_value_changing_price']))
	{
		$changePriceParams['PRICE_TYPE'] = $_REQUEST['chprice_id_price_type'];
		$changePriceParams['UNITS'] = $_REQUEST['chprice_units'];
		$changePriceParams['FORMAT_RESULTS'] = $_REQUEST['chprice_format_result'];
		$changePriceParams['INITIAL_PRICE_TYPE'] = $_REQUEST['chprice_initial_price_type'];
		$changePriceParams['RESULT_MASK'] = $_REQUEST['chprice_result_mask'];
		$changePriceParams['DIFFERENCE_VALUE'] = $_REQUEST['chprice_difference_value'];
		$changePriceParams['VALUE_CHANGING'] = $_REQUEST['chprice_value_changing_price'];

		$changePrice = new Catalog\Helpers\Admin\IblockPriceChanger( $changePriceParams, $IBLOCK_ID );
		$resultChanging = $changePrice->updatePrices( $elementsList );

		if (!$resultChanging->isSuccess())
		{
			foreach ($resultChanging->getErrors() as $error)
			{
				$lAdmin->AddGroupError(GetMessage($error->getMessage(), $error->getCode()));
			}
		}
		unset($resultChanging, $changePrice);

		$_SESSION['CHANGE_PRICE_PARAMS']['PRICE_TYPE'] = $changePriceParams['PRICE_TYPE'];
		$_SESSION['CHANGE_PRICE_PARAMS']['UNITS'] = $changePriceParams['UNITS'];
		$_SESSION['CHANGE_PRICE_PARAMS']['FORMAT_RESULTS'] = $changePriceParams['FORMAT_RESULTS'];
		$_SESSION['CHANGE_PRICE_PARAMS']['INITIAL_PRICE_TYPE'] = $changePriceParams['INITIAL_PRICE_TYPE'];
	}

	if ($bCatalog)
	{
		Catalog\Product\Sku::disableDeferredCalculation();
		Catalog\Product\Sku::calculate();
	}

	if ($lAdmin->hasGroupErrors())
	{
		$adminSidePanelHelper->sendJsonErrorResponse($lAdmin->getGroupErrors());
	}
	else
	{
		$adminSidePanelHelper->sendSuccessResponse();
	}

	if(isset($return_url) && strlen($return_url)>0)
		LocalRedirect($return_url);
}
CJSCore::Init(array('date'));

$arHeader = array();
if ($bCatalog)
{
	$arHeader[] = array(
		"id" => "CATALOG_TYPE",
		"content" => GetMessage("IBEL_CATALOG_TYPE"),
		"title" => GetMessage('IBEL_CATALOG_TYPE_TITLE'),
		"align" => "right",
		"sort" => "CATALOG_TYPE",
		"default" => true,
	);
}

$arHeader[] = array(
	"id" => "NAME",
	"content" => GetMessage("IBLOCK_FIELD_NAME"),
	"title" => "",
	"sort" => "name",
	"default" => true,
);
if ($arIBTYPE["SECTIONS"] == "Y")
{
	$arHeader[] = array(
		"id" => "SECTIONS",
		"content" => GetMessage("IBEL_A_SECTIONS"),
		"title" => "",
	);
}
$arHeader[] = array(
	"id" => "ACTIVE",
	"content" => GetMessage("IBLOCK_FIELD_ACTIVE"),
	"title" => "",
	"sort" => "active",
	"default" => true,
	"align" => "center",
);
$arHeader[] = array(
	"id" => "DATE_ACTIVE_FROM",
	"content" => GetMessage("IBEL_A_ACTFROM"),
	"title" => "",
	"sort" => "date_active_from",
	"default" => false,
);
$arHeader[] = array(
	"id" => "DATE_ACTIVE_TO",
	"content" => GetMessage("IBEL_A_ACTTO"),
	"title" => "",
	"sort" => "date_active_to",
	"default" => false,
);
$arHeader[] = array(
	"id" => "SORT",
	"content" => GetMessage("IBLOCK_FIELD_SORT"),
	"title" => "",
	"sort" => "sort",
	"default" => true,
	"align" => "right",
);
$arHeader[] = array(
	"id" => "TIMESTAMP_X",
	"content" => GetMessage("IBLOCK_FIELD_TIMESTAMP_X"),
	"title" => "",
	"sort" => "timestamp_x",
	"default" => true,
);
$arHeader[] = array(
	"id" => "USER_NAME",
	"content" => GetMessage("IBLOCK_FIELD_USER_NAME"),
	"title" => "",
	"sort" => "modified_by",
	"default" => false,
);
$arHeader[] = array(
	"id" => "DATE_CREATE",
	"content" => GetMessage("IBLOCK_EL_ADMIN_DCREATE"),
	"title" => "",
	"sort" => "created",
	"default" => false,
);
$arHeader[] = array(
	"id" => "CREATED_USER_NAME",
	"content" => GetMessage("IBLOCK_EL_ADMIN_WCREATE2"),
	"title" => "",
	"sort" => "created_by",
	"default" => false,
);
$arHeader[] = array(
	"id" => "CODE",
	"content" => GetMessage("IBEL_A_CODE"),
	"title" => "",
	"sort" => "code",
	"default" => false,
);
$arHeader[] = array(
	"id" => "EXTERNAL_ID",
	"content" => GetMessage("IBEL_A_EXTERNAL_ID"),
	"title" => "",
	"sort" => "external_id",
	"default" => false,
);
$arHeader[] = array(
	"id" => "TAGS",
	"content" => GetMessage("IBEL_A_TAGS"),
	"title" => "",
	"sort" => "tags",
	"default" => false,
);

if($bWorkFlow)
{
	$arHeader[] = array(
		"id" => "WF_STATUS_ID",
		"content" => GetMessage("IBLOCK_FIELD_STATUS"),
		"title" => "",
		"sort" => "status",
		"default" => true,
	);
	$arHeader[] = array(
		"id" => "WF_NEW",
		"content" => GetMessage("IBEL_A_EXTERNAL_WFNEW"),
		"title" => "",
		"sort" => "",
		"default" => false,
	);
	$arHeader[] = array(
		"id" => "LOCK_STATUS",
		"content" => GetMessage("IBEL_A_EXTERNAL_LOCK"),
		"title" => "",
		"default" => true,
	);
	$arHeader[] = array(
		"id" => "LOCKED_USER_NAME",
		"content" => GetMessage("IBEL_A_EXTERNAL_LOCK_BY"),
		"title" => "",
		"default" => false,
	);
	$arHeader[] = array(
		"id" => "WF_DATE_LOCK",
		"content" => GetMessage("IBEL_A_EXTERNAL_LOCK_WHEN"),
		"title" => "",
		"default" => false,
	);
	$arHeader[] = array(
		"id" => "WF_COMMENTS",
		"content" => GetMessage("IBEL_A_EXTERNAL_COM"),
		"title" => "",
		"default" => false,
	);
}

$arHeader[] = array(
	"id" => "SHOW_COUNTER",
	"content" => GetMessage("IBEL_A_EXTERNAL_SHOWS"),
	"title" => "",
	"sort" => "show_counter",
	"align" => "right",
	"default" => false,
);
$arHeader[] = array(
	"id" => "SHOW_COUNTER_START",
	"content" => GetMessage("IBEL_A_EXTERNAL_SHOW_F"),
	"title" => "",
	"sort" => "show_counter_start",
	"align" => "right",
	"default" => false,
);
$arHeader[] = array(
	"id" => "PREVIEW_PICTURE",
	"content" => GetMessage("IBEL_A_EXTERNAL_PREV_PIC"),
	"title" => "",
	"sort" => "has_preview_picture",
	"align" => "right",
	"default" => false,
	"editable" => false,
);
$arHeader[] = array(
	"id" => "PREVIEW_TEXT",
	"content" => GetMessage("IBEL_A_EXTERNAL_PREV_TEXT"),
	"title" => "",
	"default" => false,
);
$arHeader[] = array(
	"id" => "DETAIL_PICTURE",
	"content" => GetMessage("IBEL_A_EXTERNAL_DET_PIC"),
	"title" => "",
	"sort" => "has_detail_picture",
	"align" => "center",
	"default" => false,
	"editable" => false,
);
$arHeader[] = array(
	"id" => "DETAIL_TEXT",
	"content" => GetMessage("IBEL_A_EXTERNAL_DET_TEXT"),
	"title" => "",
	"default" => false,
);
$arHeader[] = array(
	"id" => "ID",
	"content" => "ID",
	"title" => "",
	"sort" => "id",
	"default" => true,
	"align" => "right",
);

foreach($arProps as $arFProps)
{
	$arHeader[] = array(
		"id" => "PROPERTY_".$arFProps['ID'],
		"content" => $arFProps['NAME'],
		"title" => "",
		"align" => ($arFProps["PROPERTY_TYPE"]=='N'? "right": "left"),
		"sort" => ($arFProps["MULTIPLE"]!='Y'? "PROPERTY_".$arFProps['ID']: ""),
		"default" => false,
		"editable" => ($arFProps["PROPERTY_TYPE"] == "F" ? false : true),
	);
}
unset($arFProps);

$arWFStatusAll = Array();
$arWFStatusPerm = Array();
if($bWorkFlow)
{
	$rsWF = CWorkflowStatus::GetDropDownList("Y");
	while($arWF = $rsWF->GetNext())
		$arWFStatusAll[$arWF["~REFERENCE_ID"]] = $arWF["~REFERENCE"];
	$rsWF = CWorkflowStatus::GetDropDownList("N", "desc");
	while($arWF = $rsWF->GetNext())
		$arWFStatusPerm[$arWF["~REFERENCE_ID"]] = $arWF["~REFERENCE"];
}

if($bCatalog)
{
	$arHeader[] = array(
		"id" => "CATALOG_AVAILABLE",
		"content" => GetMessage("IBEL_CATALOG_AVAILABLE"),
		"title" => GetMessage("IBEL_CATALOG_AVAILABLE_TITLE_EXT"),
		"align" => "center",
		"sort" => "CATALOG_AVAILABLE",
		"default" => true,
	);
	if ($arCatalog['CATALOG_TYPE'] != CCatalogSKU::TYPE_PRODUCT)
	{
		$arHeader[] = array(
			"id" => "CATALOG_QUANTITY",
			"content" => GetMessage("IBEL_CATALOG_QUANTITY_EXT"),
			"title" => "",
			"align" => "right",
			"sort" => "CATALOG_QUANTITY",
			"default" => false,
		);
		$arHeader[] = array(
			"id" => "CATALOG_QUANTITY_RESERVED",
			"content" => GetMessage("IBEL_CATALOG_QUANTITY_RESERVED"),
			"align" => "right",
		);
		$arHeader[] = array(
			"id" => "CATALOG_MEASURE_RATIO",
			"content" => GetMessage("IBEL_CATALOG_MEASURE_RATIO"),
			"title" => GetMessage('IBEL_CATALOG_MEASURE_RATIO_TITLE'),
			"align" => "right",
			"default" => false,
		);
		$arHeader[] = array(
			"id" => "CATALOG_MEASURE",
			"content" => GetMessage("IBEL_CATALOG_MEASURE"),
			"title" => GetMessage('IBEL_CATALOG_MEASURE_TITLE'),
			"align" => "right",
			"default" => false,
		);
		$arHeader[] = array(
			"id" => "CATALOG_QUANTITY_TRACE",
			"content" => GetMessage("IBEL_CATALOG_QUANTITY_TRACE"),
			"title" => "",
			"align" => "right",
			"default" => false,
		);
		$arHeader[] = array(
			"id" => "CATALOG_WEIGHT",
			"content" => GetMessage("IBEL_CATALOG_WEIGHT"),
			"title" => "",
			"align" => "right",
			"sort" => "CATALOG_WEIGHT",
			"default" => false,
		);
		$arHeader[] = array(
			"id" => "CATALOG_WIDTH",
			"content" => GetMessage("IBEL_CATALOG_WIDTH"),
			"title" => "",
			"align" => "right",
			"default" => false,
		);
		$arHeader[] = array(
			"id" => "CATALOG_LENGTH",
			"content" => GetMessage("IBEL_CATALOG_LENGTH"),
			"title" => "",
			"align" => "right",
			"default" => false,
		);
		$arHeader[] = array(
			"id" => "CATALOG_HEIGHT",
			"content" => GetMessage("IBEL_CATALOG_HEIGHT"),
			"title" => "",
			"align" => "right",
			"default" => false,
		);
		$arHeader[] = array(
			"id" => "CATALOG_VAT_INCLUDED",
			"content" => GetMessage("IBEL_CATALOG_VAT_INCLUDED"),
			"title" => "",
			"align" => "right",
			"default" => false,
		);
		if ($boolCatalogPurchasInfo)
		{
			$arHeader[] = array(
				"id" => "CATALOG_PURCHASING_PRICE",
				"content" => GetMessage("IBEL_CATALOG_PURCHASING_PRICE"),
				"title" => "",
				"align" => "right",
				"sort" => "CATALOG_PURCHASING_PRICE",
				"default" => false,
			);
		}
		if ($strUseStoreControl == "Y")
		{
			$arHeader[] = array(
				"id" => "CATALOG_BAR_CODE",
				"content" => GetMessage("IBEL_CATALOG_BAR_CODE"),
				"title" => "",
				"align" => "right",
				"default" => false,
			);
		}

		$arCatGroup = CCatalogGroup::GetListArray();
		if (!empty($arCatGroup))
		{
			foreach ($arCatGroup as $priceType)
			{
				$arHeader[] = array(
					"id" => "CATALOG_GROUP_".$priceType["ID"],
					"content" => htmlspecialcharsEx(!empty($priceType["NAME_LANG"]) ? $priceType["NAME_LANG"] : $priceType["NAME"]),
					"align" => "right",
					"sort" => "CATALOG_PRICE_".$priceType["ID"],
					"default" => false,
				);
			}
			unset($priceType);
		}

		$arCatExtra = array();
		$db_extras = CExtra::GetList(array("ID" => "ASC"));
		while ($extras = $db_extras->Fetch())
			$arCatExtra[$extras['ID']] = $extras;
		unset($extras, $db_extras);
	}
}

if ($bBizproc)
{
	$arWorkflowTemplates = CBPDocument::GetWorkflowTemplatesForDocumentType(array("iblock", "CIBlockDocument", "iblock_".$IBLOCK_ID));
	foreach ($arWorkflowTemplates as $arTemplate)
	{
		$arHeader[] = array(
			"id" => "WF_".$arTemplate["ID"],
			"content" => $arTemplate["NAME"],
		);
	}
	$arHeader[] = array(
		"id" => "BIZPROC",
		"content" => GetMessage("IBEL_A_BP_H"),
		"default" => false,
	);
	$arHeader[] = array(
		"id" => "BP_PUBLISHED",
		"content" => GetMessage("IBLOCK_FIELD_BP_PUBLISHED"),
		"sort" => "status",
		"default" => true,
	);
}

$lAdmin->AddHeaders($arHeader);
$lAdmin->AddVisibleHeaderColumn('ID');

$arSelectedFields = $lAdmin->GetVisibleHeaderColumns();
if ($arIBTYPE['SECTIONS'] != 'Y')
{
	$k = array_search("SECTIONS", $arSelectedFields);
	if ($k !== false)
		unset($arSelectedFields[$k]);
	unset($k);
}

$arSelectedProps = array();
$selectedPropertyIds = array();
$arSelect = array();
foreach($arProps as $i => $arProperty)
{
	$k = array_search("PROPERTY_".$arProperty['ID'], $arSelectedFields);
	if($k!==false)
	{
		$arSelectedProps[] = $arProperty;
		$selectedPropertyIds[] = $arProperty['ID'];
		if($arProperty["PROPERTY_TYPE"] == "L")
		{
			$arSelect[$arProperty['ID']] = array();
			$rs = CIBlockProperty::GetPropertyEnum($arProperty['ID']);
			while($ar = $rs->GetNext())
				$arSelect[$arProperty['ID']][$ar["ID"]] = $ar["VALUE"];
		}
		elseif($arProperty["PROPERTY_TYPE"] == "G")
		{
			$arSelect[$arProperty['ID']] = array();
			$rs = CIBlockSection::GetTreeList(array("IBLOCK_ID"=>$arProperty["LINK_IBLOCK_ID"]), array("ID", "NAME", "DEPTH_LEVEL"));
			while($ar = $rs->GetNext())
				$arSelect[$arProperty['ID']][$ar["ID"]] = str_repeat(" . ", $ar["DEPTH_LEVEL"]).$ar["NAME"];
		}
		unset($arSelectedFields[$k]);
	}
}

$arSelectedFields[] = "ID";
$arSelectedFields[] = "CREATED_BY";
$arSelectedFields[] = "LANG_DIR";
$arSelectedFields[] = "LID";
$arSelectedFields[] = "WF_PARENT_ELEMENT_ID";
$arSelectedFields[] = "ACTIVE";

if(in_array("LOCKED_USER_NAME", $arSelectedFields))
	$arSelectedFields[] = "WF_LOCKED_BY";
if(in_array("USER_NAME", $arSelectedFields))
	$arSelectedFields[] = "MODIFIED_BY";
if(in_array("PREVIEW_TEXT", $arSelectedFields))
	$arSelectedFields[] = "PREVIEW_TEXT_TYPE";
if(in_array("DETAIL_TEXT", $arSelectedFields))
	$arSelectedFields[] = "DETAIL_TEXT_TYPE";

$arSelectedFields[] = "LOCK_STATUS";
$arSelectedFields[] = "WF_NEW";
$arSelectedFields[] = "WF_STATUS_ID";
$arSelectedFields[] = "DETAIL_PAGE_URL";
$arSelectedFields[] = "SITE_ID";
$arSelectedFields[] = "CODE";
$arSelectedFields[] = "EXTERNAL_ID";

$measureList = array(0 => ' ');
if ($bCatalog)
{
	if (in_array("CATALOG_QUANTITY_TRACE", $arSelectedFields))
		$arSelectedFields[] = "CATALOG_QUANTITY_TRACE_ORIG";
	if (in_array('CATALOG_QUANTITY_RESERVED', $arSelectedFields) || in_array('CATALOG_MEASURE', $arSelectedFields))
	{
		if (!in_array('CATALOG_TYPE', $arSelectedFields))
			$arSelectedFields[] = 'CATALOG_TYPE';
	}
	if (in_array('CATALOG_TYPE', $arSelectedFields) && $boolCatalogSet)
		$arSelectedFields[] = 'CATALOG_BUNDLE';

	$boolPriceInc = false;
	if ($boolCatalogPurchasInfo)
	{
		if (in_array("CATALOG_PURCHASING_PRICE", $arSelectedFields))
		{
			$arSelectedFields[] = "CATALOG_PURCHASING_CURRENCY";
			$boolPriceInc = true;
		}
	}
	if (!empty($arCatGroup) && is_array($arCatGroup))
	{
		foreach($arCatGroup as &$CatalogGroups)
		{
			if (in_array("CATALOG_GROUP_".$CatalogGroups["ID"], $arSelectedFields))
			{
				$arFilter["CATALOG_SHOP_QUANTITY_".$CatalogGroups["ID"]] = 1;
				$boolPriceInc = true;
			}
		}
		unset($CatalogGroups);
	}
	if ($boolPriceInc)
	{
		$bCurrency = Loader::includeModule('currency');
		if ($bCurrency)
			$arCurrencyList = array_keys(Currency\CurrencyManager::getCurrencyList());
	}
	unset($boolPriceInc);

	if (in_array('CATALOG_MEASURE', $arSelectedFields))
	{
		$measureIterator = CCatalogMeasure::getList(array(), array(), false, false, array('ID', 'MEASURE_TITLE', 'SYMBOL_RUS'));
		while($measure = $measureIterator->Fetch())
			$measureList[$measure['ID']] = ($measure['SYMBOL_RUS'] != '' ? $measure['SYMBOL_RUS'] : $measure['MEASURE_TITLE']);
		unset($measure, $measureIterator);
	}
}

$arSelectedFieldsMap = array();
foreach($arSelectedFields as $index => $field)
{
	$arSelectedFieldsMap[$field] = true;
	if ($field == 'SECTIONS')
		unset($arSelectedFields[$index]);
}
unset($index, $field);

$rsData = CIBlockElement::GetList(
	$arOrder,
	$arFilter,
	false,
	false,
	$arSelectedFields
);
$rsData->SetTableID($sTableID);

$rsData = new CAdminUiResult($rsData, $sTableID);
$rsData->NavStart();
$listScriptName = CIBlock::GetAdminSectionListScriptName($IBLOCK_ID);
$lAdmin->SetNavigationParams($rsData, array("BASE_LINK" => $selfFolderUrl.CIBlock::GetAdminElementListScriptName(
	$IBLOCK_ID, array("skip_public" => true))));
$arRows = array();

$bSearch = Loader::includeModule('search');

function GetElementName($ID)
{
	$ID = (int)$ID;
	if ($ID <= 0)
		return '';
	static $cache = array();
	if(!isset($cache[$ID]))
	{
		$rsElement = CIBlockElement::GetList(array(), array("ID"=>$ID, "SHOW_HISTORY"=>"Y"), false, false, array("ID","IBLOCK_ID","NAME"));
		$cache[$ID] = $rsElement->GetNext();
	}
	return $cache[$ID];
}
function GetIBlockTypeID($IBLOCK_ID)
{
	$IBLOCK_ID = (int)$IBLOCK_ID;
	if ($IBLOCK_ID <= 0)
		return '';
	static $cache = array();
	if(!isset($cache[$IBLOCK_ID]))
	{
		$rsIBlock = CIBlock::GetByID($IBLOCK_ID);
		if(!($cache[$IBLOCK_ID] = $rsIBlock->GetNext()))
			$cache[$IBLOCK_ID] = array("IBLOCK_TYPE_ID"=>"");
	}
	return $cache[$IBLOCK_ID]["IBLOCK_TYPE_ID"];
}

while($arRes = $rsData->NavNext(false))
{
	$arRes_orig = $arRes;
	// in workflow mode show latest changes
	if($bWorkFlow)
	{
		$LAST_ID = CIBlockElement::WF_GetLast($arRes['ID']);
		if($LAST_ID!=$arRes['ID'])
		{
			$rsData2 = CIBlockElement::GetList(
					array(),
					array(
						"ID"=>$LAST_ID,
						"SHOW_HISTORY"=>"Y"
						),
					false,
					array("nTopCount"=>1),
					$arSelectedFields
				);
			if (isset($arCatGroup))
			{
				$arRes_tmp = array();
				foreach($arRes as $vv => $vval)
				{
					if(substr($vv, 0, 8) == "CATALOG_")
						$arRes_tmp[$vv] = $arRes[$vv];
				}
			}

			$arRes = $rsData2->NavNext(true, "f_");
			$arRes["WF_NEW"] = $arRes_orig["WF_NEW"];
			if (isset($arCatGroup))
				$arRes = array_merge($arRes, $arRes_tmp);

			$arRes["ID"] = $arRes_orig["ID"];
		}
		$lockStatus = $arRes_orig['LOCK_STATUS'];
	}
	elseif($bBizproc)
	{
		$lockStatus = CIBlockDocument::IsDocumentLocked($arRes["ID"], "") ? "red" : "green";
	}
	else
	{
		$lockStatus = "";
	}
	if ($bCatalog)
	{
		if (isset($arSelectedFieldsMap['CATALOG_QUANTITY_TRACE']))
		{
			$arRes['CATALOG_QUANTITY_TRACE'] = $arRes['CATALOG_QUANTITY_TRACE_ORIG'];
		}
		if (isset($arSelectedFieldsMap['CATALOG_TYPE']))
		{
			$arRes['CATALOG_TYPE'] = (int)$arRes['CATALOG_TYPE'];

			if (
				$arRes['CATALOG_TYPE'] == \Bitrix\Catalog\ProductTable::TYPE_SKU
				|| $arRes['CATALOG_TYPE'] == \Bitrix\Catalog\ProductTable::TYPE_SET
			)
			{
				$arRes['CATALOG_QUANTITY_RESERVED'] = '';
			}
			if (
				$arRes['CATALOG_TYPE'] == \Bitrix\Catalog\ProductTable::TYPE_SKU
				&& !$showCatalogWithOffers
			)
			{
				$arRes['CATALOG_QUANTITY'] = '';
				$arRes['CATALOG_QUANTITY_TRACE'] = '';
				$arRes['CATALOG_QUANTITY_TRACE_ORIG'] = '';
				$arRes['CATALOG_CAN_BUY_ZERO'] = '';
				$arRes['CATALOG_CAN_BUY_ZERO_ORIG'] = '';
				$arRes['CATALOG_NEGATIVE_AMOUNT_TRACE'] = '';
				$arRes['CATALOG_NEGATIVE_AMOUNT_TRACE_ORIG'] = '';
				$arRes['CATALOG_PURCHASING_PRICE'] = '';
				$arRes['CATALOG_PURCHASING_CURRENCY'] = '';
			}
		}
		if (isset($arSelectedFieldsMap['CATALOG_MEASURE']))
		{
			$arRes['CATALOG_MEASURE'] = (int)$arRes['CATALOG_MEASURE'];
			if ($arRes['CATALOG_MEASURE'] < 0)
				$arRes['CATALOG_MEASURE'] = 0;
		}
	}

	$arRes['lockStatus'] = $lockStatus;
	$arRes["orig"] = $arRes_orig;
	$arRes["edit_url"] = $selfFolderUrl.CIBlock::GetAdminElementEditLink($IBLOCK_ID, $arRes_orig['ID'], array(
		"find_section_section" => $find_section_section,
		"WF" => "Y",
		"replace_script_name" => true
	));
	$arRows[$arRes["ID"]] = $row = $lAdmin->AddRow($arRes["ID"], $arRes, $arRes["edit_url"], GetMessage("IBEL_A_EDIT"));

	$boolEditPrice = false;
	$boolEditPrice = CIBlockElementRights::UserHasRightTo($IBLOCK_ID, $arRes["ID"], "element_edit_price");

	$row->AddViewField("ID", '<a href="'.$arRes["edit_url"].'" title="'.GetMessage("IBEL_A_EDIT_TITLE").'">'.$arRes["ID"].'</a>');

	if(isset($arRes["LOCKED_USER_NAME"]) && $arRes["LOCKED_USER_NAME"])
		$row->AddViewField("LOCKED_USER_NAME", '<a href="user_edit.php?lang='.LANGUAGE_ID.'&ID='.$arRes["WF_LOCKED_BY"].'" title="'.GetMessage("IBEL_A_USERINFO").'">'.$arRes["LOCKED_USER_NAME"].'</a>');
	if(isset($arRes["USER_NAME"]) && $arRes["USER_NAME"])
		$row->AddViewField("USER_NAME", '<a href="user_edit.php?lang='.LANGUAGE_ID.'&ID='.$arRes["MODIFIED_BY"].'" title="'.GetMessage("IBEL_A_USERINFO").'">'.$arRes["USER_NAME"].'</a>');
	if(isset($arRes["CREATED_USER_NAME"]) && $arRes["CREATED_USER_NAME"])
		$row->AddViewField("CREATED_USER_NAME", '<a href="user_edit.php?lang='.LANGUAGE_ID.'&ID='.$arRes["CREATED_BY"].'" title="'.GetMessage("IBEL_A_USERINFO").'">'.$arRes["CREATED_USER_NAME"].'</a>');

	if($bWorkFlow || $bBizproc)
	{
		$lamp = '<span class="adm-lamp adm-lamp-in-list adm-lamp-'.$lockStatus.'"></span>';
		if($lockStatus=='red' && $arRes_orig['LOCKED_USER_NAME']!='')
			$row->AddViewField("LOCK_STATUS", $lamp.$arRes_orig['LOCKED_USER_NAME']);
		else
			$row->AddViewField("LOCK_STATUS", $lamp);
	}

	if($bBizproc)
		$row->AddCheckField("BP_PUBLISHED", false);

	$row->arRes['props'] = array();
	$arProperties = array();
	if (!empty($arSelectedProps))
	{
		$rsProperties = CIBlockElement::GetProperty($IBLOCK_ID, $arRes['ID'], 'id', 'asc', array('ID' => $selectedPropertyIds));
		while($ar = $rsProperties->GetNext())
		{
			if(!array_key_exists($ar["ID"], $arProperties))
				$arProperties[$ar["ID"]] = array();
			$arProperties[$ar["ID"]][$ar["PROPERTY_VALUE_ID"]] = $ar;
		}
		unset($ar);
		unset($rsProperties);
	}

	foreach($arSelectedProps as $aProp)
	{
		$arViewHTML = array();
		$arEditHTML = array();
		$arUserType = (strlen($aProp["USER_TYPE"])>0 ? CIBlockProperty::GetUserType($aProp["USER_TYPE"]) : array());

		$last_property_id = false;
		foreach($arProperties[$aProp["ID"]] as $prop_id => $prop)
		{
			$prop['PROPERTY_VALUE_ID'] = intval($prop['PROPERTY_VALUE_ID']);
			$VALUE_NAME = 'FIELDS['.$arRes["ID"].'][PROPERTY_'.$prop['ID'].']['.$prop['PROPERTY_VALUE_ID'].'][VALUE]';
			$DESCR_NAME = 'FIELDS['.$arRes["ID"].'][PROPERTY_'.$prop['ID'].']['.$prop['PROPERTY_VALUE_ID'].'][DESCRIPTION]';
			//View part
			if(array_key_exists("GetAdminListViewHTML", $arUserType))
			{
				$arViewHTML[] = call_user_func_array($arUserType["GetAdminListViewHTML"],
					array(
						$prop,
						array(
							"VALUE" => $prop["~VALUE"],
							"DESCRIPTION" => $prop["~DESCRIPTION"]
						),
						array(
							"VALUE" => $VALUE_NAME,
							"DESCRIPTION" => $DESCR_NAME,
							"MODE"=>"iblock_element_admin",
							"FORM_NAME"=>"form_".$sTableID,
						),
					));
			}
			elseif($prop['PROPERTY_TYPE']=='N')
				$arViewHTML[] = $bExcel && isset($_COOKIE[$dsc_cookie_name])? number_format($prop["VALUE"], 4, chr($_COOKIE[$dsc_cookie_name]), ''): $prop["VALUE"];
			elseif($prop['PROPERTY_TYPE']=='S')
				$arViewHTML[] = $prop["VALUE"];
			elseif($prop['PROPERTY_TYPE']=='L')
				$arViewHTML[] = $prop["VALUE_ENUM"];
			elseif($prop['PROPERTY_TYPE']=='F')
			{
				if ($bExcel)
				{
					$arFile = CFile::GetFileArray($prop["VALUE"]);
					if (is_array($arFile))
						$arViewHTML[] = CHTTP::URN2URI($arFile["SRC"]);
					else
						$arViewHTML[] = "";
				}
				else
				{
					$arViewHTML[] = CFileInput::Show('NO_FIELDS['.$prop['PROPERTY_VALUE_ID'].']', $prop["VALUE"], array(
						"IMAGE" => "Y",
						"PATH" => "Y",
						"FILE_SIZE" => "Y",
						"DIMENSIONS" => "Y",
						"IMAGE_POPUP" => "Y",
						"MAX_SIZE" => $maxImageSize,
						"MIN_SIZE" => $minImageSize,
						), array(
							'upload' => false,
							'medialib' => false,
							'file_dialog' => false,
							'cloud' => false,
							'del' => false,
							'description' => false,
						)
					);
				}
			}
			elseif($prop['PROPERTY_TYPE']=='G')
			{
				if(intval($prop["VALUE"])>0)
				{
					$rsSection = CIBlockSection::GetList(
						array(),
						array("ID" => $prop["VALUE"]),
						false,
						array('ID', 'NAME', 'IBLOCK_ID')
					);
					if($arSection = $rsSection->GetNext())
					{
						$arViewHTML[] = $arSection['NAME'].
						' [<a href="'.
						htmlspecialcharsbx($selfFolderUrl.CIBlock::GetAdminSectionEditLink(
							$arSection['IBLOCK_ID'], $arSection['ID'], array("replace_script_name" => true))).
							'" title="'.GetMessage("IBEL_A_SEC_EDIT").'">'.$arSection['ID'].'</a>]';
					}
				}
			}
			elseif($prop['PROPERTY_TYPE']=='E')
			{
				if($t = GetElementName($prop["VALUE"]))
				{
					$arViewHTML[] = $t['NAME'].
					' [<a href="'.htmlspecialcharsbx($selfFolderUrl.CIBlock::GetAdminElementEditLink($t['IBLOCK_ID'], $t['ID'], array(
						"find_section_section" => $find_section_section, 'WF' => 'Y', "replace_script_name" => true
					))).'" title="'.GetMessage("IBEL_A_EL_EDIT").'">'.$t['ID'].'</a>]';
				}
			}
			//Edit Part
			$bUserMultiple = $prop["MULTIPLE"] == "Y" &&  array_key_exists("GetPropertyFieldHtmlMulty", $arUserType);
			if($bUserMultiple)
			{
				if($last_property_id != $prop["ID"])
				{
					$VALUE_NAME = 'FIELDS['.$arRes["ID"].'][PROPERTY_'.$prop['ID'].']';
					$arEditHTML[] = call_user_func_array($arUserType["GetPropertyFieldHtmlMulty"], array(
						$prop,
						$arProperties[$prop["ID"]],
						array(
							"VALUE" => $VALUE_NAME,
							"DESCRIPTION" => $VALUE_NAME,
							"MODE"=>"iblock_element_admin",
							"FORM_NAME"=>"form_".$sTableID,
						)
					));
				}
			}
			elseif(array_key_exists("GetPropertyFieldHtml", $arUserType))
			{
				$arEditHTML[] = call_user_func_array($arUserType["GetPropertyFieldHtml"],
					array(
						$prop,
						array(
							"VALUE" => $prop["~VALUE"],
							"DESCRIPTION" => $prop["~DESCRIPTION"],
						),
						array(
							"VALUE" => $VALUE_NAME,
							"DESCRIPTION" => $DESCR_NAME,
							"MODE"=>"iblock_element_admin",
							"FORM_NAME"=>"form_".$sTableID,
						),
					));
			}
			elseif($prop['PROPERTY_TYPE']=='N' || $prop['PROPERTY_TYPE']=='S')
			{
				if($prop["ROW_COUNT"] > 1)
					$html = '<textarea name="'.$VALUE_NAME.'" cols="'.$prop["COL_COUNT"].'" rows="'.$prop["ROW_COUNT"].'">'.$prop["VALUE"].'</textarea>';
				else
					$html = '<input type="text" name="'.$VALUE_NAME.'" value="'.$prop["VALUE"].'" size="'.$prop["COL_COUNT"].'">';
				$arEditHTML[] = $html;
			}
			elseif($prop['PROPERTY_TYPE']=='L' && ($last_property_id!=$prop["ID"]))
			{
				$VALUE_NAME = 'FIELDS['.$arRes["ID"].'][PROPERTY_'.$prop['ID'].'][]';
				$arValues = array();
				foreach($arProperties[$prop["ID"]] as $g_prop)
				{
					$g_prop = intval($g_prop["VALUE"]);
					if($g_prop > 0)
						$arValues[$g_prop] = $g_prop;
				}
				if($prop['LIST_TYPE']=='C')
				{
					if($prop['MULTIPLE'] == "Y" || count($arSelect[$prop['ID']]) == 1)
					{
						$html = '<input type="hidden" name="'.$VALUE_NAME.'" value="">';
						foreach($arSelect[$prop['ID']] as $value => $display)
						{
							$html .= '<input type="checkbox" name="'.$VALUE_NAME.'" id="id'.$uniq_id.'" value="'.$value.'"';
							if(array_key_exists($value, $arValues))
								$html .= ' checked';
							$html .= '>&nbsp;<label for="id'.$uniq_id.'">'.$display.'</label><br>';
							$uniq_id++;
						}
					}
					else
					{
						$html = '<input type="radio" name="'.$VALUE_NAME.'" id="id'.$uniq_id.'" value=""';
						if(count($arValues) < 1)
							$html .= ' checked';
						$html .= '>&nbsp;<label for="id'.$uniq_id.'">'.GetMessage("IBLOCK_ELEMENT_EDIT_NOT_SET").'</label><br>';
						$uniq_id++;
						foreach($arSelect[$prop['ID']] as $value => $display)
						{
							$html .= '<input type="radio" name="'.$VALUE_NAME.'" id="id'.$uniq_id.'" value="'.$value.'"';
							if(array_key_exists($value, $arValues))
								$html .= ' checked';
							$html .= '>&nbsp;<label for="id'.$uniq_id.'">'.$display.'</label><br>';
							$uniq_id++;
						}
					}
				}
				else
				{
					$html = '<select name="'.$VALUE_NAME.'" size="'.$prop["MULTIPLE_CNT"].'" '.($prop["MULTIPLE"]=="Y"?"multiple":"").'>';
					$html .= '<option value=""'.(count($arValues) < 1? ' selected': '').'>'.GetMessage("IBLOCK_ELEMENT_EDIT_NOT_SET").'</option>';
					foreach($arSelect[$prop['ID']] as $value => $display)
					{
						$html .= '<option value="'.$value.'"';
						if(array_key_exists($value, $arValues))
							$html .= ' selected';
						$html .= '>'.$display.'</option>'."\n";
					}
					$html .= "</select>\n";
				}
				$arEditHTML[] = $html;
			}
			elseif($prop['PROPERTY_TYPE']=='F' && ($last_property_id != $prop["ID"]))
			{
				if($prop['MULTIPLE'] == "Y")
				{
					$inputName = array();
					foreach($arProperties[$prop["ID"]] as $g_prop)
					{
						$inputName['FIELDS['.$arRes["ID"].'][PROPERTY_'.$prop['ID'].']['.$g_prop['PROPERTY_VALUE_ID'].'][VALUE]'] = $g_prop["VALUE"];
					}
					if (class_exists('\Bitrix\Main\UI\FileInput', true))
					{
						$arEditHTML[] = \Bitrix\Main\UI\FileInput::createInstance(array(
								"name" => 'FIELDS['.$arRes["ID"].'][PROPERTY_'.$prop['ID'].'][n#IND#]',
								"description" => $prop["WITH_DESCRIPTION"]=="Y",
								"upload" => true,
								"medialib" => false,
								"fileDialog" => false,
								"cloud" => false,
								"delete" => true,
							))->show($inputName);
					}
					else
					{
						$arEditHTML[] = CFileInput::ShowMultiple($inputName, 'FIELDS['.$arRes["ID"].'][PROPERTY_'.$prop['ID'].'][n#IND#]', array(
							"IMAGE" => "Y",
							"PATH" => "Y",
							"FILE_SIZE" => "Y",
							"DIMENSIONS" => "Y",
							"IMAGE_POPUP" => "Y",
							"MAX_SIZE" => $maxImageSize,
							"MIN_SIZE" => $minImageSize,
							), false, array(
								'upload' => true,
								'medialib' => false,
								'file_dialog' => false,
								'cloud' => false,
								'del' => true,
								'description' => $prop["WITH_DESCRIPTION"]=="Y",
							)
						);
					}
				}
				else
				{
					$arEditHTML[] = CFileInput::Show($VALUE_NAME, $prop["VALUE"], array(
						"IMAGE" => "Y",
						"PATH" => "Y",
						"FILE_SIZE" => "Y",
						"DIMENSIONS" => "Y",
						"IMAGE_POPUP" => "Y",
						"MAX_SIZE" => $maxImageSize,
						"MIN_SIZE" => $minImageSize,
						), array(
							'upload' => true,
							'medialib' => false,
							'file_dialog' => false,
							'cloud' => false,
							'del' => true,
							'description' => $prop["WITH_DESCRIPTION"]=="Y",
						)
					);
				}
			}
			elseif(($prop['PROPERTY_TYPE']=='G') && ($last_property_id!=$prop["ID"]))
			{
				$VALUE_NAME = 'FIELDS['.$arRes["ID"].'][PROPERTY_'.$prop['ID'].'][]';
				$arValues = array();
				foreach($arProperties[$prop["ID"]] as $g_prop)
				{
					$g_prop = intval($g_prop["VALUE"]);
					if($g_prop > 0)
						$arValues[$g_prop] = $g_prop;
				}
				$html = '<select name="'.$VALUE_NAME.'" size="'.$prop["MULTIPLE_CNT"].'" '.($prop["MULTIPLE"]=="Y"?"multiple":"").'>';
				$html .= '<option value=""'.(count($arValues) < 1? ' selected': '').'>'.GetMessage("IBLOCK_ELEMENT_EDIT_NOT_SET").'</option>';
				foreach($arSelect[$prop['ID']] as $value => $display)
				{
					$html .= '<option value="'.$value.'"';
					if(array_key_exists($value, $arValues))
						$html .= ' selected';
					$html .= '>'.$display.'</option>'."\n";
				}
				$html .= "</select>\n";
				$arEditHTML[] = $html;
			}
			elseif($prop['PROPERTY_TYPE']=='E')
			{
				$VALUE_NAME = 'FIELDS['.$arRes["ID"].'][PROPERTY_'.$prop['ID'].']['.$prop['PROPERTY_VALUE_ID'].']';
				$fixIBlock = $prop["LINK_IBLOCK_ID"] > 0;
				$windowTableId = 'iblockprop-'.Iblock\PropertyTable::TYPE_ELEMENT.'-'.$prop['ID'].'-'.$prop['LINK_IBLOCK_ID'];
				if($t = GetElementName($prop["VALUE"]))
				{
					$arEditHTML[] = '<input type="text" name="'.$VALUE_NAME.'" id="'.$VALUE_NAME.'" value="'.$prop["VALUE"].'" size="5">'.
					'<input type="button" value="..." onClick="jsUtils.OpenWindow(\''.$selfFolderUrl.'iblock_element_search.php?lang='.LANGUAGE_ID.'&amp;IBLOCK_ID='.$prop["LINK_IBLOCK_ID"].'&amp;n='.urlencode($VALUE_NAME).($fixIBlock ? '&amp;iblockfix=y' : '').'&amp;tableId='.$windowTableId.'\', 900, 700);">'.
					'&nbsp;<span id="sp_'.$VALUE_NAME.'" >'.$t['NAME'].'</span>';
				}
				else
				{
					$arEditHTML[] = '<input type="text" name="'.$VALUE_NAME.'" id="'.$VALUE_NAME.'" value="" size="5">'.
					'<input type="button" value="..." onClick="jsUtils.OpenWindow(\''.$selfFolderUrl.'iblock_element_search.php?lang='.LANGUAGE_ID.'&amp;IBLOCK_ID='.$prop["LINK_IBLOCK_ID"].'&amp;n='.urlencode($VALUE_NAME).($fixIBlock ? '&amp;iblockfix=y' : '').'&amp;tableId='.$windowTableId.'\', 900, 700);">'.
					'&nbsp;<span id="sp_'.$VALUE_NAME.'" ></span>';
				}
				unset($windowTableId);
				unset($fixIBlock);
			}
			$last_property_id = $prop['ID'];
		}
		$table_id = md5($arRes["ID"].':'.$aProp['ID']);
		if($aProp["MULTIPLE"] == "Y")
		{
			$VALUE_NAME = 'FIELDS['.$arRes["ID"].'][PROPERTY_'.$prop['ID'].'][n0][VALUE]';
			$DESCR_NAME = 'FIELDS['.$arRes["ID"].'][PROPERTY_'.$prop['ID'].'][n0][DESCRIPTION]';
			if(array_key_exists("GetPropertyFieldHtmlMulty", $arUserType))
			{
			}
			elseif(array_key_exists("GetPropertyFieldHtml", $arUserType))
			{
				$arEditHTML[] = call_user_func_array($arUserType["GetPropertyFieldHtml"],
					array(
						$prop,
						array(
							"VALUE" => "",
							"DESCRIPTION" => "",
						),
						array(
							"VALUE" => $VALUE_NAME,
							"DESCRIPTION" => $DESCR_NAME,
							"MODE"=>"iblock_element_admin",
							"FORM_NAME"=>"form_".$sTableID,
						),
					));
			}
			elseif($prop['PROPERTY_TYPE']=='N' || $prop['PROPERTY_TYPE']=='S')
			{
				if($prop["ROW_COUNT"] > 1)
					$html = '<textarea name="'.$VALUE_NAME.'" cols="'.$prop["COL_COUNT"].'" rows="'.$prop["ROW_COUNT"].'"></textarea>';
				else
					$html = '<input type="text" name="'.$VALUE_NAME.'" value="" size="'.$prop["COL_COUNT"].'">';
				$arEditHTML[] = $html;
			}
			elseif($prop['PROPERTY_TYPE']=='F')
			{
			}
			elseif($prop['PROPERTY_TYPE']=='E')
			{
				$VALUE_NAME = 'FIELDS['.$arRes["ID"].'][PROPERTY_'.$prop['ID'].'][n0]';
				$fixIBlock = $prop["LINK_IBLOCK_ID"] > 0;
				$windowTableId = 'iblockprop-'.Iblock\PropertyTable::TYPE_ELEMENT.'-'.$prop['ID'].'-'.$prop['LINK_IBLOCK_ID'];
				$arEditHTML[] = '<input type="text" name="'.$VALUE_NAME.'" id="'.$VALUE_NAME.'" value="" size="5">'.
					'<input type="button" value="..." onClick="jsUtils.OpenWindow(\''.$selfFolderUrl.'iblock_element_search.php?lang='.LANGUAGE_ID.'&amp;IBLOCK_ID='.$prop["LINK_IBLOCK_ID"].'&amp;n='.urlencode($VALUE_NAME).($fixIBlock ? '&amp;iblockfix=y' : '').'&amp;tableId='.$windowTableId.'\', 900, 700);">'.
					'&nbsp;<span id="sp_'.$VALUE_NAME.'" ></span>';
				unset($windowTableId);
				unset($fixIBlock);
			}

			if(
				$prop["PROPERTY_TYPE"] !== "G"
				&& $prop["PROPERTY_TYPE"] !== "L"
				&& $prop["PROPERTY_TYPE"] !== "F"
				&& !$bUserMultiple
			)
				$arEditHTML[] = '<input type="button" value="'.GetMessage("IBLOCK_ELEMENT_EDIT_PROP_ADD").'" onClick="addNewRow(\'tb'.$table_id.'\')">';
		}
		if(!empty($arViewHTML))
		{
			if($prop["PROPERTY_TYPE"] == "F")
				$row->AddViewField("PROPERTY_".$aProp['ID'], implode("", $arViewHTML));
			else
				$row->AddViewField("PROPERTY_".$aProp['ID'], implode(" / ", $arViewHTML));
		}

		if(count($arEditHTML) > 0)
			$row->arRes['props']["PROPERTY_".$aProp['ID']] = array("table_id"=>$table_id, "html"=>$arEditHTML);
	}

	if ($bCatalog)
	{
		if (isset($arCatGroup) && !empty($arCatGroup))
		{
			$row->arRes['price'] = array();
			foreach($arCatGroup as &$CatGroup)
			{
				if (isset($arSelectedFieldsMap["CATALOG_GROUP_".$CatGroup["ID"]]))
				{
					$price = "";
					$sHTML = "";
					$selectCur = "";
					$extraId = (isset($arRes['CATALOG_EXTRA_ID_'.$CatGroup['ID']]) ? (int)$arRes['CATALOG_EXTRA_ID_'.$CatGroup['ID']] : 0);
					if (!isset($arCatExtra[$extraId]))
						$extraId = 0;
					if ($bCurrency)
					{
						$price = htmlspecialcharsEx(CCurrencyLang::CurrencyFormat(
							$arRes["CATALOG_PRICE_".$CatGroup["ID"]],
							$arRes["CATALOG_CURRENCY_".$CatGroup["ID"]],
							true
						));
						if ($extraId > 0)
						{
							$price .= ' <span title="'.
								htmlspecialcharsbx(GetMessage(
									'IBEL_CATALOG_EXTRA_DESCRIPTION',
									array('#VALUE#' => $arCatExtra[$extraId]['NAME'])
								)).
								'">(+'.$arCatExtra[$extraId]['PERCENTAGE'].'%)</span>';
						}
						if ($boolCatalogPrice && $boolEditPrice)
						{
							$selectCur = '<select name="CATALOG_CURRENCY['.$arRes["ID"].']['.$CatGroup["ID"].']" id="CATALOG_CURRENCY['.$arRes["ID"].']['.$CatGroup["ID"].']"';
							if ($CatGroup["BASE"]=="Y")
								$selectCur .= ' onchange="top.ChangeBaseCurrency('.$arRes["ID"].')"';
							elseif ($extraId > 0)
								$selectCur .= ' disabled="disabled" readonly="readonly"';
							$selectCur .= '>';
							foreach ($arCurrencyList as &$currencyCode)
							{
								$selectCur .= '<option value="'.$currencyCode.'"';
								if ($currencyCode == $arRes["CATALOG_CURRENCY_".$CatGroup["ID"]])
									$selectCur .= ' selected';
								$selectCur .= '>'.$currencyCode.'</option>';
							}
							unset($currencyCode);
							$selectCur .= '</select>';
						}
					}
					else
					{
						$price = htmlspecialcharsEx($arRes["CATALOG_PRICE_".$CatGroup["ID"]]." ".$arRes["CATALOG_CURRENCY_".$CatGroup["ID"]]);
					}

					$row->AddViewField("CATALOG_GROUP_".$CatGroup["ID"], $price);

					if ($boolCatalogPrice && $boolEditPrice)
					{
						$sHTML = '<input type="text" size="9" id="CATALOG_PRICE['.$arRes["ID"].']['.$CatGroup["ID"].']" name="CATALOG_PRICE['.$arRes["ID"].']['.$CatGroup["ID"].']" value="'.$arRes["CATALOG_PRICE_".$CatGroup["ID"]].'"';
						if ($CatGroup["BASE"]=="Y")
							$sHTML .= ' onchange="top.ChangeBasePrice('.$arRes["ID"].')"';
						elseif ($extraId > 0)
							$sHTML .= ' disabled readonly';
						$sHTML .= '> '.$selectCur;
						if ($extraId > 0)
							$sHTML .= '<input type="hidden" id="CATALOG_EXTRA['.$arRes["ID"].']['.$CatGroup["ID"].']" name="CATALOG_EXTRA['.$arRes["ID"].']['.$CatGroup["ID"].']" value="'.$arRes["CATALOG_EXTRA_ID_".$CatGroup["ID"]].'">';

						$sHTML .= '<input type="hidden" name="CATALOG_old_PRICE['.$arRes["ID"].']['.$CatGroup["ID"].']" value="'.$arRes["CATALOG_PRICE_".$CatGroup["ID"]].'">';
						$sHTML .= '<input type="hidden" name="CATALOG_old_CURRENCY['.$arRes["ID"].']['.$CatGroup["ID"].']" value="'.$arRes["CATALOG_CURRENCY_".$CatGroup["ID"]].'">';
						$sHTML .= '<input type="hidden" name="CATALOG_PRICE_ID['.$arRes["ID"].']['.$CatGroup["ID"].']" value="'.$arRes["CATALOG_PRICE_ID_".$CatGroup["ID"]].'">';
						$sHTML .= '<input type="hidden" name="CATALOG_QUANTITY_FROM['.$arRes["ID"].']['.$CatGroup["ID"].']" value="'.$arRes["CATALOG_QUANTITY_FROM_".$CatGroup["ID"]].'">';
						$sHTML .= '<input type="hidden" name="CATALOG_QUANTITY_TO['.$arRes["ID"].']['.$CatGroup["ID"].']" value="'.$arRes["CATALOG_QUANTITY_TO_".$CatGroup["ID"]].'">';

						$row->arRes['price']["CATALOG_GROUP_".$CatGroup["ID"]] = $sHTML;
					}
					unset($extraId);
				}
			}
			unset($CatGroup);
		}
		if (isset($arSelectedFieldsMap['CATALOG_MEASURE_RATIO']))
		{
			$row->arRes['CATALOG_MEASURE_RATIO'] = ' ';
		}
	}

	if ($bBizproc)
	{
		$arDocumentStates = CBPDocument::GetDocumentStates(
			array("iblock", "CIBlockDocument", "iblock_".$IBLOCK_ID),
			array("iblock", "CIBlockDocument", $arRes["ID"])
		);

		$arRes["CURRENT_USER_GROUPS"] = $USER->GetUserGroupArray();
		if ($arRes["CREATED_BY"] == $USER->GetID())
			$arRes["CURRENT_USER_GROUPS"][] = "Author";
		$row->arRes["CURRENT_USER_GROUPS"] = $arRes["CURRENT_USER_GROUPS"];

		$arStr = array();
		$arStr1 = array();
		foreach ($arDocumentStates as $kk => $vv)
		{
			$canViewWorkflow = CIBlockDocument::CanUserOperateDocument(
				CBPCanUserOperateOperation::ViewWorkflow,
				$USER->GetID(),
				$arRes["ID"],
				array("AllUserGroups" => $arRes["CURRENT_USER_GROUPS"], "DocumentStates" => $arDocumentStates, "WorkflowId" => $kk)
			);
			if (!$canViewWorkflow)
				continue;

			$arStr1[$vv["TEMPLATE_ID"]] = $vv["TEMPLATE_NAME"];
			$arStr[$vv["TEMPLATE_ID"]] .= "<a href=\"".$selfFolderUrl."bizproc_log.php?ID=".$kk."\">".(strlen($vv["STATE_TITLE"]) > 0 ? $vv["STATE_TITLE"] : $vv["STATE_NAME"])."</a><br />";

			if (strlen($vv["ID"]) > 0)
			{
				$arTasks = CBPDocument::GetUserTasksForWorkflow($USER->GetID(), $vv["ID"]);
				foreach ($arTasks as $arTask)
				{
					$arStr[$vv["TEMPLATE_ID"]] .= GetMessage("IBEL_A_BP_TASK").":<br /><a href=\"bizproc_task.php?id=".$arTask["ID"]."\" title=\"".$arTask["DESCRIPTION"]."\">".$arTask["NAME"]."</a><br /><br />";
				}
			}
		}

		$str = "";
		foreach ($arStr as $k => $v)
		{
			$row->AddViewField("WF_".$k, $v);
			$str .= "<b>".(strlen($arStr1[$k]) > 0 ? $arStr1[$k] : GetMessage("IBEL_A_BP_PROC"))."</b>:<br />".$v."<br />";
		}

		$row->AddViewField("BIZPROC", $str);
	}
}

$boolIBlockElementAdd = CIBlockSectionRights::UserHasRightTo($IBLOCK_ID, $find_section_section, "section_element_bind");

$arElementOps = CIBlockElementRights::UserHasRightTo(
	$IBLOCK_ID,
	array_keys($arRows),
	"",
	CIBlockRights::RETURN_OPERATIONS
);
$availQuantityTrace = (string)Main\Config\Option::get("catalog", "default_quantity_trace");
$arQuantityTrace = array(
	"D" => GetMessage("IBEL_DEFAULT_VALUE")." (".($availQuantityTrace=='Y' ? GetMessage("IBEL_YES_VALUE") : GetMessage("IBEL_NO_VALUE")).")",
	"Y" => GetMessage("IBEL_YES_VALUE"),
	"N" => GetMessage("IBEL_NO_VALUE"),
);
if ($bCatalog && !empty($arRows))
{
	$arRowKeys = array_keys($arRows);
	if ($strUseStoreControl == "Y" && in_array("CATALOG_BAR_CODE", $arSelectedFields))
	{
		$productsWithBarCode = array();
		$rsProducts = Catalog\ProductTable::getList(array(
			'select' => array('ID', 'BARCODE_MULTI'),
			'filter' => array('@ID' => $arRowKeys)
		));
		while ($product = $rsProducts->fetch())
		{
			if (isset($arRows[$product["ID"]]))
			{
				if ($product["BARCODE_MULTI"] == "Y")
					$arRows[$product["ID"]]->arRes["CATALOG_BAR_CODE"] = GetMessage("IBEL_CATALOG_BAR_CODE_MULTI");
				else
					$productsWithBarCode[] = $product["ID"];
			}
		}
		if (!empty($productsWithBarCode))
		{
			$rsProducts = CCatalogStoreBarCode::getList(array(), array(
				"PRODUCT_ID" => $productsWithBarCode,
			));
			while ($product = $rsProducts->Fetch())
			{
				if (isset($arRows[$product["PRODUCT_ID"]]))
				{
					$arRows[$product["PRODUCT_ID"]]->arRes["CATALOG_BAR_CODE"] = htmlspecialcharsEx($product["BARCODE"]);
				}
			}
		}
	}

	if (isset($arSelectedFieldsMap['CATALOG_MEASURE_RATIO']))
	{
		$iterator = Catalog\MeasureRatioTable::getList(array(
			'select' => array('ID', 'PRODUCT_ID', 'RATIO'),
			'filter' => array('@PRODUCT_ID' => $arRowKeys, '=IS_DEFAULT' => 'Y')
		));
		while ($row = $iterator->fetch())
		{
			$id = (int)$row['PRODUCT_ID'];
			if (isset($arRows[$id]))
				$arRows[$id]->arRes['CATALOG_MEASURE_RATIO'] = $row['RATIO'];
			unset($id);
		}
		unset($row, $iterator);
	}
}

if ($arIBTYPE['SECTIONS'] == 'Y' && isset($arSelectedFieldsMap['SECTIONS']) && !empty($arRows))
{
	$sectionList = [];
	$itemSections = [];
	$sectionToItem = [];
	$sectionIds = [];

	$elementIds = array_keys($arRows);
	sort($elementIds);
	foreach (array_chunk($elementIds, 500) as $pageIds)
	{
		$iterator = Iblock\SectionElementTable::getList([
			'select' => ['IBLOCK_SECTION_ID', 'IBLOCK_ELEMENT_ID'],
			'filter' => ['@IBLOCK_ELEMENT_ID' => $pageIds, '=ADDITIONAL_PROPERTY_ID' => null]
		]);
		while ($row = $iterator->fetch())
		{
			$itemSectionId = (int)$row['IBLOCK_SECTION_ID'];
			$itemId = (int)$row['IBLOCK_ELEMENT_ID'];
			if (!isset($sectionToItem[$itemSectionId]))
				$sectionToItem[$itemSectionId] = [];
			$sectionToItem[$itemSectionId][] = $itemId;
			$sectionIds[$itemSectionId] = $itemSectionId;
		}
		unset($itemId, $itemSectionId, $row, $iterator);
	}
	unset($pageIds);

	if (!empty($sectionIds))
	{
		sort($sectionIds);
		foreach (array_chunk($sectionIds, 500) as $pageIds)
		{
			$iterator = \CIBlockSection::GetList(
				[],
				['IBLOCK_ID' => $IBLOCK_ID, 'ID' => $pageIds, 'CHECK_PERMISSIONS' => 'Y', 'MIN_PERMISSION' => 'S'],
				false,
				['ID', 'IBLOCK_ID', 'NAME', 'LEFT_MARGIN'],
				false
			);
			while ($row = $iterator->Fetch())
			{
				$rowId = (int)$row['ID'];
				if (empty($sectionToItem[$rowId]))
					continue;
				$row['LEFT_MARGIN'] = (int)$row['LEFT_MARGIN'];
				$sectionLink = $selfFolderUrl.\CIBlock::GetAdminElementListLink($IBLOCK_ID, ['find_section_section' => $row['ID']]);
				$sectionLink = \CHTTP::urlAddParams($sectionLink, array("SECTION_ID" => $row['ID'], "apply_filter" => "Y"));
				$sectionList[$rowId] = '<a href="'.CHTTP::URN2URI($sectionLink).'" title="'.GetMessage('IBEL_SECTIONS_LINK_TITLE').'">'.htmlspecialcharsEx($row['NAME']).'</a>';
				foreach ($sectionToItem[$rowId] as $itemId)
				{
					if (!isset($itemSections[$itemId]))
						$itemSections[$itemId] = [];
					$itemSections[$itemId][] = [
						'LEFT_MARGIN' => $row['LEFT_MARGIN'],
						'ID' => $rowId
					];
				}
			}
			unset($row, $iterator);
		}
		unset($pageIds);
	}
	unset($sectionIds, $sectionToItem);

	/** @var CAdminListRow $row */
	foreach ($arRows as $itemId => $row)
	{
		$sectionsContent = '';
		if (!empty($itemSections[$itemId]))
		{
			Main\Type\Collection::sortByColumn(
				$itemSections[$itemId],
				'LEFT_MARGIN'
			);
			foreach ($itemSections[$itemId] as $data)
				$sectionsContent .= $sectionList[$data['ID']].'<br>';
			unset($data);
		}
		$row->AddViewField('SECTIONS', $sectionsContent);
	}
	unset($sectionsContent, $itemId, $row);

	unset($itemSections, $sectionList);
}

foreach($arRows as $idRow => $row)
{
	/** @var CAdminListRow $row */
	if (isset($arSelectedFieldsMap["PREVIEW_TEXT"]))
		$row->AddViewField("PREVIEW_TEXT", ($row->arRes["PREVIEW_TEXT_TYPE"]=="text" ? htmlspecialcharsEx($row->arRes["PREVIEW_TEXT"]) : HTMLToTxt($row->arRes["PREVIEW_TEXT"])));
	if (isset($arSelectedFieldsMap["DETAIL_TEXT"]))
		$row->AddViewField("DETAIL_TEXT", ($row->arRes["DETAIL_TEXT_TYPE"]=="text" ? htmlspecialcharsEx($row->arRes["DETAIL_TEXT"]) : HTMLToTxt($row->arRes["DETAIL_TEXT"])));

	if(isset($arElementOps[$idRow]) && isset($arElementOps[$idRow]["element_edit"]))
	{
		if ($bCatalog)
		{
			if ($showCatalogWithOffers || $row->arRes['CATALOG_TYPE'] != Catalog\ProductTable::TYPE_SKU)
			{
				if (isset($arElementOps[$idRow]["element_edit_price"]))
				{
					if (isset($row->arRes['price']) && is_array($row->arRes['price']))
						foreach($row->arRes['price'] as $price_id => $sHTML)
							$row->AddEditField($price_id, $sHTML);
				}
			}
			else
			{
				if(isset($row->arRes['price']) && is_array($row->arRes['price']))
					foreach($row->arRes['price'] as $price_id => $sHTML)
						$row->AddViewField($price_id, ' ');
			}
		}

		$row->AddCheckField("WF_NEW", false);
		$row->AddCheckField("ACTIVE");
		$row->AddInputField("NAME", array('size'=>'35'));
		$row->AddViewField("NAME", '<a href="'.$row->arRes["edit_url"].'" title="'.GetMessage("IBEL_A_EDIT_TITLE").'">'.htmlspecialcharsEx($row->arRes["NAME"]).'</a>');
		$row->AddInputField("SORT", array('size'=>'3'));
		$row->AddInputField("CODE");
		$row->AddInputField("EXTERNAL_ID");
		if ($bSearch)
		{
			$row->AddViewField("TAGS", htmlspecialcharsEx($row->arRes["TAGS"]));
			$row->AddEditField("TAGS", InputTags("FIELDS[".$idRow."][TAGS]", $row->arRes["TAGS"], $arIBlock["SITE_ID"]));
		}
		else
		{
			$row->AddInputField("TAGS");
		}
		$row->AddCalendarField("DATE_ACTIVE_FROM", array(), $useCalendarTime);
		$row->AddCalendarField("DATE_ACTIVE_TO", array(), $useCalendarTime);

		if(!empty($arWFStatusPerm))
			$row->AddSelectField("WF_STATUS_ID", $arWFStatusPerm);
		if($row->arRes['orig']['WF_NEW']=='Y' || $row->arRes['WF_STATUS_ID']=='1')
			$row->AddViewField("WF_STATUS_ID", htmlspecialcharsEx($arWFStatusAll[$row->arRes['WF_STATUS_ID']]));
		else
			$row->AddViewField("WF_STATUS_ID", '<a href="'.$row->arRes["edit_url"].'" title="'.
				GetMessage("IBEL_A_ED_TITLE").'">'.htmlspecialcharsEx($arWFStatusAll[$row->arRes['WF_STATUS_ID']]).
				'</a> / <a href="'.htmlspecialcharsbx($selfFolderUrl.CIBlock::GetAdminElementEditLink($IBLOCK_ID, $row->arRes['orig']['ID'], array(
				"find_section_section" => $find_section_section, "replace_script_name" => true,
				'view' => (!isset($arElementOps[$idRow]) || !isset($arElementOps[$idRow]["element_edit_any_wf_status"])? 'Y': null)
			))).'" title="'.GetMessage("IBEL_A_ED2_TITLE").'">'.htmlspecialcharsEx($arWFStatusAll[$row->arRes['orig']['WF_STATUS_ID']]).'</a>');

		if (array_key_exists("PREVIEW_PICTURE", $arSelectedFieldsMap))
		{
			$row->AddFileField("PREVIEW_PICTURE", array(
				"IMAGE" => "Y",
				"PATH" => "Y",
				"FILE_SIZE" => "Y",
				"DIMENSIONS" => "Y",
				"IMAGE_POPUP" => "Y",
				"MAX_SIZE" => $maxImageSize,
				"MIN_SIZE" => $minImageSize,
				), array(
					'upload' => true,
					'medialib' => false,
					'file_dialog' => false,
					'cloud' => true,
					'del' => true,
					'description' => true,
				)
			);
		}
		if (array_key_exists("DETAIL_PICTURE", $arSelectedFieldsMap))
		{
			$row->AddFileField("DETAIL_PICTURE", array(
				"IMAGE" => "Y",
				"PATH" => "Y",
				"FILE_SIZE" => "Y",
				"DIMENSIONS" => "Y",
				"IMAGE_POPUP" => "Y",
				"MAX_SIZE" => $maxImageSize,
				"MIN_SIZE" => $minImageSize,
				), array(
					'upload' => true,
					'medialib' => false,
					'file_dialog' => false,
					'cloud' => true,
					'del' => true,
					'description' => true,
				)
			);
		}
		if(array_key_exists("PREVIEW_TEXT", $arSelectedFieldsMap))
		{
			$sHTML = '<input type="radio" name="FIELDS['.$idRow.'][PREVIEW_TEXT_TYPE]" value="text" id="'.$idRow.'PREVIEWtext"';
			if($row->arRes["PREVIEW_TEXT_TYPE"]!="html")
				$sHTML .= ' checked';
			$sHTML .= '><label for="'.$idRow.'PREVIEWtext">text</label> /';
			$sHTML .= '<input type="radio" name="FIELDS['.$idRow.'][PREVIEW_TEXT_TYPE]" value="html" id="'.$idRow.'PREVIEWhtml"';
			if($row->arRes["PREVIEW_TEXT_TYPE"]=="html")
				$sHTML .= ' checked';
			$sHTML .= '><label for="'.$idRow.'PREVIEWhtml">html</label><br>';
			$sHTML .= '<textarea rows="10" cols="50" name="FIELDS['.$idRow.'][PREVIEW_TEXT]">'.htmlspecialcharsbx($row->arRes["PREVIEW_TEXT"]).'</textarea>';
			$row->AddEditField("PREVIEW_TEXT", $sHTML);
		}
		if(array_key_exists("DETAIL_TEXT", $arSelectedFieldsMap))
		{
			$sHTML = '<input type="radio" name="FIELDS['.$idRow.'][DETAIL_TEXT_TYPE]" value="text" id="'.$idRow.'DETAILtext"';
			if($row->arRes["DETAIL_TEXT_TYPE"]!="html")
				$sHTML .= ' checked';
			$sHTML .= '><label for="'.$idRow.'DETAILtext">text</label> /';
			$sHTML .= '<input type="radio" name="FIELDS['.$idRow.'][DETAIL_TEXT_TYPE]" value="html" id="'.$idRow.'DETAILhtml"';
			if($row->arRes["DETAIL_TEXT_TYPE"]=="html")
				$sHTML .= ' checked';
			$sHTML .= '><label for="'.$idRow.'DETAILhtml">html</label><br>';

			$sHTML .= '<textarea rows="10" cols="50" name="FIELDS['.$idRow.'][DETAIL_TEXT]">'.htmlspecialcharsbx($row->arRes["DETAIL_TEXT"]).'</textarea>';
			$row->AddEditField("DETAIL_TEXT", $sHTML);
		}
		foreach($row->arRes['props'] as $prop_id => $arEditHTML)
			$row->AddEditField($prop_id, '<table id="tb'.$arEditHTML['table_id'].'" border="0" cellpadding="0" cellspacing="0"><tr><td nowrap>'.implode("</td></tr><tr><td nowrap>", $arEditHTML['html']).'</td></tr></table>');

		if ($bCatalog)
		{
			if ($showCatalogWithOffers || $row->arRes['CATALOG_TYPE'] != Catalog\ProductTable::TYPE_SKU)
			{
				if (isset($arElementOps[$idRow]["element_edit_price"]) && $boolCatalogPrice)
				{
					if ($strUseStoreControl == "Y")
					{
						$row->AddInputField("CATALOG_QUANTITY", false);
					}
					else
					{
						$row->AddInputField("CATALOG_QUANTITY");
					}
					$row->AddCheckField('CATALOG_AVAILABLE', false);
					$row->AddSelectField("CATALOG_QUANTITY_TRACE", $arQuantityTrace);
					$row->AddInputField("CATALOG_WEIGHT");
					$row->AddInputField("CATALOG_WIDTH");
					$row->AddInputField("CATALOG_HEIGHT");
					$row->AddInputField("CATALOG_LENGTH");
					$row->AddCheckField("CATALOG_VAT_INCLUDED");
					if ($boolCatalogPurchasInfo)
					{
						$price = '';
						if ((float)$row->arRes["CATALOG_PURCHASING_PRICE"] > 0)
						{
							if ($bCurrency)
								$price = CCurrencyLang::CurrencyFormat($row->arRes["CATALOG_PURCHASING_PRICE"], $row->arRes["CATALOG_PURCHASING_CURRENCY"], true);
							else
								$price = $row->arRes["CATALOG_PURCHASING_PRICE"]." ".$row->arRes["CATALOG_PURCHASING_CURRENCY"];
						}
						$row->AddViewField("CATALOG_PURCHASING_PRICE", htmlspecialcharsEx($price));
						unset($price);
						if ($catalogPurchasInfoEdit && $bCurrency)
						{
							$editFieldCode = '<input type="hidden" name="FIELDS_OLD['.$idRow.'][CATALOG_PURCHASING_PRICE]" value="'.$row->arRes['CATALOG_PURCHASING_PRICE'].'">';
							$editFieldCode .= '<input type="hidden" name="FIELDS_OLD['.$idRow.'][CATALOG_PURCHASING_CURRENCY]" value="'.$row->arRes['CATALOG_PURCHASING_CURRENCY'].'">';
							$editFieldCode .= '<input type="text" size="5" name="FIELDS['.$idRow.'][CATALOG_PURCHASING_PRICE]" value="'.$row->arRes['CATALOG_PURCHASING_PRICE'].'">';
							$editFieldCode .= '<select name="FIELDS['.$idRow.'][CATALOG_PURCHASING_CURRENCY]">';
							foreach ($arCurrencyList as &$currencyCode)
							{
								$editFieldCode .= '<option value="'.$currencyCode.'"';
								if ($currencyCode == $row->arRes['CATALOG_PURCHASING_CURRENCY'])
									$editFieldCode .= ' selected';
								$editFieldCode .= '>'.$currencyCode.'</option>';
							}
							$editFieldCode .= '</select>';
							$row->AddEditField('CATALOG_PURCHASING_PRICE', $editFieldCode);
							unset($editFieldCode);
						}
					}
					$row->AddInputField("CATALOG_MEASURE_RATIO");
				}
				elseif ($boolCatalogRead)
				{
					$row->AddCheckField('CATALOG_AVAILABLE', false);
					$row->AddInputField("CATALOG_QUANTITY", false);
					$row->AddSelectField("CATALOG_QUANTITY_TRACE", $arQuantityTrace, false);
					$row->AddInputField("CATALOG_WEIGHT", false);
					$row->AddInputField("CATALOG_WIDTH", false);
					$row->AddInputField("CATALOG_HEIGHT", false);
					$row->AddInputField("CATALOG_LENGTH", false);
					$row->AddCheckField("CATALOG_VAT_INCLUDED", false);
					if ($boolCatalogPurchasInfo)
					{
						$price = '';
						if ((float)$row->arRes["CATALOG_PURCHASING_PRICE"] > 0)
						{
							if ($bCurrency)
								$price = CCurrencyLang::CurrencyFormat($row->arRes["CATALOG_PURCHASING_PRICE"], $row->arRes["CATALOG_PURCHASING_CURRENCY"], true);
							else
								$price = $row->arRes["CATALOG_PURCHASING_PRICE"]." ".$row->arRes["CATALOG_PURCHASING_CURRENCY"];
						}
						$row->AddViewField("CATALOG_PURCHASING_PRICE", htmlspecialcharsEx($price));
						unset($price);
					}
					$row->AddInputField("CATALOG_MEASURE_RATIO", false);
				}
			}
			else
			{
				$row->AddCheckField('CATALOG_AVAILABLE', false);
				$row->AddViewField('CATALOG_QUANTITY', ' ');
				$row->AddViewField('CATALOG_QUANTITY_TRACE', ' ');
				$row->AddViewField('CATALOG_WEIGHT', ' ');
				$row->AddViewField('CATALOG_WIDTH', ' ');
				$row->AddViewField('CATALOG_HEIGHT', ' ');
				$row->AddViewField('CATALOG_LENGTH', ' ');
				$row->AddViewField('CATALOG_VAT_INCLUDED', ' ');
				$row->AddViewField('CATALOG_PURCHASING_PRICE', ' ');
				$row->AddViewField('CATALOG_MEASURE_RATIO', ' ');
				$row->AddViewField('CATALOG_MEASURE', ' ');
				$row->arRes["CATALOG_BAR_CODE"] = ' ';
			}
		}
	}
	else
	{
		$row->AddCheckField("ACTIVE", false);
		$row->AddViewField("NAME", '<a href="'.$row->arRes["edit_url"].'" title="'.GetMessage("IBEL_A_EDIT_TITLE").'">'.htmlspecialcharsEx($row->arRes["NAME"]).'</a>');
		$row->AddInputField("SORT", false);
		$row->AddInputField("CODE", false);
		$row->AddInputField("EXTERNAL_ID", false);
		$row->AddViewField("TAGS", htmlspecialcharsEx($row->arRes["TAGS"]));
		$row->AddCalendarField("DATE_ACTIVE_FROM", false);
		$row->AddCalendarField("DATE_ACTIVE_TO", false);
		$row->AddViewField("WF_STATUS_ID", htmlspecialcharsEx($arWFStatusAll[$row->arRes['WF_STATUS_ID']]));

		if ($bCatalog)
		{
			if ($showCatalogWithOffers || $row->arRes['CATALOG_TYPE'] != Catalog\ProductTable::TYPE_SKU)
			{
				$row->AddCheckField('CATALOG_AVAILABLE', false);
				$row->AddInputField("CATALOG_QUANTITY", false);
				$row->AddSelectField("CATALOG_QUANTITY_TRACE", $arQuantityTrace, false);
				$row->AddInputField("CATALOG_WEIGHT", false);
				$row->AddCheckField("CATALOG_VAT_INCLUDED", false);
				if ($boolCatalogPurchasInfo)
				{
					$price = '';
					if ((float)$row->arRes["CATALOG_PURCHASING_PRICE"] > 0)
					{
						if ($bCurrency)
							$price = CCurrencyLang::CurrencyFormat($row->arRes["CATALOG_PURCHASING_PRICE"], $row->arRes["CATALOG_PURCHASING_CURRENCY"], true);
						else
							$price = $row->arRes["CATALOG_PURCHASING_PRICE"]." ".$row->arRes["CATALOG_PURCHASING_CURRENCY"];
					}
					$row->AddViewField("CATALOG_PURCHASING_PRICE", htmlspecialcharsEx($price));
					unset($price);
				}
				$row->AddInputField("CATALOG_MEASURE_RATIO", false);
			}
			else
			{
				$row->AddCheckField('CATALOG_AVAILABLE', false);
				$row->AddViewField('CATALOG_QUANTITY', ' ');
				$row->AddViewField('CATALOG_QUANTITY_TRACE', ' ');
				$row->AddViewField('CATALOG_WEIGHT', ' ');
				$row->AddViewField('CATALOG_WIDTH', ' ');
				$row->AddViewField('CATALOG_HEIGHT', ' ');
				$row->AddViewField('CATALOG_LENGTH', ' ');
				$row->AddViewField('CATALOG_VAT_INCLUDED', ' ');
				$row->AddViewField('CATALOG_PURCHASING_PRICE', ' ');
				$row->AddViewField('CATALOG_MEASURE_RATIO', ' ');
				$row->AddViewField('CATALOG_MEASURE', ' ');
				$row->arRes["CATALOG_BAR_CODE"] = ' ';
			}
		}
		if (array_key_exists("PREVIEW_PICTURE", $arSelectedFieldsMap))
		{
			$row->AddViewFileField("PREVIEW_PICTURE", array(
				"IMAGE" => "Y",
				"PATH" => "Y",
				"FILE_SIZE" => "Y",
				"DIMENSIONS" => "Y",
				"IMAGE_POPUP" => "Y",
				"MAX_SIZE" => $maxImageSize,
				"MIN_SIZE" => $minImageSize,
				)
			);
		}
		if (array_key_exists("DETAIL_PICTURE", $arSelectedFieldsMap))
		{
			$row->AddViewFileField("DETAIL_PICTURE", array(
				"IMAGE" => "Y",
				"PATH" => "Y",
				"FILE_SIZE" => "Y",
				"DIMENSIONS" => "Y",
				"IMAGE_POPUP" => "Y",
				"MAX_SIZE" => $maxImageSize,
				"MIN_SIZE" => $minImageSize,
				)
			);
		}
	}

	if (isset($arSelectedFieldsMap['CATALOG_TYPE']))
	{
		$strProductType = '';
		if (isset($productTypeList[$row->arRes["CATALOG_TYPE"]]))
			$strProductType = $productTypeList[$row->arRes["CATALOG_TYPE"]];
		if ($row->arRes['CATALOG_BUNDLE'] == 'Y' && $boolCatalogSet)
			$strProductType .= ('' != $strProductType ? ', ' : '').GetMessage('IBEL_CATALOG_TYPE_MESS_GROUP');
		$row->AddViewField('CATALOG_TYPE', $strProductType);
	}
	if ($bCatalog && isset($arSelectedFieldsMap['CATALOG_MEASURE']) && ($showCatalogWithOffers || $row->arRes['CATALOG_TYPE'] != Catalog\ProductTable::TYPE_SKU))
	{
		if (isset($arElementOps[$idRow]["element_edit_price"]) && $boolCatalogPrice && $row->arRes['CATALOG_TYPE'] != Catalog\ProductTable::TYPE_SET)
		{
			$row->AddSelectField('CATALOG_MEASURE', $measureList);
		}
		else
		{
			$measureTitle = (isset($measureList[$row->arRes['CATALOG_MEASURE']])
				? $measureList[$row->arRes['CATALOG_MEASURE']]
				: $measureList[0]
			);
			$row->AddViewField('CATALOG_MEASURE', $measureTitle);
			unset($measureTitle);
		}
	}

	$arActions = array();

	if($row->arRes["ACTIVE"] == "Y")
	{
		$arActive = array(
			"TEXT" => GetMessage("IBEL_A_DEACTIVATE"),
			"ACTION" => $lAdmin->ActionDoGroup($row->arRes['orig']['ID'], "deactivate", $sThisSectionUrl),
			"ONCLICK" => "",
		);
	}
	else
	{
		$arActive = array(
			"TEXT" => GetMessage("IBEL_A_ACTIVATE"),
			"ACTION" => $lAdmin->ActionDoGroup($row->arRes['orig']['ID'], "activate", $sThisSectionUrl),
			"ONCLICK" => "",
		);
	}
	$clearCounter = array(
		"TEXT" => GetMessage('IBEL_A_CLEAR_COUNTER'),
		"TITLE" => GetMessage('IBEL_A_CLEAR_COUNTER_TITLE'),
		"ACTION" => "if(confirm('".GetMessageJS("IBLOCK_CLEAR_COUNTER_CONFIRM")."')) ".$lAdmin->ActionDoGroup($row->arRes['orig']['ID'], "clear_counter", $sThisSectionUrl),
		"ONCLICK" => ""
	);

	if($bWorkFlow)
	{
		if(isset($arElementOps[$idRow]) && isset($arElementOps[$idRow]["element_edit_any_wf_status"]))
			$STATUS_PERMISSION = 2;
		else
			$STATUS_PERMISSION = CIBlockElement::WF_GetStatusPermission($row->arRes["WF_STATUS_ID"]);

		$intMinPerm = 2;

		$arUnLock = array(
			"ICON" => "unlock",
			"TEXT" => GetMessage("IBEL_A_UNLOCK"),
			"TITLE" => GetMessage("IBLOCK_UNLOCK_ALT"),
			"ACTION" => "if(confirm('".GetMessageJS("IBLOCK_UNLOCK_CONFIRM")."')) ".$lAdmin->ActionDoGroup($row->arRes['orig']['ID'], "unlock", $sThisSectionUrl),
			"ONCLICK" => "",
		);

		if($row->arRes['orig']['LOCK_STATUS'] == "red")
		{
			if (CWorkflow::IsAdmin())
				$arActions[] = $arUnLock;
		}
		else
		{
			/*
			 * yellow unlock
			 * edit
			 * copy
			 * history
			 * view (?)
			 * edit_orig (?)
			 * delete
			 */
			if (
				isset($arElementOps[$idRow])
				&& isset($arElementOps[$idRow]["element_edit"])
				&& (2 <= $STATUS_PERMISSION)
			)
			{
				if ($row->arRes['orig']['LOCK_STATUS'] == "yellow")
				{
					$arActions[] = $arUnLock;
				}

				$urlParams = array(
					"find_section_section" => $find_section_section,
					'WF' => 'Y',
				);
				if ($publicMode)
				{
					$urlParams["replace_script_name"] = true;
				}
				$arActions[] = array(
					"ICON" => "edit",
					"TEXT" => GetMessage("IBEL_A_CHANGE"),
					"DEFAULT" => true,
					"ACTION" => $lAdmin->ActionRedirect(CIBlock::GetAdminElementEditLink($IBLOCK_ID, $row->arRes['orig']['ID'], $urlParams)),
					"ONCLICK" => "",
				);
				$arActions[] = $arActive;
				$arActions[] = $clearCounter;
			}

			if (
				$boolIBlockElementAdd
				&& (2 <= $STATUS_PERMISSION)
			)
			{
				$urlParams = array(
					"find_section_section" => $find_section_section,
					'WF' => 'Y',
					'action' => 'copy',
				);
				if ($publicMode)
				{
					$urlParams["replace_script_name"] = true;
				}
				$arActions[] = array(
					"ICON" => "copy",
					"TEXT" => GetMessage("IBEL_A_COPY_ELEMENT"),
					"ACTION" => $lAdmin->ActionRedirect(CIBlock::GetAdminElementEditLink($IBLOCK_ID, $row->arRes['orig']['ID'], $urlParams)),
					"ONCLICK" => "",
				);
			}

			if(!defined("CATALOG_PRODUCT"))
			{
				$arActions[] = array(
					"ICON" => "history",
					"TEXT" => GetMessage("IBEL_A_HIST"),
					"TITLE" => GetMessage("IBLOCK_HISTORY_ALT"),
					"ACTION" => $lAdmin->ActionRedirect('iblock_history_list.php?ELEMENT_ID='.$row->arRes['orig']['ID'].$sThisSectionUrl),
					"ONCLICK" => "",
				);
			}

			if (strlen($row->arRes['DETAIL_PAGE_URL']) > 0 && !$publicMode)
			{
				$tmpVar = CIBlock::ReplaceDetailUrl($row->arRes['orig']["DETAIL_PAGE_URL"], $row->arRes['orig'], true, "E");

				if (
					$row->arRes['orig']['WF_NEW'] == "Y"
					&& isset($arElementOps[$idRow])
					&& isset($arElementOps[$idRow]["element_edit"])
					&& 2 <= $STATUS_PERMISSION
				) // not published, under workflow
				{
					$arActions[] = array(
						"ICON" => "view",
						"TEXT" => GetMessage("IBLOCK_EL_ADMIN_VIEW_WF"),
						"TITLE" => GetMessage("IBEL_A_ORIG"),
						"ACTION" => $lAdmin->ActionRedirect(htmlspecialcharsbx($tmpVar).((strpos($tmpVar, "?") !== false) ? "&" : "?")."show_workflow=Y"),
						"ONCLICK" => "",
					);
				}
				elseif ($row->arRes["WF_STATUS_ID"] > 1)
				{
					if (isset($arElementOps[$idRow])
						&& isset($arElementOps[$idRow]["element_edit"])
						&& isset($arElementOps[$idRow]["element_edit_any_wf_status"]))
					{
						$arActions[] = array(
							"ICON" => "view",
							"TEXT" => GetMessage("IBLOCK_EL_ADMIN_VIEW"),
							"TITLE" => GetMessage("IBEL_A_ORIG"),
							"ACTION" => $lAdmin->ActionRedirect(htmlspecialcharsbx($tmpVar)),
							"ONCLICK" => "",
						);

						$arActions[] = array(
							"ICON" => "view",
							"TEXT" => GetMessage("IBLOCK_EL_ADMIN_VIEW_WF"),
							"TITLE" => GetMessage("IBEL_A_ORIG"),
							"ACTION" => $lAdmin->ActionRedirect(htmlspecialcharsbx($tmpVar).((strpos($tmpVar, "?") !== false) ? "&" : "?")."show_workflow=Y"),
							"ONCLICK" => "",
						);
					}
				}
				else
				{
					if (isset($arElementOps[$idRow])
						&& isset($arElementOps[$idRow]["element_edit"])
						&& 2 <= $STATUS_PERMISSION
					)
					{
						$arActions[] = array(
							"ICON" => "view",
							"TEXT" => GetMessage("IBLOCK_EL_ADMIN_VIEW"),
							"TITLE" => GetMessage("IBEL_A_ORIG"),
							"ACTION" => $lAdmin->ActionRedirect(htmlspecialcharsbx($tmpVar)),
							"ONCLICK" => "",
						);
					}
				}
			}

			if ($row->arRes["WF_STATUS_ID"] > 1
				&& isset($arElementOps[$idRow])
				&& isset($arElementOps[$idRow]["element_edit"])
				&& isset($arElementOps[$idRow]["element_edit_any_wf_status"])
			)
			{
				$urlParams = array("find_section_section" => $find_section_section);
				if ($publicMode)
				{
					$urlParams["replace_script_name"] = true;
				}
				$arActions[] = array(
					"ICON" => "edit_orig",
					"TEXT" => GetMessage("IBEL_A_ORIG_ED"),
					"TITLE" => GetMessage("IBEL_A_ORIG_ED_TITLE"),
					"ACTION" => $lAdmin->ActionRedirect(CIBlock::GetAdminElementEditLink($IBLOCK_ID, $row->arRes['orig']['ID'], $urlParams)),
					"ONCLICK" => "",
				);
			}

			if (
				isset($arElementOps[$idRow])
				&& isset($arElementOps[$idRow]["element_delete"])
				&& (2 <= $STATUS_PERMISSION)
			)
			{
				if (!isset($arElementOps[$idRow]["element_edit_any_wf_status"]))
					$intMinPerm = CIBlockElement::WF_GetStatusPermission($row->arRes["WF_STATUS_ID"], $idRow);
				if (2 <= $intMinPerm)
				{
					$arActions[] = array(
						"ICON" => "delete",
						"TEXT" => GetMessage('MAIN_DELETE'),
						"TITLE" => GetMessage("IBLOCK_DELETE_ALT"),
						"ACTION" => "if(confirm('".GetMessageJS('IBLOCK_CONFIRM_DEL_MESSAGE')."')) ".$lAdmin->ActionDoGroup($row->arRes['orig']['ID'], "delete", $sThisSectionUrl),
						"ONCLICK" => "",
					);
				}
			}
		}
	}
	elseif($bBizproc)
	{
		$bWritePermission = CIBlockDocument::CanUserOperateDocument(
			CBPCanUserOperateOperation::WriteDocument,
			$USER->GetID(),
			$idRow,
			array(
				"IBlockId" => $IBLOCK_ID,
				"AllUserGroups" => $row->arRes["CURRENT_USER_GROUPS"],
				"DocumentStates" => $arDocumentStates,
			)
		);

		$bStartWorkflowPermission = CIBlockDocument::CanUserOperateDocument(
			CBPCanUserOperateOperation::StartWorkflow,
			$USER->GetID(),
			$idRow,
			array(
				"IBlockId" => $IBLOCK_ID,
				"AllUserGroups" => $row->arRes["CURRENT_USER_GROUPS"],
				"DocumentStates" => $arDocumentStates,
			)
		);

		if(
			$bStartWorkflowPermission
			|| (
				isset($arElementOps[$idRow])
				&& isset($arElementOps[$idRow]["element_bizproc_start"])
			)
		)
		{
			$arActions[] = array(
				"ICON" => "",
				"TEXT" => GetMessage("IBEL_A_BP_RUN"),
				"ACTION" => $lAdmin->ActionRedirect('iblock_start_bizproc.php?document_id='.$idRow.'&document_type=iblock_'.$IBLOCK_ID.'&back_url='.urlencode($APPLICATION->GetCurPageParam("", array("mode", "table_id"))).''),
				"ONCLICK" => "",
			);
		}

		if ($row->arRes['lockStatus'] == "red")
		{
			if (CBPDocument::IsAdmin())
			{
				$arActions[] = array(
					"ICON" => "unlock",
					"TEXT" => GetMessage("IBEL_A_UNLOCK"),
					"TITLE" => GetMessage("IBEL_A_UNLOCK_ALT"),
					"ACTION" => "if(confirm('".GetMessageJS("IBEL_A_UNLOCK_CONFIRM")."')) ".$lAdmin->ActionDoGroup($idRow, "unlock", $sThisSectionUrl),
					"ONCLICK" => "",
				);
			}
		}
		elseif ($bWritePermission)
		{
			$urlParams = array("find_section_section" => $find_section_section, "WF" => "Y");
			if ($publicMode)
			{
				$urlParams["replace_script_name"] = true;
			}
			$arActions[] = array(
				"ICON" => "edit",
				"TEXT" => GetMessage("IBEL_A_CHANGE"),
				"DEFAULT" => true,
				"ACTION" => $lAdmin->ActionRedirect(CIBlock::GetAdminElementEditLink($IBLOCK_ID, $idRow, $urlParams)),
				"ONCLICK" => "",
			);
			$arActions[] = $arActive;
			$arActions[] = $clearCounter;

			$urlParams["action"] = "copy";
			$arActions[] = array(
				"ICON" => "copy",
				"TEXT" => GetMessage("IBEL_A_COPY_ELEMENT"),
				"ACTION" => $lAdmin->ActionRedirect(CIBlock::GetAdminElementEditLink($IBLOCK_ID, $idRow, $urlParams)),
				"ONCLICK" => "",
			);

			if(!defined("CATALOG_PRODUCT"))
			{
				$arActions[] = array(
					"ICON" => "history",
					"TEXT" => GetMessage("IBEL_A_HIST"),
					"TITLE" => GetMessage("IBEL_A_HISTORY_ALT"),
					"ACTION" => $lAdmin->ActionRedirect('iblock_bizproc_history.php?document_id='.$idRow.'&back_url='.urlencode($APPLICATION->GetCurPageParam("", array())).''),
					"ONCLICK" => "",
				);
			}

			$arActions[] = array(
				"ICON" => "delete",
				"TEXT" => GetMessage('MAIN_DELETE'),
				"TITLE" => GetMessage("IBLOCK_DELETE_ALT"),
				"ACTION" => "if(confirm('".GetMessageJS('IBLOCK_CONFIRM_DEL_MESSAGE')."')) ".$lAdmin->ActionDoGroup($idRow, "delete", $sThisSectionUrl),
				"ONCLICK" => "",
			);
		}
	}
	else
	{
		if(
			isset($arElementOps[$idRow])
			&& isset($arElementOps[$idRow]["element_edit"])
		)
		{
			$urlParams = array("find_section_section" => $find_section_section);
			if ($publicMode)
			{
				$urlParams["replace_script_name"] = true;
			}
			$arActions[] = array(
				"ICON" => "edit",
				"TEXT" => GetMessage("IBEL_A_CHANGE"),
				"DEFAULT" => true,
				"ACTION" => $lAdmin->ActionRedirect(CIBlock::GetAdminElementEditLink($IBLOCK_ID, $row->arRes['orig']['ID'], $urlParams)),
				"ONCLICK" => "",
			);
			$arActions[] = $arActive;
			$arActions[] = $clearCounter;
		}

		if ($boolIBlockElementAdd && isset($arElementOps[$idRow])
			&& isset($arElementOps[$idRow]["element_edit"]))
		{
			$urlParams = array("find_section_section" => $find_section_section, "action" => "copy");
			if ($publicMode)
			{
				$urlParams["replace_script_name"] = true;
			}
			$arActions[] = array(
				"ICON" => "copy",
				"TEXT" => GetMessage("IBEL_A_COPY_ELEMENT"),
				"ACTION" => $lAdmin->ActionRedirect(CIBlock::GetAdminElementEditLink($IBLOCK_ID, $row->arRes['orig']['ID'], $urlParams)),
				"ONCLICK" => "",
			);
		}

		if (strlen($row->arRes['DETAIL_PAGE_URL']) > 0 && !$publicMode)
		{
			$tmpVar = CIBlock::ReplaceDetailUrl($row->arRes['orig']["DETAIL_PAGE_URL"], $row->arRes['orig'], true, "E");
			$arActions[] = array(
				"ICON" => "view",
				"TEXT" => GetMessage("IBLOCK_EL_ADMIN_VIEW"),
				"TITLE" => GetMessage("IBEL_A_ORIG"),
				"ACTION" => $lAdmin->ActionRedirect(htmlspecialcharsbx($tmpVar)),
				"ONCLICK" => "",
			);
		}

		if(
			isset($arElementOps[$idRow])
			&& isset($arElementOps[$idRow]["element_delete"])
		)
		{
			$arActions[] = array(
				"ICON" => "delete",
				"TEXT" => GetMessage('MAIN_DELETE'),
				"TITLE" => GetMessage("IBLOCK_DELETE_ALT"),
				"ACTION" => "if(confirm('".GetMessageJS('IBLOCK_CONFIRM_DEL_MESSAGE')."')) ".$lAdmin->ActionDoGroup($row->arRes['orig']['ID'], "delete", $sThisSectionUrl),
				"ONCLICK" => "",
			);
		}
	}

	if(!empty($arActions))
		$row->AddActions($arActions);
}

$arGroupActions = array();
foreach($arElementOps as $id => $arOps)
{
	if(isset($arOps["element_delete"]))
	{
		$arGroupActions["delete"] = GetMessage("MAIN_ADMIN_LIST_DELETE");
		break;
	}
}
$elementEdit = false;
foreach($arElementOps as $id => $arOps)
{
	if(isset($arOps["element_edit"]))
	{
		$elementEdit = true;
		break;
	}
}

if ($elementEdit)
{
	$arGroupActions["edit"] = GetMessage("MAIN_ADMIN_LIST_EDIT");
	$arGroupActions["activate"] = GetMessage("MAIN_ADMIN_LIST_ACTIVATE");
	$arGroupActions["deactivate"] = GetMessage("MAIN_ADMIN_LIST_DEACTIVATE");
	$arGroupActions['clear_counter'] = strtolower(GetMessage('IBEL_A_CLEAR_COUNTER'));
}

if ($elementEdit)
{
	if($arIBTYPE["SECTIONS"] == "Y")
	{
		$listSection = array(
			array("NAME" => GetMessage("MAIN_NO"), "VALUE" => ""),
			array("NAME" => GetMessage("IBLOCK_UPPER_LEVEL"), "VALUE" => "0")
		);
		$sectionQueryObject = CIBlockSection::getTreeList(
			array("IBLOCK_ID" => $IBLOCK_ID), array("ID", "NAME", "DEPTH_LEVEL"));
		while ($section = $sectionQueryObject->getNext())
		{
			$listSection[] = array(
				"NAME" => str_repeat(" . ", $section["DEPTH_LEVEL"]).$section["NAME"],
				"VALUE" => $section["ID"]
			);
		}
		$arGroupActions["section"] = array(
			"lable" => GetMessage("IBEL_A_MOVE_TO_SECTION"),
			"type" => "select",
			"name" => "section_to_move",
			"items" => $listSection
		);
		$arGroupActions["add_section"] = array(
			"lable" => GetMessage("IBEL_A_ADD_TO_SECTION"),
			"type" => "select",
			"name" => "section_to_move",
			"items" => $listSection
		);
	}
	if ($bCatalog && $USER->CanDoOperation('catalog_price'))
	{
		$elementEditPrice = false;
		foreach($arElementOps as $id => $arOps)
		{
			if(isset($arOps["element_edit_price"]))
			{
				$elementEditPrice = true;
				break;
			}
		}
		if ($elementEditPrice)
		{
			$arGroupActions["change_price"] = array(
				"lable" => GetMessage("IBLOCK_CHANGE_PRICE"),
				"type" => "customJs",
				"js" => "CreateDialogChPrice()"
			);
		}
	}
}

if($bWorkFlow)
{
	$arGroupActions["unlock"] = GetMessage("IBEL_A_UNLOCK_ACTION");
	$arGroupActions["lock"] = GetMessage("IBEL_A_LOCK_ACTION");

	$listStatuses = array();
	$workflowStatusQueryObject = CWorkflowStatus::getDropDownList("N", "desc");
	while ($workflowStatus = $workflowStatusQueryObject->fetch())
	{
		$listStatuses[] = array("NAME" => $workflowStatus["REFERENCE"], "VALUE" => $workflowStatus["REFERENCE_ID"]);
	}
	$arGroupActions["wf_status"] = array(
		"lable" => GetMessage("IBEL_A_WF_STATUS_CHANGE"),
		"type" => "select",
		"name" => "wf_status_id",
		"items" => $listStatuses
	);
}
elseif($bBizproc)
{
	$arGroupActions["unlock"] = GetMessage("IBEL_A_UNLOCK_ACTION");
}

$lAdmin->AddGroupActionTable($arGroupActions);

if ($bCatalog && $USER->CanDoOperation('catalog_price'))
{
	$lAdmin->BeginEpilogContent();

	/** Creation window of common price changer */
	CJSCore::Init(array('window'));
	?>

	<script>
		/**
		 * @func CreateDialogChPrice - creation of common changing price dialog
		 */
		function CreateDialogChPrice()
		{
			var paramsWindowChanger =
			{
				title: "<?=GetMessage("IBLOCK_CHANGING_PRICE")?>",
				content_url: "/bitrix/tools/catalog/iblock_catalog_change_price.php?lang=" + "<?=LANGUAGE_ID?>" + "&bxpublic=Y",
				content_post: "<?=bitrix_sessid_get()?>" + "&sTableID=<?=$sTableID?>",
				width: 800,
				height: 415,
				resizable: false,
				buttons: [
					{
						title: top.BX.message('JS_CORE_WINDOW_SAVE'),
						id: 'savebtn',
						name: 'savebtn',
						className: top.BX.browser.IsIE() && top.BX.browser.IsDoctype() && !top.BX.browser.IsIE10() ? '' : 'adm-btn-save'
					},
					top.BX.CAdminDialog.btnCancel
				]
			};
			var priceChanger = (new top.BX.CAdminDialog(paramsWindowChanger));
			priceChanger.Show();
		}
	</script>

	<?
	$lAdmin->EndEpilogContent();
}
$sLastFolder = '';
$lastSectionId = array();
if (!defined("CATALOG_PRODUCT"))
{
	$chain = $lAdmin->CreateChain();
	$lAdmin->ShowChain($chain);
}

if($arIBTYPE["SECTIONS"]=="Y")
{
	if (!defined("CATALOG_PRODUCT"))
	{
		$chain->AddItem(array(
			"TEXT" => htmlspecialcharsEx($arIBlock["NAME"]),
			"LINK" => htmlspecialcharsbx(CIBlock::GetAdminElementListLink($IBLOCK_ID, array('find_section_section'=>0))),
		));
	}
	$lastSectionId[] = 0;

	if($find_section_section > 0)
	{
		$sLastFolder = CIBlock::GetAdminElementListLink($IBLOCK_ID, array('find_section_section'=>0));
		$nav = CIBlockSection::GetNavChain($IBLOCK_ID, $find_section_section, array('ID', 'NAME'));
		while($ar_nav = $nav->GetNext())
		{
			$sLastFolder = CIBlock::GetAdminElementListLink($IBLOCK_ID, array('find_section_section'=>$ar_nav["ID"]));
			$lastSectionId[] = $ar_nav["ID"];
			if (!defined("CATALOG_PRODUCT"))
			{
				$chain->AddItem(array(
					"TEXT" => $ar_nav["NAME"],
					"LINK" => $sLastFolder,
				));
			}
		}
	}
}

$aContext = array();

if ($boolIBlockElementAdd)
{
	$params = array(
		'IBLOCK_SECTION_ID' => $find_section_section,
		'find_section_section' => $find_section_section
	);
	if ($publicMode)
	{
		$params['replace_script_name'] = true;
	}
	if (!empty($arCatalog))
	{
		CCatalogAdminTools::setProductFormParams();
		$arCatalogBtns = CCatalogAdminTools::getIBlockElementMenu(
			$IBLOCK_ID,
			$arCatalog,
			$params
		);
		if (!empty($arCatalogBtns))
			$aContext = $arCatalogBtns;
	}
	if (empty($aContext))
	{
		$aContext[] = array(
			"ICON" => "btn_new",
			"TEXT" => htmlspecialcharsbx($arIBlock["ELEMENT_ADD"]),
			"LINK" => CIBlock::GetAdminElementEditLink($IBLOCK_ID, 0, $params),
			"LINK_PARAM" => "",
			"TITLE" => GetMessage("IBEL_A_ADDEL_TITLE")
		);
	}
}

if($bBizproc && IsModuleInstalled("bizprocdesigner"))
{
	$bCanDoIt = CBPDocument::CanUserOperateDocumentType(
		CBPCanUserOperateOperation::CreateWorkflow,
		$USER->GetID(),
		array("iblock", "CIBlockDocument", "iblock_".$IBLOCK_ID)
	);

	if($bCanDoIt)
	{
		$aContext[] = array(
			"TEXT" => GetMessage("IBEL_BTN_BP"),
			"LINK" => $selfFolderUrl.'iblock_bizproc_workflow_admin.php?document_type=iblock_'.$IBLOCK_ID.'&lang='.LANGUAGE_ID.'&back_url_list='.urlencode($REQUEST_URI),
			"LINK_PARAM" => "",
		);
	}
}

$lAdmin->setContextSettings(array("pagePath" => $selfFolderUrl.CIBlock::GetAdminElementListScriptName($IBLOCK_ID, array("skip_public" => "Y"))));
$lAdmin->AddAdminContextMenu($aContext);

$lAdmin->CheckListMode();

$APPLICATION->SetTitle(GetMessage("IBEL_LIST_TITLE", array("#IBLOCK_NAME#" => htmlspecialcharsex($arIBlock["NAME"]))));
Main\Page\Asset::getInstance()->addJs('/bitrix/js/iblock/iblock_edit.js');
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

//We need javascript not in excel mode
if((!isset($_REQUEST["mode"]) || $_REQUEST["mode"]=='list' || $_REQUEST["mode"]=='frame') && $bCatalog && $bCurrency)
{
	?><script type="text/javascript">
		top.arCatalogShowedGroups = [];
		top.arExtra = [];
		top.arCatalogGroups = [];
		top.BaseIndex = '';
	<?
	if (!empty($arCatGroup) && is_array($arCatGroup))
	{
		$i = 0;
		$j = 0;
		foreach($arCatGroup as &$CatalogGroups)
		{
			if(in_array("CATALOG_GROUP_".$CatalogGroups["ID"], $arSelectedFields))
			{
				echo "top.arCatalogShowedGroups[".$i."]=".$CatalogGroups["ID"].";\n";
				$i++;
			}
			if ($CatalogGroups["BASE"]!="Y")
			{
				echo "top.arCatalogGroups[".$j."]=".$CatalogGroups["ID"].";\n";
				$j++;
			}
			else
			{
				echo "top.BaseIndex=".$CatalogGroups["ID"].";\n";
			}
		}
		unset($CatalogGroups);
	}
	if (!empty($arCatExtra) && is_array($arCatExtra))
	{
		$i=0;
		foreach($arCatExtra as &$CatExtra)
		{
			echo "top.arExtra[".$CatExtra["ID"]."]=".$CatExtra["PERCENTAGE"].";\n";
			$i++;
		}
		unset($CatExtra);
	}
	?>
		top.ChangeBasePrice = function(id)
		{
			for(var i = 0, cnt = top.arCatalogShowedGroups.length; i < cnt; i++)
			{
				var pr = top.document.getElementById("CATALOG_PRICE["+id+"]"+"["+top.arCatalogShowedGroups[i]+"]");
				if(pr.disabled)
				{
					var price = top.document.getElementById("CATALOG_PRICE["+id+"]"+"["+top.BaseIndex+"]").value;
					if(price > 0)
					{
						var extraId = top.document.getElementById("CATALOG_EXTRA["+id+"]"+"["+top.arCatalogShowedGroups[i]+"]").value;
						var esum = parseFloat(price) * (1 + top.arExtra[extraId] / 100);
						var eps = 1.00/Math.pow(10, 6);
						esum = Math.round((esum+eps)*100)/100;
					}
					else
						var esum = "";

					pr.value = esum;
				}
			}
		}

		top.ChangeBaseCurrency = function(id)
		{
			var currency = top.document.getElementById("CATALOG_CURRENCY["+id+"]["+top.BaseIndex+"]");
			for(var i = 0, cnt = top.arCatalogShowedGroups.length; i < cnt; i++)
			{
				var pr = top.document.getElementById("CATALOG_CURRENCY["+id+"]["+top.arCatalogShowedGroups[i]+"]");
				if(pr.disabled)
				{
					pr.selectedIndex = currency.selectedIndex;
				}
			}
		}
	</script>
	<?
}

CJSCore::Init('file_input');

$lAdmin->DisplayFilter($filterFields);
$lAdmin->DisplayList();
if($bWorkFlow || $bBizproc):
	echo BeginNote();?>
	<span class="adm-lamp adm-lamp-green"></span> - <?echo GetMessage("IBLOCK_GREEN_ALT")?><br>
	<span class="adm-lamp adm-lamp-yellow"></span> - <?echo GetMessage("IBLOCK_YELLOW_ALT")?><br>
	<span class="adm-lamp adm-lamp-red"></span> - <?echo GetMessage("IBLOCK_RED_ALT")?><br>
	<?echo EndNote();
endif;

if(CIBlockRights::UserHasRightTo($IBLOCK_ID, $IBLOCK_ID, "iblock_edit") && !defined("CATALOG_PRODUCT") && !$publicMode)
{
	echo
		BeginNote(),
		GetMessage("IBEL_A_IBLOCK_MANAGE_HINT"),
		' <a href="'.htmlspecialcharsbx('iblock_edit.php?type='.urlencode($type).'&lang='.LANGUAGE_ID.'&ID='.urlencode($IBLOCK_ID).'&admin=Y&return_url='.urlencode(CIBlock::GetAdminElementListLink($IBLOCK_ID, array('find_section_section' => intval($find_section_section))))).'">',
		GetMessage("IBEL_A_IBLOCK_MANAGE_HINT_HREF"),
		'</a>',
		EndNote()
	;
}
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");