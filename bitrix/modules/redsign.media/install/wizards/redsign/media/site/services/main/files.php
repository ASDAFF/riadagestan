<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)
	die();

if (!defined("WIZARD_SITE_ID") || !defined("WIZARD_SITE_DIR"))
	return;

function ___writeToAreasFile($path, $text)
{
	//if(file_exists($fn) && !is_writable($abs_path) && defined("BX_FILE_PERMISSIONS"))
	//	@chmod($abs_path, BX_FILE_PERMISSIONS);

	$fd = @fopen($path, "wb");
	if(!$fd)
		return false;

	if(false === fwrite($fd, $text))
	{
		fclose($fd);
		return false;
	}

	fclose($fd);

	if(defined("BX_FILE_PERMISSIONS"))
		@chmod($path, BX_FILE_PERMISSIONS);
}

if (COption::GetOptionString("main", "upload_dir") == "")
	COption::SetOptionString("main", "upload_dir", "upload");

if(COption::GetOptionString("redsign.media", "wizard_installed", "N", WIZARD_SITE_ID) == "N" || WIZARD_INSTALL_DEMO_DATA)
{
	if(file_exists(WIZARD_ABSOLUTE_PATH."/site/public/".LANGUAGE_ID."/"))
	{
		CopyDirFiles(
			WIZARD_ABSOLUTE_PATH."/site/public/".LANGUAGE_ID."/",
			WIZARD_SITE_PATH,
			$rewrite = true,
			$recursive = true,
			$delete_after_copy = false
		);
	}
	COption::SetOptionString("redsign.media", "template_converted", "Y", "", WIZARD_SITE_ID);
}
elseif (COption::GetOptionString("redsign.media", "template_converted", "N", WIZARD_SITE_ID) == "N")
{
	CopyDirFiles(
		WIZARD_ABSOLUTE_PATH."/site/services/main/".LANGUAGE_ID."/public_convert/",
		WIZARD_SITE_PATH,
		$rewrite = true,
		$recursive = true,
		$delete_after_copy = false
	);
	CopyDirFiles(
		WIZARD_SITE_PATH."/include/header/logo.php",
		WIZARD_SITE_PATH."/include/header/logo_old.php",
		$rewrite = true,
		$recursive = true,
		$delete_after_copy = true
	);
    CopyDirFiles(
		WIZARD_SITE_PATH."/include/footer/logo.php",
		WIZARD_SITE_PATH."/include/footer/logo_old.php",
		$rewrite = true,
		$recursive = true,
		$delete_after_copy = true
	);

	COption::SetOptionString("redsign.media", "template_converted", "Y", "", WIZARD_SITE_ID);
}

$wizard =& $this->GetWizard();
___writeToAreasFile(WIZARD_SITE_PATH."include/footer/copyright.php", $wizard->GetVar("siteCopy"));
//___writeToAreasFile(WIZARD_SITE_PATH."include/footer/allrights.php", $wizard->GetVar("siteCopy"));
//___writeToAreasFile(WIZARD_SITE_PATH."include/telephone.php", $wizard->GetVar("siteTelephone"));
//___writeToAreasFile(WIZARD_SITE_PATH."include/telephone2.php", $wizard->GetVar("siteTelephone2"));

/*
if ($wizard->GetVar("templateID") != "master")
{
	$arSocNets = array("shopFacebook" => "facebook", "shopTwitter" => "twitter", "shopVk" => "vk", "shopGooglePlus" => "google");
	foreach($arSocNets as $socNet=>$includeFile)
	{
		$curSocnet = $wizard->GetVar($socNet);
		if ($curSocnet)
		{
			$text = '<a href="'.$curSocnet.'"></a>';
			___writeToAreasFile(WIZARD_SITE_PATH."include/socnet_".$includeFile.".php", $text);
		}
	}
}
*/
if(COption::GetOptionString("redsign.media", "wizard_installed", "N", WIZARD_SITE_ID) == "Y" && !WIZARD_INSTALL_DEMO_DATA)
	return;

WizardServices::PatchHtaccess(WIZARD_SITE_PATH);

// #SITE_DIR#
CWizardUtil::ReplaceMacros(WIZARD_SITE_PATH."/_index.php", Array("SITE_DIR" => WIZARD_SITE_DIR));
CWizardUtil::ReplaceMacros(WIZARD_SITE_PATH."/.main.menu.php", Array("SITE_DIR" => WIZARD_SITE_DIR));
CWizardUtil::ReplaceMacros(WIZARD_SITE_PATH."/.main.menu_ext.php", Array("SITE_DIR" => WIZARD_SITE_DIR));
CWizardUtil::ReplaceMacros(WIZARD_SITE_PATH."/.topbar.menu.php", Array("SITE_DIR" => WIZARD_SITE_DIR));
CWizardUtil::ReplaceMacros(WIZARD_SITE_PATH."/sect_sidebar.php", Array("SITE_DIR" => WIZARD_SITE_DIR));
CWizardUtil::ReplaceMacros(WIZARD_SITE_PATH."/articles/index.php", Array("SITE_DIR" => WIZARD_SITE_DIR));
CWizardUtil::ReplaceMacros(WIZARD_SITE_PATH."/include/header/logo.php", Array("SITE_DIR" => WIZARD_SITE_DIR));
CWizardUtil::ReplaceMacros(WIZARD_SITE_PATH."/include/footer/logo.php", Array("SITE_DIR" => WIZARD_SITE_DIR));
CWizardUtil::ReplaceMacros(WIZARD_SITE_PATH."/include/footer/search_title.php", Array("SITE_DIR" => WIZARD_SITE_DIR));
CWizardUtil::ReplaceMacros(WIZARD_SITE_PATH."/include/tuning/component.php", Array("SITE_DIR" => WIZARD_SITE_DIR));


// SITE META
CWizardUtil::ReplaceMacros(WIZARD_SITE_PATH."/.section.php", array("SITE_DESCRIPTION" => htmlspecialcharsbx($wizard->GetVar("siteMetaDescription"))));
CWizardUtil::ReplaceMacros(WIZARD_SITE_PATH."/.section.php", array("SITE_KEYWORDS" => htmlspecialcharsbx($wizard->GetVar("siteMetaKeywords"))));

// #REDSIGN_COPYRIGHT#
CWizardUtil::ReplaceMacros($_SERVER["DOCUMENT_ROOT"].BX_ROOT."/templates/".WIZARD_TEMPLATE_ID."_".WIZARD_THEME_ID."/include/footers/type1.php", array('REDSIGN_COPYRIGHT' => GetMessage('REDSIGN_COPYRIGHT')));

// SUBSCRIBE FORM
if(IsModuleInstalled('subscribe')) {
    $subscribeForm = '<?$APPLICATION->IncludeComponent("bitrix:subscribe.form", "simple" ,Array(
        "USE_PERSONALIZATION" => "Y", 
        "PAGE" => "'.WIZARD_SITE_DIR.'personal/subscribe/subscr_edit.php", 
        "SHOW_HIDDEN" => "Y", 
        "CACHE_TYPE" => "A", 
        "CACHE_TIME" => "3600" 
    )
);?>';    
    
} else {
    $subscribeForm = '';
}

CWizardUtil::ReplaceMacros(WIZARD_SITE_PATH."/_index.php", Array("SUBSCRIBE_FORM" => $subscribeForm));
CWizardUtil::ReplaceMacros(WIZARD_SITE_PATH."/sect_sidebar.php", Array("SUBSCRIBE_FORM" => $subscribeForm));
CWizardUtil::ReplaceMacros(WIZARD_SITE_PATH."/include/articles/section_sidebar.php", Array("SUBSCRIBE_FORM" => $subscribeForm));

// if (CModule::IncludeModule("sale"))
// {
	$addResult = \Bitrix\Main\UserConsent\Internals\AgreementTable::add(array(
		"CODE" => "sale_default",
		"NAME" => GetMessage("WIZ_DEFAULT_USER_CONSENT_NAME"),
		"TYPE" => \Bitrix\Main\UserConsent\Agreement::TYPE_STANDARD,
		"LANGUAGE_ID" => LANGUAGE_ID,
		//"DATA_PROVIDER" => \Bitrix\Sale\UserConsent::DATA_PROVIDER_CODE
	));
	if ($addResult->isSuccess())
	{
        $iAgreementId = $addResult->getId();
        
        CWizardUtil::ReplaceMacros(WIZARD_SITE_PATH."articles/index.php", Array("USER_CONSENT_ID" => $iAgreementId));
        CWizardUtil::ReplaceMacros(WIZARD_SITE_PATH."personal/subscribe/subscr_edit.php", Array("USER_CONSENT_ID" => $iAgreementId));
	}
// }

$arUrlRewrite = array();
if (file_exists(WIZARD_SITE_ROOT_PATH."/urlrewrite.php"))
{
	include(WIZARD_SITE_ROOT_PATH."/urlrewrite.php");
}

$arNewUrlRewrite = array(
	array(
		"CONDITION" => "#^".WIZARD_SITE_DIR."articles/#",
		"RULE" => "",
		"ID" => "bitrix:news",
		"PATH" => WIZARD_SITE_DIR."articles/index.php",
	),
);

foreach ($arNewUrlRewrite as $arUrl)
{
	if (!in_array($arUrl, $arUrlRewrite))
	{
		CUrlRewriter::Add($arUrl);
	}
}
?>