<?php
AddEventHandler("search", "BeforeIndex", "ModifySearchContentWithTags");

function ModifySearchContentWithTags($arFields)
{
    if ($arFields["MODULE_ID"] == "iblock") {
        $elementId = $arFields["ITEM_ID"];
        $element = CIBlockElement::GetByID($elementId)->Fetch();

        if (!$element) return $arFields;


        $tagsProperty = CIBlockElement::GetProperty(
            $element["IBLOCK_ID"],
            $element["ID"],
            array(),
            array("CODE" => "SEARCH_TAGS")
        );

        $tagWords = [];
        while ($tag = $tagsProperty->Fetch()) {
            if (!empty($tag["VALUE"])) {
                $tagElement = CIBlockElement::GetByID($tag["VALUE"])->Fetch();
                if ($tagElement && !empty($tagElement["PREVIEW_TEXT"]) || $tagElement && !empty($tagElement["DETAIL_TEXT"])) {
                    $fullText = $tagElement["PREVIEW_TEXT"] . " " . $tagElement["DETAIL_TEXT"];
                    $words = array_map('trim', explode(",", $fullText));
                    $tagWords = array_merge($tagWords, $words);
                }
            }
        }

        if (!empty($tagWords)) {
            $arFields["BODY"] .= " " . implode(" ", $tagWords);
        }
    }
    file_put_contents(
        $_SERVER["DOCUMENT_ROOT"] . "/search_debug.log",
        date("Y-m-d H:i:s") . "\n" .
        "Содержимое:\n" . print_r($arFields, true) . "\n" .
        "Element:\n" . print_r($element, true) . "\n" .
        "tagwords:\n" . print_r($tagWords, true) . "\n" .
        "----------------------------------------\n",

    );

    return $arFields;
}

AddEventHandler("iblock", "OnAfterIBlockElementAdd", "ForceReindexForTaggedElements");
AddEventHandler("iblock", "OnAfterIBlockElementUpdate", "ForceReindexForTaggedElements");

function ForceReindexForTaggedElements(&$arFields)
{
    $TAGS_IBLOCK_ID = 70;

    if ($arFields["IBLOCK_ID"] != $TAGS_IBLOCK_ID) return;


    $rsElements = CIBlockElement::GetList(
        array(),
        array(
            "PROPERTY_SEARCH_TAGS" => $arFields["ID"],
            "ACTIVE" => "Y"
        ),
        false,
        false,
        array("ID", "IBLOCK_ID", "IBLOCK_TYPE", "NAME", "PREVIEW_TEXT", "DETAIL_TEXT", "TIMESTAMP_X", "PERMISSIONS", "SITE_ID", "MODULE_ID")
    );

    while ($element = $rsElements->Fetch()) {

        $fullElement = CIBlockElement::GetByID($element["ID"])->Fetch();

        $searchData = array(
            "TITLE" => $fullElement["NAME"],
            "BODY" => strip_tags($fullElement["PREVIEW_TEXT"] . " " . $fullElement["DETAIL_TEXT"]),
            "ITEM_ID" => $fullElement["ID"],
            "PARAM1" => $fullElement["IBLOCK_TYPE_ID"],
            "PARAM2" => $fullElement["IBLOCK_ID"],
            "SITE_ID" => $fullElement["LID"],
        );


        $tagsProperty = CIBlockElement::GetProperty(
            $fullElement["IBLOCK_ID"],
            $fullElement["ID"],
            array(),
            array("CODE" => "SEARCH_TAGS")
        );

        $tagContents = [];
        while ($tag = $tagsProperty->Fetch()) {
            if (!empty($tag["VALUE"])) {
                $tagElement = CIBlockElement::GetByID($tag["VALUE"])->Fetch();
                if ($tagElement && !empty($tagElement["PREVIEW_TEXT"])) {
                    $tagContents[] = $tagElement["PREVIEW_TEXT"];
                } else if ($tagElement && !empty($tagElement["DETAIL_TEXT"])) {
                    $tagContents[] = $tagElement["DETAIL_TEXT"];
                }
            }
        }

        if (!empty($tagContents)) {
            $searchData["BODY"] .= " " . implode(" ", $tagContents);
        }


        CSearch::Index("iblock", $fullElement["ID"], $searchData, true);



        file_put_contents(
            $_SERVER["DOCUMENT_ROOT"] . "/search_reindex.log",
            date("Y-m-d H:i:s") . " | Элемент ID: " . $fullElement["ID"] . "\n" .
            "Количество тегов: " . count($tagContents) . "\n" .
            "Длина BODY: " . strlen($searchData["BODY"]) . "\n" .
            "Содержимое searchData:\n" . print_r($searchData, true) . "\n" .
            "Содержимое fullElementID:\n" . print_r($fullElement, true) . "\n" .
            "----------------------------------------\n",

        );
    }
}


