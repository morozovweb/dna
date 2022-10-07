<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;
Loc::loadLanguageFile(__FILE__);

use Bitrix\Main\Loader;

Loader::includeModule('subscribe');
Loader::includeModule("form");

class Subscribe extends CBitrixComponent{
    
    public function getResult(){

        $result = array();

        $request = \Bitrix\Main\Context::getCurrent()->getRequest();

        if ($request->isPost() && check_bitrix_sessid('subscribe_form_sid')){

            $result['ERRORS'] = $this->validateData();

            if (empty($result['ERRORS'])){

                $result = $this->addSubscribe();

            } else {

                $result['STATUS'] = 'ERROR';

            }

        }

        return $result;

    }

    private function validateData(){

        $errors = [];

        $request = \Bitrix\Main\Context::getCurrent()->getRequest();


        if ($request->get('agree') !== 'yes') {
            $errors['agree'] = Loc::getMessage('Необходимо дать согласие');
        }

        $email = $request->get('subscribe_email');
        if (empty($email)) {
            $errors['subscribe_email'] = Loc::getMessage('Введите e-mail');
        } elseif (!check_email($email)) {
            $errors['subscribe_email'] = Loc::getMessage('Неверный или некорректный e-mail адрес');
        }/* else {
            foreach (Project::EMAILS_DOMENS_ERROR as $domen) {
                if (strpos($email, $domen) !== false) {
                if (strpos($email, $domen) !== false) {
                    $errors['subscribe_email'] = Loc::getMessage('Укажите ваш рабочий e-mail');
                }
            }
        }*/

        //Проверяем наличие email в базе
        $subscr = new \CSubscription;

        $subscription = \CSubscription::GetByEmail($request->get('subscribe_email'));

        if ($subscription = $subscription->Fetch()) {
            $errors['subscribe_email'] = "Вы уже подписаны на нашу рассылку";
        }

        return $errors;

    }

    private function addSubscribe(){

        $request = \Bitrix\Main\Context::getCurrent()->getRequest();

        $subscr = new \CSubscription;

        $subscription = \CSubscription::GetByEmail($request->get('subscribe_email'));

        $arValues = [
            "form_text_38" => $request->get('subscribe_email'),
            "form_text_39" => $request->get('agree'),
        ];
        if ($RESULT_ID = CFormResult::Add(5, $arValues))
        {
            echo "Результат #".$RESULT_ID." успешно создан";
        }
        else
        {
            global $strError;
            echo $strError;
        }

        if ($subscription = $subscription->Fetch()) {

            $arSubscrID = [$this->arParams['SUBSCRIBE_ID']];
            $ID = $subscription['ID'];
            $arSubscrRub = \CSubscription::GetRubricArray($ID);
            if (!empty($arSubscrRub))
                $arSubscrID = array_merge($arSubscrID, $arSubscrRub);
            $arFields = [
                "RUB_ID" => $arSubscrID,
                "CONFIRMED" => "N",
            ];

            if ($subscr->Update($ID, $arFields)){

                \CSubscription::ConfirmEvent($ID);

                return [
                    'STATUS' => 'OK',
                    'SUCCESS_TITLE' => Loc::getMessage('Поздравляем!'),
                    'SUCCESS_TEXT' => Loc::getMessage('Вы успешно подписаны на рассылку!'),
                    'WEBFORM_SID' => 'SUBSCRIBE'
                ];

            } else {

                return [
                    'STATUS' => 'ERROR',
                    'ERRORS' => ['subscribe_email' => $subscr->LAST_ERROR]
                ];

            }

        } else {

            global $USER;
            $arFields = [
                "FORMAT" => "html",
                "EMAIL" => $request->get('subscribe_email'),
                "USER_ID" => $USER->GetID(),
                "RUB_ID" => [$this->arParams['SUBSCRIBE_ID']],
                "ACTIVE" => "Y",
                "CONFIRMED" => "N",
                "SEND_CONFIRM" => "Y"
            ];
            $subscr = new CSubscription;

            if ($RESULT_ID = $subscr->Add($arFields)) {

                return [
                    'STATUS' => 'OK',
                    'SUCCESS_TITLE' => Loc::getMessage('Поздравляем!'),
                    'SUCCESS_TEXT' => Loc::getMessage('Вы успешно подписаны на рассылку!'),
                    'WEBFORM_SID' => 'SUBSCRIBE'
                ];

            } else {

                return [
                    'STATUS' => 'ERROR',
                    'ERRORS' => ['subscribe_email' => $subscr->LAST_ERROR]
                ];

            }

        }

    }

    public function executeComponent(){

        $this->arResult = $this->getResult();

        $this->includeComponentTemplate();

    }

}
?>