<?
namespace Itech;

use \Bitrix\Main\Type\DateTime;
use Bitrix\Main\Application;

class Helper{

    static function getHoursStringByMinute($minute){

        return sprintf("%02d", floor($minute / 60)).':'.sprintf("%02d", $minute%60);
    }

    static function getMinutesByHourseString($value){

        $ar = explode(':',$value);

        return intval($ar[0])*60+intval($ar[1]);
    }

    static function generatePassword(){

        global $USER;

        $defGroup = \Bitrix\Main\Config\Option::get("main", "new_user_registration_def_group", "");

        if ($defGroup != "") {
            $groupID = explode(",", $defGroup);
            $arPolicy = $USER->GetGroupPolicy($groupID);
        } else {
            $arPolicy = $USER->GetGroupPolicy(array());
        }

        $passwordMinLength = intval($arPolicy["PASSWORD_LENGTH"]);

        if ($passwordMinLength <= 0)
            $passwordMinLength = 6;

        $passwordChars = array(
            "abcdefghijklnmopqrstuvwxyz",
            "ABCDEFGHIJKLNMOPQRSTUVWXYZ",
            "0123456789",
        );
        if ($arPolicy["PASSWORD_PUNCTUATION"] === "Y")
            $password_chars[] = ",.<>/?;:'\"[]{}\|`~!@#\$%^&*()-_+=";
        return randString($passwordMinLength + 2, $passwordChars);
    }

    static function getDataMailNewBooking($bookingId){

        $bookingId = intval($bookingId);

        \Bitrix\Main\Loader::includeModule('itech.dnavr');

        $booking = \Itech\Dnavr\BookingTable::getByPrimary($bookingId,[
            'select' => [
                '*',
                'USER',
                'GAMEMODE',
                'LOCATION'
            ]
        ])->fetchObject();

        if($booking){

            $arData = [
                'CUSTOMER' => $booking->getUser()?implode(' ',[$booking->getUser()->getName(),$booking->getUser()->getLastName()]):'',
                'EMAIL' =>  $booking->getUser()?$booking->getUser()->getEmail():'',
                'PHONE' => $booking->getUser()?$booking->getUser()->getPersonalPhone():'',
                'BOOKING_ID' => $booking->getId(),
                'BOOKING_CREATE_DATE' => $booking->getDateCreate()->format('d/m/Y - H:i'),
                'PAYMENT_STATUS' => $booking->isPayed()?'PAID':'UNPAID',
                'BOOKING_GAME_MODE' => $booking->getGamemode()->getName(),
                'BOOKING_GAME_NAME' => '',
                'BOOKING_DATE' => $booking->getDateStart()->format('l, F, jS'),
                'BOOKING_TIME' => $booking->getDateStart()->format('h:i A').' - '.$booking->getDateEnd()->format('h:i A'),
                'BOOKING_LOCATION_NAME' => $booking->getLocation()->getAddress(),
                'QUANTITY' => $booking->getHeadsetsCount(),
                'SUBTOTAL' => $booking->getSubtotal(),
                'DISCOUNT' => $booking->getDiscount(),
                'PRICE' => $booking->getPrice(),
                'TAXES' => $booking->getTaxes(),
                'PAID' => $booking->getSumPay()
            ];

            return $arData;
        }

        return false;
    }

    static function disabledBooking(){

        //Получаем все активные букинги за два дня, которе не оплачены

        $objDateTimeTo = new DateTime();

        $objDateTimeFrom = new DateTime();

        $objDateTimeTo->add("-30 minute");

        $objDateTimeFrom->add("-2 day");

        $bookings = \Itech\Dnavr\BookingTable::getlist([
            'filter' => [
                'ACTIVE' => true,
                '>=DATE_CREATE' => $objDateTimeFrom,
                '<=DATE_CREATE' => $objDateTimeTo
            ],
            'order' => [
                'DATE_CREATE' => 'DESC'
            ]
        ])->fetchCollection();

        foreach($bookings as $booking){

//
//            print_r($booking->getId());
//
//            break;

            //Проверка на создание букинга оператором

            $isCreateOperator = false;

            if($booking->getCreateUserId()){

                $arGroups = \Bitrix\Main\UserTable::getUserGroupIds($booking->getCreateUserId());

                if(in_array(OPERATOR_GROUP_ID,$arGroups) || in_array(OPERATOR__MODERATOR_GROUP_ID,$arGroups)){

                    $isCreateOperator = true;
                }
            }

            $payTr =  \Itech\Dnavr\TransactionTable::getList([
                'filter' => [
                    'ENTITY_TYPE' => 1,
                    'ENTITY_ID' => $booking->getId(),
                    'PAYED' => true
                ]
            ])->fetch();

            if(!$payTr && !$isCreateOperator){

                $booking->setActive(false);
                $booking->save();
            }
        }
    }

    static function getUserByAccessToken($token){

        $data = Application::getConnection()->query("select * from oauth_access_tokens where access_token='".$token."'")->fetch();

        if($data && $data['user_id']){

            $arGroups = \CUser::GetUserGroup($data['user_id']);

            if(in_array(7,$arGroups))
                return ['group'=>'admin'];
        }
        return ['group'=>'moderator'];
    }

    static function sendNotificationAfterVisit(){

        //Получаем все активные букинги за два дня, которым больше 12 часов

        $objDateTimeTo = new DateTime();

        $objDateTimeFrom = new DateTime();

        $objDateTimeTo->add("-12 hour");

        $objDateTimeFrom->add("-24 hour");

        $bookings = \Itech\Dnavr\BookingTable::getlist([
            'select' => [
                '*',
                'USER',
                'LOCATION'
            ],
            'filter' => [
                'ACTIVE' => true,
                '>=DATE_END' => $objDateTimeFrom,
                '<=DATE_END' => $objDateTimeTo
            ],
            'order' => [
                'DATE_END' => 'DESC'
            ]
        ])->fetchCollection();

        foreach($bookings as $booking){

            $rs = \Itech\Dnavr\NotificationTable::getList([
                'filter' => [
                    'ENTITY_ID' => $booking->getId(),
                    'TYPE' => 'AFTER_VISIT',
                    'SENDED' => true
                ]
            ]);

            if($rs->fetch())
                continue;

            \CEvent::Send("ITECH_NOTIFICATION_AFTER_VISIT", ['s1'], [
                'EMAIL' => $booking->getUser()->getEmail(),
                'LINK_TRIPADVISOR' => $booking->getLocation()->getLinkTripadvisor(),
                'LINK_GOOGLE' => $booking->getLocation()->getLinkGoogle()
            ], 'Y', '', []);

            \Itech\Dnavr\NotificationTable::add([
                'ENTITY_ID' => $booking->getId(),
                'TYPE' => 'AFTER_VISIT',
                'SENDED' => true
            ]);
        }
    }

    static function paidAnotherUpdate($bookingId, $userId, $tsList)
    {

        //ставим для букинга пометку paid_another
        $booking = \Itech\Dnavr\BookingTable::getByPrimary($bookingId, [
            'select' => [
                '*',
            ]
        ])->fetchObject();

        $booking->setPaidAnother(true);

        $booking->save();

        //все транзакции с пометкой paid_another
        $rsTransAll = \Itech\Dnavr\TransactionTable::getList([
            'select' => [
                '*'
            ],
            'filter' => [
                'ENTITY_ID' => $bookingId,
                'USER_ID' => $userId,
                'PAID_ANOTHER' => true
            ],
        ]);

        //все транзакции букинга с пометкой paid_another
        while ($arTrans = $rsTransAll->fetch()) {
            $tsListAll[] = $arTrans;
        }

        //массивы

        // $tsListNew - новые транзакции для добавления (их добавлять)
        // $tsListCur - транзакции которые существуют (их обновлять)
        foreach ($tsList as $ts) {
            if ($ts->TransactNew) {
                $tsListNew[] = $ts;
            } else {
                $tsListCur[] = $ts;
            }
        }

        //создаем новые транзакции
        foreach ($tsListNew as $ts) {

            $transaction = new \Itech\Dnavr\EO\Transaction();

            $transaction
                ->setXmlId(' ')
                ->setUserId($userId)
                ->setDateCreate(new DateTime())
                ->setDatePayed(new DateTime())
                ->setPrice($ts->price)
                ->setPayed(true)
                ->setEntityType(1)
                ->setEntityId($bookingId)
                ->setPaidAnother(true)
                ->setInformationPaidAnother($ts->informationPaidAnother);

            $transaction->generateHash();

            $transaction->save();
        }

        //собираем массив id's существующих транзакций
        foreach ($tsListAll as $tsAll) {
            $tsListAllIds[] = $tsAll['ID'];
        }

        //собираем массив id's изменяемых транзакций
        foreach ($tsListCur as $tsCur) {
            $tsListCurIds[] = $tsCur->id;
        }

        if(!$tsListCurIds) {
            $tsListCurIds = [];
        }

        //собираем массив id's транзакций которые нужно удалить
        $tsListDelete = array_diff($tsListAllIds, $tsListCurIds);

        //удаляем транзакции
        if($tsListDelete) {
            foreach ($tsListDelete as $tsDelete) {
                $transaction = \Itech\Dnavr\TransactionTable::getByPrimary($tsDelete)->fetchObject();
                $transaction->delete();
            }
        }

        //обновляем текущие транзакции
        foreach ($tsListCur as $tsCur) {

            $transaction = \Itech\Dnavr\TransactionTable::getByPrimary($tsCur->id)->fetchObject();
            if ($transaction) {

                $transaction
                    ->setXmlId(' ')
                    ->setPrice($tsCur->price)
                    ->setInformationPaidAnother($tsCur->informationPaidAnother)
                    ->setPaidAnother(true);

                $transaction->save();
            }
        }

    }

    static function paidAnotherRemoveTransact($bookingId, $userId, $tsList){

        //удаляем для букинга пометку paid_another
        $booking = \Itech\Dnavr\BookingTable::getByPrimary($bookingId, [
            'select' => [
                '*',
            ]
        ])->fetchObject();

        $booking->setPaidAnother(false);

        $booking->save();

        //все транзакции с пометкой paid_another
        $rsTransAll = \Itech\Dnavr\TransactionTable::getList([
            'select' => [
                '*'
            ],
            'filter' => [
                'ENTITY_ID' => $bookingId,
                'USER_ID' => $userId,
                'PAID_ANOTHER' => true
            ],
        ]);

        //все транзакции букинга с пометкой paid_another
        while ($arTrans = $rsTransAll->fetchObject()) {
            $tsListAll[] = $arTrans;
        }

        //удаляем все транзакции
        foreach ($tsListAll as $tsAll) {
            $tsAll->delete();
        }
    }

    static function attendedUpdate($bookingId, $value)
    {

        //ставим для букинга пометку attended
        $booking = \Itech\Dnavr\BookingTable::getByPrimary($bookingId, [
            'select' => [
                '*',
            ]
        ])->fetchObject();

        $booking->setAttended($value);

        $booking->save();

    }

    static function getTaxesCustom($price){

        $subtotal = $price / (1+0.2);
        $tax = $price - $subtotal;

        return round($tax,1);
    }
}
