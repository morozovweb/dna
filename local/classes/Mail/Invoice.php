<?
namespace Itech\Mail;

use Dompdf\Dompdf;

class Invoice{

    function getData($transId){

        \Bitrix\Main\Loader::includeModule('itech.dnavr');

        $trans = \Itech\Dnavr\TransactionTable::getByPrimary(intval($transId))->fetchObject();

        if($trans){

            if($trans->getEntityType() == 1){

                $booking = $trans->fillBooking();

                $user = $booking->fillUser();

                $location = $booking->fillLocation();

                $gameMode = $booking->fillGamemode();

                $sumPay = $booking->getSumPay();

                //массив пользователя (свойства)
                $arrUser = \Bitrix\Main\UserTable::getByPrimary($user->getId(), ['select' => ['ID', 'UF_ADDRESS']])->fetchObject();

                return  [
                    'LOCATION_ADDRESS' => $location->getAddress(),
                    'VAR_NUMBER' => 'GB268148183',
                    'DATE' => $trans->getDateCreate()->format('d/m/Y'),
                    'ID' => $trans->getId(),
                    'STATUS' => $trans->getPayed()?'Paid':'Unpaid',
                    'USER' => [
                        'FULL_NAME' => $user->getName().' '.$user->getLastName(),
                        'EMAIL' => $user->getEmail(),
                        'ADDRESS' => $arrUser->get('UF_ADDRESS')
                    ],
                    'GAME_MODE_NAME' => $gameMode->getName(),
                    'BOOKING_DATE_TIME' => $booking->getDateStart()->format('D jS M Y H:i'),
                    'QUANTITY' => $booking->getHeadsetsCount(),
                    'SUBTOTAL' => $booking->getSubtotal(),
                    'TAXES' => $booking->getTaxes(),
                    'TOTAL' => $booking->getPrice(),
                    'PAID' => $sumPay,
                    'AMOUNT_PAID' => $trans->getPrice(),
                    'PLAYERS' => $booking->getPlayersCount(),
                ];
            }
        }

        return false;
    }


    function getTemplate($data){

        if ($data['USER']['ADDRESS']) {
            $address = "Address: " . nl2br($data['USER']['ADDRESS']);
        }

        if ($data['PAID'] < $data['TOTAL']) {
            $data['STATUS'] = 'Unpaid';
        } else {
            $data['STATUS'] = 'Paid';
        }

        $data['SUBTOTAL'] = $data['SUBTOTAL'] - $data['TAXES'];

        if ($data['STATUS'] == "Paid") {
            return <<<ENDHTML
                <!DOCTYPE html>
                <html lang="en">
                
                <head>
                  <meta charset="UTF-8">
                  <meta name="viewport" content="width=device-width, initial-scale=1.0">
                  <title>Document</title>
                </head>
                
                <body>
                  <table style="width: 100%; font-family: Arial, Helvetica, sans-serif; padding: 30px;">
                    <tr>
                      <td colspan="3" style="text-align: right;"><img src="{$_SERVER["DOCUMENT_ROOT"]}/assets/logo.png" alt=""></td>
                    </tr>
                    <tr>
                      <td colspan="3" style="text-align: right;">
                        <strong>DNA VR</strong>
                      </td>
                    </tr>
                    <tr>
                      <td colspan="3" style="text-align: right; font-size: 10px;">
                        <p>{$data['LOCATION_ADDRESS']}</p>
                        <p>info@dnavr.co.uk - https://www.dnavr.co.uk
                        </p>
                        <p style="margin-bottom: 30px;">Vat Number: {$data['VAR_NUMBER']}
                        </p>
                      </td>
                    </tr>
                    <tr>
                      <td> <strong style="font-size: 24px;">INVOICE</strong> </td>
                    </tr>
                    <tr style="font-size: 10px;">
                      <td style="padding-bottom: 10px;">Sold to:</td>
                      <td><strong>Invoice Date:</strong></td>
                      <td style="text-align: right;"> {$data['DATE']}</td>
                    </tr>
                   
                    <tr style="font-size: 10px;">
                      <td style="font-size: 12px; line-height: 160%;"><strong>{$data['USER']['FULL_NAME']}</strong> </td>
                      <td><strong>Invoice Number:</strong></td>
                      <td style="text-align: right;"> {$data['ID']}</td>
                    </tr>
                    <tr style="font-size: 10px;">
                      <td>Email: {$data['USER']['EMAIL']}</td>
                      <td><strong>Invoice Status:</strong></td>
                      <td style="text-align: right; color: #009A44;"> <strong> {$data['STATUS']}</strong> </td>
                    </tr>
                    <tr style="font-size: 10px;">
                      <td>{$address}</td>
                    </tr>
                    <tr>
                      <td colspan="3" style="padding: 20px;"></td>
                    </tr>
                    <tr style="font-size: 11px;">
                      <td> <strong>Description</strong> </td>
                      <td> <strong>Quantity</strong> </td>
                      <td style="text-align: right;"> <strong>Price</strong> </td>
                    </tr>
                    <tr>
                      <td colspan="3">
                        <p style="height: 1px; background: #e5e5e5;"></p>
                      </td>
                    </tr>
                    <tr style="font-size: 11px;">
                      <td>{$data['GAME_MODE_NAME']} - Booking Date/Time: {$data['BOOKING_DATE_TIME']}</td>
                      <td>
                      Number of headsets: {$data['QUANTITY']}
                      <br>
                      Number of Players: {$data['PLAYERS']}</td>
                      <td style="text-align: right;">£{$data['TOTAL']}</td>
                    </tr>
                    <tr>
                      <td colspan="3" style="padding-bottom: 10px;">
                        <p style="height: 1px; background: #f2f2f2; "></p>
                      </td>
                    </tr>
                    <tr style="font-size: 11px;">
                      <td></td>
                      <td style="padding-bottom: 8px;"><strong>Subtotal:</strong> </td>
                      <td style="text-align: right;">£{$data['SUBTOTAL']} </td>
                    </tr>
                    <tr style="font-size: 11px;">
                      <td></td>
                      <td style="padding-bottom: 8px;"> <strong>VAT:</strong> </td>
                      <td style="text-align: right;"> £{$data['TAXES']}</td>
                    </tr>
                    <tr style="font-size: 11px;">
                      <td></td>
                      <td style="padding-bottom: 8px;"> <strong>Total:</strong> </td>
                      <td style="text-align: right;">£{$data['TOTAL']}</td>
                    </tr>
                    <tr>
                      <td></td>
                      <td colspan="2">
                        <p style="height: 1px; background: #e5e5e5; margin-top: 0;"></p>
                      </td>
                    </tr>
                    <tr style="font-size: 11px;">
                      <td></td>
                      <td style="color: #009A44;"> <strong>Amount Paid:</strong> </td>
                      <td style="color: #009A44; text-align: right;"> <strong>£{$data['PAID']}</strong> </td>
                    </tr>
                    <tr>
                      <td style="font-size: 14px;" colspan="3">
                        <p style="margin-top: 40px;"><strong>Thank you for your payment!</strong></p>
                        <p style="font-size: 10px;">If you have any questions regarding this invoice then please contact us.</p>
                      </td>
                    </tr>
                
                    </tr>
                  </table>
                </body>
                
                </html>
ENDHTML;
        }
        else {
            return <<<ENDHTML
                <!DOCTYPE html>
                <html lang="en">
                
                <head>
                  <meta charset="UTF-8">
                  <meta name="viewport" content="width=device-width, initial-scale=1.0">
                  <title>Document</title>
                </head>
                
                <body>
                  <table style="width: 100%; font-family: Arial, Helvetica, sans-serif; padding: 30px;">
                    <tr>
                      <td colspan="3" style="text-align: right;"><img src="{$_SERVER["DOCUMENT_ROOT"]}/assets/logo.png" alt=""></td>
                    </tr>
                    <tr>
                      <td colspan="3" style="text-align: right;">
                        <strong>DNA VR</strong>
                      </td>
                    </tr>
                    <tr>
                      <td colspan="3" style="text-align: right; font-size: 10px;">
                        <p>{$data['LOCATION_ADDRESS']}</p>
                        <p>info@dnavr.co.uk - https://www.dnavr.co.uk
                        </p>
                        <p style="margin-bottom: 30px;">Vat Number: {$data['VAR_NUMBER']}
                        </p>
                      </td>
                    </tr>
                    <tr>
                      <td> <strong style="font-size: 24px;">INVOICE</strong> </td>
                    </tr>
                    <tr style="font-size: 10px;">
                      <td style="padding-bottom: 10px;">Sold to:</td>
                      <td><strong>Invoice Date:</strong></td>
                      <td style="text-align: right;"> {$data['DATE']}</td>
                    </tr>
                  
                    <tr style="font-size: 10px;">
                      <td style="font-size: 12px; line-height: 160%;"><strong>{$data['USER']['FULL_NAME']}</strong> </td>
                      <td><strong>Invoice Number:</strong></td>
                      <td style="text-align: right;"> {$data['ID']}</td>
                    </tr>
                    <tr style="font-size: 10px;">
                      <td>Email: {$data['USER']['EMAIL']}</td>
                      <td><strong>Invoice Status 22:</strong></td>
                      <td style="text-align: right; color: #FF0000;"> <strong> {$data['STATUS']}</strong> </td>
                    </tr>
                    <tr style="font-size: 10px;">
                      <td>{$address}</td>
                    </tr>
                    <tr>
                      <td colspan="3" style="padding: 20px;"></td>
                    </tr>
                    <tr style="font-size: 11px;">
                      <td> <strong>Description</strong> </td>
                      <td> <strong>Quantity</strong> </td>
                      <td style="text-align: right;"> <strong>Price</strong> </td>
                    </tr>
                    <tr>
                      <td colspan="3">
                        <p style="height: 1px; background: #e5e5e5;"></p>
                      </td>
                    </tr>
                    <tr style="font-size: 11px;">
                      <td>{$data['GAME_MODE_NAME']} - Booking Date/Time: {$data['BOOKING_DATE_TIME']}</td>
                      <td>Number of headsets: {$data['QUANTITY']}
                      <br>
                      Number of Players: {$data['PLAYERS']}</td>
                      <td style="text-align: right;">£{$data['TOTAL']}</td>
                    </tr>
                    <tr>
                      <td colspan="3" style="padding-bottom: 10px;">
                        <p style="height: 1px; background: #f2f2f2; "></p>
                      </td>
                    </tr>
                    <tr style="font-size: 11px;">
                      <td></td>
                      <td style="padding-bottom: 8px;"><strong>Subtotal:</strong> </td>
                      <td style="text-align: right;">£{$data['SUBTOTAL']} </td>
                    </tr>
                    <tr style="font-size: 11px;">
                      <td></td>
                      <td style="padding-bottom: 8px;"> <strong>VAT:</strong> </td>
                      <td style="text-align: right;"> £{$data['TAXES']}</td>
                    </tr>
                    <tr style="font-size: 11px;">
                      <td></td>
                      <td style="padding-bottom: 8px;"> <strong>Total:</strong> </td>
                      <td style="text-align: right;">£{$data['TOTAL']}</td>
                    </tr>
                    <tr>
                      <td></td>
                      <td colspan="2">
                        <p style="height: 1px; background: #e5e5e5; margin-top: 0;"></p>
                      </td>
                    </tr>
                    <tr style="font-size: 11px;">
                      <td></td>
                      <td style="color: #FF0000;"> <strong>Total amount:</strong> </td>
                      <td style="color: #FF0000; text-align: right;"> <strong>£{$data['TOTAL']}</strong> </td>
                    </tr>
                    <tr style="font-size: 11px;">
                      <td><br><br></td>
                      <td style="color: #009A44;"> <strong>Amount paid:</strong> </td>
                      <td style="color: #009A44; text-align: right;"> <strong>£{$data['PAID']}</strong> </td>
                    </tr>
                    <tr>
                      <td style="font-size: 14px;" colspan="3">
                        <p style="margin-top: 40px;"><strong>Thank you for your payment!</strong></p>
                        <p style="font-size: 10px;">If you have any questions regarding this invoice then please contact us.</p>
                      </td>
                    </tr>
                
                    </tr>
                    
                  </table>
                  
                </body>
                
                </html>
ENDHTML;

        }

    }

    function sendPdf($transId){

        if($data = $this->getData($transId)){

            $html = $this->getTemplate($data);

            $dompdf = new Dompdf();

            $dompdf->loadHtml($html);

            $dompdf->setPaper('A4');

            $dompdf->render();

            $tmpFile = tmpfile();

            $metadata = stream_get_meta_data($tmpFile);

            file_put_contents($metadata['uri'], $dompdf->output());

            $metadata = stream_get_meta_data($tmpFile);

            $arFile = \CFile::MakeFileArray($metadata['uri']);

            $arFile['name'] = 'Invoice.pdf';

            $arFile["MODULE_ID"] = "itech.dnavr";

            $arFiles = [
                \CFile::SaveFile($arFile, "itech.dnavr")
            ];

            //Send - для быстрого прихода письма (тест)
            //SendImmediate - для прода

            return \CEvent::SendImmediate("ITECH_INVOICE", ['s1'], [
                    'EMAIL' => $data['USER']['EMAIL']
                ],'Y','',$arFiles)>0??false;
        }

        return false;
    }
}