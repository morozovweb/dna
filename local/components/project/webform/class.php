<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;
Loc::loadLanguageFile(__FILE__);

use Bitrix\Main\Loader;

Loader::includeModule("form");

class WebForm extends CBitrixComponent
{

    private $formData;
    private $formID;
    private $formUnique;

    private function getFormData($SID)
    {

        if (!isset($SID))
            throw new Exception('Не указан SID веб формы');

        $rsForm = CForm::GetBySID($SID);
        $arForm = $rsForm->Fetch();

        if (CForm::GetDataByID($arForm['ID'],
            $form,
            $questions,
            $answers,
            $dropdown,
            $multiselect)) {

            return array(
                'FORM' => $form,
                'QUESTIONS' => $questions,
                'ANSWERS' => $answers,
                'DROPDOWN' => $dropdown,
                'MULTISELECT' => $multiselect,
            );

        } else {
            throw new Exception('Данные веб формы не найдены');
        }

    }

    private function getTitle()
    {

        $result = $this->formData['FORM']['NAME'];
        if (!empty($this->arParams['TITLE'])) {
            if ($this->arParams['TITLE'] == 'N') {
                $result = '';
            } else {
                $result = $this->arParams['TITLE'];
            }
        }
        if ($result !== '') $result = "<div class='h3 color-black'>" . $result . "</div>";
        return $result;

    }

    private function getDescription()
    {

        $result = $this->formData['FORM']['DESCRIPTION'];
        if (!empty($this->arParams['DESCRIPTION'])) {
            if ($this->arParams['DESCRIPTION'] == 'N') {
                $result = '';
            } else {
                $result = $this->arParams['DESCRIPTION'];
            }
        }
        if ($result !== '') $result = "<div class='p'>" . $result . "</div>";
        return $result;

    }

    private function getErrorText($name)
    {
        $comment = $this->formData['QUESTIONS'][$name]['COMMENTS'];
        if ($name == 'phone_or_email') $comment = Loc::getMessage('Укажите контактные данные');
        return !empty($comment) ? $comment : Loc::getMessage('Обязательное поле');
    }

    private function validateForm()
    {

        global $USER;

        $result = [
            'STATUS' => 'OK'
        ];

        $request = \Bitrix\Main\Context::getCurrent()->getRequest();

        if ($this->arParams['NOT_AGREE'] !== 'Y' && $request->get('agree') !== 'yes') {
            $result['STATUS'] = 'ERROR';
            $result['ERRORS']['agree'] = Loc::getMessage('Необходимо дать согласие');
        }

        foreach ($this->arParams['REQUIRED'] as $name) {

            $val = $request->get($name);
            if (trim($val) == '') {
                $result['STATUS'] = 'ERROR';
                $result['ERRORS'][$name] = $this->getErrorText($name);
            } elseif ($name == 'email') {
                if (!check_email($val)) {
                    $result['STATUS'] = 'ERROR';
                    $result['ERRORS'][$name] = Loc::getMessage('Эл. почта указана неверно');
                }
            } elseif ($name == 'name') {
                if (!preg_match('/^[a-zA-Zа-яА-Я -]+$/ui', $val)) {
                    $result['STATUS'] = 'ERROR';
                    $result['ERRORS'][$name] = Loc::getMessage('Некорректное значение');
                }
            }

        }

        foreach ($this->arParams['REQUIRED_OR'] as $orName => $arOr) {

            $orFlag = false;
            foreach ($arOr as $name) {
                if (!empty($request->get($name))) {
                    $orFlag = true;
                }
            }
            if ($orFlag === false) {
                $result['STATUS'] = 'ERROR';
                $result['ERRORS'][$orName] = $this->getErrorText($orName);
            }

        }

        foreach ($this->arParams['REQUIRED_FILE'] as $arRequired) {

            $name = $arRequired['NAME'];
            $arFile = $request->getFile($name);

            if ($arFile['size'] == 0) {
                if ($arRequired['IF_NOT_EMPTY'] == true) continue;
                $result['STATUS'] = 'ERROR';
                $result['ERRORS'][$name] = $this->getErrorText($name);
                continue;
            }

            if ($arFile['size'] > $arRequired['SIZE']) {
                $result['STATUS'] = 'ERROR';
                $result['ERRORS'][$name] = 'Превышен размер файла';
            } else {
                $arFileType = explode('.', $arFile['name']);
                $fileType = $arFileType[count($arFileType)-1];
                $arRequiredType = explode('/', $arRequired['TYPE']);
                $typeFlag = false;
                foreach ($arRequiredType as $reqtype) {
                    if (trim($reqtype) == $fileType) $typeFlag = true;
                }
                if ($typeFlag == false) {
                    $result['STATUS'] = 'ERROR';
                    $result['ERRORS'][$name] = 'Неверный тип файла';
                }
            }

        }

        $captcha_sid = $request->get('captcha_sid');
        if (!empty($captcha_sid) && $result['STATUS'] == 'OK') {
            global $APPLICATION;
            $captcha_word = $request->get('captcha_word');
            if (empty($captcha_word)) {
                $result['STATUS'] = 'ERROR';
                $result['ERRORS']['captcha_word'] = Loc::getMessage('Введите символы с картинки');
            } else {
                if (!$APPLICATION->CaptchaCheckCode($captcha_word, $captcha_sid)) {
                    $result['STATUS'] = 'ERROR';
                    $result['ERRORS']['captcha_word'] = 'Неверно, попробуйте еще';
                    $result['UPDATE_CAPTCHA'] = 'yes';
                    $result['CAPTCHA__DATA'] = [
                        'captcha_sid' => htmlspecialcharsbx($this->arResult["CAPTCHACode"]),
                        'src' => '/local/tools/captcha.php?captcha_sid='.htmlspecialcharsbx($this->arResult["CAPTCHACode"]),
                    ];
                }
            }
        }

        if ($result['STATUS'] == 'OK') {

            // записываю результат формы в админку
            $arValues = [];
            foreach ($this->formData['ANSWERS'] as $name => $arAnswer) {
                $value = $request->get($name);
                if (empty($value)) {
                    $value = $request->getFile($name);
                    if (is_array($value['name'])) {
                        for ($i = 0; $i < count($value['name']); $i++) {
                            $itemValue = [
                                'name' => $value['name'][$i],
                                'type' => $value['type'][$i],
                                'tmp_name' => $value['tmp_name'][$i],
                                'error' => $value['error'][$i],
                                'size' => $value['size'][$i],
                            ];
                            $arValues['form_' . $arAnswer[0]['FIELD_TYPE'] . '_' . $arAnswer[$i]['ID']] = $itemValue;
                        }
                    } else {
                        $arValues['form_' . $arAnswer[0]['FIELD_TYPE'] . '_' . $arAnswer[0]['ID']] = $value;
                    }
                } else {
                    $arValues['form_' . $arAnswer[0]['FIELD_TYPE'] . '_' . $arAnswer[0]['ID']] = $value;
                }
            }

            $RESULT_ID = CFormResult::Add($this->formID, $arValues);
            CFormResult::Mail($RESULT_ID);

            $result['SUCCESS_TITLE'] = htmlspecialcharsback($this->arParams['SUCCESS_TITLE']);
            $result['SUCCESS_TEXT'] = htmlspecialcharsback($this->arParams['SUCCESS_TEXT']);
            $result['WEBFORM_SID'] = $this->arParams['WEBFORM_SID'];

        }

        return $result;

    }

    public function executeComponent()
    {

        $request = \Bitrix\Main\Context::getCurrent()->getRequest();

        $this->formData = $this->getFormData($this->arParams['WEBFORM_SID']);
        $this->formID = $this->formData['FORM']['ID'];
        $this->formUnique = $this->formData['FORM']['ID'] . '_' . $this->arParams['FORM_SECT'];

        $this->arResult['FORM_DATA'] = $this->formData;
        $this->arResult['FORM_ID'] = $this->formID;
        $this->arResult['FORM_UNIQUE'] = $this->formUnique;
        $this->arResult['TITLE'] = Project::fixPretext($this->getTitle());
        $this->arResult['DESCRIPTION'] = Project::fixPretext($this->getDescription());

        global $APPLICATION;
        $this->arResult["CAPTCHACode"] = $APPLICATION->CaptchaGetCode();

        if ($request->isPost()
            && check_bitrix_sessid('sid')
            && $request->get('form-unique') == $this->formUnique
            && $request->isAjaxRequest()
        ) {
            $this->arResult['RESPONSE'] = $this->validateForm();
        }

        $this->includeComponentTemplate();

    }

}

?>