<?
namespace Itech\Mail;

use Dompdf\Dompdf;

class Voucher{

    function getData($id){

        \Bitrix\Main\Loader::includeModule('itech.dnavr');

        $voucher = \Itech\Dnavr\VoucherTable::getByPrimary(intval($id))->fetchObject();

        if($voucher && $voucher->isPayed()){

            $user = $voucher->fillUser();

            return [
                'VOUCHER_ID' => $voucher->getId(),
                'VOUCHER_CREATE_DATE' => $voucher->getDateCreate()->format('d.m.Y H:i:s'),
                'FORWARD' => $voucher->getForward(),
                'USER' => [
                    'FULL_NAME' => $user->getName().' '.$user->getLastName(),
                    'EMAIL' => $user->getEmail(),
                    'PHONE' => $user->getPersonalPhone()
                ],
                'MESSAGE' => $voucher->getMessage(),
                'VALUE' => $voucher->getValue(),
                'TOTAL' => $voucher->getPrice(),
                'CODE' => $voucher->getCode(),
                'EMAIL' => $voucher->getEmail(),
                'PAYMENT_STATUS' => true,

            ];
        }

        return false;
    }

    function getTemplate($data){

        $html = <<<ENDHTML
        <!DOCTYPE html>
            <html>
                <head>
                  <meta charset="utf-8">
                  <link href="https://fonts.googleapis.com/css2?family=Maven+Pro&family=Ubuntu:wght@300;400&display=swap" rel="stylesheet">
                </head>
                
                <body>
                
                  <table style="width: 100%; background-color: #F2F7FC; border-radius: 40px;">
                    <tbody>
                      <tr>
                        <td colspan="2" align="center">
                          <img src="{$_SERVER["DOCUMENT_ROOT"]}/local/tmlmail/images/logo.png" />
                        </td>
                      </tr>
                      <tr>
                        <td colspan="2" align="center">
                          <img src="{$_SERVER["DOCUMENT_ROOT"]}/local/tmlmail/images/icon.png" />
                        </td>
                      </tr>
ENDHTML;

if($data['FORWARD']):
$html .= <<<ENDHTML
                      <tr>
                        <td style="padding-left: 32px; padding-top: 32px;">
                          <img src="{$_SERVER["DOCUMENT_ROOT"]}/local/tmlmail/images/image.png" />
                        </td>
                        <td style="padding-left: 16px; padding-right: 32px; padding-top: 16px;">
                          <p
                            style="font-family: Ubuntu; font-style: normal; font-weight: bold; font-size: 18px; line-height: 22px; color: #1A1A1A;">
                            <span style="color: #444A99;">{$data['USER']['FULL_NAME']}</span> has sent you a gift voucher and wanted to say...
                          </p>
                        </td>
                      </tr>
                      <tr>
                        <td colspan="2" style="padding-left: 32px; padding-right: 32px;">
                          <p
                            style="font-family: Ubuntu; font-style: normal; font-weight: 300; font-size: 40px; line-height: 100%; margin-top: 32px; margin-bottom: 32px;">
                            {$data['MESSAGE']}
                          </p>
                        </td>
                      </tr>
ENDHTML;
endif;

               $html .= <<<ENDHTML
                        <tr>
                        <td colspan="2"
                          style="font-family: Ubuntu; font-style: normal; font-weight: normal; font-size: 16px; line-height: 20px; color: #C4C4C4; padding-left: 32px; padding-right: 32px;">
                          Total Value
                        </td>
                      </tr>
                      <tr>
                        <td colspan="2"
                          style="font-family: Maven Pro; font-style: normal; font-weight: bold; font-size: 44px; line-height: 100%; color: #261DEB; padding-left: 32px; padding-right: 32px;">
                          £{$data['VALUE']}
                        </td>
                      </tr>
                      <tr>
                        <td colspan="2"
                          style="font-family: Ubuntu; font-style: normal; font-weight: normal; font-size: 16px; line-height: 20px; color: #C4C4C4; padding-left: 32px; padding-right: 32px;">
                          Code
                        </td>
                      </tr>
                      <tr>
                        <td colspan="2"
                          style="font-family: Maven Pro; font-style: normal; font-weight: bold; font-size: 44px; line-height: 100%; color: #261DEB; padding-left: 32px; padding-right: 32px;">
                          {$data['CODE']}
                        </td>
                      </tr>
                      <tr>
                        <td colspan="2" style="padding-left: 32px; padding-right: 32px;">
                          <p
                            style="font-family: Maven Pro; font-style: normal; font-weight: bold; font-size: 16px; line-height: 20px; color: #F2F7FC; padding-top: 16px; padding-bottom: 16px; background-color: #261DEB; text-align: center; border-radius: 24px;">
                            Please visit dnavr.co.uk to<br>redeem your gift voucher.
                          </p>
                        </td>
                      </tr>
                      <tr>
                        <td colspan="2"
                          style="padding-left: 32px; padding-right: 32px; padding-bottom: 8px; text-align: center; font-family: Ubuntu; font-size: 12px; line-height: 20px; letter-spacing: 0.05em; text-transform: uppercase;">
                          
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </body>
            </html>
ENDHTML;

               return $html;
    }

    function createPdf($id){

        if($data = $this->getData($id)){

            $html = $this->getTemplate($data);

            $dompdf = new Dompdf();

            $dompdf->loadHtml($html);

            $dompdf->setPaper('A4');

            $dompdf->render();

            return $dompdf;
        }

        return false;
    }

    function getPdf($id){

        if($dompdf = $this->createPdf($id)){

            $dompdf->stream('Voucher.pdf');
        }
    }

    function sendPdf($id){

        if($data = $this->getData($id)) {

            if ($dompdf = $this->createPdf($id)) {

                $tmpFile = tmpfile();

                $metadata = stream_get_meta_data($tmpFile);

                file_put_contents($metadata['uri'], $dompdf->output());

                $metadata = stream_get_meta_data($tmpFile);

                $arFile = \CFile::MakeFileArray($metadata['uri']);

                $arFile['name'] = 'Voucher.pdf';

                $arFile["MODULE_ID"] = "itech.dnavr";

                $arFiles = [
                    \CFile::SaveFile($arFile, "itech.dnavr")
                ];

                return \CEvent::Send("ITECH_FORWARD_VOUCHER", ['s1'], [
                        'EMAIL' => $data['EMAIL']
                    ], 'Y', '', $arFiles) > 0 ?? false;
            }
        }

        return false;
    }

    function getTemplateNew($data){

        $data['PAYMENT_STATUS'] = $data['PAYMENT_STATUS']?'<span class="green" style="color:#1FD066">PAID</span>':'<span class="red" style="color:#F42D2D">Unpaid</span>';

        $protocol = (!empty($_SERVER['HTTPS']) && 'off' !== strtolower($_SERVER['HTTPS'])?"https://":"http://");

        $siteServerName = $protocol.SITE_SERVER_NAME;

        $year = date('Y');

        return <<<ENDHTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en" style="background:#f3f3f3!important">
   <head>
      <link href="https://fonts.googleapis.com/css2?family=Maven+Pro:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
      <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
      <meta name="viewport" content="width=device-width">
      <title></title>
      <style>@media only screen{html{min-height:100%;background:#f3f3f3}}@media only screen and (max-width:904px){table.body img{width:auto;height:auto}table.body center{min-width:0!important}table.body .container{width:95%!important}table.body .columns{height:auto!important;-moz-box-sizing:border-box;-webkit-box-sizing:border-box;box-sizing:border-box;padding-left:104px!important;padding-right:104px!important}table.body .columns .columns{padding-left:0!important;padding-right:0!important}th.small-2{display:inline-block!important;width:16.66667%!important}th.small-6{display:inline-block!important;width:50%!important}th.small-10{display:inline-block!important;width:83.33333%!important}th.small-12{display:inline-block!important;width:100%!important}.columns th.small-12{display:block!important;width:100%!important}table.menu{width:100%!important}table.menu td,table.menu th{width:auto!important;display:inline-block!important}table.menu[align=center]{width:auto!important}}</style>
   </head>
   <body style="-moz-box-sizing:border-box;-ms-text-size-adjust:100%;-webkit-box-sizing:border-box;-webkit-text-size-adjust:100%;Margin:0;background:#f3f3f3!important;box-sizing:border-box;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0;min-width:100%;padding:0;text-align:left;width:100%!important">
      <span class="preheader" style="color:#f3f3f3;display:none!important;font-size:1px;line-height:1px;max-height:0;max-width:0;mso-hide:all!important;opacity:0;overflow:hidden;visibility:hidden"></span>
      <table class="body" style="Margin:0;background:#f3f3f3!important;border-collapse:collapse;border-spacing:0;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;height:100%;line-height:16px;margin:0;padding:0;text-align:left;vertical-align:top;width:100%">
         <tr style="padding:0;text-align:left;vertical-align:top">
            <td class="center" align="center" valign="top" style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;hyphens:auto;line-height:16px;margin:0;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">
               <center data-parsed style="min-width:800px;width:100%">
                  <table align="center" class="container float-center" style="Margin:0 auto;background:#fefefe;border-collapse:collapse;border-spacing:0;float:none;margin:0 auto;padding:0;text-align:center;vertical-align:top;width:800px">
                     <tbody>
                        <tr style="padding:0;text-align:left;vertical-align:top">
                           <td style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;hyphens:auto;line-height:16px;margin:0;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">
                              <table class="spacer" style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top;width:100%">
                                 <tbody>
                                    <tr style="padding:0;text-align:left;vertical-align:top">
                                       <td height="16px" style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:16px;font-weight:400;hyphens:auto;line-height:16px;margin:0;mso-line-height-rule:exactly;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">&#xA0;</td>
                                    </tr>
                                 </tbody>
                              </table>
                              <table class="row" style="border-collapse:collapse;border-spacing:0;display:table;padding:0;position:relative;text-align:left;vertical-align:top;width:100%">
                                 <tbody>
                                    <tr style="padding:0;text-align:left;vertical-align:top">
                                       <th class="small-12 large-12 columns first last" style="Margin:0 auto;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0 auto;padding:0;padding-bottom:16px;padding-left:104px;padding-right:104px;text-align:left;width:696px">
                                          <table style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top;width:100%">
                                             <tr style="padding:0;text-align:left;vertical-align:top">
                                                <th style="Margin:0;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0;padding:0;text-align:left">
                                                   <img src="{$siteServerName}/assets/img/logo.png" alt="logo" style="-ms-interpolation-mode:bicubic;clear:both;display:block;max-width:100%;outline:0;text-decoration:none;width:auto">
                                                   <table class="spacer" style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top;width:100%">
                                                      <tbody>
                                                         <tr style="padding:0;text-align:left;vertical-align:top">
                                                            <td height="32px" style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:32px;font-weight:400;hyphens:auto;line-height:32px;margin:0;mso-line-height-rule:exactly;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">&#xA0;</td>
                                                         </tr>
                                                      </tbody>
                                                   </table>
                                                   <h1 style="Margin:0;Margin-bottom:24px;color:inherit;font-family:'Maven Pro',sans-serif;font-size:64px;font-weight:700;line-height:1;margin:0;margin-bottom:24px;padding:0;text-align:left;word-wrap:normal">DNA VR - Gift&nbsp;voucher confirmation</h1>
                                                   <h4 style="Margin:0;Margin-bottom:24px;color:inherit;font-family:'Maven Pro',sans-serif;font-size:24px;font-weight:700;line-height:120%;margin:0;margin-bottom:24px;padding:0;text-align:left;word-wrap:normal">Please find all of your gift voucher information below.</h4>
                                                   <table class="spacer" style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top;width:100%">
                                                      <tbody>
                                                         <tr style="padding:0;text-align:left;vertical-align:top">
                                                            <td height="16px" style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:16px;font-weight:400;hyphens:auto;line-height:16px;margin:0;mso-line-height-rule:exactly;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">&#xA0;</td>
                                                         </tr>
                                                      </tbody>
                                                   </table>
                                                   <colums>
                                                      <table class="row" style="border-collapse:collapse;border-spacing:0;display:table;padding:0;position:relative;text-align:left;vertical-align:top;width:100%">
                                                         <tbody>
                                                            <tr style="padding:0;text-align:left;vertical-align:top">
                                                               <th class="small-6 large-4 columns first" style="Margin:0 auto;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0 auto;padding:0;padding-bottom:16px;padding-left:0!important;padding-right:0!important;text-align:left;width:34%">
                                                                  <table style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top;width:100%">
                                                                     <tr style="padding:0;text-align:left;vertical-align:top">
                                                                        <th style="Margin:0;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0;padding:0;text-align:left">
                                                                           <p class="gray" style="Margin:0;Margin-bottom:10px;color:#C4C4C4;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0;margin-bottom:10px;padding:0;text-align:left">Customer</p>
                                                                           <p style="Margin:0;Margin-bottom:10px;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0;margin-bottom:10px;padding:0;text-align:left">{$data['USER']['FULL_NAME']}</p>
                                                                        </th>
                                                                     </tr>
                                                                  </table>
                                                               </th>
                                                               <th class="small-6 large-6 columns" style="Margin:0 auto;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0 auto;padding:0;padding-bottom:16px;padding-left:0!important;padding-right:0!important;text-align:left;width:34%">
                                                                  <table style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top;width:100%">
                                                                     <tr style="padding:0;text-align:left;vertical-align:top">
                                                                        <th style="Margin:0;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0;padding:0;text-align:left">
                                                                           <p class="gray" style="Margin:0;Margin-bottom:10px;color:#C4C4C4;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0;margin-bottom:10px;padding:0;text-align:left">Email</p>
                                                                           <p style="Margin:0;Margin-bottom:10px;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0;margin-bottom:10px;padding:0;text-align:left">{$data['USER']['EMAIL']}</p>
                                                                        </th>
                                                                     </tr>
                                                                  </table>
                                                               </th>
                                                               <th class="small-6 large-2 columns last" style="Margin:0 auto;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0 auto;padding:0;padding-bottom:16px;padding-left:0!important;padding-right:0!important;text-align:left;width:32%">
                                                                  <table style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top;width:100%">
                                                                     <tr style="padding:0;text-align:left;vertical-align:top">
                                                                        <th style="Margin:0;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0;padding:0;text-align:left">
                                                                           <p class="gray" style="Margin:0;Margin-bottom:10px;color:#C4C4C4;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0;margin-bottom:10px;padding:0;text-align:left">Phone</p>
                                                                           <p style="Margin:0;Margin-bottom:10px;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0;margin-bottom:10px;padding:0;text-align:left">{$data['USER']['PHONE']}</p>
                                                                        </th>
                                                                     </tr>
                                                                  </table>
                                                               </th>
                                                            </tr>
                                                         </tbody>
                                                      </table>
                                                   </colums>
                                                   <colums>
                                                      <table class="row" style="border-collapse:collapse;border-spacing:0;display:table;padding:0;position:relative;text-align:left;vertical-align:top;width:100%">
                                                         <tbody>
                                                            <tr style="padding:0;text-align:left;vertical-align:top">
                                                               <th class="small-6 large-4 columns first" style="Margin:0 auto;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0 auto;padding:0;padding-bottom:16px;padding-left:0!important;padding-right:0!important;text-align:left;width:34%">
                                                                  <table style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top;width:100%">
                                                                     <tr style="padding:0;text-align:left;vertical-align:top">
                                                                        <th style="Margin:0;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0;padding:0;text-align:left">
                                                                           <p class="gray" style="Margin:0;Margin-bottom:10px;color:#C4C4C4;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0;margin-bottom:10px;padding:0;text-align:left">Trans Reference</p>
                                                                           <p style="Margin:0;Margin-bottom:10px;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0;margin-bottom:10px;padding:0;text-align:left">#{$data['VOUCHER_ID']}</p>
                                                                        </th>
                                                                     </tr>
                                                                  </table>
                                                               </th>
                                                               <th class="small-6 large-6 columns" style="Margin:0 auto;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0 auto;padding:0;padding-bottom:16px;padding-left:0!important;padding-right:0!important;text-align:left;width:34%">
                                                                  <table style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top;width:100%">
                                                                     <tr style="padding:0;text-align:left;vertical-align:top">
                                                                        <th style="Margin:0;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0;padding:0;text-align:left">
                                                                           <p class="gray" style="Margin:0;Margin-bottom:10px;color:#C4C4C4;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0;margin-bottom:10px;padding:0;text-align:left">Trans Date/Time</p>
                                                                           <p style="Margin:0;Margin-bottom:10px;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0;margin-bottom:10px;padding:0;text-align:left">{$data['VOUCHER_CREATE_DATE']}</p>
                                                                        </th>
                                                                     </tr>
                                                                  </table>
                                                               </th>
                                                               <th class="small-6 large-2 columns last" style="Margin:0 auto;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0 auto;padding:0;padding-bottom:16px;padding-left:0!important;padding-right:0!important;text-align:left;width:32%">
                                                                  <table style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top;width:100%">
                                                                     <tr style="padding:0;text-align:left;vertical-align:top">
                                                                        <th style="Margin:0;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0;padding:0;text-align:left">
                                                                           <p class="gray" style="Margin:0;Margin-bottom:10px;color:#C4C4C4;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0;margin-bottom:10px;padding:0;text-align:left">Payment Status</p>
                                                                           <p class="gray" style="Margin:0;Margin-bottom:10px;color:#C4C4C4;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0;margin-bottom:10px;padding:0;text-align:left">{$data['PAYMENT_STATUS']}</p>
                                                                        </th>
                                                                     </tr>
                                                                  </table>
                                                               </th>
                                                            </tr>
                                                         </tbody>
                                                      </table>
                                                   </colums>
                                                </th>
                                             </tr>
                                          </table>
                                       </th>
                                    </tr>
                                 </tbody>
                              </table>
                              <table class="row" style="border-collapse:collapse;border-spacing:0;display:table;padding:0;position:relative;text-align:left;vertical-align:top;width:100%">
                                 <tbody>
                                    <tr style="padding:0;text-align:left;vertical-align:top">
                                       <th class="blue-bg small-12 large-12 columns first last" style="Margin:0 auto;background:#F2F7FC;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0 auto;padding:0;padding-bottom:16px;padding-left:104px;padding-right:104px;text-align:left;width:696px">
                                          <table style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top;width:100%">
                                             <tr style="padding:0;text-align:left;vertical-align:top">
                                                <th style="Margin:0;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0;padding:0;text-align:left">
                                                   
                                                   <br>
                                                   <br>
                                                   <p style="text-align: center; font-size: 25px; font-weight: 600;">£{$data['VALUE']}</p>
                                                    <br/>
                                                    <p style="text-align: center; font-size: 20px; font-weight: 600;">{$data['CODE']}</p>
                                                    <br/>
                                                    <br/>
                                                   <table style="border-collapse:separate;border-spacing:10px;display:table;padding:0;position:relative;text-align:left;vertical-align:top;width:100%" class="row">
                                                      <tbody>
                                                      
                                                            <th class="small-12 large-5 columns" style="Margin:0 auto;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0 auto;padding:0;padding-bottom:0;padding-left:0!important;padding-right:0!important;text-align:left;width:41.66667%">
                                                               <table style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top;width:100%">
                                                                  <tr style="padding:0;text-align:left;vertical-align:top">
                                                                     <th style="Margin:0;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0;padding:0;text-align:left">
                                                                        <table class="button btn_black expand" style="Margin:0 0 16px 0;border-collapse:collapse;border-spacing:0;margin:0 0 16px 0;padding:0;text-align:left;vertical-align:top;width:100%!important">
                                                                           <tr style="padding:0;text-align:left;vertical-align:top">
                                                                              <td style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;hyphens:auto;line-height:16px;margin:0;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">
                                                                                 <table style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top;width:100%">
                                                                                    <tr style="padding:0;text-align:left;vertical-align:top">
                                                                                       <td style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;background:0 0!important;border:0;border-collapse:collapse!important;border-color:#1A1A1A;color:#fefefe;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;hyphens:auto;line-height:16px;margin:0;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">
                                                                                          <center data-parsed style="min-width:none!important;width:100%"><a href="{$siteServerName}/transactions/" align="center" class="float-center" style="Margin:0;background:0 0!important;border:2px solid #1A1A1A;border-color:#1A1A1A;border-radius:32px;box-sizing:border-box;color:#1A1A1A!important;display:inline-block;font-family:'Maven Pro',sans-serif;font-size:18px;font-weight:700;line-height:16px;margin:0;padding:17px 40px 17px 40px;padding-left:0;padding-right:0;text-align:center;text-decoration:none;width:100%">view transaction</a></center>
                                                                                       </td>
                                                                                    </tr>
                                                                                 </table>
                                                                              </td>
                                                                              <td class="expander" style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;hyphens:auto;line-height:16px;margin:0;padding:0!important;text-align:left;vertical-align:top;visibility:hidden;width:0;word-wrap:break-word"></td>
                                                                           </tr>
                                                                        </table>
                                                                     </th>
                                                                  </tr>
                                                               </table>
                                                            </th>
                                                            <th class="small-12 large-2 columns last" style="Margin:0 auto;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0 auto;padding:0;padding-bottom:0;padding-left:0!important;padding-right:0!important;text-align:left;width:16.66667%">
                                                               <table style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top;width:100%">
                                                                  <tr style="padding:0;text-align:left;vertical-align:top">
                                                                     <th style="Margin:0;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0;padding:0;text-align:left">
                                                                        <table class="button btn_black expand" style="Margin:0 0 16px 0;border-collapse:collapse;border-spacing:0;margin:0 0 16px 0;padding:0;text-align:left;vertical-align:top;width:100%!important">
                                                                           <tr style="padding:0;text-align:left;vertical-align:top">
                                                                              <td style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;hyphens:auto;line-height:16px;margin:0;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">
                                                                                 <table style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top;width:100%">
                                                                                    <tr style="padding:0;text-align:left;vertical-align:top">
                                                                                       <td style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;background:0 0!important;border:0;border-collapse:collapse!important;border-color:#1A1A1A;color:#fefefe;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;hyphens:auto;line-height:16px;margin:0;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">
                                                                                          <center data-parsed style="min-width:none!important;width:100%"><a href="https://www.dnavr.co.uk/faq/" align="center" class="float-center" style="Margin:0;background:0 0!important;border:2px solid #1A1A1A;border-color:#1A1A1A;border-radius:32px;box-sizing:border-box;color:#1A1A1A!important;display:inline-block;font-family:'Maven Pro',sans-serif;font-size:18px;font-weight:700;line-height:16px;margin:0;padding:17px 40px 17px 40px;padding-left:0;padding-right:0;text-align:center;text-decoration:none;width:100%">FAQ</a></center>
                                                                                       </td>
                                                                                    </tr>
                                                                                 </table>
                                                                              </td>
                                                                              <td class="expander" style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;hyphens:auto;line-height:16px;margin:0;padding:0!important;text-align:left;vertical-align:top;visibility:hidden;width:0;word-wrap:break-word"></td>
                                                                           </tr>
                                                                        </table>
                                                                     </th>
                                                                  </tr>
                                                               </table>
                                                            </th>
                                                         </tr>
                                                      </tbody>
                                                   </table>
                                                   <table class="row" style="border-collapse:collapse;border-spacing:0;display:table;padding:0;position:relative;text-align:left;vertical-align:top;width:100%">
                                                      <tbody>
                                                         <tr style="padding:0;text-align:left;vertical-align:top">
                                                         </tr>
                                                      </tbody>
                                                   </table>
                                                </th>
                                             </tr>
                                          </table>
                                       </th>
                                    </tr>
                                 </tbody>
                              </table>
                              <table class="spacer" style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top;width:100%">
                                 <tbody>
                                    <tr style="padding:0;text-align:left;vertical-align:top">
                                       <td height="30px" style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:30px;font-weight:400;hyphens:auto;line-height:30px;margin:0;mso-line-height-rule:exactly;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">&#xA0;</td>
                                    </tr>
                                 </tbody>
                              </table>
                              <center class="social" data-parsed style="min-width:800px;width:100%">
                                 <table align="center" class="menu float-center" style="Margin:0 auto;border-collapse:collapse;border-spacing:0;float:none;margin:0 auto;padding:0;text-align:center;vertical-align:top;width:auto!important">
                                    <tr style="padding:0;text-align:left;vertical-align:top">
                                       <td style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;hyphens:auto;line-height:16px;margin:0;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">
                                          <table style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top">
                                             <tr style="padding:0;text-align:left;vertical-align:top">
                                                <th class="menu-item float-center" style="Margin:0 auto;color:#1A1A1A;float:none;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0 auto;padding:10px;padding-right:10px;text-align:center">
                                                   <a href="undefined" style="Margin:0;color:#261DEB;font-family:'Maven Pro',sans-serif;font-weight:400;line-height:16px;margin:0;padding:0!important;text-align:left;text-decoration:underline">
                                                      <table class="button" style="Margin:0 0 16px 0;border-collapse:collapse;border-spacing:0;margin:0 0 16px 0;padding:0;text-align:left;vertical-align:top;width:auto">
                                                         <tr style="padding:0;text-align:left;vertical-align:top">
                                                            <td style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;hyphens:auto;line-height:16px;margin:0;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">
                                                               <table style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top">
                                                                  <tr style="padding:0;text-align:left;vertical-align:top">
                                                                     <td style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;background:0 0;border:2px solid transparent;border-collapse:collapse!important;border-color:transparent;color:#fefefe;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;hyphens:auto;line-height:16px;margin:0;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">
                                                   <a href="https://www.facebook.com/DNAVR/" style="Margin:0;border:0 solid transparent;border-radius:32px;color:#261DEB;display:inline-block;font-family:'Maven Pro',sans-serif;font-size:18px;font-weight:700;line-height:16px;margin:0;padding:0!important;text-align:left;text-decoration:none"><svg width="32" height="32" viewbox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M0 16C0 7.16344 7.16344 0 16 0C24.8366 0 32 7.16344 32 16C32 24.8366 24.8366 32 16 32C7.16344 32 0 24.8366 0 16Z" fill="#3B5998"/><path fill-rule="evenodd" clip-rule="evenodd" d="M17.6676 25.4077V16.7028H20.0706L20.389 13.7031H17.6676L17.6717 12.2017C17.6717 11.4193 17.7461 11.0001 18.8698 11.0001H20.372V8H17.9687C15.082 8 14.066 9.4552 14.066 11.9024V13.7034H12.2666V16.7031H14.066V25.4077H17.6676Z" fill="white"/></svg> </a></td></tr></table></td></tr></table></a>
                                                </th>
                                                <th class="menu-item float-center" style="Margin:0 auto;color:#1A1A1A;float:none;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0 auto;padding:10px;padding-right:10px;text-align:center">
                                                   <a href="undefined" style="Margin:0;color:#261DEB;font-family:'Maven Pro',sans-serif;font-weight:400;line-height:16px;margin:0;padding:0!important;text-align:left;text-decoration:underline">
                                                      <table class="button" style="Margin:0 0 16px 0;border-collapse:collapse;border-spacing:0;margin:0 0 16px 0;padding:0;text-align:left;vertical-align:top;width:auto">
                                                         <tr style="padding:0;text-align:left;vertical-align:top">
                                                            <td style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;hyphens:auto;line-height:16px;margin:0;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">
                                                               <table style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top">
                                                                  <tr style="padding:0;text-align:left;vertical-align:top">
                                                                     <td style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;background:0 0;border:2px solid transparent;border-collapse:collapse!important;border-color:transparent;color:#fefefe;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;hyphens:auto;line-height:16px;margin:0;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">
                                                   <a href="https://www.instagram.com/dna_vr/" style="Margin:0;border:0 solid transparent;border-radius:32px;color:#261DEB;display:inline-block;font-family:'Maven Pro',sans-serif;font-size:18px;font-weight:700;line-height:16px;margin:0;padding:0!important;text-align:left;text-decoration:none"><svg width="32" height="32" viewbox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><mask id="mask0" mask-type="alpha" maskunits="userSpaceOnUse" x="0" y="0" width="32" height="32"><path fill-rule="evenodd" clip-rule="evenodd" d="M0 16C0 7.16344 7.16344 0 16 0C24.8366 0 32 7.16344 32 16C32 24.8366 24.8366 32 16 32C7.16344 32 0 24.8366 0 16Z" fill="white"/></mask><g mask="url(#mask0)"><path fill-rule="evenodd" clip-rule="evenodd" d="M0 16C0 7.16344 7.16344 0 16 0C24.8366 0 32 7.16344 32 16C32 24.8366 24.8366 32 16 32C7.16344 32 0 24.8366 0 16Z" fill="#1A1A1A"/><path fill-rule="evenodd" clip-rule="evenodd" d="M16.0012 7.4668C13.6836 7.4668 13.3928 7.47693 12.4826 7.51835C11.5741 7.55995 10.954 7.70378 10.4114 7.9148C9.85018 8.13276 9.37408 8.42432 8.89977 8.89881C8.4251 9.37313 8.13354 9.84922 7.91487 10.4103C7.70331 10.9531 7.55931 11.5733 7.51842 12.4814C7.47771 13.3917 7.46704 13.6827 7.46704 16.0002C7.46704 18.3178 7.47735 18.6077 7.5186 19.5179C7.56038 20.4264 7.7042 21.0465 7.91505 21.5891C8.13318 22.1503 8.42474 22.6264 8.89923 23.1007C9.37337 23.5754 9.84947 23.8677 10.4104 24.0856C10.9533 24.2967 11.5736 24.4405 12.4818 24.4821C13.3921 24.5235 13.6828 24.5336 16.0001 24.5336C18.3178 24.5336 18.6078 24.5235 19.518 24.4821C20.4265 24.4405 21.0473 24.2967 21.5902 24.0856C22.1513 23.8677 22.6267 23.5754 23.1008 23.1007C23.5755 22.6264 23.867 22.1503 24.0857 21.5893C24.2955 21.0465 24.4395 20.4262 24.4822 19.5181C24.523 18.6079 24.5337 18.3178 24.5337 16.0002C24.5337 13.6827 24.523 13.3918 24.4822 12.4816C24.4395 11.5732 24.2955 10.9531 24.0857 10.4105C23.867 9.84922 23.5755 9.37313 23.1008 8.89881C22.6261 8.42414 22.1515 8.13258 21.5897 7.9148C21.0457 7.70378 20.4252 7.55995 19.5168 7.51835C18.6065 7.47693 18.3168 7.4668 15.9985 7.4668H16.0012ZM15.2363 9.00462C15.4635 9.00426 15.717 9.00462 16.0018 9.00462C18.2803 9.00462 18.5503 9.0128 19.4501 9.05369C20.2821 9.09173 20.7336 9.23076 21.0344 9.34756C21.4327 9.50223 21.7166 9.68712 22.0151 9.98579C22.3137 10.2845 22.4986 10.5689 22.6537 10.9671C22.7705 11.2676 22.9097 11.7191 22.9475 12.5511C22.9884 13.4507 22.9973 13.7209 22.9973 15.9983C22.9973 18.2757 22.9884 18.5459 22.9475 19.4454C22.9095 20.2774 22.7705 20.729 22.6537 21.0295C22.499 21.4277 22.3137 21.7112 22.0151 22.0097C21.7164 22.3084 21.4328 22.4933 21.0344 22.648C20.734 22.7653 20.2821 22.904 19.4501 22.942C18.5505 22.9829 18.2803 22.9918 16.0018 22.9918C13.7232 22.9918 13.4532 22.9829 12.5536 22.942C11.7216 22.9036 11.2701 22.7646 10.9691 22.6478C10.5709 22.4931 10.2864 22.3082 9.98774 22.0096C9.68907 21.7109 9.50418 21.4271 9.34916 21.0287C9.23236 20.7283 9.09315 20.2767 9.05529 19.4447C9.0144 18.5452 9.00622 18.2749 9.00622 15.9962C9.00622 13.7174 9.0144 13.4486 9.05529 12.549C9.09333 11.717 9.23236 11.2654 9.34916 10.9646C9.50383 10.5664 9.68907 10.282 9.98774 9.9833C10.2864 9.68463 10.5709 9.49974 10.9691 9.34471C11.2699 9.22738 11.7216 9.08871 12.5536 9.05049C13.3408 9.01493 13.6459 9.00426 15.2363 9.00249V9.00462ZM20.5565 10.4216C19.9911 10.4216 19.5325 10.8797 19.5325 11.4452C19.5325 12.0105 19.9911 12.4692 20.5565 12.4692C21.1218 12.4692 21.5805 12.0105 21.5805 11.4452C21.5805 10.8799 21.1218 10.4212 20.5565 10.4212V10.4216ZM16.0016 11.618C13.5814 11.618 11.6193 13.5802 11.6193 16.0003C11.6193 18.4204 13.5814 20.3817 16.0016 20.3817C18.4217 20.3817 20.3831 18.4204 20.3831 16.0003C20.3831 13.5802 18.4215 11.618 16.0014 11.618H16.0016ZM16.0019 13.1558C17.5727 13.1558 18.8463 14.4292 18.8463 16.0003C18.8463 17.5711 17.5727 18.8447 16.0019 18.8447C14.4308 18.8447 13.1574 17.5711 13.1574 16.0003C13.1574 14.4292 14.4308 13.1558 16.0019 13.1558Z" fill="white"/></g></svg> </a></td></tr></table></td></tr></table></a>
                                                </th>
                                                <th class="menu-item float-center" style="Margin:0 auto;color:#1A1A1A;float:none;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0 auto;padding:10px;padding-right:10px;text-align:center">
                                                   <a href="undefined" style="Margin:0;color:#261DEB;font-family:'Maven Pro',sans-serif;font-weight:400;line-height:16px;margin:0;padding:0!important;text-align:left;text-decoration:underline">
                                                      <table class="button" style="Margin:0 0 16px 0;border-collapse:collapse;border-spacing:0;margin:0 0 16px 0;padding:0;text-align:left;vertical-align:top;width:auto">
                                                         <tr style="padding:0;text-align:left;vertical-align:top">
                                                            <td style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;hyphens:auto;line-height:16px;margin:0;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">
                                                               <table style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top">
                                                                  <tr style="padding:0;text-align:left;vertical-align:top">
                                                                     <td style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;background:0 0;border:2px solid transparent;border-collapse:collapse!important;border-color:transparent;color:#fefefe;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;hyphens:auto;line-height:16px;margin:0;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">
                                                   <a href="https://twitter.com/dna_vr" style="Margin:0;border:0 solid transparent;border-radius:32px;color:#261DEB;display:inline-block;font-family:'Maven Pro',sans-serif;font-size:18px;font-weight:700;line-height:16px;margin:0;padding:0!important;text-align:left;text-decoration:none"><svg width="32" height="32" viewbox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M0 16C0 7.16344 7.16344 0 16 0C24.8366 0 32 7.16344 32 16C32 24.8366 24.8366 32 16 32C7.16344 32 0 24.8366 0 16Z" fill="#55ACEE"/><path fill-rule="evenodd" clip-rule="evenodd" d="M15.5207 13.0049L15.5543 13.5585L14.9947 13.4907C12.9578 13.2308 11.1783 12.3495 9.66744 10.8694L8.92879 10.135L8.73853 10.6773C8.33563 11.8863 8.59304 13.163 9.43241 14.0217C9.88008 14.4963 9.77935 14.5641 9.00713 14.2816C8.73853 14.1912 8.5035 14.1234 8.48112 14.1573C8.40278 14.2364 8.67138 15.2646 8.88402 15.6713C9.175 16.2363 9.76816 16.7899 10.4173 17.1176L10.9657 17.3774L10.3166 17.3887C9.68982 17.3887 9.66744 17.4 9.73459 17.6373C9.95842 18.3717 10.8426 19.1513 11.8274 19.4903L12.5213 19.7276L11.917 20.0891C11.0216 20.6089 9.96961 20.9026 8.91759 20.9252C8.41397 20.9365 7.99988 20.9817 7.99988 21.0156C7.99988 21.1286 9.36526 21.7613 10.1599 22.0099C12.5437 22.7443 15.3752 22.428 17.5016 21.1738C19.0125 20.2812 20.5234 18.5073 21.2284 16.7899C21.6089 15.8747 21.9895 14.2025 21.9895 13.4003C21.9895 12.8806 22.023 12.8128 22.6498 12.1913C23.0191 11.8298 23.366 11.4343 23.4332 11.3213C23.5451 11.1067 23.5339 11.1067 22.9631 11.2988C22.0118 11.6377 21.8775 11.5925 22.3476 11.0841C22.6945 10.7225 23.1086 10.0672 23.1086 9.87512C23.1086 9.84122 22.9408 9.89771 22.7505 9.9994C22.549 10.1124 22.1014 10.2819 21.7656 10.3836L21.1613 10.5756L20.6129 10.2028C20.3107 9.9994 19.8854 9.77343 19.6616 9.70563C19.0908 9.54745 18.2179 9.57005 17.7031 9.75083C16.3041 10.2593 15.42 11.5699 15.5207 13.0049Z" fill="white"/></svg> </a></td></tr></table></td></tr></table></a>
                                                </th>
                                                <th class="menu-item float-center" style="Margin:0 auto;color:#1A1A1A;float:none;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0 auto;padding:10px;padding-right:10px;text-align:center">
                                                   <a href="undefined" style="Margin:0;color:#261DEB;font-family:'Maven Pro',sans-serif;font-weight:400;line-height:16px;margin:0;padding:0!important;text-align:left;text-decoration:underline">
                                                      <table class="button" style="Margin:0 0 16px 0;border-collapse:collapse;border-spacing:0;margin:0 0 16px 0;padding:0;text-align:left;vertical-align:top;width:auto">
                                                         <tr style="padding:0;text-align:left;vertical-align:top">
                                                            <td style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;hyphens:auto;line-height:16px;margin:0;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">
                                                               <table style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top">
                                                                  <tr style="padding:0;text-align:left;vertical-align:top">
                                                                     <td style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;background:0 0;border:2px solid transparent;border-collapse:collapse!important;border-color:transparent;color:#fefefe;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;hyphens:auto;line-height:16px;margin:0;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">
                                                   <a href="https://www.youtube.com/channel/UCeQS2EzOg_p3P6Rm2D5WeVw" style="Margin:0;border:0 solid transparent;border-radius:32px;color:#261DEB;display:inline-block;font-family:'Maven Pro',sans-serif;font-size:18px;font-weight:700;line-height:16px;margin:0;padding:0!important;text-align:left;text-decoration:none"><svg width="32" height="32" viewbox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M0 16C0 7.16344 7.16344 0 16 0C24.8366 0 32 7.16344 32 16C32 24.8366 24.8366 32 16 32C7.16344 32 0 24.8366 0 16Z" fill="#FF0000"/><path fill-rule="evenodd" clip-rule="evenodd" d="M24.1771 12.0488C23.9808 11.2948 23.4025 10.701 22.6681 10.4995C21.3373 10.1333 16.0004 10.1333 16.0004 10.1333C16.0004 10.1333 10.6635 10.1333 9.33254 10.4995C8.59819 10.701 8.01987 11.2948 7.8236 12.0488C7.46704 13.4153 7.46704 16.2666 7.46704 16.2666C7.46704 16.2666 7.46704 19.1178 7.8236 20.4845C8.01987 21.2385 8.59819 21.8322 9.33254 22.0338C10.6635 22.4 16.0004 22.4 16.0004 22.4C16.0004 22.4 21.3373 22.4 22.6681 22.0338C23.4025 21.8322 23.9808 21.2385 24.1771 20.4845C24.5337 19.1178 24.5337 16.2666 24.5337 16.2666C24.5337 16.2666 24.5337 13.4153 24.1771 12.0488Z" fill="white"/><path fill-rule="evenodd" clip-rule="evenodd" d="M14.4004 19.2V13.8667L18.6671 16.5335L14.4004 19.2Z" fill="#FF0000"/></svg> </a></td></tr></table></td></tr></table></a>
                                                </th>
                                                <th class="menu-item float-center" style="Margin:0 auto;color:#1A1A1A;float:none;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0 auto;padding:10px;padding-right:10px;text-align:center">
                                                   <a href="undefined" style="Margin:0;color:#261DEB;font-family:'Maven Pro',sans-serif;font-weight:400;line-height:16px;margin:0;padding:0!important;text-align:left;text-decoration:underline">
                                                      <table class="button" style="Margin:0 0 16px 0;border-collapse:collapse;border-spacing:0;margin:0 0 16px 0;padding:0;text-align:left;vertical-align:top;width:auto">
                                                         <tr style="padding:0;text-align:left;vertical-align:top">
                                                            <td style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;border-collapse:collapse!important;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;hyphens:auto;line-height:16px;margin:0;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">
                                                               <table style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top">
                                                                  <tr style="padding:0;text-align:left;vertical-align:top">
                                                                     <td style="-moz-hyphens:auto;-webkit-hyphens:auto;Margin:0;background:0 0;border:2px solid transparent;border-collapse:collapse!important;border-color:transparent;color:#fefefe;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;hyphens:auto;line-height:16px;margin:0;padding:0;text-align:left;vertical-align:top;word-wrap:break-word">
                                                   <a href="https://www.linkedin.com/company/dna-vr/" style="Margin:0;border:0 solid transparent;border-radius:32px;color:#261DEB;display:inline-block;font-family:'Maven Pro',sans-serif;font-size:18px;font-weight:700;line-height:16px;margin:0;padding:0!important;text-align:left;text-decoration:none"><svg width="32" height="32" viewbox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M0 16C0 7.16344 7.16344 0 16 0C24.8366 0 32 7.16344 32 16C32 24.8366 24.8366 32 16 32C7.16344 32 0 24.8366 0 16Z" fill="#0077B5"/><path fill-rule="evenodd" clip-rule="evenodd" d="M11.5459 9.8818C11.5223 8.8136 10.7584 8 9.51803 8C8.27761 8 7.46667 8.8136 7.46667 9.8818C7.46667 10.9279 8.25365 11.7649 9.47096 11.7649H9.49413C10.7584 11.7649 11.5459 10.9279 11.5459 9.8818ZM11.3071 13.2519H7.68113V24.1464H11.3071V13.2519ZM20.2086 12.9961C22.5947 12.9961 24.3835 14.5535 24.3835 17.8998L24.3833 24.1464H20.7575V18.3178C20.7575 16.8538 20.2328 15.8548 18.9202 15.8548C17.9185 15.8548 17.3218 16.5283 17.0597 17.1788C16.9639 17.4119 16.9403 17.7367 16.9403 18.0623V24.1466H13.3139C13.3139 24.1466 13.3617 14.2745 13.3139 13.2522H16.9403V14.7953C17.4216 14.0535 18.2835 12.9961 20.2086 12.9961Z" fill="white"/></svg> </a></td></tr></table></td></tr></table></a>
                                                </th>
                                             </tr>
                                          </table>
                                       </td>
                                    </tr>
                                 </table>
                                 <table align="center" class="row float-center" style="Margin:0 auto;border-collapse:collapse;border-spacing:0;display:table;float:none;margin:0 auto;padding:0;position:relative;text-align:center;vertical-align:top;width:100%">
                                    <tbody>
                                       <tr style="padding:0;text-align:left;vertical-align:top">
                                          <th class="small-12 large-12 columns first last" style="Margin:0 auto;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0 auto;padding:0;padding-bottom:16px;padding-left:104px;padding-right:104px;text-align:left;width:696px">
                                             <table style="border-collapse:collapse;border-spacing:0;padding:0;text-align:left;vertical-align:top;width:100%">
                                                <tr style="padding:0;text-align:left;vertical-align:top">
                                                   <th style="Margin:0;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0;padding:0;text-align:left">
                                                      <p class="gray" style="Margin:0;Margin-bottom:10px;color:#C4C4C4;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0;margin-bottom:10px;padding:0;text-align:center">Any other legal information</p>
                                                      <p class="gray" style="Margin:0;Margin-bottom:10px;color:#C4C4C4;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0;margin-bottom:10px;padding:0;text-align:center">© {$year} DNA VR</p>
                                                   </th>
                                                   <th class="expander" style="Margin:0;color:#1A1A1A;font-family:'Maven Pro',sans-serif;font-size:14px;font-weight:400;line-height:16px;margin:0;padding:0!important;text-align:left;visibility:hidden;width:0"></th>
                                                </tr>
                                             </table>
                                          </th>
                                       </tr>
                                    </tbody>
                                 </table>
                              </center>
                           </td>
                        </tr>
                     </tbody>
                  </table>
               </center>
            </td>
         </tr>
      </table>
      <!-- prevent Gmail on iOS font size manipulation -->
      <div style="display:none;white-space:nowrap;font:15px courier;line-height:0">&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;</div>
   </body>
</html>
ENDHTML;
    }

    function sendNewVoucher($id){

        if($data = $this->getData($id)){

            if ($dompdf = $this->createPdf($id)) {

                $tmpFile = tmpfile();

                $metadata = stream_get_meta_data($tmpFile);

                file_put_contents($metadata['uri'], $dompdf->output());

                $metadata = stream_get_meta_data($tmpFile);

                $arFile = \CFile::MakeFileArray($metadata['uri']);

                $arFile['name'] = 'Voucher.pdf';

                $arFile["MODULE_ID"] = "itech.dnavr";

                $arFiles = [
                    \CFile::SaveFile($arFile, "itech.dnavr")
                ];

                $html = $this->getTemplateNew($data);

                return \CEvent::Send("ITECH_NEW_VOUCHER", ['s1'], [
                        'EMAIL' => $data['USER']['EMAIL'],
                        'HTML' => $html
                    ],'Y', '', $arFiles) > 0 ?? false;
            }
        }

        return false;
    }
}