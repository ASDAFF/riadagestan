<?
use Bitrix\Main\Loader,
	Bitrix\Iblock;

define("STOP_STATISTICS", true);
define("BX_SECURITY_SHOW_MESSAGE", true);

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
Loader::includeModule("iblock");
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/iblock/prolog.php");
IncludeModuleLangFile(__FILE__);

$selfFolderUrl = $adminPage->getSelfFolderUrl();

$bFullForm = isset($_REQUEST["IBLOCK_ID"]) && isset($_REQUEST["ID"]);
$bSectionPopup = isset($_REQUEST["return_url"]) && ($_REQUEST["return_url"] === "section_edit");
$bReload = isset($_REQUEST["checkAction"]) && $_REQUEST["checkAction"] === "reload";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_REQUEST['saveresult']) && !isset($_REQUEST['IBLOCK_ID']))
	CUtil::JSPostUnescape();
elseif ($bSectionPopup && $bReload)
	CUtil::JSPostUnescape();
elseif ($adminAjaxHelper->isAjaxRequest())
	CUtil::JSPostUnescape();

global $DB, $APPLICATION, $USER;

define('DEF_LIST_VALUE_COUNT',5);

/*
* $intPropID - ID value or n0...nX
* $arPropInfo = array(
* 		ID
* 		XML_ID
* 		VALUE
* 		SORT
* 		DEF = Y/N
* 		MULTIPLE = Y/N
* )
*/
function __AddListValueIDCell($intPropID)
{
	return ((int)$intPropID > 0 ? $intPropID : '&nbsp;');
}

function __AddListValueXmlIDCell($intPropID,$arPropInfo)
{
	return '<input type="text" name="PROPERTY_VALUES['.$intPropID.'][XML_ID]" id="PROPERTY_VALUES_XML_'.$intPropID.'" value="'.htmlspecialcharsbx($arPropInfo['XML_ID']).'" size="15" maxlength="200" style="width:90%">';
}

function __AddListValueValueCell($intPropID,$arPropInfo)
{
	return '<input type="text" name="PROPERTY_VALUES['.$intPropID.'][VALUE]" id="PROPERTY_VALUES_VALUE_'.$intPropID.'" value="'.htmlspecialcharsbx($arPropInfo['VALUE']).'" size="35" maxlength="255" style="width:90%">';
}

function __AddListValueSortCell($intPropID,$arPropInfo)
{
	return '<input type="text" name="PROPERTY_VALUES['.$intPropID.'][SORT]" id="PROPERTY_VALUES_SORT_'.$intPropID.'" value="'.intval($arPropInfo['SORT']).'" size="5" maxlength="11">';
}

function __AddListValueDefCell($intPropID,$arPropInfo)
{
	return '<input type="'.('Y' == $arPropInfo['MULTIPLE'] ? 'checkbox' : 'radio').'" name="PROPERTY_VALUES_DEF'.('Y' == $arPropInfo['MULTIPLE'] ? '[]' : '').'" id="PROPERTY_VALUES_DEF_'.$arPropInfo['ID'].'" value="'.$arPropInfo['ID'].'" '.('Y' == $arPropInfo['DEF'] ? 'checked="checked"' : '').'>';
}

function __AddListValueRow($intPropID, $arPropInfo)
{
	return '<tr><td class="bx-digit-cell">'.__AddListValueIDCell($intPropID).'</td>
	<td>'.__AddListValueXmlIDCell($intPropID,$arPropInfo).'</td>
	<td>'.__AddListValueValueCell($intPropID,$arPropInfo).'</td>
	<td style="text-align:center">'.__AddListValueSortCell($intPropID,$arPropInfo).'</td>
	<td style="text-align:center">'.__AddListValueDefCell($intPropID,$arPropInfo).'</td></tr>';
}

$arDisabledPropFields = array(
	'ID',
	'IBLOCK_ID',
	'TIMESTAMP_X',
	'TMP_ID',
	'VERSION',
);

$defaultListValueSettings = array(
	'ID' => 'ntmp_xxx',
	'XML_ID' => '',
	'VALUE' => '',
	'SORT' => '500',
	'DEF' => 'N',
	'MULTIPLE' => 'N',
);

$arDefPropInfo = array(
	'ID' => 0,
	'IBLOCK_ID' => 0,
	'FILE_TYPE' => '',
	'LIST_TYPE' => Iblock\PropertyTable::LISTBOX,
	'ROW_COUNT' => '1',
	'COL_COUNT' => '30',
	'LINK_IBLOCK_ID' => '0',
	'DEFAULT_VALUE' => '',
	'USER_TYPE_SETTINGS' => false,
	'WITH_DESCRIPTION' => 'N',
	'SEARCHABLE' => 'N',
	'FILTRABLE' => 'N',
	'ACTIVE' => 'Y',
	'MULTIPLE_CNT' => Iblock\PropertyTable::DEFAULT_MULTIPLE_CNT,
	'XML_ID' => '',
	'PROPERTY_TYPE' => Iblock\PropertyTable::TYPE_STRING,
	'NAME' => '',
	'HINT' => '',
	'USER_TYPE' => '',
	'MULTIPLE' => 'N',
	'IS_REQUIRED' => 'N',
	'SORT' => '500',
	'CODE' => '',
	'SHOW_DEL' => 'N',
	'VALUES' => false,
	'SECTION_PROPERTY' => $bSectionPopup? 'N': 'Y',
	'SMART_FILTER' => 'N',
	'DISPLAY_TYPE' => '',
	'DISPLAY_EXPANDED' => 'N',
	'FILTER_HINT' => '',
);

$arHiddenPropFields = array(
	'IBLOCK_ID',
	'FILE_TYPE',
	'LIST_TYPE',
	'ROW_COUNT',
	'COL_COUNT',
	'LINK_IBLOCK_ID',
	'DEFAULT_VALUE',
	'USER_TYPE_SETTINGS',
	'WITH_DESCRIPTION',
	'SEARCHABLE',
	'FILTRABLE',
	'MULTIPLE_CNT',
	'HINT',
	'XML_ID',
	'VALUES',
	'SECTION_PROPERTY',
	'SMART_FILTER',
	'DISPLAY_TYPE',
	'DISPLAY_EXPANDED',
	'FILTER_HINT',
);

if ($_SERVER["REQUEST_METHOD"] == "POST" && !check_bitrix_sessid())
{
	require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
	$APPLICATION->AuthForm('');
	require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
	die();
}

$intIBlockID = false;
if (isset($_REQUEST["PARAMS"]['IBLOCK_ID']))
	$intIBlockID = (int)$_REQUEST["PARAMS"]['IBLOCK_ID'];
elseif (isset($_REQUEST["IBLOCK_ID"]))
	$intIBlockID = (int)$_REQUEST["IBLOCK_ID"];

if ($intIBlockID < 0 || $intIBlockID === false)
{
	require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
	ShowError(GetMessage("BT_ADM_IEP_IBLOCK_ID_IS_INVALID"));
	require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
	die();
}
elseif ($intIBlockID > 0)
{
	$rsIBlocks = CIBlock::GetList(array(), array(
		"ID" => $intIBlockID,
		"CHECK_PERMISSIONS" => "N",
	));
	$arIBlock = $rsIBlocks->Fetch();
	if ($arIBlock)
	{
		if (!CIBlockRights::UserHasRightTo($intIBlockID, $intIBlockID, "iblock_edit"))
		{
			require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
			$APPLICATION->AuthForm('');
			require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
			die();
		}
	}
	else
	{
		require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
		ShowError(str_replace('#ID#',$intIBlockID,GetMessage("BT_ADM_IEP_IBLOCK_NOT_EXISTS")));
		require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
		die();
	}
}

if(isset($_REQUEST["PARAMS"]['ID']))
	$str_PROPERTY_ID = htmlspecialcharsbx($_REQUEST["PARAMS"]['ID']);
elseif(isset($_REQUEST['ID']))
	$str_PROPERTY_ID = htmlspecialcharsbx($_REQUEST['ID']);
else
	$str_PROPERTY_ID = "";

if (!strlen($str_PROPERTY_ID))
{
	require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
	ShowError(GetMessage("BT_ADM_IEP_PROPERTY_ID_IS_ABSENT"));
	require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
	die();
}

$listUrl = $selfFolderUrl.'iblock_property_admin.php?lang='.LANGUAGE_ID.'&IBLOCK_ID='.$intIBlockID.
	($_REQUEST["admin"]=="Y"? "&admin=Y": "&admin=N");
if ($adminSidePanelHelper->isPublicFrame())
{
	$listUrl = $selfFolderUrl.'menu_catalog_attributes_'.$intIBlockID.'/?lang='.LANGUAGE_ID.'&IBLOCK_ID='.$intIBlockID.
		($_REQUEST["admin"]=="Y"? "&admin=Y": "&admin=N");
}

$propertyBaseTypes = Iblock\Helpers\Admin\Property::getBaseTypeList(true);

$arListValues = array();

if(Loader::includeModule('highloadblock') && isset($_POST['PROPERTY_DIRECTORY_VALUES']) && is_array($_POST['PROPERTY_DIRECTORY_VALUES']))
{
	if (isset($_POST["HLB_NEW_TITLE"]) && $_POST["PROPERTY_USER_TYPE_SETTINGS"]["TABLE_NAME"] == '-1')
	{
		$highBlockName = trim($_POST["HLB_NEW_TITLE"]);
		if ($highBlockName == '')
		{
			require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
			CAdminMessage::ShowOldStyleError(GetMessage("BT_ADM_IEP_HBLOCK_NAME_IS_ABSENT"));
			require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
			die();
		}
		$highBlockName = strtoupper(substr($highBlockName, 0, 1)).substr($highBlockName, 1);
		if (!preg_match('/^[A-Z][A-Za-z0-9]*$/', $highBlockName))
		{
			require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
			CAdminMessage::ShowOldStyleError(GetMessage("BT_ADM_IEP_HBLOCK_NAME_IS_INVALID"));
			require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
			die();
		}
		$data = array(
			'NAME' => $highBlockName,
			'TABLE_NAME' => CIBlockPropertyDirectory::createHighloadTableName($_POST['HLB_NEW_TITLE'])
		);
		if ($data['TABLE_NAME'] === false)
		{
			require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
			CAdminMessage::ShowOldStyleError(GetMessage("BT_ADM_IEP_HBLOCK_NAME_IS_ABSENT"));
			require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
			die();
		}

		$result = Bitrix\Highloadblock\HighloadBlockTable::add($data);
		if (!$result->isSuccess())
		{
			require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
			CAdminMessage::ShowOldStyleError(implode('; ',$result->getErrorMessages()));
			require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
			die();
		}

		$highBlockID = $result->getId();
		$_POST["PROPERTY_USER_TYPE_SETTINGS"]["TABLE_NAME"] = $data['TABLE_NAME'];
		$arFieldsName = $_POST['PROPERTY_DIRECTORY_VALUES'][0];
		$arFieldsName['UF_DEF'] = '';
		$arFieldsName['UF_FILE'] = '';
		$obUserField = new CUserTypeEntity();
		$intSortStep = 100;
		foreach($arFieldsName as $fieldName => $fieldValue)
		{
			if ('UF_DELETE' == $fieldName)
				continue;

			$fieldMandatory = 'N';
			switch($fieldName)
			{
				case 'UF_NAME':
				case 'UF_XML_ID':
					$fieldType = 'string';
					$fieldMandatory = 'Y';
					break;
				case 'UF_LINK':
				case 'UF_DESCRIPTION':
				case 'UF_FULL_DESCRIPTION':
					$fieldType = 'string';
					break;
				case 'UF_SORT':
					$fieldType = 'integer';
					break;
				case 'UF_FILE':
					$fieldType = 'file';
					break;
				case 'UF_DEF':
					$fieldType = 'boolean';
					break;
				default:
					$fieldType = 'string';
			}
			$arUserField = array(
				"ENTITY_ID" => "HLBLOCK_".$highBlockID,
				"FIELD_NAME" => $fieldName,
				"USER_TYPE_ID" => $fieldType,
				"XML_ID" => "",
				"SORT" => $intSortStep,
				"MULTIPLE" => "N",
				"MANDATORY" => $fieldMandatory,
				"SHOW_FILTER" => "N",
				"SHOW_IN_LIST" => "Y",
				"EDIT_IN_LIST" => "Y",
				"IS_SEARCHABLE" => "N",
				"SETTINGS" => array(),
			);
			if(isset($_POST['PROPERTY_USER_TYPE_SETTINGS']['LANG'][$fieldName]))
			{
				$arUserField["EDIT_FORM_LABEL"] = $arUserField["LIST_COLUMN_LABEL"] = $arUserField["LIST_FILTER_LABEL"] = array(LANGUAGE_ID => $_POST['PROPERTY_USER_TYPE_SETTINGS']['LANG'][$fieldName]);
			}
			$obUserField->Add($arUserField);
			$intSortStep += 100;
		}
	}
	$arImageResult = array();
	if(isset($_FILES['PROPERTY_DIRECTORY_VALUES']) && is_array($_FILES['PROPERTY_DIRECTORY_VALUES']))
		CFile::ConvertFilesToPost($_FILES['PROPERTY_DIRECTORY_VALUES'], $arImageResult);
	if($_POST["PROPERTY_USER_TYPE_SETTINGS"]["TABLE_NAME"] == '-1' && isset($result) && $result->isSuccess())
	{
		$hlblock = Bitrix\Highloadblock\HighloadBlockTable::getById($highBlockID)->fetch();
	}
	else
	{
		$hlblock = Bitrix\Highloadblock\HighloadBlockTable::getList(array('filter' => array('=TABLE_NAME' => $_POST['PROPERTY_USER_TYPE_SETTINGS']['TABLE_NAME'])))->fetch();
	}

	$entity = Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock);
	$entityDataClass = $entity->getDataClass();
	$fieldsList = $entityDataClass::getMap();
	if (count($fieldsList) == 1 && isset($fieldsList['ID']))
	{
		$fieldsList = $entityDataClass::getEntity()->getFields();
	}

	foreach($_POST['PROPERTY_DIRECTORY_VALUES'] as $dirKey => $arDirValue)
	{
		if(isset($arDirValue["UF_DELETE"]))
		{
			if($arDirValue["UF_DELETE"] === 'Y')
				if(isset($arDirValue["ID"]) && intval($arDirValue["ID"]) > 0)
				{
					$entityDataClass::delete($arDirValue["ID"]);
					continue;
				}
			unset($arDirValue["UF_DELETE"]);
		}
		if(!is_array($arDirValue) || !isset($arDirValue['UF_NAME']) || '' == trim($arDirValue['UF_NAME']))
			continue;
		if((isset($arImageResult[$dirKey]["FILE"]) && is_array($arImageResult[$dirKey]["FILE"]) && $arImageResult[$dirKey]["FILE"]['name'] != '') || (isset($_POST['PROPERTY_DIRECTORY_VALUES_del'][$dirKey]["FILE"]) && $_POST['PROPERTY_DIRECTORY_VALUES_del'][$dirKey]["FILE"] == 'Y'))
			$arDirValue['UF_FILE'] = $arImageResult[$dirKey]["FILE"];

		if($arDirValue["ID"] == $_POST['PROPERTY_VALUES_DEF'])
			$arDirValue['UF_DEF'] = true;
		else
			$arDirValue['UF_DEF'] = false;
		if(!isset($arDirValue["UF_XML_ID"]) || $arDirValue["UF_XML_ID"] == '')
			$arDirValue['UF_XML_ID'] = randString(8);

		if ($_POST["PROPERTY_USER_TYPE_SETTINGS"]["TABLE_NAME"] == '-1' && isset($result) && $result->isSuccess())
		{
			$entityDataClass::add($arDirValue);
		}
		else
		{
			if (isset($arDirValue["ID"]) && $arDirValue["ID"] > 0)
			{
				$rsData = $entityDataClass::getList(array());
				while($arData = $rsData->fetch())
				{
					$arAddField = array();
					if(!isset($arData["UF_DESCRIPTION"]))
					{
						$arAddField[] = 'UF_DESCRIPTION';
					}
					if(!isset($arData["UF_FULL_DESCRIPTION"]))
					{
						$arAddField[] = 'UF_FULL_DESCRIPTION';
					}
					$obUserField = new CUserTypeEntity();
					foreach($arAddField as $addField)
					{
						$arUserField = array(
							"ENTITY_ID" => "HLBLOCK_".$hlblock["ID"],
							"FIELD_NAME" => $addField,
							"USER_TYPE_ID" => 'string',
							"XML_ID" => "",
							"SORT" => 100,
							"MULTIPLE" => "N",
							"MANDATORY" => "N",
							"SHOW_FILTER" => "N",
							"SHOW_IN_LIST" => "Y",
							"EDIT_IN_LIST" => "Y",
							"IS_SEARCHABLE" => "N",
							"SETTINGS" => array(),
						);
						if(isset($_POST['PROPERTY_USER_TYPE_SETTINGS']['LANG'][$addField]))
						{
							$arUserField["EDIT_FORM_LABEL"] = $arUserField["LIST_COLUMN_LABEL"] = $arUserField["LIST_FILTER_LABEL"] = array(LANGUAGE_ID => $_POST['PROPERTY_USER_TYPE_SETTINGS']['LANG'][$addField]);
						}
						$obUserField->Add($arUserField);
					}
					if($arDirValue["ID"] == $arData["ID"])
					{
						unset($arDirValue["ID"]);
						$dirValueKeys = array_keys($arDirValue);
						foreach ($dirValueKeys as $oneKey)
						{
							if (!isset($fieldsList[$oneKey]))
								unset($arDirValue[$oneKey]);
						}
						if (isset($oneKey))
							unset($oneKey);
						if (!empty($arDirValue))
						{
							$entityDataClass::update($arData["ID"], $arDirValue);
						}
					}
				}
			}
			else
			{
				if (array_key_exists("ID", $arDirValue))
					unset($arDirValue["ID"]);
				$dirValueKeys = array_keys($arDirValue);
				foreach ($dirValueKeys as $oneKey)
				{
					if (!isset($fieldsList[$oneKey]))
						unset($arDirValue[$oneKey]);
				}
				if (isset($oneKey))
					unset($oneKey);
				if (!empty($arDirValue))
				{
					$entityDataClass::add($arDirValue);
				}
			}
		}
	}
}
if (isset($_POST['PROPERTY_VALUES']) && is_array($_POST['PROPERTY_VALUES']))
{
	$boolDefCheck = false;
	if ('Y' == $_POST['PROPERTY_MULTIPLE'])
	{
		$boolDefCheck = (isset($_POST['PROPERTY_VALUES_DEF']) && is_array($_POST['PROPERTY_VALUES_DEF']));
	}
	else
	{
		$boolDefCheck = isset($_POST['PROPERTY_VALUES_DEF']);
	}
	$intNewKey = 0;
	foreach ($_POST['PROPERTY_VALUES'] as $key => $arValue)
	{
		if (!is_array($arValue) || !isset($arValue['VALUE']) || '' == trim($arValue['VALUE']))
			continue;
		$arListValues[(0 < intval($key) ? $key : 'n'.$intNewKey)] = array(
			'ID' => (0 < intval($key) ? $key : 'n'.$intNewKey),
			'VALUE' => strval($arValue['VALUE']),
			'XML_ID' => (isset($arValue['XML_ID']) ? strval($arValue['XML_ID']) : ''),
			'SORT' => (isset($arValue['SORT']) ? intval($arValue['SORT']) : 500),
			'DEF' => ($boolDefCheck ?
						('Y' == $_POST['PROPERTY_MULTIPLE'] ?
							(in_array($key, $_POST['PROPERTY_VALUES_DEF']) ? 'Y' : 'N') :
							($key == $_POST['PROPERTY_VALUES_DEF'] ? 'Y' : 'N')) :
						'N'),
		);
		if (0 >= intval($key))
			$intNewKey++;
	}
}

if (1 != preg_match('/^n\d+$/',$str_PROPERTY_ID))
{
	$str_PROPERTY_IDCheck = intval($str_PROPERTY_ID);
	if (0 == $intIBlockID || ($str_PROPERTY_IDCheck.'|' != $str_PROPERTY_ID.'|') || 0 >= $str_PROPERTY_IDCheck)
	{
		require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
		ShowError(GetMessage("BT_ADM_IEP_PROPERTY_ID_IS_ABSENT"));
		require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
		die();
	}
	else
	{
		$str_PROPERTY_ID = $str_PROPERTY_IDCheck;
		unset($str_PROPERTY_IDCheck);
		$rsProps = CIBlockProperty::GetByID($str_PROPERTY_ID, $intIBlockID);
		if (!($arPropCheck = $rsProps->Fetch()))
		{
			require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
			ShowError(str_replace('#ID#',$str_PROPERTY_ID,GetMessage("BT_ADM_IEP_PROPERTY_IS_NOT_EXISTS")));
			require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
			die();
		}
	}
}

$bVarsFromForm = $bReload;
$message = false;
$strWarning = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["checkAction"]) && $_POST["checkAction"] === "delete")
{
	CIBlockProperty::Delete($str_PROPERTY_ID);

	if ($adminSidePanelHelper->isSidePanelRequest())
	{
		$adminSidePanelHelper->sendJsonSuccessResponse();
	}
	else
	{
		if (strlen($return_url) > 0)
		{
			$adminSidePanelHelper->localRedirect($return_url);
			LocalRedirect($return_url);
		}
		else
		{
			$adminSidePanelHelper->localRedirect($listUrl);
			LocalRedirect($listUrl);
		}
	}
}
elseif(!$bReload && $_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST["save"]) || isset($_POST["apply"])))
{
	$arFields = array(
		"ACTIVE" => $_POST["PROPERTY_ACTIVE"],
		"IBLOCK_ID" => $_POST["IBLOCK_ID"],
		"LINK_IBLOCK_ID" => $_POST["PROPERTY_LINK_IBLOCK_ID"],
		"NAME" => $_POST["PROPERTY_NAME"],
		"SORT" => $_POST["PROPERTY_SORT"],
		"CODE" => $_POST["PROPERTY_CODE"],
		"MULTIPLE" => $_POST["PROPERTY_MULTIPLE"],
		"IS_REQUIRED" => $_POST["PROPERTY_IS_REQUIRED"],
		"SEARCHABLE" => $_POST["PROPERTY_SEARCHABLE"],
		"FILTRABLE" => $_POST["PROPERTY_FILTRABLE"],
		"WITH_DESCRIPTION" => $_POST["PROPERTY_WITH_DESCRIPTION"],
		"MULTIPLE_CNT" => $_POST["PROPERTY_MULTIPLE_CNT"],
		"HINT" => $_POST["PROPERTY_HINT"],
		"ROW_COUNT" => $_POST["PROPERTY_ROW_COUNT"],
		"COL_COUNT" => $_POST["PROPERTY_COL_COUNT"],
		"DEFAULT_VALUE" => $_POST["PROPERTY_DEFAULT_VALUE"],
		"LIST_TYPE" => $_POST["PROPERTY_LIST_TYPE"],
		"USER_TYPE_SETTINGS" => $_POST["PROPERTY_USER_TYPE_SETTINGS"],
		"FILE_TYPE" => $_POST["PROPERTY_FILE_TYPE"],
	);

	if(isset($_POST["PROPERTY_SECTION_PROPERTY"]))
	{
		$arFields["SECTION_PROPERTY"] = $_POST["PROPERTY_SECTION_PROPERTY"];
		if(isset($_POST["PROPERTY_SMART_FILTER"]))
			$arFields["SMART_FILTER"] = $_POST["PROPERTY_SMART_FILTER"];
		if(isset($_POST["PROPERTY_DISPLAY_TYPE"]))
			$arFields["DISPLAY_TYPE"] = $_POST["PROPERTY_DISPLAY_TYPE"];
		if(isset($_POST["PROPERTY_DISPLAY_EXPANDED"]))
			$arFields["DISPLAY_EXPANDED"] = $_POST["PROPERTY_DISPLAY_EXPANDED"];
		if(isset($_POST["PROPERTY_FILTER_HINT"]))
			$arFields["FILTER_HINT"] = $_POST["PROPERTY_FILTER_HINT"];
	}
	elseif($bSectionPopup)
	{
		$arFields["SECTION_PROPERTY"] = "N";
	}

	if (isset($_POST["PROPERTY_PROPERTY_TYPE"]))
	{
		if (strpos($_POST["PROPERTY_PROPERTY_TYPE"], ":"))
		{
			list($arFields["PROPERTY_TYPE"], $arFields["USER_TYPE"]) = explode(':', $_POST["PROPERTY_PROPERTY_TYPE"], 2);
			if ($arFields["USER_TYPE"] != "")
			{
				$userType = CIBlockProperty::GetUserType($arFields['USER_TYPE']);
				if (empty($userType))
					$arFields["USER_TYPE"] = "";
				unset($userType);
			}
		}
		else
		{
			$arFields["PROPERTY_TYPE"] = $_POST["PROPERTY_PROPERTY_TYPE"];
			$arFields["USER_TYPE"] = "";
		}
	}

	if(!empty($arListValues))
		$arFields["VALUES"] = $arListValues;

	if (COption::GetOptionString("iblock", "show_xml_id", "N")=="Y")
		$arFields["XML_ID"] = $_POST["PROPERTY_XML_ID"];

	if (CIBlock::GetArrayByID($arFields["IBLOCK_ID"], "SECTION_PROPERTY") != "Y")
	{
		if($arFields["SECTION_PROPERTY"] === "N" || $arFields["SMART_FILTER"] === "Y")
		{
			$ib = new CIBlock;
			$ib->Update($arFields["IBLOCK_ID"], array("SECTION_PROPERTY" => "Y"));
		}
	}

	if (isset($arFields['CODE']) && is_string($arFields['CODE']) && $arFields['CODE'] !== '')
	{
		$propertyFilter = array(
			'=IBLOCK_ID' => $arFields["IBLOCK_ID"],
			'=CODE' => $arFields['CODE']
		);
		if ($str_PROPERTY_ID > 0)
			$propertyFilter['!=ID'] = $str_PROPERTY_ID;
		$existProperty = Iblock\PropertyTable::getList(array(
			'select' => array('ID'),
			'filter' => $propertyFilter
		))->fetch();
		if (!empty($existProperty))
		{
			$strWarning .= GetMessage(
				'BT_ADM_IEP_ERR_CODE_ALREADY_EXIST',
				array('#CODE#' => $arFields['CODE'])
			);
			$bVarsFromForm = true;
		}
		unset($propertyFilter);
	}

	if ($strWarning == '')
	{
		$ibp = new CIBlockProperty;
		if ($str_PROPERTY_ID > 0)
		{
			$res = $ibp->Update($str_PROPERTY_ID, $arFields, true);
		}
		else
		{
			$str_PROPERTY_ID = $ibp->Add($arFields);
			$res = ($str_PROPERTY_ID > 0);
			if (!$res)
				$str_PROPERTY_ID = 'n0';
		}
		if(!$res)
		{
			$strWarning .= $ibp->LAST_ERROR;
			$bVarsFromForm = true;
			if($e = $APPLICATION->GetException())
				$message = new CAdminMessage(GetMessage("admin_lib_error"), $e);
		}
	}
	if ($strWarning == '')
	{
		if(strlen($apply)<=0)
		{
			if($bSectionPopup)
			{
				$type = $propertyBaseTypes[Iblock\PropertyTable::TYPE_STRING];
				if ($arFields['USER_TYPE'] != "")
				{
					$userType = CIBlockProperty::GetUserType($arFields['USER_TYPE']);
					$type = $userType["DESCRIPTION"];
					unset($userType);
				}
				elseif (isset($propertyBaseTypes[$arFields['PROPERTY_TYPE']]))
				{
					$type = $propertyBaseTypes[$arFields['PROPERTY_TYPE']];
				}
				$type = htmlspecialcharsbx($type);

				echo '<script type="text/javascript">
					var currentWindow = top.window;
					if (top.BX.SidePanel.Instance && top.BX.SidePanel.Instance.getTopSlider())
					{
						currentWindow = top.BX.SidePanel.Instance.getTopSlider().getWindow();
					}
					currentWindow.createSectionProperty(
						'.intval($str_PROPERTY_ID).',
						"'.CUtil::JSEscape($arFields["NAME"]).'",
						"'.CUtil::JSEscape($type).'",
						'.intval($arFields["SORT"]).',
						"'.CUtil::JSEscape($arFields['PROPERTY_TYPE']).'",
						"'.CUtil::JSEscape($arFields['USER_TYPE']).'",
						"'.CUtil::JSEscape($arFields['CODE']).'",
						""
					);
					currentWindow.BX.closeWait();
					currentWindow.BX.WindowManager.Get().AllowClose();
					currentWindow.BX.WindowManager.Get().Close();
					</script>';
				die();
			}

			if ($adminSidePanelHelper->isAjaxRequest())
			{
				$adminSidePanelHelper->sendSuccessResponse("base", array("ID" => intval($str_PROPERTY_ID)));
			}

			if(strlen($return_url) > 0)
			{
				$adminSidePanelHelper->localRedirect($return_url);
				LocalRedirect($return_url);
			}
			else
			{
				$adminSidePanelHelper->localRedirect($listUrl);
				LocalRedirect($listUrl);
			}
		}
		if ($adminSidePanelHelper->isAjaxRequest())
		{
			$adminSidePanelHelper->sendSuccessResponse("base", array("ID" => intval($str_PROPERTY_ID)));
		}
		$applyUrl = $selfFolderUrl."iblock_edit_property.php?lang=".LANGUAGE_ID."&IBLOCK_ID=".$intIBlockID.
			"&find_section_section=".intval($find_section_section).'&ID='.intval($str_PROPERTY_ID).
			(strlen($return_url)>0?"&return_url=".UrlEncode($return_url):"").($_REQUEST["admin"]=="Y"? "&admin=Y": "&admin=N");
		$applyUrl = $adminSidePanelHelper->setDefaultQueryParams($applyUrl);
		LocalRedirect($applyUrl);
	}
	else
	{
		if (empty($_REQUEST["bxpublic"]))
		{
			$adminSidePanelHelper->sendJsonErrorResponse($strWarning);
		}
	}
}

$strReceiver = '';

if (isset($_REQUEST["PARAMS"]['RECEIVER']))
	$strReceiver = preg_replace("/[^a-zA-Z0-9_:]/", "", htmlspecialcharsbx(($_REQUEST["PARAMS"]['RECEIVER'])));

if (isset($_REQUEST['saveresult']))
{
	require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_js.php");

	unset($_POST['saveresult']);
	$PARAMS = $_POST['PARAMS'];
	unset($_POST['PARAMS']);

	$arProperty = array();

	$arFieldsList = $DB->GetTableFieldsList("b_iblock_property");
	foreach ($arFieldsList as $strFieldName)
	{
		if (!in_array($strFieldName,$arDisabledPropFields))
		{
			if (isset($_POST['PROPERTY_'.$strFieldName]))
			{
				$arProperty[$strFieldName] = $_POST['PROPERTY_'.$strFieldName];
			}
			else
			{
				$arProperty[$strFieldName] = $arDefPropInfo[$strFieldName];
			}
		}
	}
	unset($strFieldName);
	unset($arFieldsList);

	if (isset($_POST['PROPERTY_SECTION_PROPERTY']))
		$arProperty['SECTION_PROPERTY'] = $_POST['PROPERTY_SECTION_PROPERTY'];
	else
		$arProperty['SECTION_PROPERTY'] = $arDefPropInfo['SECTION_PROPERTY'];

	if (isset($_POST['PROPERTY_SMART_FILTER']))
		$arProperty['SMART_FILTER'] = $_POST['PROPERTY_SMART_FILTER'];
	else
		$arProperty['SMART_FILTER'] = $arDefPropInfo['SMART_FILTER'];

	if (isset($_POST['PROPERTY_DISPLAY_TYPE']))
		$arProperty['DISPLAY_TYPE'] = $_POST['PROPERTY_DISPLAY_TYPE'];
	else
		$arProperty['DISPLAY_TYPE'] = $arDefPropInfo['DISPLAY_TYPE'];

	if (isset($_POST['PROPERTY_DISPLAY_EXPANDED']))
		$arProperty['DISPLAY_EXPANDED'] = $_POST['PROPERTY_DISPLAY_EXPANDED'];
	else
		$arProperty['DISPLAY_EXPANDED'] = $arDefPropInfo['DISPLAY_EXPANDED'];

	if (isset($_POST['PROPERTY_FILTER_HINT']))
		$arProperty['FILTER_HINT'] = $_POST['PROPERTY_FILTER_HINT'];
	else
		$arProperty['FILTER_HINT'] = $arDefPropInfo['FILTER_HINT'];

	$arProperty['MULTIPLE'] = ('Y' == $arProperty['MULTIPLE'] ? 'Y' : 'N');
	$arProperty['IS_REQUIRED'] = ('Y' == $arProperty['IS_REQUIRED'] ? 'Y' : 'N');
	$arProperty['FILTRABLE'] = ('Y' == $arProperty['FILTRABLE'] ? 'Y' : 'N');
	$arProperty['SEARCHABLE'] = ('Y' == $arProperty['SEARCHABLE'] ? 'Y' : 'N');
	$arProperty['ACTIVE'] = ('Y' == $arProperty['ACTIVE'] ? 'Y' : 'N');
	$arProperty['SECTION_PROPERTY'] = ('N' == $arProperty['SECTION_PROPERTY'] ? 'N' : 'Y');
	$arProperty['SMART_FILTER'] = ('Y' == $arProperty['SMART_FILTER'] ? 'Y' : 'N');
	$arProperty['DISPLAY_TYPE'] = substr($arProperty['DISPLAY_TYPE'], 0, 1);
	$arProperty['DISPLAY_EXPANDED'] = ('Y' == $arProperty['DISPLAY_EXPANDED'] ? 'Y' : 'N');
	$arProperty['MULTIPLE_CNT'] = (int)$arProperty['MULTIPLE_CNT'];
	if ($arProperty['MULTIPLE_CNT'] <= 0)
		$arProperty['MULTIPLE_CNT'] = Iblock\PropertyTable::DEFAULT_MULTIPLE_CNT;
	$arProperty['WITH_DESCRIPTION'] = ($arProperty['WITH_DESCRIPTION'] == 'Y' ? 'Y' : 'N');

	if(!empty($arListValues))
		$arProperty["VALUES"] = $arListValues;

	$arHidden = array();
	foreach ($arHiddenPropFields as &$strPropField)
	{
		if (isset($arProperty[$strPropField]))
		{
			$arHidden[$strPropField] = $arProperty[$strPropField];
			unset($arProperty[$strPropField]);
		}
	}
	$arProperty['PROPINFO'] = base64_encode(serialize($arHidden));

	$strResult = CUtil::PhpToJSObject($arProperty);
	?><script type="text/javascript">
	var currentWindow = top.window;
	if (top.BX.SidePanel.Instance && top.BX.SidePanel.Instance.getTopSlider())
	{
		currentWindow = top.BX.SidePanel.Instance.getTopSlider().getWindow();
	}
	arResult = <? echo $strResult; ?>;
	if (currentWindow.<? echo $strReceiver; ?>)
	{
		currentWindow.<? echo $strReceiver; ?>.SetPropInfo('<?=CUtil::JSEscape($PARAMS['ID']); ?>', arResult, '<? echo bitrix_sessid(); ?>');
	}
	currentWindow.BX.closeWait(); currentWindow.BX.WindowManager.Get().AllowClose(); currentWindow.BX.WindowManager.Get().Close();
	</script><?
	require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin_js.php");
	die();
}

$aTabs = array();
$tabControl = null;

if(!$bFullForm)
{
	$arProperty = array();
	$PROPERTY = $_POST['PROP'];
	$PARAMS = $_POST['PARAMS'];

	if ((isset($PARAMS['TITLE'])) && ('' != $PARAMS['TITLE']))
	{
		$APPLICATION->SetTitle($PARAMS['TITLE']);
	}

	$arFieldsList = $DB->GetTableFieldsList("b_iblock_property");
	foreach ($arFieldsList as $strFieldName)
	{
		if (!in_array($strFieldName,$arDisabledPropFields))
			$arProperty[$strFieldName] = (isset($PROPERTY[$strFieldName]) ? htmlspecialcharsback($PROPERTY[$strFieldName]) : '');
	}
	$arProperty['PROPINFO'] = $PROPERTY['PROPINFO'];
	$arProperty['PROPINFO'] = base64_decode($arProperty['PROPINFO']);
	if (CheckSerializedData($arProperty['PROPINFO']))
	{
		$arTempo = unserialize($arProperty['PROPINFO']);
		if (is_array($arTempo))
		{
			foreach ($arTempo as $k => $v)
				$arProperty[$k] = $v;
		}
		unset($arTempo);
		unset($arProperty['PROPINFO']);
	}

	$arProperty['MULTIPLE'] = ('Y' == $arProperty['MULTIPLE'] ? 'Y' : 'N');
	$arProperty['IS_REQUIRED'] = ('Y' == $arProperty['IS_REQUIRED'] ? 'Y' : 'N');
	$arProperty['FILTRABLE'] = ('Y' == $arProperty['FILTRABLE'] ? 'Y' : 'N');
	$arProperty['SEARCHABLE'] = ('Y' == $arProperty['SEARCHABLE'] ? 'Y' : 'N');
	$arProperty['ACTIVE'] = ('Y' == $arProperty['ACTIVE'] ? 'Y' : 'N');
	$arProperty['SECTION_PROPERTY'] = ('N' == $arProperty['SECTION_PROPERTY'] ? 'N' : 'Y');
	$arProperty['SMART_FILTER'] = ('Y' == $arProperty['SMART_FILTER'] ? 'Y' : 'N');
	$arProperty['MULTIPLE_CNT'] = (int)$arProperty['MULTIPLE_CNT'];
	if ($arProperty['MULTIPLE_CNT'] <= 0)
		$arProperty['MULTIPLE_CNT'] = Iblock\PropertyTable::DEFAULT_MULTIPLE_CNT;
	$arProperty['WITH_DESCRIPTION'] = ($arProperty['WITH_DESCRIPTION'] == 'Y' ? 'Y' : 'N');

	$arProperty['USER_TYPE'] = '';
	if (false !== strpos($arProperty['PROPERTY_TYPE'],':'))
	{
		list($arProperty['PROPERTY_TYPE'],$arProperty['USER_TYPE']) = explode(':', $arProperty['PROPERTY_TYPE'], 2);
	}

	$arProperty["ID"] = $PARAMS['ID'];
	$arProperty['IBLOCK_ID'] = $intIBlockID;

	if ($arProperty["SMART_FILTER"] == "Y")
	{
		$arPropLink = CIBlockSectionPropertyLink::GetArray($intIBlockID, 0);
		if(isset($arPropLink[$arProperty["ID"]]))
		{
			$arProperty["SECTION_PROPERTY"] = "Y";
			$arProperty["SMART_FILTER"] = ($arPropLink[$arProperty["ID"]]["SMART_FILTER"] == 'Y' ? 'Y' : 'N');
			$arProperty["DISPLAY_TYPE"] = $arPropLink[$arProperty["ID"]]["DISPLAY_TYPE"];
			$arProperty["DISPLAY_EXPANDED"] = ($arPropLink[$arProperty["ID"]]["DISPLAY_EXPANDED"] == 'Y' ? 'Y' : 'N');
			$arProperty["FILTER_HINT"] = $arPropLink[$arProperty["ID"]]["FILTER_HINT"];
		}
		else
		{
			$arProperty["SECTION_PROPERTY"] = "N";
			$arProperty["SMART_FILTER"] = "N";
			$arProperty["DISPLAY_TYPE"] = "";
			$arProperty["DISPLAY_EXPANDED"] = "N";
			$arProperty["FILTER_HINT"] = "";
		}
	}
}
else
{
	if($bVarsFromForm)
	{
		$arProperty = array(
			"ID" => $str_PROPERTY_ID,
			"ACTIVE" => $_POST["PROPERTY_ACTIVE"],
			"IBLOCK_ID" => $_POST["IBLOCK_ID"],
			"NAME" => $_POST["PROPERTY_NAME"],
			"SORT" => $_POST["PROPERTY_SORT"],
			"CODE" => $_POST["PROPERTY_CODE"],
			"MULTIPLE" => $_POST["PROPERTY_MULTIPLE"],
			"IS_REQUIRED" => $_POST["PROPERTY_IS_REQUIRED"],
			"SEARCHABLE" => $_POST["PROPERTY_SEARCHABLE"],
			"FILTRABLE" => $_POST["PROPERTY_FILTRABLE"],
			"WITH_DESCRIPTION" => $_POST["PROPERTY_WITH_DESCRIPTION"],
			"MULTIPLE_CNT" => $_POST["PROPERTY_MULTIPLE_CNT"],
			"HINT" => $_POST["PROPERTY_HINT"],
			"SECTION_PROPERTY" => $_POST["PROPERTY_SECTION_PROPERTY"],
			"SMART_FILTER" => $_POST["PROPERTY_SMART_FILTER"],
			"DISPLAY_TYPE" => $_POST["PROPERTY_DISPLAY_TYPE"],
			"DISPLAY_EXPANDED" => $_POST["PROPERTY_DISPLAY_EXPANDED"],
			"FILTER_HINT" => $_POST["PROPERTY_FILTER_HINT"],
			"ROW_COUNT" => $_POST["PROPERTY_ROW_COUNT"],
			"COL_COUNT" => $_POST["PROPERTY_COL_COUNT"],
			"DEFAULT_VALUE" => $_POST["PROPERTY_DEFAULT_VALUE"],
			"FILE_TYPE" => $_POST["PROPERTY_FILE_TYPE"],
		);

		if (isset($_POST["PROPERTY_PROPERTY_TYPE"]))
		{
			if (strpos($_POST["PROPERTY_PROPERTY_TYPE"], ":"))
			{
				list($arProperty["PROPERTY_TYPE"], $arProperty["USER_TYPE"]) = explode(':', $_POST["PROPERTY_PROPERTY_TYPE"], 2);
			}
			else
			{
				$arProperty["PROPERTY_TYPE"] = $_POST["PROPERTY_PROPERTY_TYPE"];
			}
		}

		if(!empty($arListValues))
			$arProperty["VALUES"] = $arListValues;
	}
	elseif(is_array($arPropCheck))
	{
		$arProperty = $arPropCheck;
		if ($arProperty['PROPERTY_TYPE'] == "L")
		{
			$arProperty['VALUES'] = array();
			$rsLists = CIBlockProperty::GetPropertyEnum($arProperty['ID'],array('SORT' => 'ASC','ID' => 'ASC'));
			while($res = $rsLists->Fetch())
			{
				$arProperty['VALUES'][$res["ID"]] = array(
					'ID' => $res["ID"],
					'VALUE' => $res["VALUE"],
					'SORT' => $res['SORT'],
					'XML_ID' => $res["XML_ID"],
					'DEF' => $res['DEF'],
				);
			}
		}
		$arPropLink = CIBlockSectionPropertyLink::GetArray($intIBlockID, 0);
		if(isset($arPropLink[$arProperty["ID"]]))
		{
			$arProperty["SECTION_PROPERTY"] = "Y";
			$arProperty["SMART_FILTER"] = ($arPropLink[$arProperty["ID"]]["SMART_FILTER"] == 'Y' ? 'Y' : 'N');
			$arProperty["DISPLAY_TYPE"] = $arPropLink[$arProperty["ID"]]["DISPLAY_TYPE"];
			$arProperty["DISPLAY_EXPANDED"] = ($arPropLink[$arProperty["ID"]]["DISPLAY_EXPANDED"] == 'Y' ? 'Y' : 'N');
			$arProperty["FILTER_HINT"] = $arPropLink[$arProperty["ID"]]["FILTER_HINT"];
		}
		else
		{
			$arProperty["SECTION_PROPERTY"] = "N";
			$arProperty["SMART_FILTER"] = "N";
			$arProperty["DISPLAY_TYPE"] = "";
			$arProperty["DISPLAY_EXPANDED"] = "N";
			$arProperty["FILTER_HINT"] = "";
		}
	}
	else
	{
		$arProperty = $arDefPropInfo;
		$arProperty["IBLOCK_ID"] = $intIBlockID;
	}

	if (!$bSectionPopup)
	{
		$aTabs = array(
			array(
				"DIV" => "edit1",
				"TAB" => GetMessage("BT_ADM_IEP_TAB"),
				"ICON" => "iblock",
				"TITLE" => GetMessage("BT_ADM_IEP_TAB_TITLE"),
			),
		);

		$tabControl = new CAdminTabControl("tabControl", $aTabs);

		if($ID > 0)
			$APPLICATION->SetTitle(GetMessage("BT_ADM_IEP_PROPERTY_EDIT", array("#NAME#" => htmlspecialcharsbx($arProperty["NAME"]))));
		else
			$APPLICATION->SetTitle(GetMessage("BT_ADM_IEP_PROPERTY_NEW"));
	}
}

	require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

	if ('L' == $arProperty['PROPERTY_TYPE'])
		$arDefPropInfo['MULTIPLE'] = $arProperty['MULTIPLE'];

	$aMenu = array(
		array(
			"TEXT" => GetMessage("BT_ADM_IEP_LIST") ,
			"LINK" => $listUrl,
			"ICON" => "btn_list",
		),
	);

	if($str_PROPERTY_ID > 0)
	{
		$aMenu[] = array("SEPARATOR"=>"Y");
		$aMenu[] = array(
			"TEXT" => GetMessage("BT_ADM_IEP_DELETE") ,
			"LINK"=>"javascript:jsDelete('frm_prop', '".GetMessage("BT_ADM_IEP_CONFIRM_DEL_MESSAGE")."')",
			"ICON"=>"btn_delete",
		);
	}

	if(!$bReload)
	{
		$context = new CAdminContextMenu($aMenu);
		$context->Show();
	}

	if($strWarning)
		CAdminMessage::ShowOldStyleError($strWarning."<br>");
	elseif($message)
		echo $message->Show();

	?>
	<script type="text/javascript">
	function jsDelete(form_id, message)
	{
		var _form = BX(form_id);
		var _flag = BX('checkAction');
		if(!!_form && !!_flag)
		{
			if(confirm(message))
			{
				_flag.value = 'delete';
				<? if ($adminSidePanelHelper->isSidePanelFrame()): ?>
					BX.ajax.submitAjax(_form, {
						method : 'POST',
						url: _form.getAttribute("action"),
						onsuccess: BX.delegate(function(result) {
							result = BX.parseJSON(result, {});
							if (result && result.status)
							{
								if (result.status === 'success')
								{
									top.BX.onCustomEvent('SidePanel:postMessage', [
										window, "save", {"listActions": ["destroy"]}]);
								}
								else if (result.status === 'error')
								{
									alert(result.message.replace(/<br>/gi, ''));
								}
							}
							else
							{
								alert('Wrong response format');
							}
						}, this)
					});
				<? else: ?>
					_form.submit();
				<? endif; ?>
			}
		}
	}
	function reloadForm()
	{
		var _form = BX('frm_prop');
		var _flag = BX('checkAction');
		if(!!_form && !!_flag)
		{
			_flag.value = 'reload';
			<?if($bSectionPopup):?>
				BX.WindowManager.Get().PostParameters();
			<?else:?>
				_form.submit();
			<?endif?>
		}
	}
	</script>
	<form method="POST" name="frm_prop" id="frm_prop" action="<?echo $APPLICATION->GetCurPageParam(); ?>" enctype="multipart/form-data">
	<div id="form_content">
	<input type="hidden" name="PROPERTY_FILE_TYPE" value="<?echo htmlspecialcharsbx($arProperty['FILE_TYPE']); ?>">
	<?echo bitrix_sessid_post();?>
	<?if($bSectionPopup):?>
		<input type="hidden" name="bxpublic" value="Y">
		<input type="hidden" name="save" value="Y">
	<?endif;?>
	<?if(is_object($tabControl) || $bSectionPopup):

		if(is_object($tabControl))
		{
			$tabControl->Begin();
			$tabControl->BeginNextTab();
		}
		?>
		<input type="hidden" name="ID" value="<?echo $str_PROPERTY_ID?>">
		<input type="hidden" name="IBLOCK_ID" value="<?echo $intIBlockID?>">
		<input type="hidden" name="checkAction" id="checkAction" value="">
		<?
		$arProperty['USER_TYPE'] = trim($arProperty['USER_TYPE']);
		$arUserType = ('' != $arProperty['USER_TYPE'] ? CIBlockProperty::GetUserType($arProperty['USER_TYPE']) : array());

		$arPropertyFields = array();
		$USER_TYPE_SETTINGS_HTML = "";
		if(isset($arUserType["GetSettingsHTML"]))
			$USER_TYPE_SETTINGS_HTML = call_user_func_array($arUserType["GetSettingsHTML"],
				array(
					$arProperty,
					array(
						"NAME"=>"PROPERTY_USER_TYPE_SETTINGS",
					),
					&$arPropertyFields,
				)
			);

		$PROPERTY_TYPE = $arProperty['PROPERTY_TYPE'].($arProperty['USER_TYPE']? ':'.$arProperty['USER_TYPE']: '');
		?><input type="hidden" id="PROPERTY_PROPERTY_TYPE" name="PROPERTY_PROPERTY_TYPE" value="<?echo htmlspecialcharsbx($PROPERTY_TYPE); ?>">
		<?if($bSectionPopup):?>
		<table class="edit-table" width="100%"><tbody>
		<?endif;?>
		<tr>
			<td width="40%">ID:</td>
			<td width="60%"><? echo (0 < intval($arProperty['ID']) ? $arProperty['ID'] : GetMessage("BT_ADM_IEP_PROP_NEW"))?></td>
		</tr>
		<tr>
			<td width="40%"><? echo GetMessage('BT_ADM_IEP_PROPERTY_TYPE'); ?></td>
			<td width="60%">
			<?
			$arUserTypeList = CIBlockProperty::GetUserType();
			\Bitrix\Main\Type\Collection::sortByColumn($arUserTypeList, array('DESCRIPTION' => SORT_STRING));
			$boolUserPropExist = !empty($arUserTypeList);
			?>
			<select name="PROPERTY_PROPERTY_TYPE" onchange="reloadForm();">
			<?
				if ($boolUserPropExist)
				{
					?><optgroup label="<? echo GetMessage('BT_ADM_IEP_PROPERTY_BASE_TYPE_GROUP'); ?>"><?
				}
				foreach ($propertyBaseTypes as $typeId => $typeTitle)
				{
					?><option value="<?=$typeId; ?>" <?=($PROPERTY_TYPE==$typeId ? ' selected' : '');?>><?=htmlspecialcharsbx($typeTitle); ?></option><?
				}
				unset($typeTitle);
				unset($typeId);
				if ($boolUserPropExist)
				{
				?></optgroup><optgroup label="<? echo GetMessage('BT_ADM_IEP_PROPERTY_USER_TYPE_GROUP'); ?>"><?
				}
				foreach($arUserTypeList as $ar)
				{
					?><option value="<?=htmlspecialcharsbx($ar["PROPERTY_TYPE"].":".$ar["USER_TYPE"])?>" <?if($PROPERTY_TYPE==$ar["PROPERTY_TYPE"].":".$ar["USER_TYPE"])echo " selected"?>><?=htmlspecialcharsbx($ar["DESCRIPTION"])?></option>
					<?
				}
				if ($boolUserPropExist)
				{
					?></optgroup><?
				}
				?>
			</select><?
			?></td>
		</tr>
	<?else:?>
		<input type="hidden" name="saveresult" value="Y">
		<input type="hidden" name="propedit" value="<? echo $str_PROPERTY_ID; ?>">
		<input type="hidden" name="receiver" value="<? echo $strReceiver; ?>">
		<?
		foreach ($PARAMS as $key => $value)
		{
			if ('TITLE' != $key)
			{
				?><input type="hidden" name="PARAMS[<? echo htmlspecialcharsbx($key); ?>]" value="<? echo htmlspecialcharsbx($value); ?>"><?
			}
		}
		?>
		<table class="edit-table" width="100%"><tbody><?
		$arProperty['USER_TYPE'] = trim($arProperty['USER_TYPE']);
		$arUserType = ('' != $arProperty['USER_TYPE'] ? CIBlockProperty::GetUserType($arProperty['USER_TYPE']) : array());

		$arPropertyFields = array();
		$USER_TYPE_SETTINGS_HTML = "";
		if(isset($arUserType["GetSettingsHTML"]))
			$USER_TYPE_SETTINGS_HTML = call_user_func_array($arUserType["GetSettingsHTML"],
				array(
					$arProperty,
					array(
						"NAME"=>"PROPERTY_USER_TYPE_SETTINGS",
					),
					&$arPropertyFields,
				)
			);
		?><input type="hidden" id="PROPERTY_PROPERTY_TYPE" name="PROPERTY_PROPERTY_TYPE" value="<?echo htmlspecialcharsbx($arProperty['PROPERTY_TYPE'].($arProperty['USER_TYPE']? ':'.$arProperty['USER_TYPE']: '')); ?>">
		<tr>
			<td width="40%">ID:</td>
			<td width="60%"><? echo (0 < intval($arProperty['ID']) ? $arProperty['ID'] : GetMessage("BT_ADM_IEP_PROP_NEW"))?></td>
		</tr>
		<tr>
			<td width="40%"><? echo GetMessage('BT_ADM_IEP_PROPERTY_TYPE'); ?></td>
			<td width="60%"><?
			$strDescr = '';
			if (isset($arUserType['DESCRIPTION']))
			{
				$strDescr = $arUserType['DESCRIPTION'];
			}
			elseif (isset($propertyBaseTypes[$arProperty['PROPERTY_TYPE']]))
			{
				$strDescr = $propertyBaseTypes[$arProperty['PROPERTY_TYPE']];
			}
			echo $strDescr;
			?></td>
		</tr>
	<?endif;
	$showKeyExist = isset($arPropertyFields["SHOW"]) && !empty($arPropertyFields["SHOW"]) && is_array($arPropertyFields["SHOW"]);
	$hideKeyExist = isset($arPropertyFields["HIDE"]) && !empty($arPropertyFields["HIDE"]) && is_array($arPropertyFields["HIDE"]);
	?>
<tr>
	<td width="40%"><label for="PROPERTY_ACTIVE_Y"><?echo GetMessage("BT_ADM_IEP_PROP_ACT")?></label></td>
	<td width="60%"><input type="hidden" id="PROPERTY_ACTIVE_N" name="PROPERTY_ACTIVE" value="N">
		<input type="checkbox" id="PROPERTY_ACTIVE_Y" name="PROPERTY_ACTIVE" value="Y"<?if ('Y' == $arProperty['ACTIVE']) echo ' checked="checked"'; ?>></td>
</tr>
<tr>
	<td width="40%"><?echo GetMessage("BT_ADM_IEP_PROP_SORT_DET")?></td>
	<td><input type="text" size="3" maxlength="10" id="PROPERTY_SORT" name="PROPERTY_SORT" value="<? echo intval($arProperty['SORT']); ?>"></td>
</tr>
<tr class="adm-detail-required-field">
	<td width="40%"><?echo GetMessage("BT_ADM_IEP_PROP_NAME_DET")?></td>
	<td ><input type="text" size="50" maxlength="255" id="PROPERTY_NAME" name="PROPERTY_NAME" value="<? echo htmlspecialcharsbx($arProperty['NAME']);?>"></td>
</tr>
<tr>
	<td width="40%"><?echo GetMessage("BT_ADM_IEP_PROP_CODE_DET")?></td>
	<td><input type="text" size="50" maxlength="50" id="PROPERTY_CODE" name="PROPERTY_CODE" value="<? echo htmlspecialcharsbx($arProperty['CODE'])?>"></td>
</tr>
<?
	if (COption::GetOptionString("iblock", "show_xml_id", "N")=="Y")
	{?><tr>
		<td width="40%"><?echo GetMessage("BT_ADM_IEP_PROP_EXTERNAL_CODE")?></td>
		<td><input type="text" size="50" maxlength="50" id="PROPERTY_XML_ID" name="PROPERTY_XML_ID" value="<? echo htmlspecialcharsbx($arProperty['XML_ID'])?>"></td>
		</tr><?
	}
	$bShow = true;
	if($showKeyExist && in_array("MULTIPLE", $arPropertyFields["SHOW"]))
		$bShow = true;
	elseif($hideKeyExist && in_array("MULTIPLE", $arPropertyFields["HIDE"]))
		$bShow = false;

	if ($bShow)
	{?><tr>
	<td width="40%"><label for="PROPERTY_MULTIPLE_Y"><?echo GetMessage("BT_ADM_IEP_PROP_MULTIPLE")?></label></td>
	<td>
		<input type="hidden" id="PROPERTY_MULTIPLE_N" name="PROPERTY_MULTIPLE" value="N">
		<input type="checkbox" id="PROPERTY_MULTIPLE_Y" name="PROPERTY_MULTIPLE" value="Y"<?if('Y' == $arProperty['MULTIPLE']) echo ' checked="checked"'?>>
	</td>
	</tr><?
	} elseif(
		isset($arPropertyFields["SET"]["MULTIPLE"])
	)
	{
		?><input type="hidden" id="PROPERTY_MULTIPLE_Y" name="PROPERTY_MULTIPLE" value="<?echo htmlspecialcharsbx($arPropertyFields["SET"]["MULTIPLE"])?>"><?
	}?>
<tr>
	<td width="40%"><label for="PROPERTY_IS_REQUIRED_Y"><?echo GetMessage("BT_ADM_IEP_PROP_IS_REQUIRED")?></label></td>
	<td>
		<input type="hidden" id="PROPERTY_IS_REQUIRED_N" name="PROPERTY_IS_REQUIRED" value="N">
		<input type="checkbox" id="PROPERTY_IS_REQUIRED_Y" name="PROPERTY_IS_REQUIRED" value="Y"<?if('Y' == $arProperty['IS_REQUIRED'])echo ' checked="checked"'?>>
	</td>
</tr>
<?
	$bShow = true;
	if($showKeyExist && in_array("SEARCHABLE", $arPropertyFields["SHOW"]))
		$bShow = true;
	elseif($hideKeyExist && in_array("SEARCHABLE", $arPropertyFields["HIDE"]))
		$bShow = false;
	elseif('E' == $arProperty['PROPERTY_TYPE'] || 'G' == $arProperty['PROPERTY_TYPE'])
		$bShow = false;

	if ($bShow)
	{
		?><tr>
		<td width="40%"><label for="PROPERTY_SEARCHABLE_Y"><?echo GetMessage("BT_ADM_IEP_PROP_SEARCHABLE")?></label></td>
		<td>
			<input type="hidden" id="PROPERTY_SEARCHABLE_N" name="PROPERTY_SEARCHABLE" value="N">
			<input type="checkbox" id="PROPERTY_SEARCHABLE_Y" name="PROPERTY_SEARCHABLE" value="Y" <?if('Y' == $arProperty['SEARCHABLE'])echo ' checked="checked"';?>>
		</td>
		</tr><?
	}
	elseif(
		isset($arPropertyFields["SET"]["SEARCHABLE"])
	)
	{
		?><input type="hidden" id="PROPERTY_SEARCHABLE_Y" name="PROPERTY_SEARCHABLE" value="<?echo htmlspecialcharsbx($arPropertyFields["SET"]["SEARCHABLE"])?>"><?
	}

	$bShow = true;
	if($showKeyExist && in_array("FILTRABLE", $arPropertyFields["SHOW"]))
		$bShow = true;
	elseif($hideKeyExist && in_array("FILTRABLE", $arPropertyFields["HIDE"]))
		$bShow = false;
	elseif($arProperty['PROPERTY_TYPE'] == 'F')
		$bShow = false;

	if ($bShow)
	{
		?><tr>
		<td width="40%"><label for="PROPERTY_FILTRABLE_Y"><?echo GetMessage("BT_ADM_IEP_PROP_FILTRABLE")?></label></td>
		<td>
			<input type="hidden" id="PROPERTY_FILTRABLE_N" name="PROPERTY_FILTRABLE" value="N">
			<input type="checkbox" id="PROPERTY_FILTRABLE_Y" name="PROPERTY_FILTRABLE" value="Y" <?if('Y' == $arProperty['FILTRABLE'])echo ' checked="checked"'?>>
		</td>
		</tr><?
	}
	elseif(
		isset($arPropertyFields["SET"]["FILTRABLE"])
	)
	{
		?>
		<input type="hidden" id="PROPERTY_FILTRABLE_Y" name="PROPERTY_FILTRABLE" value="<?echo htmlspecialcharsbx($arPropertyFields["SET"]["FILTRABLE"])?>">
		<?
	}

	$bShow = true;
	if ($showKeyExist && in_array("WITH_DESCRIPTION", $arPropertyFields["SHOW"]))
		$bShow = true;
	elseif ($hideKeyExist && in_array("WITH_DESCRIPTION", $arPropertyFields["HIDE"]))
		$bShow = false;
	elseif ('L' == $arProperty['PROPERTY_TYPE'] || 'G' == $arProperty['PROPERTY_TYPE'] || 'E' == $arProperty['PROPERTY_TYPE'])
		$bShow = false;

	if ($bShow)
	{
		?><tr>
		<td width="40%"><label for="PROPERTY_WITH_DESCRIPTION_Y"><?echo GetMessage("BT_ADM_IEP_PROP_WITH_DESC")?></label></td>
		<td>
			<input type="hidden" id="PROPERTY_WITH_DESCRIPTION_N" name="PROPERTY_WITH_DESCRIPTION" value="N">
			<input type="checkbox" id="PROPERTY_WITH_DESCRIPTION_Y" name="PROPERTY_WITH_DESCRIPTION" value="Y" <?if('Y' == $arProperty['WITH_DESCRIPTION'])echo " checked"?>>
		</td>
		</tr><?
	}
	elseif(
		isset($arPropertyFields["SET"]["WITH_DESCRIPTION"])
	)
	{
		?>
		<input type="hidden" id="PROPERTY_WITH_DESCRIPTION_Y" name="PROPERTY_WITH_DESCRIPTION" value="<?echo htmlspecialcharsbx($arPropertyFields["SET"]["WITH_DESCRIPTION"])?>">
		<?
	}

	$bShow = true;
	if ($showKeyExist && in_array("MULTIPLE_CNT", $arPropertyFields["SHOW"]))
		$bShow = true;
	elseif ($hideKeyExist && in_array("MULTIPLE_CNT", $arPropertyFields["HIDE"]))
		$bShow = false;
	elseif ('L' == $arProperty['PROPERTY_TYPE'])
		$bShow = false;
	elseif ('F' == $arProperty['PROPERTY_TYPE'])
		$bShow = false;

	if ($bShow)
	{
		?><tr>
		<td width="40%"><?echo GetMessage("BT_ADM_IEP_PROP_MULTIPLE_CNT")?></td>
		<td><input type="text" id="PROPERTY_MULTIPLE_CNT" name="PROPERTY_MULTIPLE_CNT"  value="<?echo intval($arProperty['MULTIPLE_CNT']); ?>" size="3"></td>
		</tr><?
	}
	elseif(
		isset($arPropertyFields["SET"]["MULTIPLE_CNT"])
	)
	{
		?>
		<input type="hidden" id="PROPERTY_MULTIPLE_CNT" name="PROPERTY_MULTIPLE_CNT" value="<?echo htmlspecialcharsbx($arPropertyFields["SET"]["MULTIPLE_CNT"])?>">
		<?
	}

	?>
	<tr>
		<td width="40%"><?echo GetMessage("BT_ADM_IEP_PROP_HINT_DET")?></td>
		<td ><input type="text" size="50" maxlength="255" id="PROPERTY_HINT" name="PROPERTY_HINT" value="<?echo htmlspecialcharsbx($arProperty['HINT']);?>"></td>
	</tr>
	<?if(!$bSectionPopup):?>
	<tr>
		<td width="40%"><label for="PROPERTY_SECTION_PROPERTY_Y"><?echo GetMessage("BT_ADM_IEP_PROP_SECTION_PROPERTY")?></label></td>
		<td>
			<input type="hidden" id="PROPERTY_SECTION_PROPERTY_N" name="PROPERTY_SECTION_PROPERTY" value="N">
			<input type="checkbox" id="PROPERTY_SECTION_PROPERTY_Y" name="PROPERTY_SECTION_PROPERTY" value="Y" <?if('N' != $arProperty['SECTION_PROPERTY'])echo ' checked="checked"';?>>
		</td>
	</tr>
	<?
		$bShow = true;
		if ($showKeyExist && in_array("SMART_FILTER", $arPropertyFields["SHOW"]))
			$bShow = true;
		elseif ($hideKeyExist && in_array("SMART_FILTER", $arPropertyFields["HIDE"]))
			$bShow = false;
		elseif($arProperty['PROPERTY_TYPE'] == 'F')
			$bShow = false;
		if ($bShow)
		{
		?>
		<tr id="tr_SMART_FILTER" style="display: <? echo ($arProperty['SECTION_PROPERTY'] != 'N' ? 'table-row' : 'none'); ?>">
			<td width="40%"><label for="PROPERTY_SMART_FILTER_Y"><?echo GetMessage("BT_ADM_IEP_PROP_SMART_FILTER")?></label></td>
			<td>
				<input type="hidden" id="PROPERTY_SMART_FILTER_N" name="PROPERTY_SMART_FILTER" value="N">
				<input type="checkbox" id="PROPERTY_SMART_FILTER_Y" name="PROPERTY_SMART_FILTER" value="Y" <?if('N' != $arProperty['SMART_FILTER'])echo ' checked="checked"';?>>
			</td>
		</tr>
		<?
		$displayTypes = CIBlockSectionPropertyLink::getDisplayTypes($arProperty["PROPERTY_TYPE"], $arProperty["USER_TYPE"]);
		if ($displayTypes)
		{
		?>
		<tr id="tr_DISPLAY_TYPE" style="display: <? echo ($arProperty['SECTION_PROPERTY'] != 'N' ? 'table-row' : 'none'); ?>">
			<td width="40%"><?echo GetMessage("BT_ADM_IEP_PROP_DISPLAY_TYPE")?></td>
			<td>
		<?
			echo SelectBoxFromArray('PROPERTY_DISPLAY_TYPE', array(
				"REFERENCE_ID" => array_keys($displayTypes),
				"REFERENCE" => array_values($displayTypes),
			), $arProperty["DISPLAY_TYPE"], '', '');
		?>
			</td>
		</tr>
		<?
		}
		?>
		<tr id="tr_DISPLAY_EXPANDED" style="display: <? echo ($arProperty['SECTION_PROPERTY'] != 'N' ? 'table-row' : 'none'); ?>">
			<td width="40%"><label for="PROPERTY_DISPLAY_EXPANDED_Y"><?echo GetMessage("BT_ADM_IEP_PROP_DISPLAY_EXPANDED")?></label></td>
			<td>
				<input type="hidden" id="PROPERTY_DISPLAY_EXPANDED_N" name="PROPERTY_DISPLAY_EXPANDED" value="N">
				<input type="checkbox" id="PROPERTY_DISPLAY_EXPANDED_Y" name="PROPERTY_DISPLAY_EXPANDED" value="Y" <?if('N' != $arProperty['DISPLAY_EXPANDED'])echo ' checked="checked"';?>>
			</td>
		</tr>
		<tr id="tr_FILTER_HINT" class="adm-detail-valign-top" style="display: <? echo ($arProperty['SECTION_PROPERTY'] != 'N' ? 'table-row' : 'none'); ?>">
			<td width="40%"><?echo GetMessage("BT_ADM_IEP_PROP_FILTER_HINT")?></td>
			<td>
			<?
				Loader::includeModule("fileman");
				$LHE = new CHTMLEditor;
				$LHE->Show(array(
					'inputName' => 'PROPERTY_FILTER_HINT',
					'content' => $arProperty['FILTER_HINT'],
					'height' => 200,
					'width' => '100%',
					'minBodyWidth' => 350,
					'bAllowPhp' => false,
					'limitPhpAccess' => false,
					'autoResize' => true,
					'autoResizeOffset' => 40,
					'useFileDialogs' => false,
					'saveOnBlur' => true,
					'showTaskbars' => false,
					'showNodeNavi' => false,
					'askBeforeUnloadPage' => true,
					'bbCode' => false,
					'setFocusAfterShow' => false,
					'controlsMap' => array(
						array('id' => 'Bold', 'compact' => true, 'sort' => 80),
						array('id' => 'Italic', 'compact' => true, 'sort' => 90),
						array('id' => 'Underline', 'compact' => true, 'sort' => 100),
						array('id' => 'Strikeout', 'compact' => true, 'sort' => 110),
						array('id' => 'RemoveFormat', 'compact' => true, 'sort' => 120),
						array('id' => 'Color', 'compact' => true, 'sort' => 130),
						array('id' => 'FontSelector', 'compact' => false, 'sort' => 135),
						array('id' => 'FontSize', 'compact' => false, 'sort' => 140),
						array('separator' => true, 'compact' => false, 'sort' => 145),
						array('id' => 'OrderedList', 'compact' => true, 'sort' => 150),
						array('id' => 'UnorderedList', 'compact' => true, 'sort' => 160),
						array('id' => 'AlignList', 'compact' => false, 'sort' => 190),
						array('separator' => true, 'compact' => false, 'sort' => 200),
						array('id' => 'InsertLink', 'compact' => true, 'sort' => 210),
						array('id' => 'InsertImage', 'compact' => false, 'sort' => 220),
						array('id' => 'InsertVideo', 'compact' => true, 'sort' => 230),
						array('id' => 'InsertTable', 'compact' => false, 'sort' => 250),
						array('separator' => true, 'compact' => false, 'sort' => 290),
						array('id' => 'Fullscreen', 'compact' => false, 'sort' => 310),
						array('id' => 'More', 'compact' => true, 'sort' => 400)
					),
				));
			?>
			</td>
		</tr>
		<?
		}
		elseif(
			isset($arPropertyFields["SET"]["SMART_FILTER"])
		)
		{
		?>
			<input type="hidden" id="PROPERTY_SMART_FILTER_Y" name="PROPERTY_SMART_FILTER" value="<?echo htmlspecialcharsbx($arPropertyFields["SET"]["FILTRABLE"])?>">
		<?
		}
	?>
	<?endif;?>
	<?


// PROPERTY_TYPE specific properties
	if ('L' == $arProperty['PROPERTY_TYPE'])
	{?><tr>
	<td width="40%"><?echo GetMessage("BT_ADM_IEP_PROP_APPEARANCE")?></td>
	<td>
		<select id="PROPERTY_LIST_TYPE" name="PROPERTY_LIST_TYPE">
			<option value="L"<?if($arProperty['LIST_TYPE']!="C")echo " selected"?>><?echo GetMessage("BT_ADM_IEP_PROP_APPEARANCE_LIST")?></option>
			<option value="C"<?if($arProperty['LIST_TYPE']=="C")echo " selected"?>><?echo GetMessage("BT_ADM_IEP_PROP_APPEARANCE_CHECKBOX")?></option>
		</select>
	</td>
</tr>
<?
		$bShow = true;
		if ($showKeyExist && in_array("ROW_COUNT", $arPropertyFields["SHOW"]))
			$bShow = true;
		elseif ($hideKeyExist && in_array("ROW_COUNT", $arPropertyFields["HIDE"]))
			$bShow = false;

		if ($bShow)
		{
			?><tr>
			<td width="40%"><?echo GetMessage("BT_ADM_IEP_PROP_ROW_CNT")?></td>
			<td><input type="text" size="2" maxlength="10" id="PROPERTY_ROW_COUNT" name="PROPERTY_ROW_COUNT" value="<?echo intval($arProperty['ROW_COUNT']); ?>"></td>
			</tr><?
		}
		elseif(
			isset($arPropertyFields["SET"]["ROW_COUNT"])
		)
		{
			?>
			<input type="hidden" id="PROPERTY_ROW_COUNT" name="PROPERTY_ROW_COUNT" value="<?echo htmlspecialcharsbx($arPropertyFields["SET"]["ROW_COUNT"])?>">
			<?
		}
?><tr class="heading"><td colspan="2"><?echo GetMessage("BT_ADM_IEP_PROP_LIST_VALUES")?></td></tr>
<tr>
	<td colspan="2" align="center">
	<table class="internal" id="list-tbl" style="margin: 0 auto;">
		<tr class="heading">
			<td><?echo GetMessage("BT_ADM_IEP_PROP_LIST_ID")?></td>
			<td><?echo GetMessage("BT_ADM_IEP_PROP_LIST_XML_ID")?></td>
			<td><?echo GetMessage("BT_ADM_IEP_PROP_LIST_VALUE")?></td>
			<td><?echo GetMessage("BT_ADM_IEP_PROP_LIST_SORT")?></td>
			<td><?echo GetMessage("BT_ADM_IEP_PROP_LIST_DEFAULT")?></td>
		</tr>
	<?
		if ('Y' != $arProperty['MULTIPLE'])
		{
			$boolDef = true;
			if (isset($arProperty['VALUES']) && is_array($arProperty['VALUES']))
			{
				foreach ($arProperty['VALUES'] as &$arListValue)
				{
					if ('Y' == $arListValue['DEF'])
					{
						$boolDef = false;
						break;
					}
				}
				unset($arListValue);
			}
		?><tr>
		<td>&nbsp;</td>
		<td>&nbsp;</td>
		<td colspan="2"><?echo GetMessage("BT_ADM_IEP_PROP_LIST_DEFAULT_NO")?></td>
		<td style="text-align:center"><input type="radio" name="PROPERTY_VALUES_DEF" value="0" <?if ($boolDef) echo " checked"; ?>> </td>
		</tr>
		<?
		}
		$MAX_NEW_ID = 0;
		if (isset($arProperty['VALUES']) && is_array($arProperty['VALUES']))
		{
			foreach ($arProperty['VALUES'] as $intKey => $arListValue)
			{
				$arPropInfo = array(
					'ID' => $intKey,
					'XML_ID' => $arListValue['XML_ID'],
					'VALUE' => $arListValue['VALUE'],
					'SORT' => (0 < intval($arListValue['SORT']) ? intval($arListValue['SORT']) : '500'),
					'DEF' => ('Y' == $arListValue['DEF'] ? 'Y' : 'N'),
					'MULTIPLE' => $arProperty['MULTIPLE'],
				);
				echo __AddListValueRow($intKey,$arPropInfo);
			}
			$MAX_NEW_ID = sizeof($arProperty['VALUES']);
		}

		for ($i = $MAX_NEW_ID; $i < $MAX_NEW_ID+DEF_LIST_VALUE_COUNT; $i++)
		{
			$intKey = 'n'.$i;
			$arPropInfo = array(
				'ID' => $intKey,
				'XML_ID' => '',
				'VALUE' => '',
				'SORT' => '500',
				'DEF' => 'N',
				'MULTIPLE' => $arProperty['MULTIPLE'],
			);
			echo __AddListValueRow($intKey,$arPropInfo);
		}
		?>
		</table>
		<div style="width: 100%; text-align: center; margin: 10px 0;">
			<input class="adm-btn-big" type="button" id="propedit_add_btn" name="propedit_add" value="<?echo GetMessage("BT_ADM_IEP_PROP_LIST_MORE")?>">
		</div>
		<input type="hidden" name="PROPERTY_CNT" id="PROPERTY_CNT" value="<?echo ($MAX_NEW_ID+DEF_LIST_VALUE_COUNT)?>">
		</td>
</tr><?
	}
	elseif ("F" == $arProperty['PROPERTY_TYPE'])
	{
		$bShow = true;
		if ($showKeyExist && in_array("COL_COUNT", $arPropertyFields["SHOW"]))
			$bShow = true;
		elseif ($hideKeyExist && in_array("COL_COUNT", $arPropertyFields["HIDE"]))
			$bShow = false;

		if ($bShow)
		{
			?><tr>
			<td width="40%"><?echo GetMessage("BT_ADM_IEP_PROP_FILE_TYPES_COL_CNT")?></td>
			<td><input type="text" size="2" maxlength="10" name="PROPERTY_COL_COUNT" value="<?echo intval($arProperty['COL_COUNT'])?>"></td>
			</tr><?
		}
		elseif(
			isset($arPropertyFields["SET"]["COL_COUNT"])
		)
		{
			?>
			<input type="hidden" name="PROPERTY_COL_COUNT" value="<?echo htmlspecialcharsbx($arPropertyFields["SET"]["COL_COUNT"])?>">
			<?
		}
		?>
<tr>
	<td width="40%"><?echo GetMessage("BT_ADM_IEP_PROP_FILE_TYPES")?></td>
	<td>
		<input type="text"  size="50" maxlength="255" name="PROPERTY_FILE_TYPE" value="<?echo htmlspecialcharsbx($arProperty['FILE_TYPE']); ?>" id="CURRENT_PROPERTY_FILE_TYPE">
		<select  onchange="if(this.selectedIndex!=0) document.getElementById('CURRENT_PROPERTY_FILE_TYPE').value=this[this.selectedIndex].value">
			<option value="-"></option>
			<option value=""<?if('' == $arProperty['FILE_TYPE'])echo " selected"?>><?echo GetMessage("BT_ADM_IEP_PROP_FILE_TYPES_ANY")?></option>
			<option value="jpg, gif, bmp, png, jpeg"<?if("jpg, gif, bmp, png, jpeg" == $arProperty['FILE_TYPE'])echo " selected"?>><?echo GetMessage("BT_ADM_IEP_PROP_FILE_TYPES_PIC")?></option>
			<option value="mp3, wav, midi, snd, au, wma"<?if("mp3, wav, midi, snd, au, wma" == $arProperty['FILE_TYPE'])echo " selected"?>><?echo GetMessage("BT_ADM_IEP_PROP_FILE_TYPES_SOUND")?></option>
			<option value="mpg, avi, wmv, mpeg, mpe, flv"<?if("mpg, avi, wmv, mpeg, mpe, flv" == $arProperty['FILE_TYPE'])echo " selected"?>><?echo GetMessage("BT_ADM_IEP_PROP_FILE_TYPES_VIDEO")?></option>
			<option value="doc, txt, rtf"<?if("doc, txt, rtf" == $arProperty['FILE_TYPE'])echo " selected"?>><?echo GetMessage("BT_ADM_IEP_PROP_FILE_TYPES_DOCS")?></option>
		</select>
	</td>
</tr>
<?
	}
	elseif ("G" == $arProperty['PROPERTY_TYPE'] || "E" == $arProperty['PROPERTY_TYPE'])
	{
		$bShow = false;
		if ($showKeyExist && in_array("COL_COUNT", $arPropertyFields["SHOW"]))
		{
			$bShow = true;
		}

		if ($bShow)
		{
			?>
			<tr>
			<td width="40%"><?echo GetMessage("BT_ADM_IEP_PROP_FILE_TYPES_COL_CNT")?></td>
			<td><input type="text" size="2" maxlength="10" name="PROPERTY_COL_COUNT" value="<?echo intval($arProperty['COL_COUNT']);?>"></td>
			</tr>
			<?
		}
		elseif(
			isset($arPropertyFields["SET"]["COL_COUNT"])
		)
		{
			?>
			<input type="hidden" name="PROPERTY_COL_COUNT" value="<?echo htmlspecialcharsbx($arPropertyFields["SET"]["COL_COUNT"])?>">
			<?
		}
		?>
	<tr>
		<td width="40%"><?echo GetMessage("BT_ADM_IEP_PROP_LINK_IBLOCK")?></td>
		<td>
		<?
		$b_f = ($arProperty['PROPERTY_TYPE']=="G" || ($arProperty['PROPERTY_TYPE'] == 'E' && $arProperty['USER_TYPE'] == BT_UT_SKU_CODE) ? array("!ID"=>$intIBlockID) : array());
		echo GetIBlockDropDownList(
			$arProperty['LINK_IBLOCK_ID'],
			"PROPERTY_LINK_IBLOCK_TYPE_ID",
			"PROPERTY_LINK_IBLOCK_ID",
			$b_f,
			'class="adm-detail-iblock-types"',
			'class="adm-detail-iblock-list"'
		);
		?>
		</td>
	</tr>
	<?}
	else
	{
		$bShow = true;
		if ($hideKeyExist && in_array("COL_COUNT", $arPropertyFields["HIDE"]))
			$bShow = false;
		elseif ($hideKeyExist && in_array("ROW_COUNT", $arPropertyFields["HIDE"]))
			$bShow = false;

		if ($bShow)
		{?><tr>
			<td width="40%"><?echo GetMessage("BT_ADM_IEP_PROP_SIZE")?></td>
			<td>
				<input type="text"  size="2" maxlength="10" name="PROPERTY_ROW_COUNT" value="<?echo intval($arProperty['ROW_COUNT']); ?>"> x <input type="text"  size="2" maxlength="10" name="PROPERTY_COL_COUNT" value="<?echo intval($arProperty['COL_COUNT']); ?>">
			</td>
		</tr>
		<?}
		else
		{
			if (isset($arPropertyFields["SET"]["ROW_COUNT"]))
			{?><input type="hidden" name="PROPERTY_ROW_COUNT" value="<?echo htmlspecialcharsbx($arPropertyFields["SET"]["ROW_COUNT"])?>"><?}
			else
			{?><input type="hidden" name="PROPERTY_ROW_COUNT" value="<?echo intval($arProperty['ROW_COUNT'])?>"><?}

			if(isset($arPropertyFields["SET"]["COL_COUNT"]))
			{?><input type="hidden" name="PROPERTY_COL_COUNT" value="<?echo htmlspecialcharsbx($arPropertyFields["SET"]["COL_COUNT"])?>"><? }
			else
			{ ?><input type="hidden" name="PROPERTY_COL_COUNT" value="<?echo intval($arProperty['COL_COUNT']); ?>"><? }
		}

		$bShow = true;
		if ($hideKeyExist && in_array("DEFAULT_VALUE", $arPropertyFields["HIDE"]))
			$bShow = false;

		if ($bShow)
		{?><tr>
			<td width="40%"><?echo GetMessage("BT_ADM_IEP_PROP_DEFAULT")?></td>
			<td>
			<?if(array_key_exists("GetPropertyFieldHtml", $arUserType))
			{
				echo call_user_func_array($arUserType["GetPropertyFieldHtml"],
					array(
						$arProperty,
						array(
							"VALUE"=>$arProperty["DEFAULT_VALUE"],
							"DESCRIPTION"=>""
						),
						array(
							"VALUE"=>"PROPERTY_DEFAULT_VALUE",
							"DESCRIPTION"=>"",
							"MODE" => "EDIT_FORM",
							"FORM_NAME" => "frm_prop"
						),
					));
			}
			else
			{
				?><input type="text"  size="50" maxlength="2000" name="PROPERTY_DEFAULT_VALUE" value="<?echo is_string($arProperty['DEFAULT_VALUE']) ? htmlspecialcharsbx($arProperty['DEFAULT_VALUE']) : ''?>"><?
			}
		?></td>
	</tr><?
		}
	}
	if ($USER_TYPE_SETTINGS_HTML)
	{?><tr class="heading"><td colspan="2"><?
		echo (isset($arPropertyFields["USER_TYPE_SETTINGS_TITLE"]) && '' != trim($arPropertyFields["USER_TYPE_SETTINGS_TITLE"]) ? $arPropertyFields["USER_TYPE_SETTINGS_TITLE"] : GetMessage("BT_ADM_IEP_PROP_USER_TYPE_SETTINGS"));
		?></td></tr><?
		echo $USER_TYPE_SETTINGS_HTML;
	}

	if(is_object($tabControl))
	{
		if ($adminSidePanelHelper->isPublicFrame()):
			$tabControl->Buttons(array(
				"disabled"=>false,
				"back_url"=>$listUrl,
			));
		elseif (!defined('BX_PUBLIC_MODE') || BX_PUBLIC_MODE != 1):
			$tabControl->Buttons(array(
				"disabled"=>false,
				"back_url"=>$listUrl,
			));
		else:
			$tabControl->ButtonsPublic(array(
				'.btnSave',
				'.btnCancel'
			));
		endif;
		$tabControl->End();
	}
	else
	{
		?></tbody></table><?
	}
	?></div></form>
<script type="text/javascript"><?
	if ($arProperty['PROPERTY_TYPE'] == Iblock\PropertyTable::TYPE_LIST)
	{
?>
window.oPropSet = {
		pTypeTbl: BX("list-tbl"),
		curCount: <? echo ($MAX_NEW_ID+5); ?>,
		intCounter: BX("PROPERTY_CNT")
	};

function add_list_row()
{
	var id = window.oPropSet.curCount++,
		newRow,
		oCell,
		strContent;

	window.oPropSet.intCounter.value = window.oPropSet.curCount;
	newRow = window.oPropSet.pTypeTbl.insertRow(window.oPropSet.pTypeTbl.rows.length);

	oCell = newRow.insertCell(-1);
	strContent = '<? echo CUtil::JSEscape(__AddListValueIDCell($defaultListValueSettings['ID'])); ?>';
	strContent = strContent.replace(/tmp_xxx/ig, id);
	oCell.innerHTML = strContent;

	oCell = newRow.insertCell(-1);
	strContent = '<? echo CUtil::JSEscape(__AddListValueXmlIDCell($defaultListValueSettings['ID'], $defaultListValueSettings)); ?>';
	strContent = strContent.replace(/tmp_xxx/ig, id);
	oCell.innerHTML = strContent;
	oCell = newRow.insertCell(-1);
	strContent = '<? echo CUtil::JSEscape(__AddListValueValueCell($defaultListValueSettings['ID'], $defaultListValueSettings)); ?>';
	strContent = strContent.replace(/tmp_xxx/ig, id);
	oCell.innerHTML = strContent;

	oCell = newRow.insertCell(-1);
	strContent = '<? echo CUtil::JSEscape(__AddListValueSortCell($defaultListValueSettings['ID'], $defaultListValueSettings)); ?>';
	strContent = strContent.replace(/tmp_xxx/ig, id);
	oCell.innerHTML = strContent;

	oCell = newRow.insertCell(-1);
	strContent = '<? echo CUtil::JSEscape(__AddListValueDefCell($defaultListValueSettings['ID'], $defaultListValueSettings)); ?>';
	strContent = strContent.replace(/tmp_xxx/ig, id);
	oCell.innerHTML = strContent;
	oCell.setAttribute('align','center');

	BX.style(oCell, 'textAlign', 'center');
	BX.adminFormTools.modifyFormElements('frm_prop');
}

var obListBtn = BX('propedit_add_btn');

if (!!obListBtn && !!window.oPropSet)
	BX.bind(obListBtn, 'click', add_list_row);
<?
	}
if($bReload && $bSectionPopup)
{
?>
setTimeout(function(){
	BX.WindowManager.Get().SetButtons([BX.CAdminDialog.btnSave, BX.CAdminDialog.btnCancel]);
}, 10);
<?
}
?>
(function(){

	var tbl = BX.findChild(BX("frm_prop"), {tag: 'table', className: 'edit-table'}, true, false);
	if (!tbl)
		return;

	var n = tbl.tBodies[0].rows.length;
	for(var i=0; i<n; i++)
	{
		if(tbl.tBodies[0].rows[i].cells.length > 1)
		{
			BX.addClass(tbl.rows[i].cells[0], 'adm-detail-content-cell-l');
			BX.addClass(tbl.rows[i].cells[1], 'adm-detail-content-cell-r');
		}
	}

	BX.adminFormTools.modifyFormElements('frm_prop');

})();
BX.ready(function(){
	var obSectionCheckbox = BX('PROPERTY_SECTION_PROPERTY_Y');
	if (!!obSectionCheckbox)
	{
		BX.bind(obSectionCheckbox, 'click', function(){
			var sect = BX('PROPERTY_SECTION_PROPERTY_Y');
			var smart = BX('tr_SMART_FILTER');
			var displayTypes = BX('tr_DISPLAY_TYPE');
			var propExpand = BX('tr_DISPLAY_EXPANDED');
			var filterHint = BX('tr_FILTER_HINT');
			var trStyle;

			if (!!sect)
			{
				trStyle = (sect.checked ? 'table-row' : 'none');
				if (!!smart)
					BX.style(smart, 'display', trStyle);
				if (!!displayTypes)
					BX.style(displayTypes, 'display', trStyle);
				if (!!propExpand)
					BX.style(propExpand, 'display', trStyle);
				if (!!filterHint)
					BX.style(filterHint, 'display', trStyle);
				BX.adminFormTools.modifyFormElements('frm_prop');
			}
		});
	}
});
</script><?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");