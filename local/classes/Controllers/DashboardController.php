<?
namespace Itech\Controllers;

use function DI\string;
use Itech\Dnavr\BookingTable;
use Itech\Dnavr\EO\Location;
use Itech\Dnavr\EO\MinimumBookingNotice;
use Itech\Dnavr\EO\OpeningHour;
use Itech\Dnavr\EO\Room;
use Itech\Dnavr\EO\Schedule;
use Itech\Dnavr\MinimumBookingNoticeTable;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\App as Slim;
use OAuth2\Server;
use GuzzleHttp\Psr7\LazyOpenStream;

use \Bitrix\Main\Application,
    \Bitrix\Main\Entity\Base,
    \Itech\Dnavr\EO\Game,
    Itech\Dnavr\EO\BookingTime,
    Bitrix\Main\Error,
    Bitrix\Main\Result,
    Itech\Dnavr\EO\Booking,
    \Bitrix\Main\Type\DateTime,
    \Itech\Dnavr\EO\GameMode;


class DashboardController{

    private $server = null;

    public function __construct(Slim $app, Server $server) {

        \Bitrix\Main\Loader::includeModule('itech.dnavr');

        $this->server = $server;

        $app->post('', [$this, 'getList']);

        $app->get('/report/booking', [$this, 'getReportBookingCSV']);
        $app->get('/report/transaction', [$this, 'getReportTransactionCSV']);
        $app->get('/report/customers', [$this, 'getReportCustomersCSV']);

        $app->get('/report/not_pay', [$this, 'getReportNotPay']);
        $app->get('/report/block_slots', [$this, 'getReportBlockSlots']);
        $app->get('/report/block_all', [$this, 'getReportBlockAll']);

    }

    public function getList(Request $request, Response $response){

        $filter = json_decode($request->getBody()->getContents());

        $locationId = $filter->location??false;

        $arDateRange = $filter->dateRange??false;


        $reference = [];

        $locations = \Itech\Dnavr\LocationTable::getList(['filter' => [],'select' => ['ID','NAME']])->fetchCollection();

        foreach ($locations as $location){

            $reference['locations'][] = [
                'id' => $location->getId(),
                'name' => $location->getName()
            ];
        }

        if(!$locationId && $reference['locations'][0]['id']){

            $locationId = $reference['locations'][0]['id'];
        }

        if(!$arDateRange){

            $arDateRange = [
                date('01.m.Y'),
                date('t.m.Y')
            ];
        }

        $tableData = [];

        $resultBooking = \Itech\Dnavr\BookingTable::getList([
            'select' => [
                new \Bitrix\Main\Entity\ExpressionField('BOOKINGS', 'COUNT(*)'),
                new \Bitrix\Main\Entity\ExpressionField('SALES', 'SUM(PRICE)'),
            ],
            'filter' => [
                'ACTIVE' => true,
                'LOCATION_ID' => $locationId,
                '>=DATE_START' => new \Bitrix\Main\Type\DateTime($arDateRange[0]),
                '<=DATE_START' => new \Bitrix\Main\Type\DateTime($arDateRange[1].' 23:59:59'),
            ]
        ])->fetch();

        $resultTrans = \Itech\Dnavr\TransactionTable::getList([
            'select' => [
                new \Bitrix\Main\Entity\ExpressionField('PAID', 'SUM(%s)',['PRICE']),
            ],
            'filter' => [
                'BOOKING.LOCATION_ID' => $locationId,
                '>=BOOKING.DATE_START' => new \Bitrix\Main\Type\DateTime($arDateRange[0]),
                '<=BOOKING.DATE_START' => new \Bitrix\Main\Type\DateTime($arDateRange[1].' 23:59:59'),
                'ENTITY_TYPE' => 1,
                'PAYED' => true
            ]
        ])->fetch();

        $bookings = intval($resultBooking['BOOKINGS']);
        $sales = floatval($resultBooking['SALES']);
        $paid = floatval($resultTrans['PAID']);
        $unpaid = floatval($sales-$paid);
        $taxes = floatval($paid/100*VAT);

        $info = [
            "bookings" => $bookings,
            "sales" => round($sales,2),
            "paid" => round($paid,2),
            "unpaid" => round($unpaid,2),
            "taxes" => round($taxes,2)
        ];

        $gameModeInfo = [];

        $gameModes = \Itech\Dnavr\GameModeTable::getList()->fetchCollection();

        foreach ($gameModes as $gameMode){

            $resultBooking = \Itech\Dnavr\BookingTable::getList([
                'select' => [
                    new \Bitrix\Main\Entity\ExpressionField('BOOKINGS', 'COUNT(*)'),
                    new \Bitrix\Main\Entity\ExpressionField('SALES', 'SUM(PRICE)'),
                ],
                'filter' => [
                    'ACTIVE' => true,
                    'LOCATION_ID' => $locationId,
                    'GAMEMODE_ID' => $gameMode->getId(),
                    '>=DATE_START' => new \Bitrix\Main\Type\DateTime($arDateRange[0]),
                    '<=DATE_START' => new \Bitrix\Main\Type\DateTime($arDateRange[1].' 23:59:59'),
                ]
            ])->fetch();

            $gameModeInfo[] = [
                'name' => $gameMode->getName(),
                'bookings' => intval($resultBooking['BOOKINGS']),
                'sales' => floatval($resultBooking['SALES'])
            ];
        }

        return $response->withJson([
            'data' => [
                'info' => $info,
                'gamemode' => $gameModeInfo
            ],
            'reference' => $reference,
            'filter' => [
                'location' => $locationId,
                'dateRange' => $arDateRange
            ]
        ]);
    }

    public function getReportNotPay(Request $request, Response $response){

        $arDateRange = explode(',',$request->getParam('dateRange'));

        if(!is_array($arDateRange) && count($arDateRange)!=2){

            $arDateRange = [
                date('01.m.Y'),
                date('t.m.Y')
            ];
        }

        $filter = [
            'ACTIVE' => true,
            '>=DATE_START' => new \Bitrix\Main\Type\DateTime($arDateRange[0]),
            '<=DATE_START' => new \Bitrix\Main\Type\DateTime($arDateRange[1].' 23:59:59'),
        ];

        $bookings = \Itech\Dnavr\BookingTable::getList([
            'select' => [
                '*',
                'GAMEMODE',
                'USER',
                'GAME',
                'LOCATION',
            ],
            'filter' => $filter
        ])->fetchCollection();

        $datas = [
            [
                'Location',
                'Customer ID',
                'Customer First Name',
                'Customer Last Name',
                'Customer Email',
                'Customer Phone',
                'Booking ID',
                'Booking Status',
                'Booking Create Date',
                'Game mode',
                'Game',
                'Booking Start Date',
                'Booking End Date',
                'Booking Start Time',
                'Booking End Time',
                'Booking Quantity',
                'Booking Subtotal',
                'Booking Discount',
                'Booking Total Taxes',
                'Booking Total',
                'Paid',
                'Discount Code',
                'Voucher',
                'Discount Code value',
                'Voucher value',
                'Real money value',
                'Customer Credits value'
            ]
        ];

        $rs =\Itech\Dnavr\DiscountCouponTable::getList([
            'filter' => [
            ],

        ]);

        $arCoupon = [];

        while($ar = $rs->fetch()){
            $arCoupon[$ar["ID"]] = $ar["CODE"];
        }

        $rs =\Itech\Dnavr\VoucherTable::getList([
            'filter' => [
            ],

        ]);

        $arVoucher = [];

        while($ar = $rs->fetch()){
            $arVoucher[$ar["ID"]] = $ar["CODE"];
        }

        foreach ($bookings as $booking){

            if(!$booking->isPayed()){

                $d = [];

                $book = [];

                $couponId = $booking->getUsedCouponId();

                $voucherId = $booking->getUsedVoucherId();

                $realMoney = $booking->getPrice()-$booking->getCredits();

                $arData = [
                    'LOCATION' => $booking->getLocation()->getName(),
                    'USER_ID' => $booking->getUser()->getId(),
                    'USER_NAME' => $booking->getUser()->getName(),
                    'USER_LAST_NAME' => $booking->getUser()->getLastName(),
                    'USER_EMAIL' => $booking->getUser()->getEmail(),
                    'USER_PHONE' => $booking->getUser()->getPersonalPhone(),
                    'ID' => $booking->getId(),
                    'STATUS' => '?',
                    'DATE_CREATE' => $booking->getDateCreate()->format('d/m/Y'),
                    'GAMEMODE' => $booking->getGamemode()->getName(),
                    'GAME' => $booking->getGame()?$booking->getGame()->getName():'',
                    'DATE_START' => $booking->getDateStart()->format('d/m/Y'),
                    'DATE_END' => $booking->getDateEnd()->format('d/m/Y'),
                    'TIME_START' => $booking->getDateStart()->format('H:i'),
                    'TIME_END' => $booking->getDateEnd()->format('H:i'),
                    'COUNT' => $booking->getHeadsetsCount(),
                    'SUBTOTAL' => $booking->getSubtotal(),
                    'DISCOUNT' => $booking->getDiscount(),
                    'TAXES' => $booking->getTaxes(),
                    'TOTAL' => $booking->getPrice(),
                    'PAID' => $booking->isPayed(),
                    'NAME_COUPON' => ($couponId > 0) ? $arCoupon[$couponId] : "",
                    'NAME_VOUCHER' => ($voucherId > 0) ? $arVoucher[$voucherId] : "",
                    'Discount Code value' => ($couponId > 0 && $booking->getDiscount() > 0) ? $booking->getDiscount() : "",
                    'Voucher value' => ($voucherId && $booking->getDiscount() > 0) ? $booking->getDiscount() : "",
                    'Real money value' => ($realMoney > 0) ? $realMoney : "",
                    'Customer Credits value' => ($booking->getCredits() > 0) ? $booking->getCredits() : "",
                ];

                foreach ($arData as $item){

                    $d[] = $item;
                }

                $datas[] = $d;

            }

        }



        $contentBody = '';

        $count = count($datas);

        foreach ($datas as $i=>$row){

            $contentBody.=implode($row,';');

            if($i!=$count-1){

                $contentBody.="\r\n";
            }
        }
        $response->getBody()->write($contentBody);

        return $response
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename=reports_bookings_'.$arDateRange[0].'_'.$arDateRange[1].'_'.time().'.csv');



    }

    public function getReportBlockSlots(Request $request, Response $response){

        //Ищем локации
        $rsLoc = \Itech\Dnavr\LocationTable::getList([
            'select' => [
                '*',
            ],
        ])->fetchCollection();

        $arLocation = [];

        foreach ($rsLoc as $loc){
            $arLocation[$loc -> getId()] = $loc -> getName();
        }

        //Ищем rooms
        $rsRooms = \Itech\Dnavr\RoomTable::getList([
            'select' => [
                '*',
            ],
        ])->fetchCollection();

        foreach ($rsRooms as $room){
            $arRooms[$room -> getId()] = [
                "NAME" => $room -> getName(),
                "LOCATION" => $arLocation[$room -> getLocationId()],
            ];
        }

        $arDateRange = explode(',',$request->getParam('dateRange'));

        if(!is_array($arDateRange) && count($arDateRange)!=2){

            $arDateRange = [
                date('01.m.Y'),
                date('t.m.Y')
            ];
        }

        $filter = [
            '>=DATE_START' => new \Bitrix\Main\Type\DateTime($arDateRange[0]),
            '<=DATE_START' => new \Bitrix\Main\Type\DateTime($arDateRange[1].' 23:59:59'),
        ];

        $bookings = \Itech\Dnavr\BookingBlockTable::getList([
            'select' => [
                '*',
            ],
            'filter' => $filter
        ])->fetchCollection();

        $datas = [
            [
                'ID',
                'Location',
                'Room',
                'Date',
                'Count',
                'Comment'
            ]
        ];

        foreach ($bookings as $booking){

            $d = [];

            $arData = [
                'ID' => $booking->getId(),
                'Location' => $arRooms[$booking->getRoomId()]["LOCATION"],
                'Room' => $arRooms[$booking->getRoomId()]["NAME"],
                'Date' => $booking->getDateStart()->format('d/m/Y H:i'),
                'Count' => $booking->getCount(),
                'Comment' => $booking->getComment(),
            ];

            foreach ($arData as $item){
                $d[] = $item;
            }

            $datas[] = $d;

        }

        $contentBody = '';

        $count = count($datas);

        foreach ($datas as $i=>$row){

            $contentBody.=implode($row,';');

            if($i!=$count-1){

                $contentBody.="\r\n";
            }
        }
        $response->getBody()->write($contentBody);

        return $response
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename=reports_bookings'.time().'.csv');



    }

    public function getReportBlockAll(Request $request, Response $response){


        $arDateRange = explode(',',$request->getParam('dateRange'));

        if(!is_array($arDateRange) && count($arDateRange)!=2){

            $arDateRange = [
                date('01.m.Y'),
                date('t.m.Y')
            ];
        }

        $filter = [
            'ACTIVE' => true,
            '>=DATE_START' => new \Bitrix\Main\Type\DateTime($arDateRange[0]),
            '<=DATE_START' => new \Bitrix\Main\Type\DateTime($arDateRange[1].' 23:59:59'),
        ];

        $bookings = \Itech\Dnavr\BookingTable::getList([
            'select' => [
                '*',
                'GAMEMODE',
                'USER',
                'GAME',
                'LOCATION',
            ],
            'filter' => $filter
        ])->fetchCollection();

        $datas = [
            [
                'Location',
                'Customer ID',
                'Customer First Name',
                'Customer Last Name',
                'Customer Email',
                'Customer Phone',
                'Booking ID',
                'Booking Status',
                'Booking Create Date',
                'Game mode',
                'Game',
                'Booking Start Date',
                'Booking End Date',
                'Booking Start Time',
                'Booking End Time',
                'Booking Quantity',
                'Booking Subtotal',
                'Booking Discount',
                'Booking Total Taxes',
                'Booking Total',
                'Paid',
                'Discount Code',
                'Voucher',
                'Discount Code value',
                'Voucher value',
                'Real money value',
                'Customer Credits value'
            ]
        ];

        $rs =\Itech\Dnavr\DiscountCouponTable::getList([
            'filter' => [
            ],

        ]);

        $arCoupon = [];

        while($ar = $rs->fetch()){
            $arCoupon[$ar["ID"]] = $ar["CODE"];
        }

        $rs =\Itech\Dnavr\VoucherTable::getList([
            'filter' => [
            ],

        ]);

        $arVoucher = [];

        while($ar = $rs->fetch()){
            $arVoucher[$ar["ID"]] = $ar["CODE"];
        }

        foreach ($bookings as $booking){

            if(!$booking->isPayed()){

                $d = [];

                $book = [];

                $couponId = $booking->getUsedCouponId();

                $voucherId = $booking->getUsedVoucherId();

                $realMoney = $booking->getPrice()-$booking->getCredits();

                $arData = [
                    'LOCATION' => $booking->getLocation()->getName(),
                    'USER_ID' => $booking->getUser()->getId(),
                    'USER_NAME' => $booking->getUser()->getName(),
                    'USER_LAST_NAME' => $booking->getUser()->getLastName(),
                    'USER_EMAIL' => $booking->getUser()->getEmail(),
                    'USER_PHONE' => $booking->getUser()->getPersonalPhone(),
                    'ID' => $booking->getId(),
                    'STATUS' => '?',
                    'DATE_CREATE' => $booking->getDateCreate()->format('d/m/Y'),
                    'GAMEMODE' => $booking->getGamemode()->getName(),
                    'GAME' => $booking->getGame()?$booking->getGame()->getName():'',
                    'DATE_START' => $booking->getDateStart()->format('d/m/Y'),
                    'DATE_END' => $booking->getDateEnd()->format('d/m/Y'),
                    'TIME_START' => $booking->getDateStart()->format('H:i'),
                    'TIME_END' => $booking->getDateEnd()->format('H:i'),
                    'COUNT' => $booking->getHeadsetsCount(),
                    'SUBTOTAL' => $booking->getSubtotal(),
                    'DISCOUNT' => $booking->getDiscount(),
                    'TAXES' => $booking->getTaxes(),
                    'TOTAL' => $booking->getPrice(),
                    'PAID' => $booking->isPayed(),
                    'NAME_COUPON' => ($couponId > 0) ? $arCoupon[$couponId] : "",
                    'NAME_VOUCHER' => ($voucherId > 0) ? $arVoucher[$voucherId] : "",
                    'Discount Code value' => ($couponId > 0 && $booking->getDiscount() > 0) ? $booking->getDiscount() : "",
                    'Voucher value' => ($voucherId && $booking->getDiscount() > 0) ? $booking->getDiscount() : "",
                    'Real money value' => ($realMoney > 0) ? $realMoney : "",
                    'Customer Credits value' => ($booking->getCredits() > 0) ? $booking->getCredits() : "",
                ];

                foreach ($arData as $item){

                    $d[] = $item;
                }

                $datas[] = $d;

            }

        }

        $contentBody = '';
        $contentBody = 'Unpaid Bookings';
        $contentBody .= "\r\n";

        $count = count($datas);

        foreach ($datas as $i=>$row){

            $contentBody.=implode($row,';');

            if($i!=$count-1){

                $contentBody.="\r\n";
            }
        }

        $response->getBody()->write($contentBody);

        //Ищем локации
        $rsLoc = \Itech\Dnavr\LocationTable::getList([
            'select' => [
                '*',
            ],
        ])->fetchCollection();

        $arLocation = [];

        foreach ($rsLoc as $loc){
            $arLocation[$loc -> getId()] = $loc -> getName();
        }

        //Ищем rooms
        $rsRooms = \Itech\Dnavr\RoomTable::getList([
            'select' => [
                '*',
            ],
        ])->fetchCollection();

        foreach ($rsRooms as $room){
            $arRooms[$room -> getId()] = [
                "NAME" => $room -> getName(),
                "LOCATION" => $arLocation[$room -> getLocationId()],
            ];
        }

        $arDateRange = explode(',',$request->getParam('dateRange'));

        if(!is_array($arDateRange) && count($arDateRange)!=2){

            $arDateRange = [
                date('01.m.Y'),
                date('t.m.Y')
            ];
        }

        $filter = [
            '>=DATE_START' => new \Bitrix\Main\Type\DateTime($arDateRange[0]),
            '<=DATE_START' => new \Bitrix\Main\Type\DateTime($arDateRange[1].' 23:59:59'),
        ];

        $bookings = \Itech\Dnavr\BookingBlockTable::getList([
            'select' => [
                '*',
            ],
            'filter' => $filter
        ])->fetchCollection();

        $datas = [
            [
                'ID',
                'Location',
                'Room',
                'Date',
                'Count',
                'Comment'
            ]
        ];

        foreach ($bookings as $booking){

            $d = [];

            $arData = [
                'ID' => $booking->getId(),
                'Location' => $arRooms[$booking->getRoomId()]["LOCATION"],
                'Room' => $arRooms[$booking->getRoomId()]["NAME"],
                'Date' => $booking->getDateStart()->format('d/m/Y H:i'),
                'Count' => $booking->getCount(),
                'Comment' => $booking->getComment(),
            ];

            foreach ($arData as $item){
                $d[] = $item;
            }

            $datas[] = $d;

        }

        $contentBody = '';
        $contentBody .= "\r\n";
        $contentBody .= "\r\n";
        $contentBody .= "Blocked slots";
        $contentBody .= "\r\n";

        $count = count($datas);

        foreach ($datas as $i=>$row){

            $contentBody.=implode($row,';');

            if($i!=$count-1){

                $contentBody.="\r\n";
            }
        }
        $response->getBody()->write($contentBody);

        return $response
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename=reports_bookings'.time().'.csv');


    }


    public function getReportBookingCSV(Request $request, Response $response){

        $arDateRange = explode(',',$request->getParam('dateRange'));

        if(!is_array($arDateRange) && count($arDateRange)!=2){

            $arDateRange = [
                date('01.m.Y'),
                date('t.m.Y')
            ];
        }

        $locationId = intval($request->getParam('location'));

        $filter = [
            'ACTIVE' => true,
            '>=DATE_START' => new \Bitrix\Main\Type\DateTime($arDateRange[0]),
            '<=DATE_START' => new \Bitrix\Main\Type\DateTime($arDateRange[1].' 23:59:59'),
        ];

        if($request->getParam('all') != "y"){
            $filter["LOCATION_ID"] = $locationId;
        }

        $bookings = \Itech\Dnavr\BookingTable::getList([
            'select' => [
                '*',
                'GAMEMODE',
                'USER',
                'GAME',
                'LOCATION',
            ],
            'filter' => $filter
        ])->fetchCollection();

        $datas = [
            [
                'Location',
                'Customer ID',
                'Customer First Name',
                'Customer Last Name',
                'Customer Email',
                'Customer Phone',
                'Booking ID',
                'Booking Status',
                'Booking Create Date',
                'Game mode',
                'Game',
                'Booking Start Date',
                'Booking End Date',
                'Booking Start Time',
                'Booking End Time',
                'Booking Quantity',
                'Booking Subtotal',
                'Booking Discount',
                'Booking Total Taxes',
                'Booking Total',
                'Paid',
                'Discount Code',
                'Voucher',
                'Discount Code value',
                'Voucher value',
                'Real money value',
                'Customer Credits value'
            ]
        ];

        $rs =\Itech\Dnavr\DiscountCouponTable::getList([
            'filter' => [
            ],

        ]);

        $arCoupon = [];

        while($ar = $rs->fetch()){
            $arCoupon[$ar["ID"]] = $ar["CODE"];
        }

        $rs =\Itech\Dnavr\VoucherTable::getList([
            'filter' => [
            ],

        ]);

        $arVoucher = [];

        while($ar = $rs->fetch()){
            $arVoucher[$ar["ID"]] = $ar["CODE"];
        }

        foreach ($bookings as $booking){

            $d = [];

            $book = [];

            $couponId = $booking->getUsedCouponId();

            $voucherId = $booking->getUsedVoucherId();

            $realMoney = $booking->getPrice()-$booking->getCredits();

            $arData = [
                'LOCATION' => $booking->getLocation()->getName(),
                'USER_ID' => $booking->getUser()->getId(),
                'USER_NAME' => $booking->getUser()->getName(),
                'USER_LAST_NAME' => $booking->getUser()->getLastName(),
                'USER_EMAIL' => $booking->getUser()->getEmail(),
                'USER_PHONE' => $booking->getUser()->getPersonalPhone(),
                'ID' => $booking->getId(),
                'STATUS' => '?',
                'DATE_CREATE' => $booking->getDateCreate()->format('d/m/Y'),
                'GAMEMODE' => $booking->getGamemode()->getName(),
                'GAME' => $booking->getGame()?$booking->getGame()->getName():'',
                'DATE_START' => $booking->getDateStart()->format('d/m/Y'),
                'DATE_END' => $booking->getDateEnd()->format('d/m/Y'),
                'TIME_START' => $booking->getDateStart()->format('H:i'),
                'TIME_END' => $booking->getDateEnd()->format('H:i'),
                'COUNT' => $booking->getHeadsetsCount(),
                'SUBTOTAL' => $booking->getSubtotal(),
                'DISCOUNT' => $booking->getDiscount(),
                'TAXES' => $booking->getTaxes(),
                'TOTAL' => $booking->getPrice(),
                'PAID' => $booking->isPayed(),
                'NAME_COUPON' => ($couponId > 0) ? $arCoupon[$couponId] : "",
                'NAME_VOUCHER' => ($voucherId > 0) ? $arVoucher[$voucherId] : "",
                'Discount Code value' => ($couponId > 0 && $booking->getDiscount() > 0) ? $booking->getDiscount() : "",
                'Voucher value' => ($voucherId && $booking->getDiscount() > 0) ? $booking->getDiscount() : "",
                'Real money value' => ($realMoney > 0) ? $realMoney : "",
                'Customer Credits value' => ($booking->getCredits() > 0) ? $booking->getCredits() : "",
            ];

            foreach ($arData as $item){

                $d[] = $item;
            }

            $datas[] = $d;
        }



        $contentBody = '';

        $count = count($datas);

        foreach ($datas as $i=>$row){

            $contentBody.=implode($row,';');

            if($i!=$count-1){

                $contentBody.="\r\n";
            }
        }
        $response->getBody()->write($contentBody);

        return $response
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename=reports_bookings_'.$arDateRange[0].'_'.$arDateRange[1].'_'.time().'.csv');



    }

    public function getReportTransactionCSV(Request $request, Response $response){

        $arDateRange = explode(',',$request->getParam('dateRange'));

        if(!is_array($arDateRange) && count($arDateRange)!=2){

            $arDateRange = [
                date('01.m.Y'),
                date('t.m.Y')
            ];
        }

        $locationId = intval($request->getParam('location'));

        //Локации
        $locations = \Itech\Dnavr\LocationTable::getList([
            'select' => [
                'NAME',
                'ID'
            ],
            'filter' => [
                'ACTIVE' => true
            ]
        ])->fetchCollection();

        $arLocation = [];
        foreach ($locations as $location){
            $arLocation[$location["ID"]] = $location["NAME"];
        }


        $filter = [
            //'BOOKING.LOCATION_ID' => (!$request->getParam('all')) ? $locationId : "",
            '>=BOOKING.DATE_START' => new \Bitrix\Main\Type\DateTime($arDateRange[0]),
            '<=BOOKING.DATE_START' => new \Bitrix\Main\Type\DateTime($arDateRange[1].' 23:59:59'),
            '>=DATE_CREATE' => new \Bitrix\Main\Type\DateTime($arDateRange[0]),
            '<=DATE_CREATE' => new \Bitrix\Main\Type\DateTime($arDateRange[1].' 23:59:59'),
            'ENTITY_TYPE' => 1
        ];

        if($request->getParam('all') != "y"){
            $filter["BOOKING.LOCATION_ID"] = $locationId;
        }

        $transactions = \Itech\Dnavr\TransactionTable::getList([
            'select' => [
                '*',
                'BOOKING'
            ],
            'filter' => $filter
        ])->fetchCollection();



        $datas = [
            [
                'Location',
                'Customer ID',
                'Customer First Name',
                'Customer Last Name',
                'Customer Email',
                'Transaction ID',
                'Transaction Status',
                'Transaction Create Date',
                'Transaction Price',
                'Game mode'
            ]
        ];

        foreach ($transactions as $transaction){

            $transaction->getBooking()->fillUser();
            $transaction->getBooking()->fillGamemode();
            //$booking = $transaction->getBooking();

            $d = [];

            $arData = [
                'LOCATION' => $arLocation[$transaction->getBooking()->getLocationId()],
                'USER_ID' => $transaction->getBooking()->getUser()->getId(),
                'USER_NAME' => $transaction->getBooking()->getUser()->getName(),
                'USER_LAST_NAME' => $transaction->getBooking()->getUser()->getLastName(),
                'USER_EMAIL' => $transaction->getBooking()->getUser()->getEmail(),
                'ID' => $transaction->getId(),
                'STATUS' => $transaction->getPayed(),
                'DATE_CREATE' => $transaction->getDateCreate()->format('d/m/Y'),
                'PRICE' => $transaction->getPrice(),
                'GAMEMODE' => $transaction->getBooking()->getGamemode()->getName(),
            ];

            foreach ($arData as $item){

                $d[] = $item;
            }

            $datas[] = $d;
        }

        $contentBody = '';

        $count = count($datas);

        foreach ($datas as $i=>$row){

            $contentBody.=implode($row,';');

            if($i!=$count-1){

                $contentBody.="\r\n";
            }
        }

        $vouchers = \Itech\Dnavr\VoucherTable::getList([
            'filter' => [
                '>=DATE_CREATE' => new \Bitrix\Main\Type\DateTime($arDateRange[0]),
                '<=DATE_CREATE' => new \Bitrix\Main\Type\DateTime($arDateRange[1].' 23:59:59'),
            ]
        ]);

        foreach ($vouchers as $voucherTransact) {
            $voucherTransactDate[] = $voucherTransact["DATE_CREATE"];
        }

        $transactionsVouchers = \Itech\Dnavr\TransactionTable::getList([
            'filter' => [
                "DATE_CREATE" => $voucherTransactDate,
            ]
        ])->fetchCollection();

        foreach ($transactionsVouchers as $transactionVoucher){

            $d = [];

            $user = \Bitrix\Main\UserTable::getByPrimary($transactionVoucher->getUserId())->fetchObject();

            $arData = [
                'USER_ID' => $transactionVoucher->getUserId(),
                'USER_NAME' => $user["NAME"],
                'USER_LAST_NAME' => $user["LAST_NAME"],
                'CUSTOMER_EMAIL' => $user["EMAIL"],
                'ID' => $transactionVoucher->getId(),
                'STATUS' => $transactionVoucher->getPayed(),
                'DATE_CREATE' => $transactionVoucher["DATE_CREATE"]->format('d/m/Y'),
                'Transaction PRICE' => $transactionVoucher->getPrice(),
                'GAMEMODE' => "Gift voucher"
            ];

            foreach ($arData as $item){

                $d[] = $item;
            }

            $datas[] = $d;
        }


        $contentBody = '';

        $count = count($datas);

        foreach ($datas as $i=>$row){

            $contentBody.=implode($row,';');

            if($i!=$count-1){

                $contentBody.="\r\n";
            }
        }
        $response->getBody()->write($contentBody);

        return $response
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename=reports_transactions_'.$arDateRange[0].'_'.$arDateRange[1].'_'.time().'.csv');
    }

    public function getReportCustomersCSV(Request $request, Response $response){

        $arDateRange = explode(',',$request->getParam('dateRange'));

        if(!is_array($arDateRange) && count($arDateRange)!=2){

            $arDateRange = [
                date('01.m.Y'),
                date('t.m.Y')
            ];
        }

        if($request->getParam('all') != "y"){
            $locationId = intval($request->getParam('location'));
            $filter = [
                'ACTIVE' => true,
                '>=DATE_CREATE' => new \Bitrix\Main\Type\DateTime($arDateRange[0]),
                '<=DATE_CREATE' => new \Bitrix\Main\Type\DateTime($arDateRange[1].' 23:59:59'),
                'LOCATION_ID' => $locationId
            ];
        } else {
            $filter = [
                'ACTIVE' => true,
                '>=DATE_CREATE' => new \Bitrix\Main\Type\DateTime($arDateRange[0]),
                '<=DATE_CREATE' => new \Bitrix\Main\Type\DateTime($arDateRange[1].' 23:59:59'),
            ];
        }

        //получаем букинги пользователей в этом месяце
        $bookings = \Itech\Dnavr\BookingTable::getList([
            'select' => [
                'ID',
                'USER',
                'PRICE',
                'CREDITS'
            ],
            'filter' => $filter
        ])->fetchCollection();

        //массив всех букингов
        foreach ($bookings as $key => $booking) {
            $arBookings[$key]['NAME'] = $booking->getUser()->getName();
            $arBookings[$key]['LAST_NAME'] = $booking->getUser()->getLastName();
            $arBookings[$key]['PHONE'] = $booking->getUser()->getPersonalPhone();
            $arBookings[$key]['EMAIL'] = $booking->getUser()->getEmail();
            $arBookings[$key]['USER_ID'] = $booking->getUser()->getId();
            $arBookings[$key]['PAID_MONEY'] = $booking->getSumPay()-$booking->getCredits();
            $arBookings[$key]['UNPAID_MONEY'] = $booking->getPrice()-$booking->getSumPay();
        }

        //получаем id пользователей бронирований
        foreach ($arBookings as $booking) {
            $arIdsBookings[] = $booking['USER_ID'];
        }

        //убираем дупликаты id
        $arIdsBookings = array_unique($arIdsBookings);

        //массив пользователей и букингов
        foreach ($arBookings as $arBooking) {
            foreach ($arIdsBookings as $key => $resBooking) {
                if ($resBooking == $arBooking['USER_ID']) {
                    $rsBookings[$key][] = $arBooking;
                    break;
                }
            }
        }

        //ключ для букинга
        $i = 0;

        //итоговый массив букингов для вывода
        foreach ($rsBookings as $arBookings) {

            $paidMoney = 0;
            $unpaidMoney = 0;

            foreach ($arBookings as $keyChild => $booking) {
                $paidMoney = $booking['PAID_MONEY'] + $paidMoney;
                $unpaidMoney = $booking['UNPAID_MONEY'] + $unpaidMoney;

                if($keyChild == 0) {
                    $name = $booking['NAME'];
                    $lastName = $booking['LAST_NAME'];
                    $phone = $booking['PHONE'];
                    $email = $booking['EMAIL'];
                    $userId = $booking['USER_ID'];
                    $count = count($arBookings);
                }

            }

            $arResult['BOOKINGS'][$i]['NAME'] = $name;
            $arResult['BOOKINGS'][$i]['LAST_NAME'] = $lastName;
            $arResult['BOOKINGS'][$i]['PHONE'] = $phone;
            $arResult['BOOKINGS'][$i]['EMAIL'] = $email;
            $arResult['BOOKINGS'][$i]['USER_ID'] = $userId;
            $arResult['BOOKINGS'][$i]['PAID_MONEY'] = $paidMoney;
            $arResult['BOOKINGS'][$i]['UNPAID_MONEY'] = $unpaidMoney;
            //оставшаяся сумма кредитов на счету пользователя
            $account = \Itech\Dnavr\AccountTable::getByPrimary($userId)->fetchObject();
            $accountValue = 0;
            if($account){
                $accountValue = $account->getValue();
            }
            $arResult['BOOKINGS'][$i]['REMAINING_CREDITS'] = $accountValue;
            $arResult['BOOKINGS'][$i]['QUANTITY'] = $count;
            $arResult['BOOKINGS'][$i]['TYPE'] = 'Bookings';
            $i++;
        }


        //получаем ваучеры пользователей в этом месяце
        $vouchers = \Itech\Dnavr\VoucherTable::getList([
            'select' => [
                'ID',
                'USER',
                'PRICE'
            ],
            'filter' => [
                '>=DATE_CREATE' => new \Bitrix\Main\Type\DateTime($arDateRange[0]),
                '<=DATE_CREATE' => new \Bitrix\Main\Type\DateTime($arDateRange[1].' 23:59:59'),
            ]
        ])->fetchCollection();

        //массив всех ваучеров
        foreach ($vouchers as $key => $voucher) {
            $arVouchers[$key]['NAME'] = $voucher->getUser()->getName();
            $arVouchers[$key]['LAST_NAME'] = $voucher->getUser()->getLastName();
            $arVouchers[$key]['PHONE'] = $voucher->getUser()->getPersonalPhone();
            $arVouchers[$key]['EMAIL'] = $voucher->getUser()->getEmail();
            $arVouchers[$key]['USER_ID'] = $voucher->getUser()->getId();
            $arVouchers[$key]['PAID_MONEY'] = $voucher->getSumPay();
            $arVouchers[$key]['UNPAID_MONEY'] = $voucher->getPrice()-$voucher->getSumPay();
        }


        //получаем id пользователей с ваучерами
        foreach ($arVouchers as $voucher) {
            $arIdsVouchers[] = $voucher['USER_ID'];
        }

        //убираем дупликаты id
        $arIdsVouchers = array_unique($arIdsVouchers);

        //массив пользователей и ваучеров
        foreach ($arVouchers as $arVoucher) {
            foreach ($arIdsVouchers as $key => $resVoucher) {
                if ($resVoucher == $arVoucher['USER_ID']) {
                    $rsVouchers[$key][] = $arVoucher;
                    break;
                }
            }
        }

        //ключ для ваучера
        $i = 0;

        //итоговый массив ваучеров для вывода
        foreach ($rsVouchers as $arVouchers) {

            $paidMoney = 0;
            $unpaidMoney = 0;

            foreach ($arVouchers as $keyChild => $voucher) {
                $paidMoney = $voucher['PAID_MONEY'] + $paidMoney;
                $unpaidMoney = $voucher['UNPAID_MONEY'] + $unpaidMoney;

                if($keyChild == 0) {
                    $name = $voucher['NAME'];
                    $lastName = $voucher['LAST_NAME'];
                    $phone = $voucher['PHONE'];
                    $email = $voucher['EMAIL'];
                    $userId = $voucher['USER_ID'];
                    $count = count($arVouchers);
                }

            }

            $arResult['VOUCHERS'][$i]['NAME'] = $name;
            $arResult['VOUCHERS'][$i]['LAST_NAME'] = $lastName;
            $arResult['VOUCHERS'][$i]['EMAIL'] = $email;
            $arResult['VOUCHERS'][$i]['PHONE'] = $phone;
            $arResult['VOUCHERS'][$i]['USER_ID'] = $userId;
            $arResult['VOUCHERS'][$i]['PAID_MONEY'] = $paidMoney;
            $arResult['VOUCHERS'][$i]['UNPAID_MONEY'] = $unpaidMoney;
            //оставшаяся сумма кредитов на счету пользователя
            $account = \Itech\Dnavr\AccountTable::getByPrimary($userId)->fetchObject();
            $accountValue = 0;
            if($account){
                $accountValue = $account->getValue();
            }
            $arResult['VOUCHERS'][$i]['REMAINING_CREDITS'] = $accountValue;
            $arResult['VOUCHERS'][$i]['QUANTITY'] = $count;
            $arResult['VOUCHERS'][$i]['TYPE'] = 'Vouchers';
            $i++;
        }


        $datas = [
            [
                'Customer First Name',
                'Customer Last Name',
                'Customer Phone',
                'Customer Email',
                'Customer ID',
                'Paid money',
                'Unpaid money',
                'Quantity',
                'Remaining credits',
                'Type'
            ]
        ];

        //формируем букинги для вывода
        foreach ($arResult['BOOKINGS'] as $booking){

            $d = [];

            $arData = [
                'NAME' => $booking['NAME'],
                'LAST_NAME' => $booking['LAST_NAME'],
                'PHONE' => $booking['PHONE'],
                'EMAIL' => $booking['EMAIL'],
                'USER_ID' => $booking['USER_ID'],
                'PAID_MONEY' => $booking['PAID_MONEY'],
                'UNPAID_MONEY' => $booking['UNPAID_MONEY'],
                'COUNT' => $booking['QUANTITY'],
                'REMAINING_CREDITS' => $booking['REMAINING_CREDITS'],
                'TYPE' => $booking['TYPE']
            ];

            foreach ($arData as $item){

                $d[] = $item;
            }

            $datas[] = $d;
        }

        $contentBody = '';

        $count = count($datas);

        foreach ($datas as $i=>$row){

            $contentBody.=implode($row,';');

            if($i!=$count-1){

                $contentBody.="\r\n";
            }
        }

        //формируем ваучеры для вывода
        foreach ($arResult['VOUCHERS'] as $voucher){

            $d = [];

            $arData = [
                'NAME' => $voucher['NAME'],
                'LAST_NAME' => $voucher['LAST_NAME'],
                'PHONE' => $voucher['PHONE'],
                'EMAIL' => $voucher['EMAIL'],
                'USER_ID' => $voucher['USER_ID'],
                'PAID_MONEY' => $voucher['PAID_MONEY'],
                'UNPAID_MONEY' => $voucher['UNPAID_MONEY'],
                'COUNT' => $voucher['QUANTITY'],
                'REMAINING_CREDITS' => $voucher['REMAINING_CREDITS'],
                'TYPE' => $voucher['TYPE']
            ];

            foreach ($arData as $item){

                $d[] = $item;
            }

            $datas[] = $d;
        }


        $contentBody = '';

        $count = count($datas);

        foreach ($datas as $i=>$row){

            $contentBody.=implode($row,';');

            if($i!=$count-1){

                $contentBody.="\r\n";
            }
        }
        $response->getBody()->write($contentBody);

        return $response
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Content-Disposition', 'attachment; filename=reports_customers_'.$arDateRange[0].'_'.$arDateRange[1].'_'.time().'.csv');
    }
}