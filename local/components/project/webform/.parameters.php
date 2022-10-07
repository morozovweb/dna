<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

$arComponentParameters = [
    "PARAMETERS" => [
        "WEBFORM_SID" => [
            "PARENT" => "BASE",
            "NAME"   => "SID веб формы",
            "TYPE"   => "STRING",
        ],
        "FORM_SECT" => [
            "PARENT" => "BASE",
            "NAME"   => "Модификатор веб формы",
            "TYPE"   => "STRING",
        ],
        "REQUIRED" => [
            "PARENT" => "BASE",
            "NAME"   => "Обязательные поля",
            "TYPE"   => "STRING",
        ],
        "REQUIRED_OR" => [
            "PARENT" => "BASE",
            "NAME"   => "Обязательные поля (или)",
            "TYPE"   => "STRING",
        ],
        "SUCCESS_TITLE" => [
            "PARENT" => "BASE",
            "NAME"   => "Успешное сообщение. Заголовок",
            "TYPE"   => "STRING",
        ],
        "SUCCESS_TEXT" => [
            "PARENT" => "BASE",
            "NAME"   => "Успешное сообщение. Текст",
            "TYPE"   => "STRING",
        ],
        "TITLE" => [
            "PARENT" => "BASE",
            "NAME"   => "Заголовок формы",
            "TYPE"   => "STRING",
        ],
        "DESCRIPTION" => [
            "PARENT" => "BASE",
            "NAME"   => "Описание формы",
            "TYPE"   => "STRING",
        ],
        "NOT_AGREE" => [
            "PARENT" => "BASE",
            "NAME"   => "Не проверять согласие на обработку персональных данных (Y)",
            "TYPE"   => "STRING",
        ],
    ],
];
