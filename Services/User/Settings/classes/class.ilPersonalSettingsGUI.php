<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

/**
 * GUI class for personal profile
 * @author Alexander Killing <killing@leifos.de>
 * @ilCtrl_Calls ilPersonalSettingsGUI: ilMailOptionsGUI
 */
class ilPersonalSettingsGUI
{
    protected ilPropertyFormGUI $form;
    protected string $entered_new_password;
    protected string $entered_current_password;
    protected string $password_error;
    protected ilUserDefinedFields $user_defined_fields;
    protected string $upload_error;
    protected \ILIAS\User\StandardGUIRequest $request;
    protected ilGlobalTemplateInterface $tpl;
    public ilLanguage $lng;
    public ilCtrl $ctrl;
    protected ilUserSettingsConfig $user_settings_config;

    public function __construct()
    {
        global $DIC;

        $this->user_defined_fields = ilUserDefinedFields::_getInstance();

        $this->tpl = $DIC->ui()->mainTemplate();
        $this->lng = $DIC->language();
        $this->ctrl = $DIC->ctrl();
        $this->upload_error = "";
        $this->password_error = "";
        $this->lng->loadLanguageModule("user");
        $this->ctrl->saveParameter($this, "user_page");

        $this->user_settings_config = new ilUserSettingsConfig();
        $this->request = new \ILIAS\User\StandardGUIRequest(
            $DIC->http(),
            $DIC->refinery()
        );
        $this->entered_new_password = $this->request->getNewPassword();
        $this->entered_current_password = $this->request->getCurrentPassword();
    }

    /**
     * execute command
     */
    public function executeCommand(): void
    {
        global $DIC;

        $next_class = $this->ctrl->getNextClass();

        switch ($next_class) {
            case 'ilmailoptionsgui':
                if (!$DIC->rbac()->system()->checkAccess('internal_mail', ilMailGlobalServices::getMailObjectRefId())) {
                    throw new ilPermissionException($DIC->language()->txt('permission_denied'));
                }

                $this->__initSubTabs('showMailOptions');
                $DIC->tabs()->activateTab('mail_settings');
                $this->setHeader();
                $this->ctrl->forwardCommand(new ilMailOptionsGUI());
                break;

            default:
                $cmd = $this->ctrl->getCmd("showGeneralSettings");
                $this->$cmd();
                break;
        }
    }

    // init sub tabs
    public function __initSubTabs(string $a_cmd): void
    {
        global $DIC;

        $ilTabs = $DIC['ilTabs'];
        $ilSetting = $DIC['ilSetting'];
        $ilHelp = $DIC['ilHelp'];
        $rbacsystem = $DIC['rbacsystem'];
        $ilUser = $DIC['ilUser'];

        $ilHelp->setScreenIdComponent("user");

        $showPassword = $a_cmd == 'showPassword';
        $showGeneralSettings = $a_cmd == 'showGeneralSettings';

        // old profile

        // general settings
        $ilTabs->addTarget(
            "general_settings",
            $this->ctrl->getLinkTarget($this, "showGeneralSettings"),
            "",
            "",
            "",
            $showGeneralSettings
        );

        // password
        if ($this->allowPasswordChange()) {
            $ilTabs->addTarget(
                "password",
                $this->ctrl->getLinkTarget($this, "showPassword"),
                "",
                "",
                "",
                $showPassword
            );
        }

        if ($rbacsystem->checkAccess('internal_mail', ilMailGlobalServices::getMailObjectRefId()) && $ilSetting->get('show_mail_settings')) {
            $this->ctrl->setParameter($this, 'referrer', 'ilPersonalSettingsGUI');

            $ilTabs->addTarget(
                "mail_settings",
                $this->ctrl->getLinkTargetByClass('ilMailOptionsGUI'),
                "",
                array('ilMailOptionsGUI')
            );
        }

        if ($ilSetting->get('user_delete_own_account') &&
            $ilUser->getId() != SYSTEM_USER_ID) {
            $ilTabs->addTab(
                "delacc",
                $this->lng->txt('user_delete_own_account'),
                $this->ctrl->getLinkTarget($this, "deleteOwnAccount1")
            );
        }
    }

    /**
     * Set header
     */
    public function setHeader(): void
    {
        $this->tpl->setTitle($this->lng->txt('personal_settings'));
    }

    //
    //
    //	PASSWORD FORM
    //
    //

    public function showPassword(
        bool $a_no_init = false,
        bool $hide_form = false
    ): void {
        global $DIC;

        $ilTabs = $DIC['ilTabs'];
        $ilUser = $DIC['ilUser'];

        $this->__initSubTabs("showPersonalData");
        $ilTabs->activateTab("password");

        $this->setHeader();
        // check whether password of user have to be changed
        // due to first login or password of user is expired
        if ($ilUser->isPasswordChangeDemanded()) {
            $this->tpl->setOnScreenMessage('info', $this->lng->txt('password_change_on_first_login_demand'));
        } elseif ($ilUser->isPasswordExpired()) {
            $msg = $this->lng->txt('password_expired');
            $password_age = $ilUser->getPasswordAge();
            $this->tpl->setOnScreenMessage('info', sprintf($msg, $password_age));
        }

        if (!$a_no_init && !$hide_form) {
            $this->initPasswordForm();
        }
        $this->tpl->setContent(!$hide_form ? $this->form->getHTML() : '');
        $this->tpl->printToStdout();
    }

    public function initPasswordForm(): void
    {
        global $DIC;

        $lng = $DIC['lng'];
        $ilUser = $DIC['ilUser'];
        $ilSetting = $DIC['ilSetting'];

        $this->form = new ilPropertyFormGUI();

        // Check whether password change is allowed
        if ($this->allowPasswordChange()) {
            // The current password needs to be checked for verification
            // unless the user uses Shibboleth authentication with additional
            // local authentication for WebDAV.
            //if (
            //	($ilUser->getAuthMode(true) != ilAuthUtils::AUTH_SHIBBOLETH || !$ilSetting->get("shib_auth_allow_local"))
            //)
            $pw_info_set = false;
            if ($ilUser->getAuthMode(true) == ilAuthUtils::AUTH_LOCAL) {
                // current password
                $cpass = new ilPasswordInputGUI($lng->txt("current_password"), "current_password");
                $cpass->setInfo(ilSecuritySettingsChecker::getPasswordRequirementsInfo());
                $cpass->setRetype(false);
                $cpass->setSkipSyntaxCheck(true);
                $pw_info_set = true;
                // only if a password exists.
                if ($ilUser->getPasswd()) {
                    $cpass->setRequired(true);
                }
                $this->form->addItem($cpass);
            }

            // new password
            $ipass = new ilPasswordInputGUI($lng->txt("desired_password"), "new_password");
            if ($pw_info_set === false) {
                $ipass->setInfo(ilSecuritySettingsChecker::getPasswordRequirementsInfo());
            }
            $ipass->setRequired(true);
            $ipass->setUseStripSlashes(false);

            $this->form->addItem($ipass);
            $this->form->addCommandButton("savePassword", $lng->txt("save"));

            switch ($ilUser->getAuthMode(true)) {
                case ilAuthUtils::AUTH_LOCAL:
                    $this->form->setTitle($lng->txt("chg_password"));
                    break;

                case ilAuthUtils::AUTH_SHIBBOLETH:
                case ilAuthUtils::AUTH_CAS:
                    if (ilDAVActivationChecker::_isActive()) {
                        $this->form->setTitle($lng->txt("chg_ilias_and_webfolder_password"));
                    } else {
                        $this->form->setTitle($lng->txt("chg_ilias_password"));
                    }
                    break;
                default:
                    $this->form->setTitle($lng->txt("chg_ilias_password"));
                    break;
            }
            $this->form->setFormAction($this->ctrl->getFormAction($this));
        }
    }

    /**
     * Check, whether password change is allowed for user
     */
    protected function allowPasswordChange(): bool
    {
        global $DIC;

        $ilUser = $DIC['ilUser'];

        if (\ilSession::get('used_external_auth')) {
            return false;
        }

        $status = ilAuthUtils::isPasswordModificationEnabled($ilUser->getAuthMode(true));
        if ($status) {
            return true;
        }

        return \ilAuthUtils::isPasswordModificationHidden() && ($ilUser->isPasswordChangeDemanded() || $ilUser->isPasswordExpired());
    }

    public function savePassword(): void
    {
        global $DIC;

        $ilCtrl = $DIC['ilCtrl'];
        $ilUser = $DIC['ilUser'];

        // normally we should not end up here
        if (!$this->allowPasswordChange()) {
            $ilCtrl->redirect($this, "showPersonalData");
            return;
        }

        $this->initPasswordForm();
        if ($this->form->checkInput()) {
            $cp = $this->form->getItemByPostVar("current_password");
            $np = $this->form->getItemByPostVar("new_password");
            $error = false;

            // The old password needs to be checked for verification
            // unless the user uses Shibboleth authentication with additional
            // local authentication for WebDAV.
            #if ($ilUser->getAuthMode(true) != ilAuthUtils::AUTH_SHIBBOLETH || ! $ilSetting->get("shib_auth_allow_local"))
            if ($ilUser->getAuthMode(true) == ilAuthUtils::AUTH_LOCAL) {
                if (!ilUserPasswordManager::getInstance()->verifyPassword($ilUser, $this->entered_current_password)) {
                    $error = true;
                    $cp->setAlert($this->lng->txt('passwd_wrong'));
                }
            }

            if (!ilSecuritySettingsChecker::isPassword($this->entered_new_password, $custom_error)) {
                $error = true;
                if ($custom_error != '') {
                    $np->setAlert($custom_error);
                } else {
                    $np->setAlert($this->lng->txt("passwd_invalid"));
                }
            }
            $error_lng_var = '';
            if (!ilSecuritySettingsChecker::isPasswordValidForUserContext(
                $this->entered_new_password,
                $ilUser,
                $error_lng_var
            )) {
                $this->tpl->setOnScreenMessage('failure', $this->lng->txt('form_input_not_valid'));
                $np->setAlert($this->lng->txt($error_lng_var));
                $error = true;
            }
            if (
                ($ilUser->isPasswordExpired() || $ilUser->isPasswordChangeDemanded()) &&
                $this->entered_current_password == $this->entered_new_password) {
                $error = true;
                $np->setAlert($this->lng->txt("new_pass_equals_old_pass"));
            }

            if (!$error) {
                $ilUser->resetPassword($this->entered_new_password, $this->entered_new_password);
                if ($this->entered_current_password != $this->entered_new_password) {
                    $ilUser->setLastPasswordChangeToNow();
                    $ilUser->setPasswordPolicyResetStatus(false);
                    $ilUser->update();
                }

                if (ilSession::get('orig_request_target')) {
                    $this->tpl->setOnScreenMessage('success', $this->lng->txt('saved_successfully'), true);
                    $target = ilSession::get('orig_request_target');
                    ilSession::set('orig_request_target', '');
                    ilUtil::redirect($target);
                } else {
                    $this->tpl->setOnScreenMessage('success', $this->lng->txt('saved_successfully'));
                    $this->showPassword(true, true);
                    return;
                }
            }
        }
        $this->form->setValuesByPost();
        $this->showPassword(true);
    }

    //
    //
    //	GENERAL SETTINGS FORM
    //
    //

    public function workWithUserSetting(string $setting): bool
    {
        return $this->user_settings_config->isVisibleAndChangeable($setting);
    }

    public function userSettingVisible(string $setting): bool
    {
        return $this->user_settings_config->isVisible($setting);
    }

    public function userSettingEnabled(string $setting): bool
    {
        return $this->user_settings_config->isChangeable($setting);
    }

    public function showGeneralSettings(bool $a_no_init = false): void
    {
        global $DIC;

        $ilTabs = $DIC['ilTabs'];

        $this->__initSubTabs("showPersonalData");
        $ilTabs->activateTab("general_settings");

        $this->setHeader();

        if (!$a_no_init) {
            $this->initGeneralSettingsForm();
        }
        $this->tpl->setContent($this->form->getHTML());
        $this->tpl->printToStdout();
    }

    public function initGeneralSettingsForm(): void
    {
        global $DIC;

        $lng = $DIC['lng'];
        $ilUser = $DIC['ilUser'];
        $styleDefinition = $DIC['styleDefinition'];
        $ilSetting = $DIC['ilSetting'];

        $this->form = new ilPropertyFormGUI();

        // language
        if ($this->userSettingVisible("language")) {
            $languages = $this->lng->getInstalledLanguages();
            $options = array();
            foreach ($languages as $lang_key) {
                $options[$lang_key] = ilLanguage::_lookupEntry($lang_key, "meta", "meta_l_" . $lang_key);
            }

            $si = new ilSelectInputGUI($this->lng->txt("language"), "language");
            $si->setOptions($options);
            $si->setValue($ilUser->getLanguage());
            $si->setDisabled(
                $ilSetting->get("usr_settings_disable_language") === '1'
                || count($options) <= 1
            );
            $this->form->addItem($si);
        }

        // skin/style
        if ($this->userSettingVisible("skin_style")) {
            $skins = $styleDefinition->getAllSkins();
            if (is_array($skins)) {
                $si = new ilSelectInputGUI($this->lng->txt("skin_style"), "skin_style");

                $options = array();
                foreach ($skins as $skin) {
                    foreach ($skin->getStyles() as $style) {
                        if (!ilSystemStyleSettings::_lookupActivatedStyle($skin->getId(), $style->getId()) || $style->isSubstyle()) {
                            continue;
                        }

                        $options[$skin->getId() . ":" . $style->getId()] = $skin->getName() . " / " . $style->getName();
                    }
                }
                $si->setOptions($options);
                $si->setValue($ilUser->skin . ":" . $ilUser->prefs["style"]);
                $si->setDisabled((bool) $ilSetting->get("usr_settings_disable_skin_style"));
                $this->form->addItem($si);
            }
        }

        // help tooltips
        $module_id = (int) $ilSetting->get("help_module");
        if (((int) OH_REF_ID > 0 || $module_id > 0) && $ilUser->getLanguage() == "de" &&
            $ilSetting->get("help_mode") != "1") {
            $this->lng->loadLanguageModule("help");
            $cb = new ilCheckboxInputGUI($this->lng->txt("help_toggle_tooltips"), "help_tooltips");
            $cb->setChecked(!($ilUser->prefs["hide_help_tt"] ?? false));
            $cb->setInfo($this->lng->txt("help_toggle_tooltips_info"));
            $this->form->addItem($cb);
        }

        // hits per page
        if ($this->userSettingVisible("hits_per_page")) {
            $si = new ilSelectInputGUI($this->lng->txt("hits_per_page"), "hits_per_page");

            $hits_options = array(10,15,20,30,40,50,100,9999);
            $options = array();

            foreach ($hits_options as $hits_option) {
                $hstr = ($hits_option == 9999)
                    ? $this->lng->txt("no_limit")
                    : $hits_option;
                $options[$hits_option] = $hstr;
            }
            $si->setOptions($options);
            $si->setValue($ilUser->prefs["hits_per_page"]);
            $si->setDisabled((bool) $ilSetting->get("usr_settings_disable_hits_per_page"));
            $this->form->addItem($si);
        }

        // Store last visited
        $lv = new ilSelectInputGUI($this->lng->txt("user_store_last_visited"), "store_last_visited");
        $options = array(
            0 => $this->lng->txt("user_lv_keep_entries"),
            1 => $this->lng->txt("user_lv_keep_only_for_session"),
            2 => $this->lng->txt("user_lv_do_not_store"));
        $lv->setOptions($options);
        $last_visited = (int) ($ilUser->prefs["store_last_visited"] ?? 0);
        $lv->setValue($last_visited);
        $this->form->addItem($lv);


        if (ilSessionReminder::isGloballyActivated()) {
            $cb = new ilCheckboxInputGUI($this->lng->txt('session_reminder'), 'session_reminder_enabled');
            $cb->setInfo($this->lng->txt('session_reminder_info'));
            $cb->setValue(1);
            $cb->setChecked((bool) $ilUser->getPref('session_reminder_enabled'));

            $expires = ilSession::getSessionExpireValue();
            $lead_time_gui = new ilNumberInputGUI($this->lng->txt('session_reminder_lead_time'), 'session_reminder_lead_time');
            $lead_time_gui->setInfo(sprintf($this->lng->txt('session_reminder_lead_time_info'), ilDatePresentation::secondsToString($expires, true)));

            $min_value = ilSessionReminder::MIN_LEAD_TIME;
            $max_value = max($min_value, ($expires / 60) - 1);

            $current_user_value = $ilUser->getPref('session_reminder_lead_time');
            if ($current_user_value < $min_value ||
               $current_user_value > $max_value) {
                $current_user_value = ilSessionReminder::SUGGESTED_LEAD_TIME;
            }
            $value = min(
                max(
                    $min_value,
                    $current_user_value
                ),
                $max_value
            );

            $lead_time_gui->setValue($value);
            $lead_time_gui->setSize(3);
            $lead_time_gui->setMinValue($min_value);
            $lead_time_gui->setMaxValue($max_value);
            $cb->addSubItem($lead_time_gui);

            $this->form->addItem($cb);
        }

        // calendar settings (copied here to be reachable when calendar is inactive)
        // they cannot be hidden/deactivated

        $lng->loadLanguageModule("dateplaner");
        $user_settings = ilCalendarUserSettings::_getInstanceByUserId($ilUser->getId());

        $select = new ilSelectInputGUI($lng->txt('cal_user_timezone'), 'timezone');
        $select->setOptions(ilCalendarUtil::_getShortTimeZoneList());
        $select->setInfo($lng->txt('cal_timezone_info'));
        $select->setValue($user_settings->getTimeZone());
        $this->form->addItem($select);

        $year = date("Y");
        $select = new ilSelectInputGUI($lng->txt('cal_user_date_format'), 'date_format');
        $select->setOptions(array(
            ilCalendarSettings::DATE_FORMAT_DMY => '31.10.' . $year,
            ilCalendarSettings::DATE_FORMAT_YMD => $year . "-10-31",
            ilCalendarSettings::DATE_FORMAT_MDY => "10/31/" . $year));
        $select->setInfo($lng->txt('cal_date_format_info'));
        $select->setValue($user_settings->getDateFormat());
        $this->form->addItem($select);

        $select = new ilSelectInputGUI($lng->txt('cal_user_time_format'), 'time_format');
        $select->setOptions(array(
            ilCalendarSettings::TIME_FORMAT_24 => '13:00',
            ilCalendarSettings::TIME_FORMAT_12 => '1:00pm'));
        $select->setInfo($lng->txt('cal_time_format_info'));
        $select->setValue($user_settings->getTimeFormat());
        $this->form->addItem($select);


        // starting point
        if (ilUserUtil::hasPersonalStartingPoint()) {
            $this->lng->loadLanguageModule("administration");
            $si = new ilRadioGroupInputGUI($this->lng->txt("adm_user_starting_point"), "usr_start");
            $si->setRequired(true);
            $si->setInfo($this->lng->txt("adm_user_starting_point_info"));
            $def_opt = new ilRadioOption($this->lng->txt("adm_user_starting_point_inherit"), 0);
            $def_opt->setInfo($this->lng->txt("adm_user_starting_point_inherit_info"));
            $si->addOption($def_opt);
            foreach (ilUserUtil::getPossibleStartingPoints() as $value => $caption) {
                if ($value === ilUserUtil::START_REPOSITORY_OBJ) {
                    continue;
                }
                $si->addOption(new ilRadioOption($caption, $value));
            }
            $si->setValue(ilUserUtil::hasPersonalStartPointPref()
                ? ilUserUtil::getPersonalStartingPoint()
                : 0);
            $this->form->addItem($si);

            // starting point: repository object
            $repobj = new ilRadioOption($lng->txt("adm_user_starting_point_object"), ilUserUtil::START_REPOSITORY_OBJ);
            $repobj_id = new ilTextInputGUI($lng->txt("adm_user_starting_point_ref_id"), "usr_start_ref_id");
            $repobj_id->setInfo($lng->txt("adm_user_starting_point_ref_id_info"));
            $repobj_id->setRequired(true);
            $repobj_id->setSize(5);
            if ($si->getValue() == ilUserUtil::START_REPOSITORY_OBJ) {
                $start_ref_id = ilUserUtil::getPersonalStartingObject();
                $repobj_id->setValue($start_ref_id);
                if ($start_ref_id) {
                    $start_obj_id = ilObject::_lookupObjId($start_ref_id);
                    if ($start_obj_id) {
                        $repobj_id->setInfo($lng->txt("obj_" . ilObject::_lookupType($start_obj_id)) .
                            ": " . ilObject::_lookupTitle($start_obj_id));
                    }
                }
            }
            $repobj->addSubItem($repobj_id);
            $si->addOption($repobj);
        }

        // selector for unicode characters
        global $DIC;

        $ilSetting = $DIC['ilSetting'];
        if ($ilSetting->get('char_selector_availability') > 0) {
            $char_selector = new ilCharSelectorGUI(ilCharSelectorConfig::CONTEXT_USER);
            $char_selector->getConfig()->setAvailability((int) $ilUser->getPref('char_selector_availability'));
            $char_selector->getConfig()->setDefinition((string) $ilUser->getPref('char_selector_definition'));
            $char_selector->addFormProperties($this->form);
            $char_selector->setFormValues($this->form);
        }

        $this->form->addCommandButton("saveGeneralSettings", $lng->txt("save"));
        $this->form->setTitle($lng->txt("general_settings"));
        $this->form->setFormAction($this->ctrl->getFormAction($this));
    }

    /**
     * Save general settings
     */
    public function saveGeneralSettings(): void
    {
        global $DIC;

        $tpl = $DIC['tpl'];
        $lng = $DIC['lng'];
        $ilCtrl = $DIC['ilCtrl'];
        $ilUser = $DIC['ilUser'];
        $ilSetting = $DIC->settings();

        $this->initGeneralSettingsForm();
        if ($this->form->checkInput()) {
            if ($this->workWithUserSetting("skin_style")) {
                //set user skin and style
                if ($this->form->getInput("skin_style") != "") {
                    $sknst = explode(":", $this->form->getInput("skin_style"));

                    if ($ilUser->getPref("style") != $sknst[1] ||
                        $ilUser->getPref("skin") != $sknst[0]) {
                        $ilUser->setPref("skin", $sknst[0]);
                        $ilUser->setPref("style", $sknst[1]);
                    }
                }
            }

            // language
            if ($this->workWithUserSetting("language")) {
                $ilUser->setLanguage($this->form->getInput("language"));
            }

            // hits per page
            if ($this->workWithUserSetting("hits_per_page")) {
                if ($this->form->getInput("hits_per_page") != "") {
                    $ilUser->setPref("hits_per_page", $this->form->getInput("hits_per_page"));
                }
            }

            // help tooltips
            $module_id = (int) $ilSetting->get("help_module");
            if (((int) OH_REF_ID > 0 || $module_id > 0) && $ilUser->getLanguage() == "de" &&
                $ilSetting->get("help_mode") != "1") {
                $ilUser->setPref("hide_help_tt", (int) !$this->form->getInput("help_tooltips"));
            }

            // store last visited?
            global $DIC;

            $ilNavigationHistory = $DIC['ilNavigationHistory'];
            $ilUser->setPref("store_last_visited", (int) $this->form->getInput("store_last_visited"));
            if ((int) $this->form->getInput("store_last_visited") > 0) {
                $ilNavigationHistory->deleteDBEntries();
                if ((int) $this->form->getInput("store_last_visited") == 2) {
                    $ilNavigationHistory->deleteSessionEntries();
                }
            }

            // session reminder
            if (ilSessionReminder::isGloballyActivated()) {
                $ilUser->setPref('session_reminder_enabled', (int) $this->form->getInput('session_reminder_enabled'));
                $ilUser->setPref('session_reminder_lead_time', $this->form->getInput('session_reminder_lead_time'));
            }

            // starting point
            if (ilUserUtil::hasPersonalStartingPoint()) {
                $s_ref_id = $this->form->getInput('usr_start_ref_id');
                $s_ref_id = ($s_ref_id == "")
                    ? null
                    : (int) $s_ref_id;
                ilUserUtil::setPersonalStartingPoint(
                    (int) $this->form->getInput('usr_start'),
                    $s_ref_id
                );
            }

            // selector for unicode characters
            global $DIC;

            $ilSetting = $DIC['ilSetting'];
            if ($ilSetting->get('char_selector_availability') > 0) {
                $char_selector = new ilCharSelectorGUI(ilCharSelectorConfig::CONTEXT_USER);
                $char_selector->getFormValues($this->form);
                $ilUser->setPref('char_selector_availability', $char_selector->getConfig()->getAvailability());
                $ilUser->setPref('char_selector_definition', $char_selector->getConfig()->getDefinition());
            }

            $ilUser->update();

            // calendar settings
            $user_settings = ilCalendarUserSettings::_getInstanceByUserId($ilUser->getId());
            $user_settings->setTimeZone($this->form->getInput("timezone"));
            $user_settings->setDateFormat((int) $this->form->getInput("date_format"));
            $user_settings->setTimeFormat((int) $this->form->getInput("time_format"));
            $user_settings->save();

            $this->tpl->setOnScreenMessage('success', $lng->txtlng("common", "msg_obj_modified", $ilUser->getLanguage()), true);

            $ilCtrl->redirect($this, "showGeneralSettings");
        }

        $this->form->setValuesByPost();
        $this->showGeneralSettings(true);
    }

    /**
     * Delete own account dialog - 1st confirmation
     */
    protected function deleteOwnAccount1(): void
    {
        global $DIC;

        $ilTabs = $DIC['ilTabs'];
        $ilToolbar = $DIC['ilToolbar'];
        $ilUser = $DIC['ilUser'];
        $ilSetting = $DIC['ilSetting'];

        if (!(bool) $ilSetting->get('user_delete_own_account') ||
            $ilUser->getId() == SYSTEM_USER_ID) {
            $this->ctrl->redirect($this, "showGeneralSettings");
        }

        // too make sure
        $ilUser->removeDeletionFlag();

        $this->setHeader();
        $this->__initSubTabs("deleteOwnAccount");
        $ilTabs->activateTab("delacc");

        $this->tpl->setOnScreenMessage('info', $this->lng->txt('user_delete_own_account_info'));
        $ilToolbar->addButton(
            $this->lng->txt('btn_next'),
            $this->ctrl->getLinkTarget($this, 'deleteOwnAccount2')
        );

        $this->tpl->printToStdout();
    }

    /**
     * Delete own account dialog - login redirect
     */
    protected function deleteOwnAccount2(): void
    {
        global $DIC;

        $ilTabs = $DIC['ilTabs'];
        $ilUser = $DIC['ilUser'];
        $ilSetting = $DIC['ilSetting'];

        if (!(bool) $ilSetting->get('user_delete_own_account') ||
            $ilUser->getId() == SYSTEM_USER_ID) {
            $this->ctrl->redirect($this, "showGeneralSettings");
        }

        $this->setHeader();
        $this->__initSubTabs("deleteOwnAccount");
        $ilTabs->activateTab("delacc");

        $cgui = new ilConfirmationGUI();
        $cgui->setHeaderText($this->lng->txt('user_delete_own_account_logout_confirmation'));
        $cgui->setFormAction($this->ctrl->getFormAction($this));
        $cgui->setCancel($this->lng->txt("cancel"), "abortDeleteOwnAccount");
        $cgui->setConfirm($this->lng->txt("user_delete_own_account_logout_button"), "deleteOwnAccountLogout");
        $this->tpl->setContent($cgui->getHTML());
        $this->tpl->printToStdout();
    }

    protected function abortDeleteOwnAccount(): void
    {
        global $DIC;

        $ilCtrl = $DIC['ilCtrl'];
        $ilUser = $DIC['ilUser'];

        $ilUser->removeDeletionFlag();

        $this->tpl->setOnScreenMessage('info', $this->lng->txt("user_delete_own_account_aborted"), true);
        $ilCtrl->redirect($this, "showGeneralSettings");
    }

    protected function deleteOwnAccountLogout(): void
    {
        global $DIC;

        $ilUser = $DIC['ilUser'];

        // we are setting the flag and ending the session in the same step

        $ilUser->activateDeletionFlag();

        // see ilStartupGUI::doLogout()
        ilSession::setClosingContext(ilSession::SESSION_CLOSE_USER);
        $GLOBALS['DIC']['ilAuthSession']->logout();

        ilUtil::redirect("login.php?cmd=force_login&target=usr_" . md5("usrdelown"));
    }

    /**
     * Delete own account dialog - final confirmation
     */
    protected function deleteOwnAccount3(): void
    {
        global $DIC;

        $ilTabs = $DIC['ilTabs'];
        $ilUser = $DIC['ilUser'];
        $ilSetting = $DIC['ilSetting'];

        if (!(bool) $ilSetting->get('user_delete_own_account') ||
            $ilUser->getId() == SYSTEM_USER_ID ||
            !$ilUser->hasDeletionFlag()) {
            $this->ctrl->redirect($this, "showGeneralSettings");
        }

        $this->setHeader();
        $this->__initSubTabs("deleteOwnAccount");
        $ilTabs->activateTab("delacc");

        $cgui = new ilConfirmationGUI();
        $cgui->setHeaderText($this->lng->txt('user_delete_own_account_final_confirmation'));
        $cgui->setFormAction($this->ctrl->getFormAction($this));
        $cgui->setCancel($this->lng->txt("cancel"), "abortDeleteOwnAccount");
        $cgui->setConfirm($this->lng->txt("confirm"), "deleteOwnAccount4");
        $this->tpl->setContent($cgui->getHTML());
        $this->tpl->printToStdout();
    }

    /**
     * Delete own account dialog - action incl. notification email
     */
    protected function deleteOwnAccount4(): void
    {
        global $DIC;

        $ilUser = $DIC['ilUser'];
        $ilSetting = $DIC['ilSetting'];
        $ilLog = $DIC['ilLog'];

        if (!(bool) $ilSetting->get('user_delete_own_account') ||
            $ilUser->getId() == SYSTEM_USER_ID ||
            !$ilUser->hasDeletionFlag()) {
            $this->ctrl->redirect($this, "showGeneralSettings");
        }

        // build notification

        $ntf = new ilSystemNotification();
        $ntf->setLangModules(array("user"));
        $ntf->addAdditionalInfo("profile", $ilUser->getProfileAsString($this->lng), true);

        // mail message
        ilDatePresentation::setUseRelativeDates(false);
        $ntf->setIntroductionDirect(
            sprintf(
                $this->lng->txt("user_delete_own_account_email_body"),
                $ilUser->getLogin(),
                ILIAS_HTTP_PATH,
                ilDatePresentation::formatDate(new ilDateTime(time(), IL_CAL_UNIX))
            )
        );

        $message = $ntf->composeAndGetMessage($ilUser->getId(), null, 'read', true);
        $subject = $this->lng->txt("user_delete_own_account_email_subject");


        // send notification
        $user_email = $ilUser->getEmail();
        $admin_mail = $ilSetting->get("user_delete_own_account_email");
        /** @var ilMailMimeSenderFactory $senderFactory */
        $senderFactory = $GLOBALS["DIC"]["mail.mime.sender.factory"];

        $mmail = new ilMimeMail();
        $mmail->From($senderFactory->system());
        // to user, admin as bcc
        if ($user_email) {
            $mmail->To($user_email);
            $mmail->Bcc($admin_mail);
            $mmail->Subject($subject, true);
            $mmail->Body($message);
            $mmail->Send();
        }
        // admin only
        elseif ($admin_mail) {
            $mmail->To($admin_mail);
            $mmail->Subject($subject, true);
            $mmail->Body($message);
            $mmail->Send();
        }

        $ilLog->write("Account deleted: " . $ilUser->getLogin() . " (" . $ilUser->getId() . ")");

        $ilUser->delete();

        // terminate session
        $GLOBALS['DIC']['ilAuthSession']->logout();
        ilUtil::redirect("login.php?accdel=1");
    }
}
