<?php 
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php');
IncludeModuleLangFile(__FILE__);

$fid = intval($_REQUEST['fid']);
$bid = intval($_REQUEST['bid']);
$id = intval($_REQUEST['id']);
$origid = $id;
$resize = $_REQUEST['resize_ratio'];
$resize_w = $_REQUEST['resize_w'];
$resize_h = $_REQUEST['resize_h'];
$strEntity = (isset($_REQUEST['entity']) && 'G' == $_REQUEST['entity'] ? 'G' : 'E');

$settings = array();
if (isset($_REQUEST['settings'])) {
	$settings = $_REQUEST['settings'];
}
$settings['ratioResize'] = $resize;
$settings['crop_resize_width'] = $resize_w;
$settings['crop_resize_height'] = $resize_h;
if (array_key_exists('sessid', $settings)) {
	unset($settings['sessid']);
}

if (!function_exists('__ShowError')) {
	function __ShowError($mess) {
		require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php');
		ShowError($mess);
		require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
		die();
	}
}

if (!function_exists('__CheckEntityRights')) {
	function __CheckEntityRights($bid, $id, $entity = 'E', $workflow = false) {
		if ($entity == 'E') {
			if ($workflow) {
				return CIBlockElementRights::UserHasRightTo($bid, $id, "element_edit_any_wf_status");
			} else {
				return CIBlockElementRights::UserHasRightTo($bid, $id, 'element_edit');
			}
		} elseif ($entity == 'G') {
			return CIBlockSectionRights::UserHasRightTo($bid, $id, 'section_edit');
		}
		return false;
	}
}

if (!function_exists('__HasRightToFile')) {
	function __HasRightToFile($fid, &$id, $bid, $entity = 'E', $workflow) {
		if ($entity == 'G') {
			$rsSections = CIBlockSection::GetList(
				array(),
				array('ID' => $id, 'IBLOCK_ID' => $bid),
				false,
				array('ID', 'IBLOCK_ID', 'PICTURE', 'DETAIL_PICTURE')
			);
			if ($arSection = $rsSections->Fetch()) {
				if ($fid == $arSection['PICTURE'] || $fid == $arSection['DETAIL_PICTURE']) {
					return true;
				}
			}

		} elseif ($entity == 'E') {
			if ($workflow) {
				$wid = CIBlockElement::WF_GetLast($id);
				$STATUS_TITLE = '';
				$STATUS_ID = CIBlockElement::WF_GetCurrentStatus($wid, $STATUS_TITLE);
				$STATUS_PERMISSION = CIBlockElement::WF_GetStatusPermission($STATUS_ID);

				if ($STATUS_ID>1 && $STATUS_PERMISSION<2)
				{
					return false;
				}
			}
			$rsElements = CIBlockElement::GetList(
				array(),
				array('ID' => $id, 'IBLOCK_ID' => $bid, 'SHOW_HISTORY' => 'Y'),
				false,
				false,
				array('ID', 'IBLOCK_ID', 'PREVIEW_PICTURE', 'DETAIL_PICTURE')
			);
			if ($obElement = $rsElements->GetNextElement(false, false)) {

				$arElement = $obElement->GetFields();
				if ($fid==$arElement['PREVIEW_PICTURE'] || $fid==$arElement['DETAIL_PICTURE']) {
					return true;
				} else {
					$arPropList = $obElement->GetProperties(array(), array('PROPERTY_TYPE' => 'F'));
					foreach ($arPropList as $arProp) {
						if (!empty($arProp['VALUE'])) {
							if (is_array($arProp['VALUE'])) {
								$i = array_search($fid, $arProp['VALUE']);
								if (false !== $i) {
									return true;
								}
							} elseif ($fid == $arProp['VALUE']) {
								return true;
							}
						}
					}
				}
			}
		}
		return false;
	}
}

$bWorkflow = false;
$bError = false;

if (!CModule::IncludeModule('asd.cropphoto')) {
	$bError = true;
	__ShowError(GetMessage('ASD_ASDMIN_NOT_INST'));
}
if (!$bError) {
	if (!CModule::IncludeModule('iblock')) {
		$bError = true;
		__ShowError(GetMessage('ASD_ASDMIN_NOT_INST_IBLOCK'));
	}
}
if (!$bError) {
	$bWorkflow = CModule::IncludeModule("workflow") && (CIBlock::GetArrayByID($bid, "WORKFLOW") != "N");
	if (!__CheckEntityRights($bid, $id, $strEntity) || !__HasRightToFile($fid, $id, $bid, $strEntity, $bWorkflow)) {
		$bError = true;
		__ShowError(GetMessage('ASD_ASDMIN_ACCESS_DENIED').$id);
	}
}
if (!$bError && $fid>0) {
	if ($arFile = CFile::GetFileArray($fid)) {

		$maxW = $arFile['WIDTH']>400 ? 400 : $arFile['WIDTH'];
		$ratioJS = $arFile['WIDTH']/$maxW;
		$arFile['src'] = $arFile['SRC'];

		if (isset($_REQUEST['coords']) && check_bitrix_sessid()) {

			require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_js.php');

			$settings['crop_set_coords'] = $_REQUEST['coords'];
			CUserOptions::SetOption('asd_cropphoto', 'popup_settings_'.$bid, $settings);
			$arCoords = array('width_orig' => $arFile['WIDTH'], 'height_orig' => $arFile['HEIGHT']);
			list($arCoords['x'], $arCoords['y'], $arCoords['width'], $arCoords['height']) = explode(',', $_REQUEST['coords']);

			if ($arCoords['width']*$arCoords['height'] > 0) {

				$arTempFile = CFile::MakeFileArray($arFile['ID']);

				if (isset($arTempFile['tmp_name']) && strlen($arTempFile['tmp_name'])) {

					$tmpPath = $_SERVER['DOCUMENT_ROOT'].'/bitrix/tmp/asd.cropphoto/'.md5(uniqid()).'/';
					$io = CBXVirtualIo::GetInstance();

					if ($io->CreateDirectory($tmpPath)) {

						$sourceFile = $tmpPath.'edit_'.$arFile['FILE_NAME'];

						if ($io->Copy($io->GetLogicalName($arTempFile['tmp_name']), $sourceFile)) {

							$destinationFile = $tmpPath.$arFile['FILE_NAME'];
							$destinationFileRes = $tmpPath.'resize_'.$arFile['FILE_NAME'];

							if (CASDcropphoto::CropImageFile($sourceFile, $destinationFile, $arCoords)) {

								if ($resize=='Y' && $resize_w*$resize_h>0) {
									CFile::ResizeImageFile($destinationFile,
												$destinationFileRes,
												array('width' => $resize_w, 'height' => $resize_h));
									$destinationFile = $destinationFileRes;
								}

								$destinationFile = $io->GetPhysicalName($destinationFile);
								$arNewPict = CFile::MakeFileArray($destinationFile);
								if ('G' == $strEntity) {
									$rsSections = CIBlockSection::GetList(array(), array('ID' => $id), false, array('ID', 'PICTURE', 'DETAIL_PICTURE'));
									if ($arSection = $rsSections->Fetch()) {
										$obSection = new CIBlockSection();
										if ($fid == $arSection['PICTURE']) {
											$obSection->Update($id, array('PICTURE' => $arNewPict));
										} elseif ($fid == $arSection['DETAIL_PICTURE']) {
											$obSection->Update($id, array('DETAIL_PICTURE' => $arNewPict));
										}
									}
								} else {
									$rsElements = CIBlockElement::GetList(
										array(),
										array('ID' => $id, 'IBLOCK_ID' => $bid, 'SHOW_HISTORY' => 'Y'),
										false,
										false,
										array('ID', 'IBLOCK_ID', 'PREVIEW_PICTURE', 'DETAIL_PICTURE', 'WF_STATUS_ID')
									);
									if ($obElement = $rsElements->GetNextElement(false, false)) {
										$arElement = $obElement->GetFields();
										$el = new CIBlockElement();
										$arNewPict['COPY_FILE'] = 'Y';
										$updateArray = array();
										if ($bWorkflow && intval($arElement['WF_STATUS_ID']) > 0) {
											$updateArray['WF_STATUS_ID'] = $arElement['WF_STATUS_ID'];
										}
										if ($fid == $arElement['PREVIEW_PICTURE']) {
											$updateArray['PREVIEW_PICTURE'] = $arNewPict;
											$el->Update($origid, $updateArray, $bWorkflow);
										} elseif ($fid == $arElement['DETAIL_PICTURE']) {
											$updateArray['DETAIL_PICTURE'] = $arNewPict;
											$el->Update($origid, $updateArray, $bWorkflow);
										} else {
											if ($bWorkflow) {
												$arPropList = $obElement->GetProperties(array(), array('PROPERTY_TYPE' => 'F'));
												$search = false;
												foreach ($arPropList as $arProp) {
													if (!empty($arProp['VALUE'])) {
														if (is_array($arProp['VALUE'])) {
															$i = array_search($fid, $arProp['VALUE']);
															if (false !== $i) {
																$updateArray['PROPERTY_VALUES'] = array();
																$updateArray['PROPERTY_VALUES'][$arProp['ID']] = array();
																$updateArray['PROPERTY_VALUES'][$arProp['ID']][$arProp['PROPERTY_VALUE_ID'][$i]] = $arNewPict;
																$search = true;
																break;
															}
														} elseif ($fid == $arProp['VALUE']) {
															$updateArray['PROPERTY_VALUES'] = array();
															$updateArray['PROPERTY_VALUES'][$arProp['ID']] = array();
															$updateArray['PROPERTY_VALUES'][$arProp['ID']][$arProp['PROPERTY_VALUE_ID']] = $arNewPict;
															$search = true;
															break;
														}
													}
												}
												if ($search) {
													$el->Update($origid, $updateArray, $bWorkflow);
												}
											} else {
												$arPropList = $obElement->GetProperties(array(), array('PROPERTY_TYPE' => 'F'));
												foreach ($arPropList as $arProp) {
													if (!empty($arProp['VALUE'])) {
														if (is_array($arProp['VALUE'])) {
															$i = array_search($fid, $arProp['VALUE']);
															if (false !== $i) {
																$arNewFile = array();
																$arNewFile[$arProp['PROPERTY_VALUE_ID'][$i]] = $arNewPict;
																CIBlockElement::SetPropertyValues($id, $arElement['IBLOCK_ID'], $arNewFile, $arProp['ID']);
																break;
															}
														} elseif ($fid == $arProp['VALUE']) {
															CIBlockElement::SetPropertyValues($id, $arElement['IBLOCK_ID'], $arNewPict, $arProp['ID']);
															break;
														}
													}
												}
											}
										}
									}
								}
							}
						}
						$io->Delete($tmpPath);
					}
				}
			}
			?><script type="text/javascript">
				top.BX.closeWait(); top.BX.WindowManager.Get().AllowClose(); top.BX.WindowManager.Get().Close();
				top.BX.reload();
			</script><?
			require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin_js.php');
			die();
		} else {
			$APPLICATION->SetTitle(GetMessage('ASD_ASDMIN_SETTINGS_TITLE'));
			require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php');
			CJSCore::Init(array('asd_crop_core'));
			$arSettings = CUserOptions::GetOption('asd_cropphoto', 'popup_settings_'.$bid);
			$arFile['width'] = intval($arFile['WIDTH']);
			$arFile['height'] = intval($arFile['HEIGHT']);
			if ($arFile['width']*$arFile['height'] > 0) {
				?>
				<script type="text/javascript">
					var jcrop_api;
					var ratio = <?= $ratioJS?>;
					var aspectRatio = <?= $arSettings['aspectRatio']=='Y' ? 'true' : 'false';?>;
					$(document).ready($ready);
				</script>
				<form method="POST" name="asd_crop_form" action="/bitrix/tools/asd_cropphoto.php">
				<div id="crop_form">
					<input type="text" size="4" id="crop_select_width" name="settings[crop_select_width]" /> /
					<input type="text" size="4" id="crop_select_height" name="settings[crop_select_height]" />
					<div>
						<input type="hidden" name="settings[aspectRatio]" value="N" />
						<input type="checkbox" id="aspectRatio" name="settings[aspectRatio]" value="Y"<?if ($arSettings['aspectRatio'] == 'Y'){?> checked="checked"<?}?> />
						<label for="aspectRatio"><?= GetMessage('ASD_ASDMIN_ASPECTRATIO')?></label>
						<div id="aspectRatio_set" style="<?= $arSettings['aspectRatio']=='Y' ? '' : 'display: none;'?>">
							<input type="text" size="4" id="crop_ratio_x" name="settings[crop_ratio_x]" value="<?= $arSettings['crop_ratio_x']>0 ? $arSettings['crop_ratio_x'] : 1?>" /> :
							<input type="text" size="4" id="crop_ratio_y" name="settings[crop_ratio_y]" value="<?= $arSettings['crop_ratio_y']>0 ? $arSettings['crop_ratio_y'] : 1?>" />
						</div>
					</div>
					<div>
						<input type="hidden" name="resize_ratio" value="N" />
						<input type="checkbox" name="resize_ratio" id="ratioResize" value="Y"<?if ($arSettings['ratioResize'] == 'Y'){?> checked="checked"<?}?> />
						<label for="ratioResize"><?= GetMessage('ASD_ASDMIN_RATIORESIZE')?></label>
						<div id="ratioResize_set" style="<?= $arSettings['ratioResize']=='Y' ? '' : 'display: none;'?>">
							<input type="text" name="resize_w" size="4" id="crop_resize_width" value="<?= $arSettings['crop_resize_width']?>" /> /
							<input type="text" name="resize_h" size="4" id="crop_resize_height" value="<?= $arSettings['crop_resize_height']?>" />
						</div>
					</div>
					<input type="hidden" name="coords" id="crop_set_coords" value="" />
					<input type="hidden" name="fid" value="<?= $fid?>" />
					<input type="hidden" name="bid" value="<?= $bid?>" />
					<input type="hidden" name="id" value="<?= $id?>" />
					<input type="hidden" name="entity" value="<? echo $strEntity;?>" />
					<?= bitrix_sessid_post();?>
				</div><br/>
				</form>
				<img id="jcrop_target" src="<?= $arFile['src']?>" width="<?= $maxW?>" alt="" /><br/>
				<div style="width:0px;height:0px;overflow:hidden;margin-left:5px;" id="crop_preview"><img src="<?= $arFile['src']?>" width="<?= $maxW?>" /></div>
				<?
			}
		}
	} else {
		__ShowError(GetMessage('ASD_ASDMIN_NOT_FOUND'));
	}
}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');