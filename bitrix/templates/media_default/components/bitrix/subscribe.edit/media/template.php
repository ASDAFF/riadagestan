<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) {
    die();
}

use \Bitrix\Main\Localization\Loc;

?>
<div class="b-subscription">
    <?php foreach ($arResult["MESSAGE"] as $itemID => $itemValue): ?>
        <?=ShowMessage(array("MESSAGE"=>$itemValue, "TYPE"=>"OK"));?>
    <?php endforeach; ?>
    <?php foreach ($arResult["ERROR"] as $itemID => $itemValue): ?>
        <?=ShowMessage(array("MESSAGE"=>$itemValue, "TYPE"=>"ERROR")); ?>
    <?php endforeach; ?>

    <?php if ($arResult["ALLOW_ANONYMOUS"]=="N" && !$USER->IsAuthorized()): ?>
        <?=ShowMessage(array("MESSAGE"=>Loc::getMessage("CT_BSE_AUTH_ERR"), "TYPE"=>"ERROR"));?>
    <?php else: ?>
        <div class="row">
            <div class="col-12">
                <form action="<?=$arResult["FORM_ACTION"]?>" method="post">
                    <?=bitrix_sessid_post();?>
                    <input type="hidden" name="PostAction" value="<?=($arResult["ID"]>0? "Update":"Add")?>">
                    <input type="hidden" name="ID" value="<?=$arResult["SUBSCRIPTION"]["ID"];?>">
                    <input type="hidden" name="RUB_ID[]" value="0">

                    <div class="form-group">
                        <div class="label-wrap"><?=Loc::getMessage('RS.FIELD_EMAIL')?><span class="required"> *</span></div>
                        <input class="form-control" type="text" name="EMAIL" value="<?=$arResult["SUBSCRIPTION"]["EMAIL"]!=""? $arResult["SUBSCRIPTION"]["EMAIL"]: $arResult["REQUEST"]["EMAIL"];?>">
                    </div>

                    <div class="form-group">
                        <div class="label-wrap"><?=Loc::getMessage('CT_BSE_FORMAT_LABEL')?></div>
                        <div class="b-subscription__padleft">
                            <div class="row">
                                <div class="col-xs-12 col-sm-6 col-md-3">
                                    <div class="custom-control custom-radio">
                                        <input class="custom-control-input" type="radio" name="FORMAT" id="MAIL_TYPE_TEXT" value="text" <?php if ($arResult["SUBSCRIPTION"]["FORMAT"] != "html"): echo "checked"; endif; ?>>
                                        <label class="custom-control-label" for="MAIL_TYPE_TEXT"><?=Loc::getMessage("CT_BSE_FORMAT_TEXT")?></label>
                                    </div>
                                </div>
                                <div class="col-xs-12 col-sm-6 col-md-3">
                                    <div class="custom-control custom-radio">
                                        <input class="custom-control-input" type="radio" name="FORMAT" id="MAIL_TYPE_HTML" value="html" <?php if ($arResult["SUBSCRIPTION"]["FORMAT"] == "html"): echo "checked"; endif; ?>>
                                        <label class="custom-control-label" for="MAIL_TYPE_HTML"><?=Loc::getMessage("CT_BSE_FORMAT_HTML")?></label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="label-wrap"><?=Loc::getMessage('RS.RUBRIC')?></div>
                        <div class="b-subscription__padleft">
                            <?php foreach ($arResult["RUBRICS"] as $itemID => $itemValue): ?>
                                <div class="b-subscription__rubric">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="RUBRIC_<?=$itemID?>" name="RUB_ID[]" value="<?=$itemValue["ID"]?>"<?php if ($itemValue["CHECKED"]): echo " checked"; endif; ?>>
                                        <label for="RUBRIC_<?=$itemID?>" class="custom-control-label">
                                            <?=$itemValue["NAME"]?>
                                            <div class="b-subscription__rubric-desc"><small><?=$itemValue["DESCRIPTION"]?></small></div>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <hr>

                    <div class="form-group">
                        <?php if ($arResult["ID"]==0): ?>
                            <div class="b-subscription__note"><?=Loc::getMessage("CT_BSE_NEW_NOTE")?></div>
                        <?php else: ?>
                            <div class="b-subscription__note"><?=Loc::getMessage("CT_BSE_EXIST_NOTE")?></div>
                        <?php endif; ?>
                        <div><br>
						<?php
						$sBtnSubmitText = $arResult["ID"] > 0
							? Loc::getMessage("CT_BSE_BTN_EDIT_SUBSCRIPTION")
							: Loc::getMessage("CT_BSE_BTN_ADD_SUBSCRIPTION");

                        if ($arParams['RS_USE_CONSENT'] == 'Y') {
                            $APPLICATION->IncludeComponent(
                                "bitrix:main.userconsent.request",
                                "media",
                                array(
                                    "ID" => $arParams['RS_CONSENT_ID'],
                                    "IS_CHECKED" => 'Y',
                                    "AUTO_SAVE" => "Y",
                                    "IS_LOADED" => 'Y',
                                    "INPUT_NAME" => "SUBSCRIBE_CONFIRM_PDP",
                                    // 'SUBMIT_EVENT_NAME' => '',
                                    'REPLACE' => array(
                                        'button_caption' => $sBtnSubmitText,
                                        // 'fields' => array()
                                    )
                                )
                            );
                        }
                        ?>
                        </div>
                        <div class="b-subscription__btns">
                            <input class="btn btn-primary" type="submit" name="Save" value="<?=$sBtnSubmitText?>">
                        </div>
                    </div>

                    <?php if ($arResult["ID"]>0 && $arResult["SUBSCRIPTION"]["CONFIRMED"] <> "Y"): ?>
                        <hr>
                        <div class="field-wrap">
                            <div class="label-wrap"><?=Loc::getMessage('CT_BSE_CONF_NOTE');?></div>
                            <input class="form-control" name="CONFIRM_CODE" type="text" value="" placeholder="<?=Loc::getMessage("CT_BSE_CONFIRMATION")?>"><br>
                            <input class="btn btn-primary" type="submit" name="confirm" value="<?=Loc::getMessage("CT_BSE_BTN_CONF")?>">
                        </div>
                    <?php endif; ?>
                </form>

                <?php if (!CSubscription::IsAuthorized($arResult["ID"])): ?>
                    <hr>
                    <form action="<?=$arResult["FORM_ACTION"]?>" method="post">
                        <?=bitrix_sessid_post();?>
                        <input type="hidden" name="action" value="sendcode">
                        <div class="field-wrap">
                            <div class="label-wrap"><?=Loc::getMessage('CT_BSE_SEND_NOTE');?></div>
                            <input class="form-control" name="sf_EMAIL" type="text" value="" placeholder="<?=Loc::getMessage("CT_BSE_EMAIL")?>"><br>
                            <input class="btn btn-primary" type="submit" value="<?=Loc::getMessage("CT_BSE_BTN_SEND")?>" />
                        </div>
                    </form>
                <?php endif; ?>
                <div class="field-wrap">
                  <span><span class="required">*</span><?=Loc::getMessage('RS.REQUIRE_NOTE')?></span>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
